#!/usr/bin/env bash
# Run all frontend unit tests (plain Node.js, no framework needed).
#
# Usage (from repo root):
#   bash develop/symfony/assets/tests.sh
#
# Each *.test.mjs file under assets/tests/ is run with `node`.
# A non-zero exit code from any file counts as a failure.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TESTS_DIR="$SCRIPT_DIR/tests"

PASS=0
FAIL=0

echo "Running frontend unit tests..."
echo ""

shopt -s nullglob
for f in "$TESTS_DIR"/*.test.mjs; do
    name=$(basename "$f")
    if node "$f"; then
        PASS=$((PASS + 1))
    else
        echo ""
        echo "✗ FAILED: $name"
        FAIL=$((FAIL + 1))
    fi
    echo ""
done

echo "─────────────────────────────"
echo "Results: $PASS passed, $FAIL failed"

[ "$FAIL" -eq 0 ]

