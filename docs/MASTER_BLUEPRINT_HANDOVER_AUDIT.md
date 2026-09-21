# Udaan Live — Master Blueprint, Build Record, Audit & Recovery Handover

**Document version:** 1.3  
**Product build covered:** v0.1.0 → v0.6.0  
**Document date:** 04 September 2026  
**Canonical presentation URL:** `https://lexicon.digiti.in/ver/udan/`  
**Known Cloudways server path:** `/home/1323555.cloudwaysapps.com/pxjmtnyeba/public_html/ver/udan/`  
**Primary deployment model:** folder-installable Cloudways PHP application  
**Current persistence philosophy:** no production DB yet; Redis + AES-256-GCM encrypted temporary/pseudonymous state + AES-GCM encrypted IndexedDB + Ed25519 signed content packs; MariaDB migration planned  

---

# 0. Purpose of This File

This is the **single master recovery and continuation document** for Udaan Live.

If the original ChatGPT conversation, local build machine, or development notes are lost, this file should be enough to:

1. identify the current product architecture;
2. determine which release is actually live on the server;
3. pull the current server build safely;
4. understand all files that must be preserved;
5. recreate the development environment;
6. audit the live installation;
7. understand completed work and version history;
8. continue development without accidentally removing working features;
9. migrate temporary prototype data to MariaDB later;
10. preserve the product mission, content rules, security decisions, and low-network strategy.

**Never use a future ZIP as the only source of truth without first comparing it to the live server.** The live server may contain content-bank growth, learner history, secrets, import logs, and configuration that are intentionally excluded from hotfix ZIPs.

## Canonical Release Status (04 Sep 2026)

This table overrides any older wording elsewhere in this handover if there is a conflict.

| Version | Status | Meaning |
|---|---|---|
| v0.4.2 | SERVER CONFIRMED WORKING | User confirmed importer/application working in conversation. |
| v0.5.0 | BUILT + LOCALLY AUDITED | PWA/session Q&A cache package built; server deployment confirmation not yet recorded. |
| v0.5.1 | BUILT + LOCALLY AUDITED | Learning Reflection Rail integration; server deployment confirmation required via `/health.php`. |
| v0.5.2 | BUILT + LOCALLY AUDITED | Encrypted Offline Learning Vault, 108-card reserve, device-install UUID/HMAC, encrypted server temp state; server verification required. |
| v0.6.0 | BUILT + LOCALLY AUDITED | Distributed Content Foundation: signed packs, up to 1,008 offline cards/device, Q&A-first mix, unique room Q&A order, shuffled/frozen options; server verification required. |

**Rule for all future releases:** every build must update this master file, README, CHANGELOG, recovery notes, health expectations, protected-file list if needed, and rollback instructions. Documentation is part of the release.

---

# 1. Product North Star

Udaan is a student education, curiosity, character, future-readiness and opportunity platform.

The product should answer:

> **What useful knowledge, activity, opportunity, skill, value, historical context or future information is relevant to this student today?**

The intended long-term outcome is to help students become:

- knowledgeable;
- curious;
- scientifically minded;
- capable of critical thinking;
- skilled and future-ready;
- aware of opportunities;
- responsible and disciplined;
- connected to Indian history, knowledge traditions and culture;
- able to distinguish evidence, interpretation, traditional belief and mythology;
- globally aware without losing cultural grounding.

The experience should not become a generic news feed, entertainment feed, political persuasion product, or attention-maximizing social network.

Core principle:

> **Engagement must produce learning, reflection, discovery, action or progress.**

---

# 2. Current Product Experience

## 2.1 Presentation / live-room mode

The working presentation concept is:

```text
Presenter creates room
        ↓
Large-screen presenter dashboard
        ↓
QR code shown
        ↓
Audience scans QR
        ↓
Nickname
        ↓
WhatsApp number
        ↓
4-digit screen-based prototype OTP
        ↓
Verified learner session
        ↓
Randomized swipe micro-learning journey
        ↓
21 / 24 / 27 / 30 / 36 cards
        ↓
Daily Ladder + room-wide aggregates
        ↓
Completion = maximum 100 points
```

Recommended presentation setting:

```text
27 cards
≈ 2–3 minutes
3 cards from each of 9 pillars
```

The random journey must remain **frozen for the learner within the same verified room session**. Refresh, PWA reopen, temporary network loss, or re-verification in the same room must not reshuffle the card set.

---

# 3. Nine Learning Pillars

Canonical pillar keys are fixed and must not be renamed casually because the content bank and journey engine depend on them.

| Key | Display name |
|---|---|
| `think` | Think & Reason |
| `science` | Science & Discovery |
| `roots` | Roots & India |
| `math` | Maths & Logic |
| `build` | Build & Create |
| `future` | Future & Careers |
| `values` | Values & Character |
| `world` | India & World |
| `life` | Life Skills & Responsibility |

Current seed bank baseline:

```text
9 pillars × 48 cards = 432 seed cards
```

The architecture must **not** assume the bank remains at 432. It is designed to grow to thousands or more.

---

# 4. Supported Card Types

Current application-supported `kind` values:

```text
quiz
reveal
choice
action
```

Do not introduce a new card kind only in generated content. A new kind requires:

1. journey renderer support;
2. verifier/import schema support;
3. scoring behavior;
4. offline/local-cache handling;
5. presenter behavior where relevant;
6. tests;
7. blueprint/schema update.

---

# 5. Current Content-Bank Contract

Primary live content file:

```text
data/content/cards.json
```

Representative card fields:

```json
{
  "pillar": "science",
  "pillar_name": "Science & Discovery",
  "label": "SCIENCE & DISCOVERY",
  "topic": "gravity",
  "topic_name": "Gravity",
  "min_grade": 6,
  "max_grade": 10,
  "language": "en",
  "active": true,
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "kind": "quiz",
  "title": "What pulls objects toward Earth?",
  "subtitle": "Choose quickly, then learn from the explanation.",
  "options": [
    {"id": "a", "label": "Gravity"},
    {"id": "b", "label": "Magnetism"}
  ],
  "correct": "a",
  "explain": "Gravity attracts objects with mass toward one another.",
  "meta": {
    "source_title": "...",
    "source_section": "...",
    "source_reference": "...",
    "source_url": "...",
    "concept_key": "science:gravity:attraction",
    "educational_value": 95,
    "confidence": 0.99,
    "evidence_class": "modern_science",
    "time_sensitive": false,
    "expires_at": null,
    "generated_from_source": true
  }
}
```

Every production content addition should have a permanent GUID and should preserve source traceability where the card was generated from supplied source material.

---

# 6. Content Principles

## 6.1 Educational Value Gate

A student-facing card should help at least one of these:

```text
learn
understand
reason
question
observe
calculate
create
reflect
practise
build a skill
discover an opportunity
understand India
understand the world
prepare for the future
make a better decision
```

Avoid generating filler merely to increase card count.

## 6.2 Roots & India rule

Teach Indian history, mathematics, science, art, architecture, literature, philosophy, reform movements, traditional practices and cultural knowledge respectfully and evidence-consciously.

Distinguish:

```text
historically documented
traditional belief
mythology / legend
interpretation
modern scientific evidence
```

Do not convert mythology into modern scientific fact.

## 6.3 Current affairs rule

Convert current affairs into:

```text
WHAT HAPPENED
+
WHAT IT MEANS
+
WHAT A STUDENT CAN LEARN
```

Avoid sensationalism, partisan persuasion, outrage-oriented content, celebrity gossip and graphic material.

## 6.4 Values rule

Values cards should be practical, not preachy.

Prefer:

> Complete one task today before someone reminds you.

instead of abstract moral lecturing.

---

# 7. No-Repeat Learning Engine

This is a major product capability and must be preserved.

After verification, the raw WhatsApp number is transformed into an application-specific pseudonymous HMAC identity. Raw WhatsApp is not retained as long-term learning history.

Selection priority:

```text
1. eligible cards for learner/session
2. remove already-seen GUIDs
3. prefer unseen topics
4. balance all 9 pillars
5. balance interaction kinds
6. randomize within constraints
7. use review cards only after unseen supply is exhausted
```

