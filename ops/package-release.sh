#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SOURCE_SHA="${SOURCE_SHA:-$(git rev-parse HEAD)}"
OUTPUT_DIR="${OUTPUT_DIR:-$ROOT/dist}"
git cat-file -e "$SOURCE_SHA^{commit}"
VERSION="$(git show "$SOURCE_SHA:config.php" | php -r '$src=stream_get_contents(STDIN); $tmp=tempnam(sys_get_temp_dir(),"udaan-config-"); file_put_contents($tmp,$src); $c=require $tmp; @unlink($tmp); echo (string)$c["version"];')"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([._-][A-Za-z0-9.-]+)?$ ]] || { echo "Invalid application version: $VERSION" >&2; exit 1; }

SHORT_SHA="${SOURCE_SHA:0:12}"
STAGE="$OUTPUT_DIR/udaan"
SOURCE_STAGE="$OUTPUT_DIR/.udaan-source"
ARCHIVE="$OUTPUT_DIR/udaan-v$VERSION-$SHORT_SHA.tar.gz"
ARCHIVE_SHA="$ARCHIVE.sha256"

rm -rf "$STAGE" "$SOURCE_STAGE"
mkdir -p "$STAGE/public" "$STAGE/private" "$SOURCE_STAGE" "$OUTPUT_DIR"
git archive "$SOURCE_SHA" | tar -x -C "$SOURCE_STAGE"
rm -rf "$SOURCE_STAGE/.github" "$SOURCE_STAGE/tests"
rm -f "$SOURCE_STAGE/.gitignore"

# The certified artifact has two explicit deployment roots:
#   public/  -> public_html/udaan/
#   private/ -> private_html/udaan/
# Server implementation, configuration, runtime state and operational tooling stay private.
cp -a "$SOURCE_STAGE/." "$STAGE/private/"
for path in assets sw.js manifest.webmanifest offline.html .htaccess; do
  if [[ -e "$SOURCE_STAGE/$path" ]]; then
    cp -a "$SOURCE_STAGE/$path" "$STAGE/public/$path"
    rm -rf "$STAGE/private/$path"
  fi
done

# Public PHP files are deliberately tiny front controllers. The real scripts remain outside
# the document root. UDAAN_PRIVATE_ROOT can override the standard sibling Cloudways layout.
make_stub() {
  local rel="$1" depth="$2" out="$STAGE/public/$rel"
  mkdir -p "$(dirname "$out")"
  cat > "$out" <<PHP
<?php
declare(strict_types=1);
\$publicRoot=$depth===0?__DIR__:dirname(__DIR__,$depth);
\$privateRoot=getenv('UDAAN_PRIVATE_ROOT');
if(!is_string(\$privateRoot)||trim(\$privateRoot)===''){
    \$privateRoot=dirname(dirname(\$publicRoot)).'/private_html/udaan';
}
\$target=rtrim(\$privateRoot,'/').'/$rel';
if(!is_file(\$target)){http_response_code(503);exit('Udaan private runtime is unavailable.');}
require \$target;
PHP
}

while IFS= read -r -d '' file; do
  rel="${file#$SOURCE_STAGE/}"
  case "$rel" in
    bootstrap.php|config.php|cleanup.php) continue ;;
  esac
  make_stub "$rel" 0
done < <(find "$SOURCE_STAGE" -maxdepth 1 -type f -name '*.php' -print0)

if [[ -d "$SOURCE_STAGE/api" ]]; then
  while IFS= read -r -d '' file; do
    rel="${file#$SOURCE_STAGE/}"
    make_stub "$rel" 1
  done < <(find "$SOURCE_STAGE/api" -maxdepth 1 -type f -name '*.php' -print0)
fi
rm -rf "$STAGE/private/api" 2>/dev/null || true
cp -a "$SOURCE_STAGE/api" "$STAGE/private/api"

