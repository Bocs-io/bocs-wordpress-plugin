<?php

class Account {

    public function add_bocs_menu($items){

        $items['bocs-subscription'] = "Bocs Subscription";

        return $items;

    }

    public function bocs_subscription_endpoint(){
        add_rewrite_endpoint( 'my-account/bocs-subscription', EP_ROOT | EP_PAGES );
    }

    public function bocs_subscription_endpoint_template(){
        error_log("TEST");
        echo "TEST";
    }

}