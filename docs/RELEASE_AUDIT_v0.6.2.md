# Udaan v0.6.2 — Release Audit

Status target: **CI CERTIFIED** before merge; **SERVER VERIFIED** only after deployment checks.

## Repository / CI

- no tracked runtime secrets, learner/session state, generated packs, runtime cache, rate-limit state or Python bytecode;
- PHP syntax passes;
- JavaScript syntax passes;
- runtime smoke test passes for compiled content cache and throttle fallback;
- public signed-content HTTP endpoints remain read-only;
- presenter-state guard is present;
- browser security headers are present.

## Performance

- `data/content/runtime-cache.php` exists after a controlled content rebuild;
- `/health.php` reports `runtime_content_cache.ready=true`;
- presenter polling does not overlap requests;
- presenter polling pauses on hidden tabs;
- presenter state uses cached future labels instead of scanning the full bank;
- answer requests use the compiled bank path when cache is ready.

## Security / abuse

- room creation is CSRF protected and throttled;
- join POST is CSRF protected and throttled per device/session with a high room/network ceiling;
- answer API has bounded JSON and participant throttling;
- offline sync has bounded JSON and device throttling;
- Question of India is throttled per device with a high network flood ceiling;
- Content Bank login is CSRF protected and throttled;
- presenter state returns 403 without host session/capability;
- `.htaccess` emits CSP, HSTS and cross-origin hardening headers.

## Deployment verification

After deploying v0.6.2:

1. run the controlled signed-content rebuild if the runtime cache is not already ready;
2. confirm `/health.php` shows version `0.6.2` and runtime cache ready;
3. create a room and confirm the host presenter updates normally;
4. open the room-state URL without host session/token and confirm HTTP 403;
5. join from multiple devices behind the same network and confirm normal classroom joins are not falsely blocked;
6. answer several cards and verify latency remains stable;
7. verify offline sync;
8. verify Question of India;
9. verify Content Bank login and import;
10. inspect response headers for CSP/HSTS/CORP;
11. run `php cleanup.php` and confirm only expired runtime files are removed.

Do not mark **SERVER VERIFIED** until these checks pass on the deployed environment.
