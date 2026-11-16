# 10. SERVICE sets the counts and returns pending credit first

## Status

Accepted.

## Context

A technician services the machine: they put products in and set the coins available for change. Two
questions: does SERVICE add to what is there or replace it, and what happens to the credit of a
customer in the middle of an operation?

## Decision

- SERVICE sets, it does not add. `Inventory::restockAll()` sets the units of each product listed and
  `Wallet::restock()` replaces the coin stock with the counts given. The technician states what the
  machine now holds, which is what they see when they open it, so running SERVICE twice gives the
  same result.
- The count of each coin is part of the call, including the 1. Coins of 1 are takings and never
  change (ADR 8), so setting their count to zero is how a technician empties the cash box.
- Pending credit drops into the tray first, as RETURN-COIN would, and then the new stock is
  applied, all in one step of the aggregate (ADR 6). The stock never changes under a customer's
  credit, and the customer's coins are never swallowed.
- A freshly installed machine has its products and no stock until the first SERVICE
  (`VendingMachine::install()`); the default machine of the console is built by installing and
  servicing it with ten units of each product and ten coins of 0.05, 0.10 and 0.25.

## Consequences

- Idempotent and easy to reason about: the result depends on the call, not on the previous stock.
- Trade-off: a technician who types a wrong count overwrites the right one. Adding to the stock
  would not fix that, only make it harder to see.
- Trade-off: the pending credit is returned rather than kept for the customer, so a technician
  servicing mid-sale cancels the sale. Real machines behave the same in service mode.
- Not built yet: recording who serviced the machine (the technician id arrives with the
  `Maintenance` context).

## Alternatives considered

- **SERVICE adds to the stock.** Matches "refill", but cannot lower a count or empty the cash box,
  and the machine's state then depends on its history.
- **Refuse SERVICE while there is credit.** Safe, but leaves a technician blocked by a customer who
  walked away.
- **Keep the credit through SERVICE.** The stock could change under a customer's credit, for
  example removing the very coins that credit counts on for change.
