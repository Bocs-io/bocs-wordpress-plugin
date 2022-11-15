<?php

class Api {

    private $url = BOCS_API_URL;

    private $api_token;

    public function __construct()
    {
        $this->api_token = get_option(BOCS_SLUG.'_key');
    }

    
}