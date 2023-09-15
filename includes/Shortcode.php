<?php

class Shortcode {

     /**
     * The [bocs] shortcode.
     *
     * Accepts a title and will display a box.
     *
     * @param array  $atts    Shortcode attributes. Default empty.
     * @param string $content Shortcode content. Default null.
     * @param string $tag     Shortcode tag (name). Default empty.
     * @return string Shortcode output.
     */
    public function bocs_shortcode( $atts = [], $content = null, $tag = '' ) {

        // normalize attribute keys, lowercase
        $atts = array_change_key_case( (array) $atts, CASE_LOWER );

        $output = '<div id="bocs-widget"';

        if (isset( $atts['collection'] )) {
            $output .= ' data-type="collection" data-id="'.$atts['collection'].'"';
        } else if (isset( $atts['widget'] )) {
		    $output .= ' data-type="bocs" data-id="'.$atts['widget'].'"';
	    }

        $output .= '></div>';

        return $output;
    }

    /**
     * Central location to create all shortcodes.
     */
    public function bocs_shortcodes_init() {
        add_shortcode( 'bocs', [$this, 'bocs_shortcode'] );
    }

}