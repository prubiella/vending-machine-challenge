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
| `eval/tooling-v1` | `tooling-silent-failure` | A tooling-only change to the pre-commit hook with a planted silent failure, reviewed by the `sonnet` lenses; that case's `fixture.patch` recreates it the same way. |

Name the session that runs an iteration `vm-eval-it<N>` (see `AGENTS.md`) and start it with
`--model sonnet`, isolated from the user's own plugins, hooks and MCP servers except skill-creator:

```
claude -p --name vm-eval-it<N> --model sonnet --setting-sources project --strict-mcp-config \
  --plugin-dir <skill-creator plugin> "<task>"
```

With the official marketplace installed, the plugin folder is
`~/.claude/plugins/marketplaces/claude-plugins-official/plugins/skill-creator`.

The session only starts the runs, collects their outputs and writes the benchmark, yet in
iteration 9 it took more tokens than any other part, because on `opus` every turn re-read a
context that grew past 100k. Its model changes nothing that the evals measure: the skill pins the
models of its own orchestrator, lenses and verifiers, and the runners get theirs below.

Reuse a case's runs without the skill when nothing they depend on changed since an earlier
iteration: the case's `prompt.md`, `fixture.patch`, `files/` and tag, and the runners' model. Those
runs never load the skill, so changing the skill or its graders cannot change their output. Copy
their `outputs/` and `timing.json` from that iteration, grade them again with the current graders,
and record in the benchmark which iteration they came from. Run them again whenever one of those
inputs changed.

Graders check one condition each. Graders 01 to 08 and 15 of the skeleton case, 01 to 03 of the
clean case, 01 to 04 and 11 of the spec case and 01 to 03 and 10 of the tooling case judge the
review itself (what it finds and whether it is right); the rest check what the skill promises the
calling session (a short message that points to the full review, an untouched repository,
`review.md` and `handoff.md`), which a review without the skill is not expected to meet.

Start the grading subagents with `model: sonnet`: each checks one condition against files already
written. Start the runners, with and without the skill, with `model: opus`, the model a user's
session calls the skill from: without it they would inherit the session's `sonnet`, and the run
without the skill would stop being a fair baseline for the `opus` lenses inside it.

Results go to `.audit/vm-review-workspace/` (ignored by git), never next to the skill: they hold
diffs and reports that do not belong in the repository.
