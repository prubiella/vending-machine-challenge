# Vending machine

A vending machine modelled in PHP: it accepts coins (0.05, 0.10, 0.25 and 1), sells products
(Water 0.65, Juice 1.00, Soda 1.50), gives change, returns the inserted coins on request and lets
a technician service it by setting the available change and the stock of each product.

Work in progress: this branch sets up the infrastructure. The domain, the console interface and the
HTTP API arrive in the next iterations.

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

It creates `.env` from `.env.dist`, prepares the host (see below), builds and starts the containers,
installs the Composer dependencies and installs the git hooks. Then:

```bash
curl https://vending-machine.test/health-check
# {"status":"ok","php":"8.5.x","symfony":"8.1.x"}
```

Afterwards `make start` and `make stop` are enough. `make help` lists every target.

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

Traefik terminates TLS with the mkcert certificate, redirects HTTP to HTTPS and logs every request.
It then opens a new TLS connection to nginx and verifies nginx's certificate against the mkcert
authority, so traffic is encrypted inside the Docker network too. Symfony trusts Traefik as a proxy
(`TRUSTED_PROXIES`), so it sees the original scheme and client address.

| Service | Where |
|---|---|
| API | <https://vending-machine.test> |
| Traefik dashboard | <http://127.0.0.1:8080> |
| MySQL | `127.0.0.1:3307`, user `app`, password `app`, databases `app` and `app_test` (`make db-shell`) |

All ports bind to localhost only.

## Quality checks

| Command | What it does |
|---|---|
| `make test` | Unit tests |
| `make coverage` | Unit tests with an HTML coverage report in `var/coverage` |
| `make phpstan` | Static analysis at the max level |
| `make deptrac` | Checks that Domain depends on nothing, Application only on Domain |
| `make cs-fixer` | Fixes the coding standard |
| `make code-check` | All of the above, the coding standard in check mode |

The git hooks run inside the PHP image: `pre-commit` checks the coding standard and PHPStan on the
staged files, then deptrac and the unit tests; `commit-msg` accepts
[Conventional Commits](https://www.conventionalcommits.org/) only.

## Debugging with PhpStorm

Xdebug is installed in the PHP image. `make start` enables it with
`XDEBUG_MODE=develop,debug,coverage`; start with `XDEBUG_MODE=off make start` when you do not need it.

1. Settings > PHP > Servers: add a server named `vending-machine.test`, host `vending-machine.test`,
   port 443, and map the project root to `/var/www`.
2. Listen for debug connections (port 9003, the default).
3. Set a breakpoint and send a request, or run a console command with `make run-debug <command>`.

## Project layout

```
src/
  Domain/          Business rules. Depends on nothing else in src/.
  Application/     Use cases: commands, queries and their handlers. Depends only on Domain.
  Infrastructure/  Symfony, controllers, console commands, persistence.
tests/Unit/        Mirrors src/.
etc/infrastructure Docker images and the host setup scripts.
etc/git            Git hooks.
```

deptrac enforces the dependencies between the three layers.
