# 11. One customer per machine, optimistic locking with a retried command

## Status

Accepted. Built and merged: the version in `DbalVendingMachineRepository` and
`MongoVendingMachineRepository`, and `RetryOnConcurrentUpdateMiddleware`.

## Context

A physical machine serves one customer at a time, so the credit is state of the machine, not of a
customer session. Concurrent requests still happen: a double click, a client retry, a technician
servicing during a sale. Two requests that load the same machine and save it would lose one update,
or vend twice on the same credit.

## Decision

- One customer per machine. The API is the machine's own panel: no customer sessions, no customer
  ids.
- The machine is saved with a `version` that the save checks: the update succeeds only if the stored
  version is the one that was loaded, and it increments it (`UPDATE ... WHERE id = ? AND version = ?`).
  The first save is an `INSERT`, and a duplicate key there counts as a conflict too.
- The repository keeps the version it loaded in a `WeakMap` keyed by the aggregate object, so
  `VendingMachine` carries no version and knows nothing about persistence. The entry goes away with
  the object, and `save()` moves it to the new version, so a machine saved twice in one use case
  stays consistent.
- A failed check throws `ConcurrentUpdateException`, in `Shared/Application/Persistence`. It is not
  a `DomainException`: no business rule refused anything, another save got in first, and the same
  command on fresh data may succeed. It is also not in Domain, which does not know about
  concurrency.
- `RetryOnConcurrentUpdateMiddleware` on `command.bus` catches the `HandlerFailedException` that
  wraps a `ConcurrentUpdateException` and sends the command to the handler again, up to 3 more times
  (`$maxRetries`, set in `services.yaml`), with no backoff: a conflict on one machine clears as soon
  as the other save has committed. A handler loads the machine inside `__invoke()`, so every
  attempt decides again on fresh state: a second purchase made on the same credit fails on credit,
  so there is no double vend.
- Each retry is logged at notice and, when the retries run out, the last attempt's exception is
  rethrown and `LoggingCommandMiddleware` logs it at error (ADR 14).
- The middleware must sit right before `handle_message`: every attempt goes to the same next
  middleware, because the first one uses up the rest of the stack. That is why `command.bus` lists
  its middleware by hand with `default_middleware: false`. That setting is under review: with it, a
  command routed to a transport would run synchronously without a warning. Planned polish: check
  whether the retry works with Symfony's default middleware, so the setting can go.
- There is no `RetryableException` marker interface. Only one failure is retried today, and the
  middleware names it. A marker earns its place when a second failure is worth retrying.

## Consequences

- No locks are held and the domain code knows nothing about concurrency: the version belongs to the
  repository and the middleware.
- Correctness does not depend on a customer behaving: a double submission turns into one sale and a
  refusal.
- Trade-off: under heavy contention retries waste work. Conflicts on one physical machine are rare,
  so this is the right cost.
- Trade-off: a retried command must be safe to run again after a failed attempt. Commands load fresh
  state and the aggregate refuses before changing anything (ADR 6), so they are.

## Alternatives considered

- **Pessimistic locks (`SELECT ... FOR UPDATE`).** Hold a row for the length of the use case, for a
  conflict that is rare.
- **Conditional updates written in the query** (`UPDATE ... WHERE credit >= price`). They move
  business rules out of the domain.
- **Customer sessions.** Model a customer the machine does not have, and still need a lock for the
  technician.
