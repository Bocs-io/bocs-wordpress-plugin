<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Account.php';

class AccountTest extends TestCase
{
    public function testCreateAccount()
    {
        $account = new Account();
        $result = $account->create_account('test@example.com', 'password123');
        $this->assertTrue($result);
    }

    public function testDeleteAccount()
    {
        $account = new Account();
        $result = $account->delete_account(123);
        $this->assertTrue($result);
    }
}
