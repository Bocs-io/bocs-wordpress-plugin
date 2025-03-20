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

        // Only proceed if we have a logged-in user
        if (! empty($user_id)) {
            //error_log('Bocs Account Debug - User ID: ' . $user_id);

            $bocs_customer_id = get_user_meta($user_id, 'bocs_user_id', true);
            //error_log('Bocs Account Debug - Bocs Customer ID: ' . $bocs_customer_id);

            $current_user = wp_get_current_user();
            $url = BOCS_API_URL . 'subscriptions';

            // Step 1: Try customer.id first
            if (!empty($bocs_customer_id)) {
                $query = 'customer.id:' . urlencode($bocs_customer_id);
                $url .= '?query=' . urlencode($query);
                //error_log('Bocs Account Debug - Trying customer.id URL: ' . $url);
                
                $helper = new Bocs_Helper();
                $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                
                if (isset($subscriptions['data']['data']) && !empty($subscriptions['data']['data'])) {
                    //error_log('Bocs Account Debug - Found subscriptions by customer.id');
                } else {
                    // Step 2: Try billing.email if customer.id didn't work
                    if ($current_user->exists()) {
                        $query = 'billing.email:' . urlencode($current_user->user_email);
                        $url = BOCS_API_URL . 'subscriptions?query=' . urlencode($query);
                        //error_log('Bocs Account Debug - Trying billing.email URL: ' . $url);
                        
                        $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                        
                        if (isset($subscriptions['data']['data']) && !empty($subscriptions['data']['data'])) {
                            //error_log('Bocs Account Debug - Found subscriptions by billing.email');
                        } else {
                            // Step 3: Try order IDs if email didn't work
                            $order_ids = wc_get_orders(array(
                                'customer_id' => $user_id,
                                'limit' => -1,
                                'return' => 'ids'
                            ));
                            
                            if (!empty($order_ids)) {
                                $order_id_queries = array_map(function($order_id) {
                                    return 'externalSourceParentOrderId:' . urlencode($order_id);
                                }, $order_ids);
                                $query = implode(' OR ', $order_id_queries);
                                $url = BOCS_API_URL . 'subscriptions?query=' . urlencode($query);
                                //error_log('Bocs Account Debug - Trying order IDs URL: ' . $url);
                                
                                $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                            }
                        }
                    }
                }
            } else {
                // If no customer.id, start with billing.email
                if ($current_user->exists()) {
                    $query = 'billing.email:' . urlencode($current_user->user_email);
                    $url .= '?query=' . urlencode($query);
                    //error_log('Bocs Account Debug - Trying billing.email URL: ' . $url);
                    
                    $helper = new Bocs_Helper();
                    $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                    
                    if (isset($subscriptions['data']['data']) && !empty($subscriptions['data']['data'])) {
                        error_log('Bocs Account Debug - Found subscriptions by billing.email');
                    } else {
                        // Try order IDs if email didn't work
                        $order_ids = wc_get_orders(array(
                            'customer_id' => $user_id,
                            'limit' => -1,
                            'return' => 'ids'
                        ));
                        
                        if (!empty($order_ids)) {
                            $order_id_queries = array_map(function($order_id) {
                                return 'externalSourceParentOrderId:' . urlencode($order_id);
                            }, $order_ids);
                            $query = implode(' OR ', $order_id_queries);
                            $url = BOCS_API_URL . 'subscriptions?query=' . urlencode($query);
                            //error_log('Bocs Account Debug - Trying order IDs URL: ' . $url);
                            
                            $subscriptions = $helper->curl_request($url, 'GET', [], $this->headers);
                        }
                    }
                }
            }

            // Add fields parameter to get only needed data
            //$url .= '&fields=' . urlencode('id,subscriptionStatus,nextPaymentDateGmt,startDateGmt,total,currency,billingPeriod,frequency,externalSourceParentOrderId,orderKey,paymentMethodTitle,lineItems');
            //error_log('Bocs Account Debug - Final URL: ' . $url);

            if (isset($subscriptions['data']['data'])) {
                //error_log('Bocs Account Debug - Number of subscriptions found: ' . count($subscriptions['data']['data']));
            } else {
                //error_log('Bocs Account Debug - No subscriptions data found in response');
            }

            $template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs_subscriptions_account.php';

            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo esc_html__('Subscription template not found.', 'bocs-wordpress');
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
}
