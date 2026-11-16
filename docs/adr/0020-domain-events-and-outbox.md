# 20. Domain events, an outbox in the machine's document and a relay to a history

## Status

Accepted. Built in steps, one pull request each: the events recorded by the aggregate; the outbox
written by both repositories; the relay to the history in MySQL and its `outbox-relay` worker; the
low stock alert. All of them are built.

## Context

The machine leaves no record of what happened to it, only log lines (ADR 14). Reports such as sales
per product and day, the change given and who serviced the machine need facts that are stored and
can be queried. The machine is one MongoDB document written atomically (ADR 16), its ids come from a
port (ADR 17), the technician who serviced it travels in `ServiceMachineCommand` only to reach
the command bus log (ADR 18), and what a service collected from the cash box is only logged, by
`CashBoxCollectionLog` (ADR 19). The domain may use no framework, no clock and no generator.

## Decision

- **The aggregate records what happened.** `VendingMachine` records `ProductSold` (the product and
  the change given), `CreditReturned` (the coins, only when there were any, whether the customer
  asked or a service returned them) and `MachineServiced` (the technician's id, the units and the
  coins set, and the coins collected from the cash box). `releaseEvents()` hands them over oldest
  first and forgets them. A refused operation records nothing, since it changes nothing. Inserting
  a coin and collecting the tray record nothing: no report needs them.
- **The technician is a `TechnicianId` of `VendingMachine`.** It extends the shared `Uuid`, as the
  `TechnicianId` of `Maintenance` does, so the two contexts share no class and a technician's id
  never stands where a machine's is expected.
- **The default machine records no service.** `VendingMachine::installStocked()` builds the machine
  of the configuration with its stock and no event, since no technician serviced it.
- **The repository gives each event its envelope.** When it saves the machine, the repository takes
  its events and gives each an id (a UUID v7 from `UuidGenerator`) and the time it happened (a PSR-20
  clock). The command is synchronous, so the save follows the fact by milliseconds, and a retried
  command records its events again on the machine it reloads: the events saved are those of the
  attempt that committed. The domain gets no clock and no generator in its methods.
- **The outbox is part of the machine's write.** MongoDB pushes the events into an `outbox` array of
  the machine's document in the same `updateOne` that sets its state, with the version still in the
  filter; the DBAL adapter writes them to an outbox table in the same transaction as its row. A
  crash keeps both or neither.
- **A relay copies them to a history in MySQL.** `vending:outbox:relay` reads the pending events,
  inserts each into `machine_history` keyed by the event's id, ignoring one already there, and then
  removes the relayed ids from the outbox. It makes one pass by default; `--watch` repeats it with a
  short pause and stops cleanly on SIGTERM, and the `outbox-relay` compose service runs it that way.
  The tests call the relay directly.
- **A low stock alert rides on the same events.** A sale that leaves a slot at or under a threshold,
  coming from above it, records `StockRunningLow`; the relay hands it to a notifier port, whose
  first adapter logs a warning, before it writes the history.

## Consequences

- Delivery is at least once: a crash between the history insert and the removal from the outbox
  relays the event again, and the history ignores it by its id. The consumer is idempotent, not the
  delivery. A low stock warning may repeat after such a crash, and is never lost.
- The machine's write grows by the events it carries, and the outbox empties as the relay runs.
- `vending:reset` deletes the document and with it the events not yet relayed.
- The history is eventually consistent: a report may lag the machine by one pass of the relay.
- Who serviced the machine, and what they collected from the cash box, become a stored fact,
  `MachineServiced`, instead of log lines.

## Alternatives considered

- **Publishing after the commit**, from the handler or a listener: a crash between the save and the
  publish loses the event, and a publish that fails after the commit cannot be undone. The outbox is
  written with the state, so nothing is lost.
- **A message broker now.** One consumer (the history) and one notifier do not need one, and a
  broker is another service for every run of the stack. The next step, when there are several
  consumers or their retries matter: the relay publishes to a Messenger transport (Doctrine on
  MySQL today, the official MongoDB bridge once Symfony 8.2 is stable, or RabbitMQ when there are
  several consumers), and the history and the low stock alert become consumers with their own
  retries. The outbox does not change.
- **Ids and time from ports passed to each operation.** Every method of the aggregate would take a
  generator and a clock for a value the domain never reads.
- **An outbox collection or table apart from the machine.** In MongoDB it would need a
  multi-document transaction, and a replica set, to be atomic with the machine.
