#!/usr/bin/env bash
# wp-env: /providers REST shape, unbound 403, stub-bound connect, products, delete.
# Usage: WP_CLI="npx wp-env run cli wp" bash tests/integration-providers.sh
set -euo pipefail

WP_CLI="${WP_CLI:-npx wp-env run cli wp}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MOCK_SRC="$ROOT/tests/fixtures/providers/mu-plugin-mock.php"

echo "==> activate plugin"
$WP_CLI plugin activate wp-china-yes >/dev/null

echo "==> install WC AM mock mu-plugin"
$WP_CLI eval '
$dest = WP_CONTENT_DIR . "/mu-plugins";
if ( ! is_dir( $dest ) ) {
	wp_mkdir_p( $dest );
}
'
# Copy via wp eval file_get_contents from the mapped plugin path.
$WP_CLI eval '
$src  = WP_PLUGIN_DIR . "/wp-china-yes/tests/fixtures/providers/mu-plugin-mock.php";
$dest = WP_CONTENT_DIR . "/mu-plugins/wpcy-provider-am-mock.php";
if ( ! is_readable( $src ) ) {
	throw new Exception( "mock fixture missing: " . $src );
}
if ( ! is_dir( dirname( $dest ) ) ) {
	wp_mkdir_p( dirname( $dest ) );
}
if ( false === copy( $src, $dest ) ) {
	throw new Exception( "could not copy mock mu-plugin" );
}
echo "mock-installed\n";
'

echo "==> curl GET /wp-json/wpcy/v1/providers (route exists)"
set +e
CURL_RAW="$(curl -sS -o /tmp/wpcy-providers-curl.json -w '%{http_code}' http://localhost:8888/wp-json/wpcy/v1/providers || true)"
set -e
echo "curl HTTP $CURL_RAW"
if [ ! -f /tmp/wpcy-providers-curl.json ]; then
	echo "curl produced no body (wp-env may bind another port); continuing with rest_do_request"
else
	head -c 400 /tmp/wpcy-providers-curl.json; echo
fi

echo "==> GET /providers via rest_do_request (unbound)"
GET_RAW="$($WP_CLI eval '
wp_set_current_user( 1 );
$request  = new WP_REST_Request( "GET", "/wpcy/v1/providers" );
$response = rest_do_request( $request );
$data     = $response->get_data();
echo wp_json_encode( $data );
')"
printf '%s\n' "$GET_RAW"
printf '%s\n' "$GET_RAW" | python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
if start < 0:
    raise SystemExit("GET /providers: no JSON")
data, _ = json.JSONDecoder().raw_decode(raw[start:])
if "binding_status" not in data or "providers" not in data:
    raise SystemExit("missing binding_status or providers")
ids = [p.get("id") for p in data["providers"]]
if ids != ["weixiaoduo-mall", "wenpai-marketplace"]:
    raise SystemExit("unexpected ids: " + str(ids))
blob = json.dumps(data)
if "license_key" in blob:
    raise SystemExit("license_key leaked")
print("get-providers-ok binding_status=" + str(data.get("binding_status")))
'

echo "==> POST connect unbound → 403 wpcy_provider_binding_required"
UNBOUND_RAW="$($WP_CLI eval '
wp_set_current_user( 1 );
$request = new WP_REST_Request( "POST", "/wpcy/v1/providers/weixiaoduo-mall/connect" );
$request->set_header( "X-WP-Nonce", wp_create_nonce( "wp_rest" ) );
$request->set_body( wp_json_encode( array( "email" => "alice@example.com", "license_key" => "test-key" ) ) );
$request->set_header( "Content-Type", "application/json" );
$response = rest_do_request( $request );
$data = $response->get_data();
echo wp_json_encode( array( "status" => $response->get_status(), "data" => $data ) );
')"
printf '%s\n' "$UNBOUND_RAW"
printf '%s\n' "$UNBOUND_RAW" | python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
data, _ = json.JSONDecoder().raw_decode(raw[start:])
inner = data.get("data") or {}
code = inner.get("code") or (inner.get("data") or {}).get("code")
# WP_REST_Response error shape: {code, message, data:{status}}
if inner.get("code") != "wpcy_provider_binding_required" and data.get("status") != 403:
    # rest_do_request wraps WP_Error as {code, message, data}
    if inner.get("code") != "wpcy_provider_binding_required":
        raise SystemExit("expected binding_required, got " + json.dumps(data)[:500])
