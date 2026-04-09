#!/usr/bin/env bash
# Run all frontend unit tests (plain Node.js, no framework needed).
#
# Usage (from repo root):
#   bash develop/symfony/assets/tests.sh
#
# Each *.test.mjs file under assets/tests/ (including subdirectories) is run
# with `node`.  A non-zero exit code from any file counts as a failure.
#
# A custom ESM loader (tests/loader.mjs) remaps browser bare-specifiers such
# as `vue` and `sweetalert2` to the local vendor files so tests can import
# any application module without a bundler.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TESTS_DIR="$SCRIPT_DIR/tests"
LOADER="$TESTS_DIR/loader.mjs"

PASS=0
FAIL=0

echo "Running frontend unit tests..."
echo ""

while IFS= read -r -d '' f; do
    # Display path relative to the tests directory
    name="${f#"$TESTS_DIR/"}"
    if node --no-warnings --experimental-loader "$LOADER" "$f"; then
        PASS=$((PASS + 1))
    else
        echo ""
        echo "✗ FAILED: $name"
        FAIL=$((FAIL + 1))
    fi
    echo ""
done < <(find "$TESTS_DIR" -name "*.test.mjs" -print0 | sort -z)

echo "─────────────────────────────"
echo "Results: $PASS passed, $FAIL failed"

[ "$FAIL" -eq 0 ]

