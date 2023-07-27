<?php

class Curl {

	/**
	 * Do the processes
	 *
	 * @param $url
	 * @param $method
	 * @param $data
	 *
	 * @return array|object
	 */
	private function process($url, $method = "GET", $data = "", $module = '', $id = ''){

		$options = get_option( 'bocs_plugin_options' );
		$options['bocs_headers'] = $options['bocs_headers'] ?? array();

		$curl = curl_init();

		$header = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => array(
				'Organization: ' . $options['bocs_headers']['organization'],
				'Content-Type: application/json',
				'Store: ' . $options['bocs_headers']['store'],
				'Authorization: ' . $options['bocs_headers']['authorization']
			),
		);

		if ( $method === "PUT" || $method === "POST" || $data === ""){
			$header[CURLOPT_POSTFIELDS] = $data;
		}

		curl_setopt_array($curl, $header);

		$response = curl_exec($curl);

		curl_close($curl);

		$result = json_decode($response);

		// we will be logging the error/success here
		$logger = new Bocs_Log_Handler();
		$logger->process_log_from_result($result, $url, $data, $method);

		return $result;

	}

	/**
	 * Post HTTP method for the private API
	 * 
	 * @return array|object
	 */
	public function post($url, $data, $module = '', $id = ''){
		return $this->process( BOCS_API_URL . $url, "POST", $data, $module, $id );
	}

	/**
	 * Put HTTP method for the private API
	 * 
	 * @return array|object
	 */
	public function put($url, $data, $module = '', $id = ''){
		return $this->process( BOCS_API_URL . $url, "PUT", $data, $module, $id );
	}

	/**
	 * Get HTTP Method for the private API
	 * 
	 * @return array|object
	 */
	public function get( $url, $module = '', $id = ''){
		return $this->process(BOCS_API_URL . $url, "GET", NULL, $module, $id);
	}

}