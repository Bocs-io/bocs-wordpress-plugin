<?php
/**
 * Bocs Helper Class
 *
 * Provides utility functions for the Bocs plugin.
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 * @since      0.0.1
 */

class Bocs_Helper
{

    /**
     * Make a cURL request to the Bocs API
     *
     * @since 0.0.1
     * @param string $url The API endpoint URL
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array  $data Request data
     * @param array  $headers Request headers
     * @return array|WP_Error Response data or WP_Error on failure
     */
    public function curl_request($url, $method = 'GET', $data = [], $headers = [])
    {
        try {
            if (empty($url)) {
                // error_log("BOCS API DEBUG: Empty URL provided to curl_request");
                throw new Exception(__('API URL is required', 'bocs-wordpress'));
            }

            // error_log("BOCS API DEBUG: Making {$method} request to {$url}");

            // Check if we have required authentication headers
            if (empty($headers['Organization']) || empty($headers['Store']) || empty($headers['Authorization'])) {
                // error_log("BOCS API DEBUG: Missing headers, attempting to get from options");
                // Try to get headers from options if not provided
                $options = get_option('bocs_plugin_options');
                if (!empty($options['bocs_headers'])) {
                    $headers = [
                        'Organization' => $options['bocs_headers']['organization'] ?? '',
                        'Store' => $options['bocs_headers']['store'] ?? '',
                        'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                        'Content-Type' => 'application/json'
                    ];
                    // error_log("BOCS API DEBUG: Headers retrieved from options: " . 
                    //          "Organization=" . substr($headers['Organization'], 0, 5) . "..., " .
                    //          "Store=" . substr($headers['Store'], 0, 5) . "...");
                }

                // If still missing required headers, log and fail
                if (empty($headers['Organization']) || empty($headers['Store']) || empty($headers['Authorization'])) {
                    // error_log("BOCS API DEBUG: Still missing required headers after options fetch");
                    throw new Exception(__('Missing required API authentication headers', 'bocs-wordpress'));
                }
            }

            $args = [
                'method'      => $method,
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => $headers,
                'cookies'     => []
            ];

            // Add query parameters for GET requests
            if ($method === 'GET' && !empty($data)) {
                $url = add_query_arg($data, $url);
                // error_log("BOCS API DEBUG: Added query params to URL: {$url}");
            }

            // Add body data for non-GET requests
            if ($method !== 'GET' && !empty($data)) {
                $args['body'] = wp_json_encode($data);
                // error_log("BOCS API DEBUG: Added request body: " . wp_json_encode($data));
            }

            // error_log("BOCS API DEBUG: Sending request with args: " . print_r($args, true));
            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                // error_log("BOCS API DEBUG: wp_remote_request failed: {$error_message}");
                throw new Exception(
                    sprintf(
                        /* translators: %s: Error message */
                        __('Critical: API request failed: %s', 'bocs-wordpress'),
                        $error_message
                    )
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            // error_log("BOCS API DEBUG: Response code: {$response_code}");
            
            // Truncate long responses in logs to prevent log file bloat
            $log_body = (strlen($response_body) > 1000) ? 
                substr($response_body, 0, 500) . "... [truncated " . (strlen($response_body) - 1000) . " chars] ..." . substr($response_body, -500) : 
                $response_body;
            // error_log("BOCS API DEBUG: Response body (may be truncated): {$log_body}");

            if ($response_code !== 200) {
                // error_log("BOCS API DEBUG: Non-success status code {$response_code}");
                throw new Exception(
                    sprintf(
                        /* translators: %d: HTTP response code */
                        __('Critical: API returned non-200 response code: %d', 'bocs-wordpress'),
                        $response_code
                    )
                );
            }

            // Check if response body is empty
            if (empty($response_body)) {
                // error_log("BOCS API DEBUG: Empty response body from API");
                throw new Exception(__('Critical: Empty response from API', 'bocs-wordpress'));
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $json_error = json_last_error_msg();
                // error_log("BOCS API DEBUG: JSON parse error: {$json_error}");
                // error_log("BOCS API DEBUG: Invalid JSON response: " . substr($body, 0, 500));
                throw new Exception(__('Critical: Failed to parse API response', 'bocs-wordpress'));
            }

            // error_log("BOCS API DEBUG: Successfully parsed response JSON");
            
            // If data is empty or missing, log it
            if (empty($data) || !isset($data['data'])) {
                // error_log("BOCS API DEBUG: Response data is empty or missing 'data' key: " . print_r($data, true));
            } else {
                // Log a sample of the data to avoid huge log entries
                $data_sample = print_r($data, true);
                $data_sample = (strlen($data_sample) > 1000) ? 
                    substr($data_sample, 0, 500) . "... [truncated " . (strlen($data_sample) - 1000) . " chars] ..." . substr($data_sample, -500) : 
                    $data_sample;
                // error_log("BOCS API DEBUG: Response contains data: " . $data_sample);
                
                // Basic validation of response structure
                if (isset($data['data']) && $data['code'] == 200) {
                    // Validate data structure based on URL path
                    if (strpos($url, '/bocs/') !== false && !isset($data['data']['products'])) {
                        // error_log("BOCS API DEBUG: Bocs endpoint missing products array in response");
                    } else if (strpos($url, '/collections/') !== false && !isset($data['data']['products'])) {
                        // error_log("BOCS API DEBUG: Collection endpoint missing products array in response");
                    }
                }
            }

            return $data;
        } catch (Exception $e) {
            // error_log("BOCS API DEBUG: Exception in curl_request: " . $e->getMessage());
            return new WP_Error('bocs_api_error', $e->getMessage());
        }
    }

    /**
     * Format a price with currency symbol
     *
     * @since 0.0.1
     * @param float  $price Price to format
     * @param string $currency Currency code
     * @return string Formatted price
     */
    public function format_price($price, $currency = '')
    {
        if (empty($currency)) {
            $currency = get_woocommerce_currency();
        }

        return wc_price($price, ['currency' => $currency]);
    }

    /**
     * Get subscription interval label
     *
     * @since 0.0.1
     * @param string $interval Subscription interval (day, week, month, year)
     * @param int    $interval_count Number of intervals
     * @return string Formatted interval label
     */
    public function get_interval_label($interval, $interval_count = 1)
    {
        $intervals = [
            'day' => _n('day', 'days', $interval_count, 'bocs-wordpress'),
            'week' => _n('week', 'weeks', $interval_count, 'bocs-wordpress'),
            'month' => _n('month', 'months', $interval_count, 'bocs-wordpress'),
            'year' => _n('year', 'years', $interval_count, 'bocs-wordpress')
        ];

        if (!isset($intervals[$interval])) {
            return '';
        }

        if ($interval_count === 1) {
            return sprintf(
                /* translators: %s: Interval period (day/week/month/year) */
                __('Every %s', 'bocs-wordpress'),
                $intervals[$interval]
            );
        }

        return sprintf(
            /* translators: 1: Frequency number, 2: Time unit */
            __('Every %1$d %2$s', 'bocs-wordpress'),
            $interval_count,
            $intervals[$interval]
        );
    }

    /**
     * Validate date string
     *
     * @since 0.0.1
     * @param string $date Date string to validate
     * @param string $format Expected date format
     * @return bool True if date is valid
     */
    public function validate_date($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Log message to debug log
     *
     * @since 0.0.1
     * @param mixed  $message Message to log
     * @param string $level Log level (debug, info, warning, error)
     * @return void
     */
    public function log($message, $level = 'debug')
    {
        if ($level !== 'error') {
            return; // Only log critical errors
        }

        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            // error_log(
            //     sprintf(
            //         '[Bocs %s] %s',
            //         strtoupper($level),
            //         $message
            //     )
            // );
        }
    }

    /**
     * Sanitize and validate webhook payload
     *
     * @since 0.0.1
     * @param array $payload Raw webhook payload
     * @return array|WP_Error Sanitized payload or error
     */
    public function sanitize_webhook_payload($payload)
    {
        if (!is_array($payload)) {
            return new WP_Error(
                'invalid_payload',
                __('Invalid webhook payload format', 'bocs-wordpress')
            );
        }

        $sanitized = [];
        $required_fields = ['id', 'event_type', 'data'];

        foreach ($required_fields as $field) {
            if (!isset($payload[$field])) {
                return new WP_Error(
                    'missing_field',
                    sprintf(
                        /* translators: %s: Field name */
                        __('Missing required field: %s', 'bocs-wordpress'),
                        $field
                    )
                );
            }
        }

        $sanitized['id'] = sanitize_text_field($payload['id']);
        $sanitized['event_type'] = sanitize_text_field($payload['event_type']);
        $sanitized['data'] = $this->sanitize_webhook_data($payload['data']);

        return $sanitized;
    }

    /**
     * Recursively sanitize webhook data
     *
     * @since 0.0.1
     * @param array $data Raw data to sanitize
     * @return array Sanitized data
     */
    private function sanitize_webhook_data($data)
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_webhook_data($value);
            } else {
                $sanitized[$key] = is_numeric($value) ? $value : sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
}
?>