Core rule:

> **New before review. Review before repeat.**

Verified behavior from v0.4.0 testing:

```text
Default journey = 27 cards
Visits 1–16 = all 432 seed cards exhausted with zero repeats
Visit 17 = smart review begins
```

If new content is added after a learner has exhausted older content, new unseen content must move ahead of review content automatically.

---

# 8. Fair Scoring / Daily Ladder

Random content must not create scoring unfairness.

Current model:

```text
Verification = 10 points
Journey       = remaining 90 points
Maximum       = 100 points
```

Regardless of selected journey size:

```text
21 cards → 100 max
24 cards → 100 max
27 cards → 100 max
30 cards → 100 max
36 cards → 100 max
```

No speed bonus.

Quiz correctness provides learning feedback but random difficulty should not create an unfair leaderboard advantage.

The ladder is intended to reward **participation and completion**, not compulsive app use.

---

# 9. Security Baseline — DO NOT REGRESS

The following security changes were introduced after a real Cloudways fatal error and are mandatory guardrails.

## 9.1 Dedicated PHP session

Cookie:

```text
UDAANLIVESESSID
```

Application session data is namespaced under:

```php
$_SESSION['udaan_live']
```

Do not reintroduce generic top-level session keys such as:

```php
$_SESSION['pending']
$_SESSION['hosts']
$_SESSION['csrf']
```

Why: v0.2.0 produced a real PHP 8 `TypeError` because another app on the same domain had a conflicting generic `pending` value.

## 9.2 Room IDs

New rooms use UUID v4 identifiers.

Example:

```text
/ver/udan/room/11e21297-3d8a-427e-bdcb-b4094c0676a6/join
/ver/udan/room/11e21297-3d8a-427e-bdcb-b4094c0676a6/verify
/ver/udan/room/11e21297-3d8a-427e-bdcb-b4094c0676a6/journey
/ver/udan/room/11e21297-3d8a-427e-bdcb-b4094c0676a6/complete
/ver/udan/room/11e21297-3d8a-427e-bdcb-b4094c0676a6/present
```

UUID discourages enumeration but **must not be treated as authorization**.

## 9.3 Host actions

Presenter mutation actions such as:

```text
reset room
load demo crowd
```

must remain:

```text
POST + server-side host session + CSRF
```

Do not put host secrets in presenter URLs.

## 9.4 Verification security

Prototype screen OTP rules:

```text
4-digit screen OTP
5-minute expiry
maximum 5 attempts
session ID regenerated after success
```

This is a prototype verification experience, **not real WhatsApp OTP**.

Production must replace it with an approved WhatsApp/SMS identity provider if phone verification remains required.

## 9.5 WhatsApp privacy

Current intended lifecycle:

```text
raw WhatsApp
   ↓
verification processing
   ↓
masked display + application-specific one-way/HMAC identity
   ↓
raw number discarded from long-lived learner history
```

Before production, obtain privacy/legal review and explicit retention/consent controls.

---

# 10. Light / Dark Design Baseline

Light and dark modes were added as real paired palettes, not CSS inversion.

Theme applies across:

- landing;
- join;
- WhatsApp entry;
- OTP;
- journey;
- completion;
- presenter screen;
- Daily Ladder;
- content/admin surfaces where implemented.

Theme preference uses browser `localStorage` and defaults to system preference on first visit.

Do not remove the theme switch when updating layout unless explicitly approved.

---

# 11. QR Architecture

v0.4.0 presenter QR originally relied on Python/`shell_exec()`, which is unreliable on Cloudways because shell functions may be disabled.

v0.4.1 fixed this.

Current QR rule:

```text
canonical UUID join URL
        ↓
bundled browser-local JavaScript QR engine
        ↓
SVG QR
```

No dependency on:

```text
shell_exec()
Python runtime for normal QR rendering
external QR API
CDN
third-party network call
```

Preserve this architecture.

---

# 12. Verified Content Import

Added in v0.4.2.

Canonical admin route:

```text
/admin/content-bank
```

Required workflow:

```text
Paste JSON
   ↓
VERIFY
   ↓
Ready / Needs Review / Rejected
   ↓
Import Verified Cards
```

**Import must not be possible before verification.**

Verification should check at minimum:

```text
JSON syntax
schema
UUID/GUID validity
valid pillar
valid card kind
grade range
required fields
quiz options
quiz correct answer integrity
duplicate GUID against bank
duplicate GUID within batch
exact duplicate concept
likely semantic duplicate
```

Verified batch is stored as a temporary **server-side snapshot bound to the admin session**. Final import must import the verified snapshot, not whatever has subsequently changed in the textarea.

Snapshot timeout baseline:

```text
30 minutes
```

Import safety:

```text
current cards.json
   ↓
full backup
   ↓
merge verified cards
   ↓
write temp file
   ↓
read + validate complete bank
   ↓
atomic rename
   ↓
live cards.json
```

Backup folder:

```text
data/content/backups/
```

Import log:

```text
data/content/import-log.json
```

Content admin key:

```text
data/content-admin.key
```

or environment variable:

```text
UDAAN_CONTENT_ADMIN_KEY
```

Preserve the key across upgrades.

---

# 13. Known JSON Import Failure & Lesson

A real generated GPT batch failed with:

```text
Invalid JSON: Syntax error
```

Root cause was an unescaped quote:

```json
"source_section": ""SCO Plus" Format"
```

Correct JSON:

```json
"source_section": "\"SCO Plus\" Format"
```

Custom GPT generation instructions must explicitly require:

```text
serialize as strict JSON
escape internal double quotes as \"
no comments
no trailing commas
no Markdown fences when import mode is requested
validate the entire JSON before returning it
```

Future importer improvement: show line/column and parser context instead of only `Syntax error`.

---

# 14. Custom GPT — Udaan Card Factory

Purpose: convert approved/pasted source material into importable Udaan cards.

Current setup files generated during development:

```text
UDAAN_CUSTOM_GPT_INSTRUCTIONS_COMPACT.md
UDAAN_CUSTOM_GPT_KNOWLEDGE.md
UDAAN_CUSTOM_GPT_PROMPT_TEMPLATE.md
UDAAN_CARD_OUTPUT_EXAMPLE.json
UDAAN_CUSTOM_GPT_SETUP.zip
```

Important design decision:

- Custom GPT Instructions must stay under the platform's 8,000-character limit.
- Detailed schema, pillar rules and examples live in Knowledge.
- Generated import mode must use only the app-supported four card kinds.
- Existing bank should be supplied during major batch generation when possible to reduce semantic duplicates.

Recommended operating flow:

```text
source material
   ↓
Udaan Card Factory
   ↓
strict JSON array
   ↓
Udaan Content Bank
   ↓
Verify
   ↓
Import Verified Cards
```

---

# 15. PWA / Low-Network Architecture — v0.5.0

**Status:** BUILT / LOCALLY AUDITED. Server deployment must be confirmed with `/health.php` before calling v0.5.0 the live baseline.

v0.5.0 adds:

```text
manifest.webmanifest
sw.js
offline.html
assets/icons/icon-192.png
assets/icons/icon-512.png
assets/js/pwa.js
```

Primary goals:

- installable PWA;
- fast repeat loads;
- low-network resilience;
- resume current journey after refresh/reopen;
- freeze same random Q&A set for the same verified room session;
- locally queue answers during temporary connectivity loss;
- synchronize after connection returns.

---

# 16. Current Persistence Layers — v0.5.2

## Layer A — Device installation identity

The PWA generates a random UUID v4 called `device_install_id`. It is an application installation identifier only — never IMEI, MAC, advertising ID, or browser fingerprint. The raw UUID remains on the device. Server state receives only `device_hash = HMAC(device_install_id)`. Learner identity and device identity remain separate.

## Layer B — AES-GCM encrypted browser learning vault

IndexedDB database: `udaan-learning-vault`.

