#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SOURCE_SHA="${SOURCE_SHA:-$(git rev-parse HEAD)}"
OUTPUT_DIR="${OUTPUT_DIR:-$ROOT/dist}"

git cat-file -e "$SOURCE_SHA^{commit}"
VERSION="$(php -r '$c=require "config.php"; echo (string)$c["version"];')"
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([._-][A-Za-z0-9.-]+)?$ ]]; then
  echo "Invalid application version: $VERSION" >&2
  exit 1
fi

SHORT_SHA="${SOURCE_SHA:0:12}"
STAGE="$OUTPUT_DIR/udaan"
ARCHIVE="$OUTPUT_DIR/udaan-v$VERSION-$SHORT_SHA.tar.gz"
ARCHIVE_SHA="$ARCHIVE.sha256"

rm -rf "$STAGE"
mkdir -p "$STAGE" "$OUTPUT_DIR"

git archive "$SOURCE_SHA" | tar -x -C "$STAGE"

# Development-only repository files are not part of the server deployment.
rm -rf "$STAGE/.github" "$STAGE/tests"
rm -f "$STAGE/.gitignore"

fail_if_files() {
  local label="$1"
  shift
  local hits
  hits="$("$@" || true)"
  if [[ -n "$hits" ]]; then
    echo "Forbidden files in release package: $label" >&2
    printf '%s\n' "$hits" >&2
    exit 1
  fi
}

fail_if_files "runtime keys" find "$STAGE/data" -maxdepth 1 -type f -name '*.key' -print
fail_if_files "runtime learner/session state" bash -c "find '$STAGE/data/rooms' '$STAGE/data/history' '$STAGE/data/user-cache' '$STAGE/data/sync-outbox' '$STAGE/data/aggregates' '$STAGE/data/rate-limit' '$STAGE/data/insights' '$STAGE/data/players' '$STAGE/data/events' '$STAGE/data/missions' '$STAGE/data/readiness' -type f ! -name '.gitkeep' -print 2>/dev/null"
fail_if_files "pending import snapshots" bash -c "find '$STAGE/data/imports/pending' -type f ! -name '.gitkeep' -print 2>/dev/null"
fail_if_files "generated content runtime files" bash -c "find '$STAGE/data/content' -type f \( -name 'manifest.json' -o -name 'runtime-cache.php' -o -name 'import-log.json' -o -name '.import.lock' -o -path '*/packs/*.json' -o -path '*/backups/cards-*.json' \) -print 2>/dev/null"
fail_if_files "environment files" bash -c "find '$STAGE' -type f \( -name '.env' -o -name '.env.*' \) ! -name '.env.example' -print"
fail_if_files "Python bytecode/cache" bash -c "find '$STAGE' -type f \( -name '*.pyc' -o -name '*.pyo' \) -print; find '$STAGE' -type d -name '__pycache__' -print"

for required in   "$STAGE/index.php"   "$STAGE/config.php"   "$STAGE/.htaccess"   "$STAGE/data/.htaccess"   "$STAGE/ops/.htaccess"   "$STAGE/data/content/cards.json"   "$STAGE/ready.php"   "$STAGE/health.php"   "$STAGE/ops/preflight.php"   "$STAGE/ops/rotate-runtime-security.php"   "$STAGE/lib/Player.php"   "$STAGE/lib/Mission.php"   "$STAGE/lib/DailyReadiness.php"   "$STAGE/lib/Fit.php"   "$STAGE/lib/Momentum.php"   "$STAGE/lib/Coach.php"   "$STAGE/data/readiness/.gitkeep"   "$STAGE/player.php"   "$STAGE/today.php"   "$STAGE/readiness.php"   "$STAGE/fit.php"   "$STAGE/momentum.php"   "$STAGE/coach.php"   "$STAGE/coach_action.php"   "$STAGE/mission_action.php"; do
  if [[ ! -f "$required" ]]; then
    echo "Required release file missing: ${required#$STAGE/}" >&2
    exit 1
  fi
done

SOURCE_SHA="$SOURCE_SHA" VERSION="$VERSION" php -r '
$manifest=[
  "schema_version"=>1,
  "app"=>"Udaan Live",
  "version"=>getenv("VERSION"),
  "source_sha"=>getenv("SOURCE_SHA"),
  "artifact_policy"=>"certified-source-no-runtime-secrets",
];
file_put_contents($argv[1],json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");
' "$STAGE/RELEASE.json"

(
  cd "$STAGE"
  find . -type f ! -name 'SHA256SUMS' -print0     | LC_ALL=C sort -z     | xargs -0 sha256sum > SHA256SUMS
)

SOURCE_EPOCH="$(git show -s --format=%ct "$SOURCE_SHA")"
rm -f "$ARCHIVE" "$ARCHIVE_SHA"
tar --sort=name --mtime="@$SOURCE_EPOCH" --owner=0 --group=0 --numeric-owner -C "$STAGE" -cf - .   | gzip -n > "$ARCHIVE"
sha256sum "$ARCHIVE" > "$ARCHIVE_SHA"

echo "release_version=$VERSION"
echo "release_source_sha=$SOURCE_SHA"
echo "release_archive=$ARCHIVE"
echo "release_checksum=$ARCHIVE_SHA"
