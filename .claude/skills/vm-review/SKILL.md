---
name: vm-review
description: Use when the user wants a code review of a pull request, a git range or the current branch of this repository. The review runs in background subagents, so this session only receives a short summary; afterwards it offers to post the review on the pull request or hand the fix off to a new session.
argument-hint: "[<pr-number>|<range>] [--lenses a,b] [--spec <path>] [--full]"
disable-model-invocation: true
allowed-tools: Agent, AskUserQuestion, Bash(gh pr view:*), Bash(gh pr comment:*), Bash(claude --bg:*)
---

# vm-review

A useful review reads the whole diff, the code around it and the project rules, and looks at the
change from several angles: does it work, does it fail loudly, does it follow `AGENTS.md`, is it
tested, are the docs true, does it do what the statement asks. Doing that in this session would
fill it with diffs and file dumps that the user then has to work around. So this session only
starts one orchestrator in the background and deals with what it returns; the reading happens in
subagents.

## Launch

Start one subagent and nothing else:

- `subagent_type`: `general-purpose`
- `model`: `sonnet` (it coordinates; the reviewers it starts pick their own models)
- `run_in_background`: `true`, so the user can keep working
- `description`: `vm-review $ARGUMENTS`
- `prompt`, exactly:

  ```
  Read ${CLAUDE_SKILL_DIR}/agents/orchestrator.md and follow it.
  Arguments: $ARGUMENTS
  Skill dir: ${CLAUDE_SKILL_DIR}
  ```

Then tell the user, in one line and in their language, that the review is running and the summary
will arrive here. Leave the diff alone: reading it here is exactly what the subagents are for.

## When the result arrives

A single line starting with `vm-review:` means the review could not run (unknown pull request,
empty diff, bad argument). Relay it and stop.

Otherwise:

1. Relay the summary in the user's language, in at most 250 words: the verdict, the counts, the
   `FAILED` line unless it says `none` (a lens or check that failed means part of the change went
   unreviewed, which the user must not miss), the top findings (at most five, one line each with its `file:line`; name no others, the counts
   already say how many there are) and the path of `review.md`.
   Keep the rest of the review out of this session unless the user asks for it, for the same
   reason as above; the path is how they get to it.
2. Ask with `AskUserQuestion` (`multiSelect: true`) what to do with the review:
   - **Post on the pull request**, only when `TARGET` is a pull request. Mention that the
     repository is public, so the comment is visible to anyone.
   - **Hand off the fix**: start a new background session that works through the findings.
   - **Nothing**: keep the local files only.
3. Do what was chosen:
   - Post: check the pull request is still open (`gh pr view <n> --json state`), then
     `gh pr comment <n> --body-file <REVIEW path>`, and give the comment URL. If a step fails,
     show the error rather than retrying blindly.
   - Hand off: from the repository folder the review ran in, start the session with
     `claude --bg --name vm-fix-<target> "Read <HANDOFF path> and follow it."`, where `<target>`
     is `pr<n>` for a pull request and the review folder's name otherwise (the naming rule is in
     `AGENTS.md`), and give the user the id it prints, with
     `claude attach <id>` to join it and `claude logs <id>` to read it. It is a background
     session rather than a headless `claude -p` one because fixing may need the user: it shows
     in `claude agents`, they can attach, and it can ask them a question instead of guessing.
     If it does not start, give the same command for the user to run.

Fixing is the handoff session's job, not this one's: here the user has just been told what is
wrong, and changing code now would mix the review with its fix.

## What is in this folder

| Path | What it is | Who reads it |
|---|---|---|
| `agents/orchestrator.md` | Resolves the target, runs the lenses, merges, verifies, writes the files | the orchestrator |
| `agents/reviewer.md` | The contract every lens reviewer follows: read-only, finding format | each reviewer |
| `agents/verifier.md` | How to try to refute a finding | each verifier |
| `references/lenses/*.md` | One checklist per angle of review | the reviewer of that lens |
| `assets/*-template.md` | Skeletons of `review.md` and `handoff.md` | the orchestrator |
| `evals/` | Test cases in the skill-creator layout, run against local `eval/*` tags | whoever improves the skill |
