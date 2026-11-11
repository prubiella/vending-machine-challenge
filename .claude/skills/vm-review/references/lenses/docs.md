# Lens: docs

Whether what the change says is true. Check every factual claim in the changed `README.md`,
`AGENTS.md`, other Markdown, code comments, `make help` texts and the pull request body against the
code.

Check:

- Commands, make targets, file paths, ports, hostnames and versions exist and do what the text
  says. Read the `Makefile` rather than running targets.
- Claims about behaviour ("idempotent", "enforced by the hook", "checked by deptrac") hold. Find the
  code that makes each one true, or report it.
- Instructions that a reader would follow step by step work in that order, including undo steps.
- Comments: no comment narrates what the code does; a comment that records a non-obvious reason is
  fine. Leftover recipe or boilerplate comments are Minor findings.
- Prose written for people (Markdown, pull request body): English, no em dash or en dash, no
  company names.

Each finding quotes the wrong text and gives the corrected text in `fix`.

Not this lens: whether the code is right (correctness); only whether the text matches it.
