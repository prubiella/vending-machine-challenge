# AGENTS.md

Instructions for coding agents (and humans) working on this repository. `CLAUDE.md` imports this
file, so it is the single source for every tool.

## Project

A vending machine modelled with DDD, CQRS and a hexagonal architecture: PHP 8.5, Symfony 8.1,
MongoDB 9.0 for the machine, MySQL 8.4, nginx behind Traefik with HTTPS. `README.md` explains how
to run it.

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
  `./etc/git/hooks-docker/run-in-php-image.sh composer install`). `run-in-php-image.sh` joins
  `vending_machine_network` when it exists, so the checks it runs, the pre-commit hook's included,
  reach the mysql and mongo services: start the containers before committing, or the database
  tests fail. `post-checkout` also copies `.env.dist` to `.env` in a new worktree (it holds no
  secrets), so a Flex recipe run there finds one and writes no `.env` of its own.

## Bounded contexts

`src/` is split by bounded context first and by layer second: `src/<Context>/<Layer>/`. Only
`src/Kernel.php` lives outside a context. A folder exists once it holds code, never empty.

- `Shared`: a small, stable shared kernel that every context may use and that uses no context.
  `Shared/Domain` holds `Money`, `Uuid` (the base of every identifier) with its
  `InvalidUuidException`, and `DomainException`, the abstract base that every domain exception of
  every context extends; `Shared/Application/Bus` holds the `Command` and `Query` interfaces and
  the `CommandBus` and `QueryBus` ports, and `Shared/Application/Uuid` the `UuidGenerator` port;
  `Shared/Infrastructure` holds their Messenger implementations (`MessengerCommandBus`,
  `MessengerQueryBus`), `SymfonyUuidGenerator`,
  `UnpackExceptionMiddleware`, `BaseController`, `JsonExceptionListener` and the health check
  (liveness on `/health-check`, readiness on `/health-check/ready` and `app:health-check --ready`;
  a new dependency is one more probe port and one more entry in `HealthCheckResponse::$checks`).
  Only what several contexts need belongs here.
- `VendingMachine`: the machine of the statement (coins, products, change, service).
- `Maintenance` (planned): technicians and their login, with technician authentication.

## Layers (checked by deptrac)

Every context has the same three layers:

- `Domain`: entities, aggregates, value objects, domain services, repository interfaces and
  domain exceptions. Plain PHP: no Symfony, no Doctrine, no attributes. Depends only on
  `Shared/Domain`. To log what a domain service does, decorate it in Infrastructure, as
  `LoggingChangeMaker` decorates `ChangeMaker` in `config/services.yaml`.
- `Application`: one folder per use case. `Command/<UseCase>/<UseCase>Command` plus its
  `CommandHandler`, `Query/<UseCase>/<UseCase>Query` plus its `QueryHandler`. Depends only on its
  own Domain, `Shared/Domain` and `Shared/Application`. Handlers are registered on their bus by
  name suffix in `config/services.yaml`, for every context at once
  (`src/*/Application/Command/**/*CommandHandler.php` on `command.bus`,
  `src/*/Application/Query/**/*QueryHandler.php` on `query.bus`), so keep the suffixes and do not
  use `#[AsMessageHandler]`.
- `Infrastructure`: Symfony glue, controllers, console commands, persistence adapters. The only
  layer that may use the framework (Symfony, Doctrine, PSR, MongoDB). Depends on its own Domain and
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
  `$this->command(...)` or `$this->query(...)`; other entry points, such as console commands,
  depend on the `CommandBus` and `QueryBus` ports (`dispatch()`, `query()`), never on the
  Messenger classes that implement them.
- Commands return nothing. What a command produces is state that a query reads.
- `UnpackExceptionMiddleware` rethrows the handler's original exception.
- `LoggingCommandMiddleware` logs every command: info when handled, notice for a
  `DomainException`, error for anything else. It comes first on `command.bus`, so it sees the
  unpacked exception and logs a retried command once. It logs the command's public fields: a
  command carrying a secret or personal data implements `SensitiveCommand` and maps each such
  field to a `Masking` rule, `Hide` for secrets (`***`) and `KeepFirstThree` for personal data
  (`pau***`).
- A repository saves with optimistic locking: it throws `ConcurrentUpdateException`
  (`Shared/Application/Persistence`) when another save got in since the load.
  `RetryOnConcurrentUpdateMiddleware` runs the command again, up to three more times, so a handler
  loads what it changes inside `__invoke()`. It must stay right before `handle_message`, which is why
  `command.bus` lists its middleware without the defaults.

## Adding a use case

1. Domain: the rule lives in the aggregate or a value object, never in a handler.
2. Application: the command or query and its handler, which only loads, calls the domain and saves.
3. Infrastructure: the adapter (controller, console command, repository). `config/services.yaml`
   leaves `Domain` out of autowiring, so alias a domain port to its adapter there
   (`VendingMachineRepository` to `MongoVendingMachineRepository`).