print("unbound-403-ok")
'

echo "==> stub binding=bound"
$WP_CLI eval '
$identity = get_option( "wpcy_site_identity", array() );
if ( ! is_array( $identity ) ) {
	$identity = array();
}
$identity["schema_version"] = 1;
if ( empty( $identity["site_uuid"] ) ) {
	$identity["site_uuid"] = wp_generate_uuid4();
}
$binding = isset( $identity["binding"] ) && is_array( $identity["binding"] ) ? $identity["binding"] : array();
$binding["status"]     = "bound";
$binding["site_hash"]  = "integration-hash";
$binding["bound_at"]   = gmdate( "Y-m-d\\TH:i:s\\Z" );
$identity["binding"]   = $binding;
update_option( "wpcy_site_identity", $identity, false );
echo "bound\n";
'

echo "==> POST connect bound + mock → 200 connected"
CONNECT_RAW="$($WP_CLI eval '
wp_set_current_user( 1 );
$request = new WP_REST_Request( "POST", "/wpcy/v1/providers/weixiaoduo-mall/connect" );
$request->set_header( "X-WP-Nonce", wp_create_nonce( "wp_rest" ) );
$request->set_header( "Content-Type", "application/json" );
$request->set_body( wp_json_encode( array( "email" => "alice@example.com", "license_key" => "test-key-not-real" ) ) );
$response = rest_do_request( $request );
echo wp_json_encode( array( "status" => $response->get_status(), "data" => $response->get_data() ) );
')"
printf '%s\n' "$CONNECT_RAW"
printf '%s\n' "$CONNECT_RAW" | python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
data, _ = json.JSONDecoder().raw_decode(raw[start:])
inner = data.get("data") or {}
if data.get("status") != 200:
    raise SystemExit("connect status " + str(data.get("status")) + " " + json.dumps(inner)[:400])
if inner.get("connection") != "connected":
    raise SystemExit("connection not connected: " + json.dumps(inner)[:400])
blob = json.dumps(inner)
if "license_key" in blob or "alice@example.com" in blob:
    raise SystemExit("secret leaked")
print("connect-200-ok")
'

echo "==> GET /providers/weixiaoduo-mall/products"
PROD_RAW="$($WP_CLI eval '
wp_set_current_user( 1 );
$request = new WP_REST_Request( "GET", "/wpcy/v1/providers/weixiaoduo-mall/products" );
$response = rest_do_request( $request );
echo wp_json_encode( $response->get_data() );
')"
printf '%s\n' "$PROD_RAW"
printf '%s\n' "$PROD_RAW" | python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
data, _ = json.JSONDecoder().raw_decode(raw[start:])
if "products" not in data or not isinstance(data["products"], list):
    raise SystemExit("products missing")
if not data["products"]:
    raise SystemExit("products empty")
print("products-ok n=" + str(len(data["products"])))
'

echo "==> DELETE /providers/weixiaoduo-mall → disconnected"
DEL_RAW="$($WP_CLI eval '
wp_set_current_user( 1 );
$request = new WP_REST_Request( "DELETE", "/wpcy/v1/providers/weixiaoduo-mall" );
$request->set_header( "X-WP-Nonce", wp_create_nonce( "wp_rest" ) );
$response = rest_do_request( $request );
echo wp_json_encode( array( "status" => $response->get_status(), "data" => $response->get_data() ) );
')"
printf '%s\n' "$DEL_RAW"
printf '%s\n' "$DEL_RAW" | python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
data, _ = json.JSONDecoder().raw_decode(raw[start:])
inner = data.get("data") or {}
if inner.get("connection") != "disconnected":
    raise SystemExit("not disconnected: " + json.dumps(inner)[:400])
print("delete-disconnected-ok")
'

echo "==> cleanup mock mu-plugin"
$WP_CLI eval '
$dest = WP_CONTENT_DIR . "/mu-plugins/wpcy-provider-am-mock.php";
if ( file_exists( $dest ) ) {
	unlink( $dest );
}
echo "mock-removed\n";
'

echo "integration-providers.sh ok"
