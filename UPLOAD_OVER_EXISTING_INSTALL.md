# Udaan v0.6.0 Safe Overlay

Upload this overlay over the existing `/ver/udan/` application.

This package intentionally contains **no `data/` directory**.

Before upload, back up the live `data/` directory. Preserve:

- `data/app-secret.key`
- `data/storage-encryption.key`
- `data/content-admin.key`
- `data/content-signing.key` if already created
- `data/content/cards.json`
- `data/history/`
- `data/user-cache/`
- `data/sync-outbox/`
- `data/aggregates/`

After upload:

1. open `/health.php`;
2. confirm version `0.6.0`;
3. confirm signed content distribution ready;
4. back up newly-created `data/content-signing.key` immediately if this is the first v0.6 release;
5. hard-refresh/reopen the PWA;
6. run `/docs/RELEASE_AUDIT_v0.6.0.md`.
