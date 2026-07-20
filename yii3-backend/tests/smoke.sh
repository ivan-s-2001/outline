#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${APP_URL:-http://127.0.0.1:8080}"
RUNTIME_DIR="$(cd "$(dirname "$0")/../runtime" && pwd)"
COOKIE_JAR="$RUNTIME_DIR/smoke.cookies"
rm -f "$COOKIE_JAR"

request() {
  local endpoint="$1"
  local payload="$2"
  local output="$3"
  curl --fail-with-body --silent --show-error \
    --cookie "$COOKIE_JAR" \
    --cookie-jar "$COOKIE_JAR" \
    --header 'Content-Type: application/json' \
    --request POST \
    --data "$payload" \
    "$BASE_URL/api/$endpoint" > "$output"
}

json() {
  local file="$1"
  local expression="$2"
  php -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $v=eval("return ".$argv[2].";"); if (is_bool($v)) {echo $v?"1":"0";} elseif ($v!==null) {echo $v;}' "$file" "$expression"
}

assert_json() {
  local file="$1"
  local expression="$2"
  php -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); if (!eval("return (bool)(".$argv[2].");")) {fwrite(STDERR,"Assertion failed: ".$argv[2].PHP_EOL.file_get_contents($argv[1]).PHP_EOL); exit(1);}' "$file" "$expression"
}

curl --fail-with-body --silent --show-error "$BASE_URL/health" > "$RUNTIME_DIR/health-smoke.json"
assert_json "$RUNTIME_DIR/health-smoke.json" '$d["status"] === "ok"'
assert_json "$RUNTIME_DIR/health-smoke.json" 'str_contains((string)$d["database"]["version"], "MariaDB")'

request auth.config '{}' "$RUNTIME_DIR/auth-config-before.json"
assert_json "$RUNTIME_DIR/auth-config-before.json" '$d["data"]["installationRequired"] === true'

request installation.info '{}' "$RUNTIME_DIR/installation-info.json"
assert_json "$RUNTIME_DIR/installation-info.json" 'isset($d["data"]["version"])'

request installation.create '{"teamName":"Smoke Workspace","userName":"Smoke Admin","userEmail":"admin@example.test"}' "$RUNTIME_DIR/installation-create.json"
assert_json "$RUNTIME_DIR/installation-create.json" '$d["data"]["user"]["role"] === "admin"'
assert_json "$RUNTIME_DIR/installation-create.json" '$d["data"]["team"]["name"] === "Smoke Workspace"'
TEAM_ID="$(json "$RUNTIME_DIR/installation-create.json" '$d["data"]["team"]["id"]')"
USER_ID="$(json "$RUNTIME_DIR/installation-create.json" '$d["data"]["user"]["id"]')"
COLLECTION_ID="$(json "$RUNTIME_DIR/installation-create.json" '$d["data"]["collectionId"]')"
WELCOME_DOCUMENT_ID="$(json "$RUNTIME_DIR/installation-create.json" '$d["data"]["documentId"]')"
test -n "$TEAM_ID"
test -n "$USER_ID"
test -n "$COLLECTION_ID"
test -n "$WELCOME_DOCUMENT_ID"

request auth.info '{}' "$RUNTIME_DIR/auth-info.json"
assert_json "$RUNTIME_DIR/auth-info.json" '$d["data"]["user"]["id"] !== ""'
assert_json "$RUNTIME_DIR/auth-info.json" 'substr_count($d["data"]["collaborationToken"], ".") === 2'

request users.list '{"limit":25}' "$RUNTIME_DIR/users-list.json"
assert_json "$RUNTIME_DIR/users-list.json" 'count($d["data"]) === 1'

request collections.list '{"limit":25}' "$RUNTIME_DIR/collections-list.json"
assert_json "$RUNTIME_DIR/collections-list.json" 'count($d["data"]) === 1'

request documents.list "{\"collectionId\":\"$COLLECTION_ID\",\"limit\":25}" "$RUNTIME_DIR/documents-list.json"
assert_json "$RUNTIME_DIR/documents-list.json" 'count($d["data"]) >= 1'

request documents.create "{\"collectionId\":\"$COLLECTION_ID\",\"title\":\"Smoke document\",\"text\":\"MariaDB full text smoke marker\",\"publish\":true}" "$RUNTIME_DIR/document-create.json"
DOCUMENT_ID="$(json "$RUNTIME_DIR/document-create.json" '$d["data"]["id"]')"
REVISION="$(json "$RUNTIME_DIR/document-create.json" '$d["data"]["revision"]')"
test -n "$DOCUMENT_ID"
test "$REVISION" = "1"

request documents.info "{\"id\":\"$DOCUMENT_ID\"}" "$RUNTIME_DIR/document-info.json"
assert_json "$RUNTIME_DIR/document-info.json" '$d["data"]["title"] === "Smoke document"'

request documents.update "{\"id\":\"$DOCUMENT_ID\",\"revision\":1,\"title\":\"Smoke document updated\",\"text\":\"MariaDB full text smoke marker updated\"}" "$RUNTIME_DIR/document-update.json"
assert_json "$RUNTIME_DIR/document-update.json" '$d["data"]["revision"] === 2'

status=$(curl --silent --output "$RUNTIME_DIR/document-conflict.json" --write-out '%{http_code}' \
  --cookie "$COOKIE_JAR" --cookie-jar "$COOKIE_JAR" \
  --header 'Content-Type: application/json' --request POST \
  --data "{\"id\":\"$DOCUMENT_ID\",\"revision\":1,\"title\":\"stale\"}" \
  "$BASE_URL/api/documents.update")
test "$status" = "409"
assert_json "$RUNTIME_DIR/document-conflict.json" '$d["error"] === "revision_conflict"'

