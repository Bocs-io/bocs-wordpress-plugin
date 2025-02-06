<?php
/**
 * Bocs Processing Renewal Order Email Class
 *
 * Handles email notifications for processing subscription renewal orders.
 *
 * @package    Bocs
 * @subpackage Bocs/includes/emails
 * @since      0.0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Bocs_Email_Processing_Renewal_Order
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
     * HTML template path
     *
     * @var string
     */
    private $template_html;

    /**
     * Plain text template path
     *
     * @var string
     */
    private $template_plain;

    /**
     * Template base path
     *
     * @var string
     */
    private $template_base;

    /**
     * Initialize email settings
     *
     * @since 0.0.1
     */
    public function __construct()
    {
        $this->id = 'wc_bocs_email_processing_renewal_order';
        $this->title = __('Bocs Processing Renewal Order', 'bocs-wordpress');
        $this->description = __('Email notification for processing subscription renewal orders', 'bocs-wordpress');
        $this->heading = __('Your renewal order is being processed', 'bocs-wordpress');
        $this->subject = __('Your subscription renewal order is being processed', 'bocs-wordpress');

        $this->template_html = 'emails/process-renewal-order.php';
        $this->template_plain = 'emails/plain/process-renewal-order.php';
        $this->template_base = plugin_dir_path(__FILE__) . 'views/';
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
                        __('Failed to send processing renewal order email for order #%d', 'bocs-wordpress'),
                        $order_id
                    )
                );
            }

            return true;

        } catch (Exception $e) {
            error_log(
                sprintf(
                    /* translators: 1: Order ID, 2: Error message */
                    __('Critical: Processing renewal order email error for order #%1$d: %2$s', 'bocs-wordpress'),
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
        <h1><?php esc_html_e('Processing Subscription Renewal Order', 'bocs-wordpress'); ?></h1>
        <p><?php esc_html_e('Thank you for your continued subscription. Your renewal order is now being processed:', 'bocs-wordpress'); ?></p>
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
