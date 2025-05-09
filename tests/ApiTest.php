<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Api.php';

class ApiTest extends TestCase
{
    public function testGetEndpoint()
    {
        $api = new Api();
        $endpoint = $api->get_endpoint('test');
        $this->assertEquals('https://api.example.com/test', $endpoint);
    }

    public function testIsValidResponse()
    {
        $api = new Api();
        $response = ['status' => 200, 'data' => ['key' => 'value']];
        $isValid = $api->is_valid_response($response);
        $this->assertTrue($isValid);
    }
}