Encrypted with a non-exportable Web Crypto AES-GCM 256-bit key. It contains the current frozen randomized journey, current position, answers, pending queue, encrypted seen-GUID backup, signed offline sync token, and the offline reserve. `localStorage` must contain only the random device-install UUID; do not reintroduce plaintext Q&A/session payloads.

## Layer C — 108-card offline reserve

Default reserve is **108 cards = 12 cards × 9 pillars**. It is selected from approved active content, prioritizes unseen cards, and excludes the active room journey. Downloading the reserve does not itself mark all reserve cards seen. Seen history updates when the learner responds and the offline event syncs. Reserve activity does **not** affect the live Daily Ladder.

## Layer D — Temporary room state

Default TTL remains 6 hours. Storage is Redis preferred or `data/rooms/*.json` fallback. In v0.5.2 room payloads are encoded using the `UDENC1.` AES-256-GCM envelope when PHP OpenSSL supports `aes-256-gcm`. Legacy plaintext v0.5.1 records remain readable for forward migration.

## Layer E — Temporary per-user/device cache

Default TTL is now **7 days / 604,800 seconds** to support PWA/offline continuity. Storage is Redis or `data/user-cache/{learning_hash}.json`. It freezes the room journey, restores answers/progress, remembers device hashes and offline-reserve IDs, and de-duplicates offline event GUIDs. It is encrypted at rest in v0.5.2.

## Layer F — Long-lived pseudonymous no-repeat history

Redis `udaan:history:{learning_hash}` or JSON fallback under `data/history/`. Stores only pseudonymous learner hash, seen card/topic GUIDs and timestamps. It is encrypted at rest under the same server envelope when supported.

## Layer G — Encrypted future-DB sync outbox

Path: `data/sync-outbox/YYYY-MM-DD.jsonl`. Each line is an encrypted `UDENC1.` envelope containing a UUID `event_id` and one event such as `session_verified`, `card_answered`, `journey_completed`, or `offline_card_answered`. This outbox is the DB backfill/resilience contract.

## Server encryption key

`data/storage-encryption.key` is generated on first encrypted write and MUST be preserved on every future upgrade. It is distinct from `data/app-secret.key`. Losing `app-secret.key` breaks learner/device HMAC continuity; losing `storage-encryption.key` breaks decryption of encrypted temporary state/outbox. Neither secret belongs in reusable release ZIPs.

# 17. Future MariaDB Migration Contract

When MariaDB is introduced, it becomes the durable source of truth for learner/result history.

Target conceptual tables:

```text
learner_identities
learner_devices
learning_journeys
learning_journey_cards
learner_card_answers
learner_seen_cards
offline_learning_events
sync_import_events
```

Migration/backfill rule:

```text
JSONL outbox
   ↓
chronological batches
   ↓
DB transaction
   ↓
insert event_id into sync_import_events UNIQUE
   ↓
if event_id already exists → skip
   ↓
upsert journey / answer / seen-card records
   ↓
commit
   ↓
archive imported JSONL only after commit
```

This makes replay idempotent.

After DB rollout:

```text
MariaDB = durable source of truth
Redis   = hot active-room/session/leaderboard cache
Browser = offline/low-network acceleration
JSONL   = resilience buffer / temporary DB outage queue
```

Do not remove Redis/browser cache merely because MariaDB is added.

---

# 18. Canonical v0.5.0 File Map

Current generated v0.5.0 package contains:

```text
.htaccess
CHANGELOG.md
README.md
api/answer.php
api/state.php
assets/css/app.css
assets/icons/icon-192.png
assets/icons/icon-512.png
assets/js/journey.js
assets/js/present.js
assets/js/pwa.js
assets/js/qr-local.js
assets/js/theme.js
bootstrap.php
cleanup.php
config.php
content_admin.php
data/.htaccess
data/content/cards.json
data/history/.gitkeep
data/insights/.gitkeep
data/rooms/.gitkeep
data/sync-outbox/.gitkeep
data/user-cache/.gitkeep
demo_fill.php
docs/CONTENT_BANK.md
docs/DB_FUTURE_SCHEMA.sql
docs/PWA_AND_PERSISTENCE_BLUEPRINT.md
docs/cloudways.txt
done.php
health.php
index.php
join.php
journey.php
lib/ContentImport.php
lib/Store.php
lib/helpers.php
manifest.webmanifest
offline.html
present.php
python/build_seed_bank.py
python/make_qr.py
python/room_insights.py
qr.php
reset.php
sw.js
verify.php
```

Pretty routing is provided through `.htaccess` and PHP route handling; do not remove the direct PHP files unless routing is re-audited.

---

# 19. Files / Data That Must Be Preserved During Upgrades

**Never blindly overwrite or delete:**

```text
data/app-secret.key
```

Reason: stable HMAC identity. Replacing it changes all pseudonymous learner identities and effectively resets no-repeat continuity.

```text
data/content-admin.key
```

Reason: Content Bank admin access unless supplied via environment variable.

```text
data/content/cards.json
```

Reason: live growing content bank. The server may contain many more than the seed 432 cards.

```text
data/history/
```

Reason: no-repeat learner history.

```text
data/sync-outbox/
```

Reason: future MariaDB backfill/resilience events.

Also preserve/audit when present:

```text
data/content/import-log.json
data/content/backups/
data/user-cache/
data/rooms/
data/insights/
data/php-error.log
```

`data/rooms/` and `data/user-cache/` are temporary and may be intentionally cleared during maintenance, but should not be deleted blindly during a live event.

---

# 20. Version History & Status Matrix

## v0.1.0 — Premium Student Experience Prototype

**Status:** historical prototype / superseded.

Purpose:

- folder-installable PHP concept;
- premium minimal student dashboard;
- Daily 7;
- Roots & India;
- future pathways;
- Daily Leaderboard;
- PWA concept;
- MariaDB/Redis starter architecture.

This version established the visual/product direction but was superseded by the live-room presentation product.

---

## v0.2.0 — Udaan Live Presentation Prototype

**Status:** superseded; do not deploy.

Added:

- QR-based room join;
- nickname + WhatsApp;
- screen-based demo OTP;
- five fixed swipe interactions;
- temporary Redis/JSON room state;
- presenter live screen;
- Daily Ladder;
- no MariaDB.

Known issue:

- generic PHP session keys caused a real Cloudways PHP 8 fatal error.

Do not use v0.2.0 as a base.

---

## v0.2.1 — Verify Hotfix + UUID Pretty URLs + Themes

**Status:** historical verified security baseline; features retained in all later builds.

Fixed:

- `Cannot access offset of type string on string` in `verify.php`;
- generic PHP session-key collision;
- unsafe pending OTP assumptions;
- hidden `mbstring` dependency;
- blank fatal-error experience.

Added/secured:

- `UDAANLIVESESSID`;
- `$_SESSION['udaan_live']` namespace;
- UUID v4 room IDs;
- pretty URLs;
- POST + CSRF host controls;
- session regeneration;
- 5-attempt OTP limit;
- raw WhatsApp discard;
- private PHP error log;
- light/dark themes.

---

## v0.2.2 / v0.3.0 — Intermediate Development Branches

**Status:** not independent production baselines; superseded by v0.4.0.

These branches represented the transition from a short fixed journey toward a much larger randomized data-bank model. Their checked-in changelog metadata was not consistently updated, so they must **not** be treated as canonical release documentation.

The canonical large-bank implementation starts at v0.4.0.

---

## v0.4.0 — Unlimited Learning Bank & No-Repeat Engine

**Status:** locally audited and later used as deployed baseline before subsequent hotfixes.

Added:

- 432-card seed bank;
- 9 pillars × 48;
- permanent deterministic card GUIDs;
- 21/24/27/30/36-card journeys;
- balanced randomization;
- no duplicate card in journey;
- pseudonymous learner identity;
- persistent no-repeat history;
- unseen-before-review selection;
- same 100-point maximum for all configured journey lengths;
- live presenter progress and future-interest aggregation.

Verified test:

- 16 default 27-card visits consumed 432 unique cards with zero repeats;
- review began only after exhaustion;
- newly introduced unseen content was prioritized over review.

