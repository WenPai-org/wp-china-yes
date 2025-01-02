<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Abstract Class
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'WP_CHINA_YES_Abstract' ) ) {
#[AllowDynamicProperties]
abstract class WP_CHINA_YES_Abstract {

  public $abstract   = '';
  public $output_css = '';

  public function __construct() {

    // Collect output css and typography
    if ( ! empty( $this->args['output_css'] ) || ! empty( $this->args['enqueue_webfont'] ) ) {
      add_action( 'wp_enqueue_scripts', array( $this, 'collect_output_css_and_typography' ), 10 );
      WP_CHINA_YES::$css = apply_filters( "wp_china_yes_{$this->unique}_output_css", WP_CHINA_YES::$css, $this );
    }

  }

  public function collect_output_css_and_typography() {
    $this->recursive_output_css( $this->pre_fields );
  }

  public function recursive_output_css( $fields = array(), $combine_field = array() ) {

    if ( ! empty( $fields ) ) {

      foreach ( $fields as $field ) {

        $field_id     = ( ! empty( $field['id'] ) ) ? $field['id'] : '';
        $field_type   = ( ! empty( $field['type'] ) ) ? $field['type'] : '';
        $field_output = ( ! empty( $field['output'] ) ) ? $field['output'] : '';
        $field_check  = ( $field_type === 'typography' || $field_output ) ? true : false;
        $field_class  = 'WP_CHINA_YES_Field_' . $field_type;

        if ( $field_type && $field_id ) {


          if( $field_type === 'fieldset' ) {
            if ( ! empty( $field['fields'] ) ) {
              $this->recursive_output_css( $field['fields'], $field );
            }
          }

          if( $field_type === 'accordion' ) {
            if ( ! empty( $field['accordions'] ) ) {
              foreach ( $field['accordions'] as $accordion ) {
                $this->recursive_output_css( $accordion['fields'], $field );
              }
            }
          }

          if( $field_type === 'tabbed' ) {
            if ( ! empty( $field['tabs'] ) ) {
              foreach ( $field['tabs'] as $accordion ) {
                $this->recursive_output_css( $accordion['fields'], $field );
              }
            }
          }

          if ( class_exists( $field_class ) ) {

            if ( method_exists( $field_class, 'output' ) || method_exists( $field_class, 'enqueue_google_fonts' ) ) {

              $field_value = '';

              if ( $field_check && ( $this->abstract === 'options' || $this->abstract === 'customize' ) ) {

                if( ! empty( $combine_field ) ) {

                  $field_value = ( isset( $this->options[$combine_field['id']][$field_id] ) ) ? $this->options[$combine_field['id']][$field_id] : '';

                } else {

                  $field_value = ( isset( $this->options[$field_id] ) ) ? $this->options[$field_id] : '';

                }

              } else if ( $field_check && ( $this->abstract === 'metabox' && is_singular() || $this->abstract === 'taxonomy' && is_archive() ) ) {

                if( ! empty( $combine_field ) ) {

                  $meta_value  = $this->get_meta_value( $combine_field );
                  $field_value = ( isset( $meta_value[$field_id] ) ) ? $meta_value[$field_id] : '';

                } else {

                  $meta_value  = $this->get_meta_value( $field );
                  $field_value = ( isset( $meta_value ) ) ? $meta_value : '';

                }

              }

              $instance = new $field_class( $field, $field_value, $this->unique, 'wp/enqueue', $this );

              // typography enqueue and embed google web fonts
              if ( $field_type === 'typography' && $this->args['enqueue_webfont'] && ! empty( $field_value['font-family'] ) ) {

                $method = ( ! empty( $this->args['async_webfont'] ) ) ? 'async' : 'enqueue';

                $instance->enqueue_google_fonts( $method );

              }

              // output css
              if ( $field_output && $this->args['output_css'] ) {
                WP_CHINA_YES::$css .= $instance->output();
              }

              unset( $instance );

            }

          }

        }

      }

    }

  }

  public function pre_tabs( $sections ) {

    $count   = 100;
    $result  = array();
    $parents = array();

    foreach ( $sections as $key => $section ) {
      if ( ! empty( $section['parent'] ) ) {
        $section['priority'] = ( isset( $section['priority'] ) ) ? $section['priority'] : $count;
        $parents[$section['parent']][] = $section;
        unset( $sections[$key] );
      }
      $count++;
    }

    foreach ( $sections as $key => $section ) {
      $section['priority'] = ( isset( $section['priority'] ) ) ? $section['priority'] : $count;
      if ( ! empty( $section['id'] ) && ! empty( $parents[$section['id']] ) ) {
        $section['subs'] = wp_list_sort( $parents[$section['id']], array( 'priority' => 'ASC' ), 'ASC', true );
      }
      $result[] = $section;
      $count++;
    }

    return wp_list_sort( $result, array( 'priority' => 'ASC' ), 'ASC', true );

  }

  public function pre_sections( $sections ) {

    $result = array();

    foreach ( $this->pre_tabs( $sections ) as $section ) {
      if ( ! empty( $section['subs'] ) ) {
        foreach ( $section['subs'] as $sub ) {
          $sub['ptitle'] = ( ! empty( $section['title'] ) ) ? $section['title'] : '';
          $result[] = $sub;
        }
      }
      if ( empty( $section['subs'] ) ) {
        $result[] = $section;
      }
    }

    return $result;
  }

  public function pre_fields( $sections ) {

    $result = array();

    foreach ( $sections as $key => $section ) {
      if ( ! empty( $section['fields'] ) ) {
        foreach ( $section['fields'] as $field ) {
          $result[] = $field;
        }
      }
    }

    return $result;
  }

}
}
