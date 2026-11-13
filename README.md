# Vending machine

A vending machine modelled in PHP: it accepts coins (0.05, 0.10, 0.25 and 1), sells products
(Water 0.65, Juice 1.00, Soda 1.50), gives change, returns the inserted coins on request and lets
a technician service it by setting the available change and the stock of each product.

Work in progress: the project is being built in small pull requests, infrastructure first.

## Stack

- PHP 8.5 (FPM, with Xdebug) and Symfony 8.1
- MySQL 8.4
- nginx behind Traefik 3.7, HTTPS end to end
- PHPUnit 13, PHPStan 2 (max level), deptrac, php-cs-fixer

Everything runs in Docker: the host needs no PHP, Composer or MySQL.

## Requirements

- Docker Engine with Compose v2 (`docker compose`)
- GNU make
- [mkcert](https://github.com/FiloSottile/mkcert#installation), for the local HTTPS certificate
- `sudo` rights, once, to add the hostname to `/etc/hosts` and trust the mkcert authority
- Free ports on localhost: 80, 443, 8080 (Traefik dashboard) and 3307 (MySQL). They can be changed
  in `.env`.

## First run

```bash
make first-run
```

It creates `.env` from `.env.dist`, writes your user and group ids into it, prepares the host (see
below), builds and starts the containers, installs the Composer dependencies and enables the git
hooks. Then:

```bash
curl https://vending-machine.test/health-check
# {"status":"ok"}
make run -- app:health-check
# {"status":"ok"}
```

The same use case has two entry points: the controller behind `/health-check` and the
`app:health-check` console command both send a `HealthCheckQuery` to the query bus.

Afterwards `make start` and `make stop` are enough. `make help` lists every target, and
`make run -- <command> <options>` runs a Symfony console command, for example
`make run -- debug:router --format json`. The `--` keeps make from reading the options as its own.
Give an option its value after a space: make takes a word with `=` (`--format=json`) as a variable
and drops it.

A checkout from before HTTPS was added needs `make setup-host` once: `make start` stops with a
message until the certificate files exist in `etc/infrastructure/certs/`.

| Service | Where |
|---|---|
| Web | <https://vending-machine.test> |
| Traefik dashboard | <http://127.0.0.1:8080> |
| MySQL | `127.0.0.1:3307`, databases `app` and `app_test`, user `app` and password `app` as set in `.env` (`make db-shell`) |

All ports bind to localhost only. The PHP container runs as your user, so files it writes through
the bind mount stay yours.

## What the setup changes on your machine

`make setup-host` (part of `make first-run`) does two things, both idempotent:

1. `etc/infrastructure/setup-hostname.sh` appends one line to `/etc/hosts` (asks for sudo):

   ```
   127.0.0.1 vending-machine.test
   ```

   `.test` is a top-level domain reserved for testing, so it never clashes with a real site.

2. `etc/infrastructure/setup-ssl.sh` runs `mkcert -install`, which creates a local certificate
   authority on your machine and adds it to the system and browser trust stores, then issues a
   certificate for `vending-machine.test`, `*.vending-machine.test` and `nginx`. The certificate,
   its key and a copy of the authority's public certificate go to `etc/infrastructure/certs/`, which
   git ignores.

To undo both: remove the line from `/etc/hosts`, and run `mkcert -uninstall` (only if no other
project of yours uses mkcert).

## How a request travels

```
client --HTTPS--> Traefik --HTTPS--> nginx --FastCGI--> php-fpm (Symfony)
```

Traefik terminates TLS with the mkcert certificate, redirects HTTP to HTTPS and logs every request
(`make logs traefik`). It then opens a new TLS connection to nginx and verifies nginx's certificate
against the mkcert authority, so traffic is encrypted inside the Docker network too. Traefik finds
nginx through its Docker labels, which is why it mounts the Docker socket read-only. Symfony trusts
the proxies in the private address ranges (`TRUSTED_PROXIES`), so it sees the original scheme and
client address. Errors always come back as JSON: `{"error": {"code": 404, "message": "..."}}`.

## Quality checks

| Command | What it does |
|---|---|
| `make test` | Unit and integration tests (`make test -- --testsuite unit` for one suite) |
| `make coverage` | The tests with an HTML coverage report in `var/coverage` |
| `make phpstan` | Static analysis at the max level, with the Symfony and PHPUnit extensions |
| `make deptrac` | Per bounded context: Domain depends only on shared Domain, Application only on Domain and shared code, only Infrastructure uses the framework, and no context reaches into another |
| `make cs-fixer` | Fixes the coding standard |
| `make code-check` | The tests, PHPStan, deptrac and the coding standard in check mode (no coverage) |

`make hooks` (part of `make first-run`) points git at `etc/git/hooks-docker`. The hooks run inside
the PHP image, so the host needs no PHP: `pre-commit` checks the branch name and, when code,
configuration or tooling is staged, runs the coding standard, a container lint, PHPStan, deptrac and
the tests. The checks read the files on disk, so `pre-commit` refuses a commit whose checked files
also have unstaged changes: stage them too, or set those changes aside first. `commit-msg` accepts
[Conventional Commits](https://www.conventionalcommits.org/), plus git's own merge, revert, fixup,
squash and amend messages. `post-checkout` runs `composer install` in a checkout without `vendor/`,
such as a new git worktree.

## Debugging with PhpStorm

Xdebug is installed in the PHP image. `.env` enables it with `XDEBUG_MODE=develop,debug,coverage`
and `start_with_request=yes`, so every PHP process tries to reach the IDE on port 9003. Set
`XDEBUG_MODE=off` in `.env`, or start with `XDEBUG_MODE=off make start`, when you do not need it.

1. Settings > PHP > Servers: add a server named `vending-machine.test`, host `vending-machine.test`,
   port 443, and map the project root to `/var/www`. The container sends that name through
   `PHP_IDE_CONFIG`.
2. Listen for debug connections (port 9003, the default).
3. Set a breakpoint and send a request, or run a console command with `make run-debug -- <command> <options>`.
   `make run-debug -- app:health-check` steps through the project's own code from the CLI: put the
   breakpoint in `HealthCheckQueryHandler`.

## Project layout

The code is split by bounded context first and by layer second:

```
src/
  Kernel.php
  Shared/            Shared kernel: small, stable, used by every context, uses none.
    Application/     The Command and Query interfaces, and the health check query.
    Infrastructure/  Messenger buses, BaseController, JSON errors, health check entry points.
  VendingMachine/    The machine of the statement (arrives with its first code).
    Domain/          Business rules. Plain PHP.
    Application/     Use cases: commands, queries and their handlers.
    Infrastructure/  Symfony, controllers, console commands, persistence.
config/              Symfony configuration; routes in config/routes/<context>.php.
tests/Unit/          Unit tests, mirroring src/ (tests/Unit/<Context>/...).
tests/Integration/   Tests that boot the kernel (tests/Integration/<Context>/...).
etc/infrastructure   Docker images and the host setup scripts.
etc/git              Git hooks.
```

A second context, `Maintenance` (technicians and their login), will arrive with technician
authentication. Folders exist only once they hold code.

deptrac checks the rules between layers and contexts:

- Domain depends only on `Shared/Domain`.
- Application depends only on its own Domain, `Shared/Domain` and `Shared/Application`.
- Only Infrastructure uses the framework (Symfony, Doctrine, PSR).
- Every context may use `Shared`; `Shared` uses no context; a context never uses another
  context's Domain.

Commands and queries go through two synchronous Messenger buses. Query handlers of every context
are registered on `query.bus` by their `QueryHandler` suffix in `config/services.yaml`, so the
Application layer needs no framework attributes.

### Adding a bounded context

1. Create `src/<Context>/` with the layers that have code, and its tests under
   `tests/Unit/<Context>/` and `tests/Integration/<Context>/`.
2. Add `<Context>Domain`, `<Context>Application` and `<Context>Infrastructure` layers and their
   rules to `deptrac.yaml`, following the `VendingMachine` entries.
3. Put its routes in `config/routes/<context>.php`; the kernel imports every file in that folder.
4. `config/services.yaml` needs nothing: its globs (`src/*/Application/Query/**/*QueryHandler.php`
   and the matching exclusions) already cover any context.

### Conventions

`AGENTS.md` holds the conventions for anyone working on the code, human or agent; `CLAUDE.md`
imports it.
