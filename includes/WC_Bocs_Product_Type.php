<?php

/**
 *
 */
class WC_Bocs_Product_Type extends WC_Product {

	public function __construct($product = 0)
	{
		$this->product_type = 'bocs';
		parent::__construct($product);
	}
    
}