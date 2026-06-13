#!/usr/bin/env bash
set -euo pipefail

SCLANG="/Applications/SuperCollider.app/Contents/MacOS/sclang"
DIR="$(cd "$(dirname "$0")" && pwd)"

exec "$SCLANG" "$DIR/boot-headless.scd"
