# vm-review test cases

Each folder is one test case in the layout skill-creator and `claude plugin eval` read:
`prompt.md` (the command), `case.yaml` (what a good result looks like) and `graders/` (one
verifiable statement per file).

The cases review fixed commits through tags, so the expected findings never move. The tags are
published on GitHub so that anyone improving the skill can run the evals and check it still works.
A fresh clone already has them; an older one gets them with `git fetch --tags`.

| Tag | Case | What it points at |
|---|---|---|
| `eval/skeleton-v1` | `finds-known-skeleton-bugs` | The original skeleton (Docker, Symfony, tooling) on top of the initial commit `1d0a7b3`, before the review fixes. |
| `eval/clean-v1` | `clean-docs-change-stays-quiet` | A docs-only change; that case's `fixture.patch` recreates it on a throwaway branch. |
| `eval/spec-v2` | `spec-catches-subtle-violations` | Domain code with planted spec violations; that case's `fixture.patch` recreates it the same way. |

Name the session that runs an iteration `vm-eval-it<N>` (see `AGENTS.md`).

Graders check one condition each. Graders 01 to 08 and 15 of the skeleton case, 01 to 03 of the
clean case and 01 to 04, 11 and 12 of the spec case judge the review itself (what it finds and
whether it is right); the rest check what the skill promises the calling session (a short message
that points to the full review, an untouched repository, `review.md` and `handoff.md`), which a
review without the skill is not expected to meet.

Results go to `.audit/vm-review-workspace/` (ignored by git), never next to the skill: they hold
diffs and reports that do not belong in the repository.
