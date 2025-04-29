<?php
/**
 * Bocs Account Handler Class
 *
 * Handles all account-related functionality for the Bocs plugin.
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 * @since      0.0.118
 */

class Bocs_Account
{
    /**
     * Class instance.
     *
     * @var Bocs_Account
     */
    private static $instance = null;

    /**
     * The API base URL.
     *
     * @var string
     */
    private static $api_base_url = '';

    /**
     * The WP endpoint name.
     *
     * @var string
     */
    private static $endpoint = '';

    /**
     * Logger instance
     * 
     * @var WC_Logger
     */
    private static $logger = null;

    /**
     * Error messages array
     *
     * @var array
     */
    public static $error_messages = [];

    /** @var array API headers for Bocs authentication */
    private $headers;

    /** @var array Storage for BOCS products lookup */
    private $bocs_products = array();

    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        if (! empty($options['bocs_headers']['organization']) && ! empty($options['bocs_headers']['store']) && ! empty($options['bocs_headers']['authorization'])) {
            $this->headers = [
                'Organization' => $options['bocs_headers']['organization'] ?? '',
                'Store' => $options['bocs_headers']['store'] ?? '',
                'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                'Content-Type' => 'application/json'
            ];
        }

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Helper.php';
        
        // Add AJAX handlers
        add_action('wp_ajax_bocs_get_payment_methods', array($this, 'ajax_get_payment_methods'));
        add_action('wp_ajax_bocs_update_payment_method', array($this, 'ajax_update_payment_method'));
        add_action('wp_ajax_bocs_get_stripe_setup', array($this, 'ajax_get_stripe_setup'));
        add_action('wp_ajax_bocs_get_user_billing_details', array($this, 'ajax_get_user_billing_details'));
        add_action('wp_ajax_bocs_update_subscription', array($this, 'ajax_update_subscription'));
        add_action('wp_ajax_bocs_get_box_products', array($this, 'ajax_get_box_products'));
        add_action('wp_ajax_bocs_update_subscription_products', array($this, 'ajax_update_subscription_products'));
        add_action('wp_ajax_bocs_get_subscription_line_items', array($this, 'ajax_get_subscription_line_items'));
        add_action('wp_ajax_bocs_get_subscription_line_items_data', array($this, 'ajax_get_subscription_line_items_data'));
        add_action('wp_ajax_bocs_get_missing_tokens', array($this, 'ajax_get_missing_tokens'));
        
