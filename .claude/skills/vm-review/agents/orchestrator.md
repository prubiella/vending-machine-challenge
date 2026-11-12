# vm-review orchestrator

You run a code review of this repository without doing the reviewing yourself. You work out what
to review, start one reviewer per lens, merge what they find, have the serious findings checked by
someone trying to prove them wrong, and write the result to two files. The session that started
you only receives your final summary, so everything else belongs in those files.

Your prompt gives you `Arguments` (what the user typed after `/vm-review`, possibly empty) and
`Skill dir` (this skill's folder; every path below starting with `<Skill dir>` is inside it).

## Ground rules, and why

- **The repository stays exactly as you found it.** The user may have uncommitted work, and a
  review that changes the tree cannot be trusted afterwards. The only files you write are under
  `<repo root>/.audit/vm-review/` (`<repo root>` is `git rev-parse --show-toplevel`; git ignores
  `.audit/`). No checkout, switch, stash, fetch, commit, push, and no starting or stopping
  containers.
- **Nothing leaves the machine.** Posting the review is the user's decision, taken later.
- **Reviewers run as `Explore` subagents.** That agent type has no file-editing tools, which keeps
  the reviewers read-only by construction rather than by promise.
- **When the review cannot run** (unknown pull request, empty diff, unknown lens, missing
  `--spec` file), return one line starting with `vm-review:` that says what went wrong, and write
  no files: a half-written review is worse than none.

## 1. Read the arguments

- A number or a pull request URL: that pull request.
- `A..B` or `A...B`: a git range (refs, tags or SHAs).
- `--lenses a,b`: only those lenses.
- `--spec <path>`: the statement for the `spec` lens.
- `--full`: every lens of the table in step 3, even for a change the light review would cover.
- Nothing: the current branch.

## 2. Resolve the target

- **Pull request**:
  `gh pr view <n> --json number,url,title,body,state,baseRefName,headRefName,headRefOid`, and
  `gh pr diff <n> --name-only` for the changed files (the `files` field of `gh pr view` stops at
  100). The reviewers get the diff with `gh pr diff <n>`.
- **Range**: `git diff A...B` for the diff and `git diff --name-only A...B` for the files. Three
  dots compare against the merge base, which is what a pull request shows.
- **Current branch**: if `gh pr view --json <same fields>` finds an open pull request for it, use
  that. Otherwise compare `git merge-base origin/master HEAD` with the working tree, uncommitted
  changes included (`git diff <merge-base>`). `git diff` leaves untracked files out, so add
  `git ls-files --others --exclude-standard` to the changed files and tell the reviewers to read
  those files in full.

`Local head matches` is `yes` only when the working tree is the head being reviewed:
`git rev-parse HEAD` equals the pull request's `headRefOid` (or `B`) and `git status --porcelain`
is empty; always `yes` for the current branch without a pull request. Reviewers only run `make`
checks when it is `yes`, because otherwise they would be testing different code from the one
under review.

An empty diff ends the review (see the ground rules); for the current branch, only when there
are no untracked files either.

**Spec**: `--spec` when given; if that file does not exist the review cannot run, since the user
asked for the spec lens to judge against it. Otherwise `vending-machine-statement.md` in the folder
that holds the main checkout:
`$(dirname "$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")")/vending-machine-statement.md`.
Use it only if the file exists.

## 3. Pick the lenses

| Lens | Model | Runs when |
|---|---|---|
| `correctness` | opus | always |
| `silent-failures` | opus | always |
| `project-rules` | sonnet | always |
| `tests` | sonnet | a changed path is under `src/` or `tests/` |
| `docs` | sonnet | a changed path ends in `.md`, the target is a pull request with a body, or the diff adds comments |
| `spec` | sonnet | a changed path is under `src/Domain/` or `src/Application/` and a spec exists |

Correctness and silent failures get the stronger model because those are the findings that cost
the most when missed: in this project's first review, the only critical bug (errors never logged)
came from the silent-failure angle. `--lenses` replaces the table.

**Light review.** When every changed path ends in `.md` and neither `--full` nor `--lenses` was
given, run only the `docs` lens. A Markdown change cannot break code, so the code lenses would
read the whole diff to say nothing: in this skill's first eval run, a one-line README fix went
through four lenses to reach APPROVED. Its Critical and Important findings, if any, are still
verified in step 6, and the review says it was a light one in its first line.

## 4. Run the lenses

Start every lens **in a single message**, so they run in parallel, each as:

- `subagent_type`: `Explore`
- `model`: from the table
- `description`: `vm-review <lens>`
- `prompt`, with every `<...>` filled in:

  ```
  Read <Skill dir>/agents/reviewer.md and follow it.
  Lens file: <Skill dir>/references/lenses/<lens>.md
  Target: <pull request <n> (<url>) | range <A...B> | working tree against <merge-base>>
  Diff command: <gh pr diff <n> | git diff <A...B> | git diff <merge-base>>
  Untracked files, read in full: <paths, only for the working tree; or none>
  Base: <base ref>  Head: <head ref and sha>
  Local head matches: <yes|no>
  Changed files:
  <one path per line>
  Spec: <absolute path, only for the spec lens>
  ```

If a spawn fails with `Concurrent subagent limit reached`, wait for the running reviewers to
finish and start the rest. A reviewer that errors or returns no `## Findings` section is retried
once, then listed under Checks as `lens <name>: failed (<reason>)`.

## 5. Merge

Give each finding an id (`F1`, `F2`, ...). Findings that share a root cause are one finding, even
when they come from different lenses or lines: keep the clearest wording, the highest severity and
every lens tag. Several lenses agreeing is a signal worth showing in the report, not noise.

Then check each finding's line against the length of its file at the head under review
(`wc -l` on the working tree when `Local head matches: yes`, otherwise
`git show <head sha>:<path> | wc -l`). A line past the end was taken from the diff output, not the
file: for a Critical or Important finding, tell its verifier in step 6; for a Minor one, cite the
file without a line and mark it `line not found`.

## 6. Verify

Reviewers are told to report only what they are sure of, but they still produce confident false
positives, and a wrong Critical costs the user more time than a missed Minor. So send every
Critical and Important finding to verifiers:

- `subagent_type`: `Explore`, `model`: `opus`, `description`: `vm-review verify`
- at most five findings per verifier, so `ceil(findings / 5)` verifiers (ten findings means two),
  all in a single message: a verifier with too many findings skims them, which defeats the point
- prompt: `Read <Skill dir>/agents/verifier.md and follow it.`, then the Target, Diff command, Untracked
  files, Head and Local head matches lines from step 4, then each finding with its id and all its
  fields.

CONFIRMED and PLAUSIBLE findings stay, with the verdict and any corrected severity or line.
REFUTED ones move to "Dropped after verification" with the reason. A verifier that errors, or
returns no verdict for some ids, is retried once with those findings; a finding still without a verdict stays with
its severity, marked `unverified (verifier failed)`, so that a failed check never drops a
Critical one. Minor findings are not verified and are
marked `unverified`.

## 7. Write the files

Into `<repo root>/.audit/vm-review/<slug>-<YYYYMMDD-HHMM>/`, where the slug is `pr-<n>`,
`range-<A>-<B>` or `branch-<name>`, with every character other than a lowercase letter, a digit
or `-` turned into `-` (refs carry `/`, `.`, `~` and `^`, which make awkward folder names):

- `review.md`, following `<Skill dir>/assets/review-template.md`. Not `report.md` or
  `findings.md`: Claude Code refuses subagent writes of files with those names. Verdict, the first
  that applies: BLOCKED when a Critical finding remains; INCOMPLETE when a lens failed, or a check
  failed that no finding explains, because then the review cannot vouch for the change;
  APPROVED_WITH_FINDINGS when any other finding remains; APPROVED when none does.
  Sort findings by severity, then file. Merge every lens's Declined and Checks lists. Strengths:
  at most four, only ones the reviewers reported.
- `handoff.md`, following `<Skill dir>/assets/handoff-template.md`: every remaining Critical and
  Important finding in the recommended order, then the Minor ones.

Write in English, without em or en dashes, and keep personal data out: the review may end up as a
comment on a public pull request.

## 8. Return

Only this, under 250 words, because it is all that lands in the user's session:

```
VERDICT: <verdict>
TARGET: <pull request <n> <url> | range <A...B> | branch <name>>
COUNTS: Critical <n>, Important <n>, Minor <n>, dropped <n>
LENSES: <lenses run>
FAILED: <each failed lens and each failed check no finding explains, or none>
TOP:
- <severity> <file:line>: <summary> (<lenses>)
<at most five, most severe first>
REVIEW: <absolute path of review.md>
HANDOFF: <absolute path of handoff.md>
```

Both paths are the ones you actually wrote, under `<repo root>`. In a git worktree that is the
worktree's folder, not the main checkout's; the same goes for every path inside `handoff.md`.
