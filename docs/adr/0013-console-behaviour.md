# 13. Console: refusals never stop the machine, strict coin notation

## Status

Accepted.

## Context

`vending:run` takes a sequence of actions (`1, 0.25, 0.25, GET-SODA`) or runs an interactive session.
Two things need deciding: what happens when an action cannot be done, and which spellings of a coin
are accepted.

## Decision

- A rejected coin and a domain refusal (sold out, not enough credit, no exact change, an unknown
  selector) never stop the machine. Each is reported on the error output and the next action runs,
  as a real machine spits out a foreign coin and goes on waiting. Credit is kept on a refusal.
- The exit code is 0 in those cases. The command exits with 1 only when it cannot run, such as no
  sequence with `-n` or `-q` (the interactive session needs input), or on an unexpected error.
  A refusal is part of the machine's normal behaviour, not a failure of the program.
- A coin is accepted only in the notation of the statement: `0.05`, `0.10`, `0.25` and `1`. The
  token is compared as a string with the notation of each coin (`MoneyNotation`), never read as a
  number, so no float is involved and `0.1`, `1.00` or `.25` are rejected as foreign coins.
- Parsing and printing live in Infrastructure; the domain only sees `Coin` and `Money`. After a
  sequence the console reads the tray, prints it and collects it (ADR 7).

## Consequences

- The three examples of the statement run verbatim, and a typo in the middle of a sequence does not
  discard the rest.
- Output on the standard output is only what drops into the tray, so it can be piped or compared.
- Trade-off: a script cannot tell a refused sale from a successful one by the exit code. It can read
  the error output or the tray.
- Trade-off: being strict about notation rejects what a person might reasonably type (`0.1`). The
  alternative needs rounding rules that the domain does not want.

## Alternatives considered

- **Stop at the first refusal, with a non-zero exit code.** Standard for a batch tool, but wrong for
  a machine that is supposed to carry on.
- **Parse tokens as floats and convert to cents.** More forgiving, and brings back the inexact
  arithmetic that ADR 2 avoids (and `0.1` would pass as a coin).
- **Accept any numeric spelling that maps to a coin value.** Looser, with more ways to say the same
  thing and more cases to test.
