# Udaan v0.6.1 — P0 Security Recovery

The public repository previously tracked runtime secrets and runtime learner/session state. The normal Git branch history has now been replaced by a clean security baseline; all prior local clones must be discarded and re-cloned.

## Compromised values

The live values of these four secrets must be treated as compromised until the v0.6.1 runtime rotation command is executed on the deployed server:

- `data/app-secret.key`
- `data/storage-encryption.key`
- `data/content-admin.key`
- `data/content-signing.key`

Previously committed room/history/cache/outbox/aggregate records must also be treated as exposed.

## Repository recovery — COMPLETE

- current normal branch history starts from a sanitized root commit;
- only the supported branch remains after the rewrite;
- runtime keys, learner/session state, generated content packs/backups and import locks are absent from the tracked tree;
- CI rejects any future tracked runtime secrets/state;
- PHP and JavaScript syntax gates are active.

Git hosting providers can retain unreachable objects, pull-request refs or caches after a history rewrite. Because this repository was public, GitHub Support should be asked to purge any cached sensitive-data views that remain addressable. This is separate from the normal branch-history cleanup.

## Live runtime recovery

Run the deployment in a maintenance window. The rotation is deliberately destructive to temporary/pseudonymous runtime state because both the HMAC identity secret and storage-encryption key were exposed.

The approved content bank `data/content/cards.json` is preserved.

From the application directory:

```bash
php ops/rotate-runtime-security.php --confirm-v061-rotation
```

For a subdirectory install, set the public base path so newly generated pack URLs are correct:

```bash
UDAAN_BASE_PATH=/your-app-path php ops/rotate-runtime-security.php --confirm-v061-rotation
```

If Redis is configured as enabled, the command must connect and purge `udaan:*` runtime keys before rotation. It fails closed if Redis cannot be verified. Use `--skip-redis` only after independently confirming that this installation is file-only and no Udaan runtime state exists in Redis.

The command:

1. enables `data/maintenance.flag`, returning HTTP 503 to web traffic;
2. validates PHP Sodium and the approved content bank;
3. purges Udaan Redis runtime state when enabled;
4. purges temporary rooms, pseudonymous history/cache, sync outbox, aggregates, pending imports and generated distribution files;
5. rotates application, storage, Content Bank and Ed25519 signing credentials without printing their values;
6. enforces mode `0600` on generated key files;
7. rebuilds signed content packs at trust epoch 2;
8. removes maintenance mode only after the complete rotation succeeds.

If any step fails, maintenance mode remains enabled. Correct the failure and rerun the same command.

## Content signing trust recovery

v0.6.1 introduces signed manifest `trust_epoch=2`.

An existing browser that pinned the compromised epoch-1 public key may transition to a different signing key only when:

- the manifest is validly signed;
- the manifest trust epoch is supported by the installed application;
- the manifest epoch is strictly newer than the browser's stored trust epoch.

Once a browser records epoch 2, another signing-key change at epoch 2 is rejected. A future legitimate key rotation must therefore ship a new application-supported trust epoch.

The v0.6.1 service-worker/cache version is also advanced so existing PWA installations fetch this migration code.

## Controlled content rebuild

Public GET requests never create keys or sign/rebuild content. After an approved administrative content change, packs can be rebuilt explicitly:

```bash
php ops/rebuild-content-packs.php
```

For a subdirectory install:

```bash
UDAAN_BASE_PATH=/your-app-path php ops/rebuild-content-packs.php
```

Verified Content Bank imports still rebuild packs as part of their locked/rollback-safe write transaction.

## Production verification

Do not mark the server verified until all of these pass:

- v0.6.1 code is deployed;
- the rotation command completes with `ok: true`;
- all four replacement credentials are new and key files are mode `0600` where file-backed;
- `data/` and `ops/` are denied from HTTP access;
- `/health.php` reports version `0.6.1`, trust epoch `2`, AES-256-GCM available and signed distribution `ready=true`;
- the reported signing key ID differs from the compromised release;
- a previously used test browser accepts the controlled epoch-2 rotation and verifies new packs;
- a fresh browser verifies the same manifest/packs;
- Content Bank accepts only the replacement credential;
- room creation, join, answer and completion work;
- offline pack/resume/reconnect sync work using newly issued identities/tokens;
- `cleanup.php` retains valid active encrypted rooms and removes expired rooms only;
- repository security CI is green.

## Local-clone rule after history rewrite

Any clone created before the clean-root rewrite must be discarded and cloned again. Do not merge or push an old local branch back into `main`, because doing so could reintroduce the exposed history.