---

## v0.4.1 — QR Reliability Hotfix

**Status:** server-facing hotfix; subsequent releases inherit it.

Problem:

- Python + `shell_exec()` QR path was unreliable on Cloudways.

Fix:

- browser-local bundled JavaScript SVG QR renderer;
- no shell execution;
- no external QR API/CDN dependency;
- canonical UUID join URL encoded directly.

---

## v0.4.2 — Verified JSON Content Import

**Status:** **SERVER VERIFIED / WORKING** based on user confirmation before v0.5.0 work.

Added:

- `/admin/content-bank`;
- paste JSON;
- mandatory verify-before-import;
- Ready / Needs Review / Rejected classifications;
- session-bound verified snapshot;
- 30-minute verified batch lifetime;
- duplicate GUID/concept checks;
- semantic duplicate exclusion;
- full backup;
- temp-file validation;
- atomic live-bank replacement;
- import audit log;
- Content Admin access key.

Real import test during development:

- clean test card verified;
- bank temporarily moved 432 → 433;
- duplicate re-import blocked;
- release package restored to clean seed state before packaging.

A real GPT-generated batch later exposed malformed JSON caused by an unescaped internal quote. The corrected 31-card file parsed successfully. Importer error messaging should be improved in a future build.

---

## v0.5.0 — PWA + Q&A Session Cache + Future DB Outbox

**Status:** **BUILT / LOCALLY AUDITED; SERVER DEPLOYMENT CONFIRMATION REQUIRED.**

Added:

- PWA manifest;
- service worker;
- local PWA icons;
- static-asset cache;
- browser-local current Q&A JSON cache;
- frozen random journey per verified learner + room;
- local current-card/answer restore;
- offline/timeout answer queue;
- auto-sync on reconnect;
- server temporary user cache;
- 24-hour default user-cache TTL;
- pseudonymous JSONL future-DB outbox;
- PWA/cache/persistence details in health endpoint;
- future MariaDB schema/contract documentation.

Local verification performed:

```text
same verified learner
same room
same 27-card sequence after re-verification = PASS
previous answer restored = PASS
journey not reshuffled = PASS
```

Before declaring v0.5.0 live, server `/health.php` must report v0.5.0 and the PWA/cache fields described below.

---

# 21. Expected Health Responses

## Known server-verified v0.4.0 response

A previously reported live response was:

```json
{
  "ok": true,
  "app": "Udaan Live",
  "version": "0.4.0",
  "room_storage": "Temporary JSON",
  "history_storage": "Pseudonymous JSON history",
  "session_name": "UDAANLIVESESSID",
  "pretty_urls": true,
  "room_id": "uuid-v4",
  "content_bank": {
    "count": 432,
    "pillars": 9,
    "by_pillar": {
      "think": 48,
      "science": 48,
      "roots": 48,
      "math": 48,
      "build": 48,
      "future": 48,
      "values": 48,
      "world": 48,
      "life": 48
    },
    "version": "2026.09.seed1"
  },
  "journey_lengths": [21,24,27,30,36]
}
```

## Expected v0.5.0 additions

After v0.5.0 is deployed, `/health.php` should additionally report concepts equivalent to:

```text
version: 0.5.0
pwa: true
service_worker: sw.js
browser_qna_cache: localStorage + session resume
offline_answer_queue: true
sync_outbox: data/sync-outbox/*.jsonl
user_cache_storage: Redis or pseudonymous JSON
user_cache_ttl_seconds: 86400
room_ttl_seconds: 21600
qr_engine: Browser-local JavaScript SVG (no shell_exec)
content_import: paste-verify-import
```

---

# 22. Cloudways Deployment / Recovery Playbook

## 22.1 First principle

If this chat is lost, **pull the live server before making a new release**.

Do not assume the latest local ZIP matches the server's live content/state.

## 22.2 Create an immutable server backup

From SSH, use a timestamped archive outside the app folder if permissions allow:

```bash
cd /home/1323555.cloudwaysapps.com/pxjmtnyeba/public_html/ver

tar -czf "$HOME/udaan-server-backup-$(date +%Y%m%d-%H%M%S).tar.gz" udan
```

Also copy the full `/udan/` folder locally through SFTP before a major release.

## 22.3 Files to inspect first

```text
config.php
bootstrap.php
health.php
CHANGELOG.md
README.md
data/content/cards.json
data/app-secret.key
data/content-admin.key
data/history/
data/sync-outbox/
```

Do not print secret-key contents into tickets, chat, Git commits or public logs.

## 22.4 Get deployed version

Open:

```text
https://lexicon.digiti.in/ver/udan/health.php
```

Record:

```text
version
content_bank.count
content_bank.version
room storage backend
history backend
user-cache backend
PWA status
journey lengths
```

## 22.5 Compare server against candidate release

Before upload:

```bash
diff -ruN server-pull/ candidate-release/ > udaan-release-diff.txt
```

Review especially:

```text
routing
bootstrap/session logic
Store.php
helpers.php
journey.js
answer API
content importer
service worker
.htaccess
```

Do not wholesale-replace `data/`.

## 22.6 Writable paths

For v0.5.0, PHP must be able to write:

```text
data/rooms/
data/history/
data/user-cache/
data/sync-outbox/
data/insights/
data/content/
data/content/backups/
data/imports/pending/
```

Exact permissions should follow Cloudways ownership/security practices. Do not default to world-writable `777` unless there is no safer alternative and it is temporary.

---

# 23. Release Guardrails

Every future release should obey these rules.

## Preserve

- current live content bank;
- app secret;
- content admin key;
- learner no-repeat history;
- sync outbox;
- live route inventory;
- UUID route structure;
- dedicated session cookie/namespace;
- content import verification;
- theme switch;
- browser-local QR;
- no-repeat engine;
- fair 100-point scoring;
- low-network behavior;
- presenter mode.

## Do not

- revert to sequential/enumerable room IDs;
- put secrets into URLs;
- use GET for destructive host actions;
- bypass JSON verification;
- overwrite `cards.json` without backup;
- replace `data/app-secret.key`;
- store raw WhatsApp in long-lived history;
- assume MariaDB exists before migration is explicitly enabled;
- remove JSON/Redis fallback during DB migration;
- reward speed in the Daily Ladder;
- display raw internet content directly to students.

---

# 24. Release Audit Checklist

Before calling any new release VERIFIED:

## A. Syntax / runtime

- [ ] PHP syntax passes for all PHP files.
- [ ] JavaScript has no syntax errors.
- [ ] Python helper scripts compile if still used.
- [ ] `/health.php` returns HTTP 200 and valid JSON.
- [ ] no new PHP fatal in Cloudways logs.

## B. Routing

- [ ] home loads under `/ver/udan/`.
- [ ] UUID room creates successfully.
- [ ] `/room/{uuid}/join` works.
- [ ] `/verify` works.
- [ ] `/journey` works.
- [ ] `/complete` works.
- [ ] `/present` works.
- [ ] `/admin/content-bank` works.

## C. Verification/security

- [ ] nickname + WhatsApp accepted.
- [ ] screen OTP generated.
- [ ] wrong OTP attempts limited.
- [ ] expired OTP blocked.
- [ ] session ID regenerates after verification.
- [ ] `UDAANLIVESESSID` present.
- [ ] no generic session key regression.
- [ ] reset/demo endpoints reject GET.
- [ ] host actions require CSRF.
- [ ] no host secret in URL.

## D. Random journey

- [ ] selected journey length correct.
- [ ] no duplicate GUID in one journey.
- [ ] pillars balanced.
- [ ] same room/session does not reshuffle on refresh/rejoin.
- [ ] different future room prefers unseen cards.
- [ ] score maximum remains 100.
- [ ] no speed bonus.

## E. No-repeat

- [ ] existing learner gets unseen cards first.
- [ ] history survives room reset.
- [ ] new imported cards are prioritized.
- [ ] app-secret is unchanged from previous live build.

## F. Presenter

