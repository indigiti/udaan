# Udaan Live v0.6.5 — Certified Hardened Distributed Learning

**Architectural principle:** **Centralize trust, decentralize distribution.**  
**Base:** v0.5.2 Encrypted Offline Learning Vault  
**Database:** **Not required** for this release. PHP + encrypted JSON/Redis + encrypted IndexedDB remain the persistence model until production DB adoption.


## v0.6.4 certified deployment artifact

v0.6.4 adds a deterministic, source-traceable deployment bundle.

- `ops/package-release.sh` builds a deployable tarball from an exact Git SHA.
- PR certification smoke-builds the same release package.
- After a successful `main` security/syntax run, GitHub Actions automatically publishes a 30-day certified artifact.
- `RELEASE.json` inside the package records the exact source SHA/version.
- `SHA256SUMS` validates every packaged file.
- The tarball/checksum pair is uploaded together.
- Runtime keys/state, pending imports, generated packs/cache, environment files and Python bytecode are hard-rejected.

Use only an artifact whose source SHA matches the certified `main` commit you intend to deploy. See `docs/CERTIFIED_RELEASE_v0.6.4.md`.

## v0.6.3 production readiness

v0.6.3 formalizes the production runtime contract without removing the encrypted file fallback used by smaller installations.

- `/ready` returns HTTP 200 only when required runtime dependencies are ready; otherwise HTTP 503 with safe diagnostics.
- `php ops/preflight.php` runs the same checks from CLI.
- `REDIS_REQUIRED=1` makes Redis a hard dependency for normal application traffic.
- When Redis is optional, an outage is surfaced as degraded state while encrypted file storage remains available.
- `UDAAN_TRUST_PROXY_HEADERS` controls whether reverse-proxy HTTPS headers are trusted.
- `UDAAN_ENV=production` removes the non-production readiness warning.
- Runtime key permissions, compiled content cache and signed distribution are validated without creating or printing secrets.

See `docs/PRODUCTION_RUNTIME_v0.6.3.md` and `docs/RELEASE_AUDIT_v0.6.3.md` before marking a deployment SERVER VERIFIED.

## v0.6.2 performance and abuse hardening

v0.6.2 keeps the v0.6.1 trust-recovery model and hardens the high-frequency runtime paths:

- compiled PHP runtime content cache for faster PHP-FPM reads;
- no full-bank scan during presenter polling;
- presenter state protected by host/capability access;
- Redis-first throttling with protected file fallback;
- bounded JSON request bodies;
- classroom-safe device/session throttles with broad network flood ceilings;
- CSRF on join and Content Bank login;
- CSP/HSTS/cross-origin response headers;
- runtime smoke certification in GitHub Actions.

The JSON content bank remains authoritative. Controlled content import/rebuild paths regenerate the runtime cache.

## v0.6.5 hardening update

- Question of India POSTs now require CSRF in addition to existing device/network throttling.
- Offline sync rejects batches above 250 events rather than truncating.
- Compiled runtime content cache includes the GUID→card lookup map used by answer/sync hot paths.
- Service-worker cache is advanced to `udaan-v0.6.5` and QOI script is explicitly cache-busted.

## v0.6.1 security recovery

v0.6.1 hardens the v0.6.0 distributed-learning foundation after a public-repository exposure of runtime secrets/state.

- Runtime credentials and learner/session state are no longer tracked in Git.
- Normal branch history was replaced with a clean security baseline.
- Secret generation and sensitive storage fail closed.
- Public content delivery is read-only; signing/rebuilds happen only through controlled import/CLI paths.
- Signed manifests now carry **trust epoch 2** for the approved Ed25519 key transition.
- The PWA cache is advanced to v0.6.1 so existing devices receive the trust migration.
- Live recovery is performed with `php ops/rotate-runtime-security.php --confirm-v061-rotation`.
- The approved content bank `data/content/cards.json` is preserved during rotation.

See `docs/SECURITY_RECOVERY_v0.6.1.md` before production deployment. Do not source-control or package generated key files.

