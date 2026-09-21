# Udaan Content Bank Contract — v0.6.0

`data/content/cards.json` remains the canonical approved content bank for the no-DB prototype.

## Unlimited-growth principle

The selector does not hard-code 432 cards. The current 432-card seed is only the starting bank. Imported cards can grow into thousands while the same selector, signed-pack builder and PWA continue operating.

## Allowed pillars

`think`, `science`, `roots`, `math`, `build`, `future`, `values`, `world`, `life`

## Supported interaction kinds

- `quiz`
- `reveal`
- `choice`
- `action`

### Option ranges

- Quiz: **3–5 options**
- Choice / future preference: **3–8 options**
- Action: **2–4 options**
- Reveal: no options required

The application randomizes option positions per participant and freezes the order for resume/offline continuity.

## Session composition rule

Udaan is Q&A/info-first. A live learning session contains at most **one `action` card**.

Default 27-card target:

- 13 quiz
- 9 reveal
- 4 choice
- 1 action

## Learner selection priority

`unseen card → unseen topic → pillar balance → target interaction mix → oldest review`

The same card GUID is not intentionally re-issued until unseen supply is exhausted or review becomes educationally useful.

## Room order uniqueness

Every participant journey has a SHA-256 ordered-sequence fingerprint. The same ordered card sequence cannot be issued to two different participants in one room. The room mutation performs a final collision check under lock.

## Signed distribution

After a successful content import:

1. `cards.json` is updated atomically.
2. Content packs are rebuilt.
3. Each pack is SHA-256 hashed and Ed25519 signed.
4. The master manifest is signed.
5. If signing/publishing fails, the bank import is rolled back.

Protected key: `data/content-signing.key`.

## 1,008-card device capacity

The signed device bank may store up to:

`112 cards × 9 pillars = 1,008 cards`

When the live bank contains fewer than 1,008 cards, Udaan stores only what exists. It never creates artificial duplicates to fill capacity.
