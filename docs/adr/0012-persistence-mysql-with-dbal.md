# 12. Persistence: MySQL through DBAL with a hand-written mapper

## Status

Superseded by [ADR 16](0016-machine-stored-in-mongodb.md) and [ADR 21](0021-machine-lives-only-in-mongodb.md).

The next step at the end of this record was built, except the store chosen by configuration, which
ADR 16 rejected: the MongoDB adapter (ADR 16), the outbox in the machine's document and the history
in MySQL ([ADR 20](0020-domain-events-and-outbox.md)), and the technicians in MySQL
([ADR 18](0018-technician-authentication.md)). The MySQL outbox table of its last paragraph existed
while the DBAL adapter did, and went with it (ADR 21).

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
  deletes the row, which brings the defaults back.

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

With that adapter, each store holds what fits it:

- The machine and its outbox in MongoDB. Domain events waiting to be published (a sale, the change
  given, a service) are saved inside the machine's document, in the same atomic `updateOne` as its
  state, so a crash can never keep one without the other. A relay then publishes them and marks
  them as sent.
- The sales, change and service history in MySQL, fed from that outbox, because reports such as
  sales per product and day are a plain `GROUP BY`. The outbox must live with the aggregate; the
  history can live wherever it is easiest to query.
- Technicians and their login in MySQL, in their own `Maintenance` context.

While the machine stays on the DBAL adapter, the outbox is a MySQL table written in the same
transaction as the machine's row.
