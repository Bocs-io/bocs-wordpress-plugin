<?php
/**
 * Subscription switched email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/subscription-switched.php.
 *
 * @package BOCS/Templates/Emails
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'bocs-wordpress-plugin' ), esc_html( $subscription->get_billing_first_name() ) ); ?></p>

<p><?php esc_html_e( 'Your subscription has been switched successfully. Here are the details of your updated subscription:', 'bocs-wordpress-plugin' ); ?></p>

<?php
/**
 * Show subscription details
 */
if ( $subscription ) {
    do_action( 'woocommerce_email_order_details', $subscription, $sent_to_admin, $plain_text, $email );

    do_action( 'woocommerce_email_order_meta', $subscription, $sent_to_admin, $plain_text, $email );

    do_action( 'woocommerce_email_customer_details', $subscription, $sent_to_admin, $plain_text, $email );
}
?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email ); 