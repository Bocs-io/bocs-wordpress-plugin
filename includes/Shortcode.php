<?php

class Shortcode
{

    /**
     * The [bocs] shortcode.
     *
     * Accepts a title and will display a box.
     *
     * @param array $atts
     *            Shortcode attributes. Default empty.
     * @param string $content
     *            Shortcode content. Default null.
     * @param string $tag
     *            Shortcode tag (name). Default empty.
     * @return string Shortcode output.
     */
    public function bocs_shortcode($atts = [], $content = null, $tag = '')
    {

        // normalize attribute keys, lowercase
        $atts = array_change_key_case((array) $atts, CASE_LOWER);

        $output = '<div id="bocs-widget"';

        /*if (isset($atts['collection'])) {
            $output .= ' data-type="collections" data-id="' . $atts['collection'] . '"';
        } else if (isset($atts['widget'])) {
            $output .= ' data-type="bocs" data-id="' . $atts['widget'] . '"';
        }*/

        if (isset($atts['widget'])) {
            $output .= ' data-id="' . $atts['widget'] . '" data-url="' . NEXT_PUBLIC_API_EXTERNAL_URL . '"';
        }

        $output .= '></div>';

        return $output;
    }

    /**
     * Register all shortcodes
     */
    public function bocs_shortcodes_init()
    {
        add_shortcode('bocs', [
            $this,
            'bocs_shortcode'
        ]);
        add_shortcode('bocs_product', array($this, 'bocs_product_func'));
        add_shortcode('bocs_product_landing', array($this, 'bocs_product_landing_func'));
    }
    
    /**
     * BOCS product shortcode function
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered product content
     */
    public function bocs_product_func($atts = [])
    {
        // Normalize attribute keys to lowercase
        $atts = array_change_key_case((array) $atts, CASE_LOWER);
        
        // Extract attributes
        $attributes = shortcode_atts(
            array(
                'id' => '', // Product ID
                'widget' => '', // BOCS widget ID if needed
            ),
            $atts
        );
        
        // Output buffer
        ob_start();
        
        if (!empty($attributes['id'])) {
            // Output the WooCommerce product with BOCS subscriptions
            echo do_shortcode('[product_page id="' . esc_attr($attributes['id']) . '"]');
        } else {
            echo '<p>' . __('Please provide a product ID.', 'bocs-wordpress') . '</p>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * BOCS product landing page shortcode function
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered product landing page content
     */
    public function bocs_product_landing_func($atts = [])
    {
        // Normalize attribute keys to lowercase
        $atts = array_change_key_case((array) $atts, CASE_LOWER);
        
        // Extract attributes
        $attributes = shortcode_atts(
            array(
                'id' => '', // Product ID
                'template' => 'default', // Template style
            ),
            $atts
        );
        
        // Output buffer
        ob_start();
        
        if (!empty($attributes['id'])) {
            // Include the appropriate template
            $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/product-landing-' . esc_attr($attributes['template']) . '.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                // Fallback to default template if specified template doesn't exist
                $default_template = plugin_dir_path(dirname(__FILE__)) . 'templates/product-landing-default.php';
                
                if (file_exists($default_template)) {
                    include $default_template;
                } else {
                    echo '<p>' . __('Product landing template not found.', 'bocs-wordpress') . '</p>';
                }
            }
        } else {
            echo '<p>' . __('Please provide a product ID.', 'bocs-wordpress') . '</p>';
        }
        
        return ob_get_clean();
    }
}
