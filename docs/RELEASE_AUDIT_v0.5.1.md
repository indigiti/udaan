# Udaan Live v0.5.1 — Release Audit

## Release status

- Built: YES
- Local PHP syntax audit: REQUIRED/PASS result recorded at packaging time
- Local JS syntax audit: REQUIRED/PASS result recorded at packaging time
- Content bank JSON validation: REQUIRED/PASS result recorded at packaging time
- Server deployed: NOT ASSUMED
- Server verified: NOT ASSUMED until `/health.php` + live flow pass

## Changed behavior

- New Learning Reflection Rail after fresh card interactions.
- Default interval 3 seconds.
- Student swipe/keyboard navigation is blocked during that interval.
- Feedback/explanation remains visible.
- General form submissions can show the same progress rail without delaying server work.

## Data impact

- Content schema: NONE
- Learner history schema: NONE
- Room JSON schema: NONE
- Sync-outbox event schema: NONE
- DB migration: NONE

## Protected state

Do not overwrite/delete:

- `data/app-secret.key`
- `data/content-admin.key`
- `data/content/cards.json`
- `data/history/`
- `data/sync-outbox/`

## Server acceptance checklist

- [ ] `/health.php` = v0.5.1
- [ ] PWA = true
- [ ] learning_reflection_rail = true
- [ ] card_reflection_seconds = 3
- [ ] QR creates/scans valid UUID room link
- [ ] nickname + WhatsApp + screen OTP works
- [ ] same user/room restores frozen Q&A sequence
- [ ] fresh quiz shows feedback + 3-second rail
- [ ] reveal shows answer + 3-second rail
- [ ] choice/action uses the same reflection interval
- [ ] swipe/keyboard cannot bypass active interval
- [ ] dark mode rail is legible
- [ ] light mode rail is legible on non-journey pages
- [ ] reduced-motion mode remains understandable
- [ ] offline answer queues locally and advances after reflection
- [ ] reconnect sync works
- [ ] completion still reaches max 100 points
- [ ] Daily Ladder still updates
- [ ] Content Bank Paste → Verify → Import works
- [ ] no-repeat history remains intact

## Rollback

Restore v0.5.0 code only. Keep protected state above. The rollback requires no data conversion.
