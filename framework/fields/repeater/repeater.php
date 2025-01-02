<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: repeater
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'WP_CHINA_YES_Field_repeater' ) ) {
  class WP_CHINA_YES_Field_repeater extends WP_CHINA_YES_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $args = wp_parse_args( $this->field, array(
        'max'          => 0,
        'min'          => 0,
        'button_title' => '<i class="fas fa-plus-circle"></i>',
      ) );

      if ( preg_match( '/'. preg_quote( '['. $this->field['id'] .']' ) .'/', $this->unique ) ) {

        echo '<div class="wp_china_yes-notice wp_china_yes-notice-danger">'. esc_html__( 'Error: Field ID conflict.', 'wp_china_yes' ) .'</div>';

      } else {

        echo $this->field_before();

        echo '<div class="wp_china_yes-repeater-item wp_china_yes-repeater-hidden" data-depend-id="'. esc_attr( $this->field['id'] ) .'">';
        echo '<div class="wp_china_yes-repeater-content">';
        foreach ( $this->field['fields'] as $field ) {

          $field_default = ( isset( $field['default'] ) ) ? $field['default'] : '';
          $field_unique  = ( ! empty( $this->unique ) ) ? $this->unique .'['. $this->field['id'] .'][0]' : $this->field['id'] .'[0]';

          WP_CHINA_YES::field( $field, $field_default, '___'. $field_unique, 'field/repeater' );

        }
        echo '</div>';
        echo '<div class="wp_china_yes-repeater-helper">';
        echo '<div class="wp_china_yes-repeater-helper-inner">';
        echo '<i class="wp_china_yes-repeater-sort fas fa-arrows-alt"></i>';
        echo '<i class="wp_china_yes-repeater-clone far fa-clone"></i>';
        echo '<i class="wp_china_yes-repeater-remove wp_china_yes-confirm fas fa-times" data-confirm="'. esc_html__( 'Are you sure to delete this item?', 'wp_china_yes' ) .'"></i>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="wp_china_yes-repeater-wrapper wp_china_yes-data-wrapper" data-field-id="['. esc_attr( $this->field['id'] ) .']" data-max="'. esc_attr( $args['max'] ) .'" data-min="'. esc_attr( $args['min'] ) .'">';

        if ( ! empty( $this->value ) && is_array( $this->value ) ) {

          $num = 0;

          foreach ( $this->value as $key => $value ) {

            echo '<div class="wp_china_yes-repeater-item">';
            echo '<div class="wp_china_yes-repeater-content">';
            foreach ( $this->field['fields'] as $field ) {

              $field_unique = ( ! empty( $this->unique ) ) ? $this->unique .'['. $this->field['id'] .']['. $num .']' : $this->field['id'] .'['. $num .']';
              $field_value  = ( isset( $field['id'] ) && isset( $this->value[$key][$field['id']] ) ) ? $this->value[$key][$field['id']] : '';

              WP_CHINA_YES::field( $field, $field_value, $field_unique, 'field/repeater' );

            }
            echo '</div>';
            echo '<div class="wp_china_yes-repeater-helper">';
            echo '<div class="wp_china_yes-repeater-helper-inner">';
            echo '<i class="wp_china_yes-repeater-sort fas fa-arrows-alt"></i>';
            echo '<i class="wp_china_yes-repeater-clone far fa-clone"></i>';
            echo '<i class="wp_china_yes-repeater-remove wp_china_yes-confirm fas fa-times" data-confirm="'. esc_html__( 'Are you sure to delete this item?', 'wp_china_yes' ) .'"></i>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            $num++;

          }

        }

        echo '</div>';

        echo '<div class="wp_china_yes-repeater-alert wp_china_yes-repeater-max">'. esc_html__( 'You cannot add more.', 'wp_china_yes' ) .'</div>';
        echo '<div class="wp_china_yes-repeater-alert wp_china_yes-repeater-min">'. esc_html__( 'You cannot remove more.', 'wp_china_yes' ) .'</div>';
        echo '<a href="#" class="button button-primary wp_china_yes-repeater-add">'. $args['button_title'] .'</a>';

        echo $this->field_after();

      }

    }

    public function enqueue() {

      if ( ! wp_script_is( 'jquery-ui-sortable' ) ) {
        wp_enqueue_script( 'jquery-ui-sortable' );
      }

    }

  }
}