4. HTTP: one invokable controller per endpoint, extending `BaseController`. The route goes in
   the context's `config/routes/<context>.php`, not in a `#[Route]` attribute. Bind bodies with
   `#[MapRequestPayload]`. A customer-facing refusal gets its status and `log_level: info`
   under `framework.exceptions` in `config/packages/framework.yaml`, the one place that decides both.
   Any other exception answers 500 and is logged as critical. Every new domain exception goes on one
   side or the other: mapped there, or listed in `app.domain_exceptions_answering_500` in the same
   file when the HTTP API cannot reach it or it means a broken invariant;
   `DomainExceptionStatusTest` fails otherwise. Add the request to
   `etc/postman/vending-machine.postman_collection.json`, in its context's folder, with `{{baseUrl}}`
   for the host and test scripts for the status and body. Document the route in
   `docs/openapi.yaml` with every status it can answer and an example of each:
   `OpenApiDocumentTest` fails while a route and the document disagree, or an operation of the
   machine lacks its 500 (a command also its 409); the statuses of its own are for you to list.
5. Scenario: a customer use case gets a scenario in `features/customer/<topic>.feature`, written in
   the customer's words, reusing the steps of `tests/Behat/CustomerContext.php` and adding one only
   when no step says it. Feature files hold no PHP and no HTTP details.
6. Tests: unit tests in `tests/Unit/<Context>/` mirroring `src/<Context>/`, for every domain rule
   and for every handler with logic of its own; integration tests in `tests/Integration/<Context>/`
   for wiring that only the kernel can prove. A handler with no logic (one that returns a
   constant) is covered by the integration test that routes a message to it, since PHPStan rejects
   a unit test of it as tautological.

## Code

- `declare(strict_types=1)`, `final` and `readonly` by default, constructor property promotion.
- Domain objects keep their properties private and expose them through methods (`Money::cents()`).
  Value objects have a private constructor and are built through named constructors
  (`Money::fromCents()`, `Selector::fromString()`, `Product::of()`). DTOs (commands, queries,
  their results, request payloads) are data without behaviour: `final readonly` classes with
  public promoted properties.
- Money is integer cents, never floats: use `Shared\Domain\Money\Money`.
- An identifier is a `final readonly` class extending `Shared\Domain\Uuid\Uuid` (`MachineId`); a
  new one comes from the `UuidGenerator` port, never from the domain (ADR 17).
- A domain exception is a `final` class named after what went wrong and ending in `Exception`
  (`InsufficientMoneyException`), that extends `Shared\Domain\DomainException` and is built
  through a named constructor (`InsufficientMoneyException::toSubtract(...)`). That base is a
  `\RuntimeException`, since a business rule can refuse an operation while the code is correct;
  `\LogicException` is kept for programming errors, such as a message with no handler.
- Names explain the code. No comments that narrate behaviour; a comment is fine when it records a
  non-obvious reason. PHPDoc only for types PHP cannot express (`list<Coin>`, array shapes).

## Checks

`make test`, `make behat`, `make phpstan` (max level), `make deptrac`, `make cs-fixer`, or
`make code-check` for all of them. They must pass before committing. The integration tests use the
`app_test` databases of MySQL and MongoDB: `make migrate` brings the MySQL one up to date (from a
worktree, `bin/console doctrine:migrations:migrate -n --env=test` through `run-in-php-image.sh`),
and a kernel test that touches the machine uses `StartsWithNoStoredMachine`, so it starts from the
default machine. A new adapter of `VendingMachineRepository` gets a test case that extends
`VendingMachineRepositoryContractTestCase`. Behat (`behat.dist.php`, the FriendsOfBehat Symfony
extension) runs the scenarios of `features/` against the kernel through BrowserKit, on the same
test databases; `CustomerContext` deletes the stored machine before each scenario. `tests/Behat/`
is outside `src/`, so deptrac does not see it, and PHPStan analyses it with the rest of `tests/`.
`make hooks` (part of `make first-run`) enables the git hooks: pre-commit runs the checks when
code, configuration or tooling is staged, and refuses the commit when those staged files also have
unstaged changes, since the checks read the disk.

`/vm-review [<pr-number>|<range>]` reviews a change in a headless `claude -p` session in the
background and writes the report to `.audit/vm-review/`; the lenses live in
`.claude/skills/vm-review/references/lenses/`. The skill only runs when the developer types it
(it costs tokens and ends by offering to post on a public pull request). When asked in prose for a
review, follow the steps of its `SKILL.md` instead of improvising another way to launch it.

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
   Xdebug point, with `git switch --detach <branch>`: a branch can be checked out in one worktree
   only, and a detached checkout leaves it free, so the session keeps working on it while the
   developer tests. The session and its worktree go (`claude rm <id>`) once its branch is pushed.
3. A branch the developer has tested is pushed and its pull request opened
   (`gh pr create --assignee @me`); fixes for a review of an open pull request are pushed to its
   branch as they land. A force push, anything on `master`, discarding unpushed work and merging
   always wait for the developer.
4. A review (`/vm-review`) runs when the developer asks for it, not as soon as a pull request
   opens. It does not need the main checkout at the reviewed head: CI runs the checks on every
   pull request.

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