## What v0.6.0 proves

Udaan now separates **content trust** from **content delivery**.

```text
Verified Udaan card bank
        ↓
Ed25519 signing authority
        ↓
Signed pack manifest + SHA-256 hashes
        ↓
Origin / future peer / school edge delivery
        ↓
Device verifies before accepting
        ↓
Encrypted IndexedDB content bank
```

Only the Udaan trust core approves/signs content. Distribution can later be performed by the origin, another verified device, or a school edge node without giving those distributors authority to alter educational content.

Direct WebRTC peer transfer is **not enabled yet** in v0.6.0. This release creates the signed-pack foundation required to add it safely in later v0.6.x releases.

## Added in v0.6.0

### 1. Signed content packs

- Card bank is automatically grouped into pillar packs.
- Default pack size: **112 cards**.
- Every pack receives:
  - SHA-256 content hash;
  - Ed25519 detached signature;
  - signing key ID;
  - pack ID / sequence / pillar metadata.
- A signed master manifest contains pack hashes, pack signatures, bank version and the public verification key.
- Browser verifies manifest + pack signatures before encrypted local storage.
- Content import now rebuilds signed packs automatically.
- If pack publication fails after a JSON import, the card bank is rolled back to its pre-import backup.

### 2. Up to 1,008 cards offline per device

The encrypted PWA device bank capacity is now:

```text
9 pillars × 112 cards = 1,008 cards maximum/device
```

This is a **capacity**, not forced duplication.

- Current seed bank: 432 cards, so a clean device can cache all currently available signed content.
- As the bank grows above 1,008, the device selector caps at 1,008 balanced cards.
- A verified learner also receives an unseen personalized offline-response reserve, up to the same capacity where suitable unseen content exists.
- Downloading a card does not mark it learned/seen; the no-repeat history advances when the learner actually receives/responds through the learning flow.

A scale test with 1,728 synthetic cards returned exactly:

```text
Think & Reason                 112
Science & Discovery            112
Roots & India                  112
Maths & Logic                  112
Build & Create                 112
Future & Careers               112
Values & Character             112
India & World                  112
Life Skills & Responsibility   112
                              ────
                              1,008
```

### 3. Q&A-first session composition

`action` / “tiny action” cards are now a **hard maximum of 1 per learning session**.

Default 27-card composition:

```text
Quiz / Q&A        13
Reveal / Info      9
Choice              4
Action              1
                  ───
                   27
```

Other supported lengths:

| Journey | Quiz | Reveal | Choice | Action |
|---:|---:|---:|---:|---:|
| 21 | 10 | 7 | 3 | 1 |
| 24 | 11 | 8 | 4 | 1 |
| 27 | 13 | 9 | 4 | 1 |
| 30 | 14 | 10 | 5 | 1 |
| 36 | 18 | 12 | 5 | 1 |

The offline personalized reserve is also arranged into 27-card micro-session chunks with at most one action card in each chunk. When enough Q&A/info cards exist, actions are not added merely to fill device capacity.

### 4. No predictable Q&A order between learners

Within the same room:

1. Each learner receives a separately randomized balanced selection/order.
2. The server computes a SHA-256 fingerprint of the **ordered card GUID sequence**.
3. A sequence already assigned to another learner in the room cannot be assigned again.
4. The final uniqueness check happens inside the locked room mutation to protect against concurrent joins.
5. A collision causes regeneration before the journey is issued.

The fingerprint contains no answers or personal data.

The same verified learner reopening the same room restores the **same frozen sequence**, rather than receiving a new order.

### 5. Answer-position bias removed

Quiz/choice/action option arrays are shuffled independently for every participant.

- Quiz: **3–5 options**.
- Choice / future preference: **3–8 options**.
- Action: **2–4 options**.
- Option order is saved in the participant journey reference.
- Refresh, PWA reopen and offline resume preserve that exact order.
- Correct-answer IDs remain stable even though their visual positions change.

