<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Widget.php';

class WidgetTest extends TestCase
{
    public function testRenderWidget()
    {
        $widget = new Widget();
        $output = $widget->render_widget(['title' => 'Test Widget']);
        $this->assertStringContainsString('<h1>Test Widget</h1>', $output);
    }

    public function testUpdateWidget()
    {
        $widget = new Widget();
        $result = $widget->update_widget(123, ['title' => 'Updated Widget']);
        $this->assertTrue($result);
    }
}
