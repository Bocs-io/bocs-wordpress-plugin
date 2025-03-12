/**
 * Get a list of all email templates that can be overridden by users
 *
 * @return array List of email templates
 */
public static function get_overridable_templates() {
    return array(
        // HTML Email Templates 
        // Copy these templates to: yourtheme/bocs-wordpress/emails/
        'bocs-customer-subscription-renewal-processing.php' => __('Sent when a subscription renewal order is processing', 'bocs-wordpress'),
        'bocs-customer-welcome.php'                  => __('Welcome email sent to new customers', 'bocs-wordpress'),
        'bocs-subscription-switched.php'             => __('Sent when a customer switches their subscription', 'bocs-wordpress'),
        'bocs-customer-upcoming-renewal-reminder.php' => __('Sent before an automatic subscription renewal', 'bocs-wordpress'),
        'bocs-customer-subscription-paused.php'      => __('Sent when a subscription is paused', 'bocs-wordpress'),
        'bocs-customer-subscription-reactivated.php' => __('Sent when a subscription is reactivated', 'bocs-wordpress'),
        'bocs-customer-subscription-cancelled.php'   => __('Sent when a subscription is cancelled', 'bocs-wordpress'),
        'bocs-customer-payment-method-update.php'    => __('Sent when payment method is updated', 'bocs-wordpress'),
        'bocs-customer-payment-retry.php'            => __('Notification about payment retry attempts', 'bocs-wordpress'),
        'bocs-customer-processing-renewal-order.php' => __('Sent when a renewal order is processing', 'bocs-wordpress'),
        'bocs-customer-renewal-invoice.php'          => __('Sent for renewal orders that require payment', 'bocs-wordpress'),
        'bocs-customer-on-hold-renewal-order.php'    => __('Sent when a renewal order is placed on hold', 'bocs-wordpress'),
        'bocs-customer-completed-renewal-order.php'  => __('Sent when a renewal order is completed', 'bocs-wordpress'),
        'bocs-customer-failed-renewal-payment.php'   => __('Sent when a renewal payment fails', 'bocs-wordpress'),
        'bocs-customer-manual-renewal-reminder.php'  => __('Reminder to manually renew a subscription', 'bocs-wordpress'),
        
        // Plain Text Email Templates
        // Copy these templates to: yourtheme/bocs-wordpress/emails/plain/
        'plain/bocs-customer-subscription-renewal-processing.php' => __('Plain text version of subscription renewal processing', 'bocs-wordpress'),
        'plain/bocs-customer-welcome.php'                  => __('Plain text version of welcome email', 'bocs-wordpress'),
        'plain/bocs-subscription-switched.php'             => __('Plain text version of subscription switched email', 'bocs-wordpress'),
        'plain/bocs-customer-upcoming-renewal-reminder.php' => __('Plain text version of renewal reminder', 'bocs-wordpress'),
        'plain/bocs-customer-subscription-paused.php'      => __('Plain text version of subscription paused email', 'bocs-wordpress'),
        'plain/bocs-customer-subscription-reactivated.php' => __('Plain text version of reactivation email', 'bocs-wordpress'),
        'plain/bocs-customer-subscription-cancelled.php'   => __('Plain text version of cancellation email', 'bocs-wordpress'),
        'plain/bocs-customer-payment-method-update.php'    => __('Plain text version of payment method update', 'bocs-wordpress'),
        'plain/bocs-customer-payment-retry.php'            => __('Plain text version of payment retry notification', 'bocs-wordpress'),
        'plain/bocs-customer-processing-renewal-order.php' => __('Plain text version of processing renewal', 'bocs-wordpress'),
        'plain/bocs-customer-renewal-invoice.php'          => __('Plain text version of renewal invoice', 'bocs-wordpress'),
        'plain/bocs-customer-on-hold-renewal-order.php'    => __('Plain text version of on-hold renewal', 'bocs-wordpress'),
        'plain/bocs-customer-completed-renewal-order.php'  => __('Plain text version of completed renewal', 'bocs-wordpress'),
        'plain/bocs-customer-failed-renewal-payment.php'   => __('Plain text version of failed payment', 'bocs-wordpress'),
        'plain/bocs-customer-manual-renewal-reminder.php'  => __('Plain text version of manual renewal reminder', 'bocs-wordpress'),
    );
} 