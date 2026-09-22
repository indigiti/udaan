# Udaan v0.6.3 — Production Runtime

## Recommended production environment

Configure these values in the server/application environment. Do not commit real passwords or access keys.

```text
UDAAN_ENV=production

# Enable only when the deployment is behind the expected trusted reverse proxy.
UDAAN_TRUST_PROXY_HEADERS=1

REDIS_ENABLED=1
REDIS_REQUIRED=1
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_PASSWORD=<server-managed-secret>

# Optional: use server environment instead of data/content-admin.key.
UDAAN_CONTENT_ADMIN_KEY=<server-managed-secret>

# Required for subdirectory installs when running CLI pack/rotation commands.
UDAAN_BASE_PATH=/your-app-path
```

`REDIS_REQUIRED=1` is recommended when production consistency depends on Redis. If Redis cannot be reached, normal application bootstrap fails closed instead of silently moving rooms/history/cache to files.

For a deliberately file-backed installation, set `REDIS_ENABLED=0` and leave `REDIS_REQUIRED=0`.

## Trusted proxy policy

Udaan uses `X-Forwarded-Proto` only when `UDAAN_TRUST_PROXY_HEADERS` is enabled.

- behind the known Cloudways/reverse-proxy path: enable it;
- directly exposed to the internet without a trusted proxy rewriting forwarded headers: disable it.

Direct HTTPS via the web server's own `HTTPS` flag is accepted regardless.

## Deployment sequence

1. Deploy the certified `main` commit.
2. Discard/re-clone any local Git clone from before the security history rewrite.
3. Configure the production environment.
4. If the v0.6.1 live key rotation has not yet been executed, run:
   ```bash
   UDAAN_BASE_PATH=/your-app-path php ops/rotate-runtime-security.php --confirm-v061-rotation
   ```
5. Rebuild signed content/runtime cache when needed:
   ```bash
   UDAAN_BASE_PATH=/your-app-path php ops/rebuild-content-packs.php
   ```
6. Run:
   ```bash
   php ops/preflight.php
   ```
7. Open `/ready`; require HTTP 200 and `"ready": true`.
8. Open `/health.php` and verify the expected Redis/backend policy.
9. Run the functional checks in `docs/RELEASE_AUDIT_v0.6.3.md`.

## Readiness semantics

`/ready` and CLI preflight fail on:

- unsupported PHP baseline;
- missing AES-256-GCM;
- missing Ed25519/Sodium;
- unwritable data directory;
- missing HTTP deny rules for protected data/ops directories;
- active maintenance flag;
- invalid/empty content bank;
- missing or over-permissive runtime key files;
- missing/stale compiled runtime cache;
- missing/stale signed content distribution;
- unavailable Redis when `REDIS_REQUIRED=1`.

Warnings do not make readiness fail. Current warnings include:

- optional Redis configured but unavailable;
- PHP OPcache not reported as enabled;
- `UDAAN_ENV` not set to `production`.

The endpoint never creates keys, signs content, or prints secret values.

## Monitoring recommendation

Use `/ready` for deployment/readiness probes and `/health.php` for application telemetry. A load balancer should remove an instance from service when `/ready` returns 503.

For production Redis mode, alert on:

- `redis_connected=false`;
- `runtime_content_cache.ready=false`;
- `content_distribution.ready=false`;
- maintenance mode unexpectedly active.
