# Commit Message Middleware Access Gates 2026-04-09

```text
feat(app): add middleware access and portal gating layer

Add the middleware runtime layer used by the new API and web route surfaces.

- normalize role checks and tenant resolution for API requests
- add feature gate and subscription status middleware contracts
- add portal host, portal separation, and web access middleware for web flows
- apply nonce-based security headers and portal host configuration wiring

Validated with focused middleware, portal access, and gating feature tests.
```