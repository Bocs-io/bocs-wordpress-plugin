<?php
/**
 * Bocs Order Notes Class
 * 
 * Handles adding BOCS-specific data to order notes
 * 
 * @package    Bocs
 * @subpackage Bocs/includes
 */

class Bocs_Order_Notes {
    
    /**
     * API credentials
     *
     * @var array
     */
    private $credentials;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get API credentials from options
        $options = get_option('bocs_plugin_options', array());
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();
        
        $this->credentials = array(
            'organization' => $options['bocs_headers']['organization'] ?? '',
            'store' => $options['bocs_headers']['store'] ?? '',
            'authorization' => $options['bocs_headers']['authorization'] ?? ''
        );
        
        // Use a proper priority system to avoid duplicate processing:
        // - Hook into order creation with priority 40 (after most core processes)
        // - Hook into status change with lower priority 20 (will only run if not already processed)
        add_action('woocommerce_checkout_order_processed', array($this, 'add_bocs_details_to_order_notes'), 40, 3);
        // Add hook for Store API checkout completion
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'handle_store_api_order'), 40, 1);
        add_action('woocommerce_order_status_processing', array($this, 'add_bocs_details_to_order_notes_on_processing'), 20, 1);
    }
    
    /**
     * Handle Store API order created event (passes only the order object)
     * 
     * @param WC_Order $order The order object
     */
    public function handle_store_api_order($order) {
        // Just pass the order to our processing function, with null for the other params
        $this->fetch_and_add_bocs_details($order);
    }
    
    /**
     * Add BOCS details to order notes when an order is created
     * 
     * @param int $order_id The order ID
     * @param array $posted_data The posted data
     * @param WC_Order $order The order object
     */
    public function add_bocs_details_to_order_notes($order_id, $posted_data, $order) {
        // Add a transient to prevent duplicate processing in case of race conditions
        $transient_key = 'bocs_processing_order_' . $order_id;
        if (get_transient($transient_key)) {
            return;
        }

        // Set a short-lived transient to prevent parallel processing
        set_transient($transient_key, true, 30); // 30 seconds should be enough

        try {
            $this->fetch_and_add_bocs_details($order);
        } finally {
            // Always clean up the transient, even if an error occurs
            delete_transient($transient_key);
        }
    }
    
    /**
     * Add BOCS details to order notes when an order moves to processing status
     * 
     * @param int $order_id The order ID
     */
    public function add_bocs_details_to_order_notes_on_processing($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if we've already added the note or if it's being processed
        if (get_transient('bocs_processing_order_' . $order_id)) {
            return;
        }

        // Also check if the note was already added (double check)
        $note_added = $order->get_meta('_bocs_details_note_added');
        if ($note_added === 'yes') {
            return;
        }

        // Set processing transient
        set_transient('bocs_processing_order_' . $order_id, true, 30);

        try {
            $this->fetch_and_add_bocs_details($order);
        } finally {
            delete_transient('bocs_processing_order_' . $order_id);
        }
    }
    
    /**
     * Fetch BOCS details from API and add them to order notes
     * 
     * @param WC_Order $order The order object
     */
    private function fetch_and_add_bocs_details($order) {
        // Use WordPress transient system for locking
        $lock_key = 'bocs_lock_order_notes_' . $order->get_id();
        
        // Try to get the lock
        if (get_transient($lock_key)) {
            return;
        }
        
        // Set lock for 30 seconds
        set_transient($lock_key, true, 30);
        
        try {
            // Log all order meta for debugging
            $all_order_meta = get_post_meta($order->get_id());
            $bocs_meta = array_filter(array_keys($all_order_meta), function($key) {
                return strpos($key, '__bocs') === 0;
            });
            
            // Check for Debug cookie data
            $debug_cookies = $order->get_meta('__bocs_debug_cookies');
            
            // Get BOCS ID from order meta
            $bocs_id = $order->get_meta('__bocs_bocs_id');
            
            // If no BOCS ID, check if it's in the URL parameters (for Store API requests)
            if (empty($bocs_id) && isset($_GET['bocs']) && !empty($_GET['bocs'])) {
                $bocs_id = sanitize_text_field($_GET['bocs']);
                
                // Save it to the order meta
                $order->update_meta_data('__bocs_bocs_id', $bocs_id);
                $order->save();
            }
            
            // If still no BOCS ID, nothing to do
            if (empty($bocs_id)) {
                return;
            }
            
            // Validate BOCS ID format (should be UUID format)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $bocs_id)) {
                return;
            }
            
            // Double check if we've already added the BOCS details note
            // Do a fresh get_meta to ensure we have the latest value
            $order = wc_get_order($order->get_id()); // Refresh order object
            $note_added = $order->get_meta('_bocs_details_note_added');
            if ($note_added === 'yes') {
                return;
            }
            
            // Also check for existing notes with the same header to prevent duplicates
            $notes = wc_get_order_notes(array('order_id' => $order->get_id()));
            foreach ($notes as $note) {
                if (strpos($note->content, 'BOCS Subscription Details:') !== false) {
                    // Note already exists, mark it as added and return
                    $order->update_meta_data('_bocs_details_note_added', 'yes');
                    $order->save();
                    return;
                }
            }
            
            try {
                // Fetch BOCS data from API
                $bocs_data = $this->fetch_bocs_data($bocs_id);
                
                if (!empty($bocs_data) && isset($bocs_data['data'])) {
                    // Format and add the note
                    $note = $this->format_bocs_note($bocs_data['data'], $bocs_id, $order);
                    $order->add_order_note($note);
                    
                    // Mark that we've added the note
                    $order->update_meta_data('_bocs_details_note_added', 'yes');
                    $order->save();
                }
            } catch (Exception $e) {
                // Silent fail
            }
        } finally {
            // Always release the lock
            delete_transient($lock_key);
        }
    }
    
    /**
     * Fetch BOCS data from the API
     * 
     * @param string $bocs_id The BOCS ID
     * @return array The API response data
     */
    private function fetch_bocs_data($bocs_id) {
        // Make sure we have credentials
        if (empty($this->credentials['organization']) || 
            empty($this->credentials['store']) || 
            empty($this->credentials['authorization'])) {
            throw new Exception('BOCS API credentials are not configured');
        }
        
        // Initialize cURL
        $curl = curl_init();
        
        $api_url = BOCS_API_URL . 'bocs/' . $bocs_id;
        
        // Set cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Store: ' . $this->credentials['store'],
                'Organization: ' . $this->credentials['organization'],
                'Content-Type: application/json',
                'Authorization: ' . $this->credentials['authorization']
            ),
        ));
        
        // Execute request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        // Check for errors
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception('cURL error: ' . $error);
        }
        
        curl_close($curl);
        
        // Check HTTP status
        if ($http_code !== 200) {
            throw new Exception('API returned error code: ' . $http_code);
        }
        
        // Parse JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'JSON parsing error: ' . json_last_error_msg();
            throw new Exception($error_msg);
        }
        
        // Verify the expected data structure
        if (!isset($data['data'])) {
            $error_msg = 'Unexpected API response structure - missing data key';
            throw new Exception($error_msg);
        }
        
        return $data;
    }
    
    /**
     * Format BOCS data into a readable note
     * 
     * @param array $data The BOCS data
     * @param string $bocs_id The BOCS ID
     * @param WC_Order $order The order object
     * @return string The formatted note
     */
    private function format_bocs_note($data, $bocs_id, $order) {
        // Start building the note
        $note = '<strong>BOCS Subscription Details:</strong><br>';
        $note .= '------------------------------<br>';
        $note .= '<strong>First Purchase (Parent Order)</strong> ' . esc_html($bocs_id) . '<br>';
        
        // Basic BOCS information
        $note .= '<strong>BOCS ID:</strong> ' . esc_html($bocs_id) . '<br>';
        $note .= '<strong>Name:</strong> ' . esc_html($data['name'] ?? 'N/A') . '<br>';
        $note .= '<strong>Status:</strong> ' . esc_html($data['status'] ?? 'N/A') . '<br>';
        $note .= '<strong>Type:</strong> ' . esc_html($data['type'] ?? 'N/A') . '<br>';
        $note .= '<strong>Version:</strong> ' . esc_html($data['version'] ?? 'N/A') . '<br>';
        $note .= '<strong>Created:</strong> ' . esc_html(date('Y-m-d H:i:s', strtotime($data['createdAt'] ?? 'now'))) . '<br>';
        
        // Store Theme information if available
        if (isset($data['storeTheme']) && is_array($data['storeTheme'])) {
            $note .= '<strong>Store Theme:</strong> ' . esc_html($data['storeTheme']['name'] ?? 'N/A') . '<br>';
        }
        
        // Store Template information if available
        if (isset($data['storeTemplate']) && is_array($data['storeTemplate'])) {
            $note .= '<strong>Store Template:</strong> ' . esc_html($data['storeTemplate']['name'] ?? 'N/A') . '<br>';
        }
        
        // Get selected frequency ID from order meta
        $selected_frequency_id = $order->get_meta('__bocs_frequency_id');
        
        // Price Adjustment information - only show selected frequency
        if (isset($data['priceAdjustment']) && isset($data['priceAdjustment']['adjustments']) && is_array($data['priceAdjustment']['adjustments'])) {
            $note .= '<br><strong>Selected Subscription Plan:</strong><br>';
            
            $found_selected_frequency = false;
            
            foreach ($data['priceAdjustment']['adjustments'] as $adjustment) {
                // Only show the selected frequency
                if (empty($selected_frequency_id) || $adjustment['id'] === $selected_frequency_id) {
                    $frequency = $adjustment['frequency'] ?? '?';
                    $timeUnit = isset($adjustment['timeUnit']) ? strtolower($adjustment['timeUnit']) : 'period';
                    // Make time unit singular if frequency is 1
                    if ($frequency == 1 && substr($timeUnit, -1) === 's') {
                        $timeUnit = substr($timeUnit, 0, -1);
                    }
                    
                    $note .= '- Every ' . esc_html($frequency) . ' ' . esc_html($timeUnit);
                    
                    // Add discount information
                    if (isset($adjustment['discount']) && $adjustment['discount'] > 0) {
                        $discount = $adjustment['discount'];
                        $discountType = isset($adjustment['discountType']) ? strtolower($adjustment['discountType']) : 'dollar';
                        
                        if ($discountType === 'percent') {
                            $note .= ' (' . esc_html($discount) . '% off)';
                        } else {
                            $note .= ' ($' . esc_html($discount) . ' off)';
                        }
                    }
                    
                    // Add scheduled payment date if available
                    if (isset($adjustment['scheduledPaymentDate']) && $adjustment['scheduledPaymentDate'] > 0) {
                        $note .= ' - Payment on day ' . esc_html($adjustment['scheduledPaymentDate']);
                    }
                    
                    $note .= '<br>';
                    $found_selected_frequency = true;
                    break; // Only show the first matching frequency
                }
            }
            
            // If no selected frequency found, show a message
            if (!$found_selected_frequency) {
                // If we have a frequency ID but couldn't find it, show that information
                if (!empty($selected_frequency_id)) {
                    $note .= '- Selected frequency ID: ' . esc_html($selected_frequency_id) . ' (details not found)<br>';
                } else {
                    // Try to get frequency information from order meta directly
                    $interval = $order->get_meta('__bocs_frequency_interval');
                    $time_unit = $order->get_meta('__bocs_frequency_time_unit');
                    
                    if (!empty($interval) && !empty($time_unit)) {
                        // Normalize time unit
                        $time_unit = strtolower($time_unit);
                        if ($interval == 1 && substr($time_unit, -1) === 's') {
                            $time_unit = substr($time_unit, 0, -1);
                        }
                        
                        $note .= '- Every ' . esc_html($interval) . ' ' . esc_html($time_unit) . '<br>';
                    } else {
                        $note .= '- Frequency details not available<br>';
                    }
                }
            }
        }
        
        // Products information with detailed formatting
        if (isset($data['products']) && is_array($data['products'])) {
            $note .= '<br><strong>Products:</strong><br>';
            
            $total_price = 0;
            
            foreach ($data['products'] as $product) {
                $name = $product['name'] ?? 'Unknown Product';
                $quantity = $product['quantity'] ?? 1;
                $price = isset($product['price']) ? $product['price'] : 0;
                $formatted_price = '$' . number_format((float)$price, 2);
                $product_total = $price * $quantity;
                $total_price += $product_total;
                
                $note .= '- ' . esc_html($quantity) . 'x ' . esc_html($name) . ' (' . esc_html($formatted_price) . ' each)<br>';
                
                // Add SKU if available
                if (!empty($product['sku'])) {
                    $note .= '  <em>SKU: ' . esc_html($product['sku']) . '</em><br>';
                }
                
                // Add WooCommerce product ID if available
                if (!empty($product['externalSourceId'])) {
                    $note .= '  <em>WC Product ID: ' . esc_html($product['externalSourceId']) . '</em><br>';
                }
            }
            
            // Add total price for all products
            $note .= '<strong>Products Total:</strong> $' . number_format($total_price, 2) . '<br>';
        }
        
        // Add description if available
        if (!empty($data['description'])) {
            $note .= '<br><strong>Description:</strong><br>';
            $note .= esc_html($data['description']) . '<br>';
        }
        
        return $note;
    }
}

// Initialize class
new Bocs_Order_Notes(); 