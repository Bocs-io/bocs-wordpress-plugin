<?php

class Bocs_List_Table extends WP_List_Table
{

    // we will add some code here for the table list related to bocs
    public function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'status' => "Status",
            'subscription' => "Subscription",
            'items' => "Items",
            'total' => "Total",
            "start_date" => "Start Date",
            "next_payment" => "Next Payment",
            "last_order_date" => "Last Order Date",
            "orders" => "Orders"
        );

        return $columns;
    }

    // Bind table with columns, data and all
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array(
            $columns,
            $hidden,
            $sortable
        );
        $this->items = $this->_get_table_data();
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'status':
            case 'subscription':
            case 'items':
            case 'total':
            case "start_date":
            case "next_payment":
            case "last_order_date":
            case "orders":
            default:
                return $item[$column_name];
        }
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="element[]" value="%s" />', $item['id']);
    }

    // Get table data
    private function _get_table_data()
    {
        // get the list of the subscriptions from the Bocs API
        // error_reporting(E_ALL);
        $currency_symbol = get_woocommerce_currency_symbol();

        $result = array();

        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => BOCS_API_URL . 'subscriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Organization: ' . $options['bocs_headers']['organization'],
                'Content-Type: application/json',
                'Store: ' . $options['bocs_headers']['store'],
                'Authorization: ' . $options['bocs_headers']['authorization']
            )
        ));

        $response = curl_exec($curl);
        $object = json_decode($response);
        curl_close($curl);

        if ($object) {
            if (isset($object->data)) {
                if (count($object->data) > 0) {

                    foreach ($object->data as $subscription) {

                        $status = $subscription->status;
                        $subscription_price = round($subscription->total, 2);
                        $products = $subscription->lineItems;
                        $name = $subscription->bocs->name;
                        $type = $subscription->bocs->type;
                        $contact_name = $subscription->billing->firstName . ' ' . $subscription->billing->lastName;
                        $frequency = $subscription->frequency->frequency . ' ' . $subscription->frequency->timeUnit;
                        if ($subscription->frequency->frequency == 1) {
                            $frequency = $subscription->frequency->timeUnit;
                            if (substr($frequency, - 1) === 's') {
                                $frequency = substr($frequency, 0, - 1);
                            }
                        }
                        $discount = $subscription->frequency->discount;
                        $discount_type = $subscription->frequency->discountType;

                        $date = new DateTime($subscription->nextPaymentDateGmt);
                        $next_order_date = $date->format('F j, Y, g:i a');

                        $activationDate = '';
                        if (! empty($subscription->createdAt)) {
                            $date = new DateTime($subscription->createdAt);
                            $activationDate = $date->format('F j, Y, g:i a');
                        }

                        $total_items = 0;

                        $total = 0;

                        foreach ($products as $product) {
                            $total_items += $product->quantity;
                            $total += $product->price * $product->quantity;
                        }

                        $final_price = $subscription_price;
                        if ($subscription_price == 0) {
                            $final_price = $total;
                        }

                        $final_price = number_format($final_price, 2, '.', ',');

                        $result[] = array(
                            "id" => $subscription->id,
                            "status" => $subscription->status,
                            "first_name" => $subscription->billing->firstName,
                            "last_name" => $subscription->billing->lastName,
                            "subscription" => $name . ' for ' . $subscription->billing->firstName . ' ' . $subscription->billing->lastName,
                            "items" => $total_items . ' items',
                            "total" => $currency_symbol . $final_price,
                            "start_date" => $activationDate,
                            "next_payment" => $next_order_date,
                            "last_order_date" => $activationDate,
                            "orders" => 1
                        );
                    }
                }
            }
        }

        return $result;
    }
}
