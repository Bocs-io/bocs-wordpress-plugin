<?php

/**
 *
 * This will handle the Bocs api related to bocs
 *
 */
class Bocs_Bocs
{

    private $headers;

    private $helper;

    public function __construct()
    {
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

        $this->helper = new Bocs_Helper();
    }

    /**
     * Retrieve all business objects (bocs) from the API based on their status
     *
     * @param string $status The status of bocs to retrieve (default: 'active')
     * @return array An array of business objects matching the status criteria
     *
     * @throws Exception If the API request fails or returns invalid data
     * @since 1.0.0
     */
    public function get_all_bocs($status = 'active')
    {
        // Construct the API endpoint URL with status filter
        $api_endpoint = BOCS_API_URL . 'bocs?query=status:' . $status;

        // Make HTTP GET request to the API using helper function
        $api_response = $this->helper->curl_request(
            $api_endpoint,
            'GET',
            NULL,
            $this->headers
        );

        // Handle nested data structure in API response
        // Some endpoints return data in data.data, others directly in data
        return isset($api_response['data']['data']) 
            ? $api_response['data']['data']     // For nested response structure
            : $api_response['data'];            // For flat response structure
    }
}

?>