- [ ] QR scans correctly.
- [ ] participant count updates.
- [ ] progress updates.
- [ ] ladder updates.
- [ ] aggregate future interest updates.
- [ ] late participants can join.

## G. Content import

- [ ] malformed JSON blocked.
- [ ] valid JSON verifies.
- [ ] duplicate GUID blocked.
- [ ] broken quiz answer blocked.
- [ ] semantic duplicate excluded/reviewed.
- [ ] import unavailable before verification.
- [ ] verified snapshot expires.
- [ ] backup created before import.
- [ ] bank JSON remains valid after import.
- [ ] health bank count increases.
- [ ] import log records result.

## H. PWA / cache — v0.5+

- [ ] manifest loads.
- [ ] service worker registers over HTTPS.
- [ ] icons load.
- [ ] repeat load uses cached shell/static assets.
- [ ] current journey restores after refresh.
- [ ] current answer state restores.
- [ ] offline/pending answer queues locally.
- [ ] reconnect syncs queued answer.
- [ ] same random selection is retained.
- [ ] browser cache stores no raw WhatsApp.

## I. Data preservation

- [ ] `cards.json` backed up.
- [ ] `app-secret.key` preserved.
- [ ] `content-admin.key` preserved.
- [ ] `history/` preserved.
- [ ] `sync-outbox/` preserved.
- [ ] content backup directory preserved.

Only after all applicable items pass should a build be labelled **SERVER VERIFIED**.

---

# 25. Current Known Limitations

This is still a presentation/prototype platform, not a production student identity and analytics system.

Known limitations:

1. screen OTP is not real WhatsApp delivery;
2. no MariaDB source of truth yet;
3. no full account/consent/deletion workflow;
4. no formal parent/minor consent model;
5. no complete role/RBAC system beyond prototype admin protection;
6. semantic duplicate detection is intentionally lightweight;
7. Custom GPT-generated content still requires verification/review;
8. time-sensitive content needs stronger automatic expiry enforcement;
9. no formal moderation/reviewer workflow yet;
10. no robust observability/centralized structured error dashboard yet;
11. no production analytics warehouse;
12. JSON storage is acceptable for prototype scale but should not become the permanent high-concurrency source of truth;
13. v0.5.2 encrypted offline/PWA behavior must be confirmed on the real server before being marked live.

---

# 26. Future Work — Approved Direction

## Priority 1 — Deploy & verify v0.5.2

- [ ] back up current server;
- [ ] preserve all protected data;
- [ ] deploy v0.5.2 encrypted-offline overlay;
- [ ] confirm health version;
- [ ] test PWA installation;
- [ ] test same-session random freeze;
- [ ] test answer offline queue/reconnect;
- [ ] test outbox creation;
- [ ] confirm 108-card encrypted offline reserve and reconnect sync;
- [ ] confirm server `AES-256-GCM` health status and back up `storage-encryption.key`;
- [ ] mark v0.5.2 SERVER VERIFIED only after live audit.

## Priority 2 — Better Content Import Diagnostics

- [ ] show JSON error line number;
- [ ] show column number;
- [ ] show nearby parser context;
- [ ] identify likely unescaped quote;
- [ ] add one-click sanitized copy only when repair is unambiguous;
- [ ] retain verify-before-import rule.

## Priority 3 — Content Bank Management

- [ ] browse/search cards;
- [ ] filter by pillar/topic/grade/kind/source;
- [ ] deactivate/reactivate card;
- [ ] edit with audit history;
- [ ] content-bank versioning;
- [ ] rollback selected import;
- [ ] download rejected/review cards;
- [ ] source quality/trust metadata;
- [ ] expiry management for time-sensitive cards.

## Priority 4 — MariaDB Persistence

- [ ] create migration schema from `docs/DB_FUTURE_SCHEMA.sql`;
- [ ] add `sync_import_events.event_id UNIQUE`;
- [ ] build JSONL backfill command;
- [ ] dry-run importer;
- [ ] idempotency test;
- [ ] migrate sample outbox;
- [ ] compare DB vs JSON history;
- [ ] enable dual-write period;
- [ ] switch MariaDB to durable source of truth;
- [ ] retain Redis hot cache + browser PWA cache + JSONL resilience outbox.

## Priority 5 — Real Identity / Consent

- [ ] approved WhatsApp OTP provider;
- [ ] explicit consent and retention screen;
- [ ] learner history deletion;
- [ ] legal/privacy review;
- [ ] minor/student safeguards;
- [ ] rate limiting and abuse protection.

## Priority 6 — Personalization

- [ ] class/grade profile;
- [ ] preferred language;
- [ ] board;
- [ ] state/location;
- [ ] interests;
- [ ] difficulty adaptation;
- [ ] learning state: NEW / LEARNING / MASTERED;
- [ ] spaced review;
- [ ] weaker-concept reinforcement;
- [ ] user-selectable learning goals.

## Priority 7 — Content Factory

- [ ] source registry;
- [ ] trusted source tiers;
- [ ] raw content intake;
- [ ] AI extraction/classification;
- [ ] deduplication;
- [ ] human review;
- [ ] approval/publish workflow;
- [ ] source kill switch;
- [ ] expiry monitoring;
- [ ] audit trail.

## Priority 8 — Ecosystem

Later phases:

- parent module;
- teacher module;
- school/college accounts;
- institution publishers;
- industry programs;
- mentors;
- scholarships/opportunity engine;
- career pathways;
- student assistant.

---

# 27. Future Production Architecture

Target architecture once production persistence is enabled:

```text
STUDENT PWA
   │
   ├── local static cache
   ├── current journey cache
   └── offline answer queue
          │
          ▼
PHP APPLICATION / API
          │
          ├── Redis
          │     active rooms
          │     sessions
          │     hot no-repeat cache
          │     leaderboard
          │     rate limits
          │
          ├── MariaDB
          │     durable learner identity
          │     journeys
          │     answers
          │     seen history
          │     content metadata
          │     import audit
          │
          ├── JSONL resilience outbox
          │
          └── Python workers
                ingestion
                classification
                dedupe
                AI processing
                analytics
```

Cloudways target remains compatible with:

```text
PHP
MariaDB
Redis
Supervisor
Cron
Python workers
```

---

# 28. Data Factory / Control Center / Student Product Separation

Long-term system must remain divided conceptually into:

## A. Data Factory

```text
source discovery
collection
extraction
AI classification
deduplication
freshness/expiry
```

## B. Control Center

```text
verification
editing
approval
source management
publishing
rollback
audit
analytics
```

## C. Student Product

```text
personalization
swipe journey
Daily Ladder
future guidance
learning cards
saved content
notifications
PWA/offline
```

Raw internet content must never move directly from Data Factory to Student Product without the control/approval layer.

---

# 29. How to Resume Development After Losing Context

Follow this exact sequence.

## Step 1 — obtain this file

Read this entire master handover.

## Step 2 — query live health

```text
https://lexicon.digiti.in/ver/udan/health.php
```

Do not assume the document's last generated version is the live version.

## Step 3 — pull live server

Download or archive:

```text
/home/1323555.cloudwaysapps.com/pxjmtnyeba/public_html/ver/udan/
```

## Step 4 — make a working copy

Never develop directly on the only live copy.

## Step 5 — identify protected state

Preserve:

```text
data/app-secret.key
data/content-admin.key
data/content/cards.json
data/history/
data/sync-outbox/
```

## Step 6 — run audit checklist

Use Section 24.

## Step 7 — compare against last known package

Latest generated package covered by this document:

```text
Udaan Live v0.5.0
```

But the last server-confirmed functional baseline before v0.5.0 work was v0.4.2 behavior. Always use `/health.php` to determine reality.

## Step 8 — create next release as overlay where possible

Prefer a small update package that does not include protected `data/` state.

## Step 9 — preserve changelog

Every release must update:

```text
CHANGELOG.md
README.md
this master handover or its successor
health.php version
```

## Step 10 — mark status precisely

Use only:

```text
PLANNED
BUILT
LOCALLY AUDITED
DEPLOYED
SERVER VERIFIED
ROLLED BACK
SUPERSEDED
```

