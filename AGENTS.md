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
  targets run its code, not the worktree's. The git hooks do work from a worktree once it has its
  own `vendor/` (`./etc/git/hooks-docker/run-in-php-image.sh composer install`).

## Layers (checked by deptrac)

- `src/Domain`: entities, aggregates, value objects, domain services, repository interfaces and
  domain exceptions. Plain PHP: no Symfony, no Doctrine, no attributes.
- `src/Application`: one folder per use case. `Command/<UseCase>/<UseCase>Command` plus its
  `CommandHandler`, `Query/<UseCase>/<UseCase>Query` plus its `QueryHandler`. Depends only on
  Domain. Handlers are registered on their bus by name suffix in `config/services.yaml`, so keep
  the suffixes and do not use `#[AsMessageHandler]`. Only `query_handlers` exists so far; add the
  matching `command_handlers` block (`bus: command.bus`) with the first command.
- `src/Infrastructure`: Symfony glue, controllers, console commands, persistence adapters.

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
   `config/routes.php`, not in a `#[Route]` attribute. Bind bodies with `#[MapRequestPayload]`.
   Map each domain exception to an HTTP status in `JsonExceptionListener` (it has no map yet: add
   it with the first one).
5. Tests: unit tests in `tests/Unit/` mirroring `src/`, for every domain rule and for every
   handler with logic of its own; integration tests in `tests/Integration/` for wiring that only
   the kernel can prove. A handler with no logic (one that returns a constant) is covered by the
   integration test that routes a message to it, since PHPStan rejects a unit test of it as
   tautological.

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

## Git

- Branches: `feature/<name>` (also `fix/`, `chore/`, `docs/`), lowercase, at most 15 characters
  after the prefix. The pre-commit hook rejects other names.
- Commits: Conventional Commits (`feat(scope): ...`), in English. The `commit-msg` hook enforces it.
- Never `--no-verify`. Push with `FORCE_COLOR=0 git push`.
- Small commits that each leave the project working, and small pull requests that a person can
  review in one sitting: the history is part of the deliverable.
- No company names in code, docs, commits or pull requests.

## Discover, don't guess

Framework APIs change between versions. Check the project before relying on memory:
`make run about`, `make run debug:router`, `make run debug:container`, `make run debug:messenger`,
`make run debug:config <bundle>`, and the sources under `vendor/`.
