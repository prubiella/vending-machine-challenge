# Vending machine

[![CI](https://github.com/prubiella/vending-machine-challenge/actions/workflows/ci.yml/badge.svg)](https://github.com/prubiella/vending-machine-challenge/actions/workflows/ci.yml)

A vending machine modelled in PHP 8.5 and Symfony 8.1 with DDD, CQRS and a hexagonal architecture.
It accepts coins (0.05, 0.10, 0.25 and 1), sells products (Water 0.65, Juice 1.00, Soda 1.50),
gives change, returns the inserted coins on request and lets a technician service it by setting the
available change and the stock of each product. A console command and a JSON API drive the same
machine, which is stored in MongoDB. Everything runs in Docker: the host needs no PHP or Composer.

- [Design document](docs/design.md): architecture, domain model and main flows, with diagrams.
- [Architecture decision records](docs/adr/README.md): one short record per decision.
- [How it was built](docs/development.md): Claude Code sessions, reviews and CI.
- [`docs/openapi.yaml`](docs/openapi.yaml): the HTTP API, also in Swagger UI at
  <https://docs.vending-machine.test> once it runs.
- [Postman collection](etc/postman/vending-machine.postman_collection.json), see [Postman](#postman).

## Contents

- [Requirements](#requirements)
- [Running it](#running-it)
- [The statement's examples](#the-statements-examples)
- [Console](#console)
- [HTTP API](#http-api)
- [Technicians](#technicians)
- [Where the machine lives](#where-the-machine-lives)
- [Logs](#logs)
- [Checks and CI](#checks-and-ci)
- [Development tools](#development-tools)
- [Postman](#postman)
- [Infrastructure](#infrastructure)
- [Project layout](#project-layout)

## Requirements

- Docker Engine with Compose v2 (`docker compose`)
- GNU make
- [mkcert](https://github.com/FiloSottile/mkcert#installation), for the local HTTPS certificate
- `sudo` rights, once, to add the hostnames to `/etc/hosts` and trust the mkcert authority
- Free ports on localhost: 80, 443, 8080 (Traefik dashboard), 3307 (MySQL) and 27018 (MongoDB).
  They can be changed in `.env`.

## Running it

```bash
make first-run
```

It creates `.env` from `.env.dist`, prepares the host (`make setup-host`, see
[Infrastructure](#infrastructure) for what it changes), builds and starts the containers, installs
the Composer dependencies, creates the key pair that signs the technicians' tokens (`make jwt-keys`,
in `config/jwt/`, which git ignores), runs the database migrations and enables the git hooks. Then:

```bash
curl https://vending-machine.test/health-check
# {"status":"ok","checks":{"database":"ok","document_store":"ok","jwt_keys":"ok"}}
make run -- app:health-check
# {"status":"ok","checks":{"database":"ok","document_store":"ok","jwt_keys":"ok"}}
```

`/health-check` and `app:health-check` answer 200 and exit 0 even when a dependency is `ko`
(liveness); `/health-check/ready` and `app:health-check --ready` answer 503 and exit 1
(readiness). [Design, section 8](docs/design.md#8-operations) has the details.

Afterwards `make start` and `make stop` are enough, and `make help` lists every target.
`make run -- <command> <options>` runs a Symfony console command. The `--` keeps make from reading
the options as its own, and an option takes its value after a space (`--format json`): make takes a
word with `=` as a variable and drops it.

| Service | Where |
|---|---|
| Web and API | <https://vending-machine.test> |
| API documentation (Swagger UI) | <https://docs.vending-machine.test> |
| Traefik dashboard | <http://127.0.0.1:8080> |
| MySQL | `127.0.0.1:3307`, databases `app` and `app_test`, user and password `app` (`make db-shell`) |
| MongoDB | `127.0.0.1:27018`, databases `app` and `app_test`, user and password `root` (`make mongo-shell`) |

The credentials are the ones in `.env`. All ports bind to localhost only.

## The statement's examples

Start from the default machine, with `make run -- vending:reset`. The console prints what drops
into the tray, the products first, then the coins from the largest:

```bash
make run -- vending:run "1, 0.25, 0.25, GET-SODA"
# SODA
make run -- vending:run "0.10, 0.10, RETURN-COIN"
# 0.10, 0.10
make run -- vending:run "1, GET-WATER"
# WATER, 0.25, 0.10
```

The same three over HTTP. Commands answer `204` with no body; the tray is read with a query, as a
list of selectors and the cents of each coin, from the largest:

```bash
api=https://vending-machine.test/machine
json='Content-Type: application/json'

# 1, 0.25, 0.25, GET-SODA
for coin in 1 0.25 0.25; do curl -X POST $api/coins -H "$json" -d "{\"coin\": \"$coin\"}"; done
curl -X POST $api/selections/SODA
curl $api/tray
# {"selectors":["SODA"],"coins":[]}
curl -X POST $api/tray/collect

# 0.10, 0.10, RETURN-COIN
for coin in 0.10 0.10; do curl -X POST $api/coins -H "$json" -d "{\"coin\": \"$coin\"}"; done
curl -X POST $api/coin-return
curl $api/tray
# {"selectors":[],"coins":[10,10]}
curl -X POST $api/tray/collect

# 1, GET-WATER
curl -X POST $api/coins -H "$json" -d '{"coin": "1"}'
curl -X POST $api/selections/WATER
curl $api/tray
# {"selectors":["WATER"],"coins":[25,10]}
curl -X POST $api/tray/collect
```

If curl does not trust the certificate, add `--cacert etc/infrastructure/certs/rootCA.pem`. The API
and the console share one machine: credit inserted over HTTP is credit that `vending:run` sees,
and the other way round.

## Console

`vending:run` takes a sequence of actions separated by commas, runs them on the machine and prints
what drops into the tray. An action is a coin (`0.05`, `0.10`, `0.25` or `1`), `GET-<SELECTOR>`
(`GET-WATER`, `GET-JUICE`, `GET-SODA`) or `RETURN-COIN`. The machine rejects any other coin, as a
real one spits out a foreign coin, and a refused action (sold out, not enough credit, no exact
change, an unknown selector) keeps the credit: both are reported on the error output and the
sequence goes on ([ADR 13](docs/adr/0013-console-behaviour.md)).

Without a sequence, `make run -- vending:run` opens an interactive session: it lists the products,
takes one action per line, prints the tray after each one and shows the credit in the prompt.
`SERVICE` asks for a technician's username and password (hidden), then the units of each product and
the count of each coin (Enter keeps the current value). `QUIT` or Ctrl+D ends the session; Ctrl+D in
the middle of `SERVICE` leaves the machine as it was. The session needs interactive input: with `-n`
or `-q` and no sequence the command reports it and exits with 1, the only case besides an unexpected
error where it does not exit with 0.

`vending:service` services the machine outside a session. `--username` and `--password` give a
technician's credentials ([Technicians](#technicians)) and it asks what they leave out, with `-n`
both are required. `--units SELECTOR:UNITS` and `--coins COIN:COUNT` give the new values, each
repeated as needed; it asks the rest with the current values as defaults, and with `-n` it keeps
them. Servicing returns the credit left in the machine, so it first prints those coins as
`vending:run` prints the tray:

```bash
make run -- vending:service --username ana --units WATER:20 --coins 0.25:15 --coins 1:0
# Password:
# Units of JUICE [10]:
# ... every unit and coin the options leave out, Enter keeps the current value
# The machine is serviced.
```

`vending:service` exits with 1 whenever the machine is not serviced: an option it cannot read (not a
pair, given twice, a foreign coin, a selector the machine lacks), input that ends before the last
answer, credentials missing or wrong, or a service the machine refuses. Both ways of servicing read
the current values once, before asking, and set every value, the kept ones included, so a sale that
another session saves in between is undone: service the machine while nobody else is using it.

How the machine behaves between runs:

- It is stored, so each run carries on where the last one left it. Credit left at the end of a
  sequence stays until `RETURN-COIN` gives it back, also from a later run.
- Until a machine is stored, and after `make run -- vending:reset`, it is the default machine of
  `config/services.yaml`: the three products, ten units of each and ten coins of 0.05, 0.10 and 0.25
  for change. A stored machine wins over a change to `vending_machine.default_products` until
  `vending:reset` removes it.
- Change is made only from coins of 0.05, 0.10 and 0.25 ([ADR 8](docs/adr/0008-change-making.md)).
  Coins of 1 stay in the machine as takings and are never given as change; `SERVICE` asks their count
  so the technician can empty the cash box.
- A slot holds at most 200 units and a coin tube at most 500 coins: servicing refuses more and
  changes nothing. A sale is not bounded, so the customer's coins may take a tube past 500
  ([ADR 19](docs/adr/0019-machine-capacities.md)).

## HTTP API

Commands answer `204 No Content` with no body: what they did is read with a query, such as the tray
after a purchase. The customer's routes need no token.

| Request | What it does | Answer |
|---|---|---|
| `GET /machine` | The products (selector, name, price in cents, whether one is left) and the credit in cents | `200` |
| `POST /machine/coins` with `{"coin": "0.25"}` | Inserts a coin, written as in the statement: `0.05`, `0.10`, `0.25` or `1` | `204` |
| `POST /machine/selections/{selector}` | Sells the product: it drops into the tray with the change | `204` |
| `POST /machine/coin-return` | Drops the credit into the tray as coins | `204` |
| `GET /machine/tray` | The selectors of the products in the tray and the cents of each coin, from the largest | `200` |
| `POST /machine/tray/collect` | Empties the tray | `204` |

A refused request answers with its reason, `{"error": {"code": 409, "message": "..."}}`: `404` for an
unknown selector; `409` when the credit does not pay the product, it is sold out or the machine has
no exact change, and also when another request changed the machine at the same time and the retries
ran out (send it again); `422` for a foreign coin, a body without a coin as a string or a malformed
selector. Anything else, such as a stored machine that breaks one of its rules, answers `500` and is
logged as critical.

### The technician's side

A technician logs in for a token and sends it as a bearer token to these routes:

| Request | What it does | Answer |
|---|---|---|
| `POST /auth/login` with `{"username": "ana", "password": "..."}` | Gives a token that lasts one hour | `200 {"token": "..."}`, `401` for a wrong username or password |
| `GET /machine/status` | The units of each product and the count of each coin, by its cents | `200`, `401` without a valid token, `403` without the technician's role |
| `PUT /machine/service` with `{"units": {"WATER": 20}, "coins": {"0.25": 15, "1": 0}}` | Services the machine as `vending:service` does: sets the given units and the whole change (a coin left out is set to none), and drops the credit into the tray | `204`, `401`, `403`, `404` for a product the machine lacks, `409` when the retries ran out, `422` for a foreign coin, a negative count, or more than a slot or a coin tube holds |

```bash
token=$(curl -s -X POST https://vending-machine.test/auth/login -H 'Content-Type: application/json' \
  -d '{"username": "ana", "password": "correct-horse"}' | jq -r .token)
curl https://vending-machine.test/machine/status -H "Authorization: Bearer $token"
# {"unitsBySelector":{"WATER":10,"JUICE":10,"SODA":10},"coinStockByCents":{"5":10,"10":10,"25":10,"100":0}}
curl -X PUT https://vending-machine.test/machine/service -H "Authorization: Bearer $token" \
  -H 'Content-Type: application/json' -d '{"units": {"WATER": 20}, "coins": {"0.05": 10, "0.10": 10, "0.25": 15, "1": 0}}'
```

`make run -- vending:technician:token --username ana` prints the same token from a shell, and nothing
else on stdout, so `token=$(make run -- vending:technician:token --username ana)` works. It asks
what `--username` and `--password` leave out, the password hidden, on stderr; a wrong or missing
credential goes to stderr with exit code 1. A missing, invalid or expired token answers `401` in the
API's error format and a token without the role `403`
([ADR 18](docs/adr/0018-technician-authentication.md), [design, section 7](docs/design.md#7-security)).

[`docs/openapi.yaml`](docs/openapi.yaml) describes every route, with its bodies, the statuses it
answers and an example of each. It is written by hand, and `OpenApiDocumentTest` fails when it and
the routes disagree. Read it, and try the requests, in Swagger UI at
<https://docs.vending-machine.test>; the API answers cross-origin requests from that origin only.
PhpStorm renders the file too, or paste it into [Swagger Editor](https://editor.swagger.io).

## Technicians

A technician is who services the machine. `vending:technician:create` stores one in the
`technicians` table with a username (3 to 32 lowercase letters, digits, dots, hyphens or
underscores, unique), an email (a valid address, unique) and the bcrypt hash of a password of 8
characters to 4096 bytes, and prints the id it gives the technician:

```bash
make run -- vending:technician:create --username ana --email ana@example.com
# Password:
# Technician ana created with id 019a8c5e-....
```

It asks what `--username`, `--email` and `--password` leave out, the password hidden; with `-n` the
three are required. An answer the domain refuses is asked again with the reason, up to three times,
while a refused option ends the command. The technician logs in by username; the email is only kept.
A password is never trimmed, so spaces around it count. Given as an option it stays in the shell
history and shows in `ps`: prefer the prompt.

Servicing, in the console or over HTTP, needs a technician. An unknown username and a wrong password
are refused with the same message, so a refusal does not tell which usernames exist, and the service
command carries the technician's id, which the command bus logs: that log tells who serviced the
machine. The password is never stored or logged in clear ([Logs](#logs)).

## Where the machine lives

MongoDB stores the machine as one document of the `vending_machines` collection, whose `_id` is its
UUID: `VENDING_MACHINE_ID`, a fixed default in `config/services.yaml` that `.env` can override
([ADR 16](docs/adr/0016-machine-stored-in-mongodb.md), [ADR 17](docs/adr/0017-uuid-identities.md)).
It holds the `version` that optimistic locking checks, the `inventory`, the credit and the change
(`wallet_credit` and `wallet_stock`, the count of each coin by its cents) and the `tray`. To look at
it:

```bash
make mongo-shell
db.vending_machines.findOne()
```

MySQL holds the technicians and the migrations table, and the `vending_machine` table of
`DbalVendingMachineRepository`, a second adapter that passes the same contract test and stays empty
while the machine lives in MongoDB. Switching the machine to it means pointing the aliases of
`VendingMachineRepository` and `StoredMachineResetter` in `config/services.yaml` at it.
[Design, section 6](docs/design.md#6-persistence) has the rest.

## Logs

The command bus logs every command with its name and data: info when handled, notice when the domain
refuses it, error for anything else, and notice for each retry after a concurrent update. A command
carrying a secret or personal data implements `SensitiveCommand` and masks those fields: a password
is logged as `***` and a username as its first three characters (`pau***`), and a console
`--password` option is masked too. `LoggingChangeMaker` logs the change computed for a sale.

In dev the application's own logs go to a file a day, `var/log/dev-<YYYY-MM-DD>.log` (the last seven
are kept; `tail -f var/log/dev-$(date +%F).log`), and only its warnings and errors also reach stderr,
so the console shows the machine's output alone. In prod everything goes to stderr as JSON from info
up (`make logs php-fpm`). [Design, section 8](docs/design.md#8-operations) and
[ADR 14](docs/adr/0014-logging.md) explain it.

## What the setup changes on your machine

`make setup-host` (part of `make first-run`) does two things, both idempotent:

1. `etc/infrastructure/setup-hostname.sh` appends one line per host to `/etc/hosts` (asks for
   sudo), the API, its Swagger UI and the two optional database tools, since `/etc/hosts` has no
   wildcards:

   ```
   127.0.0.1 vending-machine.test
   127.0.0.1 docs.vending-machine.test
   127.0.0.1 pma.vending-machine.test
   127.0.0.1 mongo.vending-machine.test
   ```

   `.test` is a top-level domain reserved for testing, so it never clashes with a real site.

2. `etc/infrastructure/setup-ssl.sh` runs `mkcert -install`, which creates a local certificate
   authority on your machine and adds it to the system and browser trust stores, then issues a
   certificate for `vending-machine.test`, `*.vending-machine.test` and `nginx`. The certificate,
   its key and a copy of the authority's public certificate go to `etc/infrastructure/certs/`, which
   git ignores.

To undo both: remove the lines from `/etc/hosts`, and run `mkcert -uninstall` (only if no other
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
| `make behat` | The customer and technician scenarios in `features/` (`make behat -- --name "sold out"` for one), run against the Symfony kernel through BrowserKit: no server, no hosts entry, no TLS |
| `make coverage` | The tests with an HTML coverage report in `var/coverage` |
| `make phpstan` | Static analysis at the max level, with the Symfony and PHPUnit extensions |
| `make deptrac` | Per bounded context: Domain depends only on shared Domain, Application only on Domain and shared code, only Infrastructure uses the framework, no context reaches into another, and every class belongs to a layer |
| `make cs-fixer` | Fixes the coding standard |
| `make cs-check` | Checks the coding standard without changing files |
| `make lint-container` | Compiles the service container and checks that every service is wired |
| `make code-check` | The tests, the scenarios, PHPStan, deptrac, the coding standard in check mode and the container lint (no coverage) |

GitHub Actions (`.github/workflows/ci.yml`) runs the same checks on every pull request and on
`master`, in the project's own PHP image on GitHub-hosted runners. One job builds the image into
the Actions cache; then each static check, the unit tests, the integration tests and the scenarios
run as parallel jobs that load it, so a failing check does not hide the others, and only the tests
that need a database start one. It also runs the skill check and validates `composer.lock`. Branch
protection requires the last job, `All checks passed`, which fails unless every other job passed.

`make hooks` (part of `make first-run`) points git at `etc/git/hooks-docker`. The hooks run inside
the PHP image, so the host needs no PHP: `pre-commit` checks the branch name and, when code,
configuration or tooling is staged, runs the coding standard, a container lint, PHPStan, deptrac,
the tests and the scenarios. The hooks join the containers' network when it exists, so the tests that use MySQL or
MongoDB need the containers started (`make start`). The checks read the files on disk, so `pre-commit` refuses a commit whose checked files
also have unstaged changes: stage them too, or set those changes aside first. When a Claude Code
skill or `AGENTS.md` is staged, it first checks the skills (`.claude/scripts/skill-check.sh`),
with the same refusal. `commit-msg` accepts
[Conventional Commits](https://www.conventionalcommits.org/), plus git's own merge, revert, fixup,
squash and amend messages. `post-checkout` runs `composer install` in a checkout without `vendor/`,
such as a new git worktree.

### Scenarios

`features/customer/` holds the customer's use cases as Gherkin scenarios that a non-developer can
read, such as "When I insert 0.25 / And I select SODA / Then the tray holds SODA and no coins". The
steps live in `tests/Behat/CustomerContext.php`. Each scenario starts from the default machine, and
a `Given` such as "SODA is sold out" or "the machine has 10 coins of 0.25" services it first. The
scenarios use the test database, so `make migrate` must have run.

`features/technician/` holds the technician's side, with the steps of
`tests/Behat/TechnicianContext.php`: logging in, the 401 of a wrong password or of no token,
reading the stock and the change, and servicing the machine. Each scenario starts with no technician and the default machine,
and a `Given` creates the technician it logs in as. Every login gives a token with the technician's
role, so a 403 cannot be reached from a scenario: the kernel tests make a token without the role to
cover it.

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

### Connecting a database client

Both databases publish a port on localhost, so a desktop client reaches them with the credentials
of `.env`. In PhpStorm, open the Database tool window and add a data source for each (PhpStorm
downloads the driver the first time):

| | MySQL | MongoDB |
|---|---|---|
| Host and port | `127.0.0.1` and `MYSQL_PORT` (`3307`) | `127.0.0.1` and `MONGO_PORT` (`27018`) |
| User and password | `MYSQL_USER` and `MYSQL_PASSWORD` (`app` / `app`) | `MONGO_ROOT_USER` and `MONGO_ROOT_PASSWORD` (`root` / `root`) |
| Authentication database | | `admin` |
| Database | `app`, and `app_test` for the tests | `app`, and `app_test` for the tests |

MongoDB holds the machine, as the document of `vending_machines` whose `_id` is
`VENDING_MACHINE_ID`. MySQL holds the migrations table, the `technicians` table and the
`vending_machine` table of the DBAL adapter, empty while the machine lives in MongoDB. The same
settings work in any other client (DBeaver, MongoDB
Compass, the `mysql` and `mongosh` command lines); `make db-shell` and `make mongo-shell` open a
shell inside the containers without installing anything.

To look at the data in a browser instead, `make tools` starts phpMyAdmin at
`https://pma.vending-machine.test` (already logged in as `MYSQL_USER`) and mongo-express at
`https://mongo.vending-machine.test` (connected as the MongoDB root user; its own basic auth is
`MONGO_EXPRESS_USER` and `MONGO_EXPRESS_PASSWORD`, `admin` / `admin` by default). They sit behind
Traefik with the same certificate as the API and publish no port. They are for local development
only, never for production: they open the databases with powerful credentials and weak or no
login. `make tools-stop` removes them. Run `make setup-host` again once so `/etc/hosts` gets the
two new hosts lines.

## Trying the API with Postman

`etc/postman/` holds a collection with every endpoint, one folder per bounded context, and an
environment that points `baseUrl` at `https://vending-machine.test`.

1. Import both files (File > Import).
2. Settings > Certificates: turn on CA certificates and select `etc/infrastructure/certs/rootCA.pem`,
   so Postman trusts the mkcert certificate.
3. Select the `vending-machine.test` environment and send a request, or run the whole collection.

The `Maintenance` folder logs in as the technician of the environment (`technicianUsername`,
`technicianPassword`) and keeps the token for the technician's requests, 18 and 19 of the
`VendingMachine` folder, which run after the customer's story, read the machine's status and set
the default stock and change back. Create that technician once:
`make run -- vending:technician:create --username postman --email postman@example.com --password postman-password`.

The `VendingMachine` folder is a customer's session told as a story. It plays the statement's last
example, `1, GET-WATER`, checking the credit and the tray after each step (the water with 0.25 and
0.10, then the empty tray once collected); then it returns a coin and checks three refusals. Every
request tests its status and its body. Run it in order from a machine with no credit and an empty
tray, as `make run -- vending:reset` leaves it. A run ends by servicing the machine back to the
default stock and change, so the folder passes again on every run.

To run it from the command line with newman, reset the machine first and mount `etc/postman/`;
`--insecure` skips the check of the mkcert certificate:

```
make run -- vending:reset
docker run --rm --network host -v "$PWD/etc/postman:/etc/newman" postman/newman:6.1.3-alpine \
  run vending-machine.postman_collection.json -e vending-machine.test.postman_environment.json --insecure
```

The repository is the source of the collection: after changing it in Postman, export it (v2.1)
over the file in `etc/postman/`. If you change `TRAEFIK_HTTPS_PORT` in `.env`, add the port to
`baseUrl`.

## Project layout

The code is split by bounded context first and by layer second:

```
src/
  Kernel.php
  Shared/            Shared kernel: small, stable, used by every context, uses none.
    Domain/          Money and DomainException, the base of every domain exception.
    Application/     The Command and Query interfaces, the bus ports, the health check query,
                     the TechnicianAuthenticator port that VendingMachine logs technicians in with.
    Infrastructure/  Messenger buses, BaseController, JSON errors, health check entry points,
                     the JSON answer to a missing or invalid token.
  VendingMachine/    The machine of the statement.
    Domain/          Business rules. Plain PHP.
    Application/     Use cases: commands, queries and their handlers.
    Infrastructure/  Symfony, controllers, console commands, persistence.
  Maintenance/       The technicians who service the machine.
    Domain/          Technician, its username and password rules, the repository and hasher ports.
    Application/     Use cases: creating a technician, checking its credentials.
    Infrastructure/  The technicians table, the Symfony password hasher, console commands, the
                     TechnicianAuthenticator adapter over the credentials query, login.
config/              Symfony configuration; routes in config/routes/<context>.php.
tests/Unit/          Unit tests, mirroring src/ (tests/Unit/<Context>/...).
tests/Integration/   Tests that boot the kernel (tests/Integration/<Context>/...).
etc/infrastructure   Docker images and the host setup scripts.
etc/git              Git hooks.
etc/postman          Postman collection and environment.
docs/                The OpenAPI document of the HTTP API.
```

Folders exist only once they hold code.

deptrac checks the rules between layers and contexts:

- Domain depends only on `Shared/Domain`.
- Application depends only on its own Domain, `Shared/Domain` and `Shared/Application`.
- Only Infrastructure uses the framework (Symfony, Doctrine, PSR, MongoDB).
- Every context may use `Shared`; `Shared` uses no context; a context never uses another
  context's Domain.
- Every class in `src/` belongs to a layer, and every dependency points to a layer: a class of an
  undeclared context, or a library outside the `Framework` layer, fails the check.

Commands and queries go through two synchronous Messenger buses, behind the `CommandBus` and
`QueryBus` ports of `Shared/Application/Bus`. Command and query handlers of every context are
registered on `command.bus` and `query.bus` by their `CommandHandler` and `QueryHandler` suffixes
in `config/services.yaml`, so the Application layer needs no framework attributes.

The machine is saved with optimistic locking: its stored document has a version, and a save that
finds another version than the one it loaded fails with a `ConcurrentUpdateException`. The command
bus then runs the command again on the fresh machine, up to three more times, before it gives up.

### Adding a bounded context

1. Create `src/<Context>/` with the layers that have code, and its tests under
   `tests/Unit/<Context>/` and `tests/Integration/<Context>/`.
2. Add `<Context>Domain`, `<Context>Application` and `<Context>Infrastructure` layers and their
   rules to `deptrac.yaml`, following the `VendingMachine` entries. Until then `make deptrac`
   fails and lists the context's classes as unassigned.
3. Put its routes in `config/routes/<context>.php`; the kernel imports every file in that folder.
4. `config/services.yaml` needs nothing: its globs
   (`src/*/Application/Command/**/*CommandHandler.php`,
   `src/*/Application/Query/**/*QueryHandler.php` and the matching exclusions) already cover any
   context.

### Conventions

`AGENTS.md` holds the conventions for anyone working on the code, human or agent; `CLAUDE.md`
imports it.
