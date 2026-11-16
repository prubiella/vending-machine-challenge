# 4. Selector as a value object, not an enum

## Status

Accepted.

## Context

A customer picks a product with a selector: `GET-WATER`, `GET-JUICE`, `GET-SODA`. Coins and
selectors look alike (a small, named set), so an enum is tempting. They differ in who owns the set:
the coins are fixed by the currency, while the products of a machine are configured, not hard-coded.

## Decision

`Selector` is a `final readonly` value object over a string, built with `Selector::fromString()`,
which accepts only upper case letters, digits and underscores (`^[A-Z0-9_]+$`) and throws
`InvalidSelectorException` otherwise. It has `value()` and `equals()`. The three products and their
prices are data (`config/services.yaml` holds the default machine), not code.

An unknown selector is a domain refusal (`UnknownSelectorException`) raised by the `Inventory`,
which knows which selectors exist; the value object only guarantees the shape.

## Consequences

- A new product is data, with no code change or deploy.
- The domain cannot know statically which selectors exist: that check happens at run time in the
  `Inventory`, so a typo is a refusal rather than a compile error.
- Trade-off: no exhaustive `match` over products. Nothing needs one, since a product carries its own
  name and price.

## Alternatives considered

- **An enum `Selector { Water, Juice, Soda }`.** Type safe, but it hard-codes the catalogue: every
  product would need a release.
- **A plain string.** No validation and primitive obsession: any string could be passed as a
  selector.
