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

    /** @var array API headers for Bocs authentication */
    private $headers;

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

            // Step 1: Try customer.id first
            if (!empty($bocs_customer_id)) {
                $query = 'customer.id:' . urlencode($bocs_customer_id);
                $url .= '?query=' . urlencode($query);
                
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('debug', '[Subscriptions Page] Searching by customer.id', [
                        'query' => $query,
                        'full_url' => $url
                    ]);
                }
                
                $helper = new Bocs_Helper();
                $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                
                if (is_wp_error($subscriptions)) {
                    if (class_exists('Bocs_Log_Handler')) {
                        $logger->insert_log('error', '[Subscriptions Page] WP Error when fetching subscriptions', [
                            'error' => $subscriptions->get_error_message()
                        ]);
                    }
                    return;
                }
                
                if (class_exists('Bocs_Log_Handler')) {
                    $logger->insert_log('debug', '[Subscriptions Page] API Response for customer.id', [
                        'has_data' => isset($subscriptions['data']),
                        'has_data_data' => isset($subscriptions['data']['data']),
                        'data_count' => isset($subscriptions['data']['data']) ? count($subscriptions['data']['data']) : 0,
                        'response_keys' => array_keys($subscriptions)
                    ]);
                }
                
                if (isset($subscriptions['data']['data']) && !empty($subscriptions['data']['data'])) {
                    // Found subscriptions by customer.id
                    if (class_exists('Bocs_Log_Handler')) {
                        $logger->insert_log('debug', '[Subscriptions Page] Found subscriptions by customer.id', [
                            'count' => count($subscriptions['data']['data'])
                        ]);
                    }
                } else {
                    // Step 2: Try billing.email if customer.id didn't work
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
                        } else {
                            // Step 3: Try order IDs if email didn't work
                            $order_ids = wc_get_orders(array(
                                'customer_id' => $user_id,
                                'limit' => -1,
                                'return' => 'ids'
                            ));
                            
                            if (class_exists('Bocs_Log_Handler')) {
                                $logger->insert_log('debug', '[Subscriptions Page] Searching by order IDs', [
                                    'order_count' => count($order_ids),
                                    'order_ids' => $order_ids
                                ]);
                            }
                            
                            if (!empty($order_ids)) {
                                $order_id_queries = array_map(function($order_id) {
                                    return 'externalSourceParentOrderId:' . urlencode($order_id);
                                }, $order_ids);
                                $query = implode(' OR ', $order_id_queries);
                                $url = BOCS_API_URL . 'subscriptions?query=' . urlencode($query);
                                
                                if (class_exists('Bocs_Log_Handler')) {
                                    $logger->insert_log('debug', '[Subscriptions Page] Order ID query', [
                                        'query' => $query,
                                        'full_url' => $url
                                    ]);
                                }
                                
                                $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                                
                                if (class_exists('Bocs_Log_Handler') && isset($subscriptions['data']['data'])) {
                                    $logger->insert_log('debug', '[Subscriptions Page] Found by order IDs', [
                                        'count' => count($subscriptions['data']['data'])
                                    ]);
                                }
                            }
                        }
                    }
                }
            } else {
                // If no customer.id, start with billing.email
                if ($current_user->exists()) {
                    $query = 'billing.email:' . urlencode($current_user->user_email);
                    $url .= '?query=' . urlencode($query);
                    
                    if (class_exists('Bocs_Log_Handler')) {
                        $logger->insert_log('debug', '[Subscriptions Page] No customer ID, searching by email', [
                            'query' => $query,
                            'full_url' => $url,
                            'email' => $current_user->user_email
                        ]);
                    }
                    
                    $helper = new Bocs_Helper();
                    $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                    
                    if (is_wp_error($subscriptions)) {
                        if (class_exists('Bocs_Log_Handler')) {
                            $logger->insert_log('error', '[Subscriptions Page] WP Error on email search', [
                                'error' => $subscriptions->get_error_message()
                            ]);
                        }
                        return;
                    }
                    
                    if (class_exists('Bocs_Log_Handler')) {
                        $logger->insert_log('debug', '[Subscriptions Page] API Response for billing.email (no customer ID)', [
                            'has_data' => isset($subscriptions['data']),
                            'has_data_data' => isset($subscriptions['data']['data']),
                            'data_count' => isset($subscriptions['data']['data']) ? count($subscriptions['data']['data']) : 0
                        ]);
                    }
                    
                    if (isset($subscriptions['data']['data']) && !empty($subscriptions['data']['data'])) {
                        // error_log('Bocs Account Debug - Found subscriptions by billing.email');
                    } else {
                        // Try order IDs if email didn't work
                        $order_ids = wc_get_orders(array(
                            'customer_id' => $user_id,
                            'limit' => -1,
                            'return' => 'ids'
                        ));
                        
                        if (class_exists('Bocs_Log_Handler')) {
                            $logger->insert_log('debug', '[Subscriptions Page] Searching by order IDs (fallback)', [
                                'order_count' => count($order_ids),
                                'order_ids' => $order_ids
                            ]);
                        }
                        
                        if (!empty($order_ids)) {
                            $order_id_queries = array_map(function($order_id) {
                                return 'externalSourceParentOrderId:' . urlencode($order_id);
                            }, $order_ids);
                            $query = implode(' OR ', $order_id_queries);
                            $url = BOCS_API_URL . 'subscriptions?query=' . urlencode($query);
                            
                            if (class_exists('Bocs_Log_Handler')) {
                                $logger->insert_log('debug', '[Subscriptions Page] Order ID query (fallback)', [
                                    'query' => $query,
                                    'full_url' => $url
                                ]);
                            }
                            
                            $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                            
                            if (class_exists('Bocs_Log_Handler') && isset($subscriptions['data']['data'])) {
                                $logger->insert_log('debug', '[Subscriptions Page] Found by order IDs (fallback)', [
                                    'count' => count($subscriptions['data']['data'])
                                ]);
                            }
                        }
                    }
                }
            }

            // Add fields parameter to get only needed data
            //$url .= '&fields=' . urlencode('id,subscriptionStatus,nextPaymentDateGmt,startDateGmt,total,currency,billingPeriod,frequency,externalSourceParentOrderId,orderKey,paymentMethodTitle,lineItems');
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
                    if (!empty($subscription['id'])) {
                        $pending_orders = wc_get_orders(array(
                            'status' => 'pending',
                            'limit' => 1,
                            'meta_key' => '__bocs_subscription_id',
                            'meta_value' => $subscription['id'],
                        ));
                        
                        // If pending orders exist, set status to "upcoming"
                        if (!empty($pending_orders)) {
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
                            $url = BOCS_API_URL . 'bocs/' . $bocs_id;
                            $bocs_details = $helper->curl_request($url, 'GET', [], $this->headers);
                            
                            if (isset($bocs_details['data']['name']) && !empty($bocs_details['data']['name'])) {
                                $formatted['bocs']['name'] = $bocs_details['data']['name'];
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
                            isset($subscription['shipping']['address1']) ? $subscription['shipping']['address1'] : '',
                            isset($subscription['shipping']['city']) ? $subscription['shipping']['city'] : '',
                            isset($subscription['shipping']['state']) ? $subscription['shipping']['state'] : '',
                            isset($subscription['shipping']['postcode']) ? $subscription['shipping']['postcode'] : ''
                        );
                        $formatted['delivery_address'] = array(
                            'formatted' => implode(', ', array_filter($address_parts))
                        );
                        
                        // Add full shipping address data
                        $formatted['shipping'] = array(
                            'address1' => isset($subscription['shipping']['address1']) ? $subscription['shipping']['address1'] : '',
                            'city' => isset($subscription['shipping']['city']) ? $subscription['shipping']['city'] : '',
                            'state' => isset($subscription['shipping']['state']) ? $subscription['shipping']['state'] : '',
                            'postcode' => isset($subscription['shipping']['postcode']) ? $subscription['shipping']['postcode'] : ''
                        );
                    } else {
                        $formatted['delivery_address'] = array(
                            'formatted' => __('No address provided', 'bocs-wordpress')
                        );
                        $formatted['shipping'] = array();
                    }
                    
                    // Add billing address
                    if (isset($subscription['billing']) && is_array($subscription['billing'])) {
                        $formatted['billing'] = array(
                            'address1' => isset($subscription['billing']['address1']) ? $subscription['billing']['address1'] : '',
                            'city' => isset($subscription['billing']['city']) ? $subscription['billing']['city'] : '',
                            'state' => isset($subscription['billing']['state']) ? $subscription['billing']['state'] : '',
                            'postcode' => isset($subscription['billing']['postcode']) ? $subscription['billing']['postcode'] : ''
                        );
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

        $template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs_edit_details.php';

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
        check_ajax_referer('bocs-subscriptions-nonce', 'nonce');
        
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
        
        // Get saved payment methods
        $saved_methods = wc_get_customer_saved_methods_list($user_id);
        $payment_methods = array();
        
        if (isset($saved_methods['cc']) && is_array($saved_methods['cc'])) {
            $payment_methods = $saved_methods['cc'];
        }
        
        // Get add payment method URL
        $add_payment_url = wc_get_endpoint_url('add-payment-method');
        
        wp_send_json_success(array(
            'payment_methods' => $payment_methods,
            'add_payment_url' => $add_payment_url
        ));
    }
    
    /**
     * AJAX handler for updating subscription payment method
     */
    public function ajax_update_payment_method() {
        // Check nonce for security
        check_ajax_referer('bocs-subscriptions-nonce', 'nonce');
        
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
        
        // Success response
        wp_send_json_success(array(
            'message' => 'Payment method updated successfully',
            'response' => $response
        ));
    }

    /**
     * AJAX handler for getting user billing details
     */
    public function ajax_get_user_billing_details() {
        check_ajax_referer('bocs-subscriptions-nonce', 'nonce');
        
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
        check_ajax_referer('bocs-subscriptions-nonce', 'nonce');
        
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
        check_ajax_referer('bocs-switch-nonce', 'nonce');
        
        // Get parameters from request
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        $bocs_id = isset($_POST['bocs_id']) ? sanitize_text_field($_POST['bocs_id']) : '';
        $frequency_id = isset($_POST['frequency_id']) ? sanitize_text_field($_POST['frequency_id']) : '';
        $products_json = isset($_POST['products']) ? sanitize_text_field($_POST['products']) : '';
        
        if (empty($subscription_id) || empty($bocs_id) || empty($frequency_id)) {
            wp_send_json_error(array(
                'message' => 'Missing required parameters'
            ));
            return;
        }
        
        // Load helper
        $helper = new Bocs_Helper();
        
        // First, get the current subscription details
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        $subscription = $helper->curl_request($url, 'GET', [], $this->headers);
        
        if (is_wp_error($subscription)) {
            wp_send_json_error(array(
                'message' => $subscription->get_error_message()
            ));
            return;
        }
        
        // Get the BOCS details to get the frequency information
        $url = BOCS_API_URL . 'bocs/' . $bocs_id;
        $bocs_details = $helper->curl_request($url, 'GET', [], $this->headers);
        
        if (is_wp_error($bocs_details)) {
            wp_send_json_error(array(
                'message' => $bocs_details->get_error_message()
            ));
            return;
        }
        
        // Get the frequency details from the BOCS adjustments
        $frequency_details = null;
        if (isset($bocs_details['data']['priceAdjustment']['adjustments'])) {
            foreach ($bocs_details['data']['priceAdjustment']['adjustments'] as $adjustment) {
                if (isset($adjustment['id']) && $adjustment['id'] === $frequency_id) {
                    $frequency_details = $adjustment;
                    break;
                }
            }
        }
        
        if (!$frequency_details) {
            wp_send_json_error(array(
                'message' => 'Frequency details not found'
            ));
            return;
        }
        
        // Prepare request data
        $request_data = array(
            'bocs' => array(
                'id' => $bocs_id
            ),
            'frequency' => array(
                'id' => $frequency_id,
                'frequency' => $frequency_details['frequency'],
                'timeUnit' => $frequency_details['timeUnit'],
                'discount' => isset($frequency_details['discount']) ? $frequency_details['discount'] : 0,
                'discountType' => isset($frequency_details['discountType']) ? $frequency_details['discountType'] : 'PERCENT'
            )
        );
        
        // Add line items if this is a custom box with selected products
        if (!empty($products_json)) {
            $selected_products = json_decode($products_json, true);
            
            if (is_array($selected_products)) {
                // Filter out products with quantity 0
                $line_items = array();
                foreach ($selected_products as $product) {
                    if (isset($product['quantity']) && $product['quantity'] > 0) {
                        // Format line item as required by the API
                        $line_items[] = array(
                            'productId' => $product['id'],
                            'name' => $product['name'],
                            'quantity' => $product['quantity'],
                            'price' => $product['price'],
                            'total' => $product['price'] * $product['quantity'],
                            'metaData' => array()
                        );
                    }
                }
                
                // Add line items to request data
                if (!empty($line_items)) {
                    $request_data['lineItems'] = $line_items;
                }
            }
        }
        
        // Include existing data that should be preserved
        if (isset($subscription['data'])) {
            // Copy these fields from the current subscription if they exist
            $fields_to_preserve = ['taxLines', 'nextPaymentDateGmt', 'couponLines', 'discountTotal', 
                                  'shippingTotal', 'shipping', 'billingInterval', 'discountTax'];
            
            foreach ($fields_to_preserve as $field) {
                if (isset($subscription['data'][$field])) {
                    $request_data[$field] = $subscription['data'][$field];
                }
            }
        }
        
        // Make the API request to update the subscription
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        $response = $helper->curl_request($url, 'PUT', $request_data, $this->headers);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Subscription updated successfully',
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
}
