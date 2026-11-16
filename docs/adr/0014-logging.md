# 14. Logging in Infrastructure, business facts as events later

## Status

Accepted. Built and merged: `LoggingCommandMiddleware`, the retry log and `LoggingChangeMaker`.
Domain events come later.

## Context

The machine should leave a trace of what it did and what it refused. The logger (PSR) belongs to the
`Framework` layer, which Domain and Application may not use (ADR 1), so the domain cannot call it.
Two different things get logged: what the system did (technical traces) and what happened to the
business (a sale, the change given).

## Decision

- Use cases: `LoggingCommandMiddleware` logs every command with its name and data and its outcome:
  info when it is handled, notice on a `DomainException` (sold out, no exact change), error on
  anything else, which includes retries that ran out (ADR 11). It is the first middleware of
  `command.bus`, so it sees the exception that `UnpackExceptionMiddleware` has already unpacked, and
  a command that was retried is logged once. One piece covers every use case, and handlers log
  nothing.
- Retries: `RetryOnConcurrentUpdateMiddleware` logs each retry at notice, with the same context as
  the command log (the command class), because only it knows that an attempt happened.
- Entry points: `vending:run` logs each rejected token (a bad coin or action) at notice. A token is
  rejected before any command exists, so no middleware sees it.
- Domain services: a decorator in Infrastructure, `LoggingChangeMaker`, implements `ChangeMaker`,
  wraps `FewestCoinsChangeMaker` and is wired with `decorates:` in `services.yaml`. It logs the
  change at info and a refusal for want of exact change at notice. The handler receives it without
  knowing. This is one more reason for the interface (ADR 8). One decorator per domain service.
  Known issue: the message reads "Change of N cents made", but the decorator logs before the save
  and once per retry, so it does not mean the change was given. Planned polish: reword it to say the
  change was computed.
- Where it goes: the application's own channel (`app`) writes JSON to `var/log/dev.log` in dev, and
  only its warnings and errors also reach stderr, so the console shows the machine's output and its
  refusals alone. Symfony's own logs go to stderr as JSON, without the noisy `event`, `messenger`,
  `console` and `doctrine` channels. In prod everything goes to stderr as JSON from info up (a
  container writes to its stdout and stderr, not to files).
- No query logging. There is no `LoggingQueryMiddleware`: queries change nothing, there are many of
  them, and their unexpected failures are already logged by Symfony. The SQL statements are not
  logged either: the `doctrine` channel is left out of stderr, and a failing statement surfaces as
  an exception that the command middleware logs at error. The readiness probe logs a failed
  database connection at warning.
- Sensitive data: the middleware logs every field of a command, which is fine while commands hold
  only product selectors and coins. Before technician login exists, fields such as a password or a
  token must be masked. Note, 2026-11-16: masking exists. A command that carries a secret or
  personal data implements `SensitiveCommand` and maps each such field to a `Masking` rule (`Hide`
  logs `***`, `KeepFirstThree` logs `pau***`), and `Maintenance` uses it: `CreateTechnicianCommand`
  hides the password and keeps the first three characters of the username and the email.
- The domain never depends on a logger and has no `DomainLogger` port.
- Business facts are better as domain events published after the commit. A subscriber can then log
  them, send them to a queue or build a sales history. That work comes later.

## Consequences

- Domain and Application stay free of PSR, and deptrac keeps it that way.
- Logging is added, changed or removed in one place per kind of thing logged.
- Trade-off: the middleware sees commands, not business meaning. It can say "SelectProduct was
  refused as sold out", not "a Soda was sold for 1.50". The meaning needs events.
- Trade-off: events published after the commit are not an outbox. If the process dies between the
  commit and the publish, an event is lost. That is acceptable for a log and alerts, not for
  accounting, where an outbox would be needed.

## Alternatives considered

- **A `LoggerInterface` in the domain or in handlers.** Breaks the layering, and every handler
  repeats the same lines.
- **A `DomainLogger` port in the domain.** Treats a business fact as a technical trace; a business
  fact is an event.
- **Logging only in the console command and controllers.** Misses what the HTTP and console entry
  points share, and is duplicated per entry point.
