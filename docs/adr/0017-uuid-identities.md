# 17. UUID identities

## Status

Accepted. Built: `Uuid`, `MachineId`, `UuidGenerator` and `SymfonyUuidGenerator`, and the machine
stored under its UUID by both adapters. `TechnicianId` extends `Uuid` too, and
`vending:technician:create` takes the new technician's id from `UuidGenerator`. Changes the stored `_id: 1` of [ADR 16](0016-machine-stored-in-mongodb.md).

## Context

The machine had no identity in the domain: both adapters stored it under a constant `1`, the
`_id` of the MongoDB document and the `id` of the MySQL row. The `Maintenance` context brings a
second aggregate, the technician, whose id is created when the technician is. Commands return
nothing (ADR 7), so whoever creates an aggregate must choose its id before dispatching the command
and read it back through a query. An auto-increment key cannot be known that early, and it would tie
the domain to one database.

## Decision

- **UUID v7 for new ids.** It is created anywhere without asking the database, and its first 48
  bits are a timestamp, so ids sort by creation time and index well in MySQL and MongoDB, unlike the
  random v4.
- **A shared base.** `Shared\Domain\Uuid\Uuid` is an abstract value object: it accepts an RFC 4122
  UUID of any version, keeps it lowercased, and gives `fromString()`, `value()` and `equals()`. Each
  identifier is a `final` class that extends it (`MachineId`, `TechnicianId`), so a technician's id
  never stands where a machine's is expected, and `equals()` is false across kinds. The base is plain
  PHP: Domain cannot use symfony/uid (deptrac). A malformed id throws `InvalidUuidException`.
- **Generation is a port.** `Shared\Application\Uuid\UuidGenerator` gives a new id as a string;
  `SymfonyUuidGenerator` returns a v7. It lives in Application because the callers are the entry
  points and handlers that create an aggregate, never the domain, which receives its ids. The test
  of a caller passes a fixed generator, so it knows the id it will read back.
- **The single machine's id is configuration.** The statement asks for one machine, so the ports
  keep `get()` and `save()` without an id, and routes, commands and queries carry none. The
  `vending_machine.id` parameter (`VENDING_MACHINE_ID`, whose only default is a fixed UUID in
  `config/services.yaml`, overridden in `.env`) names it: `DefaultVendingMachineFactory` installs the
  default machine with that id, and the adapters load the machine stored under it.
- **Storage.** The MongoDB document's `_id` is the UUID string. The MySQL `vending_machine.id`
  column becomes `CHAR(36)` through a migration that deletes the rows stored so far, so the next
  load gives the default machine. `vending:reset` also deletes the MongoDB document stored under the
  old `_id: 1`.

## Consequences

- The aggregate knows its identity, and a second aggregate type gets its id in one line.
- A fleet only needs the id passed through: routes, commands, queries and the repository port take
  it, and the configured id becomes the default machine of a fresh installation.
- Trade-off: an id is a 36-character string instead of an integer, in every row and document.
- Trade-off: the machine stored before this change is lost, both in MySQL (the migration) and in
  MongoDB (no longer loaded, removed by the next `vending:reset`). The machine was reset to the
  default anyway whenever an operator ran `vending:reset`.
- MongoDB has no migrations on purpose: its data is one document that `vending:reset` rebuilds
  from configuration, and the only change of shape so far (the `_id`) is handled by that reset and
  a line in the README. With more documents, or data that cannot be regenerated, the next step is a
  lazy migration in the mapper (read the old and the new shape, always write the new one), or a
  small migration command with a `migrations` collection and versioned classes, run by
  `make migrate`.

## Alternatives considered

- **Auto-increment ids.** The database would choose the id, too late for a command that returns
  nothing, and the domain would depend on the store.
- **UUID v4.** Random, so new rows land anywhere in the index and ids say nothing about order.
- **symfony/uid's `Uuid` in the domain.** deptrac forbids the framework in Domain, and a validated
  string is all the domain needs.
- **A fleet now.** Routes and commands carrying a machine id are more work and more API than the
  statement asks for; the configured id keeps the door open without it.
