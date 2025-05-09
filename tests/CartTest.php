<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/Bocs_Cart.php';

class CartTest extends TestCase
{
    public function testAddToCart()
    {
        $cart = new Bocs_Cart();
        $result = $cart->add_to_cart(123, 2);
        $this->assertTrue($result);
    }

    public function testRemoveFromCart()
    {
        $cart = new Bocs_Cart();
        $result = $cart->remove_from_cart(123);
        $this->assertTrue($result);
    }
}
