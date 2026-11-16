# 19. The machine has capacities that bound a service, not a sale

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
- A sale is not bounded. The customer's coins join the stock (ADR 8) and may take a tube past 500.
  On a real machine that overflow drops into a cash box; the cash box is not modelled, so the stock
  simply holds the extra coins until the next service sets the counts again.
- `CoinCollection` is built from counts without one element per coin, so a large count costs no
  more memory than a small one, whatever the caller.

## Consequences

- A technician cannot set a stock the machine could not hold, and a huge count is refused with 422
  instead of exhausting memory.
- A stored machine may hold more than 500 coins of a denomination after sales; loading it is not a
  restock, so it is not refused.

## Alternatives considered

- Bounds on the request only (a `maximum` in the payload): it would protect the API but not the
  console, and a rule of the machine belongs in the domain.
- Bounding the sale too, refusing a coin when its tube is full: it would refuse a customer's money
  for a limit the customer cannot see; a cash box is the real machine's answer, left as future work.