fail_if_files() {
  local label="$1"; shift
  local hits; hits="$("$@" || true)"
  if [[ -n "$hits" ]]; then
    echo "Forbidden files in release package: $label" >&2
    printf '%s\n' "$hits" >&2
    exit 1
  fi
}
PRIVATE="$STAGE/private"
fail_if_files "runtime keys" find "$PRIVATE/data" -maxdepth 1 -type f -name '*.key' -print
fail_if_files "runtime learner/session state" bash -c "find '$PRIVATE/data/rooms' '$PRIVATE/data/history' '$PRIVATE/data/user-cache' '$PRIVATE/data/sync-outbox' '$PRIVATE/data/aggregates' '$PRIVATE/data/rate-limit' '$PRIVATE/data/insights' '$PRIVATE/data/players' '$PRIVATE/data/events' '$PRIVATE/data/missions' '$PRIVATE/data/readiness' '$PRIVATE/data/social' '$PRIVATE/data/competition' -type f ! -name '.gitkeep' -print 2>/dev/null"
fail_if_files "pending import snapshots" bash -c "find '$PRIVATE/data/imports/pending' -type f ! -name '.gitkeep' -print 2>/dev/null"
fail_if_files "generated content runtime files" bash -c "find '$PRIVATE/data/content' -type f \( -name 'manifest.json' -o -name 'runtime-cache.php' -o -name 'import-log.json' -o -name '.import.lock' -o -path '*/packs/*.json' -o -path '*/backups/cards-*.json' \) -print 2>/dev/null"
fail_if_files "environment files" bash -c "find '$STAGE' -type f \( -name '.env' -o -name '.env.*' \) ! -name '.env.example' -print"
fail_if_files "Python bytecode/cache" bash -c "find '$STAGE' -type f \( -name '*.pyc' -o -name '*.pyo' \) -print; find '$STAGE' -type d -name '__pycache__' -print"

for required in \
  "$STAGE/public/index.php" "$STAGE/public/.htaccess" "$STAGE/public/assets" \
  "$PRIVATE/bootstrap.php" "$PRIVATE/config.php" "$PRIVATE/data/.htaccess" "$PRIVATE/ops/.htaccess" \
  "$PRIVATE/data/content/cards.json" "$PRIVATE/ready.php" "$PRIVATE/health.php" \
  "$PRIVATE/ops/preflight.php" "$PRIVATE/ops/rotate-runtime-security.php" \
  "$PRIVATE/lib/Player.php" "$PRIVATE/lib/Mission.php" "$PRIVATE/lib/Social.php" "$PRIVATE/lib/Competition.php"; do
  [[ -e "$required" ]] || { echo "Required release path missing: ${required#$STAGE/}" >&2; exit 1; }
done

# Security contract: implementation/config/runtime directories must never be web-published.
for forbidden in private lib data ops config.php bootstrap.php cleanup.php; do
  [[ ! -e "$STAGE/public/$forbidden" ]] || { echo "Private path leaked into public artifact: $forbidden" >&2; exit 1; }
done
grep -q "private_html/udaan" "$STAGE/public/index.php" || { echo "Public front controller does not target private runtime." >&2; exit 1; }

SOURCE_SHA="$SOURCE_SHA" VERSION="$VERSION" php -r '
$manifest=[
 "schema_version"=>2,
 "app"=>"Udaan Live",
 "version"=>getenv("VERSION"),
 "source_sha"=>getenv("SOURCE_SHA"),
 "artifact_policy"=>"certified-public-private-split-no-runtime-secrets",
 "deploy_roots"=>["public"=>"public_html/udaan","private"=>"private_html/udaan"],
];
file_put_contents($argv[1],json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");
' "$STAGE/RELEASE.json"

(
 cd "$STAGE"
 find . -type f ! -name 'SHA256SUMS' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > SHA256SUMS
)

SOURCE_EPOCH="$(git show -s --format=%ct "$SOURCE_SHA")"
rm -f "$ARCHIVE" "$ARCHIVE_SHA"
tar --sort=name --mtime="@$SOURCE_EPOCH" --owner=0 --group=0 --numeric-owner -C "$STAGE" -cf - . | gzip -n > "$ARCHIVE"
sha256sum "$ARCHIVE" > "$ARCHIVE_SHA"
rm -rf "$SOURCE_STAGE"

echo "release_version=$VERSION"
echo "release_source_sha=$SOURCE_SHA"
echo "release_archive=$ARCHIVE"
echo "release_checksum=$ARCHIVE_SHA"
echo "release_layout=public-private"
