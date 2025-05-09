<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Bocs_Log_Handler.php';

class LogHandlerTest extends TestCase
{
    public function testLogInfo()
    {
        $logHandler = new Bocs_Log_Handler();
        $result = $logHandler->log_info('Test info message');
        $this->assertTrue($result);
    }

    public function testLogError()
    {
        $logHandler = new Bocs_Log_Handler();
        $result = $logHandler->log_error('Test error message');
        $this->assertTrue($result);
    }
}
