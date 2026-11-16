# 16. The machine stored in MongoDB

## Status

Accepted. Built and merged: `MongoVendingMachineRepository` stores the machine, and
`DbalVendingMachineRepository` stays as a second adapter of the port. The embedded outbox is built
([ADR 20](0020-domain-events-and-outbox.md)): both adapters write the machine's events with its
state in one write. The history in MySQL is planned. Supersedes ADR 12, whose mapper and DBAL
adapter this record keeps. `InMemoryVendingMachineRepository` still exists in `src/`; moving it to `tests/` as a test double is
planned polish. [ADR 17](0017-uuid-identities.md) replaced the document's `_id: 1` with the
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
  documents. `VendingMachineMapper::toDocument()` builds those parts, and the DBAL adapter encodes the
  same arrays into its columns, so there is one mapping.
- Optimistic locking as in ADR 11: a new machine is an `insertOne`, and a duplicate key there is a
  conflict; a stored one is an `updateOne` with the loaded `version` in its filter that sets the
  parts and the next version (so a field the machine does not own, such as the outbox, survives a
  save), and a matched count of 0 is a conflict. Both throw
  `ConcurrentUpdateException`, so the retry middleware works unchanged, and the versions live in the
  same kind of `WeakMap`.
- The store is not configurable: `config/services.yaml` aliases `VendingMachineRepository` and
  `StoredMachineResetter` to the MongoDB adapter. Going back to MySQL is pointing both aliases at
  `DbalVendingMachineRepository`.
- `vending:reset` forgets the stored machine through `StoredMachineResetter`, a port of
  Infrastructure that each adapter implements: resetting is an operator's console action, not
  something the domain does.
- `VendingMachineRepositoryContractTestCase` holds the repository tests once, and each adapter's
  test case runs them, which shows that the adapters are interchangeable.
- The `mongo` service starts with the rest of the stack, and the PHP container waits until it is
  healthy. The health check reports it as `document_store`, next to MySQL's `database`, so
  readiness fails while it is down.

### Still planned

- The machine's outbox in MongoDB. Domain events waiting to be published (a sale, the change given,
  a service) are saved inside the machine's document, in the same atomic `updateOne` as its state,
  so a crash can never keep one without the other. A relay then publishes them and marks them as
  sent.
- The sales, change and service history in MySQL, fed from that outbox, because reports such as
  sales per product and day are a plain `GROUP BY`. The outbox must live with the aggregate; the
  history can live wherever it is easiest to query.
- Technicians and their login in MySQL, in their own `Maintenance` context.

The outbox and the history wait for domain events, which do not exist yet.

## Consequences

- Nothing in Domain or Application changed: the port took a second adapter.
- The machine and, later, its outbox are one atomic write.
- Trade-off: the stack runs two databases, MySQL for what is relational and MongoDB for the
  machine. Every `make start`, test run and hook needs both.
- Trade-off: the DBAL adapter, its table and the JSON half of the mapper stay without being wired.
  They keep the port honest through the contract test, at the cost of code that production does not
  run.
- A machine stored in MySQL before the switch is not copied: the first run on MongoDB starts from
  the default machine.

## Alternatives considered

- **The store chosen by an environment variable.** Nothing needs both stores at run time, so a
  switch would be configuration that no deployment uses.
- **Deleting the DBAL adapter.** Git keeps it, but the contract test would then run against one
  adapter only and prove nothing about the port.
- **Keeping MongoDB in a compose profile.** The tests would then skip or fail without it, and the
  store the application uses must always be there.
