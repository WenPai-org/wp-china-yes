#!/usr/bin/env bash
# wp-env / Studio: GET /wpcy/v1/stats and /events shape after a diagnostics run.
# Usage: WP_CLI="npx wp-env run cli wp" bash tests/integration-stats.sh
set -euo pipefail

WP_CLI="${WP_CLI:-npx wp-env run cli wp}"

echo "==> activate plugin"
$WP_CLI plugin activate wp-china-yes >/dev/null

echo "==> reset state left by earlier integration scripts (integration-recovery.sh leaves recovery_mode=true; events would then only record recovery_*)"
$WP_CLI eval '
$settings = get_option( "wpcy_settings", array() );
if ( is_array( $settings ) ) {
	$settings["recovery_mode"] = false;
	update_option( "wpcy_settings", $settings );
}
delete_option( "wpcy_events" );
delete_transient( \WenPai\ChinaYes\Diagnostics\Checker::STORE_KEY ); // integration-cli.sh already ran a check; first_check needs an empty "before".
echo "reset-ok\n";
'

echo "==> run diagnostics checker (cron hook equivalent)"
$WP_CLI eval '
$events  = new \WenPai\ChinaYes\Stats\Events();
$checker = new \WenPai\ChinaYes\Diagnostics\Checker( null, null, null, null, null, $events );
$checker->run();
$events->flush();
echo "checker-run-ok\n";
'

echo "==> GET /wpcy/v1/stats?days=7"
STATS_RAW="$($WP_CLI eval '
wp_set_current_user( 1 );
$request = new WP_REST_Request( "GET", "/wpcy/v1/stats" );
$request->set_param( "days", 7 );
$response = rest_do_request( $request );
if ( $response->is_error() ) {
	throw new Exception( "stats error: " . $response->as_error()->get_error_code() );
}
echo wp_json_encode( $response->get_data() );
')"
printf '%s\n' "$STATS_RAW" | python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
if start < 0:
    raise SystemExit("stats stdout has no JSON object")
data, _ = json.JSONDecoder().raw_decode(raw[start:])
need = ("installed_at", "days", "from", "to", "series", "totals")
missing = [k for k in need if k not in data]
if missing:
    raise SystemExit("stats missing keys: " + ",".join(missing))
if data["days"] != 7:
    raise SystemExit("days != 7")
if len(data["series"]) != 10:
    raise SystemExit("series must have 10 counters, got %d" % len(data["series"]))
for name, points in data["series"].items():
    if len(points) != 7:
        raise SystemExit("%s series length %d != 7" % (name, len(points)))
    dates = [p["date"] for p in points]
    if dates != sorted(dates):
        raise SystemExit("%s dates not ascending" % name)
blob = json.dumps(data, ensure_ascii=False)
import re
if "http" in blob.lower():
    raise SystemExit("stats privacy: http in JSON")
if "?" in blob:
    raise SystemExit("stats privacy: ? in JSON")
if re.search(r"\b(?:\d{1,3}\.){3}\d{1,3}\b", blob):
    raise SystemExit("stats privacy: IPv4 literal in JSON")
print("stats days:", data["days"])
print("stats from/to:", data["from"], data["to"])
print("stats counters:", ",".join(sorted(data["series"].keys())))
print("stats installed_at:", data["installed_at"])
print("stats totals.mirror_downloads:", data["totals"].get("mirror_downloads"))
'

echo "==> GET /wpcy/v1/events"
EVENTS_RAW="$($WP_CLI eval '
wp_set_current_user( 1 );
$request = new WP_REST_Request( "GET", "/wpcy/v1/events" );
$response = rest_do_request( $request );
if ( $response->is_error() ) {
	throw new Exception( "events error: " . $response->as_error()->get_error_code() );
}
echo wp_json_encode( $response->get_data() );
')"
printf '%s\n' "$EVENTS_RAW" | python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
if start < 0:
    raise SystemExit("events stdout has no JSON object")
data, _ = json.JSONDecoder().raw_decode(raw[start:])
if "events" not in data or not isinstance(data["events"], list):
    raise SystemExit("events missing list")
if not data["events"]:
    raise SystemExit("events list empty after checker run")
row = data["events"][0]
for key in ("id", "at", "type", "tone", "title", "detail"):
    if key not in row:
        raise SystemExit("event missing " + key)
if len(row["id"]) != 26:
    raise SystemExit("event id length %d != 26" % len(row["id"]))
if row["type"] != "first_check":
    raise SystemExit("expected first_check, got " + row["type"])
blob = json.dumps(data, ensure_ascii=False)
import re
if "http" in blob.lower():
    raise SystemExit("events privacy: http in JSON")
if "?" in blob:
    raise SystemExit("events privacy: ? in JSON")
if re.search(r"\b(?:\d{1,3}\.){3}\d{1,3}\b", blob):
    raise SystemExit("events privacy: IPv4 literal in JSON")
print("event type:", row["type"])
print("event title:", row["title"])
print("event detail:", row["detail"])
print("event id_len:", len(row["id"]))
'

echo "integration-stats.sh ok"
