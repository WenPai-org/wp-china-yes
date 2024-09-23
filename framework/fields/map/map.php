<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: map
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'WP_CHINA_YES_Field_map' ) ) {
  class WP_CHINA_YES_Field_map extends WP_CHINA_YES_Fields {

    public $version = '1.9.4';
    public $cdn_url = 'https://jsd.admincdn.com/npm/leaflet@';

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $args              = wp_parse_args( $this->field, array(
        'placeholder'    => esc_html__( 'Search...', 'wp_china_yes' ),
        'latitude_text'  => esc_html__( 'Latitude', 'wp_china_yes' ),
        'longitude_text' => esc_html__( 'Longitude', 'wp_china_yes' ),
        'address_field'  => '',
        'height'         => '',
      ) );

      $value             = wp_parse_args( $this->value, array(
        'address'        => '',
        'latitude'       => '20',
        'longitude'      => '0',
        'zoom'           => '2',
      ) );

      $default_settings   = array(
        'center'          => array( $value['latitude'], $value['longitude'] ),
        'zoom'            => $value['zoom'],
        'scrollWheelZoom' => false,
      );

      $settings = ( ! empty( $this->field['settings'] ) ) ? $this->field['settings'] : array();
      $settings = wp_parse_args( $settings, $default_settings );

      $style_attr  = ( ! empty( $args['height'] ) ) ? ' style="min-height:'. esc_attr( $args['height'] ) .';"' : '';
      $placeholder = ( ! empty( $args['placeholder'] ) ) ? array( 'placeholder' => $args['placeholder'] ) : '';

      echo $this->field_before();

      if ( empty( $args['address_field'] ) ) {
        echo '<div class="wp_china_yes--map-search">';
        echo '<input type="text" name="'. esc_attr( $this->field_name( '[address]' ) ) .'" value="'. esc_attr( $value['address'] ) .'"'. $this->field_attributes( $placeholder ) .' />';
        echo '</div>';
      } else {
        echo '<div class="wp_china_yes--address-field" data-address-field="'. esc_attr( $args['address_field'] ) .'"></div>';
      }

      echo '<div class="wp_china_yes--map-osm-wrap"><div class="wp_china_yes--map-osm" data-map="'. esc_attr( json_encode( $settings ) ) .'"'. $style_attr .'></div></div>';

      echo '<div class="wp_china_yes--map-inputs">';

        echo '<div class="wp_china_yes--map-input">';
        echo '<label>'. esc_attr( $args['latitude_text'] ) .'</label>';
        echo '<input type="text" name="'. esc_attr( $this->field_name( '[latitude]' ) ) .'" value="'. esc_attr( $value['latitude'] ) .'" class="wp_china_yes--latitude" />';
        echo '</div>';

        echo '<div class="wp_china_yes--map-input">';
        echo '<label>'. esc_attr( $args['longitude_text'] ) .'</label>';
        echo '<input type="text" name="'. esc_attr( $this->field_name( '[longitude]' ) ) .'" value="'. esc_attr( $value['longitude'] ) .'" class="wp_china_yes--longitude" />';
        echo '</div>';

      echo '</div>';

      echo '<input type="hidden" name="'. esc_attr( $this->field_name( '[zoom]' ) ) .'" value="'. esc_attr( $value['zoom'] ) .'" class="wp_china_yes--zoom" />';

      echo $this->field_after();

    }

    public function enqueue() {

      if ( ! wp_script_is( 'wp_china_yes-leaflet' ) ) {
        wp_enqueue_script( 'wp_china_yes-leaflet', esc_url( $this->cdn_url . $this->version .'/dist/leaflet.js' ), array( 'wp_china_yes' ), $this->version, true );
      }

      if ( ! wp_style_is( 'wp_china_yes-leaflet' ) ) {
        wp_enqueue_style( 'wp_china_yes-leaflet', esc_url( $this->cdn_url . $this->version .'/dist/leaflet.css' ), array(), $this->version );
      }

      if ( ! wp_script_is( 'jquery-ui-autocomplete' ) ) {
        wp_enqueue_script( 'jquery-ui-autocomplete' );
      }

    }

  }
}
