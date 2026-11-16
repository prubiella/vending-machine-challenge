# 5. Domain exceptions

## Status

Accepted.

## Context

The domain refuses operations for business reasons: sold out, not enough credit, no exact change, an
unknown selector. Those refusals must be told apart from programming errors, must be mapped to a
client error (4xx) in one place, and must read consistently.

## Decision

- Every domain exception is a `final` class named after what went wrong and ending in `Exception`
  (`InsufficientMoneyException`, `SoldOutException`), built through a named constructor
  (`InsufficientMoneyException::toSubtract(...)`) so messages stay consistent.
- All of them extend `Shared\Domain\DomainException`, an abstract class that extends
  `\RuntimeException`. It is deliberately not PHP's `\DomainException`.
- Each context keeps its exceptions next to the concept they belong to (`Inventory/SoldOutException`).

## Consequences

- One `catch (DomainException)` is enough to turn every business refusal into a 4xx response or a
  console message, and the HTTP listener maps each concrete exception to a status.
- PHP's `\DomainException` is a `\LogicException`, which means a bug fixed by changing code. A sold
  out product or a short credit happens with correct code, so it is a runtime error. Extending
  `\RuntimeException` keeps a `catch (\LogicException)` for programming errors, such as a message
  with no handler, from swallowing business refusals, and avoids two classes that differ only by a
  backslash.
- Trade-off: one class per failure means many small files. They are cheap, and the name is the
  documentation.
- Trade-off: the base is shared by every context, so it is part of the shared kernel and must stay
  empty.

## Alternatives considered

- **PHP's `\DomainException`.** Wrong semantics, as above.
- **SPL subtypes (`\UnderflowException`, `\OutOfBoundsException`).** PHP has single inheritance, so a
  common base of our own could not coexist with them, and the meaning lives in our names anyway.
- **Naming the class after the problem only (`SoldOut`).** Hides that it is an exception and breaks
  the PHP and Symfony naming convention.
- **Result objects instead of exceptions.** Explicit, but every call site must unwrap them, and the
  aggregate's "refuse and change nothing" is simpler with a throw (ADR 6).
