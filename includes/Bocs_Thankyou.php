<?php
/**
 * Bocs Thankyou Class
 * 
 * Handles displaying BOCS subscription information on the thank-you/order-received page
 * 
 * @package    Bocs
 * @subpackage Bocs/includes
 */

class Bocs_Thankyou {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add hooks for displaying subscription info on thank-you page
        add_action('woocommerce_thankyou', array($this, 'display_subscription_details'), 10, 1);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_subscription_details_after_order'), 10, 1);
    }
    
    /**
     * Display subscription details on thank-you page
     * 
     * @param int $order_id Order ID
     * @return void
     */
    public function display_subscription_details($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Get BOCS ID from order meta
        $bocs_id = $order->get_meta('__bocs_bocs_id');
        if (empty($bocs_id)) {
            return;
        }
        
        // Get subscription details
        $subscription_details = $this->get_subscription_details_from_order($order);
        if (empty($subscription_details)) {
            return;
        }
        
        // Add a wrapper div with specific class for branding/styling
        echo '<div class="bocs-subscription-wrapper">';
        $this->render_subscription_box($subscription_details, $order);
        echo '</div>';
        
        // Add BOCS branding script to specifically target this order page
        ?>
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                // Add BOCS branding class to the order received page
                $('.woocommerce-order-received').addClass('has-bocs-subscription');
                
                // Add subscription badge to order summary if it exists
                if ($('.woocommerce-order-overview').length) {
                    $('.woocommerce-order-overview').before(
                        '<div class="bocs-subscription-badge">' +
                        '<span><?php echo esc_html__('Subscription Order', 'bocs-wordpress'); ?></span>' +
                        '</div>'
                    );
                }
            });
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Display subscription details after order table on order page
     * 
     * @param WC_Order $order Order object
     * @return void
     */
    public function display_subscription_details_after_order($order) {
        if (!$order) {
            return;
        }
        
        // Get BOCS ID from order meta
        $bocs_id = $order->get_meta('__bocs_bocs_id');
        if (empty($bocs_id)) {
            return;
        }
        
        // Get subscription details
        $subscription_details = $this->get_subscription_details_from_order($order);
        if (empty($subscription_details)) {
            return;
        }
        
        $this->render_subscription_box($subscription_details, $order);
    }
    
    /**
     * Get subscription details from order meta
     * 
     * @param WC_Order $order Order object
     * @return array|null Subscription details or null if not available
     */
    private function get_subscription_details_from_order($order) {
        // Get basic subscription details from order meta
        $bocs_id = $order->get_meta('__bocs_bocs_id');
        $frequency = $order->get_meta('__bocs_frequency_interval');
        $time_unit = $order->get_meta('__bocs_frequency_time_unit');
        $frequency_id = $order->get_meta('__bocs_frequency_id');
        $discount = $order->get_meta('__bocs_discount');
        $discount_type = $order->get_meta('__bocs_discount_type');
        
        // If no frequency info, try to get it from a note
        if (empty($frequency) || empty($time_unit)) {
            // Get the note containing subscription details if it exists
            $notes = wc_get_order_notes(array('order_id' => $order->get_id()));
            foreach ($notes as $note) {
                if (strpos($note->content, 'BOCS Subscription Details:') !== false) {
                    // Extract frequency and time unit using regex
                    if (preg_match('/Every\s+(\d+)\s+([a-z]+)/', $note->content, $matches)) {
                        $frequency = $matches[1];
                        $time_unit = $matches[2];
                    }
                    break;
                }
            }
        }
        
        // If still no frequency info, return null
        if (empty($frequency) || empty($time_unit)) {
            return null;
        }
        
        // Format discount text
        $discount_text = '';
        if (!empty($discount) && $discount > 0) {
            if ($discount_type === 'percent') {
                $discount_text = '(' . $discount . '% off)';
            } else {
                $discount_text = '($' . $discount . ' off)';
            }
        }
        
        // Build subscription details array
        return array(
            'bocs_id' => $bocs_id,
            'frequency' => $frequency,
            'time_unit' => $time_unit,
            'discount_text' => $discount_text,
            'frequency_id' => $frequency_id,
        );
    }
    
    /**
     * Render subscription box on thank you page
     * 
     * @param array $subscription_details Subscription details
     * @param WC_Order $order Order object
     * @return void
     */
    private function render_subscription_box($subscription_details, $order) {
        // Get next payment date - this is an estimation
        $next_payment_date = $this->calculate_next_payment_date($subscription_details, $order);
        
        // Start output
        echo '<div class="bocs-subscription-box">';
        
        // Add subscription icon/badge
        echo '<div class="bocs-subscription-header">';
        echo '<span class="bocs-subscription-icon">↻</span>';
        echo '<h2>' . esc_html__('Subscription Information', 'bocs-wordpress') . '</h2>';
        echo '</div>';
        
        echo '<table class="bocs-subscription-details-table">';
        
        // Subscription details
        echo '<tr>';
        echo '<th>' . esc_html__('Subscription', 'bocs-wordpress') . '</th>';
        echo '<td>' . sprintf(
            esc_html__('Every %s %s', 'bocs-wordpress'),
            esc_html($subscription_details['frequency']),
            esc_html($subscription_details['time_unit'])
        );
        
        // Show discount if available
        if (!empty($subscription_details['discount_text'])) {
            echo ' ' . esc_html($subscription_details['discount_text']);
        }
        
        echo '</td>';
        echo '</tr>';
        
        // Next payment date
        if (!empty($next_payment_date)) {
            echo '<tr>';
            echo '<th>' . esc_html__('Next Payment', 'bocs-wordpress') . '</th>';
            echo '<td>' . esc_html($next_payment_date) . '</td>';
            echo '</tr>';
        }
        
        // Subscription ID
        echo '<tr>';
        echo '<th>' . esc_html__('Subscription ID', 'bocs-wordpress') . '</th>';
        echo '<td>' . esc_html($subscription_details['bocs_id']) . '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // Add link to cancel subscription if available
        if (!empty($subscription_details['bocs_id'])) {
            echo '<div class="bocs-subscription-actions">';
            echo '<a href="' . esc_url(wc_get_account_endpoint_url('subscriptions')) . '" class="button">' . 
                esc_html__('Manage Subscription', 'bocs-wordpress') . '</a>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // We're no longer adding inline styles here since they're in the CSS file
        // which is conditionally loaded only when a BOCS subscription is detected
    }
    
    /**
     * Calculate next payment date based on subscription details
     * 
     * @param array $subscription_details Subscription details
     * @param WC_Order $order Order object
     * @return string Formatted next payment date
     */
    private function calculate_next_payment_date($subscription_details, $order) {
        // Get order date
        $order_date = $order->get_date_created();
        if (!$order_date) {
            return '';
        }
        
        // Get frequency and time unit
        $frequency = intval($subscription_details['frequency']);
        $time_unit = strtolower($subscription_details['time_unit']);
        
        // Convert time unit to compatible format
        $interval_spec = 'P';
        switch ($time_unit) {
            case 'day':
            case 'days':
                $interval_spec .= $frequency . 'D';
                break;
            case 'week':
            case 'weeks':
                $interval_spec .= $frequency * 7 . 'D';
                break;
            case 'month':
            case 'months':
                $interval_spec .= $frequency . 'M';
                break;
            case 'year':
            case 'years':
                $interval_spec .= $frequency . 'Y';
                break;
            default:
                return '';
        }
        
        // Create DateInterval and add to order date
        try {
            $date_interval = new DateInterval($interval_spec);
            $next_date = clone $order_date;
            $next_date->add($date_interval);
            
            // Format the date
            return $next_date->date_i18n(get_option('date_format'));
        } catch (Exception $e) {
            return '';
        }
    }
}

// Initialize the class
new Bocs_Thankyou(); 