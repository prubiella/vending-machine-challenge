# 16. The machine stored in MongoDB

## Status

Accepted. Built and merged: `MongoVendingMachineRepository` stores the machine. The record kept
`DbalVendingMachineRepository` as a second adapter of the port; [ADR 21](0021-machine-lives-only-in-mongodb.md)
removed it, and the passages below that describe it say how it was when this record was written.
Everything under "Still planned" is built: the embedded outbox and the history in MySQL
([ADR 20](0020-domain-events-and-outbox.md)), and the technicians in MySQL
([ADR 18](0018-technician-authentication.md)). Supersedes ADR 12. [ADR 17](0017-uuid-identities.md) replaced the document's `_id: 1` with the
machine's UUID.

## Context

ADR 12 stored the machine in MySQL through DBAL, one row with JSON columns, and parked MongoDB as
the next step. A `VendingMachine` is naturally one document: MongoDB writes a document atomically,
and optimistic locking is a `version` field in the update filter. The reason to move is the outbox
that comes with domain events: the events waiting to be published must be written in the same
atomic write as the machine's state, and a document can hold both.

## Decision

- `mongodb/mongodb`, the library alone: no ODM and no bundle. `config/services.yaml` defines
  `MongoDB\Client` from `MONGODB_URL` and `MongoDB\Database` from `MONGODB_DATABASE`, with `_test`
  added in the test environment, as Doctrine does for MySQL. The client gives up after 2 s instead
  of the driver's 30 s when mongo does not answer, so requests and the readiness probe answer.
- `MongoVendingMachineRepository` stores the machine as one document of the `vending_machines`
  collection: `_id: 1`, a `version`, and the parts that ADR 12's row holds as JSON columns, as nested
  documents. `VendingMachineMapper::toDocument()` builds those parts, and the DBAL adapter (since removed)
  encoded the same arrays into its columns, so there was one mapping.
- Optimistic locking as in ADR 11: a new machine is an `insertOne`, and a duplicate key there is a
  conflict; a stored one is an `updateOne` with the loaded `version` in its filter that sets the
  parts and the next version (so a field the machine does not own, such as the outbox, survives a
  save), and a matched count of 0 is a conflict. Both throw
  `ConcurrentUpdateException`, so the retry middleware works unchanged, and the versions live in the
  same kind of `WeakMap`.
- The store is not configurable: `config/services.yaml` aliases `VendingMachineRepository` and
  `StoredMachineResetter` to the MongoDB adapter.
- `vending:reset` forgets the stored machine through `StoredMachineResetter`, a port of
  Infrastructure that each adapter implements: resetting is an operator's console action, not
  something the domain does.
- `VendingMachineRepositoryContractTestCase` holds the repository tests once, and each adapter's
  test case runs them, which shows that the adapters are interchangeable.
- The `mongo` service starts with the rest of the stack, and the PHP container waits until it is
  healthy. The health check reports it as `document_store`, next to MySQL's `database`, so
  readiness fails while it is down.

### Still planned (all built since, see the status)

- The machine's outbox in MongoDB. Domain events waiting to be published (a sale, the change given,
  a service) are saved inside the machine's document, in the same atomic `updateOne` as its state,
  so a crash can never keep one without the other. A relay then publishes them and marks them as
  sent.
- The sales, change and service history in MySQL, fed from that outbox, because reports such as
  sales per product and day are a plain `GROUP BY`. The outbox must live with the aggregate; the
  history can live wherever it is easiest to query.
- Technicians and their login in MySQL, in their own `Maintenance` context.

The outbox and the history waited for domain events, which did not exist yet when this record was
written.

## Consequences

- Nothing in Domain or Application changed: the port took a second adapter.
- The machine and, later, its outbox are one atomic write.
- Trade-off: the stack runs two databases, MySQL for what is relational and MongoDB for the
  machine. Every `make start`, test run and hook needs both.
- Trade-off: the DBAL adapter, its table and the JSON half of the mapper stayed without being wired.
  They kept the port honest through the contract test, at the cost of code that production did not
  run; ADR 21 removed them.
- A machine stored in MySQL before the switch is not copied: the first run on MongoDB starts from
  the default machine.

## Alternatives considered

- **The store chosen by an environment variable.** Nothing needs both stores at run time, so a
  switch would be configuration that no deployment uses.
- **Deleting the DBAL adapter.** Git keeps it, but the contract test would then run against one
  adapter only. Rejected here, and taken in ADR 21.
- **Keeping MongoDB in a compose profile.** The tests would then skip or fail without it, and the
  store the application uses must always be there.