A 200-journey bias test on the 3-option seed quizzes placed the correct answer approximately across all three positions rather than predominantly first.

### 6. Richer Future choices

The 12 seed Future preference cards now use **5 choices** instead of the old yes/maybe/later pattern:

- strongly interested;
- curious / show me more;
- try a small activity first;
- not sure yet;
- not for me right now.

Future imported choice cards may contain up to 8 options.


### Existing-bank Future-choice migration

The clean v0.6.0 package already contains five-option Future seed cards. The safe hotfix overlay deliberately does **not** overwrite `data/content/cards.json`, because a live installation may contain hundreds/thousands of imported cards.

After overlay deployment, Content Bank detects only the legacy three-option seed Future-interest cards and offers a one-click **v0.6 Content Migration**. The migration:

- creates a complete `cards.json` backup;
- preserves imported cards and all GUIDs;
- changes only matching legacy `future` + `choice` + `future_interest` cards;
- upgrades those options to the five approved IDs;
- atomically writes the bank;
- rebuilds signed packs;
- rolls back if signing/publication fails.

## PWA / offline storage

Preserved from v0.5.2:

- device-install UUID v4;
- server stores HMAC device hash only;
- AES-GCM encrypted IndexedDB vault;
- encrypted current journey/progress/answer queue;
- offline resume;
- encrypted server temporary state;
- future MariaDB event outbox.

New in v0.6.0:

- signed pack registry in encrypted IndexedDB;
- content trust record containing the trusted Ed25519 public key;
- up to 1,008 verified offline cards/device;
- personalized offline reserve up to 1,008 where unseen Q&A/info supply permits.

## New protected signing key

On the first signed-manifest/pack generation Udaan creates:

```text
data/content-signing.key
```

**Back this up immediately. Do not overwrite it in future upgrades.**

The file contains the origin Ed25519 private signing key. Devices learn only the public key.

If this key is accidentally replaced, devices that previously trusted the old signing key intentionally reject the new key until a controlled key-rotation mechanism is performed.

Also continue preserving:

- `data/app-secret.key`
- `data/storage-encryption.key`
- `data/content-admin.key`
- `data/content-signing.key`
- `data/content/cards.json`
- `data/history/`
- `data/user-cache/`
- `data/sync-outbox/`

## No database requirement

v0.6.0 still works without MariaDB.

```text
Content bank          JSON
Signed packs          JSON + Ed25519
Manifest              JSON + Ed25519
Temporary rooms       Redis or encrypted JSON
Learner history       Redis or encrypted JSON
User cache            Redis or encrypted JSON
Offline results       encrypted JSONL outbox
Device content        encrypted IndexedDB
```

MariaDB remains planned for production durability, RBAC, permanent accounts, audit/moderation, multi-device learner history, analytics and large-scale administrative workflows. Signed/P2P distribution will remain useful after MariaDB is introduced.

## Content distribution endpoints

Pretty URLs:

```text
/content/manifest
/content/pack/{pack-id}
```

The pack JSON files themselves remain under protected `data/`; they are delivered only through controlled endpoints.

## Health check

Open `/health.php` after deployment. Important v0.6.0 fields include:

```json
{
  "version": "0.6.0",
  "architecture_principle": "Centralize trust, decentralize distribution.",
  "offline_device_bank_capacity": 1008,
  "content_trust": "Ed25519 signed packs + SHA-256 integrity",
  "journey_privacy": {
    "room_unique_qna_order": true,
    "max_action_cards_per_session": 1,
    "quiz_options": "3–5",
    "choice_options": "3–8"
  }
}
```

`content_distribution.ready` must be `true` before marking v0.6.0 server verified. If it is false because Sodium is unavailable, enable PHP Sodium before approving the release.

## Deployment on existing v0.5.2 installation

Use the **hotfix overlay**, not the clean full package.

