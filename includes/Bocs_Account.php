<?php

class Bocs_Account
{

    private $headers;

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

    // bocs subscriptions under My Account
    public function bocs_account_menu_item($items)
    {
        $items['bocs-subscriptions'] = __('Bocs Subscriptions', 'bocs-subscriptions');
        return $items;
    }

    // bocs subscription new endpoint
    public function register_bocs_account_endpoint()
    {
        add_rewrite_endpoint('bocs-subscriptions', EP_ROOT | EP_PAGES);
    }

    /**
     * Content for the My Account Bocs Subscriptions
     */
    public function bocs_endpoint_content()
    {
        $user_id = get_current_user_id();

        // get the bocs customer id
        $bocs_customer_id = '';

        $data = false;

        if (! empty($user_id)) {
            $bocs_customer_id = get_user_meta($user_id, 'bocs_user_id', true);

            $url = BOCS_API_URL . 'subscriptions';

            if (! empty($bocs_customer_id)) {
                $data = [
                    'customer.id' => $bocs_customer_id
                ];
            } else {
                $data = [
                    'customer.externalSourceId' => $user_id
                ];
            }
        }

        if ($data === false) {
            $current_user = wp_get_current_user();
            if ($current_user->exists()) {
                $data = [
                    'billing.email' => esc_html($current_user->user_email)
                ];
            }
        }

        if ($data !== false) {
            // get the list of subscriptions of the current user
            $helper = new Bocs_Helper();
            $subscriptions = $helper->curl_request($url, 'GET', $data, $this->headers);
            $template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs_subscriptions_account.php';

            if (file_exists($template_path))
                include $template_path;
        } else {
            echo "";
        }
    }

    /**
     * register bocs subscription view under My Account
     */
    public function register_bocs_view_subscription_endpoint()
    {
        add_rewrite_endpoint('bocs-view-subscription', EP_PAGES);
    }

    public function bocs_view_subscription_endpoint_content()
    {
        global $wp;

        // Get the subscription ID from the URL
        $bocs_subscription_id = $wp->query_vars['bocs-view-subscription'];

        // get the details of the subscription
        if ($bocs_subscription_id) {

            $url = BOCS_API_URL . 'subscriptions/' . $bocs_subscription_id;

            // Retrieve the subscription details (replace this with actual subscription retrieval logic)
            $helper = new Bocs_Helper();
            $subscription = $helper->curl_request($url, 'GET', [], $this->headers);

            // get the related orders
            $url = BOCS_API_URL . 'orders?query=subscriptionId:' . $bocs_subscription_id;
            $related_orders = $helper->curl_request($url, 'GET',[], $this->headers);
            
            if(isset($related_orders['data'])) {
                if( $related_orders['data'] == 'Internal server error.' ) {
                    $related_orders['data'] = [];
                }
            } else {
                $related_orders['data'] = [];
            }

            // in case that the related orders not exists or there is none
            if (! isset($related_orders['data']) || empty($related_orders['data'])) {
                
                $args = array(
                    'limit' => - 1, // Retrieve all orders
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'meta_key' => '__bocs_subscription_id',
                    'meta_value' => $bocs_subscription_id,
                    'meta_compare' => '=',
                    'return' => 'ids' // Return only order IDs
                );

                $query = new WC_Order_Query($args);
                $related_orders['order_ids'] = $query->get_orders();
            }

            $template_path = plugin_dir_path(dirname(__FILE__)) . 'views/bocs_view_subscription.php';

            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<p>' . __('Invalid subscription ID.', 'woocommerce') . '</p>';
            }
        } else {
            echo '<p>' . __('No subscription ID provided.', 'woocommerce') . '</p>';
        }
    }
}
