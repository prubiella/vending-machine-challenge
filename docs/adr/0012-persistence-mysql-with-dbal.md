# 12. Persistence: a hand-written mapper, MySQL through DBAL, then MongoDB

## Status

Accepted. Built and merged: `DbalVendingMachineRepository` and `VendingMachineMapper` first, then
`MongoVendingMachineRepository`, which now stores the machine (see
[The MongoDB adapter, built](#the-mongodb-adapter-built)). The DBAL adapter stays as a second
implementation of the port, which the contract test runs. `InMemoryVendingMachineRepository` still
exists in `src/`; moving it to `tests/` as a test double is planned polish.

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
  a state that the domain would refuse. Rehydration has its own: `VendingMachine::restore()` takes
  the parts of a stored machine (inventory, wallet and tray) and `Wallet::of()` takes its credit and
  stock. They are the only way in that does not start from a fresh machine, they are plain domain
  code with no persistence in them, and the tests of the mapper cover the round trip.
- The row has the columns `version`, `inventory`, `wallet_credit`, `wallet_stock` and `tray`, the
  last four as JSON, and the machine always has id 1: there is one machine (ADR 11).
- When no row exists, `get()` returns the default machine, built by `DefaultVendingMachineFactory`
  from `vending_machine.default_products` and the coin stock in `config/services.yaml`, and the
  first `save()` inserts it. Once a machine is stored it wins over those defaults. `vending:reset`
  deletes the stored machine, which brings the defaults back. It does so through
  `StoredMachineResetter`, a port of Infrastructure that each adapter implements: resetting is an
  operator's console action, not something the domain does.

## Consequences

- The domain carries no mapping, no attributes and no proxy requirements, and stays free of
  Doctrine.
- Saving the aggregate is one atomic write, and no query needs to join its parts.
- Trade-off: the mapper is code to write and test, which an ORM would generate. It is small, and
  an integration test against `app_test` covers it.
- Trade-off: JSON columns cannot be queried or indexed as tables. Nothing needs to: the machine is
  always read by id. A reporting need would be served by a read model (ADR 14, events), not by
  querying these columns.
- Trade-off: the stack runs two databases, MySQL for what is relational and MongoDB for the
  machine. MongoDB earns its place with the outbox, which must be written with the aggregate.

## Alternatives considered

- **Doctrine ORM with XML mapping.** Keeps the domain free of attributes, but it must map immutable
  readonly parts, enums and collections that are replaced whole, and Doctrine skips constructors
  when it hydrates, which fits an aggregate that guards its invariants in constructors badly.
- **Normalised tables through DBAL.** Closer to the relational model, with a join and several
  writes for what is one unit.
- **MongoDB.** Parked for the first adapter, then built, see below.

### MongoDB, parked at first

A `VendingMachine` is naturally one document. MongoDB writes a document atomically, and optimistic
locking is a `version` field in the update filter. It was parked for the first adapter because
MySQL was already in the stack and nothing in this problem needs a document store. Because the
repository is a port, the decision was cheap to revisit, and the next section revisits it.

### The MongoDB adapter, built

- `mongodb/mongodb`, the library alone: no ODM and no bundle. `config/services.yaml` defines
  `MongoDB\Client` from `MONGODB_URL` and `MongoDB\Database` from `MONGODB_DATABASE`, with `_test`
  added in the test environment, as Doctrine does for MySQL.
- `MongoVendingMachineRepository` stores the machine as one document of the `vending_machines`
  collection: `_id: 1`, a `version`, and the parts that the row holds as JSON columns, as nested
  documents. `VendingMachineMapper::toDocument()` builds those parts, and the DBAL adapter encodes the
  same arrays into its columns, so there is one mapping.
- Optimistic locking as in ADR 11: a new machine is an `insertOne`, and a duplicate key there is a
  conflict; a stored one is an `updateOne` with the loaded `version` in its filter that sets the
  parts and the next version, and a matched count of 0 is a conflict. Both throw
  `ConcurrentUpdateException`, so the retry middleware works unchanged, and the versions live in the
  same kind of `WeakMap`.
- The store is not configurable: `config/services.yaml` aliases `VendingMachineRepository` and
  `StoredMachineResetter` to the MongoDB adapter. Going back to MySQL is pointing both aliases at
  `DbalVendingMachineRepository`. A switch chosen by an environment variable was considered and left
  out: nothing needs both stores at run time.
- `VendingMachineRepositoryContractTestCase` holds the repository tests once, and each adapter's
  test case runs them, which shows that the adapters are interchangeable.
- The `mongo` service starts with the rest of the stack, and the PHP container waits until it is
  healthy, so the tests and the hooks always reach it.

### Still planned

Each store will hold what fits it:

- The machine's outbox in MongoDB. Domain events waiting to be published (a sale, the change given,
  a service) are saved inside the machine's document, in the same atomic `updateOne` as its state,
  so a crash can never keep one without the other. A relay then publishes them and marks them as
  sent.
- The sales, change and service history in MySQL, fed from that outbox, because reports such as
  sales per product and day are a plain `GROUP BY`. The outbox must live with the aggregate; the
  history can live wherever it is easiest to query.
- Technicians and their login in MySQL, in their own `Maintenance` context.

The outbox and the history wait for domain events, which do not exist yet.
