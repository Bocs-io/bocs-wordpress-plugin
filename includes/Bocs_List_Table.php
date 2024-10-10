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

    /**
     * Get the table data from the Bocs API
     * 
     * @return array    
     */
    private function _get_table_data()
    {
        
        $result = array(); // Initialize an empty array to store subscription data
        
        // Retrieve Bocs plugin options and headers
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        if(empty($options['bocs_headers']['organization']) || empty($options['bocs_headers']['store']) || empty($options['bocs_headers']['authorization'])) {
            return $result;
        }

        // Retrieve the list of subscriptions from the Bocs API
        $currency_symbol = get_woocommerce_currency_symbol(); // Get the currency symbol for WooCommerce

        // Initialize cURL session
        $curl = curl_init();

        // Set cURL options for the API request
        curl_setopt_array($curl, array(
            CURLOPT_URL => BOCS_API_URL . 'subscriptions', // API endpoint for subscriptions
            CURLOPT_RETURNTRANSFER => true, // Return the response as a string
            CURLOPT_ENCODING => '', // Handle all encodings
            CURLOPT_MAXREDIRS => 10, // Maximum number of redirects
            CURLOPT_TIMEOUT => 0, // No timeout
            CURLOPT_FOLLOWLOCATION => true, // Follow redirects
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Use HTTP/1.1
            CURLOPT_CUSTOMREQUEST => 'GET', // Use GET method
            CURLOPT_HTTPHEADER => array( // Set HTTP headers
                'Organization: ' . $options['bocs_headers']['organization'],
                'Content-Type: application/json',
                'Store: ' . $options['bocs_headers']['store'],
                'Authorization: ' . $options['bocs_headers']['authorization']
            )
        ));

        // Execute the cURL request and decode the JSON response
        $response = curl_exec($curl);
        $object = json_decode($response);
        curl_close($curl); // Close the cURL session

        // Check if the response contains data
        if ($object && isset($object->data->data) && count($object->data->data) > 0) {
            // Iterate over each subscription in the response
            foreach ($object->data->data as $subscription) {

                // Extract subscription details
                $status = $subscription->status;
                $subscription_price = round($subscription->total, 2);
                $products = $subscription->lineItems;

                // Determine subscription name and type
                $name = $subscription->bocs && $subscription->bocs->name ? $subscription->bocs->name : "";
                $type = $subscription->bocs && $subscription->bocs->type ? $subscription->bocs->type : "";

                // Construct contact name and frequency
                $contact_name = $subscription->billing->firstName . ' ' . $subscription->billing->lastName;
                $frequency = $subscription->frequency->frequency . ' ' . $subscription->frequency->timeUnit;
                if ($subscription->frequency->frequency == 1) {
                    $frequency = $subscription->frequency->timeUnit;
                    if (substr($frequency, -1) === 's') {
                        $frequency = substr($frequency, 0, -1);
                    }
                }

                // Calculate discount details
                $discount = $subscription->frequency->discount;
                $discount_type = $subscription->frequency->discountType;

                // Format next payment date
                $date = new DateTime($subscription->nextPaymentDateGmt);
                $next_order_date = $date->format('F j, Y, g:i a');

                // Format activation date
                $activationDate = '';
                if (!empty($subscription->createdAt)) {
                    $date = new DateTime($subscription->createdAt);
                    $activationDate = $date->format('F j, Y, g:i a');
                }

                // Calculate total items and total price
                $total_items = 0;
                $total = 0;
                foreach ($products as $product) {
                    $total_items += $product->quantity;
                    $total += $product->price * $product->quantity;
                }

                // Determine final price
                $final_price = $subscription_price == 0 ? $total : $subscription_price;
                $final_price = number_format($final_price, 2, '.', ',');

                // Set default name if empty
                if (trim($name) == '') {
                    $name = 'Bocs per ' . $frequency;
                }

                // Add subscription data to the result array
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

        return $result; // Return the array of subscription data
    }
}
