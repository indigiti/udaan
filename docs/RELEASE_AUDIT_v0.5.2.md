# Udaan Live v0.5.2 — Release Audit

## Build status

**BUILT + LOCAL STATIC/RUNTIME AUDIT. SERVER VERIFICATION REQUIRED.**

## Mandatory live checks

- `/health.php` reports v0.5.2.
- `server_temp_encryption` reports `AES-256-GCM`.
- New room JSON/Redis payload is encrypted.
- `data/storage-encryption.key` exists, is protected, and is backed up securely.
- Join creates/reuses a random device-install UUID in the browser.
- Server participant/user cache contains device hash, not raw device UUID.
- Verify/journey works normally.
- Browser encrypted IndexedDB vault is created.
- At least 108 reserve cards are cached after online journey start.
- Current journey works after disabling network.
- Offline shell restores after refresh/PWA reopen.
- Offline quiz displays local correct/explanation feedback.
- Offline reserve responses survive close/reopen.
- Reconnect sync clears acknowledged pending events.
- No raw WhatsApp number is present in local browser learning state.
- New Q&A/answer payloads are not stored as plaintext localStorage records.
- Daily Ladder remains unaffected by reserve-only learning.
- No-repeat behavior remains intact after synced reserve responses.
- Content importer still verifies/imports without data schema regression.
- Light/dark mode and Learning Reflection Rail still work.

## Security regression checks

- Dedicated `UDAANLIVESESSID` remains.
- UUID pretty room URLs remain.
- Host mutations remain POST + CSRF.
- `/data/` remains web-denied.
- `data/app-secret.key` preserved.
- `data/storage-encryption.key` preserved.
- No hardware/browser fingerprinting introduced.

## Rollback warning

Once server temporary/history/user-cache files have been rewritten to `UDENC1.` format, pure v0.5.1 code cannot read them. Take a full protected-data backup before deployment. If rollback is required, retain the v0.5.2 `secure_unpack()` compatibility layer or restore a pre-upgrade data backup.
