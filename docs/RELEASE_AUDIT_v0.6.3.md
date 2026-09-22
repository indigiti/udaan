# Udaan v0.6.3 — Release Audit

## CI certification

Before merge:

- repository safety passes;
- PHP and JavaScript syntax pass;
- v0.6.2 runtime cache/throttle smoke remains green;
- repository-mode production preflight passes;
- trusted-proxy smoke passes;
- Redis-policy smoke passes;
- readiness route and required-Redis contract checks pass.

## Server preflight

After deployment run:

```bash
php ops/preflight.php
```

Required result: `READY`.

Then request `/ready`.

Required result:

- HTTP 200;
- `ready=true`;
- no failures;
- environment is `production` for production promotion;
- runtime keys pass permissions;
- runtime content cache is fresh;
- signed distribution is ready;
- Redis is online when required.

## Functional certification

1. Open `/health.php`; verify version `0.6.3`.
2. Confirm expected `runtime.redis_required`, `runtime.redis_connected` and backend fields.
3. Create a live room.
4. Join from two different devices.
5. Verify presenter polling and protected state.
6. Answer cards and complete the journey.
7. Verify Daily 9.
8. Verify Question of India.
9. Verify offline pack/resume/sync.
10. Verify Content Bank authentication and a no-op verification flow.
11. Run `php cleanup.php`.
12. Confirm `/ready` remains green after functional traffic.

## Failure-mode certification

On a staging instance only:

- with Redis optional, simulate Redis unavailable and confirm `/ready` stays ready with an optional-fallback warning/state if all other requirements pass;
- with `REDIS_REQUIRED=1`, simulate Redis unavailable and confirm:
  - normal app traffic fails closed;
  - `/ready` remains accessible;
  - `/ready` returns HTTP 503 with `redis=required-unavailable`.

Do not mark **SERVER VERIFIED** until the live deployment passes the successful path. Failure-mode testing may be performed on staging.