        // Add init action to handle payment method redirect
        add_action('wp', array($this, 'handle_payment_setup_redirect'));
    }

    /**
     * Add Bocs subscriptions menu item to My Account menu
     *
     * @param array $items Existing menu items
     * @return array Modified menu items
     */
    public function bocs_account_menu_item($items)
    {
        // Remove the original menu item
        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);
        
        // Insert Bocs Subscriptions after Orders
        $new_items = array();
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if ($key === 'orders') {
                $new_items['bocs-subscriptions'] = __('Bocs Subscriptions', 'bocs-wordpress');
            }
        }
        
        // Add logout back at the end
        if ($logout) {
            $new_items['customer-logout'] = $logout;
        }
        
        return $new_items;
    }

    /**
     * Register the Bocs subscriptions endpoint
     */
    public function register_bocs_account_endpoint()
    {
        add_rewrite_endpoint('bocs-subscriptions', EP_ROOT | EP_PAGES);
    }

    /**
     * Display content for the My Account Bocs Subscriptions page.
     *
     * Retrieves and displays user's Bocs subscriptions by searching through multiple identifiers:
     * 1. Bocs customer ID (if exists)
     * 2. WordPress user ID (as external source ID)
     * 3. User's email address
     *
     * @since      0.0.118
     * @access     public
     *
     * @global     WP_User $current_user WordPress current user object.
     *
     * @return     void
     */
    public function bocs_endpoint_content()
    {
        $user_id = get_current_user_id();
        $bocs_customer_id = '';
        $data = false;
        $url = '';

        // Start logging
        $log_data = [
            'user_id' => $user_id,
            'time' => current_time('mysql'),
            'page' => 'bocs-subscriptions'
        ];
        
        if (class_exists('Bocs_Log_Handler')) {
            $logger = new Bocs_Log_Handler();
            $logger->insert_log('debug', '[Subscriptions Page] Started loading subscriptions page', $log_data);
        } else {
            error_log('BOCS DEBUG: Started loading subscriptions page - ' . json_encode($log_data));
        }

        // Only proceed if we have a logged-in user
        if (! empty($user_id)) {
            
            $bocs_customer_id = get_user_meta($user_id, 'bocs_user_id', true);
            
            if (class_exists('Bocs_Log_Handler')) {
                $logger->insert_log('debug', '[Subscriptions Page] User details', [
                    'user_id' => $user_id,
                    'bocs_customer_id' => $bocs_customer_id
                ]);
            }
            
            $current_user = wp_get_current_user();
            $url = BOCS_API_URL . 'subscriptions';
            
            if (class_exists('Bocs_Log_Handler')) {
                $logger->insert_log('debug', '[Subscriptions Page] API URL', [
                    'url' => $url,
                    'api_base' => BOCS_API_URL
                ]);
            }

            // Step 1: Start with customer.externalSourceId
            $query = 'customer.externalSourceId:' . urlencode($user_id);
            $url .= '?query=' . urlencode($query);
            
            if (class_exists('Bocs_Log_Handler')) {
                $logger->insert_log('debug', '[Subscriptions Page] Searching by customer.externalSourceId', [
                    'query' => $query,
                    'full_url' => $url,
                    'user_id' => $user_id
                ]);
            }
            
            $helper = new Bocs_Helper();
            $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
            
            if (is_wp_error($subscriptions)) {
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('error', '[Subscriptions Page] WP Error when fetching by externalSourceId', [
                        'error' => $subscriptions->get_error_message()
                    ]);
                }
                // Continue to next method without returning
            } 
            
            if (class_exists('Bocs_Log_Handler')) {
                $logger->insert_log('debug', '[Subscriptions Page] API Response for customer.externalSourceId', [
                    'has_data' => isset($subscriptions['data']),
                    'has_data_data' => isset($subscriptions['data']['data']),
                    'data_count' => isset($subscriptions['data']['data']) ? count($subscriptions['data']['data']) : 0,
                    'response_keys' => array_keys($subscriptions)
                ]);
            }
            
            if (isset($subscriptions['data']['data']) && !empty($subscriptions['data']['data'])) {
                // Found subscriptions by customer.externalSourceId
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('debug', '[Subscriptions Page] Found subscriptions by customer.externalSourceId', [
                        'count' => count($subscriptions['data']['data'])
                    ]);
                }
            } else {
                // Step 2: Try billing.email if externalSourceId didn't work
                if ($current_user->exists()) {
                    $query = 'billing.email:' . urlencode($current_user->user_email);
                    $url = BOCS_API_URL . 'subscriptions?query=' . urlencode($query);
                    
                    if (class_exists('Bocs_Log_Handler')) {
                        $logger->insert_log('debug', '[Subscriptions Page] Searching by billing.email', [
                            'query' => $query,
                            'full_url' => $url,
                            'email' => $current_user->user_email
                        ]);
                    }
                    
                    $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                    
                    if (is_wp_error($subscriptions)) {
                        if (class_exists('Bocs_Log_Handler')) {
                            $logger->insert_log('error', '[Subscriptions Page] WP Error when fetching by email', [
                                'error' => $subscriptions->get_error_message()
                            ]);
                        }
                        return;
                    }
                    
                    if (class_exists('Bocs_Log_Handler')) {
                        $logger->insert_log('debug', '[Subscriptions Page] API Response for billing.email', [
                            'has_data' => isset($subscriptions['data']),
                            'has_data_data' => isset($subscriptions['data']['data']),
                            'data_count' => isset($subscriptions['data']['data']) ? count($subscriptions['data']['data']) : 0
                        ]);
                    }
                    
                    if (isset($subscriptions['data']['data']) && !empty($subscriptions['data']['data'])) {
                        // Found subscriptions by billing.email
                        if (class_exists('Bocs_Log_Handler')) {
                            $logger->insert_log('debug', '[Subscriptions Page] Found subscriptions by billing.email', [
                                'count' => count($subscriptions['data']['data'])
                            ]);
                        }
                    }
                }
            }

            // Add fields parameter to get only needed data
            //$url .= '&fields=' . urlencode('id,subscriptionStatus,nextPaymentDateGmt,startDateGmt,total,currency,billingPeriod,frequency,externalSourceParentOrderId,orderKey,paymentMethodTitle,lineItems');
            
            // Make sure $subscriptions is not a WP_Error before proceeding
            if (is_wp_error($subscriptions)) {
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('error', '[Subscriptions Page] Cannot proceed with WP_Error', [
                        'error_message' => $subscriptions->get_error_message()
                    ]);
                }
                // Set an empty subscriptions array to prevent errors
                $subscriptions = ['data' => ['data' => []]];
            }
            
            if (isset($subscriptions['data']['data'])) {
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('debug', '[Subscriptions Page] Final subscription data', [
                        'count' => count($subscriptions['data']['data']),
                        'first_subscription_id' => !empty($subscriptions['data']['data']) ? $subscriptions['data']['data'][0]['id'] : 'none'
                    ]);
                }
            } else {
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('debug', '[Subscriptions Page] No subscriptions found', [
                        'response_keys' => isset($subscriptions) ? array_keys($subscriptions) : 'no response',
                        'data_keys' => isset($subscriptions['data']) ? array_keys($subscriptions['data']) : 'no data'
                    ]);
                }
            }
            
            // Format subscriptions data for template
            $subscriptions_formatted = array();
            if (isset($subscriptions['data']['data']) && is_array($subscriptions['data']['data']) && !empty($subscriptions['data']['data'])) {
                foreach ($subscriptions['data']['data'] as $subscription) {
                    if (!isset($subscription['id'])) {
                        continue; // Skip invalid subscription entries
                    }
                    
                    // Format a single subscription
                    $formatted = array(
                        'id' => $subscription['id'],
                        'status' => isset($subscription['subscriptionStatus']) ? $subscription['subscriptionStatus'] : 'active',
                        'price' => isset($subscription['total']) ? $subscription['total'] : 0,
                        'frequency' => isset($subscription['billingPeriod']) ? ucfirst($subscription['billingPeriod']) : '',
                        'billing_date' => isset($subscription['nextPaymentDateGmt']) ? date_i18n(get_option('date_format'), strtotime($subscription['nextPaymentDateGmt'])) : '',
                        'change_by_date' => isset($subscription['nextPaymentDateGmt']) ? date_i18n(get_option('date_format'), strtotime($subscription['nextPaymentDateGmt'] . ' -3 days')) : '',
                        'externalSourceParentOrderId' => isset($subscription['externalSourceParentOrderId']) ? $subscription['externalSourceParentOrderId'] : '',
                        'next_payment_date' => isset($subscription['nextPaymentDateGmt']) ? date_i18n(get_option('date_format'), strtotime($subscription['nextPaymentDateGmt'])) : '',
                        'next_delivery_date' => isset($subscription['nextPaymentDateGmt']) ? date_i18n(get_option('date_format'), strtotime($subscription['nextPaymentDateGmt'] . ' +3 days')) : '',
                    );
                    
                    // Check if there are any pending orders for this subscription
                    if (!empty($subscription['id']) && $formatted['status'] != 'paused') {
                        $pending_orders = wc_get_orders(array(
                            'status' => 'pending',
                            'limit' => 1,
                            'meta_key' => '__bocs_subscription_id',
                            'meta_value' => $subscription['id'],
                        ));
                        
                        // If pending orders exist, set status to "upcoming"
                        if (!empty($pending_orders) ) {
                            $formatted['status'] = 'upcoming';
                        }
                    }
                    
                    // Process BOCS info and name
                    if (isset($subscription['bocs'])) {
                        $formatted['bocs'] = $subscription['bocs'];
                        
                        // If BOCS name is empty but we have the ID, try to fetch it
                        if (empty($subscription['bocs']['name']) && !empty($subscription['bocs']['id'])) {
                            if (class_exists('Bocs_Log_Handler')) {
                                $logger->insert_log('debug', '[Subscriptions Page] Fetching BOCS name for empty value', [
                                    'bocs_id' => $subscription['bocs']['id']
                                ]);
                            }
                            
                            $bocs_id = $subscription['bocs']['id'];
                            
                            // Use static cache to prevent duplicate requests
                            static $bocs_cache = [];
                            
                            if (isset($bocs_cache[$bocs_id])) {
                                // Use cached data
                                $bocs_details = $bocs_cache[$bocs_id];
                                if (class_exists('Bocs_Log_Handler')) {
                                    $logger->insert_log('debug', '[Subscriptions Page] Using cached BOCS details', [
                                        'bocs_id' => $bocs_id
                                    ]);
                                }
                            } else {
                                // Make API request and cache the result
                                $url = BOCS_API_URL . 'bocs/' . $bocs_id;
                                $bocs_details = $helper->curl_request($url, 'GET', [], $this->headers);
                                
                                // Cache the result
                                if (!is_wp_error($bocs_details)) {
                                    $bocs_cache[$bocs_id] = $bocs_details;
                                }
                            }
                            
                            // Check for WP_Error before attempting to access as array
                            if (!is_wp_error($bocs_details) && isset($bocs_details['data']['name']) && !empty($bocs_details['data']['name'])) {
                                $formatted['bocs']['name'] = $bocs_details['data']['name'];
                            } else if (is_wp_error($bocs_details)) {
                                // Log the error
                                if (class_exists('Bocs_Log_Handler')) {
                                    $logger->insert_log('error', '[Subscriptions Page] Error fetching BOCS details', [
                                        'bocs_id' => $bocs_id,
                                        'error_message' => $bocs_details->get_error_message()
                                    ]);
                                } else {
                                    error_log('BOCS Error: Failed to get BOCS details - ' . $bocs_details->get_error_message());
                                }
                            }
                        }
                    } else {
                        $formatted['bocs'] = array(
                            'name' => '',
                            'id' => '',
                        );
                    }
                    
                    // Add frequency formatted
                    if (isset($subscription['frequency'])) {
                        $frequency_value = isset($subscription['frequency']['frequency']) ? $subscription['frequency']['frequency'] : 1;
                        $time_unit = isset($subscription['frequency']['timeUnit']) ? $subscription['frequency']['timeUnit'] : 'month';
                        
                        // For singular values, handle grammar correctly
                        if ($frequency_value == 1) {
                            // Use singular form for single units
                            $formatted_time_unit = rtrim($time_unit, 's');
                            $formatted['frequency_formatted'] = sprintf(
                                __('every %s', 'bocs-wordpress'),
                                $formatted_time_unit
                            );
                        } else {
                            // Use plural form for multiple units, ensure it ends with 's'
                            $formatted_time_unit = rtrim($time_unit, 's') . 's';
                            $formatted['frequency_formatted'] = sprintf(
                                __('every %d %s', 'bocs-wordpress'),
                                $frequency_value,
                                $formatted_time_unit
                            );
                        }
                    } else if (isset($subscription['billingPeriod'])) {
                        // Fallback to old method if frequency object is not available
                        $frequency_value = isset($subscription['frequency']) ? $subscription['frequency'] : 1;
                        $period = $subscription['billingPeriod'];
                        
                        if ($frequency_value == 1) {
                            // For singular values, display "Every month" instead of "Every 1 month"
                            $formatted['frequency_formatted'] = sprintf(
                                __('Every %s', 'bocs-wordpress'),
                                rtrim($period, 's')
                            );
                        } else {
                            $formatted['frequency_formatted'] = sprintf(
                                __('Every %d %s', 'bocs-wordpress'),
                                $frequency_value,
                                $frequency_value > 1 ? $period : rtrim($period, 's')
                            );
                        }
                    }
                    
                    // Add delivery address
                    if (isset($subscription['shipping']) && is_array($subscription['shipping'])) {
                        $address_parts = array(
                            isset($subscription['shipping']['firstName']) && isset($subscription['shipping']['lastName']) ? 
                                $subscription['shipping']['firstName'] . ' ' . $subscription['shipping']['lastName'] : '',
                            isset($subscription['shipping']['company']) ? $subscription['shipping']['company'] : '',
                            isset($subscription['shipping']['address1']) ? $subscription['shipping']['address1'] : '',
                            isset($subscription['shipping']['address2']) ? $subscription['shipping']['address2'] : '',
                            isset($subscription['shipping']['city']) ? $subscription['shipping']['city'] : '',
                            isset($subscription['shipping']['state']) ? $subscription['shipping']['state'] : '',
                            isset($subscription['shipping']['postcode']) ? $subscription['shipping']['postcode'] : '',
                            isset($subscription['shipping']['country']) ? $subscription['shipping']['country'] : ''
                        );
                        $formatted['delivery_address'] = array(
                            'formatted' => implode(', ', array_filter($address_parts))
                        );
                        
                        // Add full shipping address data - maintain original data structure
                        $formatted['shipping'] = $subscription['shipping'];
                    } else {
                        $formatted['delivery_address'] = array(
                            'formatted' => __('No address provided', 'bocs-wordpress')
                        );
                        $formatted['shipping'] = array();
                    }
                    
                    // Add billing address
                    if (isset($subscription['billing']) && is_array($subscription['billing'])) {
                        // Preserve the complete billing data
                        $formatted['billing'] = $subscription['billing'];
                    } else {
                        $formatted['billing'] = array();
                    }
                    
                    // Add payment method
                    if (isset($subscription['paymentMethodTitle'])) {
                        $formatted['payment_method'] = array(
                            'formatted' => $subscription['paymentMethodTitle']
                        );
                        if (isset($subscription['paymentMethodId']) && preg_match('/\d{4}$/', $subscription['paymentMethodId'], $matches)) {
                            $formatted['payment_method']['formatted'] .= ' | **** ' . $matches[0];
                        }
                    } else {
                        $formatted['payment_method'] = array(
                            'formatted' => __('Unknown payment method', 'bocs-wordpress')
                        );
                    }
                    
                    // Add line items
                    $formatted['items'] = array();
                    if (isset($subscription['lineItems']) && is_array($subscription['lineItems'])) {
                        foreach ($subscription['lineItems'] as $item) {
                            $formatted['items'][] = array(
                                'name' => isset($item['name']) ? $item['name'] : __('Unknown product', 'bocs-wordpress'),
                                'quantity' => isset($item['quantity']) ? $item['quantity'] : 1,
                                'price' => isset($item['price']) ? $item['price'] : 0,
                                'total' => isset($item['total']) ? $item['total'] : 0
                            );
                        }
                    }
                    
                    $subscriptions_formatted[] = $formatted;
                }
                
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('debug', '[Subscriptions Page] Formatted subscription data', [
                        'count' => count($subscriptions_formatted)
                    ]);
                }
            } elseif (class_exists('Bocs_Log_Handler')) {
                $logger->insert_log('warning', '[Subscriptions Page] No valid subscription data to format', [
                    'has_data' => isset($subscriptions['data']),
                    'has_data_data' => isset($subscriptions['data']['data']),
                    'is_array' => isset($subscriptions['data']['data']) && is_array($subscriptions['data']['data']),
                    'is_empty' => isset($subscriptions['data']['data']) && empty($subscriptions['data']['data'])
                ]);
            }

            // Define paths for both templates
            $legacy_template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs_subscriptions_account.php';
            $new_template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/myaccount/subscription-list.php';
            
            if (class_exists('Bocs_Log_Handler')) {
                $logger->insert_log('debug', '[Subscriptions Page] Template paths', [
                    'legacy_template_exists' => file_exists($legacy_template_path),
                    'new_template_exists' => file_exists($new_template_path),
                    'legacy_path' => $legacy_template_path,
                    'new_path' => $new_template_path
                ]);
            }
            
            // Set necessary variables for template
            $options = get_option('bocs_plugin_options');
            
            // Use the new template if it exists, otherwise fall back to legacy template
            if (file_exists($new_template_path)) {
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('debug', '[Subscriptions Page] Including new template', [
                        'template_path' => $new_template_path
                    ]);
                }
                include $new_template_path;
            } elseif (file_exists($legacy_template_path)) {
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('debug', '[Subscriptions Page] Including legacy template', [
                        'template_path' => $legacy_template_path
                    ]);
                }
                include $legacy_template_path;
            } else {
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('error', '[Subscriptions Page] No template found', [
                        'new_template_path' => $new_template_path,
                        'legacy_template_path' => $legacy_template_path
                    ]);
                }
                echo esc_html__('Subscription template not found.', 'bocs-wordpress');
            }
        } else {
            if (class_exists('Bocs_Log_Handler')) {
                $logger->insert_log('debug', '[Subscriptions Page] No logged in user', [
                    'time' => current_time('mysql')
                ]);
            }
        }
    }

    /**
     * Register the Bocs subscription view endpoint
     */
    public function register_bocs_view_subscription_endpoint()
    {
        add_rewrite_endpoint('bocs-view-subscription', EP_PAGES);
    }

    /**
     * Register the Bocs update box endpoint
     */
    public function register_bocs_update_box_endpoint()
    {
        add_rewrite_endpoint('bocs-update-box', EP_PAGES);
    }

    /**
     * Register the Bocs subscription edit details endpoint
     */
    public function register_bocs_edit_details_endpoint()
    {
        add_rewrite_endpoint('bocs-edit-details', EP_PAGES);
        
        // Force flush rewrite rules on this specific endpoint
        $this->maybe_flush_rewrite_rules();
    }

    /**
     * Flush rewrite rules if needed
     */
    private function maybe_flush_rewrite_rules() 
    {
        // Only flush once per session
        if (!get_transient('bocs_flushed_rewrite_rules')) {
            flush_rewrite_rules(true);
            set_transient('bocs_flushed_rewrite_rules', 1, HOUR_IN_SECONDS);
        }
    }

    /**
     * Register the Bocs switch bocs endpoint
     */
    public function register_bocs_switch_bocs_endpoint()
    {
        add_rewrite_endpoint('bocs-switch-bocs', EP_PAGES);
    }

    /**
     * Display content for the subscription view page
     */
    public function bocs_view_subscription_endpoint_content()
    {
        global $wp;

        $bocs_subscription_id = isset($wp->query_vars['bocs-view-subscription']) 
            ? sanitize_text_field($wp->query_vars['bocs-view-subscription']) 
            : '';

        if ($bocs_subscription_id) {
            $url = BOCS_API_URL . 'subscriptions/' . $bocs_subscription_id;
            $helper = new Bocs_Helper();
            $subscription = $helper->curl_request($url, 'GET', [], $this->headers);

            // Get related orders
            $url = BOCS_API_URL . 'orders?query=subscriptionId:' . $bocs_subscription_id;
            $related_orders = $helper->curl_request($url, 'GET', [], $this->headers);
            
            if (isset($related_orders['data'])) {
                if ($related_orders['data'] == 'Internal server error.') {
                    $related_orders['data'] = [];
                }
            } else {
                $related_orders['data'] = [];
            }

            if (! isset($related_orders['data']) || empty($related_orders['data'])) {
                $args = array(
                    'limit' => -1,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'meta_key' => '__bocs_subscription_id',
                    'meta_value' => $bocs_subscription_id,
                    'meta_compare' => '=',
                    'return' => 'ids'
                );

                $query = new WC_Order_Query($args);
                $related_orders['order_ids'] = $query->get_orders();
            }

            $template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs_view_subscription.php';

            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<p>' . esc_html__('Invalid subscription ID.', 'bocs-wordpress') . '</p>';
            }
        } else {
            echo '<p>' . esc_html__('No subscription ID provided.', 'bocs-wordpress') . '</p>';
        }
    }

    /**
     * Display content for the update box page
     */
    public function bocs_update_box_endpoint_content()
    {
        global $wp;

        $bocs_subscription_id = isset($wp->query_vars['bocs-update-box']) 
            ? sanitize_text_field($wp->query_vars['bocs-update-box']) 
            : '';

        if (empty($bocs_subscription_id)) {
            echo '<div class="woocommerce-error">' . esc_html__('Invalid subscription ID.', 'bocs-wordpress') . '</div>';
            return;
        }

        $template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs_update_box.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo esc_html__('Update box template not found.', 'bocs-wordpress');
        }
    }

    /**
     * Display content for the edit details page
     */
    public function bocs_edit_details_endpoint_content()
    {
        global $wp;

        $bocs_subscription_id = isset($wp->query_vars['bocs-edit-details']) 
            ? sanitize_text_field($wp->query_vars['bocs-edit-details']) 
            : '';

        if (empty($bocs_subscription_id)) {
            echo '<div class="woocommerce-error">' . esc_html__('Invalid subscription ID.', 'bocs-wordpress') . '</div>';
            return;
        }

        // Use the new template from myaccount directory
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/myaccount/edit-details.php';

        // Fallback to old template if new one doesn't exist
        if (!file_exists($template_path)) {
            $template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs_edit_details.php';
        }

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo esc_html__('Edit details template not found.', 'bocs-wordpress');
        }
    }

    /**
     * Display content for the switch bocs page
     */
    public function bocs_switch_bocs_endpoint_content()
    {
        global $wp;

        $bocs_subscription_id = isset($wp->query_vars['bocs-switch-bocs']) 
            ? sanitize_text_field($wp->query_vars['bocs-switch-bocs']) 
            : '';

        if (empty($bocs_subscription_id)) {
            echo '<div class="woocommerce-error">' . esc_html__('Invalid subscription ID.', 'bocs-wordpress') . '</div>';
            return;
        }

        $template_path = $this->get_template_path('myaccount/switch-bocs.php');

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo esc_html__('Switch bocs template not found.', 'bocs-wordpress');
        }
    }

    /**
     * Get the path to a template file
     * 
     * @param string $template_name The name of the template file
     * @return string The full path to the template file
     */
    public function get_template_path($template_name) {
        // Use the templates directory in the plugin
        return plugin_dir_path(dirname(__FILE__)) . 'templates/' . $template_name;
    }

    /**
     * Check if the current cart contains a Bocs subscription.
     *
     * @since 0.0.97
     * @access private
     * @return bool True if cart contains a Bocs subscription
     */
    private function _cart_contains_bocs_subscription() {
        $bocs_id = '';
        
        if (isset(WC()->session)) {
            $bocs_id = WC()->session->get('bocs');
        }
        
        if (empty($bocs_id) && isset($_COOKIE['__bocs_id'])) {
            $bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
        }

        return !empty($bocs_id);
    }
    
    /**
     * Enable registration requirement when purchasing Bocs products
     *
     * @since 0.0.97
     * @param bool $account_required Current account requirement status
     * @return bool Modified account requirement status
     */
    public function require_registration_during_checkout($account_required) {
        return $this->_cart_contains_bocs_subscription() && !is_user_logged_in() 
            ? true 
            : $account_required;
    }

    /**
     * Force registration during checkout for Bocs subscriptions
     *
     * @since 0.0.97
     * @param array $data Posted checkout form data
     * @return array Modified checkout form data
     */
    public function force_registration_during_checkout($data) {
        if ($this->_cart_contains_bocs_subscription() && !is_user_logged_in()) {
            $data['createaccount'] = 1;
        }
        return $data;
    }

    /**
     * Enable registration for carts containing Bocs products
     *
     * @since 3.1.0
     * @param bool $registration_enabled Current registration status
     * @return bool Modified registration status
     */
    public function maybe_enable_registration($registration_enabled) {
        if ($registration_enabled) {
            return true;
        }

        return $this->_cart_contains_bocs_subscription() ? true : $registration_enabled;
    }

    /**
     * Get pending order URL for a subscription
     * 
     * Finds any pending WooCommerce orders associated with a BOCS subscription
     * and returns the payment URL for the first one found.
     *
     * @param string $subscription_id The BOCS subscription ID
     * @return string|false The payment URL or false if no pending order found
     */
    public function get_pending_order_url($subscription_id) {
        if (!function_exists('wc_get_orders')) {
            return false;
        }
        
        // Find orders with this subscription ID in meta
        $orders = wc_get_orders(array(
            'status' => 'pending',
            'limit' => 1,
            'meta_key' => '__bocs_subscription_id',
            'meta_value' => $subscription_id
        ));
        
        if (empty($orders)) {
            return false;
        }
        
        // Get the first order
        $order = reset($orders);
        
        // Generate the payment URL
        return $order->get_checkout_payment_url();
    }

    /**
     * AJAX handler for getting available payment methods
     */
    public function ajax_get_payment_methods() {
        // Check nonce for security
        check_ajax_referer('bocs-ajax-nonce', 'nonce');
        
        // Get the current user ID
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }
        
        // Get the subscription ID from the request
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            wp_send_json_error(array('message' => 'Subscription ID not provided'));
            return;
        }
        
        // Force cache refresh for tokens
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wc_payment_tokens%'");
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Query database directly for all tokens - first do a broad search
        $all_tokens = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens 
                WHERE user_id = %d ORDER BY token_id DESC LIMIT 20",
                $user_id
            )
        );
        
        $this->log('Direct database query found ' . count($all_tokens) . ' tokens for user ' . $user_id);
        
        // Log all found tokens to understand what's in the database
        foreach ($all_tokens as $token) {
            $this->log("Token ID: {$token->token_id}, Gateway: {$token->gateway_id}, Token: {$token->token}");
        }
        
        // Now filter for Stripe tokens
        $db_tokens = [];
        foreach ($all_tokens as $token) {
            if ($token->gateway_id == 'stripe') {
                $db_tokens[] = $token;
            }
        }
        
        $this->log('After filtering, found ' . count($db_tokens) . ' Stripe tokens for user ' . $user_id);
        
        // Check for specific tokens we're missing
        $recent_token_ids = [47, 48, 49, 50, 51, 52]; // Specific IDs we're looking for
        foreach ($recent_token_ids as $missing_id) {
            $found = false;
            foreach ($db_tokens as $token) {
                if ($token->token_id == $missing_id) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // Check if this token exists at all
                $specific_token = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token_id = %d",
                        $missing_id
                    )
                );
                
                if ($specific_token) {
                    $this->log("Found token {$missing_id} but it's not associated with current user. User ID: {$specific_token->user_id}, Gateway: {$specific_token->gateway_id}");
                    
                    // If this token is a Stripe token but just has wrong user ID, add it
                    if ($specific_token->gateway_id == 'stripe') {
                        $this->log("Adding token {$missing_id} to results even though user ID is {$specific_token->user_id}");
                        $db_tokens[] = $specific_token;
                    }
                } else {
                    $this->log("Token {$missing_id} not found in database at all");
                }
            }
        }
        
        // Log and create a map of token IDs to tokens
        $token_map = [];
        foreach ($db_tokens as $db_token) {
            $this->log('DB Token ID: ' . $db_token->token_id . ', Token: ' . $db_token->token);
            $token_map[$db_token->token_id] = $db_token;
        }
        
        // Get token metadata from database
        $token_meta = [];
        if (!empty($db_tokens)) {
            $token_ids = array_column($db_tokens, 'token_id');
            $placeholders = implode(',', array_fill(0, count($token_ids), '%d'));
            
            // Build the query with the correct number of placeholders
            $query = "SELECT payment_token_id, meta_key, meta_value 
                     FROM {$wpdb->prefix}woocommerce_payment_tokenmeta 
                     WHERE payment_token_id IN ($placeholders)";
            
            // Prepare the query with token_ids
            $prepared_query = $wpdb->prepare($query, ...$token_ids);
            
            // Execute query
            $meta_results = $wpdb->get_results($prepared_query);
            
            $this->log('Found ' . count($meta_results) . ' token metadata entries');
            
            // Group metadata by token ID
            foreach ($meta_results as $meta) {
                if (!isset($token_meta[$meta->payment_token_id])) {
                    $token_meta[$meta->payment_token_id] = [];
                }
                $token_meta[$meta->payment_token_id][$meta->meta_key] = $meta->meta_value;
            }
        }
        
        // Look up tokens using WC_Payment_Tokens (standard method)
        $tokens = WC_Payment_Tokens::get_customer_tokens($user_id);
        $this->log('Payment methods request: Found ' . count($tokens) . ' total payment tokens for user ' . $user_id);
        
        // Get tokens specific to Stripe
        $stripe_tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
        $this->log('Payment methods request: Found ' . count($stripe_tokens) . ' Stripe payment tokens via API');
        
        // Log details of all tokens for debugging
        foreach ($stripe_tokens as $token) {
            $this->log('Token ID: ' . $token->get_id() . ', Type: ' . $token->get_card_type() . ', Last4: ' . $token->get_last4());
        }
        
        // Get saved payment methods using the WooCommerce function
        $saved_methods = wc_get_customer_saved_methods_list($user_id);
        $payment_methods = array();
        
        // Check if we have credit card methods
        if (isset($saved_methods['cc']) && is_array($saved_methods['cc'])) {
            $payment_methods = $saved_methods['cc'];
            $this->log('Found ' . count($payment_methods) . ' credit card payment methods via wc_get_customer_saved_methods_list');
        } else {
            $this->log('No credit card payment methods found in saved_methods');
        }
        
        // Create a tracking map of payment methods already in the list
        $tracked_payment_methods = [];
        $normalized_cards = []; // Track by last4 and card type
        
        // First pass - build tracking maps
        foreach ($payment_methods as $key => $pm) {
            if (isset($pm['token_id'])) {
                $tracked_payment_methods[$pm['token_id']] = $key;
            }
            if (isset($pm['method']['id'])) {
                $tracked_payment_methods[$pm['method']['id']] = $key;
            }
            
            // Track by card details (last4 + brand) to catch case-insensitive duplicates
            if (isset($pm['method']['last4']) && isset($pm['method']['brand'])) {
                $signature = strtolower($pm['method']['last4'] . '_' . $pm['method']['brand']);
                $normalized_cards[$signature] = $key;
            }
        }
        
        // Remove any case-sensitive duplicates (keep first occurrence)
        $filtered_payment_methods = [];
        foreach ($payment_methods as $key => $pm) {
            if (isset($pm['method']['last4']) && isset($pm['method']['brand'])) {
                $signature = strtolower($pm['method']['last4'] . '_' . $pm['method']['brand']);
                
                // If we haven't seen this card before, or this is the first occurrence we tracked
                if (!isset($normalized_cards[$signature]) || $normalized_cards[$signature] === $key) {
                    $filtered_payment_methods[] = $pm;
                } else {
                    $this->log("Removing duplicate payment method with last4: " . $pm['method']['last4'] . 
                               " and brand: " . $pm['method']['brand'] . " (case-insensitive match)");
                }
            } else {
                // Keep methods that don't have complete card info
                $filtered_payment_methods[] = $pm;
            }
        }
        
        // Replace original array with filtered array
        $payment_methods = $filtered_payment_methods;
        
        // Rebuild tracking maps with the filtered list
        $tracked_payment_methods = [];
        $normalized_cards = [];
        foreach ($payment_methods as $key => $pm) {
            if (isset($pm['token_id'])) {
                $tracked_payment_methods[$pm['token_id']] = true;
            }
            if (isset($pm['method']['id'])) {
                $tracked_payment_methods[$pm['method']['id']] = true;
            }
            
            // Track by card details
            if (isset($pm['method']['last4']) && isset($pm['method']['brand'])) {
                $signature = strtolower($pm['method']['last4'] . '_' . $pm['method']['brand']);
                $normalized_cards[$signature] = true;
            }
        }
        
        // Build payment methods from database tokens that might not be in the API results
        if (count($db_tokens) > 0) {
            $this->log('Building payment methods manually from database token data');
            
            foreach ($db_tokens as $db_token) {
                // Skip tokens that are already in the payment methods
                if (isset($tracked_payment_methods[$db_token->token_id]) || isset($tracked_payment_methods[$db_token->token])) {
                    $this->log("Skipping token {$db_token->token_id} as it's already in the list");
                    continue;
                }
                
                // Get token metadata
                $metadata = isset($token_meta[$db_token->token_id]) ? $token_meta[$db_token->token_id] : [];
                
                // Log available metadata keys for debugging
                $meta_keys = empty($metadata) ? 'none' : implode(', ', array_keys($metadata));
                $this->log("Token {$db_token->token_id} metadata keys: " . $meta_keys);
                
                // Try multiple approaches to get card details
                
                // 1. Try to get directly from WC token API
                $card_data = [
                    'last4' => '',
                    'exp_month' => '',
                    'exp_year' => '',
                    'type' => ''
                ];
                
                // Check if this token exists in the WC API tokens
                foreach ($stripe_tokens as $api_token) {
                    if ($api_token->get_id() == $db_token->token_id) {
                        $this->log("Found token {$db_token->token_id} in API, using its data");
                        $card_data = [
                            'last4' => $api_token->get_last4(),
                            'exp_month' => $api_token->get_expiry_month(),
                            'exp_year' => $api_token->get_expiry_year(),
                            'type' => $api_token->get_card_type()
                        ];
                        break;
                    }
                }
                
                // 2. If not found in API, try different metadata key patterns
                if (empty($card_data['last4'])) {
                    // Check for different possible metadata keys
                    $possible_keys = [
                        'last4' => ['last4', '_last4'],
                        'exp_month' => ['expiry_month', '_expiry_month', 'exp_month', '_exp_month'],
                        'exp_year' => ['expiry_year', '_expiry_year', 'exp_year', '_exp_year'],
                        'type' => ['card_type', '_card_type', 'type', '_type', 'brand', '_brand']
                    ];
                    
                    foreach ($possible_keys as $data_key => $meta_keys) {
                        foreach ($meta_keys as $meta_key) {
                            if (isset($metadata[$meta_key]) && !empty($metadata[$meta_key])) {
                                $card_data[$data_key] = $metadata[$meta_key];
                                break;
                            }
                        }
                    }
                }
                
                // 3. As a last resort, try to get from Stripe API
                if (empty($card_data['last4']) || empty($card_data['type'])) {
                    try {
                        // Load Stripe SDK if needed
                        if (!class_exists('\\Stripe\\Stripe')) {
                            if (file_exists(BOCS_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php')) {
                                require_once BOCS_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php';
                            } elseif (defined('WC_STRIPE_PLUGIN_PATH') && file_exists(WC_STRIPE_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php')) {
                                require_once WC_STRIPE_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php';
                            }
                        }
                        
                        // Get Stripe API key
                        $stripe_settings = get_option('woocommerce_stripe_settings', array());
                        $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
                        $secret_key = $test_mode && isset($stripe_settings['test_secret_key']) 
                            ? $stripe_settings['test_secret_key'] 
                            : (isset($stripe_settings['secret_key']) ? $stripe_settings['secret_key'] : '');
                            
                        if (!empty($secret_key)) {
                            \Stripe\Stripe::setApiKey($secret_key);
                            $payment_method = \Stripe\PaymentMethod::retrieve($db_token->token);
                            
                            if ($payment_method && isset($payment_method->card)) {
                                $card_data = [
                                    'last4' => $payment_method->card->last4,
                                    'exp_month' => $payment_method->card->exp_month,
                                    'exp_year' => $payment_method->card->exp_year,
                                    'type' => strtolower($payment_method->card->brand)
                                ];
                                
                                // Update metadata for future use
                                update_metadata('payment_token', $db_token->token_id, 'last4', $card_data['last4']);
                                update_metadata('payment_token', $db_token->token_id, 'expiry_month', $card_data['exp_month']);
                                update_metadata('payment_token', $db_token->token_id, 'expiry_year', $card_data['exp_year']);
                                update_metadata('payment_token', $db_token->token_id, 'card_type', $card_data['type']);
                            }
                        }
                    } catch (\Exception $e) {
                        $this->log("Error retrieving payment method data from Stripe: " . $e->getMessage());
                    }
                }
                
                // If we have the minimum required data, add the payment method
                if (!empty($card_data['last4']) && !empty($card_data['type'])) {
                    // Check if we already have a payment method with this last4 and card type (case insensitive)
                    $card_signature = strtolower($card_data['last4'] . '_' . $card_data['type']);
                    if (isset($normalized_cards[$card_signature])) {
                        $this->log("Skipping token {$db_token->token_id} as a payment method with same last4 and card type already exists");
                        continue;
                    }
                    
                    $this->log("Adding token {$db_token->token_id} to payment methods list");
                    
                    $payment_methods[] = array(
                        'method' => array(
                            'id' => $db_token->token,
                            'brand' => strtoupper($card_data['type']),
                            'last4' => $card_data['last4'],
                            'exp_month' => $card_data['exp_month'],
                            'exp_year' => $card_data['exp_year'],
                        ),
                        'expires' => $card_data['exp_month'] . '/' . substr($card_data['exp_year'], -2),
                        'is_default' => $db_token->is_default == 1,
                        'actions' => array(),
                        'token_id' => $db_token->token_id
                    );
                    
                    // Add to tracking
                    $tracked_payment_methods[$db_token->token_id] = true;
                    $tracked_payment_methods[$db_token->token] = true;
                    $normalized_cards[$card_signature] = true;
                } else {
                    $this->log("Skipping token {$db_token->token_id} due to missing required metadata");
                }
            }
            
            $this->log('Final payment methods count: ' . count($payment_methods));
        }
        
        // If we still have Stripe tokens but they're not showing up in the payment methods, 
        // manually build the payment methods array from WC_Payment_Tokens
        if (count($stripe_tokens) > 0 && count($payment_methods) === 0) {
            $this->log('Building payment methods from WC_Payment_Tokens');
            
            foreach ($stripe_tokens as $token) {
                $type = $token->get_card_type();
                $last4 = $token->get_last4();
                $exp_month = $token->get_expiry_month();
                $exp_year = $token->get_expiry_year();
                $token_id = $token->get_token(); // This is the payment_method_id (pm_*)
                
                // Skip tokens without proper data
                if (empty($type) || empty($last4)) {
                    continue;
                }
                
                $payment_methods[] = array(
                    'method' => array(
                        'id' => $token_id,
                        'brand' => strtoupper($type),
                        'last4' => $last4,
                        'exp_month' => $exp_month,
                        'exp_year' => $exp_year,
                    ),
                    'expires' => $exp_month . '/' . substr($exp_year, -2),
                    'is_default' => $token->is_default(),
                    'actions' => array(),
                    'token_id' => $token->get_id()
                );
            }
            
            $this->log('Built ' . count($payment_methods) . ' payment methods from WC_Payment_Tokens');
        }
        
        // Final check - directly query for recent tokens that might be missing
        $recent_token_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens 
                WHERE gateway_id = 'stripe' AND user_id = %d
                ORDER BY token_id DESC LIMIT 10",
                $user_id
            )
        );
        
        if (!empty($recent_token_ids)) {
            $this->log('Found recent token IDs for user ' . $user_id . ': ' . implode(', ', $recent_token_ids));
            
            foreach ($recent_token_ids as $token_id) {
                // Skip tokens that are already in the payment methods
                $already_added = false;
                foreach ($payment_methods as $pm) {
                    if (isset($pm['token_id']) && $pm['token_id'] == $token_id) {
                        $already_added = true;
                        break;
                    }
                }
                
                if ($already_added) {
                    continue;
                }
                
                // Get token from database
                $token_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token_id = %d",
                        $token_id
                    )
                );
                
                if (!$token_row) {
                    continue;
                }
                
                $this->log("Processing recent token {$token_id}: " . $token_row->token);
                
                // Check if token belongs to current user, or we want to show all
                if ($token_row->user_id != $user_id) {
                    $this->log("Token {$token_id} belongs to user {$token_row->user_id}, not current user {$user_id}");
                    
                    // For debugging, include tokens that might belong to other users
                    // Comment out this line in production
                    // continue;
                }
                
                // Try to get token via Stripe API
                try {
                    // Load Stripe SDK if needed
                    if (!class_exists('\\Stripe\\Stripe')) {
                        if (file_exists(BOCS_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php')) {
                            require_once BOCS_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php';
                        } elseif (defined('WC_STRIPE_PLUGIN_PATH') && file_exists(WC_STRIPE_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php')) {
                            require_once WC_STRIPE_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php';
                        }
                    }
                    
                    // Get Stripe API key
                    $stripe_settings = get_option('woocommerce_stripe_settings', array());
                    $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
                    $secret_key = $test_mode && isset($stripe_settings['test_secret_key']) 
                        ? $stripe_settings['test_secret_key'] 
                        : (isset($stripe_settings['secret_key']) ? $stripe_settings['secret_key'] : '');
                        
                    if (!empty($secret_key)) {
                        \Stripe\Stripe::setApiKey($secret_key);
                        $payment_method = \Stripe\PaymentMethod::retrieve($token_row->token);
                        
                        if ($payment_method && isset($payment_method->card)) {
                            $last4 = $payment_method->card->last4;
                            $exp_month = $payment_method->card->exp_month;
                            $exp_year = $payment_method->card->exp_year;
                            $type = strtolower($payment_method->card->brand);
                            
                            $this->log("Found recent token {$token_id} via Stripe API");
                            
                            // Update metadata for future use
                            update_metadata('payment_token', $token_id, 'last4', $last4);
                            update_metadata('payment_token', $token_id, 'expiry_month', $exp_month);
                            update_metadata('payment_token', $token_id, 'expiry_year', $exp_year);
                            update_metadata('payment_token', $token_id, 'card_type', $type);
                            
                            $payment_methods[] = array(
                                'method' => array(
                                    'id' => $token_row->token,
                                    'brand' => strtoupper($type),
                                    'last4' => $last4,
                                    'exp_month' => $exp_month,
                                    'exp_year' => $exp_year,
                                ),
                                'expires' => $exp_month . '/' . substr($exp_year, -2),
                                'is_default' => $token_row->is_default == 1,
                                'actions' => array(),
                                'token_id' => $token_id
                            );
                        }
                    }
                } catch (\Exception $e) {
                    $this->log("Error retrieving payment method data from Stripe for token {$token_id}: " . $e->getMessage());
                }
            }
        }
        
        // Return the available payment methods
        $response = array(
            'payment_methods' => $payment_methods
        );
        
        // Final check - log the final count
        $this->log('Final payment methods count: ' . count($payment_methods));
        
        // Additional summary logging - show what payment methods will be returned
        $summary = [];
        foreach ($payment_methods as $pm) {
            if (isset($pm['method']['brand']) && isset($pm['method']['last4'])) {
                $summary[] = $pm['method']['brand'] . ' ending in ' . $pm['method']['last4'];
            } elseif (isset($pm['token_id'])) {
                $summary[] = 'Token ID: ' . $pm['token_id'];
            }
        }
        
        $this->log('Payment methods to be returned: ' . implode(', ', $summary));
        
        // Get add payment method URL
        $add_payment_url = wc_get_endpoint_url('add-payment-method');
        
        // Return success response with all payment methods
        wp_send_json_success(array(
            'payment_methods' => $payment_methods,
            'add_payment_url' => $add_payment_url
        ));
    }
    
    /**
     * SPECIAL: AJAX handler to specifically get the missing tokens 47 and 48
     */
    public function ajax_get_missing_tokens() {
        // Check nonce for security
        check_ajax_referer('bocs-ajax-nonce', 'nonce');
        
        // Get the current user ID
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }
        
        global $wpdb;
        
        // Check for specific tokens 47 and 48
        $token_ids = [47, 48];
        $missing_tokens = [];
        
        foreach ($token_ids as $token_id) {
            // Get token from payment_tokens table
            $token_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token_id = %d",
                    $token_id
                )
            );
            
            if ($token_row) {
                $this->log("Found missing token {$token_id} in database: " . $token_row->token);
                
                // Get token metadata
                $meta_results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT meta_key, meta_value FROM {$wpdb->prefix}woocommerce_payment_tokenmeta WHERE payment_token_id = %d",
                        $token_id
                    )
                );
                
                $metadata = [];
                foreach ($meta_results as $meta) {
                    $metadata[$meta->meta_key] = $meta->meta_value;
                    $this->log("Token {$token_id} metadata: {$meta->meta_key} = {$meta->meta_value}");
                }
                
                // Create token object and add to list
                $token = new WC_Payment_Token_CC();
                $token->set_id($token_id);
                $token->set_token($token_row->token);
                $token->set_gateway_id($token_row->gateway_id);
                $token->set_user_id($token_row->user_id);
                
                if (isset($metadata['last4'])) {
                    $token->set_last4($metadata['last4']);
                }
                
                if (isset($metadata['expiry_month'])) {
                    $token->set_expiry_month($metadata['expiry_month']);
                }
                
                if (isset($metadata['expiry_year'])) {
                    $token->set_expiry_year($metadata['expiry_year']);
                }
                
                if (isset($metadata['card_type'])) {
                    $token->set_card_type($metadata['card_type']);
                }
                
                $missing_tokens[] = $token;
            } else {
                $this->log("Token {$token_id} not found in database");
            }
        }
        
        // Get tokens directly from the gateway
        $available_gateways = WC()->payment_gateways->payment_gateways();
        $gateway = isset($available_gateways['stripe']) ? $available_gateways['stripe'] : null;
        
        if ($gateway && method_exists($gateway, 'get_tokens')) {
            $gateway_tokens = $gateway->get_tokens();
            $this->log('Found ' . count($gateway_tokens) . ' tokens from Stripe gateway');
            
            foreach ($gateway_tokens as $token_id => $token_data) {
                $this->log("Gateway token: {$token_id}, data: " . json_encode($token_data));
            }
        }
        
        // Success response
        wp_send_json_success(array(
            'tokens_found' => count($missing_tokens),
            'token_details' => array_map(function($token) {
                return [
                    'id' => $token->get_id(),
                    'token' => $token->get_token(),
                    'last4' => $token->get_last4(),
                    'expiry' => $token->get_expiry_month() . '/' . $token->get_expiry_year(),
                    'type' => $token->get_card_type(),
                ];
            }, $missing_tokens)
        ));
    }
    
    /**
     * AJAX handler for updating payment method
     */
    public function ajax_update_payment_method() {
        // Check nonce for security
        check_ajax_referer('bocs-ajax-nonce', 'nonce');
        
        // Get the subscription ID from the request
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            wp_send_json_error(array('message' => 'Subscription ID not provided'));
            return;
        }
        
        // Get the payment method ID from the request
        $payment_method_id = isset($_POST['payment_method_id']) ? sanitize_text_field($_POST['payment_method_id']) : '';
        if (empty($payment_method_id)) {
            wp_send_json_error(array('message' => 'Payment method ID not provided'));
            return;
        }
        
        // Check if this is a new method that needs to be saved to WooCommerce
        $is_new_method = isset($_POST['is_new_method']) ? (bool)$_POST['is_new_method'] : false;
        
        // Update the subscription payment method in the BOCS API
        $helper = new Bocs_Helper();
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id . '/payment';
        
        $response = $helper->curl_request($url, 'PUT', array(
            'payment_method_id' => $payment_method_id
        ), $this->headers);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        // If this is a new payment method, save it to WooCommerce payment tokens
        if ($is_new_method && strpos($payment_method_id, 'pm_') === 0) {
            $user_id = get_current_user_id();
            $token_id = null;
            
            // Log the start of token saving
            $this->log('Saving new WooCommerce payment token for method: ' . $payment_method_id);
            
            // Use our helper method to ensure the payment method is saved in WC tokens
            $token_id = $this->ensure_payment_method_in_wc_tokens($payment_method_id, $user_id);
            
            // Include the token ID in the response if it was created
            if ($token_id) {
                $response['token_id'] = $token_id;
            }
        }
        
        // Success response
        wp_send_json_success(array(
            'message' => 'Payment method updated successfully',
            'response' => $response
        ));
    }
    
    /**
     * Simple logging helper function
     * 
     * @param string $message The message to log
     * @param array $context Optional context data
     */
    private function log($message, $context = []) {
        // Always use error_log for critical debugging info
        error_log('BOCS TOKEN DEBUG: ' . $message);
        
        if (class_exists('Bocs_Log_Handler')) {
            $logger = new Bocs_Log_Handler();
            $logger->insert_log('info', $message, $context);
        }
    }

    /**
     * AJAX handler for getting user billing details
     */
    public function ajax_get_user_billing_details() {
        check_ajax_referer('bocs-ajax-nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }
        
        // Get user data
        $user = get_userdata($user_id);
        
        // Get customer billing data from WooCommerce
        $customer = new WC_Customer($user_id);
        
        $billing_details = array(
            'name' => $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name(),
            'email' => $customer->get_billing_email() ?: $user->user_email,
            'phone' => $customer->get_billing_phone(),
            'address' => array(
                'line1' => $customer->get_billing_address_1(),
                'line2' => $customer->get_billing_address_2(),
                'city' => $customer->get_billing_city(),
                'state' => $customer->get_billing_state(),
                'postal_code' => $customer->get_billing_postcode(),
                'country' => $customer->get_billing_country() ?: 'AU'
            )
        );
        
        // Get Stripe customer ID if available
        $stripe_customer_id = '';
        if (function_exists('wc_stripe_get_customer_id')) {
            $stripe_customer_id = wc_stripe_get_customer_id($user_id);
        }
        
        wp_send_json_success(array(
            'billing_details' => $billing_details,
            'stripe_customer_id' => $stripe_customer_id
        ));
    }

    /**
     * AJAX handler for getting Stripe setup intent data
     */
    public function ajax_get_stripe_setup() {
        check_ajax_referer('bocs-ajax-nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }
        
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            wp_send_json_error(array('message' => 'Subscription ID not provided'));
            return;
        }
        
        // Get Stripe API keys
        $stripe_options = get_option('woocommerce_stripe_settings', array());
        $test_mode = isset($stripe_options['testmode']) && $stripe_options['testmode'] === 'yes';
        
        $publishable_key = $test_mode 
            ? (isset($stripe_options['test_publishable_key']) ? $stripe_options['test_publishable_key'] : '') 
            : (isset($stripe_options['publishable_key']) ? $stripe_options['publishable_key'] : '');
            
        $secret_key = $test_mode 
            ? (isset($stripe_options['test_secret_key']) ? $stripe_options['test_secret_key'] : '') 
            : (isset($stripe_options['secret_key']) ? $stripe_options['secret_key'] : '');
        
        if (empty($publishable_key) || empty($secret_key)) {
            wp_send_json_error(array('message' => 'Stripe is not properly configured'));
            return;
        }
        
        // Initialize Stripe
        try {
            // Check if we have direct access to Stripe SDK
            if (!class_exists('\\Stripe\\Stripe')) {
                // Try loading from WooCommerce Stripe gateway
                if (class_exists('WC_Gateway_Stripe')) {
                    $wc_stripe = new WC_Gateway_Stripe();
                    if (method_exists($wc_stripe, 'get_stripe_api')) {
                        $stripe = $wc_stripe->get_stripe_api();
                    } else {
                        wp_send_json_error(array('message' => 'Stripe API access not available'));
                        return;
                    }
                } else {
                    wp_send_json_error(array('message' => 'Stripe gateway not available'));
                    return;
                }
            }
            
            // Set Stripe API key
            if (class_exists('\\Stripe\\Stripe')) {
                \Stripe\Stripe::setApiKey($secret_key);
            }
            
            // Create a SetupIntent
            if (class_exists('\\Stripe\\SetupIntent')) {
                $setup_intent = \Stripe\SetupIntent::create([
                    'usage' => 'off_session',
                    'metadata' => [
                        'subscription_id' => $subscription_id,
                        'user_id' => $user_id
                    ]
                ]);
            } else {
                // Fall back to using WooCommerce's API if direct access isn't available
                if (class_exists('WC_Stripe_API') && method_exists('WC_Stripe_API', 'request')) {
                    $response = WC_Stripe_API::request(
                        array(
                            'usage' => 'off_session',
                            'metadata' => array(
                                'subscription_id' => $subscription_id,
                                'user_id' => $user_id
                            )
                        ),
                        'setup_intents'
                    );
                    
                    if (is_wp_error($response)) {
                        wp_send_json_error(array('message' => $response->get_error_message()));
                        return;
                    }
                    
                    $setup_intent = $response;
                } else {
                    wp_send_json_error(array('message' => 'Stripe API access not available'));
                    return;
                }
            }
            
            // Get saved payment methods
            $saved_methods = array();
            
            if (class_exists('WC_Payment_Tokens')) {
                $tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
                
                foreach ($tokens as $token) {
                    $saved_methods[] = array(
                        'id' => $token->get_token(),
                        'last4' => $token->get_last4(),
                        'brand' => $token->get_card_type(),
                        'exp_month' => $token->get_expiry_month(),
                        'exp_year' => $token->get_expiry_year()
                    );
                }
            }
            
            wp_send_json_success(array(
                'publishable_key' => $publishable_key,
                'client_secret' => $setup_intent->client_secret,
                'subscription_id' => $subscription_id,
                'saved_methods' => $saved_methods
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error creating Stripe setup: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX handler for updating subscription details (including box switching)
     */
    public function ajax_update_subscription() {
        // Check nonce - accept either bocs-switch-nonce or bocs_edit_details_nonce
        $nonce_verified = false;
        
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'bocs-switch-nonce')) {
                $nonce_verified = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'bocs_edit_details_nonce')) {
                $nonce_verified = true;
            }
        }
        
        if (!$nonce_verified) {
            wp_send_json_error(array(
                'message' => 'Security verification failed'
            ));
            return;
        }
        
        // Get parameters from request
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        $update_type = isset($_POST['update_type']) ? sanitize_text_field($_POST['update_type']) : '';
        
        // Handle different update types
        switch ($update_type) {
            case 'schedule':
                // Handle schedule update
                $next_payment_date = isset($_POST['next_payment_date']) ? sanitize_text_field($_POST['next_payment_date']) : '';
                $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
                
                if (empty($next_payment_date)) {
                    wp_send_json_error(array('message' => __('Next payment date is required', 'bocs-wordpress')));
                    return;
                }
                
                // Format date for API
                $next_payment_date_gmt = date('Y-m-d\TH:i:s\Z', strtotime($next_payment_date));
                
                // Prepare data for API
                $data = array(
                    'nextPaymentDateGmt' => $next_payment_date_gmt
                );
                
                if (!empty($reason)) {
                    $data['metaData'] = array(
                        array(
                            'key' => 'schedule_change_reason',
                            'value' => $reason
                        )
                    );
                }
                
                // Make API request
                $result = $this->update_subscription($data);
                
                if ($result['success']) {
                    wp_send_json_success(array('message' => __('Schedule updated successfully', 'bocs-wordpress')));
                } else {
                    wp_send_json_error(array('message' => $result['message']));
                }
                break;
            
            // Existing cases...
        }
    }
    
    /**
     * Handle pause and resume subscription requests
     * 
     * @param string $subscription_id The subscription ID
     * @param string $action Either 'pause' or 'resume'
     */
    private function handle_pause_resume_subscription($subscription_id, $action) {
        if (empty($subscription_id)) {
            wp_send_json_error(array('message' => 'Missing subscription ID'));
            return;
        }
        
        if (!in_array($action, array('pause', 'resume'))) {
            wp_send_json_error(array('message' => 'Invalid action'));
            return;
        }
        
        // Get reason for pause if provided
        $reason = '';
        if ($action === 'pause' && isset($_POST['reason'])) {
            $reason = sanitize_textarea_field($_POST['reason']);
        }
        
        $helper = new Bocs_Helper();
        
        // Prepare request data based on action type
        $request_data = array(
            'subscriptionStatus' => $action === 'pause' ? 'PAUSED' : 'ACTIVE'
        );
        
        // Add reason for pause if provided
        if ($action === 'pause' && !empty($reason)) {
            $request_data['pauseReason'] = $reason;
        }
        
        // Make API request
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        
        // Log the request for debugging
        error_log('Pause/Resume Request: ' . json_encode($request_data));
        
        $response = $helper->curl_request($url, 'PUT', $request_data, $this->headers);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
            return;
        }
        
        $message = $action === 'pause' ? 'Subscription paused successfully' : 'Subscription resumed successfully';
        
        wp_send_json_success(array(
            'message' => $message,
            'data' => $response
        ));
    }

    /**
     * AJAX handler for getting products for a box
     */
    public function ajax_get_box_products() {
        check_ajax_referer('bocs-switch-nonce', 'nonce');
        
        // Get box ID from request
        $bocs_id = isset($_POST['bocs_id']) ? sanitize_text_field($_POST['bocs_id']) : '';
        
        if (empty($bocs_id)) {
            wp_send_json_error(array(
                'message' => 'Box ID not provided'
            ));
            return;
        }
        
        // Load helper
        $helper = new Bocs_Helper();
        
        // Make API request to get box details with products
        $url = BOCS_API_URL . 'bocs/' . $bocs_id . '/products';
        $response = $helper->curl_request($url, 'GET', [], $this->headers);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
            return;
        }
        
        if (!isset($response['data']) || empty($response['data'])) {
            // If no products returned, try to get products from box details
            $url = BOCS_API_URL . 'bocs/' . $bocs_id;
            $box_details = $helper->curl_request($url, 'GET', [], $this->headers);
            
            if (is_wp_error($box_details)) {
                wp_send_json_error(array(
                    'message' => $box_details->get_error_message()
                ));
                return;
            }
            
            // Extract products from box details
            $products = [];
            if (isset($box_details['data']['products']) && is_array($box_details['data']['products'])) {
                $products = $box_details['data']['products'];
            } elseif (isset($box_details['data']['availableProducts']) && is_array($box_details['data']['availableProducts'])) {
                $products = $box_details['data']['availableProducts'];
            }
            
            wp_send_json_success(array(
                'products' => $products
            ));
            return;
        }
        
        // Return products
        wp_send_json_success(array(
            'products' => $response['data']
        ));
    }

    /**
     * AJAX handler for updating subscription products
     */
    public function ajax_update_subscription_products() {
        // More reliable nonce verification method
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_ajax_nonce')) {
            wp_send_json_error(array(
                'success' => false,
                'message' => 'Security verification failed. Please refresh the page and try again.'
            ));
            return;
        }
        
        $response = array('success' => false);
        
        // Check subscription ID
        if (empty($_POST['subscription_id'])) {
            $response['message'] = 'Subscription ID is required';
            wp_send_json_error($response);
            return;
        }
        
        $subscription_id = sanitize_text_field($_POST['subscription_id']);
        
        // Check products
        if (empty($_POST['products'])) {
            $response['message'] = 'No products selected';
            wp_send_json_error($response);
            return;
        }
        
        // Process products data
        $products_data = $_POST['products'];
        
        // Handle string or direct array
        if (is_string($products_data)) {
            $products = json_decode(stripslashes($products_data), true);
        } else {
            $products = $products_data;
        }
        
        // Validate products
        if (!is_array($products) || empty($products)) {
            $response['message'] = 'Invalid products data';
            wp_send_json_error($response);
            return;
        }
        
        // Log the raw incoming product data
        error_log('DETAILED DEBUG - Received product data: ' . json_encode($products));
        
        try {
            // Check if products already have complete data
            $has_complete_data = false;
            if (!empty($products) && isset($products[0])) {
                $required_fields = ['productId', 'quantity', 'total', 'subtotal', 'price', 'taxClass', 'taxes', 'parentName', 'variationId', 'sku'];
                $has_complete_data = true;
                foreach ($required_fields as $field) {
                    if (!isset($products[0][$field])) {
                        error_log('DEBUG - Missing required field in product data: ' . $field);
                        $has_complete_data = false;
                    }
                }
            }
            
            error_log('DEBUG - Products have complete data: ' . ($has_complete_data ? 'Yes' : 'No'));
            
            // If we already have complete data, use it directly
            if ($has_complete_data) {
                error_log('Using complete product data from frontend');
                
                // We still need the subscription details for product name lookups
                $helper = new Bocs_Helper();
                $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
                $current_subscription = $helper->curl_request($url, 'GET', [], $this->headers);
                
                // Make it available globally for lookup even if there was an error
                global $bocs_current_subscription;
                $bocs_current_subscription = is_wp_error($current_subscription) ? null : $current_subscription;
                
                // Log error but continue with available data
                if (is_wp_error($current_subscription)) {
                    error_log('Warning: Could not retrieve subscription data for name lookup: ' . $current_subscription->get_error_message());
                }
                
                // Format lineItems from complete data
                $formatted_line_items = [];
                foreach ($products as $product) {
                    // Ensure productId is available - use id as fallback
                    if (!isset($product['productId']) && isset($product['id'])) {
                        $product['productId'] = $product['id'];
                    }
                    
                    // Skip invalid products
                    if (!isset($product['productId']) || !isset($product['quantity']) || (int)$product['quantity'] <= 0) {
                        continue;
                    }
                    
                    // Make sure name field is preserved from the frontend data
                    if (!isset($product['name']) || empty($product['name'])) {
                        error_log('WARNING - Product ' . $product['productId'] . ' is missing name field');
                        // Look for name in other sources
                        $product['name'] = $this->get_product_name_from_id($product['productId']);
                    } else {
                        error_log('DEBUG - Product ' . $product['productId'] . ' has name: ' . $product['name']);
                    }
                    
                    // Add to formatted line items - pass all fields without modification
                    $formatted_line_items[] = $product;
                }
                
                $request_data = [
                    'lineItems' => $formatted_line_items
                ];
            } else {
                // Otherwise get current subscription to maintain necessary data
                $helper = new Bocs_Helper();
                $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
                $current_subscription = $helper->curl_request($url, 'GET', [], $this->headers);
                
                // Make it available globally for lookup
                global $bocs_current_subscription;
                $bocs_current_subscription = is_wp_error($current_subscription) ? null : $current_subscription;
                
                if (is_wp_error($current_subscription)) {
                    error_log('Error fetching current subscription: ' . $current_subscription->get_error_message());
                    $response['message'] = 'Error retrieving current subscription data';
                    wp_send_json_error($response);
                    return;
                }
                
                if (!isset($current_subscription['data'])) {
                    error_log('Current subscription data is missing');
                    $response['message'] = 'Current subscription data is invalid';
                    wp_send_json_error($response);
                    return;
                }
                
                $bocs_id = $current_subscription['data']['bocs']['id'];
                error_log('DEBUG - Bocs ID: ' . $bocs_id);
                
                // Use static cache to prevent duplicate requests
                static $bocs_cache = [];
                
                // get the bocs details
                if (isset($bocs_cache[$bocs_id])) {
                    // Use cached data
                    $bocs_details = $bocs_cache[$bocs_id];
                    error_log('DEBUG - Using cached BOCS details for ID: ' . $bocs_id);
                } else {
                    // Make API request and cache the result
                    $bocs_details = $helper->curl_request(BOCS_API_URL . 'bocs/' . $bocs_id, 'GET', [], $this->headers);
                    
                    // Cache the result only if not an error
                    if (!is_wp_error($bocs_details)) {
                        $bocs_cache[$bocs_id] = $bocs_details;
                        error_log('DEBUG - Caching BOCS details for ID: ' . $bocs_id);
                    }
                }
                
                if (is_wp_error($bocs_details)) {
                    error_log('Error fetching bocs details: ' . $bocs_details->get_error_message());
                    $response['message'] = 'Error retrieving bocs details';
                    wp_send_json_error($response);
                    return;
                }

                // Create a lookup table for bocs products
                $this->bocs_products = [];
                if (isset($bocs_details['data']['products']) && is_array($bocs_details['data']['products'])) {
                    foreach ($bocs_details['data']['products'] as $bocs_product) {
                        if (isset($bocs_product['id'])) {
                            $this->bocs_products[$bocs_product['id']] = $bocs_product;
                            error_log('DEBUG - Added product to lookup: ' . $bocs_product['id'] . ' - ' . $bocs_product['name']);
                        }
                    }
                }

                // Log how many products we found in the bocs
                error_log('Found ' . count($this->bocs_products) . ' products in bocs details');
                
                // Prepare line items for API request based on the format from the API
                $line_items = [];
                foreach ($products as $product) {
                    if (isset($product['id']) && isset($product['quantity']) && $product['quantity'] > 0) {
                        $product_id = $product['id'];
                        $quantity = (int) $product['quantity'];
                        
                        error_log('DEBUG - Processing product: ' . $product_id . ' with quantity ' . $quantity);
                        
                        // Start with a base line item
                        $line_item = [
                            'productId' => $product_id,
                            'quantity' => $quantity,
                            'taxClass' => '',
                            'taxes' => [],
                            'metaData' => []
                        ];
                        
                        // First check if we have this product in the bocs details
                        if (isset($this->bocs_products[$product_id])) {
                            $bocs_product = $this->bocs_products[$product_id];
                            
                            // Copy all required fields from bocs product
                            $line_item['taxClass'] = $bocs_product['taxClass'] ?? '';
                            $line_item['taxes'] = $bocs_product['taxes'] ?? [];
                            $line_item['parentName'] = $bocs_product['parentName'] ?? '';
                            $line_item['variationId'] = $bocs_product['variationId'] ?? '0';
                            $line_item['sku'] = $bocs_product['sku'] ?? '';
                            $line_item['price'] = (float) ($bocs_product['price'] ?? 45);
                            $line_item['name'] = $bocs_product['name'] ?? $product['name'] ?? 'Product';
                            
                            // Copy any other fields that might be useful
                            if (isset($bocs_product['externalSourceId'])) {
                                $line_item['externalSourceId'] = $bocs_product['externalSourceId'];
                            }
                            
                            error_log('Found product in bocs details: ' . $product_id);
                        } else {
                            // Fallback to subscription or user-provided data
                            error_log('Product not found in bocs details, using fallback data: ' . $product_id);
                            
                            // Add name if available
                            if (isset($product['name'])) {
                                $line_item['name'] = $product['name'];
                            }
                            
                            // Add price if available
                            if (isset($product['price'])) {
                                $line_item['price'] = (float) $product['price'];
                            } else {
                                // Try to find the product price from current subscription
                                if (isset($current_subscription['data']['lineItems'])) {
                                    foreach ($current_subscription['data']['lineItems'] as $current_item) {
                                        if ($current_item['productId'] === $product_id) {
                                            $line_item['price'] = (float) $current_item['price'];
                                            break;
                                        }
                                    }
                                }
                                
                                // Default price if not found
                                if (!isset($line_item['price'])) {
                                    $line_item['price'] = 45.00;
                                }
                            }
                            
                            // Set default values for required fields
                            $line_item['variationId'] = "0";
                            $line_item['sku'] = "";
                            $line_item['parentName'] = "";
                            
                            // Look for external source ID in current subscription
                            if (isset($current_subscription['data']['lineItems'])) {
                                foreach ($current_subscription['data']['lineItems'] as $current_item) {
                                    if ($current_item['productId'] === $product_id && isset($current_item['externalSourceId'])) {
                                        $line_item['externalSourceId'] = $current_item['externalSourceId'];
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Calculate totals based on discount
                        $discount_percent = 0;
                        if (isset($current_subscription['data']['frequency']) && 
                            isset($current_subscription['data']['frequency']['discount'])) {
                            $discount_percent = (float) $current_subscription['data']['frequency']['discount'];
                        }
                        
                        $discount_factor = 1 - ($discount_percent / 100);
                        $subtotal = $line_item['price'] * $quantity;
                        $total = $subtotal * $discount_factor;
                        
                        $line_item['subtotal'] = $subtotal;
                        $line_item['total'] = $total;
                        
                        // Calculate tax - typically 10% in AU
                        $tax_rate = 0.1;
                        $line_item['subtotalTax'] = $subtotal * $tax_rate;
                        $line_item['totalTax'] = $total * $tax_rate;
                        
                        // Log the complete line item to check if all fields are present
                        error_log('DEBUG - Final line item for product ' . $product_id . ': ' . json_encode($line_item));
                        
                        // Verify all required fields are present
                        $required_fields = ['productId', 'quantity', 'total', 'subtotal', 'price', 'taxClass', 'taxes', 'parentName', 'variationId', 'sku'];
                        foreach ($required_fields as $field) {
                            if (!isset($line_item[$field])) {
                                error_log('DEBUG - MISSING REQUIRED FIELD in final line item: ' . $field);
                            }
                        }
                        
                        $line_items[] = $line_item;
                    }
                }
                
                if (empty($line_items)) {
                    $response['message'] = 'No valid products selected';
                    wp_send_json_error($response);
                    return;
                }
                
                // Prepare request data - using the format the API expects
                $request_data = [
                    'lineItems' => $line_items
                ];
            }
            
            // Log the complete request data for debugging
            error_log('DEBUG - Complete update request data: ' . json_encode($request_data));
            
            // API request to update subscription products
            $api = new Bocs_API();
            
            // IMPORTANT: Pass the request data directly to avoid format conversion issues
            // The data already has the 'lineItems' key, so pass it as is
            $api_response = $api->update_subscription_products($subscription_id, $request_data);
            
            if (is_wp_error($api_response)) {
                error_log('API Error: ' . $api_response->get_error_message());
                $response['message'] = $api_response->get_error_message();
                wp_send_json_error($response);
                return;
            }
            
            // Log successful response
            error_log('Subscription products updated successfully for ID: ' . $subscription_id);
            
            // Success response
            $response = [
                'success' => true,
                'message' => 'Products updated successfully',
                'data' => $api_response
            ];
            
            wp_send_json_success($response);
        } catch (Exception $e) {
            error_log('Exception during API update: ' . $e->getMessage());
            $response['message'] = $e->getMessage();
            wp_send_json_error($response);
        }
    }

    // Helper function to update subscription with API
    private function update_subscription($data) {
        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        
        if (empty($subscription_id)) {
            return array(
                'success' => false,
                'message' => __('Subscription ID is required', 'bocs-wordpress')
            );
        }
        
        // Get plugin options
        $options = get_option('bocs_plugin_options');
        
        // Setup headers
        $headers = array(
            'Organization' => isset($options['bocs_headers']['organization']) ? $options['bocs_headers']['organization'] : '',
            'Store' => isset($options['bocs_headers']['store']) ? $options['bocs_headers']['store'] : '',
            'Authorization' => isset($options['bocs_headers']['authorization']) ? $options['bocs_headers']['authorization'] : '',
            'Content-Type' => 'application/json'
        );
        
        // Make API request
        $helper = new Bocs_Helper();
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        $response = $helper->curl_request($url, 'PUT', $data, $headers);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        // Log the response
        if (class_exists('Bocs_Log_Handler')) {
            $logger = new Bocs_Log_Handler();
            $logger->insert_log('info', 'Subscription update response', array(
                'subscription_id' => $subscription_id,
                'response' => $response
            ));
        }
        
        return array(
            'success' => true,
            'message' => __('Subscription updated successfully', 'bocs-wordpress'),
            'data' => $response
        );
    }

    /**
     * Get product name from its ID using various sources
     * 
     * @param string $product_id The product ID
     * @return string The product name or a default name
     */
    private function get_product_name_from_id($product_id) {
        global $bocs_current_subscription;
        
        // Check if the global variable is set
        if (isset($bocs_current_subscription)) {
            error_log('Looking for product name for: ' . $product_id);
            
            // Try to find in lineItems at data.lineItems
            if (isset($bocs_current_subscription['data']) && 
                isset($bocs_current_subscription['data']['lineItems']) && 
                is_array($bocs_current_subscription['data']['lineItems'])) {
                error_log('Checking in data.lineItems with ' . count($bocs_current_subscription['data']['lineItems']) . ' items');
                foreach ($bocs_current_subscription['data']['lineItems'] as $item) {
                    if (isset($item['productId']) && $item['productId'] === $product_id && isset($item['name'])) {
                        error_log('Found name in data.lineItems: ' . $item['name']);
                        return $item['name'];
                    }
                }
            }
            
            // Try to find in direct lineItems
            if (isset($bocs_current_subscription['lineItems']) && 
                is_array($bocs_current_subscription['lineItems'])) {
                error_log('Checking in lineItems with ' . count($bocs_current_subscription['lineItems']) . ' items');
                foreach ($bocs_current_subscription['lineItems'] as $item) {
                    if (isset($item['productId']) && $item['productId'] === $product_id && isset($item['name'])) {
                        error_log('Found name in lineItems: ' . $item['name']);
                        return $item['name'];
                    }
                }
            }
            
            // Check old format with line_items
            if (isset($bocs_current_subscription['data']) && 
                isset($bocs_current_subscription['data']['line_items']) && 
                is_array($bocs_current_subscription['data']['line_items'])) {
                foreach ($bocs_current_subscription['data']['line_items'] as $item) {
                    if (isset($item['productId']) && $item['productId'] === $product_id && isset($item['name'])) {
                        error_log('Found name in data.line_items: ' . $item['name']);
                        return $item['name'];
                    }
                }
            }
        } else {
            error_log('bocs_current_subscription is not set');
        }
        
        // Try to get name from BOCS product data (from local variable in this context)
        if (isset($this->bocs_products) && isset($this->bocs_products[$product_id]) && isset($this->bocs_products[$product_id]['name'])) {
            error_log('Found name in bocs_products: ' . $this->bocs_products[$product_id]['name']);
            return $this->bocs_products[$product_id]['name'];
        }
        
        // Try to get name from current context if available
        if (isset($bocs_products) && isset($bocs_products[$product_id]) && isset($bocs_products[$product_id]['name'])) {
            error_log('Found name in local bocs_products: ' . $bocs_products[$product_id]['name']);
            return $bocs_products[$product_id]['name'];
        }
        
        // Try to get name from WooCommerce if we have an externalSourceId mapping
        $product_mapping = get_option('bocs_product_mapping', []);
        if (isset($product_mapping[$product_id])) {
            $wc_product_id = $product_mapping[$product_id];
            if (function_exists('wc_get_product')) {
                $product = wc_get_product($wc_product_id);
                if ($product) {
                    error_log('Found name in WooCommerce: ' . $product->get_name());
                    return $product->get_name();
                }
            }
        }
        
        error_log('Could not find name for product: ' . $product_id);
        
        // Fallback default name
        return 'Product';
    }

    /**
     * AJAX handler for getting subscription line items HTML
     * 
     * Returns the HTML for the subscription line items component
     * for dynamic updates in the frontend
     */
    public function ajax_get_subscription_line_items() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs-ajax-nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to perform this action']);
            return;
        }
        
        // Check if subscription ID is provided
        if (!isset($_POST['subscription_id']) || empty($_POST['subscription_id'])) {
            wp_send_json_error(['message' => 'No subscription ID provided']);
            return;
        }
        
        $subscription_id = sanitize_text_field($_POST['subscription_id']);
        
        // Get the subscription data
        $subscription_data = $this->get_subscription_data($subscription_id);
        
        if (is_wp_error($subscription_data)) {
            wp_send_json_error(['message' => $subscription_data->get_error_message()]);
            return;
        }
        
        // Extract line items and totals
        $items = isset($subscription_data['items']) ? $subscription_data['items'] : [];
        $subtotal = isset($subscription_data['subtotal']) ? $subscription_data['subtotal'] : 0;
        $discount = isset($subscription_data['discount']) ? $subscription_data['discount'] : 0;
        $shipping = isset($subscription_data['shipping']) ? $subscription_data['shipping'] : 0;
        $tax = isset($subscription_data['tax']) ? $subscription_data['tax'] : 0;
        $total = isset($subscription_data['total']) ? $subscription_data['total'] : 0;
        $coupon_lines = isset($subscription_data['couponLines']) ? $subscription_data['couponLines'] : [];
        
        // Set unique component ID
        $component_id = 'bocs-order-items-' . uniqid();
        
        // Load the component template
        ob_start();
        
        // Include the component template
        include $this->get_template_path('components/order-line-items.php');
        
        $html = ob_get_clean();
        
        // Return the HTML
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX handler for getting subscription line items data
     * 
     * Returns the raw data for subscription line items
     * for processing in the frontend
     */
    public function ajax_get_subscription_line_items_data() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs-ajax-nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to perform this action']);
            return;
        }
        
        // Check if subscription ID is provided
        if (!isset($_POST['subscription_id']) || empty($_POST['subscription_id'])) {
            wp_send_json_error(['message' => 'No subscription ID provided']);
            return;
        }
        
        $subscription_id = sanitize_text_field($_POST['subscription_id']);
        
        // Get the subscription data
        $subscription_data = $this->get_subscription_data($subscription_id);
        
        if (is_wp_error($subscription_data)) {
            wp_send_json_error(['message' => $subscription_data->get_error_message()]);
            return;
        }
        
        // Extract line items and totals data
        $data = [
            'items' => isset($subscription_data['items']) ? $subscription_data['items'] : [],
            'subtotal' => isset($subscription_data['subtotal']) ? $subscription_data['subtotal'] : 0,
            'discount' => isset($subscription_data['discount']) ? $subscription_data['discount'] : 0,
            'shipping' => isset($subscription_data['shipping']) ? $subscription_data['shipping'] : 0,
            'tax' => isset($subscription_data['tax']) ? $subscription_data['tax'] : 0,
            'total' => isset($subscription_data['total']) ? $subscription_data['total'] : 0,
            'coupon_lines' => isset($subscription_data['couponLines']) ? $subscription_data['couponLines'] : [],
        ];
        
        // Return the data
        wp_send_json_success($data);
    }
    
    /**
     * Get subscription data including line items
     * 
     * @param string $subscription_id The subscription ID
     * @return array|WP_Error Subscription data or error
     */
    private function get_subscription_data($subscription_id) {
        // Get the BOCS subscription
        $url = BOCS_API_URL . 'subscriptions/' . urlencode($subscription_id);
        $helper = new Bocs_Helper();
        $response = $helper->curl_request($url, 'GET', [], $this->headers);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (!isset($response['data']) || empty($response['data'])) {
            return new WP_Error('no_subscription', 'Subscription not found');
        }
        
        $subscription = $response['data'];
        
        // Start preparing the data for return
        $data = [];
        
        // Get the line items
        $data['items'] = isset($subscription['lineItems']) ? $subscription['lineItems'] : [];
        
        // Check if product names or prices are empty and get BOCS data if needed
        $need_bocs_data = false;
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                if (empty($item['name']) || empty($item['price']) || $item['price'] == 0) {
                    $need_bocs_data = true;
                    break;
                }
            }
            
            // Log whether we need to retrieve BOCS data
            if (function_exists('bocs_log')) {
                bocs_log('Checking for missing product info in subscription: ' . $subscription_id, 'debug', [
                    'need_bocs_data' => $need_bocs_data ? 'Yes' : 'No',
                    'has_bocs_id' => isset($subscription['bocs']['id']) ? 'Yes' : 'No',
                    'bocs_id' => isset($subscription['bocs']['id']) ? $subscription['bocs']['id'] : 'None'
                ]);
            }
            
            // If we need BOCS data and we have a BOCS ID, fetch it
            if ($need_bocs_data && isset($subscription['bocs']['id']) && !empty($subscription['bocs']['id'])) {
                $bocs_id = $subscription['bocs']['id'];
                $bocs_url = BOCS_API_URL . 'bocs/' . urlencode($bocs_id);
                
                if (function_exists('bocs_log')) {
                    bocs_log('Fetching BOCS details for ID: ' . $bocs_id, 'debug');
                }
                
                $bocs_response = $helper->curl_request($bocs_url, 'GET', [], $this->headers);
                
                if (is_wp_error($bocs_response)) {
                    if (function_exists('bocs_log')) {
                        bocs_log('Error fetching BOCS details', 'error', [
                            'message' => $bocs_response->get_error_message()
                        ]);
                    }
                } elseif (!isset($bocs_response['data']['products']) || empty($bocs_response['data']['products'])) {
                    if (function_exists('bocs_log')) {
                        bocs_log('No products found in BOCS response', 'warning');
                    }
                } else {
                    if (function_exists('bocs_log')) {
                        bocs_log('BOCS data received', 'debug', [
                            'bocs_name' => isset($bocs_response['data']['name']) ? $bocs_response['data']['name'] : 'Unknown',
                            'product_count' => count($bocs_response['data']['products'])
                        ]);
                    }
                    
                    // Create a map of product IDs to product details for quick lookup
                    $bocs_products = [];
                    foreach ($bocs_response['data']['products'] as $product) {
                        if (isset($product['id'])) {
                            $bocs_products[$product['id']] = $product;
                        }
                    }
                    
                    if (function_exists('bocs_log')) {
                        bocs_log('Created product ID mapping', 'debug', [
                            'product_id_count' => count($bocs_products)
                        ]);
                    }
                    
                    // Update line items with product details from BOCS
                    $updated_count = 0;
                    foreach ($data['items'] as &$item) {
                        if (isset($item['productId']) && isset($bocs_products[$item['productId']])) {
                            $bocs_product = $bocs_products[$item['productId']];
                            $item_updated = false;
                            
                            // Update name if empty
                            if (empty($item['name'])) {
                                $item['name'] = $bocs_product['name'];
                                $item_updated = true;
                            }
                            
                            // Update price if empty or zero
                            if (empty($item['price']) || $item['price'] == 0) {
                                $item['price'] = $bocs_product['price'];
                                
                                // Also update total based on quantity and price
                                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                                $item['total'] = $bocs_product['price'] * $quantity;
                                $item['subtotal'] = $bocs_product['price'] * $quantity;
                                $item_updated = true;
                            }
                            
                            if ($item_updated) {
                                $updated_count++;
                            }
                        }
                    }
                    
                    if (function_exists('bocs_log')) {
                        bocs_log('Updated line items with BOCS product data', 'debug', [
                            'updated_count' => $updated_count,
                            'total_items' => count($data['items'])
                        ]);
                    }
                }
            }
        }
        
        // Calculate the subtotal
        $subtotal = 0;
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $price = isset($item['price']) ? (float)$item['price'] : 0;
                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                $subtotal += $price * $quantity;
            }
        }
        $data['subtotal'] = $subtotal;
        
        // Get discount
        $discount = 0;
        if (isset($subscription['couponLines']) && is_array($subscription['couponLines'])) {
            $coupon_lines = $subscription['couponLines'];
            $data['couponLines'] = $coupon_lines;
            
            foreach ($coupon_lines as $coupon) {
                if (isset($coupon['discount'])) {
                    $discount += (float)$coupon['discount'];
                }
            }
        }
        $data['discount'] = $discount;
        
        // Get shipping
        $data['shipping'] = isset($subscription['shippingTotal']) ? (float)$subscription['shippingTotal'] : 0;
        
        // Get tax
        $data['tax'] = isset($subscription['taxTotal']) ? (float)$subscription['taxTotal'] : 0;
        
        // Get total
        $data['total'] = isset($subscription['total']) ? (float)$subscription['total'] : ($subtotal - $discount + $data['shipping'] + $data['tax']);
        
        return $data;
    }

    /**
     * Handle payment method setup after 3D Secure redirect
     * 
     * This function handles the callback after a payment method has been set up and verified with 3D Secure.
     * It needs to run on page load before any AJAX calls.
     */
    public function handle_payment_setup_redirect() {
        // Only run on the account pages
        if (!is_account_page()) {
            return;
        }
        
        // Check if we have a redirect status parameter (from Stripe)
        if (isset($_GET['redirect_status']) && isset($_GET['setup_intent']) && isset($_GET['payment_method_id'])) {
            $redirect_status = sanitize_text_field($_GET['redirect_status']);
            $setup_intent_id = sanitize_text_field($_GET['setup_intent']);
            $payment_method_id = sanitize_text_field($_GET['payment_method_id']);
            $subscription_id = isset($_GET['subscription_id']) ? sanitize_text_field($_GET['subscription_id']) : '';
            
            // Log the payment setup redirect parameters
            $this->log('Payment setup redirect detected: ' . $redirect_status);
            $this->log('Setup Intent: ' . $setup_intent_id);
            $this->log('Payment Method: ' . $payment_method_id);
            
            // Only proceed if the setup succeeded
            if ($redirect_status === 'succeeded') {
                $user_id = get_current_user_id();
                
                if (empty($user_id)) {
                    $this->log('No user ID found for payment setup redirect');
                    return;
                }
                
                // Use our helper method to ensure the payment method is saved in WC tokens
                $token_id = $this->ensure_payment_method_in_wc_tokens($payment_method_id, $user_id);
                
                if ($token_id) {
                    $this->log('Successfully saved token after redirect with ID: ' . $token_id);
                    
                    // If we have a subscription ID, update it with the new payment method
                    if (!empty($subscription_id)) {
                        // Update the subscription payment method in the BOCS API
                        $helper = new Bocs_Helper();
                        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id . '/payment';
                        
                        $response = $helper->curl_request($url, 'PUT', array(
                            'payment_method_id' => $payment_method_id
                        ), $this->headers);
                        
                        if (is_wp_error($response)) {
                            $this->log('Error updating subscription payment method after redirect: ' . $response->get_error_message());
                        } else {
                            $this->log('Successfully updated subscription payment method after redirect');
                            
                            // Add a success message
                            wc_add_notice('Your payment method has been saved and your subscription has been updated.', 'success');
                        }
                    }
                } else {
                    $this->log('Failed to save token after redirect');
                }
            } else {
                $this->log('Payment setup failed with status: ' . $redirect_status);
                wc_add_notice('There was a problem setting up your payment method. Please try again.', 'error');
            }
            
            // Redirect to remove the query parameters regardless of success/failure
            wp_safe_redirect(wc_get_account_endpoint_url('bocs-subscriptions'));
            exit;
        }
    }

    /**
     * Ensures a Stripe payment method is stored in WooCommerce payment tokens
     * Following WooCommerce Stripe's native implementation pattern
     * 
     * @param string $payment_method_id The Stripe payment method ID (pm_*)
     * @param int $user_id The WordPress user ID
     * @return int|bool Token ID if successful, false if failed
     */
    private function ensure_payment_method_in_wc_tokens($payment_method_id, $user_id) {
        if (empty($payment_method_id) || empty($user_id)) {
            $this->log('Missing payment method ID or user ID');
            return false;
        }
        
        $this->log('Creating WooCommerce payment token for method: ' . $payment_method_id . ' for user ID: ' . $user_id);
        
        // Verify user exists and get current user info
        $current_user_id = get_current_user_id();
        $this->log('Current logged in user ID: ' . $current_user_id);
        
        if ($current_user_id != $user_id) {
            $this->log('WARNING: Passed user ID ' . $user_id . ' does not match current user ' . $current_user_id);
        }
        
        // Get user data to verify
        $user_data = get_userdata($user_id);
        if (!$user_data) {
            $this->log('ERROR: User ID ' . $user_id . ' does not exist in WordPress');
            
            // Fall back to current user if provided user doesn't exist
            if ($current_user_id) {
                $this->log('Falling back to current user ID: ' . $current_user_id);
                $user_id = $current_user_id;
            } else {
                return false;
            }
        } else {
            $this->log('User exists: ' . $user_data->user_login . ' (ID: ' . $user_id . ')');
        }
        
        $this->log('Creating WooCommerce payment token for method: ' . $payment_method_id);
        
        // First check if token already exists
        if (class_exists('WC_Payment_Tokens')) {
            $existing_tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
            $this->log('Current user tokens count: ' . count($existing_tokens));
            
            // Log information about each existing token
            if (!empty($existing_tokens)) {
                $this->log('Existing tokens for user:');
                foreach ($existing_tokens as $existing_token) {
                    $this->log('Token ID: ' . $existing_token->get_id() . ', Payment Method: ' . $existing_token->get_token());
                }
            }
            
            foreach ($existing_tokens as $token) {
                if ($token->get_token() === $payment_method_id) {
                    $this->log('Payment method already exists as token ID: ' . $token->get_id());
                    
                    // Log detailed token information
                    $this->log('Logging detailed information for existing token:');
                    $this->log_token_from_db($token->get_id());
                    
                    // Set this token as default
                    WC_Payment_Tokens::set_users_default($user_id, $token->get_id());
                    $this->log('Setting token ' . $token->get_id() . ' as default for user ' . $user_id);
                    
                    // Verify the token to be sure
                    if ($this->verify_token($token->get_id(), $payment_method_id)) {
                        $this->log('Token verified successfully');
                        return $token->get_id();
                    } else {
                        $this->log('Token verification failed for existing token - will attempt to create a new one');
                    }
                }
            }
        }
        
        // Get Stripe API key for later use
        $stripe_settings = get_option('woocommerce_stripe_settings', array());
        $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
        $secret_key = $test_mode && isset($stripe_settings['test_secret_key']) 
            ? $stripe_settings['test_secret_key'] 
            : (isset($stripe_settings['secret_key']) ? $stripe_settings['secret_key'] : '');
            
        if (empty($secret_key)) {
            $this->log('No Stripe API key found');
            return false;
        }
        
        // Try to get Stripe customer ID
        $stripe_customer_id = false;
        if (function_exists('wc_stripe_get_customer_id')) {
            $stripe_customer_id = wc_stripe_get_customer_id($user_id);
        } else {
            $stripe_customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
            if ($test_mode && empty($stripe_customer_id)) {
                $stripe_customer_id = get_user_meta($user_id, '_stripe_test_customer_id', true);
            }
        }
        
        // APPROACH 1: First try using WooCommerce Stripe's native token methods if available
        // This is the recommended approach with updated WooCommerce Stripe plugin
        if (class_exists('WC_Stripe_Payment_Tokens') && method_exists('WC_Stripe_Payment_Tokens', 'add_token')) {
            $this->log('Attempting to save token using WC_Stripe_Payment_Tokens::add_token method');
            
            // First need to get payment method details from Stripe
            try {
                // Load the Stripe SDK if not already loaded
                if (!class_exists('\\Stripe\\Stripe')) {
                    if (file_exists(BOCS_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php')) {
                        require_once BOCS_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php';
                        $this->log('Loaded Stripe SDK from BOCS plugin');
                    } elseif (defined('WC_STRIPE_PLUGIN_PATH') && file_exists(WC_STRIPE_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php')) {
                        require_once WC_STRIPE_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php';
                        $this->log('Loaded Stripe SDK from WooCommerce Stripe plugin');
                    }
                }
                
                // Initialize Stripe with secret key
                \Stripe\Stripe::setApiKey($secret_key);
                
                // Get payment method details
                $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
                
                if (!$payment_method || !isset($payment_method->card)) {
                    $this->log('Invalid payment method or not a card');
                    return false;
                }
                
                // Create token using WC Stripe's method
                $token_data = [
                    'token_id' => $payment_method_id,
                    'customer' => $stripe_customer_id ?: '',
                    'default' => true,
                    'source_id' => $payment_method_id,
                    'type' => 'card',
                    'card' => [
                        'brand' => $payment_method->card->brand,
                        'last4' => $payment_method->card->last4,
                        'exp_month' => $payment_method->card->exp_month,
                        'exp_year' => $payment_method->card->exp_year
                    ]
                ];
                
                $this->log('Calling WC_Stripe_Payment_Tokens::add_token with data: ' . json_encode($token_data));
                $token_id = WC_Stripe_Payment_Tokens::add_token($user_id, $token_data, 'stripe');
                
                if ($token_id) {
                    $this->log('Successfully saved token using WC_Stripe_Payment_Tokens::add_token. Token ID: ' . $token_id);
                    
                    // Force database commit and flush caches to ensure token is available
                    global $wpdb;
                    $wpdb->query("COMMIT");
                    $wpdb->query("SET AUTOCOMMIT=1");
                    
                    if (function_exists('wp_cache_flush')) {
                        wp_cache_flush();
                    }
                    
                    // Log detailed token information
                    $this->log('Logging token details after WC_Stripe_Payment_Tokens::add_token:');
                    $this->log_token_from_db($token_id);
                    
                    // Add a small delay before verification
                    sleep(1);
                    
                    // Verify the token
                    if ($this->verify_token($token_id, $payment_method_id)) {
                        $this->log('Token verification successful after WC_Stripe_Payment_Tokens::add_token');
                        
                        // Make sure it's set as default
                        WC_Payment_Tokens::set_users_default($user_id, $token_id);
                        
                        return $token_id;
                    } else {
                        $this->log('Token verification failed after WC_Stripe_Payment_Tokens::add_token');
                    }
                } else {
                    $this->log('WC_Stripe_Payment_Tokens::add_token returned falsy value');
                }
            } catch (\Exception $e) {
                $this->log('Exception during WC_Stripe_Payment_Tokens::add_token approach: ' . $e->getMessage());
            }
        }
        
        // APPROACH 2: Fall back to original method if WC Stripe method failed or is not available
        $this->log('Falling back to manual token creation method');
        
        try {
            // Load the Stripe SDK if not already loaded above
            if (!class_exists('\\Stripe\\Stripe')) {
                if (file_exists(BOCS_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php')) {
                    require_once BOCS_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php';
                    $this->log('Loaded Stripe SDK from BOCS plugin');
                } elseif (defined('WC_STRIPE_PLUGIN_PATH') && file_exists(WC_STRIPE_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php')) {
                    require_once WC_STRIPE_PLUGIN_PATH . 'vendor/stripe/stripe-php/init.php';
                    $this->log('Loaded Stripe SDK from WooCommerce Stripe plugin');
                }
            }
            
            // Initialize Stripe if not already initialized
            \Stripe\Stripe::setApiKey($secret_key);
            
            // Get payment method details from Stripe if not already retrieved above
            if (!isset($payment_method)) {
                $this->log('Retrieving payment method details from Stripe');
                $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
                
                if (!$payment_method || !isset($payment_method->card)) {
                    $this->log('Invalid payment method or not a card');
                    return false;
                }
            }
            
            // Check if WC_Payment_Token_CC class exists
            if (!class_exists('WC_Payment_Token_CC')) {
                $this->log('WC_Payment_Token_CC class not found');
                return false;
            }
            
            // Create token following WooCommerce's standard pattern
            $this->log('Creating WC_Payment_Token_CC object');
            $token = new WC_Payment_Token_CC();
            $token->set_token($payment_method_id);
            $token->set_gateway_id('stripe'); // Must match gateway ID expected by WooCommerce
            $token->set_card_type(strtolower($payment_method->card->brand));
            $token->set_last4($payment_method->card->last4);
            $token->set_expiry_month($payment_method->card->exp_month);
            $token->set_expiry_year($payment_method->card->exp_year);
            $token->set_user_id($user_id);
            
            // Perform validation
            if (!$token->validate()) {
                $this->log('Token validation failed');
                return false;
            }
            
            // Save the token
            $this->log('Saving token to database');
            $save_result = $token->save();
            
            if (!$save_result) {
                $this->log('Token save failed');
                return false;
            }
            
            $token_id = $token->get_id();
            $this->log('Successfully saved token with ID: ' . $token_id);
            
            // Try to use WooCommerce Stripe's native token addition method if available
            if (class_exists('WC_Stripe_Payment_Tokens') && method_exists('WC_Stripe_Payment_Tokens', 'add_token')) {
                $this->log('WC_Stripe_Payment_Tokens class found, attempting to use native add_token method');
                
                $wc_stripe_token_saved = WC_Stripe_Payment_Tokens::add_token(
                    $user_id,
                    [
                        'token_id' => $payment_method_id,
                        'customer' => isset($stripe_customer_id) ? $stripe_customer_id : '',
                        'default' => true,
                        'source_id' => $payment_method_id,
                    ],
                    'stripe'
                );
                
                $this->log('WC_Stripe_Payment_Tokens::add_token result: ' . ($wc_stripe_token_saved ? 'Success' : 'Failed'));
                
                // Force database commit and flush caches
                global $wpdb;
                $wpdb->query("COMMIT");
                $wpdb->query("SET AUTOCOMMIT=1");
                
                if (function_exists('wp_cache_flush')) {
                    $this->log('Flushing WordPress object cache');
                    wp_cache_flush();
                }
            }
            
            // Log detailed token information from the database
            $this->log('Logging detailed token information from database:');
            $this->log_token_from_db($token_id);
            
            // Add any additional metadata that might be needed
            update_metadata('payment_token', $token_id, '_stripe_source_id', $payment_method_id);
            
            // Store card details in token metadata for easier access
            update_metadata('payment_token', $token_id, 'last4', $payment_method->card->last4);
            update_metadata('payment_token', $token_id, 'expiry_month', $payment_method->card->exp_month);
            update_metadata('payment_token', $token_id, 'expiry_year', $payment_method->card->exp_year);
            update_metadata('payment_token', $token_id, 'card_type', strtolower($payment_method->card->brand));
            
            // Add Stripe customer ID as metadata if available
            if (!empty($stripe_customer_id)) {
                update_metadata('payment_token', $token_id, '_stripe_customer_id', $stripe_customer_id);
            }
            
            // Set this token as default for the user
            WC_Payment_Tokens::set_users_default($user_id, $token_id);
            $this->log('Setting token ' . $token_id . ' as default for user ' . $user_id);
            
            // Force database commit and flush caches
            global $wpdb;
            $wpdb->query("COMMIT");
            $wpdb->query("SET AUTOCOMMIT=1");
            
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Add a small delay to ensure database operations are complete
            sleep(1);
            
            // Verify the token was properly saved
            if ($this->verify_token($token_id, $payment_method_id)) {
                $this->log('Token verification successful');
                
                // Log token information after verification
                $this->log('Logging token information after verification:');
                $this->log_token_from_db($token_id);
                
                // Attach payment method to Stripe customer if not already
                if (!empty($stripe_customer_id)) {
                    try {
                        $this->log('Found Stripe customer ID: ' . $stripe_customer_id);
                        
                        // Check if already attached
                        $customer_methods = \Stripe\PaymentMethod::all([
                            'customer' => $stripe_customer_id,
                            'type' => 'card',
                        ]);
                        
                        $is_attached = false;
                        foreach ($customer_methods->data as $method) {
                            if ($method->id === $payment_method_id) {
                                $is_attached = true;
                                break;
                            }
                        }
                        
                        if (!$is_attached) {
                            $this->log('Attaching payment method to customer');
                            $payment_method->attach(['customer' => $stripe_customer_id]);
                            $this->log('Successfully attached payment method to customer');
                        } else {
                            $this->log('Payment method already attached to customer');
                        }
                    } catch (\Exception $e) {
                        $this->log('Failed to attach payment method to customer: ' . $e->getMessage());
                    }
                }
                
                // Send email notification about payment method update
                do_action('woocommerce_new_payment_method_added', $user_id, $token);
                $this->log('Payment method updated email sent to ' . wp_get_current_user()->user_email);
                
                $this->log('Payment method setup completed successfully. Token ID: ' . $token_id . ', Payment Method ID: ' . $payment_method_id);
                return $token_id;
            } else {
                $this->log('Token verification failed after save');
                return false;
            }
            
        } catch (\Exception $e) {
            $this->log('Exception during payment method setup: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify that a token exists and contains the correct payment method ID
     *
     * @param int $token_id WooCommerce Payment Token ID
     * @param string $payment_method_id Stripe Payment Method ID
     * @return bool Whether the token was verified successfully
     */
    private function verify_token($token_id, $payment_method_id) {
        if (empty($token_id) || empty($payment_method_id)) {
            return false;
        }
        
        // Get the token from WC
        $token = WC_Payment_Tokens::get($token_id);
        
        // Check if the token exists
        if (!$token) {
            $this->log('Verification failed: Token not found in the database');
            $this->log('Checking token status from direct database query:');
            $this->log_token_from_db($token_id);
            return false;
        }
        
        // Check if token has correct payment method ID
        if ($token->get_token() !== $payment_method_id) {
            $this->log('Verification failed: Token does not match payment method ID');
            $this->log('Expected: ' . $payment_method_id . ', Found: ' . $token->get_token());
            $this->log('Checking token details from database:');
            $this->log_token_from_db($token_id);
            return false;
        }
        
        // Additional check - make sure the token appears in customer tokens
        $user_id = $token->get_user_id();
        $customer_tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
        $found_in_customer_tokens = false;
        
        foreach ($customer_tokens as $customer_token) {
            if ($customer_token->get_id() == $token_id) {
                $found_in_customer_tokens = true;
                break;
            }
        }
        
        if (!$found_in_customer_tokens) {
            $this->log('Verification warning: Token not found in customer tokens list. Will try refreshing.');
            $this->log('Checking token details before cache refresh:');
            $this->log_token_from_db($token_id);
            
            // Try clearing caches to refresh the list
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wc_payment_tokens%'");
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Check again after clearing cache
            $customer_tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
            foreach ($customer_tokens as $customer_token) {
                if ($customer_token->get_id() == $token_id) {
                    $found_in_customer_tokens = true;
                    $this->log('Verification success: Token found after cache refresh');
                    break;
                }
            }
            
            if (!$found_in_customer_tokens) {
                $this->log('Verification failed: Token not found in customer tokens list even after cache refresh');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Log detailed information about a payment token directly from the database
     * Useful for debugging token verification issues
     *
     * @param int $token_id WooCommerce Payment Token ID
     * @return void
     */
    private function log_token_from_db($token_id) {
        if (empty($token_id)) {
            $this->log('Cannot log token details: Empty token ID');
            return;
        }
        
        global $wpdb;
        
        // Get token from payment_tokens table
        $token_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token_id = %d",
                $token_id
            )
        );
        
        if (!$token_row) {
            $this->log("Token ID {$token_id} not found in database table {$wpdb->prefix}woocommerce_payment_tokens");
            return;
        }
        
        $this->log("Token DB record found:");
        $this->log(" - Token ID: {$token_row->token_id}");
        $this->log(" - User ID: {$token_row->user_id}");
        $this->log(" - Gateway ID: {$token_row->gateway_id}");
        $this->log(" - Token: {$token_row->token}");
        $this->log(" - Is Default: {$token_row->is_default}");
        
        // Get token metadata
        $token_meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokenmeta WHERE payment_token_id = %d",
                $token_id
            )
        );
        
        if (empty($token_meta)) {
            $this->log("No metadata found for token ID {$token_id}");
        } else {
            $this->log("Token metadata:");
            foreach ($token_meta as $meta) {
                $this->log(" - {$meta->meta_key}: {$meta->meta_value}");
            }
        }
        
        // Check if token appears in user's tokens list according to WC API
        $user_id = $token_row->user_id;
        $api_token = WC_Payment_Tokens::get($token_id);
        if (!$api_token) {
            $this->log("WARNING: Token ID {$token_id} exists in database but cannot be retrieved via WC_Payment_Tokens::get()");
        } else {
            $this->log("Token successfully retrieved via WC_Payment_Tokens::get()");
            $this->log(" - Token details from API: " . $api_token->get_token());
            $this->log(" - Card type: " . $api_token->get_card_type());
            $this->log(" - Last4: " . $api_token->get_last4());
            $this->log(" - Expiry: " . $api_token->get_expiry_month() . '/' . $api_token->get_expiry_year());
        }
        
        // Check if token appears in customer tokens list
        $customer_tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
        $found_in_list = false;
        foreach ($customer_tokens as $customer_token) {
            if ($customer_token->get_id() == $token_id) {
                $found_in_list = true;
                break;
            }
        }
        
        $this->log("Token found in customer tokens list: " . ($found_in_list ? 'Yes' : 'No'));
        $this->log("Total customer tokens: " . count($customer_tokens));
    }
}
