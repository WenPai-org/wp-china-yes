<?php

require_once ABSPATH . WPINC . '/Text/Diff.php';
require_once ABSPATH . WPINC . '/Text/Diff/Renderer.php';
require_once ABSPATH . WPINC . '/Text/Diff/Renderer/inline.php';
require_once ABSPATH . WPINC . '/wp-diff.php';

/**
 * Diff renderer extending that which WordPress uses for post revisions.
 */
class Loco_output_DiffRenderer extends WP_Text_Diff_Renderer_Table {

    /**
     * {@inheritdoc}
     */
    public function __construct( $params = array() ){
        parent::__construct( $params + array (
            'show_split_view' => true,
            'leading_context_lines' => 1,
            'trailing_context_lines' => 1,
        ) );
    }


    /**
     * Render diff of two files, presumed to be PO or POT
     * @return string HTML table
     */
    public function renderFiles( Loco_fs_File $lhs, Loco_fs_File $rhs ){
        loco_require_lib('compiled/gettext.php');
        // attempt to raise memory limit to WP_MAX_MEMORY_LIMIT
        if( function_exists('wp_raise_memory_limit') ){
            wp_raise_memory_limit('loco');
        }
        // like wp_text_diff but avoiding whitespace normalization
        // uses deprecated signature for 'auto' in case of old WordPress
        return $this->render( new Text_Diff (
            preg_split( '/(?:\\n|\\r\\n?)/', Loco_gettext_Data::ensureUtf8( $lhs->getContents() ) ),
            preg_split( '/(?:\\n|\\r\\n?)/', Loco_gettext_Data::ensureUtf8( $rhs->getContents() ) )
        ) );
    }


    /**
     * {@inheritdoc}
     */
    public function _startDiff() {
        return "<table class=\"diff\">\n";
    }


    /**
     * {@inheritdoc}
     */
    public function _endDiff() {
        return "</table>\n";
    }


    /**
     * {@inheritdoc}
     */
    public function _startBlock( $header ) {
        return '<tbody data-diff="'.esc_attr($header)."\">\n";
    }


    /**
     * {@inheritdoc}
     */
    public function _endBlock() {
        return "</tbody>\n";
    }

}
