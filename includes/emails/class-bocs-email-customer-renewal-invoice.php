<?php
/**
 * Bocs Customer Renewal Invoice Email Class
 *
 * Handles email notifications for subscription renewal invoices.
 *
 * @package    Bocs
 * @subpackage Bocs/includes/emails
 * @since      0.0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Bocs_Email_Customer_Renewal_Invoice
{
    /**
     * Email ID
     *
     * @var string
     */
    private $id;

    /**
     * Email title
     *
     * @var string
     */
    private $title;

    /**
     * Email description
     *
     * @var string
     */
    private $description;

    /**
     * Email heading
     *
     * @var string
     */
    private $heading;

    /**
     * Email subject
     *
     * @var string
     */
    private $subject;

    /**
     * Email recipient
     *
     * @var string
     */
    private $recipient;

    /**
     * Order object
     *
     * @var WC_Order
     */
    private $object;

    /**
     * Initialize email settings
     *
     * @since 0.0.1
     */
    public function __construct()
    {
        $this->id = 'wc_bocs_email_customer_renewal_invoice';
        $this->title = __('Bocs Customer Renewal Invoice', 'bocs-wordpress');
        $this->description = __('Email sent to customers containing their renewal order invoice', 'bocs-wordpress');
        $this->heading = __('Subscription Renewal Invoice', 'bocs-wordpress');
        $this->subject = __('Your renewal invoice', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email
     *
     * @since 0.0.1
     * @param int $order_id Order ID
     * @return bool
     */
    public function trigger($order_id)
    {
        try {
            if (!$order_id) {
                throw new Exception(__('No order ID provided', 'bocs-wordpress'));
            }

            $this->object = wc_get_order($order_id);
            if (!$this->object) {
                throw new Exception(
                    sprintf(
                        /* translators: %d: Order ID */
                        __('Order #%d not found', 'bocs-wordpress'),
                        $order_id
                    )
                );
            }

            $this->recipient = $this->object->get_billing_email();
            if (!$this->recipient) {
                throw new Exception(
                    sprintf(
                        /* translators: %d: Order ID */
                        __('No recipient email found for order #%d', 'bocs-wordpress'),
                        $order_id
                    )
                );
            }

            $headers = array('Content-Type: text/html; charset=UTF-8');
            $message = $this->get_email_content();

            $sent = wp_mail(
                $this->recipient,
                $this->subject,
                $message,
                $headers
            );

            if (!$sent) {
                throw new Exception(
                    sprintf(
                        /* translators: %d: Order ID */
                        __('Failed to send renewal invoice email for order #%d', 'bocs-wordpress'),
                        $order_id
                    )
                );
            }

            return true;

        } catch (Exception $e) {
            error_log(
                sprintf(
                    /* translators: 1: Order ID, 2: Error message */
                    __('Critical: Renewal invoice email error for order #%1$d: %2$s', 'bocs-wordpress'),
                    $order_id,
                    $e->getMessage()
                )
            );
            return false;
        }
    }

    /**
     * Get email content
     *
     * @since 0.0.1
     * @return string
     */
    private function get_email_content()
    {
        $order = $this->object;
        
        ob_start();
        ?>
        <h1><?php esc_html_e('Subscription Renewal Invoice', 'bocs-wordpress'); ?></h1>
        
        <?php if ($order->has_status('pending')): ?>
            <p>
                <?php 
                printf(
                    /* translators: %s: Payment link */
                    esc_html__('An order has been created for you to renew your subscription. To pay for this invoice please use the following link: %s', 'bocs-wordpress'),
                    sprintf('<a href="%1$s">%2$s</a>',
                        esc_url($order->get_checkout_payment_url()),
                        esc_html__('Pay Now »', 'bocs-wordpress')
                    )
                );
                ?>
            </p>
        <?php elseif ($order->has_status('failed')): ?>
            <p>
                <?php 
                printf(
                    /* translators: %s: Payment link */
                    esc_html__('The automatic payment to renew your subscription has failed. To reactivate the subscription, please log in and pay for the renewal from your account page: %s', 'bocs-wordpress'),
                    sprintf('<a href="%1$s">%2$s</a>',
                        esc_url($order->get_checkout_payment_url()),
                        esc_html__('Pay Now »', 'bocs-wordpress')
                    )
                );
                ?>
            </p>
        <?php endif; ?>

        <p><?php esc_html_e('Your renewal order details are below:', 'bocs-wordpress'); ?></p>

        <p>
            <?php 
            echo sprintf(
                /* translators: %s: Order number */
                esc_html__('Order Number: %s', 'bocs-wordpress'),
                esc_html($order->get_order_number())
            ); 
            ?>
        </p>
        <p>
            <?php 
            echo sprintf(
                /* translators: %s: Order date */
                esc_html__('Order Date: %s', 'bocs-wordpress'),
                esc_html(wc_format_datetime($order->get_date_created()))
            ); 
            ?>
        </p>

        <h2><?php esc_html_e('Order Details', 'bocs-wordpress'); ?></h2>
        <ul>
            <?php
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                printf(
                    '<li>%1$s x %2$d</li>',
                    esc_html($product->get_name()),
                    esc_html($item->get_quantity())
                );
            }
            ?>
        </ul>

        <p>
            <?php 
            echo sprintf(
                /* translators: %s: Order total */
                esc_html__('Total: %s', 'bocs-wordpress'),
                wp_kses_post($order->get_formatted_order_total())
            ); 
            ?>
        </p>

        <p><?php esc_html_e('We appreciate your business and look forward to serving you again.', 'bocs-wordpress'); ?></p>
        <?php
        return ob_get_clean();
    }
}
