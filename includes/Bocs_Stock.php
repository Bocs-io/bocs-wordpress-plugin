<?php
/**
 * The file that defines the stock management related functionality.
 *
 * @link       https://app.bocs.io/
 * @since      1.0.0
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 */

/**
 * The stock management class.
 *
 * This class defines all code necessary to handle stock checking and inventory management.
 *
 * @since      1.0.0
 * @package    Bocs
 * @subpackage Bocs/includes
 * @author     Bocs <your.email@bocs.io>
 */
class Bocs_Stock {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        add_action('wp_ajax_check_product_stock', array($this, 'check_product_stock_ajax'));
        add_action('wp_ajax_nopriv_check_product_stock', array($this, 'check_product_stock_ajax'));
    }

    /**
     * AJAX handler to check if a product is in stock with sufficient quantity
     * 
     * @return void
     */
    public function check_product_stock_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ajax-nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check if required parameters are provided
        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error('Product ID is required');
            return;
        }
        
        $product_id = intval($_POST['product_id']);
        $requested_quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        // Get the product
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
            return;
        }
        
        // Check if product is purchasable
        if (!$product->is_purchasable()) {
            wp_send_json_error('Product is not available for purchase');
            return;
        }
        
        // Check if product is in stock
        if (!$product->is_in_stock()) {
            wp_send_json_error('Product is out of stock');
            return;
        }
        
        // Check stock quantity if stock is managed
        if ($product->managing_stock()) {
            $stock_quantity = $product->get_stock_quantity();
            
            if ($stock_quantity < $requested_quantity) {
                wp_send_json_error(array(
                    'message' => 'Insufficient stock',
                    'available' => $stock_quantity,
                    'requested' => $requested_quantity
                ));
                return;
            }
        }
        
        // All checks passed
        wp_send_json_success(array(
            'product_name' => $product->get_name(),
            'in_stock' => true,
            'managing_stock' => $product->managing_stock(),
            'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : null
        ));
    }

    /**
     * Get stock quantity for a product
     * 
     * @param int $product_id The product ID
     * @return int|null The stock quantity or null if not managing stock
     */
    public function get_stock_quantity($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return null;
        }
        
        if ($product->managing_stock()) {
            return $product->get_stock_quantity();
        }
        
        return null;
    }

    /**
     * Check if there's sufficient stock for the requested quantity
     * 
     * @param int $product_id The product ID
     * @param int $quantity The requested quantity
     * @return bool|array True if sufficient stock, or array with error details
     */
    public function check_stock($product_id, $quantity) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return array(
                'success' => false,
                'message' => 'Product not found'
            );
        }
        
        if (!$product->is_in_stock()) {
            return array(
                'success' => false,
                'message' => 'Product is out of stock'
            );
        }
        
        if ($product->managing_stock()) {
            $stock_quantity = $product->get_stock_quantity();
            
            if ($stock_quantity < $quantity) {
                return array(
                    'success' => false,
                    'message' => 'Insufficient stock',
                    'available' => $stock_quantity,
                    'requested' => $quantity
                );
            }
        }
        
        return array(
            'success' => true,
            'product_name' => $product->get_name(),
            'managing_stock' => $product->managing_stock(),
            'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : null
        );
    }
}

// Initialize the class
new Bocs_Stock();
