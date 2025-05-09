<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Frontend.php';

class FrontendTest extends TestCase
{
    public function testRenderShortcode()
    {
        $frontend = new Frontend();
        $output = $frontend->render_shortcode(['id' => 123]);
        $this->assertStringContainsString('<div', $output);
        $this->assertStringContainsString('id="123"', $output);
    }

    public function testEnqueueScripts()
    {
        $frontend = new Frontend();
        $result = $frontend->enqueue_scripts();
        $this->assertTrue($result);
    }
}
