<?php

class Curl
{

    /**
     * Process the curl request
     *
     * @param string $url
     * @param string $method
     * @param string $data
     * @param string $module
     * @param string $id
     * @param array $headers
     * @return array|object
     */
    private function process($url, $method = "GET", $data = "", $module = '', $id = '', $headers = [])
    {
        $result = null;
        $max_retries = 3;
        $retry_delay = 1; // seconds
        
        try {
            for ($retry = 0; $retry <= $max_retries; $retry++) {
                $curl = curl_init();
                $options = get_option('bocs_plugin_options');
                $options['bocs_headers'] = $options['bocs_headers'] ?? array();

                // Improved URL handling to prevent duplication
                if (strpos($url, 'http') === 0) {
                    // URL already has http prefix, use as is
                    $full_url = $url;
                    error_log("BOCS API Debug - Using full URL directly: " . $full_url);
                } else if (strpos($url, 'execute-api') !== false) {
                    // AWS API Gateway URL, don't add BOCS_API_URL prefix
                    $full_url = $url;
                    error_log("BOCS API Debug - AWS API Gateway URL detected, using as is: " . $full_url);
                } else {
                    // Add the API URL prefix
                    $full_url = defined('BOCS_API_URL') ? BOCS_API_URL . $url : $url;
                    error_log("BOCS API Debug - Added API URL prefix: " . $full_url);
                }
                
                // Check for double URL issue
                if (defined('BOCS_API_URL') && strpos($full_url, BOCS_API_URL . BOCS_API_URL) === 0) {
                    // Fix double URL
                    $full_url = str_replace(BOCS_API_URL . BOCS_API_URL, BOCS_API_URL, $full_url);
                    error_log("BOCS API Debug - Fixed double URL prefix: " . $full_url);
                }
                
                error_log("BOCS API Debug - Final URL: " . $full_url);

                // Prepare the headers array
                $curl_headers = [];
                
                // If custom headers are provided, use them directly
                if (!empty($headers)) {
                    // Convert associative array to format required by cURL
                    if (is_array($headers)) {
                        foreach ($headers as $key => $value) {
                            $curl_headers[] = $key . ': ' . $value;
                        }
                    } else {
                        // If already formatted properly, use as is
                        $curl_headers = $headers;
                    }
                } else {
                    // Use default headers from options
                    if(!empty($options['bocs_headers']['organization']) && 
                       !empty($options['bocs_headers']['store']) && 
                       !empty($options['bocs_headers']['authorization'])) {
                        
                        $curl_headers = array(
                            'Organization: ' . $options['bocs_headers']['organization'],
                            'Content-Type: application/json',
                            'Store: ' . $options['bocs_headers']['store'],
                            'Authorization: ' . $options['bocs_headers']['authorization']
                        );
                    } else {
                        error_log("BOCS API Error: Missing required headers");
                        return new WP_Error('missing_headers', 'Required API headers are missing');
                    }
                }
                
                // Special handling for AWS API Gateway
                if (strpos($full_url, 'execute-api') !== false) {
                    error_log("BOCS API Debug - AWS API Gateway detected:");
                    error_log("BOCS API Debug - URL: " . $full_url);
                    
                    // Extract hostname and region from the URL
                    $parsed_url = parse_url($full_url);
                    $host = $parsed_url['host'];
                    error_log("BOCS API Debug - Host: " . $host);
                    
                    // Extract region from host (e.g. "ap-southeast-2" from "hudaq97o4b.execute-api.ap-southeast-2.amazonaws.com")
                    preg_match('/execute-api\.([a-z0-9-]+)\.amazonaws\.com/', $host, $matches);
                    $region = $matches[1] ?? '';
                    error_log("BOCS API Debug - Region: " . $region);
                    
                    // For AWS API Gateway, we need to add special headers
                    $aws_date = gmdate('Ymd\THis\Z');
                    
                    // Preserve the authorization in a custom header and modify for AWS format
                    foreach ($curl_headers as $i => $header) {
                        if (strpos($header, 'Authorization:') === 0) {
                            // Extract the token value
                            $auth_value = trim(substr($header, 14));
                            // Add to debug headers
                            $curl_headers[] = 'X-Bocs-Authorization: ' . substr($auth_value, 0, 10) . '...';
                            error_log("BOCS API Debug - Authorization header copied to X-Bocs-Authorization");
                            break;
                        }
                    }
                    
                    // Add AWS SigV4 specific headers
                    $curl_headers[] = 'X-Amz-Date: ' . $aws_date;
                    $curl_headers[] = 'host: ' . $host;
                    error_log("BOCS API Debug - Added AWS SigV4 headers to request - Date: " . $aws_date);
                }
                
                // Debug log
                error_log("BOCS API Request - URL: " . preg_replace('/([?&]key=)[^&]+/', '$1[key_redacted]', $full_url));
                error_log("BOCS API Request - Method: " . $method);
                
                // Create a sanitized version of headers for logging (hide sensitive values)
                $log_headers = [];
                foreach ($curl_headers as $header) {
                    if (strpos($header, 'Authorization:') === 0 || strpos($header, 'X-Bocs-Authorization:') === 0) {
                        $parts = explode(':', $header, 2);
                        $log_headers[] = $parts[0] . ': ' . substr(trim($parts[1]), 0, 10) . '...';
                    } else {
                        $log_headers[] = $header;
                    }
                }
                error_log("BOCS API Request - Headers: " . json_encode($log_headers));
                
                // Prepare the request options
                $curl_options = array(
                    CURLOPT_URL => $full_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 10, // Increased timeout
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_HTTPHEADER => $curl_headers
                );

                // Add request body for PUT/POST
                if (($method === "PUT" || $method === "POST") && !empty($data)) {
                    if (is_array($data) || is_object($data)) {
                        $curl_options[CURLOPT_POSTFIELDS] = json_encode($data);
                        error_log("BOCS API Request Body: " . json_encode($data));
                    } else {
                        $curl_options[CURLOPT_POSTFIELDS] = $data;
                        error_log("BOCS API Request Body: " . $data);
                    }
                }
                
                // Set cURL options
                curl_setopt_array($curl, $curl_options);
                
                // Execute the request
                $response = curl_exec($curl);
                $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curl_error = '';
                
                error_log("BOCS API Response Code: " . $http_code);
                
                // Get response headers for debugging
                $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $header_string = substr($response, 0, $header_size);
                $body = substr($response, $header_size);
                
                // Log response details
                error_log("BOCS API Response - Code: " . $http_code);
                error_log("BOCS API Response - Headers: " . json_encode(curl_getinfo($curl)));
                error_log("BOCS API Response - Body (truncated): " . substr($response, 0, 500) . (strlen($response) > 500 ? "..." : ""));
                
                if ($response === false) {
                    $curl_error = curl_error($curl);
                    error_log("BOCS API Error: " . $curl_error . " when calling " . $full_url);
                    
                    // Create an empty result object with error details
                    $result = (object) [
                        'success' => false,
                        'error' => true,
                        'message' => 'Connection error: ' . $curl_error,
                        'code' => 'connection_error'
                    ];
                } else if ($http_code >= 500) {
                    error_log("BOCS API Server Error: Received HTTP " . $http_code . " when calling " . $full_url . " (Attempt " . ($retry + 1) . " of " . ($max_retries + 1) . ")");
                    
                    // On server error, retry after delay if not last attempt
                    if ($retry < $max_retries) {
                        curl_close($curl);
                        sleep($retry_delay);
                        continue;
                    }
                    
                    // Create an empty result object with error details
                    $result = (object) [
                        'success' => false,
                        'error' => true,
                        'message' => 'API server error (HTTP ' . $http_code . ') after ' . ($retry + 1) . ' attempts',
                        'code' => 'server_error'
                    ];
                } else {
                    // Regular processing for successful responses
                    $result = json_decode($response);
                    
                    // If json_decode fails, create a basic response object
                    if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
                        error_log("BOCS API JSON Error: " . json_last_error_msg() . " when parsing response from " . $full_url);
                        error_log("BOCS API Raw Response: " . substr($response, 0, 1000));
                        $result = (object) [
                            'success' => false,
                            'error' => true,
                            'message' => 'Invalid JSON response: ' . json_last_error_msg(),
                            'code' => 'json_error',
                            'raw_response' => substr($response, 0, 1000) // First 1000 chars for debugging
                        ];
                    }
                }
                
                curl_close($curl);
                
                // If we got a result (success or non-502 error), break the retry loop
                break;
            }
            
            // We will be logging the error/success here
            $logger = new Bocs_Log_Handler();
            $logger->process_log_from_result($result, $full_url, $data, $method, $module, $id);
            
            return $result;
            
        } catch (Exception $e) {
            if (isset($curl) && is_resource($curl)) {
                curl_close($curl);
            }
            
            error_log("BOCS API Exception: " . $e->getMessage() . " when calling " . $full_url);
            
            // Create an error result object
            $result = (object) [
                'success' => false,
                'error' => true,
                'message' => 'Exception: ' . $e->getMessage(),
                'code' => 'exception'
            ];
            
            // Log the error
            $logger = new Bocs_Log_Handler();
            $logger->process_log_from_result($result, $full_url, $data, $method, $module, $id);
            
            return $result;
        }
        
