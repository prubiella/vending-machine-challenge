# 6. The VendingMachine aggregate

## Status

Accepted.

## Context

A sale touches three things at once: the stock of the product, the customer's credit with the coin
stock, and the tray. If any step fails, none may have happened: a refused sale keeps the credit and
leaves the machine as it was. The rules that span those parts need one owner.

## Decision

`VendingMachine` is the aggregate and the consistency boundary. It holds three immutable parts:
`Inventory` (slots with product and units), `Wallet` (credit plus coin stock) and `Tray` (what was
dispensed and not collected).

- The parts are immutable: every operation returns a new one. The aggregate is the only mutable
  object and replaces whole parts.
- Every method computes everything first and applies last. `select()` reads the product, asks the
  inventory to dispense, asks the wallet to charge and make change, builds the new tray, and only
  then assigns all three through `replaceParts()`. Any exception happens before the first
  assignment, so a refusal changes nothing.
- The arithmetic and rules of a part live in the part (`Wallet::charge`, `Inventory::dispense`). The
  aggregate owns the order and the atomicity across parts. Handlers only load, call and save.
- The aggregate is saved as one unit (ADR 12).

There is no `SaleService`. A service that orchestrates from outside would need setters on the
machine, so anyone could replace the inventory without charging, or empty the wallet without
filling the tray, and the rules would depend on every caller using the service correctly. That is
an anemic model: data in classes, rules elsewhere. Domain services are kept for logic that belongs
to no single entity, such as change making (ADR 8, passed to `select()` as ADR 9 explains).

## Consequences

- Atomicity does not depend on a database transaction or on rollback code: the failed call never
  assigned anything.
- The aggregate is thin (a few lines per method) but not passive: order and atomicity are its job.
- Trade-off: parts are copied on every change. The machine has a handful of slots and coin kinds, so
  the cost is negligible.
- Trade-off: `inventory()`, `wallet()` and `tray()` are public so queries can read them. The parts
  are immutable, so reading cannot break an invariant.

## Alternatives considered

- **An anemic `VendingMachine` plus a `SaleService`.** Rejected, as above.
- **Mutable parts changed in place.** A failure in the middle of `select()` would leave the machine
  half changed, and the code would need compensation or a transaction to undo it.
- **One big class with the arithmetic inline.** Fewer files, but the coin, stock and tray rules would
  become one tangle that is hard to test apart.
