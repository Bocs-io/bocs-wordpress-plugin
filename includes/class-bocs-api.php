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

        if (! empty($options['bocs_headers']['organization']) && ! empty($options['bocs_headers']['store']) && ! empty($options['bocs_headers']['authorization'])) {
            $this->headers = [
                'Organization' => $options['bocs_headers']['organization'] ?? '',
                'Store' => $options['bocs_headers']['store'] ?? '',
                'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                'Content-Type' => 'application/json'
            ];
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
        $this->helper->log('Updating products for subscription: ' . $subscription_id, 'info');
        $this->helper->log('Update data: ' . json_encode($data), 'info');

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

                // Copy other fields if available
                $copy_fields = [
                    'name', 'sku', 'taxes', 'metaData', 'taxClass', 'parentName', 
                    'variationId', 'externalSourceId', 'totalTax', 'subtotalTax'
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

        // Prepare request data
        $request_data = [
            'lineItems' => $line_items,
            'subtotal' => round($subtotal, 2),
            'total' => round($final_total, 2),
            'totalTax' => round($total_tax + $shipping_tax, 2)
        ];

        // Add discount fields if there's a discount
        if ($has_discount) {
            $request_data['discountTotal'] = round($discount_total, 2);
            $request_data['discountTax'] = round($discount_tax, 2);
            $request_data['couponLines'] = $coupon_lines;
        }

        // Add shipping fields if there's shipping
        if ($shipping_total > 0) {
            $request_data['shippingTotal'] = round($shipping_total, 2);
            $request_data['shippingTax'] = round($shipping_tax, 2);
        }

        // Add cart tax
        $request_data['cartTax'] = round($total_tax, 2);

        // Add frequency fields if provided
        if (isset($data['frequency'])) {
            $request_data['frequency'] = $data['frequency'];
            
            // Add billing fields if not already provided
            if (isset($data['frequency']['frequency']) && !isset($data['billingInterval'])) {
                $request_data['billingInterval'] = $data['frequency']['frequency'];
            }
            
            if (isset($data['frequency']['timeUnit']) && !isset($data['billingPeriod'])) {
                $request_data['billingPeriod'] = strtolower($data['frequency']['timeUnit']);
            }
        }

        // Add additional fields if provided
        $extra_fields = [
            'billingInterval', 'billingPeriod', 'bocsId', 
            'frequencyId', 'dateCreated', 'dateModified'
        ];
        
        foreach ($extra_fields as $field) {
            if (isset($data[$field])) {
                $request_data[$field] = $data[$field];
            }
        }

        // Log the request data
        $this->helper->log('Prepared subscription update data: ' . json_encode($request_data), 'info');

        // Make the request to update the subscription
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        
        $this->helper->log('Making PUT request to: ' . $url, 'info');
        
        $response = $this->helper->curl_request($url, 'PUT', $request_data, $this->headers);
        
        // Handle the response
        if (is_wp_error($response)) {
            $this->helper->log('Error updating subscription: ' . $response->get_error_message(), 'error');
            return $response;
        }

        // Check for success
        if (isset($response['code']) && $response['code'] === 200) {
            $this->helper->log('Successfully updated subscription products', 'info');
            return $response;
        } else {
            $error_message = isset($response['message']) ? $response['message'] : 'Failed to update subscription';
            $this->helper->log('API error: ' . $error_message, 'error');
            return new WP_Error('api_error', $error_message);
        }
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
        }

        // Format the products data for the frontend
        $products = array_map(function($product) {
            return array(
                'id' => $product['externalSourceId'],
                'name' => $product['name'],
                'price' => $product['price'],
                'max_quantity' => $product['stockQuantity'],
                'image_url' => !empty($product['images']) ? $product['images'][0]['url'] : '',
                'description' => $product['description'],
                'sku' => $product['sku']
            );
        }, $data['data']['products']);

        error_log('BOCS API - Formatted ' . count($products) . ' products for frontend');

        return array(
            'code' => $data['code'],
            'message' => $data['message'],
            'data' => $products
        );
    }
} 