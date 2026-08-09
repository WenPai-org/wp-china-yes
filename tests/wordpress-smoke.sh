#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

npx wp-env run cli wp plugin activate wp-china-yes
npx wp-env run cli wp eval 'if ( "3.9.3" !== CHINA_YES_VERSION ) { throw new Exception( "wrong plugin version" ); }'

# A damaged option must not make plugin bootstrap or get_settings() crash.
npx wp-env run cli wp option update wp_china_yes corrupted-string
npx wp-env run cli wp eval '$settings = \WenPai\ChinaYes\get_settings(); if ( ! is_array( $settings ) || ! empty( $settings["telemetry"] ) ) { throw new Exception( "settings guard or telemetry default failed" ); }'

# Telemetry is opt-in and must not leave a legacy cron event scheduled.
npx wp-env run cli wp cron event run wpcy_daily_telemetry --due-now || true
npx wp-env run cli wp eval 'if ( wp_next_scheduled( "wpcy_daily_telemetry" ) ) { throw new Exception( "telemetry scheduled without consent" ); }'

npx wp-env run cli wp plugin deactivate wp-china-yes
echo "WordPress activation, corrupted-settings and telemetry smoke tests passed."