1. Back up the entire `data/` directory.
2. Preserve all protected keys/history/content.
3. Upload the v0.6.0 overlay.
4. Open `/health.php`.
5. Confirm signed distribution is ready.
6. The first distribution build creates `data/content-signing.key`.
7. Back up `data/content-signing.key` immediately.
8. Hard-refresh/reopen the PWA to activate `udaan-v0.6.0-static`.
9. Create a room and join with at least two different participants/devices.
10. Confirm different Q&A order and different option positions.
11. Confirm each journey has at most one action card.
12. Open Content Bank. If the **v0.6 Content Migration** panel appears, run it once. It backs up the live bank, upgrades only the 12 legacy seed Future-interest cards to five choices, preserves imported cards/GUIDs, and rebuilds signed packs.
13. Confirm Future choice cards show five options.
14. Test offline reserve/device bank and reconnect sync.
15. Run `docs/RELEASE_AUDIT_v0.6.0.md`.

## Release status

**v0.6.1 repository/runtime recovery code = BUILT + CI CERTIFIED.**  
Do not mark `SERVER VERIFIED` until the v0.6.1 live rotation and production checks pass.

## Student growth loop added to final v0.6.0

The distributed foundation is now surfaced through a simple student-facing growth loop rather than being infrastructure-only.

### Daily 9

`/daily`

- one card from each of the nine pillars;
- 9 cards total;
- approximately 60–120 seconds plus the reflection rail;
- device-GUID pseudonymous identity, no account required for the prototype;
- unseen GUIDs prioritized;
- 3 quiz + 3 reveal + 2 choice + maximum 1 action in the current 9-card target mix;
- independently shuffled card/options order;
- same encrypted/offline vault as live journeys;
- same 100-point ceiling model (10 verification/start points + 90 distributed card points).

### Offline Daily 9

After an online session prepares the encrypted device reserve, `/offline-learning.php` now serves the reserve in balanced **9-card offline Daily 9 chunks** where available, one pillar per chunk where supply permits. A learner can start the next offline Daily 9 from the remaining encrypted reserve without reconnecting.

### Friend Challenge

A completed Daily 9 creates a privacy-safe challenge URL:

```text
/challenge/{daily-room-uuid}
```

The friend receives the **same concepts** but:

- a different ordered card sequence;
- independently shuffled option positions;
- no personal identifier from the sender;
- no speed bonus;
- room-level SHA-256 sequence collision protection.

### Safe share card

Daily 9 completion renders a compact share surface showing only:

- nickname;
- Daily 9 completion;
- ideas explored;
- challenge link / QR.

It does **not** expose WhatsApp number, device GUID, school, learning history or detailed answers.

### Anonymous aggregate signals

First answers are counted into encrypted aggregate counters. Journey feedback may show privacy-safe signals such as:

```text
62% of 48 learners chose this.
```

No individual response is publicly exposed.

### Question of India prototype

`/question-of-india`

A deterministic approved quiz/choice card is selected for the day. A device may respond pseudonymously and then view anonymous aggregate percentages. This proves the future national shared-question network effect without requiring MariaDB.

### Mystery Card

`/mystery/{room-uuid}`

Daily 9 completion unlocks one non-scoring reveal card selected outside the completed concept set. It exists to reward curiosity, not to inflate points or screen time.

### Lightweight badges

Completion currently supports local/session badges such as:

- Daily 9 Finisher;
- 9-Pillar Explorer;
- Curiosity Champion;
- Fresh Explorer.

Permanent badge history remains a future MariaDB concern.

## North-star product metric

The product should optimize for **Weekly Meaningful Learners**, not raw time spent.

A meaningful learner is a learner who completes at least **3 learning sessions in a week**.

Future analytics should track first-card starts, Daily 9 completion, day-2/day-7 return, 3+ sessions/week, voluntary extra cards, friend challenges, teacher repeat use, opportunity opens, offline sessions and sync success.

## Future roadmap

See:

```text
/docs/FUTURE_DEVELOPMENT_PLAN_v0.6.0.md
```

The next planned release is **v0.6.1 — Opportunity & Future Radar**. Direct WebRTC/P2P pack transport remains planned for v0.6.6 after the product-growth and classroom layers are proven.
