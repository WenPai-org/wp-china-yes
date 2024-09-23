<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: backup
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'WP_CHINA_YES_Field_backup' ) ) {
  class WP_CHINA_YES_Field_backup extends WP_CHINA_YES_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $unique = $this->unique;
      $nonce  = wp_create_nonce( 'wp_china_yes_backup_nonce' );
      $export = add_query_arg( array( 'action' => 'wp_china_yes-export', 'unique' => $unique, 'nonce' => $nonce ), admin_url( 'admin-ajax.php' ) );

      echo $this->field_before();

      echo '<textarea name="wp_china_yes_import_data" class="wp_china_yes-import-data"></textarea>';
      echo '<button type="submit" class="button button-primary wp_china_yes-confirm wp_china_yes-import" data-unique="'. esc_attr( $unique ) .'" data-nonce="'. esc_attr( $nonce ) .'">'. esc_html__( 'Import', 'wp_china_yes' ) .'</button>';
      echo '<hr />';
      echo '<textarea readonly="readonly" class="wp_china_yes-export-data">'. esc_attr( json_encode( get_option( $unique ) ) ) .'</textarea>';
      echo '<a href="'. esc_url( $export ) .'" class="button button-primary wp_china_yes-export" target="_blank">'. esc_html__( 'Export & Download', 'wp_china_yes' ) .'</a>';
      echo '<hr />';
      echo '<button type="submit" name="wp_china_yes_transient[reset]" value="reset" class="button wp_china_yes-warning-primary wp_china_yes-confirm wp_china_yes-reset" data-unique="'. esc_attr( $unique ) .'" data-nonce="'. esc_attr( $nonce ) .'">'. esc_html__( 'Reset', 'wp_china_yes' ) .'</button>';

      echo $this->field_after();

    }

  }
}
