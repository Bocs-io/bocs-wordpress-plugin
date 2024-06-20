<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Bocs_Email_Customer_Renewal_Invoice
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
        $this->id = 'wc_bocs_email_customer_renewal_invoice';
        $this->title = 'Bocs Customer Renewal Invoice';
        $this->description = 'Bocs email customer renewal invoice';
        $this->heading = 'Thank you for your order';
        $this->subject = 'Your renewal invoice';

        $this->template_html  = 'emails/customer-renewal-invoice.php';
        $this->template_plain = 'emails/plain/customer-renewal-invoice.php';
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
            $subject = 'Your Renewal Invoice';
            $message = '';

            if ($order->has_status('pending')) {
                $message = 'An order has been created for you to renew your subscription. To pay for this invoice please use the following link: <a href="' . esc_url($order->get_checkout_payment_url()) . '">Pay Now &raquo;</a>';
            } else if ($order->has_status('failed')) {
                $message = 'The automatic payment to renew your subscription has failed. To reactivate the subscription, please log in and pay for the renewal from your account page: <a href="' . esc_url($order->get_checkout_payment_url()) . '">Pay Now &raquo;</a>';
            }
            $message .= '<p>Thank you for your continued subscription. Your renewal order details are below:</p>';
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
