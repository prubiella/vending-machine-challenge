# 15. HTTPS: Traefik terminates TLS and re-encrypts to nginx

## Status

Accepted.

## Context

The HTTP API should be served over HTTPS locally, with a certificate the browser and tools trust,
and without depending on anything outside the repository (no shared Docker network, no external
proxy). The reverse proxy should also see the requests, to log them and redirect HTTP to HTTPS.

## Decision

```
client --HTTPS--> Traefik --HTTPS--> nginx --FastCGI--> php-fpm
```

- Traefik terminates TLS with a certificate issued by mkcert for `vending-machine.test`, so it
  handles plain HTTP requests: access log, middlewares and the redirect from HTTP to HTTPS.
- It then opens a new TLS connection to nginx, which listens on 443 with the same certificate and
  verifies it against the mkcert authority (`serversTransport` with `rootCAs` and `serverName`).
  Verification is on: no `insecureSkipVerify`.
- Traefik finds nginx through its Docker labels, with the Docker socket mounted read-only.
- Symfony trusts the proxies in the private address ranges (`TRUSTED_PROXIES`), so it sees the
  original scheme and client address.
- Host setup is explicit and scripted (`make setup-host`): one line in `/etc/hosts` and the mkcert
  local authority, both idempotent and documented with how to undo them in the README. The hostname
  uses `.test`, a top-level domain reserved for this.
- All ports bind to localhost only.

## Consequences

- Traffic is encrypted inside the Docker network too, and the setup mirrors deployments that
  re-encrypt behind a load balancer.
- Locally the inner hop protects nothing real. In a cluster that hop would more likely be mutual TLS
  from a service mesh than certificates in nginx.
- Trade-off: setup needs `mkcert` and `sudo` once, which the README lists. The HTTP side cannot
  start without the certificate files, so `make start` stops with a message until they exist. The
  console needs none of it.
- Whoever only evaluates the project skips all of it: `make quick-start` serves the API over plain
  HTTP on a localhost port, through an nginx without TLS and without Traefik (`nginx-http`, in the
  `quick` compose profile). This setup stays the development environment.
- Trade-off: mounting the Docker socket is root-equivalent on the host, even read-only. It is kept
  for label-based discovery; with one route, the file provider would remove the need.

## Alternatives considered

- **Terminate at Traefik and speak plain HTTP to nginx.** Simpler, with one certificate fewer, but
  the traffic inside the network is clear.
- **TLS passthrough to nginx.** Traefik could not read the requests, so no access log by path and no
  HTTP routing middlewares.
- **Skip verification of nginx's certificate.** Easy, and defeats the point of the inner hop.
- **Plain HTTP on localhost.** The simplest, but it does not show how the service is meant to be
  served and hides problems with the scheme and trusted proxies.
