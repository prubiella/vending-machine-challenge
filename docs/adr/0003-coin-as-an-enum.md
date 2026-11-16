# 3. Coin as an enum, not a class

## Status

Accepted.

## Context

The machine accepts a closed set of coins: 0.05, 0.10, 0.25 and 1. The question is how to model one
coin: as a class (a value object holding cents) or as an enum.

## Decision

`Coin` is an `int`-backed enum with four cases, `FiveCents` to `OneHundredCents`. `Coin::fromCents()`
turns an integer into a case or throws `UnknownCoinException`, and `amount()` gives its `Money`.
Reading the `0.05` / `1` notation belongs to Infrastructure, not to the enum.

Rules about coins live where they are used, not in the enum. For example, "the dollar coin is never
given as change" is configuration of the change maker (ADR 8).

## Consequences

- An invalid coin cannot be represented, so no domain code validates one.
- Comparison is `===`, `match` is checked for exhaustiveness, and `Coin::cases()` lets code adapt to
  the list, for example when building the usage message.
- A new coin is one case plus tests and a deploy. That is acceptable for something that changes
  every few years, and rechecking change making is part of that change (ADR 8).
- Trade-off: the set of coins is code, not data. A technician cannot enable a coin, and one machine
  cannot accept a different set than another.

When to revisit: switch `Coin` to a value object (cents plus currency), with the accepted set as a
policy of the machine, if coins become configuration or state: several countries or currencies,
machine models that accept different coins, or a technician enabling coins in SERVICE. The change
stays local, because the rest of the domain depends on `Money` and `CoinCollection`, not on the
enum.

## Alternatives considered

- **A `Coin` value object over `Money`.** More flexible, but an invalid coin becomes representable
  and every entry point must validate it, to buy a flexibility the problem does not ask for.
- **Constants or plain integers.** No type safety and no exhaustive `match`.
