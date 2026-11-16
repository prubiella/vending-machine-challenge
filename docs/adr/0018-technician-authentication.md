# 18. Technician authentication

## Status

Accepted. Built: the `Maintenance` context, the console credentials of `SERVICE` and
`vending:service`, `POST /auth/login`, `vending:technician:token`, the protected technician routes
and the readiness probe of the key pair.

## Context

Servicing the machine sets its stock and its change, so it must be a technician's act, and the log
must tell which technician did it. The customer side stays public, like the panel of a real machine.
Technicians service the machine from the console and over HTTP, so both need the same credentials
check. `VendingMachine` must not depend on whatever keeps the technicians
([ADR 1](0001-bounded-contexts-and-shared-kernel.md)), and no password may reach a log
([ADR 14](0014-logging.md)).

## Decision

- **A `Maintenance` context keeps the technicians.** A technician is a UUID, a unique username, a
  unique email and the bcrypt hash of a password (Symfony's `NativePasswordHasher` behind a domain
  port), stored in the `technicians` table of MySQL. Technicians are not part of the machine, they
  have rules of their own (username format, password length, uniqueness), and they will gain more
  (roles, a history of services), so they get their own context with the same three layers rather
  than a corner of `VendingMachine`. `vending:technician:create` adds one.
- **`VendingMachine` reaches them through a port of `Shared/Application`.** `TechnicianAuthenticator`
  gives a technician's id for a username and a password; `Maintenance` implements it by asking
  `AuthenticateTechnicianQuery` on the query bus. No context uses another, and deptrac checks it.
- **One credentials check for every entry point.** `AuthenticateTechnicianQuery` checks the
  credentials, as a query, so the command bus never logs the password. The console servicer asks it
  through the port; `POST /auth/login` and `vending:technician:token` ask it on the query bus like
  any other controller or command, and hand the technician it lets in to `TechnicianTokenIssuer`,
  whose only job is signing the token.
- **Stateless JWT, not sessions.** lexik/jwt-authentication-bundle signs an RS256 token that lasts
  one hour, whose `sub` claim is the technician's id and whose roles hold `ROLE_TECHNICIAN`. The
  `machine` firewall is stateless and builds the user from the token with the bundle's `lexik_jwt`
  provider, so a request costs no database read; `access_control` lists the technician's routes
  (`GET /machine/status`, `PUT /machine/service`) and reads the role from the issuer's constant. An
  API client keeps no cookie, and the console has no session to share. The key pair is created by
  `make jwt-keys`, ignored by git, and its paths and passphrase are in `.env.dist`.
- **A hand-written login, not `json_login`.** `json_login` needs a Symfony user provider that loads
  a user by username and checks its password itself, a second credentials check next to the one the
  console uses, and it ties the token's identifier to the username. The login controller instead
  asks the same query as the console and signs the token with the technician's id.
- **Not FOSUserBundle.** The bundle is archived, its user is a Doctrine ORM entity extending the
  bundle's class (this project has no ORM and keeps Symfony out of its domain), and its features
  (registration, password reset by email, profile forms) are not needed here.
- **Console credentials.** `SERVICE` and `vending:service` ask the username and the password that
  the options leave out, the password hidden; `vending:technician:token` prints a token alone on
  stdout, its prompts and refusals on stderr, for calling the API from a shell. A password given as
  an option stays in the shell history, so the README recommends the prompt.
- **One refusal for both mistakes.** An unknown username and a wrong password answer the same
  "Wrong username or password.", and a username the format rejects is refused the same way, so a
  refusal does not tell which usernames exist.
- **A password is never trimmed**, at a prompt as in an option or a login body, so the spaces typed
  are part of it; only the line ending that closes an answer goes.
- **Nothing logs a secret.** `CreateTechnicianCommand` implements `SensitiveCommand`: the password is
  hidden and the username and the email keep their first three characters. A refusal's message
  never quotes a username, an email or a password. Symfony logs a console command with its input, so
  `PasswordOptionMaskingProcessor` logs a `--password` option as `***`.
- **Who serviced the machine.** `ServiceMachineCommand` carries the authenticated technician's id,
  from the console login or from the token. No handler reads it: the command bus log records it on
  every service, and that log is the audit trail until domain events exist, when a
  `MachineServiced` event goes to the outbox and the history.
- **Failures the client can fix answer 401, the server's own do not.** A missing, malformed, badly
  signed or expired token answers 401 in the API's error format; a token the server cannot check
  because of its own key (a file it cannot read) answers 500 and is logged as critical, since
  logging in again would not help.
- **Readiness checks the key pair.** The health check signs a throwaway token and verifies it, and
  reports `jwt_keys` `ko` when a key is missing, unreadable or of another pair, so
  `/health-check/ready` answers 503 and `app:health-check --ready` exits 1.

## Consequences

- One credentials check, one identity (the technician's id) and one masking rule serve the console
  and HTTP alike, and `VendingMachine` still knows nothing of `Maintenance`.
- A request needs no database to know its technician. The price: a token stays valid for its hour
  after its technician is removed or changes password, since nothing is looked up per request.
- A key pair that does not match is caught by the readiness probe, not at boot: the application
  starts, and login answers, until something reads readiness.
- Every login token carries `ROLE_TECHNICIAN`, so a 403 cannot be reached from a login; the kernel
  tests cover it with a token made without the role.
- The audit trail is a log line until domain events exist.

## Alternatives considered

- Sessions with a login form: state on the server and a cookie, for an API whose clients are
  scripts and the console.
- `json_login` with a user provider over the technicians table: a second credentials check, and the
  username as the token's identity.
- FOSUserBundle: archived, ORM-based, and mostly features this project does not need.
- Credentials in `ServiceMachineCommand`, checked by its handler: the password would travel in a
  loggable command, and every entry point would have to carry it.
- Reloading the technician on every request, to revoke a token at once: a database read per request
  for a risk bounded by the token's hour.

See [docs/design.md](../design.md), section 7, for how the pieces fit at run time.
