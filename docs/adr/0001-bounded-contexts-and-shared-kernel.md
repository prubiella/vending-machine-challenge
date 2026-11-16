# 1. Bounded contexts first, a small Shared kernel

## Status

Accepted.

## Context

The problem is small, but it has to show how the code would grow: more features (technician login,
a fleet of machines) and more people working on it. A layer-first layout (`Domain/`, `Application/`,
`Infrastructure/` at the top) puts every concept of the system in the same three folders, so
unrelated features share namespaces and nothing stops one from reaching into another.

## Decision

`src/` is split by bounded context first and by layer second: `src/<Context>/<Layer>/`.

- `VendingMachine`: coins, products, change, service.
- `Maintenance` (technicians and their login) is added when technician authentication arrives. A
  context exists only once a feature needs it, and a folder only once it holds code. Note,
  2026-11-16: it now exists, with the technicians and their hashed passwords, under the same layers
  and deptrac rules.
- `Shared` is a small, stable kernel that every context may use and that uses no context:
  `Money` and the base `DomainException`, the command and query bus ports and their Messenger
  implementations, the base controller and the JSON error listener.
- `Money` lives in `Shared` because it is generic and stable: any other context would reuse it
  unchanged. `Coin` and `CoinCollection` live in `VendingMachine` because they are the physical coins
  this machine accepts and its change tube.
- deptrac enforces it: Domain depends only on `Shared/Domain`; Application on its own Domain and
  `Shared`; only Infrastructure uses the framework; no context uses another context's Domain.
  `make deptrac` also fails on a class that belongs to no layer and on a dependency that points
  outside every layer, so a new context cannot be forgotten.

## Consequences

- Adding a context is a documented, mechanical step: a folder, its deptrac layers and a routes file.
  The globs in `config/services.yaml` already match any context.
- Contexts talk through ids or the Application layer, and deptrac needs an explicit rule for each
  integration, so every one is a conscious decision.
- Trade-off: with one real context the split looks like ceremony today. It costs a path segment and
  a few lines of deptrac configuration.
- Trade-off: `Shared` attracts "just put it there". The rule is that only what several contexts need
  belongs in it.

## Alternatives considered

- **Layer-first folders with no contexts.** Simplest today, but the first second context forces a
  move of every file, and the layers cannot be enforced per feature.
- **Everything in one context, `Money` beside `Coin`.** Fewer files, but `Money` would be tied to
  the machine and reused by copying, or by one context reaching into another.
- **A separate package per context.** Stronger isolation, far more tooling than a repository of this
  size justifies.
