# Lens: spec

Whether the change does what the statement asks. Read the whole statement at the `Spec` path first.

1. List the requirements of the statement that the changed code touches.
2. For each, check the change implements it fully: every operation, input, output, error case and
   amount the statement names.
3. Where the statement is silent, judge by what a reasonable user of a vending machine expects (a
   coin it cannot accept is returned, change is exact, a sold-out product is not charged). A
   reasonable expectation counts as a requirement; the statement's silence is not permission.
4. Flag behaviour the change adds that the statement contradicts.

Each finding quotes the requirement in `summary` and says how the change misses it in
`failure_scenario`.

Requirements this change does not touch are not findings; list the ones you set aside under
Declined, one line each.
