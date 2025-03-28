<?php

/**
 * BOCS Payment Methods Handler
 *
 * Handles payment method related functionality for BOCS plugin
 */
class Bocs_Payment_Methods {

    /** @var Bocs_Log_Handler Logger instance */
    private $logger;

    /**
     * Initialize the payment methods handler
     */
    public function __construct() {
        // Initialize logger
        $this->logger = new Bocs_Log_Handler();
        
        // Add filter to ensure proper payment method data formatting
        add_filter('woocommerce_payment_methods_list_item', array($this, 'format_payment_method_data'), 10, 2);
        
        // Add filter to ensure payment methods are properly formatted before template
        add_filter('woocommerce_get_customer_payment_tokens', array($this, 'format_payment_tokens'), 10, 3);
        
        // Add filter to ensure saved methods are properly formatted
        add_filter('woocommerce_get_customer_saved_methods_list', array($this, 'format_saved_methods'), 10, 1);
        
        // Add filter to ensure payment method columns are properly formatted
        add_filter('woocommerce_account_payment_methods_columns', array($this, 'format_payment_method_columns'), 10, 1);

        // Add filter to ensure payment methods are properly formatted before template rendering
        add_filter('woocommerce_account_payment_methods', array($this, 'format_payment_methods_before_template'), 10, 1);
    }

    /**
     * Format payment method columns to ensure they exist
     *
     * @param array $columns Array of column definitions
     * @return array Formatted columns
     */
    public function format_payment_method_columns($columns) {
        $this->log_debug('Formatting payment method columns', [
            'original_columns' => $columns
        ]);

        if (!is_array($columns)) {
            $this->log_warning('Columns is not an array', [
                'columns_type' => gettype($columns)
            ]);
            return array(
                'method' => __('Method', 'woocommerce'),
                'expires' => __('Expires', 'woocommerce'),
                'actions' => __('Actions', 'woocommerce'),
            );
        }

        return wp_parse_args($columns, array(
            'method' => __('Method', 'woocommerce'),
            'expires' => __('Expires', 'woocommerce'),
            'actions' => __('Actions', 'woocommerce'),
        ));
    }

    /**
     * Format saved payment methods before they reach the template
     *
     * @param array $saved_methods Array of saved payment methods
     * @return array Formatted saved methods
     */
    public function format_saved_methods($saved_methods) {
        $this->log_debug('Formatting saved methods', [
            'original_data' => $saved_methods,
            'data_type' => gettype($saved_methods)
        ]);

        if (!is_array($saved_methods)) {
            $this->log_warning('Saved methods is not an array', [
                'saved_methods_type' => gettype($saved_methods)
            ]);
            return array();
        }

        $formatted_methods = array();

        foreach ($saved_methods as $type => $methods) {
            if (!is_array($methods)) {
                $this->log_warning('Methods for type is not an array', [
                    'type' => $type,
                    'methods_type' => gettype($methods)
                ]);
                $formatted_methods[$type] = array();
                continue;
            }

            $formatted_methods[$type] = array();

            foreach ($methods as $key => $method) {
                // If method is a string, convert it to proper array structure
                if (is_string($method)) {
                    $this->log_warning('Method is a string, converting to array', [
                        'type' => $type,
                        'key' => $key,
                        'method' => $method
                    ]);
                    $formatted_methods[$type][] = array(
                        'method' => array(
                            'last4' => '',
                            'brand' => $method,
                        ),
                        'expires' => '',
                        'is_default' => false,
                        'actions' => array(),
                    );
                }
                // If method is an array but missing required structure
                elseif (is_array($method)) {
                    // Ensure method has required structure
                    $formatted_method = wp_parse_args($method, array(
                        'method' => array(
                            'last4' => '',
                            'brand' => '',
                        ),
                        'expires' => '',
                        'is_default' => false,
                        'actions' => array(),
                    ));

                    // Ensure method.method has required structure
                    if (!isset($formatted_method['method']) || !is_array($formatted_method['method'])) {
                        $formatted_method['method'] = array(
                            'last4' => '',
                            'brand' => '',
                        );
                    } else {
                        // Ensure method.method has required keys
                        $formatted_method['method'] = wp_parse_args($formatted_method['method'], array(
                            'last4' => '',
                            'brand' => '',
                        ));
                    }

                    $formatted_methods[$type][] = $formatted_method;
                }
                // If method is neither string nor array, log warning and skip
                else {
                    $this->log_warning('Invalid method type', [
                        'type' => $type,
                        'key' => $key,
                        'method_type' => gettype($method)
                    ]);
                    continue;
                }
            }
        }

        $this->log_debug('Saved methods formatted successfully', [
            'final_data' => $formatted_methods
        ]);

        return $formatted_methods;
    }

    /**
     * Format payment tokens before they reach the template
     *
     * @param array $tokens Array of payment tokens
     * @param int $customer_id Customer ID
     * @param string $type Token type
     * @return array Formatted tokens
     */
    public function format_payment_tokens($tokens, $customer_id, $type) {
        $this->log_debug('Formatting payment tokens', [
            'customer_id' => $customer_id,
            'type' => $type,
            'token_count' => count($tokens)
        ]);

        if (!is_array($tokens)) {
            $this->log_warning('Tokens is not an array', [
                'tokens_type' => gettype($tokens)
            ]);
            return array();
        }

        foreach ($tokens as $token_id => $token) {
            if (!is_object($token) || !method_exists($token, 'get_token_data')) {
                $this->log_warning('Invalid token object', [
                    'token_id' => $token_id,
                    'token_type' => gettype($token)
                ]);
                continue;
            }

            $token_data = $token->get_token_data();
            if (!is_array($token_data)) {
                $this->log_warning('Token data is not an array', [
                    'token_id' => $token_id,
                    'token_data_type' => gettype($token_data)
                ]);
                continue;
            }

            // Ensure token data has required structure
            $token_data = wp_parse_args($token_data, array(
                'last4' => '',
                'brand' => '',
                'expiry_month' => '',
                'expiry_year' => '',
                'is_default' => false
            ));

            $token->set_token_data($token_data);
        }

        return $tokens;
    }

