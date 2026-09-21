# v0.5.1 Learning Reflection Rail — Safe Overlay

Upload this overlay over the existing `/ver/udan/` folder.

This hotfix intentionally contains **no `data/` directory**, so it will not overwrite:

- live `data/content/cards.json` (including cards imported after the 432 seed)
- `data/history/`
- `data/sync-outbox/`
- `data/app-secret.key`
- `data/content-admin.key`
- active room/user-cache JSON

After upload:

1. Hard refresh / reopen installed PWA.
2. Check `/health.php` for `version: 0.5.1`, `learning_reflection_rail: true`, `card_reflection_seconds: 3`.
3. Create a new room and run the v0.5.1 server acceptance checklist in `docs/RELEASE_AUDIT_v0.5.1.md`.
