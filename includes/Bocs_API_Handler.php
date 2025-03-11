<?php
/**
 * BOCS API Handler
 *
 * A wrapper class to handle all API calls to the BOCS API with improved error handling,
 * fallbacks, and retry mechanisms to ensure the plugin functions even when the API
 * is experiencing issues.
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 * @since      1.0.0
 */

class Bocs_API_Handler {
    /**
     * Maximum number of retry attempts for API calls
     */
    const MAX_RETRIES = 2;
    
    /**
     * Seconds to wait before considering the API call timed out
     */
    const TIMEOUT_SECONDS = 5;
    
    /**
     * Cached API responses for non-critical endpoints
     */
    private $cached_responses = [];
    
    /**
     * Whether the API is considered available based on recent responses
     */
    private static $api_available = true;
    
    /**
     * Timestamp when we last checked API availability
     */
    private static $last_api_check = 0;
    
    /**
     * How long to wait (in seconds) before rechecking API availability
     */
    const API_RETRY_INTERVAL = 300; // 5 minutes
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize cache from transient if available
        $cached_data = get_transient('bocs_api_response_cache');
        if ($cached_data) {
            $this->cached_responses = $cached_data;
        }
        
        // Check if we need to restore API availability
        if (!self::$api_available && (time() - self::$last_api_check) > self::API_RETRY_INTERVAL) {
            self::$api_available = true; // Reset and allow retry
        }
    }
    
    /**
     * Makes a GET request to the BOCS API with improved error handling
     *
     * @param string $endpoint API endpoint to call
     * @param string $module Module name for logging purposes
     * @param string $id ID for logging purposes
     * @param bool $is_critical Whether this API call is critical for plugin function
     * @param int $cache_ttl How long to cache the response (in seconds)
     * @return object Response object with success/error properties
     */
    public function get($endpoint, $module = '', $id = '', $is_critical = false, $cache_ttl = 3600) {
        // Check if API is unavailable and this is a non-critical call
        if (!self::$api_available && !$is_critical) {
            return $this->get_cached_or_fallback($endpoint);
        }
        
        // Check if we have a valid cached response
        $cache_key = md5($endpoint);
        if (!$is_critical && isset($this->cached_responses[$cache_key]) && isset($this->cached_responses[$cache_key]['expires']) && $this->cached_responses[$cache_key]['expires'] > time()) {
            return $this->cached_responses[$cache_key]['data'];
        }
        
        // Make the API call through the regular Curl class
        $curl = new Curl();
        $result = $curl->get($endpoint, $module, $id);
        
        // Handle result and caching
        return $this->handle_response($result, $endpoint, $is_critical, $cache_ttl);
    }
    
    /**
     * Makes a POST request to the BOCS API with improved error handling
     *
     * @param string $endpoint API endpoint to call
     * @param string $data JSON data to send
     * @param string $module Module name for logging purposes
     * @param string $id ID for logging purposes
     * @param bool $is_critical Whether this API call is critical for plugin function
     * @return object Response object with success/error properties
     */
    public function post($endpoint, $data, $module = '', $id = '', $is_critical = false) {
        // Check if API is unavailable and this is a non-critical call
        if (!self::$api_available && !$is_critical) {
            return $this->get_fallback_response("API temporarily unavailable");
        }
        
        // Make the API call with retry logic
        $curl = new Curl();
        $result = null;
        $attempts = 0;
        
        while ($attempts < self::MAX_RETRIES) {
            $result = $curl->post($endpoint, $data, $module, $id);
            
            // Break the loop if we got a successful response or a client error (not a server error)
            if (isset($result->success) && $result->success || 
                (isset($result->code) && $result->code !== 'server_error' && $result->code !== 'connection_error')) {
                break;
            }
            
            $attempts++;
            if ($attempts < self::MAX_RETRIES) {
                // Wait briefly before retrying
                usleep(500000); // 0.5 seconds
            }
        }
        
        // Handle result
        return $this->handle_response($result, $endpoint, $is_critical);
    }
    
    /**
     * Makes a PUT request to the BOCS API with improved error handling
     *
     * @param string $endpoint API endpoint to call
     * @param string $data JSON data to send
     * @param string $module Module name for logging purposes
     * @param string $id ID for logging purposes
     * @param bool $is_critical Whether this API call is critical for plugin function
     * @return object Response object with success/error properties
     */
    public function put($endpoint, $data, $module = '', $id = '', $is_critical = false) {
        // Check if API is unavailable and this is a non-critical call
        if (!self::$api_available && !$is_critical) {
            return $this->get_fallback_response("API temporarily unavailable");
        }
        
        // Make the API call with retry logic
        $curl = new Curl();
        $result = null;
        $attempts = 0;
        
        while ($attempts < self::MAX_RETRIES) {
            $result = $curl->put($endpoint, $data, $module, $id);
            
            // Break the loop if we got a successful response or a client error (not a server error)
            if (isset($result->success) && $result->success || 
                (isset($result->code) && $result->code !== 'server_error' && $result->code !== 'connection_error')) {
                break;
            }
            
            $attempts++;
            if ($attempts < self::MAX_RETRIES) {
                // Wait briefly before retrying
                usleep(500000); // 0.5 seconds
            }
        }
        
        // Handle result
        return $this->handle_response($result, $endpoint, $is_critical);
    }
    
    /**
     * Handles API response, including caching and error tracking
     *
     * @param object $result API response
     * @param string $endpoint API endpoint that was called
     * @param bool $is_critical Whether this API call is critical
     * @param int $cache_ttl How long to cache the response (in seconds)
     * @return object Processed response object
     */
    private function handle_response($result, $endpoint, $is_critical = false, $cache_ttl = 3600) {
        // Check for server errors that might indicate API unavailability
        if (isset($result->code) && ($result->code === 'server_error' || $result->code === 'connection_error')) {
            // Mark API as unavailable if we get server errors
            self::$api_available = false;
            self::$last_api_check = time();
            
            // Log the issue
            error_log("BOCS API marked as unavailable due to {$result->code} on endpoint {$endpoint}");
            
            // For non-critical requests, return cached or fallback data
            if (!$is_critical) {
                return $this->get_cached_or_fallback($endpoint);
            }
        }
        
        // Cache successful GET responses for non-critical endpoints
        if ($cache_ttl > 0 && !$is_critical && isset($result->success) && $result->success) {
            $cache_key = md5($endpoint);
            $this->cached_responses[$cache_key] = [
                'data' => $result,
                'expires' => time() + $cache_ttl
            ];
            
            // Save cache to transient occasionally (1 in 10 chance to reduce DB writes)
            if (mt_rand(1, 10) === 1) {
                set_transient('bocs_api_response_cache', $this->cached_responses, 24 * HOUR_IN_SECONDS);
            }
        }
        
        return $result;
    }
    
    /**
     * Returns cached data or a fallback response for an endpoint
     *
     * @param string $endpoint API endpoint
     * @return object Response object
     */
    private function get_cached_or_fallback($endpoint) {
        $cache_key = md5($endpoint);
        
        // Return cached data if available (even if expired)
        if (isset($this->cached_responses[$cache_key]) && isset($this->cached_responses[$cache_key]['data'])) {
            // Mark as stale but still usable
            $response = $this->cached_responses[$cache_key]['data'];
            if (!isset($response->is_stale_cache)) {
                $response->is_stale_cache = true;
            }
            return $response;
        }
        
        // Otherwise return a generic fallback
        return $this->get_fallback_response("API temporarily unavailable, using cached data");
    }
    
    /**
     * Creates a fallback response object
     *
     * @param string $message Message to include in the response
     * @return object Fallback response object
     */
    private function get_fallback_response($message) {
        return (object) [
            'success' => false,
            'error' => true,
            'message' => $message,
            'code' => 'api_unavailable',
            'is_fallback' => true,
            'data' => (object) [] // Empty data object
        ];
    }
    
    /**
     * Checks if the BOCS API is available
     *
     * @return bool True if the API is available, false otherwise
     */
    public static function is_api_available() {
        return self::$api_available;
    }
} 