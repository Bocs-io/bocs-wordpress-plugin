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
                'Organization' => $options['bocs_headers']['organization'],
                'Store' => $options['bocs_headers']['store'],
                'Authorization' => $options['bocs_headers']['authorization'],
                'Content-Type' => 'application/json'
            ];
        }

        $this->helper = new Bocs_Helper();
    }

    public function get_all_bocs($status = 'active')
    {
        $url = BOCS_API_URL . 'bocs?status=' . $status;
        $bocs = $this->helper->curl_request($url, 'GET', NULL, $this->headers);
        return $bocs['data'];
    }
}

?>