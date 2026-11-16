# 2. Money as integer cents

## Status

Accepted.

## Context

The prices (0.65, 1.00, 1.50) and the coins (0.05, 0.10, 0.25, 1) are decimal amounts. Floats cannot
represent 0.10 exactly, so sums and comparisons drift (`0.1 + 0.2 !== 0.3`), which is unacceptable
when the machine must decide whether the credit covers a price and give exact change.

## Decision

All money is an integer number of cents. `Shared\Domain\Money\Money` is an immutable value object
over an `int`, built with `Money::fromCents()`, never negative, with `add`, `subtract` and
comparisons. A negative amount throws `NegativeMoneyException`, and subtracting more than the amount
throws `InsufficientMoneyException`.

The decimal notation (`0.25`, `1`) exists only at the edge: the console parses it and prints it
(`MoneyNotation`) without ever converting to a float. The domain never sees a decimal.

## Consequences

- Arithmetic is exact and cheap, and equality is `===` on integers.
- A negative amount is unrepresentable, so "credit minus price" cannot silently go below zero.
- Trade-off: `Money` has no currency. A second currency would add one to the value object and make
  `Coin` a value object too (see ADR 3).
- Trade-off: amounts below one cent do not exist. That is the point for coins, but a pricing rule
  with fractions of a cent would need another type.

## Alternatives considered

- **Floats.** Rejected: inexact.
- **Strings or `bcmath` decimals.** Exact, but slower to read and easy to mix with plain strings;
  nothing in the problem needs arbitrary precision.
- **A money library such as `moneyphp/money`.** Mature and multi-currency, but a dependency for a
  single-currency problem whose domain needs a dozen lines of arithmetic.
