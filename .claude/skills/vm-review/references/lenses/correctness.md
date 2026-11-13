# Lens: correctness

Bugs that make the changed code do the wrong thing. Each finding needs a concrete failure scenario:
the input, state or command sequence, and the wrong result.

Look for:

- Logic errors: wrong condition, off-by-one, inverted check, missing case in a `match` or `if` chain.
- Invariants: a value the code must keep valid (a quantity or balance going negative, an amount
  rounded or stored in another unit than the project rules require, a state transition that should
  be rejected but is accepted).
- Wiring that compiles but does not run: a handler not registered on its bus, a route pointing to
  the wrong controller, a service that receives the wrong implementation, a config key ignored
  because of a typo.
- Shell, Makefile and git hooks: quoting, unset variables, wrong exit codes, behaviour with paths
  containing spaces, renamed or deleted files, `git commit -a`, worktrees, containers stopped.
- Docker and HTTP: ports, hostnames, TLS verification, health checks, environment variables that
  are read but never set.
- Persistence once it exists: lost updates, missing transactions, queries without parameters.

Not this lens: swallowed errors and logging (silent-failures), project conventions (project-rules),
missing tests (tests), documentation accuracy (docs). Whether the change meets the product's
requirements is for the project's own tests, not for a review.
