# Udaan Live — Change Log

## v0.9.0 — Performance Graph & Personal Best

### Performance
- Added a mission-derived Performance Graph with no synthetic overall student score.
- Added 14-day and 30-day training summaries.
- Added meaningful training days, completed Missions, completed minutes and Mission completion rate.
- Added Arena-level completion/minute breakdown.
- Added all-time Personal Bests for Missions completed in one day and completed training minutes in one day.

### Personal Best events
- Genuine Mission completion can emit `performance.personal_best` events when a Player exceeds their prior best.
- Personal Bests are derived only from completed Mission history.
- Skips and assigned Missions never inflate performance.

### Experience
- Added private `/progress` dashboard.
- Linked Today and Player surfaces to Progress.
- Advanced app/PWA assets to v0.9.0.

### Safety / product boundaries
- v0.9.0 deliberately does not create a universal student score.
- Health/readiness scoring remains out of scope until the dedicated Readiness release.
- Reflection text remains uncollected.

---

## v0.8.0 — Universal Mission Engine

### Mission core
- Added one Mission model shared by Learn, Fit, Mind and Reflect.
- Added idempotent per-day mission assignment from Player Arena preferences.
- Added persistent encrypted Mission state with Redis or encrypted JSON fallback.
- Added mission status, duration, difficulty, completion rule, content references and result metadata.
- Added bounded recent Mission history.

### Daily experience
- `/today` now renders live daily missions and actual completion counts.
- Added simple self-report movement, focus and reflection missions.
- Added non-punitive skip-for-today actions.
- Daily 9 remains a verified activity and cannot be completed through self-report.
- Finishing a real Daily 9 automatically completes its matching mission.

### Events / privacy
- Mission assignment, completion and skip events enter the encrypted Player Event Ledger.
- Reflection text is never requested or stored in this release.
- Mission state remains private runtime data and is excluded from Git and certified artifacts.

### Operations
- Added Mission storage readiness and health reporting.
- Added Mission state cleanup during cryptographic rotation.
- Added Mission lifecycle smoke certification and v0.8 CI contract checks.

---

## v0.7.0 — Player Foundation

### Core
- Added persistent pseudonymous Udaan Player profiles anchored to secure device HMAC identity.
- Added goal, exam target, age band, learning stage, preferred language, daily time budget and Arena preferences.
- Player privacy defaults to private; parent/institution sharing remains disabled in this release.
- Added Learn, Fit, Mind and Reflect Arena preferences without creating separate identity systems.

### Event foundation
- Added encrypted append-only Player Event Ledger.
- Player create/update events use a structured envelope ready for future Performance Graph, Coach and analytics.

### Experience
- Added `/player` onboarding/profile editing.
- Added `/today` mission-first foundation shell.
- Added protected `/admin/players` operational visibility.
- Connected the public home and Content Bank admin to the new Player surfaces.

### Operations / security
- Player and event runtime files are Git-ignored, CI-rejected and excluded from certified release packages.
- Production readiness checks Player/event directories.
- Runtime health reports Player/event storage mode.
- Security rotation purges encrypted Player/event state when cryptographic identity/storage keys must be replaced.
- Added Player foundation smoke certification.

---

## v0.6.5 — QOI CSRF, Strict Offline Sync & Card Lookup Cache

### Security
- Question of India submissions now require the same-session CSRF token in addition to existing device/network throttling.
- Offline sync rejects batches larger than 250 events instead of silently truncating them.

### Performance
- Compiled content runtime cache now includes a GUID → card map.
- Hot answer/offline-sync card lookup can use the compiled map directly instead of rebuilding it per request.

### PWA
- Service-worker cache advanced to `udaan-v0.6.5`.
- QOI script URL advanced to `qoi.js?v=0.6.5` so previously active service workers cannot serve the pre-CSRF client.

---

## v0.6.4 — Certified Deployment Artifact

### Release integrity

- Added deterministic deployment package builder at `ops/package-release.sh`.
- Every PR now smoke-builds the deployable package before merge.
- A separate workflow publishes an artifact only after the exact `main` SHA passes **Udaan Security and Syntax**.
- Release packages embed `RELEASE.json` with version + source SHA and `SHA256SUMS` for every packaged file.
- Tarballs use normalized file ordering, timestamps and ownership for reproducible output.
- Deployment artifacts exclude repository-only CI/tests and reject runtime keys, learner/session state, pending imports, generated content state, environment files and Python bytecode.

### Repository hygiene

- Removed a residual tracked pending-import verification snapshot containing an expired runtime session hash/result payload.
- Expanded CI to reject pending imports, rate-limit files, runtime cache files and generated insights in addition to existing runtime state families.

