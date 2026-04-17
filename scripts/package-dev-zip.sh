#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PROJECT_NAME="Tag-Marketplace"
OUTPUT_NAME="${1:-Tag-Marketplace-dev.zip}"
TMP_DIR="$(mktemp -d)"
mkdir -p "$TMP_DIR/$PROJECT_NAME"
rsync -a --exclude='.git' --exclude='node_modules' --exclude='storage/logs/*.log' "$ROOT_DIR/" "$TMP_DIR/$PROJECT_NAME/"
(cd "$TMP_DIR" && zip -qr "$ROOT_DIR/$OUTPUT_NAME" "$PROJECT_NAME")
rm -rf "$TMP_DIR"
echo "Created: $ROOT_DIR/$OUTPUT_NAME"
