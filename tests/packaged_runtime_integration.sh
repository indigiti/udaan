#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "\${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
SERVER_PID=""
cleanup() {
  if [[ -n "$SERVER_PID" ]]; then kill "$SERVER_PID" 2>/dev/null || true; wait "$SERVER_PID" 2>/dev/null || true; fi
  rm -rf "$TMP"
}
trap cleanup EXIT

SOURCE_SHA="$(git -C "$ROOT" rev-parse HEAD)"
OUTPUT_DIR="$TMP/package" SOURCE_SHA="$SOURCE_SHA" bash "$ROOT/ops/package-release.sh" >/dev/null

RUNTIME="$TMP/runtime"
mkdir -p "$RUNTIME/public_html" "$RUNTIME/private_html"
cp -a "$TMP/package/udaan/public" "$RUNTIME/public_html/udaan"
cp -a "$TMP/package/udaan/private" "$RUNTIME/private_html/udaan"

test -f "$RUNTIME/public_html/udaan/index.php"
test -f "$RUNTIME/public_html/udaan/api/state.php"
test -f "$RUNTIME/private_html/udaan/bootstrap.php"
test ! -e "$RUNTIME/public_html/udaan/lib"
test ! -e "$RUNTIME/public_html/udaan/data"
test ! -e "$RUNTIME/public_html/udaan/config.php"

PORT="$(python3 - <<'PY'
import socket
s=socket.socket()
s.bind(("127.0.0.1",0))
print(s.getsockname()[1])
s.close()
PY
)"
BASE="http://127.0.0.1:$PORT"

UDAAN_ENV=production \
REDIS_ENABLED=0 \
UDAAN_TRUST_PROXY_HEADERS=0 \
UDAAN_LEGACY_PLAINTEXT_STORAGE=read \
UDAAN_PUBLIC_URL="$BASE/udaan" \
php -S "127.0.0.1:$PORT" -t "$RUNTIME/public_html" >"$TMP/php-server.log" 2>&1 &
SERVER_PID="$!"

for _ in $(seq 1 50); do
  if curl -fsS "$BASE/udaan/health.php" >/dev/null 2>&1; then break; fi
  sleep 0.1
done
kill -0 "$SERVER_PID"

curl -fsS -D "$TMP/index.headers" -o "$TMP/index.html" "$BASE/udaan/index.php"
grep -q "Udaan" "$TMP/index.html"
grep -q '/udaan/assets/' "$TMP/index.html"
grep -Eiq '^Set-Cookie: UDAANLIVESESSID=.*Path=/udaan/' "$TMP/index.headers"

curl -sS -D "$TMP/api.headers" -o "$TMP/api.json" "$BASE/udaan/api/state.php?room=11111111-1111-4111-8111-111111111111"
grep -q '"error":"Room expired"' "$TMP/api.json"
grep -Eiq '^Set-Cookie: UDAANLIVESESSID=.*Path=/udaan/' "$TMP/api.headers"

curl -fsS "$BASE/udaan/health.php" >"$TMP/health.json"
HEALTH_JSON="$(cat "$TMP/health.json")" php -r '
$d=json_decode((string)getenv("HEALTH_JSON"),true);
if(!is_array($d)||array_keys($d)!==["ok","app","version","time"]||empty($d["ok"]))exit(1);
'

curl -sS "$BASE/udaan/ready.php" >"$TMP/ready.json"
READY_JSON="$(cat "$TMP/ready.json")" php -r '
$d=json_decode((string)getenv("READY_JSON"),true);
if(!is_array($d))exit(1);
$keys=array_keys($d);sort($keys);$expected=["app","ready","status","time","version"];sort($expected);
if($keys!==$expected||isset($d["checks"])||isset($d["warnings"]))exit(1);
'

curl -fsS "$BASE/udaan/manifest.webmanifest" >/dev/null
curl -fsS "$BASE/udaan/sw.js" >/dev/null

http_code="$(curl -sS -o "$TMP/private-leak.txt" -w '%{http_code}' "$BASE/udaan/lib/helpers.php")"
[[ "$http_code" == "404" ]]

mv "$RUNTIME/private_html/udaan" "$RUNTIME/private_html/udaan.offline"
http_code="$(curl -sS -o "$TMP/private-missing.txt" -w '%{http_code}' "$BASE/udaan/health.php")"
[[ "$http_code" == "503" ]]
grep -q 'Udaan private runtime is unavailable' "$TMP/private-missing.txt"
mv "$RUNTIME/private_html/udaan.offline" "$RUNTIME/private_html/udaan"

echo "packaged-runtime-integration: PASS"
