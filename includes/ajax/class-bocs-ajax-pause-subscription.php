<?php
/**
 * BOCS AJAX Pause Subscription Handler
 *
 * Handles AJAX requests to pause a subscription
 *
 * @package BOCS
 * @subpackage AJAX
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BOCS AJAX Pause Subscription Handler
 */
class Bocs_Ajax_Pause_Subscription {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_bocs_pause_subscription', array($this, 'pause_subscription'));
        add_action('wp_ajax_nopriv_bocs_pause_subscription', array($this, 'pause_subscription'));
    }

    /**
     * Pause a subscription
     */
    public function pause_subscription() {
        // Log the request for debugging
        if (function_exists('bocs_log')) {
            bocs_log('Pause subscription AJAX request received', 'debug', array(
                'post_data' => $_POST,
                'nonce_exists' => isset($_POST['nonce']),
                'nonce_value' => isset($_POST['nonce']) ? substr($_POST['nonce'], 0, 5) . '...' : 'not set'
            ));
        }

        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs-ajax-nonce')) {
            // Log the nonce verification failure
            if (function_exists('bocs_log')) {
                bocs_log('Pause subscription nonce verification failed', 'error', array(
                    'nonce_exists' => isset($_POST['nonce']),
                    'nonce_value' => isset($_POST['nonce']) ? substr($_POST['nonce'], 0, 5) . '...' : 'not set',
                    'expected_nonce_action' => 'bocs-ajax-nonce'
                ));
            }

            wp_send_json_error(array(
                'message' => 'Invalid security token'
            ));
            return;
        }

        // Check subscription ID
        if (!isset($_POST['subscription_id']) || empty($_POST['subscription_id'])) {
            wp_send_json_error(array(
                'message' => 'Subscription ID is required'
            ));
            return;
        }

        $subscription_id = sanitize_text_field($_POST['subscription_id']);

        // Get options
        $options = get_option('bocs_options', array());
        if (!isset($options['bocs_headers']) || empty($options['bocs_headers'])) {
            wp_send_json_error(array(
                'message' => 'API headers not configured'
            ));
            return;
        }

        // Log the request
        if (function_exists('bocs_log')) {
            bocs_log('Pause subscription AJAX request', 'info', array(
                'subscription_id' => $subscription_id
            ));
        }

        // Make API request
        $helper = new Bocs_Helper();
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id . '/pause';
        $response = $helper->curl_request($url, 'PUT', array(), $options['bocs_headers']);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
            return;
        }

        // Log the response
        if (function_exists('bocs_log')) {
            bocs_log('Pause subscription AJAX response', 'info', array(
                'response' => $response
            ));
        }

        wp_send_json_success(array(
            'message' => 'Subscription paused successfully',
            'data' => $response
        ));
    }
}

// Initialize the class
new Bocs_Ajax_Pause_Subscription();
