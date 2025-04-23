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
     * Track recent API requests to prevent loops
     * 
     * @var array
     */
    private static $recent_requests = [];
    
    /**
     * Max number of allowed similar requests within the threshold time
     */
    const MAX_SIMILAR_REQUESTS = 3;
    
    /**
     * Threshold time in seconds for detecting duplicate requests
     */
    const REQUEST_THRESHOLD_TIME = 5;

    /**
     * Make an HTTP request to the BOCS API
     *
     * @param string $url API endpoint URL
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array  $data Request data
     * @param array  $headers Request headers
     * @return array|WP_Error Response data or WP_Error on failure
     */
    public function curl_request($url, $method = 'GET', $data = [], $headers = [])
    {
        try {
            // Generate a simplified request fingerprint for loop detection
            // Don't include all data/headers to avoid false positives
            $request_fingerprint = md5($url . $method);
            $current_time = time();
            
            // Get stack trace for debugging
            $debug_backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $calling_function = '';
            $calling_file = '';
            
            // Skip the first entry (it's this function)
            if (isset($debug_backtrace[1])) {
                $calling_function = isset($debug_backtrace[1]['function']) ? $debug_backtrace[1]['function'] : 'unknown';
                $calling_file = isset($debug_backtrace[1]['file']) ? basename($debug_backtrace[1]['file']) : 'unknown';
                $calling_line = isset($debug_backtrace[1]['line']) ? $debug_backtrace[1]['line'] : 'unknown';
                
                // For deeper inspection, capture the next caller too
                if (isset($debug_backtrace[2])) {
                    $parent_function = isset($debug_backtrace[2]['function']) ? $debug_backtrace[2]['function'] : 'unknown';
                    $parent_file = isset($debug_backtrace[2]['file']) ? basename($debug_backtrace[2]['file']) : 'unknown';
                    $parent_line = isset($debug_backtrace[2]['line']) ? $debug_backtrace[2]['line'] : 'unknown';
                    
                    $calling_function = "{$parent_function}() -> {$calling_function}()";
                    $calling_file = "{$parent_file}:{$parent_line} -> {$calling_file}:{$calling_line}";
                } else {
                    $calling_function .= '()';
                    $calling_file .= ':' . $calling_line;
                }
            }
            
            // Track the number of similar requests
            if (!isset(self::$recent_requests[$request_fingerprint])) {
                self::$recent_requests[$request_fingerprint] = [
                    'count' => 1,
                    'first_time' => $current_time,
                    'last_time' => $current_time,
                    'callers' => ["{$calling_file} in {$calling_function}"]
                ];
            } else {
                // Only count requests within the threshold time
                if ($current_time - self::$recent_requests[$request_fingerprint]['first_time'] <= self::REQUEST_THRESHOLD_TIME) {
                    self::$recent_requests[$request_fingerprint]['count']++;
                    self::$recent_requests[$request_fingerprint]['last_time'] = $current_time;
                    
                    // Track unique callers (up to 5)
                    $caller_key = "{$calling_file} in {$calling_function}";
                    if (!in_array($caller_key, self::$recent_requests[$request_fingerprint]['callers']) 
                        && count(self::$recent_requests[$request_fingerprint]['callers']) < 5) {
                        self::$recent_requests[$request_fingerprint]['callers'][] = $caller_key;
                    }
                    
                    // Check if we're potentially in a loop
                    if (self::$recent_requests[$request_fingerprint]['count'] > self::MAX_SIMILAR_REQUESTS) {
                        $message = sprintf(
                            'Potential API request loop detected: %s %s has been called %d times in %d seconds',
                            $method,
                            $url,
                            self::$recent_requests[$request_fingerprint]['count'],
                            $current_time - self::$recent_requests[$request_fingerprint]['first_time']
                        );
                        
                        // Log callers for debugging
                        error_log('BOCS API ERROR - ' . $message);
                        error_log('BOCS API ERROR - Current call from: ' . $calling_file . ' in ' . $calling_function);
                        error_log('BOCS API ERROR - Call stack: ' . implode(' | ', self::$recent_requests[$request_fingerprint]['callers']));
                        
                        return new WP_Error('bocs_api_loop', $message);
                    }
                } else {
                    // Reset counter if outside threshold
                    self::$recent_requests[$request_fingerprint] = [
                        'count' => 1,
                        'first_time' => $current_time,
                        'last_time' => $current_time,
                        'callers' => ["{$calling_file} in {$calling_function}"]
                    ];
                }
            }
            
            // Clean up old request records
            foreach (self::$recent_requests as $fp => $data) {
                if ($current_time - $data['last_time'] > self::REQUEST_THRESHOLD_TIME * 2) {
                    unset(self::$recent_requests[$fp]);
                }
            }

            if (empty($url)) {
                throw new Exception(__('API URL is required', 'bocs-wordpress'));
            }

            // Check if we have required authentication headers
            if (empty($headers['Organization']) || empty($headers['Store']) || empty($headers['Authorization'])) {
                // Try to get headers from options if not provided
                $options = get_option('bocs_plugin_options');
                if (!empty($options['bocs_headers'])) {
                    $headers = [
                        'Organization' => $options['bocs_headers']['organization'] ?? '',
                        'Store' => $options['bocs_headers']['store'] ?? '',
                        'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                        'Content-Type' => 'application/json'
                    ];
                }

                // If still missing required headers, log and fail
                if (empty($headers['Organization']) || empty($headers['Store']) || empty($headers['Authorization'])) {
                    throw new Exception(__('Missing required API authentication headers', 'bocs-wordpress'));
                }
            }

            // Handle AWS SigV4 authentication for API Gateway
            if (strpos($url, 'execute-api.') !== false && strpos($url, 'amazonaws.com') !== false) {
                // This appears to be an AWS API Gateway URL
                $parsed_url = parse_url($url);
                $host = $parsed_url['host'];
                $region = $this->extract_aws_region($host);
                
                // Add detailed logging for debugging
                error_log('BOCS API Debug - AWS API Gateway detected:');
                error_log('BOCS API Debug - URL: ' . $url);
                error_log('BOCS API Debug - Host: ' . $host);
                error_log('BOCS API Debug - Region: ' . $region);
                
                // Check for issues with existing headers that might cause looping
                $this->debug_aws_headers($headers);
                
                // Check if we already have AWS headers to prevent duplicates
                if (isset($headers['X-Amz-Date']) || isset($headers['X-Bocs-Authorization'])) {
                    error_log('BOCS API Debug - AWS headers already exist, skipping addition to avoid duplication');
                } else {
                    // Add AWS SigV4 required headers
                    $date = gmdate('Ymd\THis\Z');
                    $headers['X-Amz-Date'] = $date;
                    $headers['host'] = $host;
                    
                    // Add original Authorization token as custom header
                    if (isset($headers['Authorization'])) {
                        $headers['X-Bocs-Authorization'] = $headers['Authorization'];
                        error_log('BOCS API Debug - Authorization header copied to X-Bocs-Authorization');
                    }
                    
                    error_log('BOCS API Debug - Added AWS SigV4 headers to request - Date: ' . $date);
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
            }

            // Add body data for non-GET requests
            if ($method !== 'GET' && !empty($data)) {
                $args['body'] = wp_json_encode($data);
            }

            // Add detailed logging for request
            $log_url = preg_replace('/\?.*/', '?[query_params_redacted]', $url); // Redact query params
            error_log('BOCS API Request - URL: ' . $log_url);
            error_log('BOCS API Request - Method: ' . $method);
            
            // Log headers with sensitive data redacted
            $log_headers = $headers;
            if (isset($log_headers['Authorization'])) {
                $log_headers['Authorization'] = substr($log_headers['Authorization'], 0, 10) . '...';
            }
            if (isset($log_headers['X-Bocs-Authorization'])) {
                $log_headers['X-Bocs-Authorization'] = substr($log_headers['X-Bocs-Authorization'], 0, 10) . '...';
            }
            error_log('BOCS API Request - Headers: ' . json_encode($log_headers));
            
            // Log request body for debugging (redact sensitive data)
            if ($method !== 'GET' && !empty($data)) {
                $log_data = is_array($data) ? $data : json_decode($data, true);
                if (is_array($log_data)) {
                    // Redact sensitive fields
                    if (isset($log_data['card']) || isset($log_data['payment_method'])) {
                        $log_data = '[payment_data_redacted]';
                    }
                }
                error_log('BOCS API Request - Body: ' . json_encode($log_data));
            }
            
            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
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
            $response_headers = wp_remote_retrieve_headers($response);
            
            // Log response details
            error_log('BOCS API Response - Code: ' . $response_code);
            
            // Log response headers (useful for debugging auth issues)
            $log_response_headers = [];
            foreach ($response_headers as $header_name => $header_value) {
                // Skip sensitive headers
                if (in_array(strtolower($header_name), ['set-cookie', 'authorization', 'x-bocs-authorization'])) {
                    continue;
                }
                $log_response_headers[$header_name] = $header_value;
            }
            
            if (!empty($log_response_headers)) {
                error_log('BOCS API Response - Headers: ' . json_encode($log_response_headers));
            }
            
            // Truncate long responses for logging
            if (strlen($response_body) > 1000) {
                error_log('BOCS API Response - Body (truncated): ' . substr($response_body, 0, 500) . '...');
            } else {
                error_log('BOCS API Response - Body: ' . $response_body);
            }
            
            // Detect AWS API Gateway specific errors
            if ($response_code >= 400) {
                $response_json = json_decode($response_body, true);
                
                // Check for common API Gateway error patterns
                if (isset($response_json['message'])) {
                    if (strpos($response_json['message'], 'Missing Authentication Token') !== false) {
                        error_log('BOCS API Error - AWS API Gateway authentication error - Check URL path and authorization headers');
                    } else if (strpos($response_json['message'], 'Access Denied') !== false) {
                        error_log('BOCS API Error - AWS API Gateway access denied - Check IAM permissions and authorization tokens');
                    }
                }
            }

            if ($response_code < 200 || $response_code >= 300) {
                throw new Exception(
                    sprintf(
                        /* translators: %d: HTTP response code */
                        __('Critical: API returned non-success response code: %d - %s', 'bocs-wordpress'),
                        $response_code,
                        $response_body
                    )
                );
            }

            // Check if response body is empty
            if (empty($response_body)) {
                throw new Exception(__('Critical: Empty response from API', 'bocs-wordpress'));
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $json_error = json_last_error_msg();
                throw new Exception(__('Critical: Failed to parse API response', 'bocs-wordpress'));
            }

            return $data;
        } catch (Exception $e) {
            error_log('BOCS API Error: ' . $e->getMessage());
            return new WP_Error('bocs_api_error', $e->getMessage());
        }
    }
    
    /**
     * Extract AWS region from API Gateway host
     * 
     * @param string $host The API Gateway host
     * @return string The AWS region
     */
    private function extract_aws_region($host) {
        // API Gateway hosts are typically in the format: {id}.execute-api.{region}.amazonaws.com
        if (preg_match('/\.execute-api\.([^.]+)\.amazonaws\.com/', $host, $matches)) {
            return $matches[1];
        }
        
        // Default to us-east-1 if we can't extract the region
        return 'ap-southeast-2';
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

    /**
     * Debug AWS SigV4 headers for potential issues
     * 
     * @param array $headers Headers array to check
     * @return void
     */
    private function debug_aws_headers(&$headers) {
        // Check for case sensitivity issues
        $header_keys_lower = array_change_key_case($headers, CASE_LOWER);
        $problematic_headers = [];
        
        // Check for duplicate headers with different cases
        foreach (['x-amz-date', 'host', 'authorization', 'x-bocs-authorization'] as $key) {
            if (isset($header_keys_lower[$key])) {
                // Find all variations of this header
                $variations = array_filter(array_keys($headers), function($header) use ($key) {
                    return strtolower($header) === $key;
                });
                
                if (count($variations) > 1) {
                    $problematic_headers[$key] = $variations;
                    error_log('BOCS API Debug - Duplicate header found with different cases: ' . $key . ' as ' . implode(', ', $variations));
                    
                    // Keep only one variation (preferably the correct case for AWS)
                    $preferred_case = $key === 'host' ? 'host' : ($key === 'x-amz-date' ? 'X-Amz-Date' : $key);
                    
                    // If preferred case exists, use it, otherwise use the first one
                    $keep_header = in_array($preferred_case, $variations) ? $preferred_case : reset($variations);
                    
                    foreach ($variations as $variation) {
                        if ($variation !== $keep_header) {
                            error_log('BOCS API Debug - Removing duplicate header: ' . $variation . ' (keeping ' . $keep_header . ')');
                            unset($headers[$variation]);
                        }
                    }
                }
            }
        }
        
        // Check for API Gateway known requirements
        if (isset($headers['Host']) && !isset($headers['host'])) {
            error_log('BOCS API Debug - Converting "Host" header to lowercase "host" for AWS compatibility');
            $headers['host'] = $headers['Host'];
            unset($headers['Host']);
        }
        
        // Check for common header issues
        if (isset($headers['X-Amz-Date']) && isset($headers['x-amz-date'])) {
            error_log('BOCS API Debug - Both X-Amz-Date and x-amz-date headers found, using X-Amz-Date');
            unset($headers['x-amz-date']);
        }
    }
}
?>