    /**
     * Format payment method data to ensure it's in the correct format
     *
     * @param array|string $method_data The payment method data
     * @param WC_Payment_Token $payment_token The payment token object
     * @return array The formatted payment method data
     */
    public function format_payment_method_data($method_data, $payment_token) {
        // Log incoming data for debugging
        $this->log_debug('Formatting payment method data', [
            'original_data' => $method_data,
            'token_type' => get_class($payment_token),
            'token_id' => $payment_token->get_id(),
            'data_type' => gettype($method_data)
        ]);

        // If method_data is not an array, try to decode it if it's a JSON string
        if (!is_array($method_data)) {
            if (is_string($method_data) && !empty($method_data)) {
                $decoded = json_decode($method_data, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $method_data = $decoded;
                    $this->log_debug('Successfully decoded JSON string to array');
                } else {
                    $this->log_warning('Unable to decode payment method string data', [
                        'data' => $method_data,
                        'json_error' => json_last_error_msg()
                    ]);
                }
            }
        }

        // If still not an array or empty, create default structure
        if (!is_array($method_data) || empty($method_data)) {
            $this->log_debug('Creating default payment method structure');
            
            // Get basic information from the token if available
            $method_data = array(
                'method' => array(
                    'last4' => $payment_token->get_last4() ?: '',
                    'brand' => $payment_token->get_card_type() ?: '',
                ),
                'expires' => $payment_token->get_expiry_month() ? 
                    sprintf('%s/%s', $payment_token->get_expiry_month(), $payment_token->get_expiry_year()) : '',
                'is_default' => $payment_token->get_is_default(),
                'actions' => array(),
            );
        }

        // Ensure the method key exists and is an array
        if (!isset($method_data['method']) || !is_array($method_data['method'])) {
            $this->log_debug('Ensuring method key exists and is array');
            $method_data['method'] = array(
                'last4' => $payment_token->get_last4() ?: '',
                'brand' => $payment_token->get_card_type() ?: '',
            );
        }

        // Ensure required keys exist in method array
        $method_data['method'] = wp_parse_args($method_data['method'], array(
            'last4' => '',
            'brand' => '',
        ));

        // Ensure other required keys exist
        $method_data = wp_parse_args($method_data, array(
            'expires' => '',
            'is_default' => false,
            'actions' => array(),
        ));

        // Log the final formatted data
        $this->log_debug('Payment method data formatted successfully', [
            'final_data' => $method_data
        ]);

        return $method_data;
    }

    /**
     * Format payment methods before they reach the template
     *
     * @param array $payment_methods Array of payment methods
     * @return array Formatted payment methods
     */
    public function format_payment_methods_before_template($payment_methods) {
        $this->log_debug('Formatting payment methods before template', [
            'original_data' => $payment_methods,
            'data_type' => gettype($payment_methods)
        ]);

        if (!is_array($payment_methods)) {
            $this->log_warning('Payment methods is not an array', [
                'payment_methods_type' => gettype($payment_methods)
            ]);
            return array();
        }

        foreach ($payment_methods as $key => $method) {
            // If method is a string, convert it to proper array structure
            if (is_string($method)) {
                $this->log_warning('Method is a string, converting to array', [
                    'key' => $key,
                    'method' => $method
                ]);
                $payment_methods[$key] = array(
                    'method' => array(
                        'last4' => '',
                        'brand' => $method,
                    ),
                    'expires' => '',
                    'is_default' => false,
                    'actions' => array(),
                );
            }
            // If method is an array but missing required structure
            elseif (is_array($method) && (!isset($method['method']) || !is_array($method['method']))) {
                $this->log_warning('Method array missing required structure', [
                    'key' => $key,
                    'method' => $method
                ]);
                $payment_methods[$key] = wp_parse_args($method, array(
                    'method' => array(
                        'last4' => '',
                        'brand' => '',
                    ),
                    'expires' => '',
                    'is_default' => false,
                    'actions' => array(),
                ));
            }
            // Ensure method.method has required structure
            elseif (is_array($method) && isset($method['method'])) {
                $payment_methods[$key]['method'] = wp_parse_args($method['method'], array(
                    'last4' => '',
                    'brand' => '',
                ));
            }
        }

        $this->log_debug('Payment methods formatted successfully', [
            'final_data' => $payment_methods
        ]);

        return $payment_methods;
    }

    /**
     * Log a debug message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    private function log_debug($message, $context = []) {
        if ($this->logger) {
            $this->logger->insert_log('debug', '[Payment Methods] ' . $message, $context);
        }
    }

    /**
     * Log a warning message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    private function log_warning($message, $context = []) {
        if ($this->logger) {
            $this->logger->insert_log('warning', '[Payment Methods] ' . $message, $context);
        }
    }
}

// Initialize the payment methods handler
new Bocs_Payment_Methods(); 