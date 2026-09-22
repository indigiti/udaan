# Udaan v0.6.4 — Certified Release Artifact

## Why this exists

A deployment should never be created by copying an arbitrary working tree. Udaan now produces a deterministic package from an exact certified Git commit.

## Certification chain

```text
Pull request
  ↓
Udaan Security and Syntax
  ↓
package-release smoke build
  ↓
merge to main
  ↓
Udaan Security and Syntax on exact main SHA
  ↓
Udaan Certified Release Artifact workflow
  ↓
versioned tar.gz + sha256
```

The artifact workflow runs only when:

- the upstream workflow conclusion is `success`;
- its head branch is `main`;
- the upstream event is a `push`.

## Artifact contents

The deployment package includes application source, protected `.htaccess` rules, the approved content bank, operational CLI tools and production documentation.

It excludes:

- `.github/`;
- `tests/`;
- repository `.gitignore`;
- runtime cryptographic/authentication keys;
- rooms/history/user cache/sync outbox/aggregates/rate-limit/insight state;
- pending import verification snapshots;
- generated content manifest/packs/runtime cache/import logs/backups;
- `.env` files;
- Python bytecode/cache.

## Embedded provenance

Every package contains:

```text
RELEASE.json
SHA256SUMS
```

`RELEASE.json` records:

- schema version;
- application name;
- application version;
- full source commit SHA;
- artifact policy.

`SHA256SUMS` contains a SHA-256 digest for every packaged file except the checksum file itself.

The outer `.tar.gz.sha256` verifies the archive as downloaded.

## Local build

From a clean clone:

```bash
SOURCE_SHA="$(git rev-parse HEAD)" bash ops/package-release.sh
```

Output:

```text
dist/udaan-v<VERSION>-<12-char-SHA>.tar.gz
dist/udaan-v<VERSION>-<12-char-SHA>.tar.gz.sha256
```

## Deployment verification

Before extracting on a server:

```bash
sha256sum -c udaan-v*.tar.gz.sha256
```

After extracting, compare `RELEASE.json.source_sha` with the certified GitHub `main` SHA.

Then follow:

1. `docs/PRODUCTION_RUNTIME_v0.6.3.md`;
2. live key rotation if not already completed;
3. `php ops/preflight.php`;
4. `/ready` must return HTTP 200 and `ready=true`;
5. complete the current release audit.

## Runtime data rule

Deployment overlays must preserve the live approved content bank and server-managed runtime credentials according to the recovery/runbook. Never copy a local runtime key or generated state file into a package.

## Repository note

v0.6.4 also removed one residual tracked pending-import verification snapshot and expanded CI so this runtime file class cannot re-enter the tracked tree.
