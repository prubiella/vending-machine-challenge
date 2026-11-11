# Lens: silent-failures

Places where something fails and nobody finds out: the user sees success, the log is empty, or the
exit code is 0.

Look for:

- PHP: empty or broad `catch`, exceptions turned into `null`, `false` or a default value, fallbacks
  that hide a bug, `@` suppression, errors mapped to a generic 500 without logging, messages of
  5xx responses leaked outside debug.
- Symfony: the order of the exception listener and the error listener, Messenger exceptions
  unwrapped or lost (`HandlerFailedException` with several handlers, a query with no
  `HandledStamp`), monolog handlers or channel filters that drop errors (`request`, `messenger`,
  `deprecation`), deprecations that hide a future break.
- Shell and Makefile: missing `set -e` or `pipefail`, `|| true`, `2>/dev/null` on commands whose
  failure matters, the `-` prefix in make recipes, catch-all targets that turn typos into a pass,
  pipes that mask the exit code of the first command.
- Git hooks: checks that print a warning but exit 0, file filters that skip renamed, deleted or
  out-of-scope files and still print "passed", hooks that check the working tree instead of the
  index.
- Configuration: defaults that silently disable a check, a tool that is skipped when missing instead
  of failing.

For each finding, say what the user or developer sees and what they should have seen.

Not this lens: logic bugs where an error is raised correctly (correctness).
