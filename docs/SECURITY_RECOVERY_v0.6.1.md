# Udaan v0.6.1 — P0 Security Recovery

The public repository previously tracked runtime secrets and runtime learner/session state.

## Compromised values

Rotate these on the live server:

- `data/app-secret.key`
- `data/storage-encryption.key`
- `data/content-admin.key`
- `data/content-signing.key`

Treat previously committed room/history/cache/outbox/aggregate records as exposed.

Removing them from the current tree does not remove them from Git history.

## Coordinated rotation

Use a maintenance window. Rotating these keys changes HMAC learner/device identities, invalidates offline-sync tokens, makes existing encrypted temporary state unreadable, and changes the Ed25519 trust root.

Preserve the approved live content bank `data/content/cards.json`, but discard compromised temporary runtime state and regenerated distribution artifacts before creating new keys.

## Git history purge

After this PR is merged, coordinate a `git filter-repo` history rewrite for the removed secret/runtime paths. This changes commit SHAs and requires collaborators to re-clone.

## Signing trust

The old signing private key was exposed. Existing browsers that pinned its public key must not continue trusting it. Until a versioned trust-epoch migration ships, test devices should clear Udaan site/PWA data after the replacement signing key is generated.

## Verification

Before production promotion verify:

- all four replacement keys are new and mode `0600`;
- `data/` is blocked from HTTP;
- encrypted room cleanup retains valid active rooms;
- new signed manifest/packs verify;
- Content Bank accepts only the replacement credential;
- room/join/answer/offline-sync flows work;
- repository safety CI passes.

## Repository history purge status

Automated clean-root rewrite initiated after P0 certification.

History purge execution armed after workflow syntax correction.

History purge trigger re-issued after simplifying the workflow parser path.

History purge trigger re-issued after removing the final tracked import lock.
