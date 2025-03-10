<?php 

/**
 * Class Bocs_Product
 * 
 * Handles WooCommerce product-related functionality for the BOCS plugin.
 * This class provides methods to interact with WooCommerce products,
 * including price retrieval and product details fetching via AJAX.
 * 
 * @since 1.0.0
 */
class Bocs_Product
{
    /**
     * Retrieves and returns the regular price of a WooCommerce product via AJAX.
     * 
     * This method handles an AJAX request to get a product's regular price.
     * It includes several validation steps:
     * 1. Checks if WooCommerce is active
     * 2. Validates the security nonce
     * 3. Validates the product ID
     * 4. Retrieves and validates the product price
     * 
     * @since 1.0.0
     * @return void Sends JSON response and exits:
     *               - Success: ['price' => float, 'product_id' => string] Product's price and ID
     *               - Error: ['message' => string, 'details' => string] Error message if validation fails
     */
    public function get_product_price_callback()
    {
        try {
            // Check if WooCommerce is active
            if (!function_exists('wc_get_product')) {
                wp_send_json_error(['message' => 'WooCommerce is not active']);
                return;
            }
    
            // Verify security nonce to prevent CSRF attacks
            if (!check_ajax_referer('get_product_price', 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid security token']);
                return;
            }
    
            // Get and sanitize product ID from POST request
            $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
            if (empty($product_id)) {
                wp_send_json_error(['message' => 'Product ID is required']);
                return;
            }
    
            // Attempt to get the product object
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error(['message' => "Product not found: {$product_id}"]);
                return;
            }
    
            // Get product price, falling back to sale price if regular price is empty
            $price = $product->get_regular_price();
            if (empty($price)) {
                $price = $product->get_price();
            }
    
            // Convert price to float or default to 0 if invalid
            $price = is_numeric($price) ? floatval($price) : 0;
    
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

    /**
     * Retrieves detailed information about multiple WooCommerce products via AJAX.
     * 
     * This method handles an AJAX request to get multiple products' details including
     * name and SKU. It processes an array of product IDs and returns consolidated
     * information for all valid products.
     * 
     * @since 1.0.0
     * @return void Sends JSON response and exits:
     *               - Success: ['success' => true, 'data' => array] Array of product details
     *               - Error: ['success' => false] If nonce verification fails
     */
    public function get_product_details_ajax() {
        try {
            // Verify security nonce
            if (!check_ajax_referer('get_product_details', 'nonce', false)) {
                error_log('BOCS: Nonce verification failed in get_product_details_ajax');
                wp_send_json_error('Security check failed');
                return;
            }
            
            // Get and cast product IDs to array
            $product_ids = isset($_POST['product_ids']) ? (array) $_POST['product_ids'] : array();
            
            if (empty($product_ids)) {
                error_log('BOCS: No product IDs provided in get_product_details_ajax');
                wp_send_json_error('No product IDs provided');
                return;
            }
            
            $products = array();
            
            // Iterate through product IDs and collect details
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $products[$product_id] = array(
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku()
                    );
                } else {
                    error_log(sprintf('BOCS: Product not found for ID %s in get_product_details_ajax', $product_id));
                }
            }
            
            if (empty($products)) {
                error_log('BOCS: No valid products found in get_product_details_ajax');
                wp_send_json_error('No valid products found');
                return;
            }
            
            wp_send_json_success($products);
            
        } catch (Exception $e) {
            error_log('BOCS: Exception in get_product_details_ajax: ' . $e->getMessage());
            wp_send_json_error('Internal server error');
        }
    }
}   