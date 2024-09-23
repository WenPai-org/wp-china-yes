<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: background
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'WP_CHINA_YES_Field_background' ) ) {
  class WP_CHINA_YES_Field_background extends WP_CHINA_YES_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $args                             = wp_parse_args( $this->field, array(
        'background_color'              => true,
        'background_image'              => true,
        'background_position'           => true,
        'background_repeat'             => true,
        'background_attachment'         => true,
        'background_size'               => true,
        'background_origin'             => false,
        'background_clip'               => false,
        'background_blend_mode'         => false,
        'background_gradient'           => false,
        'background_gradient_color'     => true,
        'background_gradient_direction' => true,
        'background_image_preview'      => true,
        'background_auto_attributes'    => false,
        'compact'                       => false,
        'background_image_library'      => 'image',
        'background_image_placeholder'  => esc_html__( 'Not selected', 'wp_china_yes' ),
      ) );

      if ( $args['compact'] ) {
        $args['background_color']           = false;
        $args['background_auto_attributes'] = true;
      }

      $default_value                    = array(
        'background-color'              => '',
        'background-image'              => '',
        'background-position'           => '',
        'background-repeat'             => '',
        'background-attachment'         => '',
        'background-size'               => '',
        'background-origin'             => '',
        'background-clip'               => '',
        'background-blend-mode'         => '',
        'background-gradient-color'     => '',
        'background-gradient-direction' => '',
      );

      $default_value = ( ! empty( $this->field['default'] ) ) ? wp_parse_args( $this->field['default'], $default_value ) : $default_value;

      $this->value = wp_parse_args( $this->value, $default_value );

      echo $this->field_before();

      echo '<div class="wp_china_yes--background-colors">';

      //
      // Background Color
      if ( ! empty( $args['background_color'] ) ) {

        echo '<div class="wp_china_yes--color">';

        echo ( ! empty( $args['background_gradient'] ) ) ? '<div class="wp_china_yes--title">'. esc_html__( 'From', 'wp_china_yes' ) .'</div>' : '';

        WP_CHINA_YES::field( array(
          'id'      => 'background-color',
          'type'    => 'color',
          'default' => $default_value['background-color'],
        ), $this->value['background-color'], $this->field_name(), 'field/background' );

        echo '</div>';

      }

      //
      // Background Gradient Color
      if ( ! empty( $args['background_gradient_color'] ) && ! empty( $args['background_gradient'] ) ) {

        echo '<div class="wp_china_yes--color">';

        echo ( ! empty( $args['background_gradient'] ) ) ? '<div class="wp_china_yes--title">'. esc_html__( 'To', 'wp_china_yes' ) .'</div>' : '';

        WP_CHINA_YES::field( array(
          'id'      => 'background-gradient-color',
          'type'    => 'color',
          'default' => $default_value['background-gradient-color'],
        ), $this->value['background-gradient-color'], $this->field_name(), 'field/background' );

        echo '</div>';

      }

      //
      // Background Gradient Direction
      if ( ! empty( $args['background_gradient_direction'] ) && ! empty( $args['background_gradient'] ) ) {

        echo '<div class="wp_china_yes--color">';

        echo ( ! empty( $args['background_gradient'] ) ) ? '<div class="wp_china_yes---title">'. esc_html__( 'Direction', 'wp_china_yes' ) .'</div>' : '';

        WP_CHINA_YES::field( array(
          'id'          => 'background-gradient-direction',
          'type'        => 'select',
          'options'     => array(
            ''          => esc_html__( 'Gradient Direction', 'wp_china_yes' ),
            'to bottom' => esc_html__( '&#8659; top to bottom', 'wp_china_yes' ),
            'to right'  => esc_html__( '&#8658; left to right', 'wp_china_yes' ),
            '135deg'    => esc_html__( '&#8664; corner top to right', 'wp_china_yes' ),
            '-135deg'   => esc_html__( '&#8665; corner top to left', 'wp_china_yes' ),
          ),
        ), $this->value['background-gradient-direction'], $this->field_name(), 'field/background' );

        echo '</div>';

      }

      echo '</div>';

      //
      // Background Image
      if ( ! empty( $args['background_image'] ) ) {

        echo '<div class="wp_china_yes--background-image">';

        WP_CHINA_YES::field( array(
          'id'          => 'background-image',
          'type'        => 'media',
          'class'       => 'wp_china_yes-assign-field-background',
          'library'     => $args['background_image_library'],
          'preview'     => $args['background_image_preview'],
          'placeholder' => $args['background_image_placeholder'],
          'attributes'  => array( 'data-depend-id' => $this->field['id'] ),
        ), $this->value['background-image'], $this->field_name(), 'field/background' );

        echo '</div>';

      }

      $auto_class   = ( ! empty( $args['background_auto_attributes'] ) ) ? ' wp_china_yes--auto-attributes' : '';
      $hidden_class = ( ! empty( $args['background_auto_attributes'] ) && empty( $this->value['background-image']['url'] ) ) ? ' wp_china_yes--attributes-hidden' : '';

      echo '<div class="wp_china_yes--background-attributes'. esc_attr( $auto_class . $hidden_class ) .'">';

      //
      // Background Position
      if ( ! empty( $args['background_position'] ) ) {

        WP_CHINA_YES::field( array(
          'id'              => 'background-position',
          'type'            => 'select',
          'options'         => array(
            ''              => esc_html__( 'Background Position', 'wp_china_yes' ),
            'left top'      => esc_html__( 'Left Top', 'wp_china_yes' ),
            'left center'   => esc_html__( 'Left Center', 'wp_china_yes' ),
            'left bottom'   => esc_html__( 'Left Bottom', 'wp_china_yes' ),
            'center top'    => esc_html__( 'Center Top', 'wp_china_yes' ),
            'center center' => esc_html__( 'Center Center', 'wp_china_yes' ),
            'center bottom' => esc_html__( 'Center Bottom', 'wp_china_yes' ),
            'right top'     => esc_html__( 'Right Top', 'wp_china_yes' ),
            'right center'  => esc_html__( 'Right Center', 'wp_china_yes' ),
            'right bottom'  => esc_html__( 'Right Bottom', 'wp_china_yes' ),
          ),
        ), $this->value['background-position'], $this->field_name(), 'field/background' );

      }

      //
      // Background Repeat
      if ( ! empty( $args['background_repeat'] ) ) {

        WP_CHINA_YES::field( array(
          'id'          => 'background-repeat',
          'type'        => 'select',
          'options'     => array(
            ''          => esc_html__( 'Background Repeat', 'wp_china_yes' ),
            'repeat'    => esc_html__( 'Repeat', 'wp_china_yes' ),
            'no-repeat' => esc_html__( 'No Repeat', 'wp_china_yes' ),
            'repeat-x'  => esc_html__( 'Repeat Horizontally', 'wp_china_yes' ),
            'repeat-y'  => esc_html__( 'Repeat Vertically', 'wp_china_yes' ),
          ),
        ), $this->value['background-repeat'], $this->field_name(), 'field/background' );

      }

      //
      // Background Attachment
      if ( ! empty( $args['background_attachment'] ) ) {

        WP_CHINA_YES::field( array(
          'id'       => 'background-attachment',
          'type'     => 'select',
          'options'  => array(
            ''       => esc_html__( 'Background Attachment', 'wp_china_yes' ),
            'scroll' => esc_html__( 'Scroll', 'wp_china_yes' ),
            'fixed'  => esc_html__( 'Fixed', 'wp_china_yes' ),
          ),
        ), $this->value['background-attachment'], $this->field_name(), 'field/background' );

      }

      //
      // Background Size
      if ( ! empty( $args['background_size'] ) ) {

        WP_CHINA_YES::field( array(
          'id'        => 'background-size',
          'type'      => 'select',
          'options'   => array(
            ''        => esc_html__( 'Background Size', 'wp_china_yes' ),
            'cover'   => esc_html__( 'Cover', 'wp_china_yes' ),
            'contain' => esc_html__( 'Contain', 'wp_china_yes' ),
            'auto'    => esc_html__( 'Auto', 'wp_china_yes' ),
          ),
        ), $this->value['background-size'], $this->field_name(), 'field/background' );

      }

      //
      // Background Origin
      if ( ! empty( $args['background_origin'] ) ) {

        WP_CHINA_YES::field( array(
          'id'            => 'background-origin',
          'type'          => 'select',
          'options'       => array(
            ''            => esc_html__( 'Background Origin', 'wp_china_yes' ),
            'padding-box' => esc_html__( 'Padding Box', 'wp_china_yes' ),
            'border-box'  => esc_html__( 'Border Box', 'wp_china_yes' ),
            'content-box' => esc_html__( 'Content Box', 'wp_china_yes' ),
          ),
        ), $this->value['background-origin'], $this->field_name(), 'field/background' );

      }

      //
      // Background Clip
      if ( ! empty( $args['background_clip'] ) ) {

        WP_CHINA_YES::field( array(
          'id'            => 'background-clip',
          'type'          => 'select',
          'options'       => array(
            ''            => esc_html__( 'Background Clip', 'wp_china_yes' ),
            'border-box'  => esc_html__( 'Border Box', 'wp_china_yes' ),
            'padding-box' => esc_html__( 'Padding Box', 'wp_china_yes' ),
            'content-box' => esc_html__( 'Content Box', 'wp_china_yes' ),
          ),
        ), $this->value['background-clip'], $this->field_name(), 'field/background' );

      }

      //
      // Background Blend Mode
      if ( ! empty( $args['background_blend_mode'] ) ) {

        WP_CHINA_YES::field( array(
          'id'            => 'background-blend-mode',
          'type'          => 'select',
          'options'       => array(
            ''            => esc_html__( 'Background Blend Mode', 'wp_china_yes' ),
            'normal'      => esc_html__( 'Normal', 'wp_china_yes' ),
            'multiply'    => esc_html__( 'Multiply', 'wp_china_yes' ),
            'screen'      => esc_html__( 'Screen', 'wp_china_yes' ),
            'overlay'     => esc_html__( 'Overlay', 'wp_china_yes' ),
            'darken'      => esc_html__( 'Darken', 'wp_china_yes' ),
            'lighten'     => esc_html__( 'Lighten', 'wp_china_yes' ),
            'color-dodge' => esc_html__( 'Color Dodge', 'wp_china_yes' ),
            'saturation'  => esc_html__( 'Saturation', 'wp_china_yes' ),
            'color'       => esc_html__( 'Color', 'wp_china_yes' ),
            'luminosity'  => esc_html__( 'Luminosity', 'wp_china_yes' ),
          ),
        ), $this->value['background-blend-mode'], $this->field_name(), 'field/background' );

      }

      echo '</div>';

      echo $this->field_after();

    }

    public function output() {

      $output    = '';
      $bg_image  = array();
      $important = ( ! empty( $this->field['output_important'] ) ) ? '!important' : '';
      $element   = ( is_array( $this->field['output'] ) ) ? join( ',', $this->field['output'] ) : $this->field['output'];

      // Background image and gradient
      $background_color        = ( ! empty( $this->value['background-color']              ) ) ? $this->value['background-color']              : '';
      $background_gd_color     = ( ! empty( $this->value['background-gradient-color']     ) ) ? $this->value['background-gradient-color']     : '';
      $background_gd_direction = ( ! empty( $this->value['background-gradient-direction'] ) ) ? $this->value['background-gradient-direction'] : '';
      $background_image        = ( ! empty( $this->value['background-image']['url']       ) ) ? $this->value['background-image']['url']       : '';


      if ( $background_color && $background_gd_color ) {
        $gd_direction   = ( $background_gd_direction ) ? $background_gd_direction .',' : '';
        $bg_image[] = 'linear-gradient('. $gd_direction . $background_color .','. $background_gd_color .')';
        unset( $this->value['background-color'] );
      }

      if ( $background_image ) {
        $bg_image[] = 'url('. $background_image .')';
      }

      if ( ! empty( $bg_image ) ) {
        $output .= 'background-image:'. implode( ',', $bg_image ) . $important .';';
      }

      // Common background properties
      $properties = array( 'color', 'position', 'repeat', 'attachment', 'size', 'origin', 'clip', 'blend-mode' );

      foreach ( $properties as $property ) {
        $property = 'background-'. $property;
        if ( ! empty( $this->value[$property] ) ) {
          $output .= $property .':'. $this->value[$property] . $important .';';
        }
      }

      if ( $output ) {
        $output = $element .'{'. $output .'}';
      }

      $this->parent->output_css .= $output;

      return $output;

    }

  }
}
