# 7. Output through the tray and strict CQRS

## Status

Accepted.

## Context

A purchase produces output: the product and the change. A return produces the inserted coins. The
usual shortcut is to let the command return what it dispensed. The project follows CQRS strictly, so
the question is where that output goes.

## Decision

- Every command returns nothing. What a command produces is state that a query reads.
- The output goes into the `Tray` of the aggregate, as on a real machine: products and coins the
  machine has dispensed and the customer has not collected yet.
- The `GetTrayContents` query reads the tray and the `CollectTray` command empties it. The console
  runs a sequence, then reads the tray, prints it and collects it.
- There is no `Pickup` object returned by `Tray::collect()`. What leaves the tray leaves the model:
  the query reads it first, so a value returned by the command had no consumer. (`Wallet::charge()`
  and `returnCredit()` do return a `Payout`, because those coins have a destination inside the
  model: the tray.)

## Consequences

- Commands stay pure, so they could travel through a queue later without changing their contract.
- The tray models real state (uncollected items), and the machine can be asked what is waiting.
- Trade-off: the tray is state beyond what the statement lists.
- Trade-off: reading and emptying the tray are two steps, not one atomic operation. That is fine
  with one customer per machine (ADR 11); if it mattered, `CollectTray` would carry the version it
  read and rely on optimistic locking, not return data.

## Alternatives considered

- **Commands return a `Dispensed` result.** Simpler, with one call and no extra state, and it was
  weighed. It gives up the rule that commands return nothing, and with it the ability to run a
  command asynchronously.
- **A `Pickup` returned by `Tray::collect()`.** A first version had it, and it was dropped for the
  reason above.
- **Events carrying the output to the caller.** Heavier than the problem needs; business events are
  planned for reporting (ADR 14), not for returning values.
