# Vending machine

A vending machine modelled in PHP: it accepts coins (0.05, 0.10, 0.25 and 1), sells products
(Water 0.65, Juice 1.00, Soda 1.50), gives change, returns the inserted coins on request and lets
a technician service it by setting the available change and the stock of each product.

Work in progress: the project is being built in small pull requests, infrastructure first.

## Stack

- PHP 8.5 (FPM, with Xdebug)
- MySQL 8.4

Everything runs in Docker: the host needs no PHP, Composer or MySQL.

## Requirements

- Docker Engine with Compose v2 (`docker compose`)
- GNU make
- A free port 3307 on localhost for MySQL (change `MYSQL_PORT` in `.env` otherwise)

## First run

```bash
make first-run
```

It creates `.env` from `.env.dist`, writes your user and group ids into it, and builds and starts
the containers. Afterwards `make start` and `make stop` are enough. `make help` lists every target.

| Service | Where |
|---|---|
| MySQL | `127.0.0.1:3307`, databases `app` and `app_test`, user `app` and password `app` as set in `.env` (`make db-shell`) |

The PHP container runs as your user, so files it writes through the bind mount stay yours.

## Debugging with PhpStorm

Xdebug is installed in the PHP image. `.env` enables it with `XDEBUG_MODE=develop,debug,coverage`
and `start_with_request=yes`, so every PHP process tries to reach the IDE on port 9003. Set
`XDEBUG_MODE=off` in `.env`, or start with `XDEBUG_MODE=off make start`, when you do not need it.

1. Settings > PHP > Servers: add a server named `vending-machine.test` and map the project root to
   `/var/www`. The container sends that name through `PHP_IDE_CONFIG`.
2. Listen for debug connections (port 9003, the default).
3. Set a breakpoint and run the code inside the container.
