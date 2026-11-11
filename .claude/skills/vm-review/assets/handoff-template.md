# Fix the review findings of <target>

You are picking up the findings of a code review. The review was done in another session; nothing
in it has been fixed yet. The full report is at `<absolute path of report.md>`.

## Where

- Repository: `<absolute path of the main checkout>`
- Branch: `<head branch>`, based on `<base branch>`
- Pull request: <url, or "none">

Work on that branch. Before pushing, check that the pull request is still open
(`gh pr view <n> --json state`); if it was merged, start a new branch from the updated base.

## How

1. Read `AGENTS.md` first: it holds the project rules.
2. Take the findings in the order below. For each one, verify it against the code before changing
   anything. If a finding is wrong, do not apply it: note why, with the line or command that shows
   it, and move on.
3. Fix with the smallest change that solves it, plus a test when the finding is about behaviour.
4. Run `make code-check`. It must pass.
5. One Conventional Commit per finding or per small group of related findings, in English.
6. Never `--no-verify`. Push with `FORCE_COLOR=0 git push`.
7. End with the list of findings: fixed (commit), or not applied (why).

## Findings to fix

1. **<severity>** `<file:line>`: <summary>
   - Scenario: <failure scenario>
   - Suggested fix: <fix>
   - Verified: <CONFIRMED | PLAUSIBLE>
