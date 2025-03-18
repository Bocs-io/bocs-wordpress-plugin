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