        return $result;
    }

    /**
     * Post method to perform POST API calls
     *
     * @param string $url
     * @param array|object|string $data
     * @param string $module
     * @param string $id
     * @param array $headers
     * @return object
     */
    public function post($url, $data = "", $module = '', $id = '', $headers = [])
    {
        // Don't add BOCS_API_URL if the URL already contains http or is an AWS API Gateway URL
        if (strpos($url, 'http') === 0 || strpos($url, 'execute-api') !== false) {
            return $this->process($url, "POST", $data, $module, $id, $headers);
        }
        return $this->process(BOCS_API_URL . $url, "POST", $data, $module, $id, $headers);
    }

    /**
     * Put method to perform PUT API calls
     *
     * @param string $url
     * @param array|object|string $data
     * @param string $module
     * @param string $id
     * @param array $headers
     * @return object
     */
    public function put($url, $data = "", $module = '', $id = '', $headers = [])
    {
        // Don't add BOCS_API_URL if the URL already contains http or is an AWS API Gateway URL
        if (strpos($url, 'http') === 0 || strpos($url, 'execute-api') !== false) {
            return $this->process($url, "PUT", $data, $module, $id, $headers);
        }
        return $this->process(BOCS_API_URL . $url, "PUT", $data, $module, $id, $headers);
    }

    /**
     * Get method to perform GET API calls
     *
     * @param string $url
     * @param string $module
     * @param string $id
     * @param array $headers
     * @return object
     */
    public function get($url, $module = '', $id = '', $headers = [])
    {
        // Don't add BOCS_API_URL if the URL already contains http or is an AWS API Gateway URL
        if (strpos($url, 'http') === 0 || strpos($url, 'execute-api') !== false) {
            return $this->process($url, "GET", '', $module, $id, $headers);
        }
        return $this->process(BOCS_API_URL . $url, "GET", '', $module, $id, $headers);
    }
}
