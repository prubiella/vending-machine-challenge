# Lens: readability

Whether a reader can follow the changed code as it is written, without running it in their head.
It judges the code that is there; whether less code would do is for the design lens. Report only
what the change adds or rewrites, and say what the reader has to work out and how to spare them.

Look for:

- Nesting: more than two levels of loops or conditions in one method. A guard clause, an early
  return or a named private method usually flattens it.
- A method that does several steps without naming them, so the reader has to execute it to see
  what it is for. Each step that deserves a name gets a private method whose name says the intent.
- Names: a name that describes the mechanism instead of the intent (`$loopResult`, `process()`),
  or is vague or abbreviated (`$r`, `$tmp`, `handle`, `run`, `data`). A boolean named for what it
  holds, not for the question it answers.
- Consistency: the same concept named differently across the change, or sibling classes that do
  the same operation under different names.
- Dense expressions: a chain of calls, a nested ternary or a condition with several `&&` and `||`
  that needs mental execution; extract it into a variable or method with a name.
- Tests: a test whose name does not say what it proves, or whose arrange, act and assert cannot be
  told apart.

Name the fix concretely (the method to extract and what to call it, the name to use). Severity is
Minor by default; Important only when the central logic of the change cannot be followed without
executing it.

Not this lens: bugs (correctness), compliance with `AGENTS.md` (project-rules), structure and
unneeded pieces (design), formatting (php-cs-fixer).
