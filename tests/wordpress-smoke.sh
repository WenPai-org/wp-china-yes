#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

npx wp-env run cli wp plugin activate wp-china-yes
npx wp-env run cli wp eval 'if ( "3.9.3" !== CHINA_YES_VERSION ) { throw new Exception( "wrong plugin version" ); }'

# A damaged option must not make plugin bootstrap or get_settings() crash.
npx wp-env run cli wp option update wp_china_yes corrupted-string
npx wp-env run cli wp eval '$settings = \WenPai\ChinaYes\get_settings(); if ( ! is_array( $settings ) ) { throw new Exception( "settings guard failed" ); }'

# The daily compatibility report has no switch: it must be scheduled even when
# the bridge update channel is off and legacy telemetry flags are stored as false.
# Clear any event left by earlier bootstraps first, so the assertion below can
# only be satisfied by the fresh bootstrap that follows the option change.
npx wp-env run cli wp eval 'wp_clear_scheduled_hook( "wpcy_daily_telemetry" ); update_option( "wp_china_yes", [ "store" => "off", "bridge" => false, "telemetry" => false, "telemetry_site_url" => false, "admincdn" => [], "admincdn_public" => [], "admincdn_files" => [], "admincdn_dev" => [], "cravatar" => "off" ] ); if ( wp_next_scheduled( "wpcy_daily_telemetry" ) ) { throw new Exception( "cron clear failed" ); }'
npx wp-env run cli wp eval 'if ( ! wp_next_scheduled( "wpcy_daily_telemetry" ) ) { throw new Exception( "compatibility report not scheduled with bridge=false" ); }'

# Maintenance callbacks must exist; 3.9.2 registered methods that were absent
# from the autoloaded class and caused fatal errors on both front and admin.
npx wp-env run cli wp eval 'update_option( "wp_china_yes", [ "store" => "off", "admincdn" => [], "admincdn_public" => [], "admincdn_files" => [], "admincdn_dev" => [], "cravatar" => "off", "maintenance_mode" => true ] );'
npx wp-env run cli wp eval '$class = new ReflectionClass( "WenPai\\ChinaYes\\Service\\Maintenance" ); foreach ( [ "check_maintenance_mode", "add_admin_bar_notice" ] as $method ) { if ( ! $class->hasMethod( $method ) ) { throw new Exception( "missing maintenance callback: " . $method ); } } if ( ! has_action( "template_redirect" ) ) { throw new Exception( "maintenance mode did not register" ); }'

# The memory service is instantiated during plugins_loaded, so registering a
# second plugins_loaded callback there silently leaves the feature inactive.
npx wp-env run cli wp eval 'update_option( "wp_china_yes", [ "store" => "off", "admincdn" => [], "admincdn_public" => [], "admincdn_files" => [], "admincdn_dev" => [], "cravatar" => "off", "memory" => true ] );'
npx wp-env run cli wp eval 'if ( ! has_filter( "admin_footer_text" ) ) { throw new Exception( "memory footer did not register" ); }'

# Windfonts current API accepts character-set subsets, and the stylesheet must
# not opt into CORS because the provider does not send an ACAO header.
npx wp-env run cli wp eval 'update_option( "wp_china_yes", [ "store" => "off", "admincdn" => [], "admincdn_public" => [], "admincdn_files" => [], "admincdn_dev" => [], "cravatar" => "off", "windfonts" => "frontend", "windfonts_list" => [ [ "family" => "wenfeng-hcszt", "subset" => "full", "selector" => "body", "enable" => true ] ] ] );'
npx wp-env run cli wp eval 'ob_start(); do_action( "wp_head" ); $html = ob_get_clean(); if ( false === strpos( $html, "family=wenfeng-hcszt" ) || false === strpos( $html, "subset=full" ) || false !== strpos( $html, "crossorigin" ) ) { throw new Exception( "invalid Windfonts stylesheet output" ); }'

# Malformed or incomplete stored options must not emit array-key warnings.
npx wp-env run cli wp eval 'update_option( "wp_china_yes", [ "store" => "off", "admincdn" => [], "admincdn_public" => [], "admincdn_files" => [], "admincdn_dev" => [], "cravatar" => "off", "waimao_enable" => true, "waimao_language_split" => true ] );'
npx wp-env run cli wp eval 'do_action( "admin_init" ); $saved = get_option( "wp_china_yes" ); if ( ! is_array( $saved ) ) { throw new Exception( "language settings were corrupted" ); }'
npx wp-env run cli wp eval 'update_option( "wp_china_yes", [ "store" => "off", "admincdn" => [], "admincdn_public" => [], "admincdn_files" => [], "admincdn_dev" => [], "cravatar" => "off", "webp_support" => true ] );'
npx wp-env run cli wp eval '$metadata = apply_filters( "wp_generate_attachment_metadata", [] ); if ( [] !== $metadata ) { throw new Exception( "empty media metadata changed" ); }'

# 3.x path (WPCY_KERNEL undefined) still loads the settings framework.
npx wp-env run cli wp eval 'if ( ! class_exists( "WP_CHINA_YES_Setup" ) ) { throw new Exception( "3.x path did not load WP_CHINA_YES_Setup" ); }'

# WPCY_KERNEL=v4 cannot be defined before plugin bootstrap via `wp eval`
# (WordPress and plugins are already loaded). Covered by KernelSwitchTest.

npx wp-env run cli wp plugin deactivate wp-china-yes
echo "WordPress activation, maintenance, memory, fonts, language, media and compatibility-report smoke tests passed."
