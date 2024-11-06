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
        $result = array();
        
        try {
            // Verify WooCommerce is active
            if (!function_exists('get_woocommerce_currency_symbol')) {
                throw new Exception('WooCommerce is not active');
            }

            // Retrieve and validate Bocs plugin options
            $options = get_option('bocs_plugin_options', array());
            $options['bocs_headers'] = $options['bocs_headers'] ?? array();

            if(empty($options['bocs_headers']['organization']) || 
               empty($options['bocs_headers']['store']) || 
               empty($options['bocs_headers']['authorization'])) {
                return $result;
            }

            $currency_symbol = get_woocommerce_currency_symbol();

            // Initialize and validate cURL
            if (!function_exists('curl_init')) {
                throw new Exception('cURL is not installed');
            }

            $curl = curl_init();
            if ($curl === false) {
                throw new Exception('Failed to initialize cURL');
            }

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

            // Execute request and handle potential errors
            $response = curl_exec($curl);
            if ($response === false) {
                throw new Exception('cURL error: ' . curl_error($curl));
            }

            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($http_code !== 200) {
                throw new Exception('API returned error code: ' . $http_code);
            }

            curl_close($curl);

            // Validate JSON response
            $object = json_decode($response);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            if (!$object || !isset($object->data->data)) {
                return $result;
            }

            foreach ($object->data->data as $subscription) {
                try {
                    // Validate required subscription properties
                    if (!isset($subscription->id) || 
                        !isset($subscription->status) || 
                        !isset($subscription->billing) || 
                        !isset($subscription->lineItems)) {
                        continue;
                    }

                    // Safely access properties with null coalescing
                    $status = $subscription->status ?? '';
                    $subscription_price = round($subscription->total ?? 0, 2);
                    $products = $subscription->lineItems ?? array();

                    // Safely get name and type
                    $name = isset($subscription->bocs->name) ? $subscription->bocs->name : "";
                    $type = isset($subscription->bocs->type) ? $subscription->bocs->type : "";

                    // Safely construct contact info
                    $first_name = $subscription->billing->firstName ?? '';
                    $last_name = $subscription->billing->lastName ?? '';
                    
                    // Safely handle dates
                    $next_order_date = '';
                    if (!empty($subscription->nextPaymentDateGmt)) {
                        try {
                            $date = new DateTime($subscription->nextPaymentDateGmt);
                            $next_order_date = $date->format('F j, Y, g:i a');
                        } catch (Exception $e) {
                            error_log('Invalid next payment date: ' . $e->getMessage());
                        }
                    }

                    $activationDate = '';
                    if (!empty($subscription->createdAt)) {
                        try {
                            $date = new DateTime($subscription->createdAt);
                            $activationDate = $date->format('F j, Y, g:i a');
                        } catch (Exception $e) {
                            error_log('Invalid creation date: ' . $e->getMessage());
                        }
                    }

                    // Calculate totals safely
                    $total_items = 0;
                    $total = 0;
                    foreach ($products as $product) {
                        $quantity = $product->quantity ?? 0;
                        $price = $product->price ?? 0;
                        $total_items += $quantity;
                        $total += $price * $quantity;
                    }

                    $final_price = $subscription_price == 0 ? $total : $subscription_price;
                    $final_price = number_format($final_price, 2, '.', ',');

                    if (trim($name) == '') {
                        $frequency = '';
                        if (isset($subscription->frequency)) {
                            $freq_num = $subscription->frequency->frequency ?? 1;
                            $time_unit = $subscription->frequency->timeUnit ?? '';
                            if ($freq_num == 1 && !empty($time_unit)) {
                                $frequency = rtrim($time_unit, 's');
                            } else {
                                $frequency = $freq_num . ' ' . $time_unit;
                            }
                        }
                        $name = 'Bocs per ' . $frequency;
                    }

                    $result[] = array(
                        "id" => $subscription->id,
                        "status" => $status,
                        "first_name" => $first_name,
                        "last_name" => $last_name,
                        "subscription" => $name . ' for ' . $first_name . ' ' . $last_name,
                        "items" => $total_items . ' items',
                        "total" => $currency_symbol . $final_price,
                        "start_date" => $activationDate,
                        "next_payment" => $next_order_date,
                        "last_order_date" => $activationDate,
                        "orders" => 1
                    );
                } catch (Exception $e) {
                    error_log('Error processing subscription: ' . $e->getMessage());
                    continue;
                }
            }

        } catch (Exception $e) {
            error_log('Bocs table data error: ' . $e->getMessage());
        }

        return $result;
    }
}
