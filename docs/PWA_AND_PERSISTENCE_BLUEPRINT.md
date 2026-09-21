# Udaan PWA, Offline & Persistence Blueprint — v0.6.0 → MariaDB

## Current no-DB model

Udaan v0.6.0 deliberately remains operational without MariaDB.

### Browser

- PWA service worker
- Device Install UUID v4
- non-exportable Web Crypto AES-GCM key
- encrypted IndexedDB learning vault
- frozen current journey
- frozen per-participant option order
- encrypted answer/progress queue
- signed content-pack registry
- up to 1,008 verified content cards/device

### Server

- temporary room state: Redis or AES-256-GCM encrypted JSON
- learner no-repeat history: Redis or encrypted JSON
- short-lived user/device cache: Redis or encrypted JSON
- append-only encrypted JSONL sync outbox
- canonical approved `cards.json`
- Ed25519 signed content packs + master manifest

## Device content capacity

Maximum signed content bank:

`9 pillars × 112 = 1,008 cards/device`

The current 432-card seed cannot magically fill 1,008; only real approved cards are cached. As the bank grows, the device bank expands automatically until the configured cap.

## Personalized offline answering

A verified learner may receive up to 1,008 unseen reserve cards where enough suitable content exists.

Reserve ordering follows the Q&A-first policy and is arranged in default 27-card micro-session chunks with at most one action card/chunk.

## Local-first answer model

```text
Learner answers
      ↓
Write encrypted local state immediately
      ↓
Continue UI
      ↓
Network available?
  YES → sync now
  NO  → encrypted pending queue
      ↓
Reconnect
      ↓
idempotent event sync
```

## Future MariaDB migration

The existing event contract should map into durable tables without changing the learner UX.

Recommended durable entities:

- users / learner identities
- learner_devices
- learning_sessions
- learning_session_cards
- card_answers
- offline_learning_events
- learner_card_history
- achievements / streaks
- content_items / card_versions
- content_sources / approvals
- audit_events

Every event must retain a UUID `event_id`; MariaDB imports must use a unique constraint on `event_id` so replay is idempotent.

## Signed distribution after MariaDB

MariaDB does not replace the signed pack network.

```text
MariaDB / Control Center
      ↓
approved content publication
      ↓
Ed25519 signer
      ↓
signed manifest/packs
      ↓
origin / CDN / P2P / school edge
      ↓
PWA verify + encrypted local bank
```

## Privacy boundary for future P2P

Peers may exchange only public approved signed content packs/manifests.

Never peer-share:

- WhatsApp number
- learner hash
- persistent Device Install UUID
- answers
- progress
- commitments
- interests
- leaderboard identity
- offline sync token
