# Lens: design

Whether the change is built with the fewest pieces that put each rule where it belongs. It judges
structure, not whether the code works. Judge what the change adds or moves, not the design of code
it leaves alone. Each finding names the piece and where it should live or what could go, and says
what that costs: the next change that gets harder, or the reader who is misled.

Look for:

- Placement: a rule held by a class that only coordinates or stores state (an aggregate, a
  handler, a controller) when a collaborator that exists to decide it (a policy, a strategy, a
  value object) could own it. A caller that adjusts, filters or pre-computes what it hands a
  collaborator so that the collaborator "stays generic" is a rule in the wrong place: the
  collaborator cannot be tested or changed for it. Behaviour in a layer that does not own it,
  judged by the layers the project has (the written rules are for project-rules).
- Cohesion: a class or method with two jobs; a data object that grows behaviour for one caller.
- Speculative structure: an interface with one implementation and no second one in sight, a
  wrapper or layer with a single trivial use, an option, parameter or hook nobody asked for.
- Duplication: the same logic in two places that must change together, or hand-written code where
  the language, the framework or a class of the project already does it.
- Dead weight: unused parameters, properties, methods or branches, and handling for a case that
  cannot happen.

Name the alternative concretely (which class would own the rule, which piece would go), or do not
report it. A choice between two reasonable designs, where neither is wrong, goes under Decisions,
not Findings. Severity is Minor by default; Important when keeping the structure means a caller
must know a rule it should not, or one rule has to be changed in two places.

Not this lens: bugs (correctness), compliance with `AGENTS.md` (project-rules), names and how the
code reads (readability), formatting (php-cs-fixer).
