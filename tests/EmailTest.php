<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Bocs_Email.php';

class EmailTest extends TestCase
{
    public function testSendEmail()
    {
        $email = new Bocs_Email();
        $result = $email->send_email('test@example.com', 'Subject', 'Message');
        $this->assertTrue($result);
    }

    public function testValidateEmail()
    {
        $email = new Bocs_Email();
        $isValid = $email->validate_email('test@example.com');
        $this->assertTrue($isValid);
    }
}
