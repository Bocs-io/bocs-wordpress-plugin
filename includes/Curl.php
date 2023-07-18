<?php

class Curl {

	/**
	 * @param $url
	 * @param $method
	 * @param $data
	 * @return bool|string
	 */
	private function process($url, $method = "GET", $data = ""){

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

		return $response;

	}

	public function post($url, $data){
		return $this->process( BOCS_API_URL . $url, "POST", $data );
	}

	public function put($url, $data){
		return $this->process( BOCS_API_URL . $url, "PUT", $data );
	}

	public function get( $url ){
		return $this->process(BOCS_API_URL . $url, "GET");
	}

}