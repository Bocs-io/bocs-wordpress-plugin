<?php

class Bocs_Helper
{

    /**
     * helper for curl requests
     *
     * @param unknown $url
     * @param string $method
     * @param array $data
     * @param array $headers
     * @return boolean[]|string[]|unknown
     */
    public function curl_request($url, $method = 'GET', $data = [], $headers = [])
    {
        $ch = curl_init();

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                break;
            case 'GET':
            default:
                if (! empty($data)) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }

        // Set the cURL options

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Organization: ' . $headers['Organization'],
            'Content-Type: application/json',
            'Store: ' . $headers['Store'],
            'Authorization: ' . $headers['Authorization']
        ));

        // Execute the request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
        }

        // Close the cURL session
        curl_close($ch);

        if (isset($error_msg)) {
            return [
                'error' => true,
                'message' => $error_msg
            ];
        }

        return json_decode($response, true);
    }
}
?>