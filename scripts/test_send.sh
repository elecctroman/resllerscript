#!/usr/bin/env bash

set -euo pipefail

if [ $# -lt 2 ]; then
  echo "Kullanım: $0 <telefon> <mesaj> [event]" >&2
  exit 1
fi

PHONE="$1"
MESSAGE="$2"
EVENT="${3:-manual}"

API_KEY="${APP_GATEWAY_KEY:-supersecretkey}"
API_URL="${API_URL:-http://localhost:8080/internal/whatsapp_send.php}"

curl -sS -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: $API_KEY" \
  -d "{\"to\":\"$PHONE\",\"message\":\"$MESSAGE\",\"event\":\"$EVENT\"}"
