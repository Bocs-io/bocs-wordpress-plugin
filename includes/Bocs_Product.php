<?php 

/**
 * Class Bocs_Product
 * 
 * Handles WooCommerce product-related functionality.
 */
class Bocs_Product
{
    /**
     * Retrieves and returns the regular price of a WooCommerce product via AJAX.
     * 
     * This method handles an AJAX request to get a product's regular price.
     * It expects a 'product_id' parameter in the POST request.
     * 
     * @return void Sends JSON response and exits:
     *               - Success: ['price' => string] Product's regular price
     *               - Error: ['message' => string] Error message if product is invalid or not found
     */
    public function get_product_price_callback()
    {
        try {
            // Check if WooCommerce is active
            if (!function_exists('wc_get_product')) {
                wp_send_json_error(['message' => 'WooCommerce is not active']);
                return;
            }
    
            // Verify nonce
            if (!check_ajax_referer('get_product_price', 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid security token']);
                return;
            }
    
            // Get and validate product ID
            $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
            if (empty($product_id)) {
                wp_send_json_error(['message' => 'Product ID is required']);
                return;
            }
    
            // Get product
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error(['message' => "Product not found: {$product_id}"]);
                return;
            }
    
            // Get price
            $price = $product->get_regular_price();
            if (empty($price)) {
                $price = $product->get_price();
            }
    
            // Ensure we have a valid numeric price
            $price = is_numeric($price) ? floatval($price) : 0;
    
            // Log success for debugging
            error_log("Successfully retrieved price for product {$product_id}: {$price}");
    
            wp_send_json_success([
                'price' => $price,
                'product_id' => $product_id
            ]);
    
        } catch (Exception $e) {
            error_log("Error in get_wc_product_price: " . $e->getMessage());
            wp_send_json_error([
                'message' => 'Server error occurred',
                'details' => $e->getMessage()
            ]);
        }
    }
}   