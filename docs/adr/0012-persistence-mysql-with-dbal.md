# 12. Persistence: MySQL through DBAL with a hand-written mapper

## Status

Accepted. Implementation in progress: today the repository is in memory
(`InMemoryVendingMachineRepository`); the MySQL adapter is being built in the persistence work.

## Context

The machine must survive between runs. The aggregate is small and always loaded and saved whole:
slots, coin stock, credit and tray, by one id. The domain is plain PHP with immutable, `readonly`
parts, enums and value objects, and Domain may not use Doctrine (ADR 1). The stack already has MySQL
8.4.

## Decision

- The `VendingMachineRepository` port stays in Domain, with `get()` and `save()`. Only
  Infrastructure changes.
- The MySQL adapter uses Doctrine DBAL, not the ORM. A hand-written mapper turns the aggregate into
  a row and back.
- One row per machine, with JSON columns for the parts that are collections (slots, coin stock,
  credit, tray) and a `version` column for optimistic locking (ADR 11). The aggregate is written
  with one statement, so its consistency boundary is the row.
- The mapper rebuilds the object through the domain's named constructors, so loading cannot produce
  a state that the domain would refuse.

## Consequences

- The domain carries no mapping, no attributes and no proxy requirements, and stays free of
  Doctrine.
- Saving the aggregate is one atomic write, and no query needs to join its parts.
- Trade-off: the mapper is code to write and test, which an ORM would generate. It is small, and
  an integration test against `app_test` covers it.
- Trade-off: JSON columns cannot be queried or indexed as tables. Nothing needs to: the machine is
  always read by id. A reporting need would be served by a read model (ADR 14, events), not by
  querying these columns.

## Alternatives considered

- **Doctrine ORM with XML mapping.** Keeps the domain free of attributes, but it must map immutable
  readonly parts, enums and collections that are replaced whole, and Doctrine skips constructors
  when it hydrates, which fits an aggregate that guards its invariants in constructors badly.
- **Normalised tables through DBAL.** Closer to the relational model, with a join and several
  writes for what is one unit.
- **MongoDB.** Considered and parked, see below.

### MongoDB, considered and parked

A `VendingMachine` is naturally one document. MongoDB writes a document atomically, and optimistic
locking is a `version` field in the update filter. A MongoDB adapter would be a different
implementation of the same port: with `mongodb/mongodb` and a hand-written mapper, nothing in
Domain or Application changes.

It is parked because MySQL is already in the stack, nothing in this problem needs a document store,
and a second database would add an extension and an image to build for no behaviour. Because the
repository is a port, the decision is cheap to revisit. A MongoDB read model fed by domain events
(sales, stock and service history) is the more interesting place for it, and belongs with the
events work.

### Next step: a MongoDB adapter

Planned as a later iteration, after the MySQL adapter: a `mongo` service in Docker Compose and
`ext-mongodb` in the PHP image; a `MongoVendingMachineRepository` storing the machine as one
document, the same array the DBAL mapper already builds, so the mapper is shared; the `version`
field in the `updateOne` filter, raising the same concurrency exception, so the retry middleware
works unchanged; the adapter chosen by configuration (for example `VENDING_MACHINE_STORE=mysql` or
`mongo` deciding the port's alias); and one contract test run against both adapters, which shows
they are interchangeable.
