# 8. Change making

## Status

Accepted.

## Context

After a sale the machine gives change from the coins it has, which are finite. The obvious algorithm,
greedy (largest coin first), is wrong with limited stock: for 30 cents with one 25 and three 10s,
greedy takes the 25, is left with 5 and no 5-cent coin, and fails, although three 10s work. The
statement also limits what change may be made of, and says what RETURN-COIN gives back.

## Decision

- Change making sits behind `ChangeMaker`, a domain interface:
  `makeChange(Money $amount, CoinCollection $available): CoinCollection`. It throws
  `NoExactChangeException` when no combination of the available coins adds up.
- `FewestCoinsChangeMaker` is a dynamic programming search bounded by the target amount and by the
  stock of each coin: for each coin, it extends the best combination found for every reachable
  amount, using at most as many coins as are in stock. It returns the combination with the fewest
  coins. A unit test pins the 30 cents counterexample.
- No tie-break rule is needed with these coins: the fewest-coins combination is unique. The only
  equal swap, 4 x 10 against 25 + 3 x 5, is beaten by 25 + 10 + 5. A new denomination could introduce
  ties, so adding a coin means rechecking this (ADR 3).
- Change is made only of 0.05, 0.10 and 0.25. The coins it may give are the constructor arguments of
  the change maker, set in `config/services.yaml`. Coins of 1 are never given as change: an
  inserted one goes to the cash box ([ADR 19](0019-machine-capacities.md)), and one stocked through
  SERVICE stays in its tube.
- On a sale the inserted coins join the coin stock first, and change is taken from that stock.
- RETURN-COIN is not change: it gives back exactly the coins inserted, a 1 included.
- A refusal (insufficient credit, no exact change) keeps the credit.

## Consequences

- The search finds a solution whenever one exists, and its cost is small (coin kinds times amount in
  cents times stock), which is nothing for the cents involved.
- The interface keeps the strategy replaceable and lets Infrastructure decorate it (ADR 14).
- Trade-off: "fewest coins" is a policy, not a law. It keeps the coin stock balanced for the next
  customer less well than some alternatives would, and the interface exists so another policy can
  replace it.
- Trade-off: the dollar coins are never given as change, so a machine with only 1s and a short
  credit refuses a sale that a real machine might serve. This is what the statement's valid
  responses ask for.

## Alternatives considered

- **Greedy.** Fails with limited stock, as above.
- **Exhaustive backtracking.** Correct but exponential in the worst case, with no benefit over the
  table.
- **A rule in the `Wallet` that skips the dollar coin.** It was the first place for it and moved into
  the change maker, because "which coins may be given as change" is part of making change.
