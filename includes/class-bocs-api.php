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
        // Log incoming data for debugging
        $this->helper->log('Starting subscription products update for subscription ID: ' . $subscription_id, 'info');
        $this->helper->log('Incoming data: ' . json_encode($data), 'info');
        
        // Validate subscription ID
        if (empty($subscription_id)) {
            $this->helper->log('Invalid subscription ID provided', 'error');
            return new WP_Error('invalid_subscription_id', 'Invalid subscription ID provided');
        }

        // Get current subscription first
        $subscription = $this->get_subscription($subscription_id);
        if (is_wp_error($subscription)) {
            $this->helper->log('Error retrieving subscription: ' . $subscription->get_error_message(), 'error');
            return $subscription;
        }
        
        // Make the subscription data available globally for lookups
        global $bocs_current_subscription;
        $bocs_current_subscription = $subscription;
        
        // Get BOCS data if available
        $bocs_id = '';
        if (isset($subscription['bocs']['id']) && !empty($subscription['bocs']['id'])) {
            $bocs_id = $subscription['bocs']['id'];
        } else {
            // Look for BOCS ID in metadata
            if (isset($subscription['metaData']) && is_array($subscription['metaData'])) {
                foreach ($subscription['metaData'] as $meta) {
                    if (isset($meta['key']) && ($meta['key'] === '_bocs_id' || $meta['key'] === '__bocs_bocs_id')) {
                        $bocs_id = $meta['value'];
                        break;
                    }
                }
            }
        }
        
        // If we have a BOCS ID, get the BOCS data for direct product lookups
        if (!empty($bocs_id)) {
            $bocs_data = $this->get_bocs_products($bocs_id);
            if (!is_wp_error($bocs_data)) {
                // Store BOCS data in global for lookups
                global $bocs_current_data;
                $bocs_current_data = $bocs_data;
                $this->helper->log('BOCS data retrieved and stored for product lookups', 'info');
            } else {
                $this->helper->log('Could not retrieve BOCS data: ' . $bocs_data->get_error_message(), 'warning');
            }
        }
        
        // Get current subscription to have the latest data
        $current_sub = $this->get_subscription($subscription_id);
        if (is_wp_error($current_sub)) {
            return $current_sub;
        }

        // Check if we have line items
        $has_line_items = false;
        $existing_line_items = [];

        // Check the 'data' key in case we have a nested structure
        $current_data = isset($current_sub['data']) ? $current_sub['data'] : $current_sub;
        
        // Check for line_items (legacy format)
        if (isset($current_data['line_items']) && !empty($current_data['line_items'])) {
            $this->helper->log('Found line_items in current subscription data', 'info');
            $has_line_items = true;
            $existing_line_items = $current_data['line_items'];
        }
        
        // Check for lineItems (newer format)
        if (isset($current_data['lineItems']) && !empty($current_data['lineItems'])) {
            $this->helper->log('Found lineItems in current subscription data', 'info');
            $has_line_items = true;
            $existing_line_items = $current_data['lineItems'];
        }

        if (!$has_line_items) {
            $this->helper->log('No line items found in current subscription data', 'error');
            return new WP_Error('no_line_items', 'No line items found in current subscription data');
        }

        // Check if we're receiving simplified product data (id, name, quantity only)
        $simplified_product_data = false;
        if (isset($data[0]) && is_array($data[0])) {
            // This appears to be an array of products, not a structured update
            $this->helper->log('Received simplified product data format', 'info');
            $simplified_product_data = true;
            
            // Format the simplified data to the expected structure
            $line_items_data = [];
            foreach ($data as $item) {
                if (isset($item['id']) && isset($item['quantity'])) {
                    $line_items_data[] = [
                        'productId' => $item['id'],
                        'quantity' => intval($item['quantity'])
                    ];
                }
            }
            
            if (!empty($line_items_data)) {
                $data = ['lineItems' => $line_items_data];
            } else {
                $this->helper->log('No valid products found in simplified data', 'error');
                return new WP_Error('invalid_product_data', 'No valid products found in data');
            }
        }

        // Validate line items
        if (!isset($data['lineItems']) || empty($data['lineItems'])) {
            $this->helper->log('No line items provided in update data', 'error');
            return new WP_Error('missing_line_items', 'No line items provided for update');
        }

        // Process line items
        $line_items = [];
        $subtotal = 0;
        $total_tax = 0;
        $shipping_total = 0;
        $shipping_tax = 0;

        // Map existing products for reference
        $existing_products = [];
        foreach ($existing_line_items as $item) {
            $product_id = isset($item['productId']) ? $item['productId'] : '';
            if (!empty($product_id)) {
                $existing_products[$product_id] = $item;
            }
        }

        // Existing shipping item
        $shipping_item = null;
        foreach ($existing_line_items as $item) {
            if (isset($item['productId']) && $item['productId'] === 'shipping') {
                $shipping_item = $item;
                $shipping_total = isset($item['price']) ? floatval($item['price']) : 0;
                $shipping_tax = isset($item['totalTax']) ? floatval($item['totalTax']) : 0;
                break;
            }
        }

        // Process each line item
        foreach ($data['lineItems'] as $item) {
            // Skip shipping items - we'll handle separately
            if (isset($item['productId']) && $item['productId'] === 'shipping') {
                continue;
            }
            
            // Validate required fields
            if (!isset($item['productId']) || !isset($item['quantity'])) {
                $this->helper->log('Missing required fields in line item: ' . json_encode($item), 'error');
                continue;
            }

            $product_id = $item['productId'];
            $quantity = intval($item['quantity']);

            // Skip if quantity is zero
            if ($quantity <= 0) {
                continue;
            }

            // Debug log for received externalSourceId values
            if (isset($item['externalSourceId'])) {
                $ext_id_value = empty($item['externalSourceId']) ? 'empty string' : $item['externalSourceId'];
                $this->helper->log("Item {$product_id} received with externalSourceId: {$ext_id_value}", 'info');
            } else {
                $this->helper->log("Item {$product_id} has no externalSourceId field", 'info');
            }

            // Initialize line item with required fields
            $line_item = [
                'productId' => $product_id,
                'quantity' => $quantity
            ];

            // Copy values from existing product if available
            if (isset($existing_products[$product_id])) {
                $existing_item = $existing_products[$product_id];
                
                // Copy price if not provided
                if (!isset($item['price']) && isset($existing_item['price'])) {
                    $line_item['price'] = floatval($existing_item['price']);
                } else if (isset($item['price'])) {
                    $line_item['price'] = floatval($item['price']);
                } else {
                    $line_item['price'] = 0;
                }

                // Ensure externalSourceId is preserved
                if (isset($existing_item['externalSourceId']) && !empty($existing_item['externalSourceId'])) {
                    $line_item['externalSourceId'] = (string)$existing_item['externalSourceId'];
                    $this->helper->log('Preserved externalSourceId for product: ' . $product_id . ' -> ' . $line_item['externalSourceId'], 'info');
                } elseif (isset($item['externalSourceId']) && !empty($item['externalSourceId'])) {
                    $line_item['externalSourceId'] = (string)$item['externalSourceId'];
                    $this->helper->log('Using provided externalSourceId for product: ' . $product_id . ' -> ' . $line_item['externalSourceId'], 'info');
                } else {
                    // First check in BOCS products data - this is the most direct source
                    global $bocs_current_data;
                    if (isset($bocs_current_data) && !empty($bocs_current_data['data']) && !empty($bocs_current_data['data']['products'])) {
                        $products = $bocs_current_data['data']['products'];
                        foreach ($products as $bocs_product) {
                            if (isset($bocs_product['id']) && $bocs_product['id'] === $product_id) {
                                if (isset($bocs_product['externalSourceId']) && !empty($bocs_product['externalSourceId'])) {
                                    $line_item['externalSourceId'] = (string)$bocs_product['externalSourceId'];
                                    $this->helper->log('Found externalSourceId in BOCS products data: ' . $product_id . ' -> ' . $line_item['externalSourceId'], 'info');
                                    
                                    // Save this mapping for future reference
                                    $this->save_product_mapping($product_id, $line_item['externalSourceId']);
                                    break;
                                }
                            }
                        }
                    }
                    
                    // If still not found, use our lookup function
                    if (!isset($line_item['externalSourceId']) || empty($line_item['externalSourceId'])) {
                        $wc_product_id = $this->get_wc_product_id_from_bocs_id($product_id);
                        if ($wc_product_id) {
                            $line_item['externalSourceId'] = (string)$wc_product_id;
                            $this->helper->log('Found WooCommerce product ID for product: ' . $product_id . ' -> ' . $wc_product_id, 'info');
                        } else {
                            $line_item['externalSourceId'] = '';
                            $this->helper->log('WARNING: Could not find WooCommerce product ID for product: ' . $product_id, 'warning');
                        }
                    }
                }

                // Copy other fields if available
                $copy_fields = [
                    'name', 'sku', 'taxes', 'metaData', 'taxClass', 'parentName', 
                    'variationId', 'totalTax', 'subtotalTax'
                ];
                
                foreach ($copy_fields as $field) {
                    if (isset($existing_item[$field])) {
                        $line_item[$field] = $existing_item[$field];
                    }
                }
            } else {
                // Set default values for required fields
                if (isset($item['price'])) {
                    $line_item['price'] = floatval($item['price']);
                } else {
                    $line_item['price'] = 0;
                }
                
                // Make sure we set externalSourceId if available in the input data
                if (isset($item['externalSourceId']) && !empty($item['externalSourceId'])) {
                    $line_item['externalSourceId'] = (string)$item['externalSourceId'];
                    $this->helper->log('Using provided externalSourceId for new product: ' . $product_id . ' -> ' . $line_item['externalSourceId'], 'info');
                } else {
                    // First check in BOCS products data - this is the most direct source
                    global $bocs_current_data;
                    if (isset($bocs_current_data) && !empty($bocs_current_data['data']) && !empty($bocs_current_data['data']['products'])) {
                        $products = $bocs_current_data['data']['products'];
                        foreach ($products as $bocs_product) {
                            if (isset($bocs_product['id']) && $bocs_product['id'] === $product_id) {
                                if (isset($bocs_product['externalSourceId']) && !empty($bocs_product['externalSourceId'])) {
                                    $line_item['externalSourceId'] = (string)$bocs_product['externalSourceId'];
                                    $this->helper->log('Found externalSourceId in BOCS products data: ' . $product_id . ' -> ' . $line_item['externalSourceId'], 'info');
                                    
                                    // Save this mapping for future reference
                                    $this->save_product_mapping($product_id, $line_item['externalSourceId']);
                                    break;
                                }
                            }
                        }
                    }
                    
                    // If still not found, use our lookup function
                    if (!isset($line_item['externalSourceId']) || empty($line_item['externalSourceId'])) {
                        $wc_product_id = $this->get_wc_product_id_from_bocs_id($product_id);
                        if ($wc_product_id) {
                            $line_item['externalSourceId'] = (string)$wc_product_id;
                            $this->helper->log('Found WooCommerce product ID for new product: ' . $product_id . ' -> ' . $wc_product_id, 'info');
                        } else {
                            $line_item['externalSourceId'] = '';
                            $this->helper->log('WARNING: Could not find WooCommerce product ID for new product: ' . $product_id, 'warning');
                        }
                    }
                }
                
                // Set name if provided
                if (isset($item['name'])) {
                    $line_item['name'] = $item['name'];
                }
                
                // Set required fields with defaults
                $line_item['taxClass'] = '';
                $line_item['taxes'] = [];
                $line_item['metaData'] = [];
                $line_item['parentName'] = '';
                $line_item['variationId'] = '0';
                $line_item['sku'] = '';
                $line_item['totalTax'] = 0;
                $line_item['subtotalTax'] = 0;
            }

            // Calculate total for this line item
            $line_item['total'] = $line_item['price'] * $quantity;
            $line_item['subtotal'] = $line_item['total'];
            
            // Add to subtotal and tax totals
            $subtotal += $line_item['total'];
            $total_tax += isset($line_item['totalTax']) ? floatval($line_item['totalTax']) : 0;
            
            // Add to line items array
            $line_items[] = $line_item;
        }

        // Add shipping item if it exists
        if ($shipping_item) {
            $line_items[] = $shipping_item;
        }

        // Calculate discounts
        $discount_total = 0;
        $discount_tax = 0;
        $has_discount = false;
        $coupon_lines = [];

        // Check if discount data is provided
        if (isset($data['discountTotal']) && floatval($data['discountTotal']) > 0) {
            $discount_total = floatval($data['discountTotal']);
            $has_discount = true;
            
            // Use provided discount tax or default to 0
            $discount_tax = isset($data['discountTax']) ? floatval($data['discountTax']) : 0;
            
            // Check for coupon lines
            if (isset($data['couponLines']) && is_array($data['couponLines'])) {
                $coupon_lines = $data['couponLines'];
            } else if ($discount_total > 0) {
                // Create a default coupon line if not provided
                $coupon_code = 'bocs-auto-' . date('Ymd-His');
                $coupon_lines = [
                    [
                        'code' => $coupon_code,
                        'discount' => $discount_total,
                        'discountTax' => $discount_tax
                    ]
                ];
            }
        } else if (isset($current_data['discountTotal']) && floatval($current_data['discountTotal']) > 0) {
            // Use existing discount if none provided
            $discount_total = floatval($current_data['discountTotal']);
            $discount_tax = isset($current_data['discountTax']) ? floatval($current_data['discountTax']) : 0;
            $has_discount = true;
            
            // Use existing coupon lines if available
            if (isset($current_data['couponLines']) && !empty($current_data['couponLines'])) {
                $coupon_lines = $current_data['couponLines'];
            } else {
                // Create a default coupon line
                $coupon_code = 'bocs-auto-' . date('Ymd-His');
                $coupon_lines = [
                    [
                        'code' => $coupon_code,
                        'discount' => $discount_total,
                        'discountTax' => $discount_tax
                    ]
                ];
            }
        }

        // Calculate final total
        $final_total = $subtotal - $discount_total + $total_tax + $shipping_total + $shipping_tax;

        // Build request with essential data from current subscription
        $request_data = [];
        
        // Always start with what was provided in the data
        if (is_array($data)) {
            $request_data = $data;
        }
        
        // Preserve essential fields from the current subscription
        $essential_fields = [];
        
        foreach ($essential_fields as $field) {
            if (!isset($request_data[$field]) && isset($current_data[$field])) {
                $request_data[$field] = $current_data[$field];
                $this->helper->log('Added field to request data: ' . $field, 'info');
            }
        }
        
        // Ensure we have properly formatted lineItems
        $request_data['lineItems'] = $line_items;
        
        // Calculate total
        $total = $subtotal - $discount_total + $shipping_total;
        $request_data['total'] = $total;
        $request_data['subtotal'] = $subtotal;
        
        if ($shipping_total > 0) {
            $request_data['shippingTotal'] = $shipping_total;
        }
        
        if ($total_tax > 0) {
            $request_data['totalTax'] = $total_tax + $shipping_tax;
        }
        
        if ($has_discount) {
            $request_data['discountTotal'] = $discount_total;
            if (!empty($coupon_lines)) {
                $request_data['couponLines'] = $coupon_lines;
            }
        }
        
        // Log request data for debugging
        $this->helper->log('Request data: ' . json_encode($request_data), 'info');
        
        // Make API request - use the main subscription endpoint (full URL for clarity)
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        $this->helper->log('Using subscription endpoint: ' . $url, 'info');
        
        $curl = new Curl();
        
        // Create a request with ONLY the line items field
        $api_request = [
            'lineItems' => []
        ];
        
        // Extract only the required fields from each line item
        foreach ($line_items as $item) {
            $simplified_item = [
                'productId' => $item['productId'],
                'quantity' => $item['quantity']
            ];
            
            // Add external source ID if available (very important!)
            if (isset($item['externalSourceId']) && !empty($item['externalSourceId'])) {
                $simplified_item['externalSourceId'] = (string)$item['externalSourceId'];
            }
            
            $api_request['lineItems'][] = $simplified_item;
        }
        
        // Include shipping item if available in the same simplified format
        if ($shipping_item) {
            $shipping = [
                'productId' => 'shipping',
                'quantity' => 1
            ];
            
            if (isset($shipping_item['price'])) {
                $shipping['price'] = floatval($shipping_item['price']);
            }
            
            $api_request['lineItems'][] = $shipping;
        }
        
        $this->helper->log('Simplified API request data: ' . json_encode($api_request), 'info');
        
        // Make the API request with the simplified data
        $response = $curl->put($url, $api_request, 'subscriptions', $subscription_id);
        
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
        
        error_log('BOCS API Request - Getting products for BOCS ID: ' . $bocs_id);
        error_log('BOCS API URL: ' . $url);
        error_log('BOCS API Headers: ' . print_r($this->get_headers(), true));
        
        $response = wp_remote_get(
            $url,
            array(
                'headers' => $this->get_headers(),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            error_log('BOCS API Error - WP Error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        error_log('BOCS API Response Code: ' . $response_code);
        error_log('BOCS API Response Headers: ' . print_r($response_headers, true));
        
        if ($response_code !== 200) {
            $error_message = 'Failed to get BOCS products.';
            
            // Handle 400 Bad Request specifically
            if ($response_code === 400) {
                error_log('BOCS API Error - 400 Bad Request');
                error_log('Request URL: ' . $url);
                error_log('Request Headers: ' . print_r($this->get_headers(), true));
                error_log('Response Body: ' . $response_body);
                
                // Try to parse error message from response
                $response_data = json_decode($response_body, true);
                if ($response_data && isset($response_data['message'])) {
                    $error_message .= ' Error: ' . $response_data['message'];
                } else {
                    $error_message .= ' Invalid request format or parameters.';
                }
            } else {
                error_log('BOCS API Error - Non-200 Status Code: ' . $response_code);
                error_log('BOCS API Response: ' . $response_body);
            }

            return new WP_Error(
                'bocs_api_error',
                $error_message . ' (Status code: ' . $response_code . ')'
            );
        }

        $data = json_decode($response_body, true);

        if (!$data) {
            error_log('BOCS API Error - Invalid JSON response: ' . $response_body);
            return new WP_Error(
                'bocs_api_error',
                'Invalid JSON response from API'
            );
        }

        if (!isset($data['data']) || !isset($data['data']['products'])) {
            error_log('BOCS API Error - Invalid data structure. Response: ' . print_r($data, true));
            return new WP_Error(
                'bocs_api_error',
                'Invalid BOCS data structure'
            );
        }

        if (empty($data['data']['products'])) {
            error_log('BOCS API Notice - No products found for BOCS ID: ' . $bocs_id);
        } else {
            error_log('BOCS API Success - Found ' . count($data['data']['products']) . ' products');
            
            // Check for externalSourceId in products and log a warning if missing
            foreach ($data['data']['products'] as $product) {
                if (!isset($product['externalSourceId']) || empty($product['externalSourceId'])) {
                    error_log('BOCS API Warning - Product missing externalSourceId: ' . json_encode($product));
                }
            }
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

        error_log('BOCS API - Formatted ' . count($formatted_products) . ' products for frontend');

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