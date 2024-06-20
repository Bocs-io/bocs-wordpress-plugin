<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Bocs_Email_Processing_Renewal_Order
{
    private $id;
    private $title;
    private $description;
    private $heading;
    private $subject;
    private $recipient;
    private $object;
    private $template_html;
    private $template_plain;
    private $template_base;

    public function __construct()
    {

        // Set email ID, title, description, and other properties
        $this->id = 'wc_bocs_email_processing_renewal_order';
        $this->title = 'Bocs Processing Renewal Order';
        $this->description = 'Bocs email processing renewal order';
        $this->heading = 'Thank you for your order';
        $this->subject = 'Your renewal order receipt';

        $this->template_html  = 'emails/process-renewal-order.php';
        $this->template_plain = 'emails/plain/process-renewal-order.php';
        $this->template_base  = plugin_dir_path(__FILE__) . 'views/';
    }

    public function trigger($order_id)
    {
        error_log('will be sending emails');
        if ($order_id) {
            error_log($order_id);
            $this->object = wc_get_order($order_id);
            $order = $this->object;
            $this->recipient = $this->object->get_billing_email();
            $to = $this->recipient;

            error_log('sending to ' . $to);

            if (!$to) {
                return;
            }


            $headers = array('Content-Type: text/html; charset=UTF-8');

            // Set the email subject
            $subject = 'Bocs Processing Renewal order';

            $message = 'Just to let you know &mdash; we\'ve received your subscription renewal order, and it is now being processed:';

            // Construct the email message
            //$message = '<h1>Subscription Renewal Order</h1>';
            //$message .= '<p>Thank you for your continued subscription. Your renewal order details are below:</p>';
            $message .= '<p>Order Number: ' . $order->get_order_number() . '</p>';
            $message .= '<p>Order Date: ' . wc_format_datetime($order->get_date_created()) . '</p>';
            $message .= '<h2>Order Details</h2>';
            $message .= '<ul>';

            // Get the order items
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                $message .= '<li>' . $product->get_name() . ' x ' . $item->get_quantity() . '</li>';
            }

            $message .= '</ul>';
            $message .= '<p>Total: ' . $order->get_formatted_order_total() . '</p>';
            $message .= '<p>We appreciate your business and look forward to serving you again.</p>';

            error_log($subject);
            error_log($message);
            error_log(print_r($headers, true));

            $send = wp_mail($to, $subject, $message, $headers);

            error_log(print_r($send, true));
        }
    }
}
