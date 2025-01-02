<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: icon
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'WP_CHINA_YES_Field_icon' ) ) {
  class WP_CHINA_YES_Field_icon extends WP_CHINA_YES_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $args = wp_parse_args( $this->field, array(
        'button_title' => esc_html__( 'Add Icon', 'wp_china_yes' ),
        'remove_title' => esc_html__( 'Remove Icon', 'wp_china_yes' ),
      ) );

      echo $this->field_before();

      $nonce  = wp_create_nonce( 'wp_china_yes_icon_nonce' );
      $hidden = ( empty( $this->value ) ) ? ' hidden' : '';

      echo '<div class="wp_china_yes-icon-select">';
      echo '<span class="wp_china_yes-icon-preview'. esc_attr( $hidden ) .'"><i class="'. esc_attr( $this->value ) .'"></i></span>';
      echo '<a href="#" class="button button-primary wp_china_yes-icon-add" data-nonce="'. esc_attr( $nonce ) .'">'. $args['button_title'] .'</a>';
      echo '<a href="#" class="button wp_china_yes-warning-primary wp_china_yes-icon-remove'. esc_attr( $hidden ) .'">'. $args['remove_title'] .'</a>';
      echo '<input type="hidden" name="'. esc_attr( $this->field_name() ) .'" value="'. esc_attr( $this->value ) .'" class="wp_china_yes-icon-value"'. $this->field_attributes() .' />';
      echo '</div>';

      echo $this->field_after();

    }

    public function enqueue() {
      add_action( 'admin_footer', array( 'WP_CHINA_YES_Field_icon', 'add_footer_modal_icon' ) );
      add_action( 'customize_controls_print_footer_scripts', array( 'WP_CHINA_YES_Field_icon', 'add_footer_modal_icon' ) );
    }

    public static function add_footer_modal_icon() {
    ?>
      <div id="wp_china_yes-modal-icon" class="wp_china_yes-modal wp_china_yes-modal-icon hidden">
        <div class="wp_china_yes-modal-table">
          <div class="wp_china_yes-modal-table-cell">
            <div class="wp_china_yes-modal-overlay"></div>
            <div class="wp_china_yes-modal-inner">
              <div class="wp_china_yes-modal-title">
                <?php esc_html_e( 'Add Icon', 'wp_china_yes' ); ?>
                <div class="wp_china_yes-modal-close wp_china_yes-icon-close"></div>
              </div>
              <div class="wp_china_yes-modal-header">
                <input type="text" placeholder="<?php esc_html_e( 'Search...', 'wp_china_yes' ); ?>" class="wp_china_yes-icon-search" />
              </div>
              <div class="wp_china_yes-modal-content">
                <div class="wp_china_yes-modal-loading"><div class="wp_china_yes-loading"></div></div>
                <div class="wp_china_yes-modal-load"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php
    }

  }
}
