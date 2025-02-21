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
                throw new Exception(__('API URL is required', 'bocs-wordpress'));
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

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                throw new Exception(
                    sprintf(
                        /* translators: %s: Error message */
                        __('Critical: API request failed: %s', 'bocs-wordpress'),
                        $response->get_error_message()
                    )
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Exception(
                    sprintf(
                        /* translators: %d: HTTP response code */
                        __('Critical: API returned non-200 response code: %d', 'bocs-wordpress'),
                        $response_code
                    )
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Critical: Failed to parse API response', 'bocs-wordpress'));
            }

            return $data;

        } catch (Exception $e) {
            error_log(sprintf(
                /* translators: %s: Error message */
                __('Critical: API Error: %s', 'bocs-wordpress'),
                $e->getMessage()
            ));
            return new WP_Error(
                'bocs_api_error',
                $e->getMessage(),
                ['status' => 400]
            );
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
            error_log(
                sprintf(
                    '[Bocs %s] %s',
                    strtoupper($level),
                    $message
                )
            );
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