---

## v0.6.3 — Production Readiness & Runtime Policy

### Runtime policy

- Added `UDAAN_ENV` environment classification.
- Added explicit trusted-proxy policy through `UDAAN_TRUST_PROXY_HEADERS`.
- Added `REDIS_REQUIRED=1` fail-closed production mode.
- Redis remains optional when required mode is not enabled; degraded file fallback is reported explicitly.

### Readiness / deployment

- Added public `/ready` JSON readiness endpoint independent from normal application bootstrap.
- Added `php ops/preflight.php` deployment preflight.
- Readiness validates encryption/signing support, protected directories, content bank, runtime keys/permissions, compiled content cache, signed distribution and Redis policy without creating secrets.
- `/health.php` now reports environment, trusted-proxy policy and safe Redis/backend runtime state.
- Added trusted-proxy and Redis-policy smoke tests.
- CI now runs repository-mode preflight before merge.

### Safety

- Required Redis outages now fail normal application bootstrap rather than silently changing persistence semantics.
- Forwarded HTTPS headers are honored only when trusted-proxy handling is enabled.
- Readiness diagnostics expose only safe status/error codes and never Redis passwords or secret material.

---

## v0.6.2 — Hot-Path Performance & Abuse Hardening

### Performance

- Added an opcache-friendly compiled runtime content cache while keeping `data/content/cards.json` as the source of truth.
- Controlled content rebuild/import paths regenerate the compiled runtime cache automatically.
- Presenter state no longer scans the full card bank on every poll.
- Presenter polling is non-overlapping, pauses while the tab is hidden, and stops after authorization denial.

### Security / abuse controls

- Live presenter state now requires the creator host session or a per-room capability token.
- Added Redis-first rate limiting with a protected file fallback.
- Room creation, room joins, answers, offline sync, Question of India, and Content Bank login are throttled.
- Classroom/NAT-safe throttling uses device/session identity first with high network flood ceilings.
- Join and Content Bank login forms now require CSRF tokens.
- JSON APIs enforce bounded request bodies.
- Added CSP, HSTS, Cross-Origin-Resource-Policy and X-Permitted-Cross-Domain-Policies headers.

### Operations

- `/health.php` reports compiled runtime-cache readiness and security protection state.
- Cleanup prunes stale file-backed throttle records.
- Key rotation purges stale throttle/cache artifacts before regenerating signed distribution.
- CI smoke-tests compiled content caching and rate limiting in addition to PHP/JS syntax and repository safety.

---

## v0.6.1 — Security Recovery & Runtime Trust Rotation

### Security

- Removed the compromised runtime key files and learner/session runtime artifacts from the normal Git branch history.
- Replaced repository history with a clean security baseline and re-certified it with GitHub Actions.
- Application, storage, Content Bank and Ed25519 signing keys are runtime-only and mode `0600`.
- Application secret initialization now fails closed; the known static fallback pepper is removed.
- Sensitive server storage now fails closed when AES-256-GCM is unavailable instead of silently writing plaintext.
- Encrypted-room cleanup now uses `secure_unpack()` and retains unreadable records for investigation instead of deleting them.
- Public manifest, pack and health reads no longer rebuild/sign content.
- Added fail-closed maintenance mode for coordinated live key rotation.

### Content trust recovery

- Added signed content `trust_epoch=2`.
- Existing epoch-1 clients may accept exactly the approved epoch-2 signing-key transition.
- Any signing-key change without a strictly newer supported trust epoch is rejected.
- PWA/static asset cache version advanced to v0.6.1 so existing devices receive the trust migration code.
- Added controlled CLI commands:
  - `php ops/rotate-runtime-security.php --confirm-v061-rotation`
  - `php ops/rebuild-content-packs.php`

### CI

- Rejects any tracked secrets/runtime learner state/generated packs.
- PHP and JavaScript syntax certification.
- Verifies public content delivery remains read-only.
- Rejects stale v0.6.0 runtime asset references.
- Verifies both `data/` and `ops/` are denied over HTTP.

---

## v0.6.0 — Distributed Daily Learning Foundation

### Architectural principle

**Centralize trust, decentralize distribution.**

### Added

