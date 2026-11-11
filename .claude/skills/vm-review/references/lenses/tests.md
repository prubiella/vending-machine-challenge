# Lens: tests

Whether the tests prove the change works and would catch it breaking.

1. List the behaviour the change adds or modifies: domain rules, handlers, controllers, wiring.
2. For each, find the test that covers it. `AGENTS.md` asks for unit tests in `tests/Unit/`
   mirroring `src/` for every domain rule and handler, and integration tests in
   `tests/Integration/` for wiring only the kernel can prove.
3. Rate each gap from 1 to 10 by what a regression would cost: 9-10 data loss, wrong money or a
   security hole; 7-8 a user-visible bug; 5-6 an edge case; 1-4 nice to have.
4. Report only gaps rated 6 or more, as findings with severity Important (8-10) or Minor (6-7).
   Put the rating in `summary`, and sketch the test in `fix`: file path, test name, arrange, act,
   assert.

Also check the tests that exist:

- They assert behaviour, not the implementation (no mocks of the class under test, no asserting the
  same constant the code returns).
- One reason to fail per test; a data provider for value objects with many valid and invalid
  inputs.
- `assertSame` over `assertEquals`; test classes are `final`.
- When `Local head matches: yes`, run `make test` and report the result under Checks.

Not this lens: bugs in the production code itself (correctness).
