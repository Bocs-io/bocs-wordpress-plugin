<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Sync.php';

class SyncTest extends TestCase
{
    public function testSyncContacts()
    {
        $sync = new Sync();
        $result = $sync->sync_contacts();
        $this->assertTrue($result);
    }

    public function testSyncOrders()
    {
        $sync = new Sync();
        $result = $sync->sync_orders();
        $this->assertTrue($result);
    }
}
