<?php

/**
 * Class BOCS_API
 *
 * This class handles all the API interactions for the BOCS plugin.
 */
class BOCS_API {
    /**
     * Headers for API requests
     *
     * @var array
     */
    private $headers;

    /**
     * Helper instance
     *
     * @var Bocs_Helper
     */
    private $helper;

    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        $this->headers = [
            'Content-Type' => 'application/json'
        ];

        if (! empty($options['bocs_headers']['organization']) && ! empty($options['bocs_headers']['store'])) {
            $this->headers['Organization'] = $options['bocs_headers']['organization'];
            $this->headers['Store'] = $options['bocs_headers']['store'];
        }
        
        // Handle authorization
        if (! empty($options['bocs_headers']['authorization'])) {
            $auth_value = $options['bocs_headers']['authorization'];
            
            // If this is a pre-formatted AWS SigV4 header, use it directly
            if (strpos($auth_value, 'Credential=') !== false && 
                strpos($auth_value, 'SignedHeaders=') !== false && 
                strpos($auth_value, 'Signature=') !== false) {
                $this->headers['Authorization'] = $auth_value;
            } 
            // Otherwise use it as a simple authorization token
            else {
                $this->headers['Authorization'] = $auth_value;
            }
        }
        
        // Initialize the helper
        $this->helper = new Bocs_Helper();
    }

    /**
     * Get headers for API requests
     *
     * @return array
     */
    private function get_headers() {
        return $this->headers;
    }

    /**
     * Updates the products in a subscription
     *
     * @param string $subscription_id The ID of the subscription to update
     * @param array  $data            The data containing line items to update
     * @return array|WP_Error         The response from the API or an error
     */
    public function update_subscription_products($subscription_id, $data) {
        
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        $curl = new Curl();
        
        // Log the raw request data for debugging
        error_log('DEBUG - Raw request data: ' . json_encode($data));
        
        // Try a simplified request to see if it works
        $simplified_request = [
            'lineItems' => []
        ];
        
        // Only include essential fields that match the API format
        if (isset($data['lineItems']) && is_array($data['lineItems'])) {
            foreach ($data['lineItems'] as $item) {
                if (isset($item['productId']) && isset($item['quantity'])) {
                    // Most minimal request possible
                    $line_item = [
                        'productId' => $item['productId'],
                        'quantity' => (int)$item['quantity'],
                        'name' => $item['name'],
                        'price' => (float)number_format((float)$item['price'], 2, '.', ''),
                        'total' => (float)number_format((float)$item['quantity'] * (float)$item['price'], 2, '.', ''),
                        'subtotal' => (float)number_format((float)$item['quantity'] * (float)$item['price'], 2, '.', ''),
                        'externalSourceId' => (string)$item['externalSourceId']
                    ];

                    if(!empty($item['sku'])) {
                        $line_item['sku'] = $item['sku'];
                    }
                    
                    if(!empty($item['parentName'])) {
                        $line_item['parentName'] = $item['parentName'];
                    }

                    if(!empty($item['variationId'])) {
                        $line_item['variationId'] = $item['variationId'];
                    }
                    

                    $simplified_request['lineItems'][] = $line_item;
                }
            }
        }
        
        error_log('DEBUG - Using simplified request: ' . json_encode($simplified_request));
        
        // Make the API request with simplified data first
        $response = $curl->put($url, $simplified_request, 'subscriptions', $subscription_id);
        
        // Extra debug logging for response
        if (is_wp_error($response)) {
            error_log('DEBUG - WP Error response: ' . $response->get_error_message());
        } else {
            error_log('DEBUG - API response: ' . json_encode($response));
        }
        
        if (is_wp_error($response)) {
            $this->helper->log('API request failed: ' . $response->get_error_message(), 'error');
            return $response;
        }
        
        // Check for error in response
        if (isset($response->error) && $response->error === true) {
            $this->helper->log('API returned error: ' . ($response->message ?? 'Unknown error'), 'error');
            return new WP_Error('api_error', $response->message ?? 'Unknown API error');
        }
        
        $this->helper->log('Subscription products updated successfully', 'info');
        return $response;
    }

    /**
     * Get a subscription by ID
     *
     * @param string $subscription_id The subscription ID
     * @return array|WP_Error The subscription data or a WP_Error object
     */
    public function get_subscription($subscription_id) {
        if (empty($subscription_id)) {
            return new WP_Error('missing_id', 'Subscription ID is required');
        }
        
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        
        $this->helper->log('Getting subscription data for ID: ' . $subscription_id, 'info');
        $this->helper->log('Request URL: ' . $url, 'info');
        
        $response = $this->helper->curl_request($url, 'GET', [], $this->headers);
        
        if (is_wp_error($response)) {
            $this->helper->log('Error getting subscription: ' . $response->get_error_message(), 'error');
            return $response;
        }
        
        if (!isset($response['code']) || $response['code'] !== 200) {
            $error_message = isset($response['message']) ? $response['message'] : 'Failed to retrieve subscription';
            $this->helper->log('API error: ' . $error_message, 'error');
            return new WP_Error('api_error', $error_message);
        }
        
        $this->helper->log('Successfully retrieved subscription data', 'info');
        return $response;
    }

    /**
     * Retrieves and formats products for a specific BOCS.
     *
     * Fetches the BOCS data from the API and extracts the products information.
     * The products data is formatted for frontend use, including essential details
     * like name, price, stock quantity, and images.
     *
     * @since 1.0.0
     *
     * @param string $bocs_id The unique identifier of the BOCS.
     * @return array|WP_Error {
     *     The formatted products data or WP_Error on failure.
     *
     *     @type int    $code    The response code from the API.
     *     @type string $message The response message from the API.
     *     @type array  $data    {
     *         Array of formatted product data.
     *
     *         @type array {
     *             Individual product data.
     *
     *             @type string $id           The external source ID of the product.
     *             @type string $name         The product name.
     *             @type float  $price        The product price.
     *             @type int    $max_quantity The maximum available quantity (stock).
     *             @type string $image_url    The URL of the product's primary image.
     *             @type string $description  The product description.
     *             @type string $sku          The product SKU.
     *         }
     *     }
     * }
     */
    public function get_bocs_products($bocs_id) {
        $url = BOCS_API_URL . 'bocs/' . $bocs_id;
        
        $response = wp_remote_get(
            $url,
            array(
                'headers' => $this->get_headers(),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        if ($response_code !== 200) {
            $error_message = 'Failed to get BOCS products.';
            
            // Handle 400 Bad Request specifically
            if ($response_code === 400) {
                // Try to parse error message from response
                $response_data = json_decode($response_body, true);
                if ($response_data && isset($response_data['message'])) {
                    $error_message .= ' Error: ' . $response_data['message'];
                } else {
                    $error_message .= ' Invalid request format or parameters.';
                }
            }

            return new WP_Error(
                'bocs_api_error',
                $error_message . ' (Status code: ' . $response_code . ')'
            );
        }

        $data = json_decode($response_body, true);

        if (!$data) {
            return new WP_Error(
                'bocs_api_error',
                'Invalid JSON response from API'
            );
        }

        if (!isset($data['data']) || !isset($data['data']['products'])) {
            return new WP_Error(
                'bocs_api_error',
                'Invalid BOCS data structure'
            );
        }

        // Format the products data for frontend while preserving the original data
        $formatted_products = array_map(function($product) {
            return array(
                'id' => $product['id'],
                'externalSourceId' => isset($product['externalSourceId']) ? $product['externalSourceId'] : '',
                'name' => $product['name'],
                'price' => $product['price'],
                'max_quantity' => $product['stockQuantity'],
                'image_url' => !empty($product['images']) ? $product['images'][0]['url'] : '',
                'description' => $product['description'],
                'sku' => $product['sku']
            );
        }, $data['data']['products']);

        // Return both the raw data and formatted products
        return $data;
    }

    /**
     * Helper method to get WooCommerce product ID from BOCS product ID
     * 
     * @param string $bocs_product_id The BOCS product ID
     * @return string|null WooCommerce product ID or null if not found
     */
    private function get_wc_product_id_from_bocs_id($bocs_product_id) {
        if (empty($bocs_product_id)) {
            $this->helper->log("Empty BOCS product ID provided to lookup function", 'warning');
            return null;
        }

        $this->helper->log("Looking up WooCommerce product ID for BOCS product: " . $bocs_product_id, 'info');
        
        // STEP 1: Check if we already have the BOCS data in the current request context
        global $bocs_current_data;
        if (isset($bocs_current_data)) {
            // Try to find the product in different possible structures
            
            // Check in data.products (from API response)
            if (isset($bocs_current_data['data']) && isset($bocs_current_data['data']['products'])) {
                foreach ($bocs_current_data['data']['products'] as $product) {
                    if (isset($product['id']) && $product['id'] === $bocs_product_id && 
                        isset($product['externalSourceId']) && !empty($product['externalSourceId'])) {
                        $this->helper->log("Found WC product ID in BOCS data.products: " . $product['externalSourceId'], 'info');
                        return (string)$product['externalSourceId'];
                    }
                }
            }
            
            // Check in products (older format or already processed data)
            if (isset($bocs_current_data['products'])) {
                foreach ($bocs_current_data['products'] as $product) {
                    if (isset($product['id']) && $product['id'] === $bocs_product_id && 
                        isset($product['externalSourceId']) && !empty($product['externalSourceId'])) {
                        $this->helper->log("Found WC product ID in BOCS products: " . $product['externalSourceId'], 'info');
                        return (string)$product['externalSourceId'];
                    }
                }
            }
        }
        
        // STEP 2: Check if this ID is in a current subscription's line items
        global $bocs_current_subscription;
        if (isset($bocs_current_subscription)) {
            // Check multiple possible places where line items might be stored
            
            // Check in top level of subscription
            if (isset($bocs_current_subscription['lineItems'])) {
                foreach ($bocs_current_subscription['lineItems'] as $item) {
                    if (isset($item['productId']) && $item['productId'] === $bocs_product_id && 
                        isset($item['externalSourceId']) && !empty($item['externalSourceId'])) {
                        $this->helper->log("Found WC product ID in subscription lineItems: " . $item['externalSourceId'], 'info');
                        return (string)$item['externalSourceId'];
                    }
                }
            }
            
            // Check in data.lineItems (API nested response)
            if (isset($bocs_current_subscription['data']) && isset($bocs_current_subscription['data']['lineItems'])) {
                foreach ($bocs_current_subscription['data']['lineItems'] as $item) {
                    if (isset($item['productId']) && $item['productId'] === $bocs_product_id && 
                        isset($item['externalSourceId']) && !empty($item['externalSourceId'])) {
                        $this->helper->log("Found WC product ID in subscription data.lineItems: " . $item['externalSourceId'], 'info');
                        return (string)$item['externalSourceId'];
                    }
                }
            }
            
            // Check for legacy format (line_items)
            if (isset($bocs_current_subscription['line_items'])) {
                foreach ($bocs_current_subscription['line_items'] as $item) {
                    if (isset($item['productId']) && $item['productId'] === $bocs_product_id && 
                        isset($item['externalSourceId']) && !empty($item['externalSourceId'])) {
                        $this->helper->log("Found WC product ID in subscription line_items: " . $item['externalSourceId'], 'info');
                        return (string)$item['externalSourceId'];
                    }
                }
            }
            
            // Also check data.line_items (legacy + nested)
            if (isset($bocs_current_subscription['data']) && isset($bocs_current_subscription['data']['line_items'])) {
                foreach ($bocs_current_subscription['data']['line_items'] as $item) {
                    if (isset($item['productId']) && $item['productId'] === $bocs_product_id && 
                        isset($item['externalSourceId']) && !empty($item['externalSourceId'])) {
                        $this->helper->log("Found WC product ID in subscription data.line_items: " . $item['externalSourceId'], 'info');
                        return (string)$item['externalSourceId'];
                    }
                }
            }
        }
        
        // STEP 3: Check in saved mappings as fallback
        $product_mapping = get_option('bocs_product_mapping', []);
        if (is_array($product_mapping) && isset($product_mapping[$bocs_product_id])) {
            $wc_product_id = $product_mapping[$bocs_product_id];
            $this->helper->log("Found WC product ID in saved mapping: " . $wc_product_id, 'info');
            return (string)$wc_product_id;
        }
        
        // STEP 4: Check global mapping if available
        global $bocs_product_mapping;
        if (isset($bocs_product_mapping) && is_array($bocs_product_mapping) && isset($bocs_product_mapping[$bocs_product_id])) {
            $wc_product_id = $bocs_product_mapping[$bocs_product_id];
            $this->helper->log("Found WC product ID in global mapping: " . $wc_product_id, 'info');
            return (string)$wc_product_id;
        }
        
        // STEP 5: Check if the BOCS ID is actually a SKU that matches a WooCommerce product
        global $wpdb;
        $wc_product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
            WHERE meta_key = '_sku' AND meta_value = %s 
            LIMIT 1",
            $bocs_product_id
        ));
        
        if ($wc_product_id) {
            $this->helper->log("Found WC product ID by SKU lookup: " . $wc_product_id, 'info');
            
            // Save for future reference
            $this->save_product_mapping($bocs_product_id, $wc_product_id);
            
            return (string)$wc_product_id;
        }
        
        // STEP 6: Check if this BOCS ID is stored in product meta
        $wc_product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
            WHERE meta_key = '_bocs_product_id' AND meta_value = %s 
            LIMIT 1",
            $bocs_product_id
        ));
        
        if ($wc_product_id) {
            $this->helper->log("Found WC product ID by _bocs_product_id meta: " . $wc_product_id, 'info');
            
            // Save for future reference
            $this->save_product_mapping($bocs_product_id, $wc_product_id);
            
            return (string)$wc_product_id;
        }
        
        // STEP 7: If ID is numeric, it might already be a WooCommerce product ID
        if (is_numeric($bocs_product_id)) {
            $product = wc_get_product($bocs_product_id);
            if ($product) {
                $this->helper->log("BOCS product ID appears to already be a valid WooCommerce product ID", 'info');
                return (string)$bocs_product_id;
            }
        }
        
        $this->helper->log("Could not find a WooCommerce product ID for BOCS product: " . $bocs_product_id, 'warning');
        return '';
    }
    
    /**
     * Get product name from BOCS ID if possible
     * 
     * @param string $bocs_product_id The BOCS product ID
     * @return string|null The product name if found, null otherwise
     */
    private function get_product_name_from_bocs_id($bocs_product_id) {
        // Check if we have this product in the current request context
        global $bocs_current_subscription;
        if (isset($bocs_current_subscription) && !empty($bocs_current_subscription['lineItems'])) {
            foreach ($bocs_current_subscription['lineItems'] as $item) {
                if (isset($item['productId']) && $item['productId'] === $bocs_product_id) {
                    return $item['name'] ?? null;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Save product mapping for future use
     * 
     * @param string $bocs_product_id The BOCS product ID
     * @param string $wc_product_id The WooCommerce product ID
     */
    private function save_product_mapping($bocs_product_id, $wc_product_id) {
        $product_mapping = get_option('bocs_product_mapping', []);
        $product_mapping[$bocs_product_id] = (string)$wc_product_id;
        update_option('bocs_product_mapping', $product_mapping);
    }
} 