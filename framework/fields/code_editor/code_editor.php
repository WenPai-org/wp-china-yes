<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: code_editor
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'WP_CHINA_YES_Field_code_editor' ) ) {
  class WP_CHINA_YES_Field_code_editor extends WP_CHINA_YES_Fields {

    public $version = '6.65.7';
    public $cdn_url = 'https://jsd.admincdn.com/npm/codemirror@';

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $default_settings = array(
        'tabSize'       => 2,
        'lineNumbers'   => true,
        'theme'         => 'default',
        'mode'          => 'htmlmixed',
        'cdnURL'        => $this->cdn_url . $this->version,
      );

      $settings = ( ! empty( $this->field['settings'] ) ) ? $this->field['settings'] : array();
      $settings = wp_parse_args( $settings, $default_settings );

      echo $this->field_before();
      echo '<textarea name="'. esc_attr( $this->field_name() ) .'"'. $this->field_attributes() .' data-editor="'. esc_attr( json_encode( $settings ) ) .'">'. $this->value .'</textarea>';
      echo $this->field_after();

    }

    public function enqueue() {

      $page = ( ! empty( $_GET[ 'page' ] ) ) ? sanitize_text_field( wp_unslash( $_GET[ 'page' ] ) ) : '';

      // Do not loads CodeMirror in revslider page.
      if ( in_array( $page, array( 'revslider' ) ) ) { return; }

      if ( ! wp_script_is( 'wp_china_yes-codemirror' ) ) {
        wp_enqueue_script( 'wp_china_yes-codemirror', esc_url( $this->cdn_url . $this->version .'/lib/codemirror.min.js' ), array( 'wp_china_yes' ), $this->version, true );
        wp_enqueue_script( 'wp_china_yes-codemirror-loadmode', esc_url( $this->cdn_url . $this->version .'/addon/mode/loadmode.min.js' ), array( 'wp_china_yes-codemirror' ), $this->version, true );
      }

      if ( ! wp_style_is( 'wp_china_yes-codemirror' ) ) {
        wp_enqueue_style( 'wp_china_yes-codemirror', esc_url( $this->cdn_url . $this->version .'/lib/codemirror.min.css' ), array(), $this->version );
      }

    }

  }
}
