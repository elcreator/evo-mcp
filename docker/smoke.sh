#!/usr/bin/env bash
# End-to-end check against the docker site: auth, impersonation, permission-aware tools.
#   docker/smoke.sh [base_url] [token]
set -euo pipefail

BASE="${1:-http://localhost:${EVO_PUBLIC_PORT:-8080}}"
TOKEN="${2:-$(docker compose -f "$(dirname "$0")/docker-compose.yml" exec -T evo cat //var/www/html/core/storage/emcp-token.txt)}"
URL="$BASE/mcp/content"
BODY="$(mktemp)"
trap 'rm -f "$BODY"' EXIT

rpc() { # rpc <token> <json>
    curl -sS -o "$BODY" -w '%{http_code}' -X POST "$URL" \
        -H 'Content-Type: application/json' -H 'Accept: application/json' \
        ${1:+-H "Authorization: Bearer $1"} -d "$2"
}
expect() { # expect <label> <status> <got-status> [grep-pattern]
    if [ "$2" != "$3" ]; then echo "FAIL $1: expected HTTP $2, got $3"; cat "$BODY"; echo; exit 1; fi
    if [ -n "${4:-}" ] && ! grep -q -- "$4" "$BODY"; then echo "FAIL $1: body lacks '$4'"; cat "$BODY"; echo; exit 1; fi
    echo "ok   $1"
}

INIT='{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"smoke","version":"1"}}}'
LIST='{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
call() { printf '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"%s","arguments":%s}}' "$1" "$2"; }

expect "GET without token -> 401"       401 "$(curl -sS -o "$BODY" -w '%{http_code}' "$URL")"
expect "GET with token -> 405"          405 "$(curl -sS -o "$BODY" -w '%{http_code}' -H "Authorization: Bearer $TOKEN" "$URL")"
expect "no token -> 401"                401 "$(rpc '' "$INIT")" unauthenticated
expect "bad token -> 401"               401 "$(rpc 'emcp_nope' "$INIT")" invalid_token
expect "initialize"                     200 "$(rpc "$TOKEN" "$INIT")" '"platform":"eMCP"'
expect "tools/list"                     200 "$(rpc "$TOKEN" "$LIST")" 'evo.elements.list'
expect "content.get id=1"               200 "$(rpc "$TOKEN" "$(call evo.content.get '{"id":1}')")" '"pagetitle"'
expect "elements.list chunks"           200 "$(rpc "$TOKEN" "$(call evo.elements.list '{"type":"chunk","limit":5}')")" '"items"'
expect "write: cache.clear"             200 "$(rpc "$TOKEN" "$(call evo.write.cache.clear '{}')")" '"cleared":true'
expect "write: content.update"          200 "$(rpc "$TOKEN" "$(call evo.write.content.update '{"id":1,"fields":{"description":"touched by MCP smoke test"}}')")" '"updated_fields"'
NAME="mcpSmoke$RANDOM"
expect "write: elements.save chunk"     200 "$(rpc "$TOKEN" "$(call evo.write.elements.save "{\"type\":\"chunk\",\"fields\":{\"name\":\"$NAME\",\"snippet\":\"<p>hello from MCP</p>\"}}")")" "$NAME"
expect "elements.get chunk by name"     200 "$(rpc "$TOKEN" "$(call evo.elements.get "{\"type\":\"chunk\",\"name\":\"$NAME\"}")")" 'hello from MCP'

echo
echo "All smoke checks passed against $URL"
