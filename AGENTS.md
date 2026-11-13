# AGENTS.md

Instructions for coding agents (and humans) working on this repository. `CLAUDE.md` imports this
file, so it is the single source for every tool.

## Project

A vending machine modelled with DDD, CQRS and a hexagonal architecture: PHP 8.5, Symfony 8.1,
MySQL 8.4, nginx behind Traefik with HTTPS. `README.md` explains how to run it.

## Everything runs in the containers

- Use the `make` targets or `docker compose exec php-fpm ...`. Never a host PHP, Composer or
  `symfony serve`.
- `make help` lists the targets. `make run -- <command> <options>` runs `bin/console`; give an
  option its value after a space (`--format json`), since make takes `--format=json` as a variable.
- Add dependencies with `make require <package>` / `make require-dev <package>`, so the Flex
  recipe registers the bundle. Review what a recipe generates before committing: its defaults
  (attribute routing, YAML comments, sample folders) may clash with the conventions below.
- `.env` is git-ignored and created from `.env.dist`. New variables go in `.env.dist` with a safe
  default. Never commit a real secret; `.env.dev` and `.env.test` only hold non-secret values:
  Symfony's throwaway `APP_SECRET` for those environments and, in `.env.test`, `KERNEL_CLASS`.
- Work on a branch rather than a git worktree: the containers bind-mount the main checkout, so `make`
  targets run its code, not the worktree's. The git hooks do work from a worktree: `post-checkout`
  installs its `vendor/` when git creates it (by hand:
  `./etc/git/hooks-docker/run-in-php-image.sh composer install`).

## Bounded contexts

`src/` is split by bounded context first and by layer second: `src/<Context>/<Layer>/`. Only
`src/Kernel.php` lives outside a context. A folder exists once it holds code, never empty.

- `Shared`: a small, stable shared kernel that every context may use and that uses no context.
  `Shared/Application/Bus` holds the `Command` and `Query` interfaces; `Shared/Infrastructure`
  holds the Messenger buses, `UnpackExceptionMiddleware`, `BaseController`,
  `JsonExceptionListener` and the health check. Only what several contexts need belongs here.
- `VendingMachine`: the machine of the statement (coins, products, change, service). Its layers
  are ready in deptrac; its folder arrives with its first code.
- `Maintenance` (planned): technicians and their login, with technician authentication.

## Layers (checked by deptrac)

Every context has the same three layers:

- `Domain`: entities, aggregates, value objects, domain services, repository interfaces and
  domain exceptions. Plain PHP: no Symfony, no Doctrine, no attributes. Depends only on
  `Shared/Domain`.
- `Application`: one folder per use case. `Command/<UseCase>/<UseCase>Command` plus its
  `CommandHandler`, `Query/<UseCase>/<UseCase>Query` plus its `QueryHandler`. Depends only on its
  own Domain, `Shared/Domain` and `Shared/Application`. Handlers are registered on their bus by
  name suffix in `config/services.yaml`, for every context at once
  (`src/*/Application/Query/**/*QueryHandler.php`), so keep the suffixes and do not use
  `#[AsMessageHandler]`. Only `query_handlers` exists so far; add the matching `command_handlers`
  block (`src/*/Application/Command/**/*CommandHandler.php`, `bus: command.bus`) with the first
  command.
- `Infrastructure`: Symfony glue, controllers, console commands, persistence adapters. The only
  layer that may use the framework (Symfony, Doctrine, PSR). Depends on its own Domain and
  Application and on all of `Shared`.

deptrac allows each context only its own layers and `Shared`, so no context uses another
context's Domain, and `Shared` uses no context. `make deptrac` also fails when a class in `src/`
belongs to no layer (`deptrac debug:unassigned`) or a dependency points outside every layer
(`--fail-on-uncovered`): a new vendor namespace goes into `Framework` or a layer of its own.

## Adding a bounded context

1. Folder: `src/<Context>/` with the layers that have code, tests in `tests/Unit/<Context>/` and
   `tests/Integration/<Context>/`.
2. `deptrac.yaml`: the layers `<Context>Domain`, `<Context>Application` and
   `<Context>Infrastructure` (collector `^App\\<Context>\\<Layer>\\.*`) and their rules, copied
   from the `VendingMachine` entries, in the same commit as the context's first class: without
   them `make deptrac` and the pre-commit hook fail on its unassigned classes.
3. Routes: `config/routes/<context>.php` (snake_case). The kernel imports every file in
   `config/routes/`, so nothing else references it.
4. `config/services.yaml`: nothing to add. Its exclusions and handler resources use `src/*/`, so
   they match the new context as long as the use case naming above is kept.

## Buses

- `command.bus` and `query.bus` run synchronously. Controllers extending `BaseController` call
  `$this->command(...)` or `$this->query(...)`.
- Commands return nothing. What a command produces is state that a query reads.
- `UnpackExceptionMiddleware` rethrows the handler's original exception.

## Adding a use case

