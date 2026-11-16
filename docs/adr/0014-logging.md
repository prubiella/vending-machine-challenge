# 14. Logging in Infrastructure, business facts as events later

## Status

Accepted. Implementation in progress, with the persistence work: the middleware and the decorator
are not on `master` yet; domain events come later.

## Context

The machine should leave a trace of what it did and what it refused. The logger (PSR) belongs to the
`Framework` layer, which Domain and Application may not use (ADR 1), so the domain cannot call it.
Two different things get logged: what the system did (technical traces) and what happened to the
business (a sale, the change given).

## Decision

- Use cases: one command bus middleware logs every command with its data and its outcome: info on
  success, notice on a domain refusal (sold out, no exact change), error on anything else and on
  exhausted retries (ADR 11). One piece covers every use case, and handlers log nothing.
- Domain services: a decorator in Infrastructure, `LoggingChangeMaker`, implements `ChangeMaker`,
  wraps `FewestCoinsChangeMaker` and is wired with `decorates:` in `services.yaml`. The handler
  receives it without knowing. This is one more reason for the interface (ADR 8).
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
