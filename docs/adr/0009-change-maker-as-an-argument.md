# 9. The ChangeMaker passed to select(), not stored in the machine

## Status

Accepted.

## Context

`ChangeMaker` is a domain service, and `VendingMachine::select()` needs one. It can be a field of the
aggregate, given at `install()`, or an argument of `select()`.

## Decision

`select(Selector $selector, ChangeMaker $changeMaker)` takes it as an argument. The aggregate holds
only data: inventory, wallet and tray. The `SelectProduct` handler is the only caller, and it
receives the `ChangeMaker` from the container, configured once.

## Consequences

- Persisting and loading the aggregate needs nothing else. Nothing has to be injected again after a
  load, which matters because persistence builds the object from stored data.
- Tests pick the strategy per call, and Infrastructure can decorate it without the aggregate
  noticing (ADR 14).
- Trade-off: the signature of `select()` is longer, and the strategy is not fixed per machine. The
  argument stays inside the Application layer, since only one handler passes it.

## Alternatives considered

- **Stored at `install()`.** The signature reads better and the strategy belongs to the machine, but
  the aggregate then carries a service, and every load must inject it again, through a
  `reconstitute()` factory or a Doctrine `postLoad` listener (Doctrine skips the constructor).
- **A static or global strategy.** Hidden dependency, hard to test.
