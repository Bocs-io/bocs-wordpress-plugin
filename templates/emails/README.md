## Template Overrides

### Email Templates
The BOCS plugin includes several email templates that can be customized by copying them to your theme. To override a template:

1. Copy the template file from the BOCS plugin's `templates/emails/` directory to `yourtheme/bocs-wordpress/emails/`
2. For plain text templates, copy from `templates/emails/plain/` to `yourtheme/bocs-wordpress/emails/plain/`
3. Modify the copied template file in your theme directory
4. WordPress will now use your customized template instead of the plugin's default

#### Available Email Templates for Override

**HTML Email Templates:**  
(Copy to `yourtheme/bocs-wordpress/emails/`)
- `customer-welcome.php` - Sent when a customer creates a new subscription
- `bocs-customer-welcome.php` - Alternative welcome email template
- `subscription-switched.php` - Sent when a customer switches their subscription
- `bocs-subscription-switched.php` - Alternative subscription switched template
- `customer-upcoming-renewal-reminder.php` - Sent before an automatic subscription renewal
- `customer-subscription-paused.php` - Sent when a subscription is paused
- `customer-subscription-reactivated.php` - Sent when a subscription is reactivated
- `customer-subscription-cancelled.php` - Sent when a subscription is cancelled
- `bocs-customer-subscription-cancelled.php` - Alternative subscription cancelled template
- `customer-payment-method-update.php` - Sent when payment method is updated
- `customer-payment-retry.php` - Notification about payment retry attempts
- `customer-processing-renewal-order.php` - Sent when a renewal order is processing
- `customer-renewal-invoice.php` - Sent for renewal orders that require payment
- `customer-invoice.php` - Sent for regular invoices
- `customer-on-hold-renewal-order.php` - Sent when a renewal order is placed on hold
- `customer-completed-renewal-order.php` - Sent when a renewal order is completed
- `customer-failed-renewal-payment.php` - Sent when a renewal payment fails
- `customer-manual-renewal-reminder.php` - Reminder to manually renew a subscription
- `email-header.php` - Email header template
- `email-footer.php` - Email footer template
- `email-styles.php` - Styles for HTML emails

**Plain Text Email Templates:**  
(Copy to `yourtheme/bocs-wordpress/emails/plain/`)
- `customer-welcome.php` - Plain text version of welcome email
- `bocs-customer-welcome.php` - Alternative plain text welcome email
- `subscription-switched.php` - Plain text version of subscription switched email
- `customer-upcoming-renewal-reminder.php` - Plain text version of renewal reminder
- `customer-subscription-paused.php` - Plain text version of subscription paused email
- `customer-subscription-reactivated.php` - Plain text version of reactivation email
- `customer-subscription-cancelled.php` - Plain text version of cancellation email
- `customer-payment-method-update.php` - Plain text version of payment method update
- `customer-payment-retry.php` - Plain text version of payment retry notification
- `customer-processing-renewal-order.php` - Plain text version of processing renewal
- `customer-renewal-invoice.php` - Plain text version of renewal invoice
- `customer-invoice.php` - Plain text version of regular invoice
- `customer-on-hold-renewal-order.php` - Plain text version of on-hold renewal
- `customer-completed-renewal-order.php` - Plain text version of completed renewal
- `customer-failed-renewal-payment.php` - Plain text version of failed payment
- `customer-manual-renewal-reminder.php` - Plain text version of manual renewal reminder

### Other Templates 
