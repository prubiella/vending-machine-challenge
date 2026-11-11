---
name: vm-review
description: Use when the user wants a code review of a pull request, a git range or the current branch of this repository. The review runs in background subagents; afterwards it offers to post the review on the pull request or hand the fix off to another session.
argument-hint: "[<pr-number>|<range>] [--lenses a,b] [--spec <path>]"
disable-model-invocation: true
allowed-tools: Agent(vm-review-orchestrator), AskUserQuestion, Bash(gh pr view:*), Bash(gh pr comment:*)
---

# vm-review

You do not review anything yourself, and you do not read the diff: that would fill this session,
which is what the subagents are for. You start the orchestrator, then handle its result.

## Launch

Start one subagent and nothing else:

- `subagent_type`: `vm-review-orchestrator`
- `run_in_background`: `true`
- `description`: `vm-review $ARGUMENTS`
- `prompt`, exactly:

  ```
  Arguments: $ARGUMENTS
  Skill dir: ${CLAUDE_SKILL_DIR}
  ```

Then tell the user, in one line and in their language, that the review is running in the
background and the summary will arrive here. Do not wait, poll or run git commands.

## When the result arrives

If it is a single line starting with `vm-review:`, relay it and stop.

Otherwise:

1. Relay the summary in the user's language: verdict, counts, the top findings with their
   `file:line`, and the path of the report. Do not read `report.md` into this session unless the
   user asks for it.
2. Ask with `AskUserQuestion` (`multiSelect: true`) what to do with the review. Options:
   - **Post on the pull request**, only when `TARGET` is a pull request. Say that the repository is
     public and the comment will be visible.
   - **Hand off the fix**: a command to fix the findings from a new session.
   - **Nothing**: keep the local files only.
3. Do what was chosen:
   - Post: check the pull request is open (`gh pr view <n> --json state`), then
     `gh pr comment <n> --body-file <REPORT path>` and give the comment URL it prints. If either
     step fails, show the error and do not retry.
   - Hand off: give this command, to run from the repository folder:

     ```
     claude "Read <HANDOFF path> and follow it."
     ```

Never fix findings, edit files or commit from this skill.