1. Domain: the rule lives in the aggregate or a value object, never in a handler.
2. Application: the command or query and its handler, which only loads, calls the domain and saves.
3. Infrastructure: the adapter (controller, console command, repository).
4. HTTP: one invokable controller per endpoint, extending `BaseController`. The route goes in
   the context's `config/routes/<context>.php`, not in a `#[Route]` attribute. Bind bodies with
   `#[MapRequestPayload]`. Map each domain exception to an HTTP status in `JsonExceptionListener`
   (it has no map yet: add it with the first one).
5. Tests: unit tests in `tests/Unit/<Context>/` mirroring `src/<Context>/`, for every domain rule
   and for every handler with logic of its own; integration tests in `tests/Integration/<Context>/`
   for wiring that only the kernel can prove. A handler with no logic (one that returns a
   constant) is covered by the integration test that routes a message to it, since PHPStan rejects
   a unit test of it as tautological.

## Code

- `declare(strict_types=1)`, `final` and `readonly` by default, constructor property promotion.
- Money is integer cents, never floats.
- Names explain the code. No comments that narrate behaviour; a comment is fine when it records a
  non-obvious reason. PHPDoc only for types PHP cannot express (`list<Coin>`, array shapes).

## Checks

`make test`, `make phpstan` (max level), `make deptrac`, `make cs-fixer`, or `make code-check` for
all of them. They must pass before committing. `make hooks` (part of `make first-run`) enables the
git hooks: pre-commit runs the checks when code, configuration or tooling is staged, and refuses
the commit when those staged files also have unstaged changes, since the checks read the disk.

`/vm-review [<pr-number>|<range>]` reviews a change in background subagents and writes the report
to `.audit/vm-review/`; the lenses live in `.claude/skills/vm-review/references/lenses/`.

Every Claude session launched from this repository (`claude --bg` or `claude -p`) gets
`--name vm-<type>-<target>[-<topic>]`: `vm-fix-pr7` for a fix, `vm-fix-pr7-rules` when one pull
request gets more than one, `vm-review-pr7` for a review, `vm-eval-it6` for an eval run,
`vm-lens-docs-quiet` for a lens test. The `vm-` prefix tells this project's sessions apart in
`claude agents`, and the type first groups them.

## Working with agent sessions

The project is built with Claude Code sessions that one orchestrating session coordinates. The
steps below take a change from a session to `master`. They live in this file on purpose, so
whoever reviews the repository can see how the work was done; in a team they would usually sit in
each developer's own agent memory or settings, since they describe a personal way of working
rather than a rule of the codebase.

1. A session that changes code runs in its own git worktree (`claude --bg -w <name>`), so the
   main checkout stays untouched while it works. It commits on its branch, runs the checks with
   `./etc/git/hooks-docker/run-in-php-image.sh`, and stops there: no push, no pull request.
2. The developer tests the branch in the main checkout, where the containers, `make`, the IDE and
   Xdebug point. A branch can be checked out in one worktree only, so the session and its
   worktree go first (`claude rm <id>`), then `git switch <branch>`.
3. Only with the developer's approval is the branch pushed and a pull request opened
   (`gh pr create --assignee @me`).
4. A review (`/vm-review`) runs when the developer asks for it, not as soon as a pull request
   opens.

## Changing a skill

Skills live in `.claude/skills/<name>/`. Test a change with the cheapest check that covers it:

| What changed | Run | Cost |
|---|---|---|
| Anything under `.claude/skills/` | `.claude/scripts/skill-check.sh`, which pre-commit runs: frontmatter, cited paths, eval cases and their tags, no em or en dashes | no tokens |
| A part with tests of its own, such as a vm-review lens or `agents/reviewer.md` | its tests: `.claude/skills/vm-review/lens-tests/run.sh [<test> ...]` | 50k to 80k input tokens per test, on sonnet |
| What the skill does (`SKILL.md`, the orchestrator, the models it picks), before merging | one eval iteration of the affected cases, as the skill's `evals/README.md` says | millions of tokens |

A headless session that tests a skill runs with `--setting-sources project --strict-mcp-config`,
so the user's own plugins, hooks, MCP servers and effort settings neither change the result nor
add to its cost (`CLAUDE.md` files still load). A plugin the session needs, such as skill-creator
for an eval, comes back with `--plugin-dir`. A skill whose parts can be tested apart gives them
cheap tests like vm-review's `lens-tests/`; one without goes from `skill-check` to its evals.

## Git

- Branches: `feature/<name>` (also `fix/`, `chore/`, `docs/`, `refactor/`), lowercase, at most
  15 characters after the prefix. The pre-commit hook rejects other names.
- Commits: Conventional Commits (`feat(scope): ...`), in English. The `commit-msg` hook enforces it.
- Never `--no-verify`. Push with `FORCE_COLOR=0 git push`.
- Small commits that each leave the project working, and small pull requests that a person can
  review in one sitting: the history is part of the deliverable.
- Never write the name of the company that set this challenge, anywhere: code, tests, evals, docs,
  commits, pull requests, reviews or comments. Do not look it up in the statement or search the
  code for it; if you need to refer to it, say "the company".

## Discover, don't guess

Framework APIs change between versions. Check the project before relying on memory:
`make run about`, `make run debug:router`, `make run debug:container`, `make run debug:messenger`,
`make run debug:config <bundle>`, and the sources under `vendor/`.
