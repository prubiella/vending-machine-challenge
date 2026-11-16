# 11. One customer per machine, optimistic locking with a retried command

## Status

Accepted. Implementation in progress, with the persistence work: the version column and the retry
are not on `master` yet.

## Context

A physical machine serves one customer at a time, so the credit is state of the machine, not of a
customer session. Concurrent requests still happen: a double click, a client retry, a technician
servicing during a sale. Two requests that load the same machine and save it would lose one update,
or vend twice on the same credit.

## Decision

- One customer per machine. The API is the machine's own panel: no customer sessions, no customer
  ids.
- The machine is saved with a `version` that the save checks: the update succeeds only if the stored
  version is the one that was loaded, and it increments it.
- On a version conflict a command bus middleware reloads and runs the command again. The domain
  decides again on fresh state: a second purchase made on the same credit fails on credit, so there
  is no double vend.
- Retries are limited; when they run out the error is logged (ADR 14) and reported.

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
