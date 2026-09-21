# Udaan Live v0.6.0 — Release Audit

Status before live deployment: **BUILT + LOCALLY AUDITED**.

Do not mark SERVER VERIFIED until all live checks pass.

## 1. Protected-data backup

Before upload back up:

- `data/app-secret.key`
- `data/storage-encryption.key`
- `data/content-admin.key`
- `data/content-signing.key` if already present
- `data/content/cards.json`
- `data/history/`
- `data/user-cache/`
- `data/sync-outbox/`

## 2. Health

`/health.php` must show:

- version `0.6.0`
- `offline_device_bank_capacity = 1008`
- `architecture_principle = Centralize trust, decentralize distribution.`
- `content_distribution.ready = true`
- signing `Ed25519`
- server temp encryption available
- `room_unique_qna_order = true`
- `max_action_cards_per_session = 1`

## 3. Content signing

- Open `/content/manifest`.
- Confirm manifest is returned.
- Confirm pack count > 0.
- Open one `/content/pack/{id}` endpoint.
- Confirm SHA-256/signature metadata exists.
- Confirm `data/content-signing.key` was created and back it up immediately.

## 4. Two-user uniqueness

Create one room and join with two different participants/devices.

Verify:

- Q&A card order differs.
- Option order differs on at least some quiz/choice cards.
- Refresh each device and confirm its own order remains unchanged.
- Rejoin same room with same verified learner and confirm its sequence is restored.

## 5. Session content mix

For each supported journey length, verify maximum action cards = 1.

Default 27-card room should be approximately/exactly:

- 13 quiz
- 9 reveal
- 4 choice
- 1 action

## 6. Future choices

On an upgraded live bank, open Content Bank. If the v0.6 migration panel appears, run it once and confirm a backup is created and the bank count is unchanged.

Confirm current seed Future choice cards display 5 options and the UI remains readable on mobile.

Test an imported 8-option `choice` card and confirm validation/import/display work.

## 7. Offline

- Open journey online.
- Confirm signed bank synchronization begins.
- Current 432-card seed should be locally eligible in the signed device bank.
- With a bank >1,008, device capacity must cap at 1,008.
- Disable network and continue learning.
- Reopen PWA and resume.
- Restore network and confirm answer sync.

## 8. Import regression

- Paste valid JSON.
- Verify.
- Import.
- Confirm backup created.
- Confirm signed manifest/pack rebuild succeeds.
- Confirm bank count rises.
- Re-import duplicate and confirm blocked.

## 9. Presenter / QR regression

- Browser-local QR renders.
- presenter polling updates.
- ladder remains completion/participation based.
- reset/demo crowd require host session + POST + CSRF.

## 10. PWA regression

- service worker `udaan-v0.6.0-static` activates.
- install prompt works where supported.
- dark/light mode works offline.
- encrypted IndexedDB remains readable after app reopen.

## Local audit evidence

- 200/200 generated default journeys had unique fingerprints.
- action max = 1 for 21/24/27/30/36.
- default kind mix = 13/9/4/1.
- correct-answer positions distributed across positions 1/2/3 in current seed quizzes.
- 8-option choice verifier PASS.
- 5-option quiz verifier PASS.
- synthetic 1,728-card bank → 1,008 offline cards PASS.
- synthetic 1,008 result = 112 × 9 pillars PASS.
- 27-card offline chunks with >1 action = 0.
- manifest Ed25519 verify PASS.
- pack Ed25519 verify PASS.

## Promotion rule

Only after all live checks pass declare:

> **v0.6.0 — Distributed Daily Learning Foundation — SERVER VERIFIED**

## 11. Daily 9 growth-loop regression

Open `/daily` and verify:

- exactly 9 cards are allocated;
- every one of the 9 pillars appears once where bank supply permits;
- maximum action cards = 1;
- card and option order are frozen on refresh/PWA resume;
- completion reaches the normal 100-point ceiling;
- completion displays badges and safe challenge/share surface.

## 12. Friend challenge

From Daily 9 completion:

- open the generated `/challenge/{uuid}` link on a second browser/device;
- confirm the same nine card GUIDs are used;
- confirm the ordered GUID fingerprint differs;
- confirm option order differs on at least some cards;
- confirm no sender phone/device/learning identity is present in the challenge URL or page.

## 13. Anonymous response aggregates

- Answer a quiz/choice card with two distinct test identities.
- Confirm feedback can display aggregate percentages.
- Confirm aggregate storage is encrypted/protected and only counts/percentages are returned publicly.
- Confirm re-answering the same card by the same pseudonymous learner does not inflate the aggregate.

## 14. Question of India

Open `/question-of-india`:

- approved quiz/choice card renders;
- option selection saves once per device/day pseudonymously;
- aggregate percentages render after answer;
- no personal identifiers appear in result UI.

## 15. Mystery Card

After Daily 9 completion:

- Mystery Card opens;
- card is not part of the completed Daily 9 concept set where alternate reveal content exists;
- it carries no points;
- reveal interaction works in light/dark mode.

## 16. Offline Daily 9

After signed bank/reserve preparation:

- disconnect the device;
- finish any active room journey;
- confirm encrypted reserve is surfaced in a 9-card offline Daily 9 chunk;
- confirm one pillar/card per pillar where available;
- complete the chunk and start another offline Daily 9 from remaining cards;
- reconnect and confirm queued responses sync.

## Final v0.6.0 local HTTP evidence

A PHP HTTP test harness confirmed:

- Daily 9 POST created a UUID room with mode `daily9`;
- `journey_length = 9`;
- challenge concept set = 9 GUIDs;
- exactly one card from each of 9 pillars;
- observed Daily 9 kind mix = 3 quiz / 3 reveal / 2 choice / 1 action;
- friend challenge joined the same concept room;
- source + friend participant count = 2;
- source + friend ordered sequence fingerprints = 2 unique;
- Question of India endpoint accepted a device-pseudonymous response;
- second submission from the same device/day did not increase aggregate total;
- Mystery Card page rendered successfully.

The built-in PHP server does not execute Apache rewrite rules; pretty-route verification must therefore be repeated on Cloudways/Apache/Nginx before `SERVER VERIFIED` promotion.
