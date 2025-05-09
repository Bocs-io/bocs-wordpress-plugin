<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Loader.php';

class LoaderTest extends TestCase
{
    public function testRegisterHooks()
    {
        $loader = new Loader();
        $result = $loader->register_hooks();
        $this->assertTrue($result);
    }

    public function testUnregisterHooks()
    {
        $loader = new Loader();
        $result = $loader->unregister_hooks();
        $this->assertTrue($result);
    }
}