Never call something verified solely because a ZIP was generated.

---

# 30. Versioning Convention Going Forward

Recommended:

```text
v0.5.1 = Learning Reflection Rail hotfix
v0.5.2 = Encrypted Offline Learning Vault
v0.6.0 = meaningful feature group
v1.0.0 = first production-approved architecture
```

Each release note should include:

```text
version
release title
base version
files changed
schema/state changes
protected files
migration steps
rollback steps
local tests
server tests
known limitations
```

---

# 31. Suggested Next Baseline

If live deployment and the v0.5.2 audit pass, declare:

> **v0.5.2 — Encrypted Offline Learning Vault — SERVER VERIFIED**

Do **not** declare v0.5.2 until the real server `/health.php`, 108-card offline reserve, encrypted PWA resume, reconnect sync, importer and presenter regressions all pass.

After that, the next planned feature release should be:

> **v0.6.0 — Content Operations & MariaDB Transition Foundation**

Recommended v0.6.0 scope:

1. enhanced JSON error diagnostics;
2. Content Bank browse/search/edit/deactivate;
3. import rollback UI;
4. structured audit log;
5. MariaDB schema/migrator behind feature flag;
6. JSONL backfill command;
7. dual-write test mode;
8. Redis remains active hot cache;
9. encrypted PWA/offline vault preserved unchanged.

Do not add broad new social features before this persistence/control foundation is stable.

---

# 32. Final Product Guardrail

The application's technical sophistication is secondary to the learning mission.

Every future change should be checked against four questions:

```text
Does it help a student learn or think?
Does it preserve trust and safety?
Does it remain fast on weak networks?
Does it keep the content system auditable and recoverable?
```

If not, it should not receive priority merely because it increases engagement metrics.

---

# 33. Recovery Summary — One Screen

```text
PRODUCT
Udaan Live

LIVE URL
https://lexicon.digiti.in/ver/udan/

KNOWN SERVER PATH
/home/1323555.cloudwaysapps.com/pxjmtnyeba/public_html/ver/udan/

LAST CONFIRMED WORKING FEATURE BASELINE
v0.4.2 behavior: verified JSON importer + v0.4.1 QR + v0.4.0 no-repeat engine

LATEST GENERATED / LOCALLY TESTED BUILD
v0.5.1: v0.5.0 PWA/session cache + 3-second Learning Reflection Rail

SEED BANK
432 cards
9 pillars × 48

DEFAULT JOURNEY
27 cards
≈ 2–3 minutes

MAX SCORE
100

SESSION COOKIE
UDAANLIVESESSID

ROOM ID
UUID v4

CURRENT DATABASE
None required

TEMP STORAGE
Redis preferred / JSON fallback

LONGER NO-REPEAT HISTORY
Pseudonymous Redis / JSON

CONTENT BANK
data/content/cards.json

IMPORT
/admin/content-bank
Paste → Verify → Import Verified Cards

MUST PRESERVE
data/app-secret.key
data/content-admin.key
data/content/cards.json
data/history/
data/sync-outbox/

FUTURE DATABASE
MariaDB

FUTURE CACHE
Redis remains

FUTURE LOW-NETWORK LAYER
PWA/browser cache remains

NEXT SAFE ACTION
Pull live server → health check → backup protected keys/data → diff → deploy/audit v0.5.2 → only then start v0.6.0
```

---



---

# 34. v0.5.1 — Learning Reflection Rail

**Release state in this document:** BUILT + LOCALLY AUDITED; server verification pending.

## Purpose

Integrate the approved minimal 3-second blurred-spectrum footer interaction as an education-oriented reflection interval. It should increase useful dwell time without creating an infinite-feed mechanic.

## Student journey behavior

```text
Answer / reveal
      ↓
Save to server or local offline queue
      ↓
Show correctness / explanation / learning feedback
      ↓
3-second Learning Reflection Rail
      ↓
Advance to next card
```

At the recommended 27 cards, the default 3-second interval adds approximately 81 seconds of feedback-viewing time. This supports the 2–3 minute presentation engagement target.

## Files added / changed

```text
ADDED
assets/js/learning-progress.js
docs/LEARNING_REFLECTION_RAIL.md

CHANGED
assets/css/app.css
assets/js/journey.js
lib/helpers.php
config.php
index.php
join.php
verify.php
journey.php
present.php
content_admin.php
health.php
sw.js
README.md
CHANGELOG.md
docs/PWA_AND_PERSISTENCE_BLUEPRINT.md
docs/MASTER_BLUEPRINT_HANDOVER_AUDIT.md
```

## Config

```php
'card_reflection_seconds' => 3,
```

This is presentation/learning UX only. It does not change the content schema, learner GUID history, score model, or future MariaDB event contract.

## PWA impact

Service-worker version changes to:

```text
udaan-v0.5.1
```

and precaches:

```text
assets/js/learning-progress.js?v=0.5.1
```

Hard refresh / service-worker activation is required after deployment to avoid stale v0.5.0 assets.

## Accessibility

`prefers-reduced-motion` keeps the countdown semantics but disables the animated width transition and reduces visual blur motion.

## Server verification required

`/health.php` must report:

```json
{
  "version": "0.5.1",
  "pwa": true,
  "learning_reflection_rail": true,
  "card_reflection_seconds": 3
}
```

Live regression:

- create UUID room;
- join + screen OTP;
- answer a fresh quiz;
- confirm feedback remains visible for ~3 seconds;
- confirm bottom spectrum rail/countdown;
- confirm swipe cannot bypass interval;
- confirm next card advances automatically;
- test reveal/choice/action;
- test offline answer;
- refresh/reverify and confirm frozen Q&A session;
- verify Content Bank paste → verify → import remains working;
- verify light/dark mode;
- verify presenter QR and ladder.

## Rollback

Rollback to v0.5.0 is code-only. Do not overwrite or remove:

```text
data/app-secret.key
data/content-admin.key
data/content/cards.json
data/history/
data/sync-outbox/
```

No data migration is required.

# 35. Documentation-as-Release Guardrail

From v0.5.1 onward, every future release MUST update at minimum:

```text
README.md
CHANGELOG.md
docs/MASTER_BLUEPRINT_HANDOVER_AUDIT.md
relevant subsystem blueprint(s)
health.php expectations
version string / service-worker cache version
rollback notes
protected-file notes when changed
local + server audit checklist
```

A release is incomplete if code changes but recovery/version documentation is not updated.


# 35. v0.5.2 — Encrypted Offline Learning Vault

## Completed in build

- Device Install UUID v4 generated locally; no hardware/browser fingerprinting.
- Server stores only HMAC `device_hash`.
- Browser current journey/progress/answers/pending state moved to AES-GCM encrypted IndexedDB.
- Minimum 108-card balanced offline reserve (12 cards per pillar).
- Static `offline-learning.php` PWA shell reconstructs the latest journey/reserve from the encrypted vault after network loss/reopen.
- Offline quiz feedback includes correct answer/explanation from the encrypted local deck.
- Offline reserve responses receive UUID `event_id`s and sync using a signed learner+device token.
- Reserve activity is excluded from room/Daily Ladder scoring.
- Server temporary room/history/user-cache/outbox data uses `UDENC1.` AES-256-GCM envelope when OpenSSL supports it.
- Legacy plaintext v0.5.1 records remain readable and migrate on write.
- Temporary user-cache TTL extended to 7 days.
- Future DB schema now includes `learner_devices` and `offline_learning_events`.

## New protected file

```text
data/storage-encryption.key
```

This file MUST be backed up and preserved before every future release. A v0.5.1 rollback cannot read state already rewritten to `UDENC1.` without retaining the v0.5.2 decrypt compatibility layer or restoring pre-upgrade data.

## Live verification required

Do not label v0.5.2 SERVER VERIFIED until the live Cloudways installation confirms:

