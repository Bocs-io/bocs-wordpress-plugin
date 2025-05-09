<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Bocs_Helper.php';

class BocsHelperTest extends TestCase
{
    public function testFormatPrice()
    {
        $helper = new Bocs_Helper();
        $formattedPrice = $helper->format_price(1234.56);
        $this->assertEquals('$1,234.56', $formattedPrice);
    }

    public function testSanitizeInput()
    {
        $helper = new Bocs_Helper();
        $sanitized = $helper->sanitize_input('<script>alert("XSS")</script>');
        $this->assertEquals('alert("XSS")', $sanitized);
    }
}