- **Daily 9** everyday learning mode (`/daily`): one card per pillar, nine total, ~1–2 minute ritual.
- Offline Daily 9 chunks generated from the encrypted signed device reserve.
- Friend challenge flow using the same nine concepts with independently randomized order/options.
- Privacy-safe completion share card + local QR challenge link.
- Encrypted anonymous response aggregates and in-journey percentage signals.
- **Question of India** prototype with one shared daily approved card and aggregate response percentages.
- Non-scoring **Mystery Card** after Daily 9 completion.
- Lightweight session badges: Daily 9 Finisher, 9-Pillar Explorer, Curiosity Champion and Fresh Explorer.
- Encrypted aggregate storage under protected `data/aggregates/`.
- Ed25519 content-signing authority.
- SHA-256 integrity for signed card packs and manifest.
- Signed content pack builder with default 112-card pack size.
- Signed master content manifest.
- `/content/manifest` and `/content/pack/{pack-id}` endpoints.
- Browser signature verification before storing packs in encrypted IndexedDB.
- Local signed-pack registry with maximum 1,008-card device capacity.
- New protected `data/content-signing.key` recovery file.
- Automatic signed-pack rebuild after verified JSON content import.
- Automatic card-bank rollback if signed-pack publication fails during import.
- Room-level unique Q&A sequence fingerprints.
- Atomic collision protection for concurrent participants.
- Per-participant shuffled/frozen option order.
- Variable choice-card support up to 8 options.
- Five-option Future preference seed cards.
- Safe Content Bank v0.6 migration for live installations: preserves imported cards, backs up the bank, upgrades only legacy three-option Future-interest seed cards, then rebuilds signed packs.

### Changed

- Offline device bank: 108 → **up to 1,008 cards**.
- Personalized offline reserve capacity: up to 1,008 unseen cards where appropriate content exists.
- Offline reserve remains balanced across all nine pillars.
- Journey content mix now explicitly Q&A/info-first.
- `action` cards limited to **maximum 1 per session**.
- 27-card default mix: 13 quiz + 9 reveal + 4 choice + 1 action.
- Offline response reserve is arranged in 27-card chunks with no more than one action per chunk.
- Quiz importer accepts 3–5 options.
- Choice importer accepts 3–8 options.
- Action importer accepts 2–4 options.
- Future-interest aggregation accepts new graded-interest option IDs.
- PWA cache version advanced to `udaan-v0.6.0-static`.

### Preserved

- v0.5.2 encrypted IndexedDB learning vault.
- Device-install UUID + server HMAC device identity.
- AES-256-GCM encrypted server temporary storage.
- Offline answer queue / reconnect sync.
- v0.5.1 Learning Reflection Rail.
- v0.4.2 paste → verify → import content bank flow.
- v0.4.1 browser-local QR generation.
- v0.4.0 GUID card bank and no-repeat learner history.
- UUID pretty room routes, screen OTP, dedicated session cookie, CSRF controls, light/dark mode.

### Local audit results

- 200 generated 27-card journeys: 200 unique sequence fingerprints.
- Max action cards observed/session: 1.
- 27-card kind mix observed: 13 quiz / 9 reveal / 4 choice / 1 action.
- Correct-answer visual position distributed across all three positions in the current 3-option quiz seed cards.
- All supported journey lengths (21/24/27/30/36) retained action cap = 1.
- 8-option choice import validation: PASS.
- 5-option quiz import validation: PASS.
- 1,728-card synthetic scale bank → 1,008-card offline reserve: PASS.
- Scale reserve balance → exactly 112 × 9 pillars: PASS.
- Offline 27-card chunks with >1 action: 0.
- Ed25519 manifest signature: PASS.
- Ed25519 sample pack signature: PASS.
- Current production seed bank remains 432 cards / 9 pillars.

### Rollback

v0.6.0 creates `data/content-signing.key` and may generate signed manifest/pack files. Preserve the signing key even if rolling back, because future v0.6.x devices may already trust its public counterpart. Never overwrite any protected `data/` files with a clean package.

---

## v0.5.2 — Encrypted Offline Learning Vault

- Device Install UUID v4; server stores only HMAC device hash.
- AES-GCM encrypted IndexedDB vault.
- 108-card initial offline-reserve baseline.
- Encrypted server temporary storage and sync outbox.
- Offline response queue / reconnect sync.

## v0.5.1 — Learning Reflection Rail

- 3-second learning/reflection rail after new card interactions.

## v0.5.0 — PWA + Session Q&A Cache

- Frozen random journey, local session cache and reconnect queue.

## v0.4.2 — Verified JSON Content Import

- Paste → Verify → Import, backups and rollback-safe content updates.

## v0.4.1 — QR Reliability / Security Hotfix

- Browser-local QR, namespaced session architecture, UUID routes, themes.

## v0.4.0 — Unlimited Card Bank / No Repeat

- 432 seed cards, 9 pillars × 48, persistent pseudonymous no-repeat history.