request revisions.list "{\"documentId\":\"$DOCUMENT_ID\"}" "$RUNTIME_DIR/revisions-list.json"
assert_json "$RUNTIME_DIR/revisions-list.json" 'count($d["data"]) === 2'

request documents.search '{"query":"marker updated","limit":20}' "$RUNTIME_DIR/documents-search.json"
assert_json "$RUNTIME_DIR/documents-search.json" 'count($d["data"]) >= 1'

request comments.create "{\"documentId\":\"$DOCUMENT_ID\",\"data\":{\"text\":\"Smoke comment\"}}" "$RUNTIME_DIR/comment-create.json"
COMMENT_ID="$(json "$RUNTIME_DIR/comment-create.json" '$d["data"]["id"]')"
test -n "$COMMENT_ID"

request reactions.create "{\"commentId\":\"$COMMENT_ID\",\"emoji\":\"+1\"}" "$RUNTIME_DIR/reaction-create.json"
assert_json "$RUNTIME_DIR/reaction-create.json" '$d["data"]["commentId"] !== ""'

request comments.list "{\"documentId\":\"$DOCUMENT_ID\"}" "$RUNTIME_DIR/comments-list.json"
assert_json "$RUNTIME_DIR/comments-list.json" 'count($d["data"]["comments"]) === 1'
assert_json "$RUNTIME_DIR/comments-list.json" 'count($d["data"]["reactions"]) === 1'

request stars.create "{\"documentId\":\"$DOCUMENT_ID\"}" "$RUNTIME_DIR/star-create.json"
STAR_ID="$(json "$RUNTIME_DIR/star-create.json" '$d["data"]["id"]')"
request stars.list '{}' "$RUNTIME_DIR/stars-list.json"
assert_json "$RUNTIME_DIR/stars-list.json" 'count($d["data"]["stars"]) === 1'
request stars.delete "{\"id\":\"$STAR_ID\"}" "$RUNTIME_DIR/star-delete.json"

request pins.create "{\"documentId\":\"$DOCUMENT_ID\",\"collectionId\":\"$COLLECTION_ID\"}" "$RUNTIME_DIR/pin-create.json"
PIN_ID="$(json "$RUNTIME_DIR/pin-create.json" '$d["data"]["id"]')"
request pins.list "{\"collectionId\":\"$COLLECTION_ID\"}" "$RUNTIME_DIR/pins-list.json"
assert_json "$RUNTIME_DIR/pins-list.json" 'count($d["data"]["pins"]) === 1'
request pins.delete "{\"id\":\"$PIN_ID\"}" "$RUNTIME_DIR/pin-delete.json"

request templates.create '{"name":"Smoke template","title":"Template title","text":"Template body"}' "$RUNTIME_DIR/template-create.json"
TEMPLATE_ID="$(json "$RUNTIME_DIR/template-create.json" '$d["data"]["id"]')"
request templates.list '{}' "$RUNTIME_DIR/templates-list.json"
assert_json "$RUNTIME_DIR/templates-list.json" 'count($d["data"]) === 1'
request templates.delete "{\"id\":\"$TEMPLATE_ID\"}" "$RUNTIME_DIR/template-delete.json"

request groups.create '{"name":"Smoke group"}' "$RUNTIME_DIR/group-create.json"
GROUP_ID="$(json "$RUNTIME_DIR/group-create.json" '$d["data"]["id"]')"
request groups.add_user "{\"groupId\":\"$GROUP_ID\",\"userId\":\"$USER_ID\"}" "$RUNTIME_DIR/group-add-user.json"
request groups.users "{\"groupId\":\"$GROUP_ID\"}" "$RUNTIME_DIR/group-users.json"
assert_json "$RUNTIME_DIR/group-users.json" 'count($d["data"]["users"]) === 1'

request shares.create "{\"documentId\":\"$DOCUMENT_ID\",\"includeChildDocuments\":true}" "$RUNTIME_DIR/share-create.json"
SHARE_ID="$(json "$RUNTIME_DIR/share-create.json" '$d["data"]["id"]')"
SHARE_URL_ID="$(json "$RUNTIME_DIR/share-create.json" '$d["data"]["urlId"]')"
test -n "$SHARE_ID"
test -n "$SHARE_URL_ID"

curl --fail-with-body --silent --show-error \
  --header 'Content-Type: application/json' \
  --request POST \
  --data "{\"id\":\"$SHARE_URL_ID\"}" \
  "$BASE_URL/api/shares.info" > "$RUNTIME_DIR/share-public.json"
assert_json "$RUNTIME_DIR/share-public.json" '$d["data"]["document"]["title"] === "Smoke document updated"'

request shares.revoke "{\"id\":\"$SHARE_ID\"}" "$RUNTIME_DIR/share-revoke.json"

request notifications.list '{}' "$RUNTIME_DIR/notifications-list.json"
assert_json "$RUNTIME_DIR/notifications-list.json" 'isset($d["data"]["notifications"])'

request documents.delete "{\"id\":\"$DOCUMENT_ID\"}" "$RUNTIME_DIR/document-delete.json"
request documents.restore "{\"id\":\"$DOCUMENT_ID\"}" "$RUNTIME_DIR/document-restore.json"
assert_json "$RUNTIME_DIR/document-restore.json" '$d["data"]["id"] !== ""'

request auth.delete '{}' "$RUNTIME_DIR/auth-delete.json"
status=$(curl --silent --output "$RUNTIME_DIR/auth-after-delete.json" --write-out '%{http_code}' \
  --cookie "$COOKIE_JAR" --cookie-jar "$COOKIE_JAR" \
  --header 'Content-Type: application/json' --request POST --data '{}' \
  "$BASE_URL/api/auth.info")
test "$status" = "401"

echo "Smoke tests passed."
