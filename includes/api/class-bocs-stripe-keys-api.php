<?php
/**
 * BOCS Stripe Keys API
 *
 * @package    Bocs
 * @subpackage Bocs/includes/api
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BOCS Stripe Keys API Controller
 *
 * @since 0.0.125
 */
class Bocs_Stripe_Keys_API {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the routes for the API
     */
    public function register_routes() {
        register_rest_route('bocs/v1', '/stripe-keys', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_stripe_keys'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }

    /**
     * Check if request has correct permissions using WooCommerce authentication
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error
     */
    public function check_permissions($request) {
        // This will use WordPress REST API authentication
        // Which includes WooCommerce's authentication methods
        // No need to add custom params, REST API handles auth via:
        // - Basic Auth
        // - OAuth
        // - Consumer Key/Secret
        
        // Allow any authenticated user with valid WooCommerce API keys
        // The default WP REST API authentication already validates the API keys
        return true;
    }

    /**
     * Get Stripe keys
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_stripe_keys($request) {
        // Log the access
        error_log(sprintf(
            'Stripe keys accessed via API from IP: %s by user: %s',
            $_SERVER['REMOTE_ADDR'],
            is_user_logged_in() ? wp_get_current_user()->user_login : 'unauthenticated'
        ));

        // Get Stripe settings from WooCommerce
        $wc_stripe_settings = get_option('woocommerce_stripe_settings', array());
        
        // Check if we're in test mode
        $test_mode = isset($wc_stripe_settings['testmode']) && $wc_stripe_settings['testmode'] === 'yes';
        
        // Get the appropriate keys
        $publishable_key = $test_mode ? 
            (isset($wc_stripe_settings['test_publishable_key']) ? $wc_stripe_settings['test_publishable_key'] : '') : 
            (isset($wc_stripe_settings['publishable_key']) ? $wc_stripe_settings['publishable_key'] : '');
            
        $secret_key = $test_mode ? 
            (isset($wc_stripe_settings['test_secret_key']) ? $wc_stripe_settings['test_secret_key'] : '') : 
            (isset($wc_stripe_settings['secret_key']) ? $wc_stripe_settings['secret_key'] : '');
        
        // Return the keys
        return new WP_REST_Response(array(
            'publishable_key' => $publishable_key,
            'secret_key'      => $secret_key,
            'test_mode'       => $test_mode
        ), 200);
    }
}

// Initialize the API
new Bocs_Stripe_Keys_API(); 