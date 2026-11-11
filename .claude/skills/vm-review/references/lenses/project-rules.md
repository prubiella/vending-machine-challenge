# Lens: project-rules

Compliance with `AGENTS.md`. Cite the section of `AGENTS.md` each finding breaks.

Check:

- Layers: `src/Domain` is plain PHP (no Symfony, no Doctrine, no attributes); `src/Application`
  depends only on Domain. deptrac does not see vendor imports, so grep the changed files under
  `src/Domain` and `src/Application` for `use Symfony\`, `use Doctrine\` and attributes yourself.
- Use cases: one folder per use case, `<UseCase>Command` / `<UseCase>CommandHandler`,
  `<UseCase>Query` / `<UseCase>QueryHandler`; handlers registered by suffix in
  `config/services.yaml`, never `#[AsMessageHandler]`; the rule lives in the aggregate or a value
  object, the handler only loads, calls the domain and saves; commands return nothing.
- HTTP: one invokable controller per endpoint extending `BaseController`, the route in
  `config/routes.php` (no `#[Route]`), bodies bound with `#[MapRequestPayload]`, each new domain
  exception mapped in `JsonExceptionListener`.
- Code: `declare(strict_types=1)`, `final` and `readonly` by default, constructor promotion, money
  in integer cents, no comments that narrate behaviour, PHPDoc only for types PHP cannot express.
- Configuration: new variables in `.env.dist` with a safe default, no real secret committed, no
  company names anywhere.
- History: every commit message in the target follows Conventional Commits in English
  (`git log --format='%h %s' <range>`); the branch name follows the `AGENTS.md` rule; each commit
  leaves the project working (flag a commit that obviously cannot, such as one adding a class that
  only a later commit registers).

Not this lens: whether the code works (correctness) or reports failures (silent-failures).
