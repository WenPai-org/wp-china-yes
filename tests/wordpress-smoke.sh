#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

npx wp-env run cli wp plugin activate wp-china-yes
npx wp-env run cli wp eval 'if ( ! defined( "CHINA_YES_VERSION" ) ) { throw new Exception( "CHINA_YES_VERSION missing" ); } if ( ! class_exists( "WenPai\\ChinaYes\\Core\\Plugin" ) ) { throw new Exception( "Core\\Plugin not loaded" ); }'

# A damaged 3.x option must not Fatal; LegacyReader returns []; boot already completed.
npx wp-env run cli wp option update wp_china_yes corrupted-string
npx wp-env run cli wp eval '$legacy = ( new \WenPai\ChinaYes\Migration\LegacyReader() )->read(); if ( ! is_array( $legacy ) ) { throw new Exception( "LegacyReader must return array" ); } if ( array() !== $legacy ) { throw new Exception( "damaged option must read as empty array" ); } if ( ! class_exists( "WenPai\\ChinaYes\\Core\\Plugin" ) ) { throw new Exception( "Core\\Plugin not loaded after damaged option" ); }'

# Compatibility report stays on (TelemetryModule is always-on). Assert the
# hook is registered after writing 4.0 settings (analysis §4: 钩子仍在).
npx wp-env run cli wp eval '$settings = get_option( "wpcy_settings", array() ); if ( ! is_array( $settings ) ) { $settings = array(); } update_option( "wpcy_settings", $settings );'
npx wp-env run cli wp eval 'if ( ! has_action( "wpcy_daily_telemetry" ) ) { throw new Exception( "compatibility report hook not registered" ); }'

# 3.x Maintenance is gone.
npx wp-env run cli wp eval 'if ( class_exists( "WenPai\\ChinaYes\\Service\\Maintenance" ) ) { throw new Exception( "3.x Maintenance class must not exist" ); }'

# Windfonts via 4.0 option (new process so the module reads stored settings).
npx wp-env run cli wp eval '$settings = get_option( "wpcy_settings", array() ); if ( ! is_array( $settings ) ) { $settings = array(); } $settings["modules"]["windfonts"] = true; $settings["integrations"]["windfonts"]["fonts"] = array( array( "family" => "wenfeng-hcszt", "subset" => "full", "selector" => "body", "enable" => true ) ); $settings["connectivity"]["avatar"] = "off"; update_option( "wpcy_settings", $settings );'
npx wp-env run cli wp eval 'ob_start(); do_action( "wp_head" ); $html = ob_get_clean(); if ( false === strpos( $html, "family=wenfeng-hcszt" ) || false === strpos( $html, "subset=full" ) || false !== strpos( $html, "crossorigin" ) ) { throw new Exception( "invalid Windfonts stylesheet output" ); }'

# First-boot migration: 4.0 option absent + wp_china_yes present → Runner::execute().
npx wp-env run cli wp eval 'delete_option( "wpcy_settings" ); delete_option( "wpcy_migration_backup" ); update_option( "wp_china_yes", array( "store" => "off", "cravatar" => "off" ) );'
npx wp-env run cli wp eval 'if ( false === get_option( "wpcy_settings", false ) ) { throw new Exception( "first boot did not write wpcy_settings" ); } if ( false === get_option( "wpcy_migration_backup", false ) ) { throw new Exception( "first boot did not write wpcy_migration_backup" ); } $legacy = get_option( "wp_china_yes" ); if ( ! is_array( $legacy ) || "off" !== ( $legacy["store"] ?? null ) ) { throw new Exception( "first boot must not rewrite wp_china_yes" ); }'

# 4.0 path must not load the 3.x settings framework.
npx wp-env run cli wp eval 'if ( class_exists( "WP_CHINA_YES_Setup" ) ) { throw new Exception( "WP_CHINA_YES_Setup must not exist" ); } $hits = array_filter( get_included_files(), function ( $p ) { return false !== strpos( str_replace( "\\\\", "/", $p ), "/framework/" ); } ); if ( count( $hits ) ) { throw new Exception( "framework/ was loaded" ); }'

npx wp-env run cli wp plugin deactivate wp-china-yes
echo "WordPress 4.0 activation, compatibility-report, fonts, first-boot migration and no-framework smoke tests passed."