1. `/health.php` version `0.5.2`;
2. `server_temp_encryption = AES-256-GCM`;
3. 108-card reserve is cached;
4. offline refresh/reopen resumes encrypted journey;
5. offline answer feedback and reconnect sync work;
6. no new plaintext Q&A localStorage cache is created;
7. no-repeat history remains correct after reserve sync;
8. importer, QR, themes, reflection rail and presenter ladder regressions are clear.

## Future work after v0.5.2 verification

- MariaDB dual-write/backfill importer from encrypted outbox.
- Class/grade/language-aware offline reserve selection.
- Storage quota diagnostics and adaptive 108+ reserve sizing.
- User-visible offline vault status/settings and explicit clear-device-learning-data control.
- Real WhatsApp OTP + consent/retention workflows.
- Stronger content lifecycle/expiry and reviewer workflow.

---

# End of Master Handover

When this document is superseded, keep the old copy and create a new timestamped/versioned master. Do not silently rewrite the only historical handover file.


---

# 36. v0.6.0 — Distributed Daily Learning Foundation

## 36.1 Architectural principle

> **Centralize trust, decentralize distribution.**

This principle is now part of Udaan's canonical architecture.

### Centralized trust

Udaan retains authority over:

- trusted sources;
- content verification;
- human approval;
- canonical content IDs/versions;
- Ed25519 content signing;
- manifest authority;
- revocation/version decisions;
- future moderation/RBAC.

### Decentralized distribution

Approved signed content may eventually be delivered through:

- origin server;
- encrypted PWA cache;
- another verified device over future WebRTC;
- school edge node / LAN cache;
- future CDN/object storage.

A distributor never gains authority to alter content. Devices verify the signed pack before accepting it.

## 36.2 No database requirement

v0.6.0 still runs without MariaDB.

```text
Canonical content         data/content/cards.json
Signed pack manifest      data/content/manifest.json
Signed pack files         data/content/packs/*.json
Trust private key         data/content-signing.key
Temporary rooms           Redis or encrypted JSON
Learner history           Redis or encrypted JSON
User/session cache        Redis or encrypted JSON
Device learning bank      AES-GCM encrypted IndexedDB
Future DB events          encrypted JSONL sync outbox
```

MariaDB is still required before full production for permanent users, reviewer workflows, durable results, audit, analytics, multi-device reconciliation, school/parent/teacher relationships and large-scale operations. P2P/signed distribution will remain after DB introduction.

## 36.3 Signed content-pack system

v0.6.0 adds:

- default **112 cards per pack**;
- pillar-based pack grouping;
- SHA-256 pack integrity;
- Ed25519 detached signatures;
- signed master manifest;
- browser verification before local storage;
- content-import-triggered signed-pack rebuild;
- import rollback if pack/signature publication fails.

Pretty endpoints:

```text
/content/manifest
/content/pack/{pack-id}
```

`data/` remains denied from direct web access. Pack files are served through controlled endpoints.

### Protected signing key

New protected file:

```text
data/content-signing.key
```

This key MUST be backed up on first creation and preserved on every future release. Never ship it in a hotfix ZIP and never replace it with a key from another installation.

Device trust is intentionally pinned to the Ed25519 public key. An unexpected key change is treated as a trust failure rather than silently accepted.

## 36.4 Offline device bank — up to 1,008 cards

The device content capacity changes from the original 108-card v0.5.2 reserve concept to:

```text
112 cards × 9 pillars = 1,008 cards/device maximum
```

This does not mean Udaan invents or repeats cards to reach 1,008.

Current seed bank remains 432 cards, therefore a device can cache the available signed approved bank. As the content library grows, the signed local bank caps at 1,008 balanced cards unless a future release changes this budget.

The personalized offline response reserve also supports up to 1,008 unseen cards where enough suitable Q&A/info content exists.

Downloading/caching content alone does not mark a learner as having learned it.

## 36.5 Session Q&A-first composition

User-approved hard rule:

> **Maximum one `action` / “tiny action” card per learning session.**

The session should focus primarily on Q&A and information sharing.

Default 27-card composition:

```text
Quiz                  13
Reveal / information   9
Choice                  4
Action                  1
                       ──
                       27
```

Supported targets:

```text
21 → 10 quiz / 7 reveal / 3 choice / 1 action
24 → 11 quiz / 8 reveal / 4 choice / 1 action
27 → 13 quiz / 9 reveal / 4 choice / 1 action
30 → 14 quiz /10 reveal / 5 choice / 1 action
36 → 18 quiz /12 reveal / 5 choice / 1 action
```

Offline response reserves are arranged into default 27-card micro-session chunks with at most one action card per chunk. When enough non-action content exists, action cards are not added simply to fill the 1,008-card storage capacity.

## 36.6 Variable choices and anti-position-bias

The old assumption of three options is removed.

Current validator/UI contract:

```text
quiz        3–5 options
choice      3–8 options
action      2–4 options
reveal      no choice array required
```

Future/career choice cards may therefore present 4, 5, 6, 7 or 8 meaningful options.

The 12 current seed Future choice cards now use five graded-interest options rather than only yes/maybe/later.

### Option shuffling

For every participant:

1. option IDs remain canonical;
2. visible option order is cryptographically randomized server-side;
3. the order is stored in that participant's journey reference;
4. refresh/rejoin restores the same order;
5. encrypted offline resume restores the same order;
6. the correct answer can no longer be predicted from the first position.

Local audit over 200 generated default journeys distributed the current three-option quiz correct answers across all positions.

## 36.7 No two learners share the same Q&A order inside a room

Room-level uniqueness is now a hard allocation rule.

```text
Generate balanced journey
        ↓
Randomize ordered card GUIDs
        ↓
SHA-256 sequence fingerprint
        ↓
Compare with room fingerprints
        ↓
Collision?
   YES → regenerate
   NO  → atomic room reservation
```

The final collision check occurs inside the locked room mutation, so two simultaneous OTP verifications cannot intentionally reserve the same order due to a race.

Only the SHA-256 fingerprint is stored for uniqueness checking. It does not reveal answers or personal information.

The same learner returning to the same room restores their existing frozen order. The uniqueness rule applies between different room participants.

## 36.8 v0.6.0 local audit evidence

Passed locally:

- 200 generated 27-card participant journeys → 200 unique sequence fingerprints;
- action max observed = 1;
- default mix = 13 quiz / 9 reveal / 4 choice / 1 action;
- all journey lengths 21/24/27/30/36 respected action max = 1;
- 8-option choice validator PASS;
- 5-option quiz validator PASS;
- 12 Future seed choice cards contain five options;
- current bank remains 432 cards / 9 pillars;
- synthetic 1,728-card bank → exactly 1,008-card offline reserve;
- 1,008 scale reserve → exactly 112 cards × 9 pillars;
- offline 27-card chunks with >1 action = 0;
- Ed25519 manifest signature verification PASS;
- Ed25519 sample pack signature verification PASS;
- PHP/JS/JSON syntax audit required again immediately before packaging.

## 36.9 P2P scope boundary

v0.6.0 is **P2P foundation**, not direct browser-to-browser transport.

Future v0.6.x may add:

```text
short-lived peer identity
      ↓
signaling
      ↓
WebRTC data channel
      ↓
manifest comparison
      ↓
missing signed pack transfer
      ↓
SHA-256 + Ed25519 verify
      ↓
encrypted IndexedDB
      ↓
origin fallback if no peer
```

Never expose persistent Device Install GUID, learner hash, WhatsApp number, answers, commitments, progress or interests to peers.

## 36.10 v0.6.0 protected-file list

Always preserve:

```text
data/app-secret.key
data/storage-encryption.key
data/content-admin.key
data/content-signing.key
data/content/cards.json
data/history/
data/user-cache/
data/sync-outbox/
```

Also preserve import logs/backups as operational history where possible.

## 36.11 v0.6.0 recovery procedure

If all conversation/build context is lost:

1. open live `/health.php` and record version/status;
2. SFTP/SSH copy the entire live `/ver/udan/` folder;
3. separately back up all protected keys/data listed above;
4. read `/docs/MASTER_BLUEPRINT_HANDOVER_AUDIT.md` from the live build;
5. inspect `/data/content/cards.json` count/version but never overwrite it from an old ZIP;
6. inspect `data/content-signing.key` existence;
7. open `/content/manifest` and record key ID/manifest hash;
8. diff the pulled live code against the last candidate release;
9. run `docs/RELEASE_AUDIT_v0.6.0.md`;
10. only then continue development.

## 36.12 v0.6.0 server-verification promotion

Current status in this handover:

> **v0.6.0 — BUILT + LOCALLY AUDITED**

Do not promote to SERVER VERIFIED until live Cloudways confirms:

- `/health.php` version 0.6.0;
- PHP Sodium / signed distribution ready;
- `data/content-signing.key` created and backed up;
- manifest/pack endpoints work;
- two real participants receive different Q&A orders;
- option order varies between participants but remains frozen for each;
- max one action card/session;
- 5-option Future cards render correctly;
- PWA/offline/resume/sync regressions pass;
- content import rebuilds signed packs successfully.

After those checks, update this table/document to:

> **v0.6.0 — Distributed Daily Learning Foundation — SERVER VERIFIED**

# 37. v0.6.0 Finalization Addendum — Distributed Daily Learning Foundation

The final v0.6.0 release extends the signed/distributed infrastructure into the student growth loop. The release name should now be treated as:

> **v0.6.0 — Distributed Daily Learning Foundation**

The architecture principle remains:

> **Centralize trust, decentralize distribution.**

The product principle is:

> **Make 2–5 minutes in Udaan so useful, interesting and satisfying that a learner voluntarily comes back tomorrow.**

## 37.1 Daily 9

New route:

```text
/daily
```

Daily 9 is the recognizable everyday ritual:

```text
1 Think & Reason
1 Science & Discovery
1 Roots & India
1 Maths & Logic
1 Build & Create
1 Future & Careers
1 Values & Character
1 India & World
1 Life Skills & Responsibility
────────────────────────────
9 cards
```

Current generated 9-card target mix:

```text
Quiz      3
Reveal    3
Choice    2
Action    1 maximum
```

Daily 9 uses the same GUID/no-repeat history, option shuffling, encrypted browser vault, signed device bank and offline sync architecture as live rooms.

For the no-DB prototype, Daily 9 may use the Udaan Device Install GUID as the local continuity anchor. The raw GUID remains browser-local; the server receives/stores only its HMAC-derived pseudonymous identity.

## 37.2 Offline Daily 9

The encrypted 1,008-card reserve is no longer presented as one enormous endless deck.

When there is no active unfinished room journey, `/offline-learning.php` creates a local **offline Daily 9** from the encrypted reserve, selecting one available card per pillar where supply permits. After completion, another offline Daily 9 may be started from remaining cached cards.

This preserves the daily ritual even when the origin is unavailable.

Offline results continue to use GUID `event_id` records for later idempotent sync / future MariaDB import.

## 37.3 Friend Challenge

New route:

```text
/challenge/{room-uuid}
```

A completed Daily 9 can be shared as a challenge.

Challenge contract:

```text
same 9 concepts
+
different ordered GUID sequence
+
independently shuffled options
+
no speed bonus
```

The shared URL contains no sender phone number, learner hash, device GUID, detailed answer history or school data.

The challenge participant is added to the same temporary concept room so the room-level sequence-fingerprint collision protection can guarantee a different ordered sequence from existing participants.

## 37.4 Safe share card

Daily 9 completion renders a privacy-safe share surface containing only:

- nickname;
- Daily 9 completion;
- 9/9 ideas explored;
- learning theme line;
- challenge URL / local QR.

It deliberately excludes:

- raw or masked WhatsApp number;
- Device Install GUID;
- learner HMAC;
- exact answers;
- detailed performance history;
- school/location information.

## 37.5 Anonymous response signals

`api/answer.php` now records first responses into encrypted protected aggregate counters.

Public feedback can return only aggregate information such as:

```text
62% of 48 learners chose this.
```

Aggregate records are deduplicated by a one-way responder token scoped to the aggregate key. Re-answering the same card by the same pseudonymous learner does not inflate the public total.

No individual response identity is returned by the aggregate API.

## 37.6 Question of India prototype

New route:

```text
/question-of-india
```

A deterministic approved quiz/choice card is selected for the current day from the trusted card bank.

A device can answer once pseudonymously and then see anonymous response percentages.

This is a prototype network-effect layer. It does not yet claim national-scale participation or permanent analytics.

Future production aggregation belongs in MariaDB/analytics infrastructure.

## 37.7 Mystery Card

New route:

```text
/mystery/{room-uuid}
```

Daily 9 completion may unlock one non-scoring reveal card outside the completed concept set where alternate reveal content is available.

Purpose:

- novelty;
- curiosity;
- completion reward;
- no extra leaderboard advantage.

## 37.8 Lightweight badges

Current session/local badges include:

```text
Daily 9 Finisher
9-Pillar Explorer
Curiosity Champion
Fresh Explorer
Journey Finisher
```

These are prototype/session recognitions. Permanent badge/streak history is reserved for the production data platform.

## 37.9 Student growth routes added in final v0.6.0

```text
/daily
/question-of-india
/challenge/{uuid}
/mystery/{uuid}
```

Existing live room routes remain unchanged.

## 37.10 Final v0.6.0 local audit additions

In addition to the earlier distributed-content tests, the finalization audit confirmed:

- Daily 9 creates exactly 9 cards;
- all 9 pillars represented exactly once in the current 432-card bank;
- Daily 9 mix observed: 3 quiz / 3 reveal / 2 choice / 1 action;
- friend challenge reuses the same 9 GUIDs in a different order;
- source Daily 9 and first friend challenge had different SHA-256 journey fingerprints;
- Question of India page renders supported options;
- repeated Question of India response from the same device pseudonym did not increase aggregate count;
- Mystery Card route renders a non-scoring reveal card;
- full PHP syntax audit PASS;
- all application JS syntax checks PASS;
- manifest JSON PASS;
- 432 unique card GUIDs PASS;
- 48 cards × 9 pillars PASS;
- Ed25519 manifest verification PASS;
- Ed25519 pack verification PASS;
- synthetic 1,728-card bank → exactly 1,008 reserve cards PASS;
- 1,008 reserve = 112 × 9 pillars PASS;
- offline 27-card reserve chunks with >1 action = 0.

## 37.11 North-star metric

Future durable analytics should use:

> **Weekly Meaningful Learners (WML)**

Definition:

> learner completes at least 3 Udaan learning sessions in a week.

Do not optimize for raw minutes/screens viewed.

## 37.12 Future development map

The authoritative roadmap is now also stored at:

```text
/docs/FUTURE_DEVELOPMENT_PLAN_v0.6.0.md
```

Planned sequence:

```text
v0.6.1  Opportunity & Future Radar
v0.6.2  Explain & Learn Deeper
v0.6.3  Student Contribution Network
v0.6.4  Teacher Classroom Mode
v0.6.5  School Participation Network
v0.6.6  P2P Signed Pack Transfer
v0.6.7  School Edge Node
v0.7.0  MariaDB + durable production platform
v0.7.x  Trusted contributor/reviewer reputation
v0.8.x  National Learning Network
```

## 37.13 Database decision remains unchanged

v0.6.0 can operate without MariaDB.

MariaDB becomes required before broad production rollout for durable identity, multi-device merge, permanent results/streaks/badges, schools/teachers, moderation/RBAC, opportunity saves/reminders, consent, audit and analytics.

The signed/distributed content layer remains useful after the database is introduced.

## 37.14 Documentation-as-release guardrail

For every Udaan release after v0.6.0, development is incomplete until all applicable documentation is updated:

- `README.md`;
- `CHANGELOG.md`;
- `docs/MASTER_BLUEPRINT_HANDOVER_AUDIT.md`;
- release audit;
- subsystem blueprint(s);
- future-work status;
- protected-file list;
- health expectations;
- rollback/recovery instructions;
- version-history status.

This rule is mandatory so development can resume safely from a pulled live server even if conversation context is unavailable.
