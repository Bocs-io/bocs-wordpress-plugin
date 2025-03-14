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
     * @return array|object
     */
    private function process($url, $method = "GET", $data = "", $module = '', $id = '')
    {
        $result = array();
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        if(empty($options['bocs_headers']['organization']) || empty($options['bocs_headers']['store']) || empty($options['bocs_headers']['authorization'])) {
            return $result;
        }

        if (isset($options['bocs_headers']['organization']) && isset($options['bocs_headers']['store']) && isset($options['bocs_headers']['authorization'])) {
            $curl = curl_init();

            $header = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => array(
                    'Organization: ' . $options['bocs_headers']['organization'],
                    'Content-Type: application/json',
                    'Store: ' . $options['bocs_headers']['store'],
                    'Authorization: ' . $options['bocs_headers']['authorization']
                )
            );

            if ($method === "PUT" || $method === "POST" || $data === "") {
                $header[CURLOPT_POSTFIELDS] = $data;
            }

            curl_setopt_array($curl, $header);

            try {
                $response = curl_exec($curl);
                $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curl_error = '';
                
                if ($response === false) {
                    $curl_error = curl_error($curl);
                    error_log("BOCS API Error: " . $curl_error . " when calling " . $url);
                    
                    // Create an empty result object with error details
                    $result = (object) [
                        'success' => false,
                        'error' => true,
                        'message' => 'Connection error: ' . $curl_error,
                        'code' => 'connection_error'
                    ];
                } else if ($http_code >= 500) {
                    error_log("BOCS API Server Error: Received HTTP " . $http_code . " when calling " . $url);
                    
                    // Create an empty result object with error details
                    $result = (object) [
                        'success' => false,
                        'error' => true,
                        'message' => 'API server error (HTTP ' . $http_code . ')',
                        'code' => 'server_error'
                    ];
                } else {
                    // Regular processing for successful responses
                    $result = json_decode($response);
                    
                    // If json_decode fails, create a basic response object
                    if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
                        error_log("BOCS API JSON Error: " . json_last_error_msg() . " when parsing response from " . $url);
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
                
                // we will be logging the error/success here
                $logger = new Bocs_Log_Handler();
                $logger->process_log_from_result($result, $url, $data, $method, $module, $id);
                
                return $result;
                
            } catch (Exception $e) {
                if (isset($curl) && is_resource($curl)) {
                    curl_close($curl);
                }
                
                error_log("BOCS API Exception: " . $e->getMessage() . " when calling " . $url);
                
                // Create an error result object
                $result = (object) [
                    'success' => false,
                    'error' => true,
                    'message' => 'Exception: ' . $e->getMessage(),
                    'code' => 'exception'
                ];
                
                // Log the error
                $logger = new Bocs_Log_Handler();
                $logger->process_log_from_result($result, $url, $data, $method, $module, $id);
                
                return $result;
            }
        }
        
        return $result;
    }

    /**
     * Post HTTP method for the private API
     *
     * @return array|object
     */
    public function post($url, $data, $module = '', $id = '')
    {
        return $this->process(BOCS_API_URL . $url, "POST", $data, $module, $id);
    }

    /**
     * Put HTTP method for the private API
     *
     * @return array|object
     */
    public function put($url, $data, $module = '', $id = '')
    {
        return $this->process(BOCS_API_URL . $url, "PUT", $data, $module, $id);
    }

    /**
     * Get HTTP Method for the private API
     *
     * @return array|object
     */
    public function get($url, $module = '', $id = '')
    {
        return $this->process(BOCS_API_URL . $url, "GET", NULL, $module, $id);
    }
}
