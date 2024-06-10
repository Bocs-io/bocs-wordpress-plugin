<?php

class Account
{

    public function add_bocs_menu($items)
    {
        $items['bocs-subscriptions'] = "Bocs Subscriptions";

        return $items;
    }

    public function bocs_subscription_endpoint()
    {
        add_rewrite_endpoint('my-account/bocs-subscription', EP_ROOT | EP_PAGES);
    }

    public function bocs_subscription_endpoint_template()
    {}
}