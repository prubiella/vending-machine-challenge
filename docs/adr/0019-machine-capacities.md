# 19. The machine has capacities that bound a service, and a cash box takes the overflow of a sale

## Status

Accepted.

## Context

A service sets the units of each product and the count of each coin (ADR 10). Nothing bounded
them, and a service over HTTP made that reachable from outside the console: a count of a hundred
million coins was a valid request, and building the coin stock one coin at a time ran PHP out of
memory. A real machine has a bounded slot for each product and a bounded tube for each coin of the
change.

## Decision

- A slot holds at most `Slot::CAPACITY` (200) units of its product, and each coin tube of the change
  holds at most `Wallet::TUBE_CAPACITY` (500) coins of its denomination. They are domain rules, with
  one exception each, `SlotCapacityExceededException` and `CoinTubeCapacityExceededException`, and
  the HTTP API answers them with 422.
- They are checked when the machine is restocked, by SERVICE in the console and by
  `PUT /machine/service`, before anything changes.
- A sale is not refused for a full tube. The customer's coins join the stock (ADR 8), except the
  ones that do not fit their tube, which go to the cash box (`Wallet::cashBox()`), as the overflow of
  a real machine does. Coins of 1 are never given as change, so they have no tube to fill and always go
  to the cash box. A tube already past 500 (a machine stored before the cash box) takes no more coins.
- The coins that go to the cash box are not a rule of the wallet: `Wallet::charge()` asks the change
  maker which coins it gives (`ChangeMaker::gives()`, answered from the coins it is configured with,
  ADR 8) and sends every other coin to the cash box, so the configuration stays the only place that
  says the 1 is never given as change.
- The cash box is never used for change: the change maker only sees the tubes. It is filled by a
  sale only, and `SERVICE` empties it, since the technician takes the money; `SERVICE` still sets the
  tube of the 1s like any other.
- `GET /machine/status` shows it as `cashBoxByCents`, and `vending:service` prints what it collected.
  What a service collected is also logged at info level by `CashBoxCollectionLog`, a port of the
  `ServiceMachine` use case that a logger adapter implements, until a `MachineServiced` domain event
  carries it.
- It is stored as one more part of the machine, `wallet_cash_box`: a field of the MongoDB document and
  a JSON column of the MySQL row, added by a migration with an empty default (MySQL takes no literal
  default on a JSON column, so it is the expression `JSON_OBJECT()`). A document stored before it is
  read as one with an empty cash box.
- `CoinCollection` is built from counts without one element per coin, so a large count costs no
  more memory than a small one, whatever the caller.

## Consequences

- A technician cannot set a stock the machine could not hold, and a huge count is refused with 422
  instead of exhausting memory.
- A sale never leaves a tube past 500 coins: the coins that do not fit are kept apart, and no money
  is lost or refused. A stored machine that holds more than 500 coins of a denomination, from before
  the cash box, still loads: loading is not a restock.
- A full tube cannot give change in the coins that overflowed, even when it could have, since they
  went to the cash box before the change was made.

## Alternatives considered

- Bounds on the request only (a `maximum` in the payload): it would protect the API but not the
  console, and a rule of the machine belongs in the domain.
- Bounding the sale too, refusing a coin when its tube is full: it would refuse a customer's money
  for a limit the customer cannot see; the cash box is the real machine's answer.
- Letting the cash box give change: it would make the tubes' capacity meaningless, and a real machine
  does not give change from its cash box.
