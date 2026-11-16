# Architecture decision records

The [design document](../design.md) shows how these decisions fit together.

One short record per decision: context, the decision, its consequences (trade-offs included) and the
alternatives considered. All are accepted and built, except where a record says it was superseded.

1. [Bounded contexts first, a small Shared kernel](0001-bounded-contexts-and-shared-kernel.md): `src/<Context>/<Layer>/`, `Money` shared, `Coin` in the context, deptrac enforcing it.
2. [Money as integer cents](0002-money-as-integer-cents.md): no floats anywhere in the domain.
3. [Coin as an enum, not a class](0003-coin-as-an-enum.md): a closed set, and when it would become a value object.
4. [Selector as a value object, not an enum](0004-selector-as-a-value-object.md): products are data, not code.
5. [Domain exceptions](0005-domain-exceptions.md): named after the failure, one shared base that is a `\RuntimeException`.
6. [The VendingMachine aggregate](0006-vending-machine-aggregate.md): immutable parts, compute then apply, no `SaleService`.
7. [Output through the tray and strict CQRS](0007-tray-and-strict-cqrs.md): commands return nothing, a query reads the tray, a command empties it.
8. [Change making](0008-change-making.md): bounded search behind `ChangeMaker`, not greedy; change only in 0.05, 0.10 and 0.25.
9. [The ChangeMaker passed to select()](0009-change-maker-as-an-argument.md): the aggregate holds data, not services.
10. [SERVICE sets the counts and returns pending credit first](0010-service-sets-stock-and-returns-credit.md): set, not add.
11. [One customer per machine, optimistic locking](0011-one-customer-and-optimistic-locking.md): a version kept by the repository and checked on save, the command retried.
12. [Persistence: MySQL through DBAL](0012-persistence-mysql-with-dbal.md): a hand-written mapper and JSON columns, MongoDB parked. Superseded by 16.
13. [Console behaviour](0013-console-behaviour.md): refusals never stop the machine, exit code 0, strict coin notation.
14. [Logging](0014-logging.md): a command bus middleware and decorators in Infrastructure, business facts as events later.
15. [HTTPS with Traefik](0015-https-with-traefik.md): TLS terminated at Traefik, re-encrypted to nginx with a local CA.
16. [The machine stored in MongoDB](0016-machine-stored-in-mongodb.md): one document with a version, the same mapper, the DBAL adapter kept, the outbox planned.
17. [UUID identities](0017-uuid-identities.md): UUID v7, a shared `Uuid` base, generation behind a port, the single machine's id from configuration.

A new record takes the next number and is never renumbered. A decision that changes gets a new
record that supersedes the old one, and the old status becomes "Superseded by N".
