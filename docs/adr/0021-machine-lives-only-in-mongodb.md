# 21. The machine lives only in MongoDB

## Status

Accepted. Built and merged: `DbalVendingMachineRepository`, its tables and the row half of the
mappers are removed. Supersedes [ADR 12](0012-persistence-mysql-with-dbal.md) for the machine, and
the part of [ADR 16](0016-machine-stored-in-mongodb.md) that kept the DBAL adapter as a second
adapter.

## Context

ADR 16 moved the machine to MongoDB and kept `DbalVendingMachineRepository` as a second adapter of
the `VendingMachineRepository` port, so that the contract test would run against two
implementations. In practice nothing used it: `config/services.yaml` aliases the port,
`StoredMachineResetter` and `MachineOutbox` to the MongoDB adapter only, no configuration selects
another store, and the adapter ran only inside its own contract test. It cost a MySQL table for the
machine and one for its outbox, the JSON half of two mappers, and a paragraph in the documents to
explain why code that production never runs was there. A reader takes unused code for production
code, or for a store that was half switched.

## Decision

- **One store per aggregate.** The machine, with its outbox, lives in MongoDB. MySQL holds what is
  relational: the technicians, `machine_history` and the migrations table.
- **`DbalVendingMachineRepository` is removed**, with its integration test, the `toRow()` and
  `fromRow()` of `VendingMachineMapper`, and `OutboxEventMapper::fromRow()`. `OutboxEventMapper::toRow()`
  stays: `DbalMachineHistory` writes the history rows with it.
- **A new migration drops `vending_machine` and `vending_machine_outbox`.** The earlier migrations
  are never edited; the new one's `down()` recreates both tables as they left them.
- **The port stays swappable, and that is still proven.** `VendingMachineRepositoryContractTestCase`
  stays as an abstract class, and `MongoVendingMachineRepositoryTest` extends it. The in-memory fake
  that the unit tests use is the port's other implementation. A new adapter extends the contract
  test case to be accepted.
- **Git keeps the adapter.** If MySQL is ever wanted again, the last commit that has
  `DbalVendingMachineRepository` is the starting point, and the contract test says when it is done.

## Consequences

- No code and no table that production does not use.
- Trade-off: the contract test now runs against one adapter, so by itself it no longer shows that
  two engines are interchangeable. What it keeps is a written definition of the port's behaviour
  that any adapter must pass.
- Trade-off: going back to MySQL is work, not a change of alias.
- The development and test MySQL databases need `make migrate` once to lose the two tables.

## Alternatives considered

- **Keeping the adapter as a second implementation.** ADR 16 did, to keep the port honest. It
  proved the port at the price of a table, an outbox and a mapper that nothing ran, and it made the
  documents describe two stores when there was one.
- **Choosing the store with an environment variable.** Nothing needs both at run time, so the
  switch would be configuration that no deployment uses (the same reason ADR 16 gave).
- **Folding the contract test into the Mongo test.** It would save one abstract class, but then a
  future adapter would copy the cases instead of extending them, and the contract would stop being
  a thing of its own.
