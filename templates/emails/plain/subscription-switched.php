<?php
/**
 * Subscription switched email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/subscription-switched.php.
 *
 * @package BOCS/Templates/Emails/Plain
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo sprintf( esc_html__( 'Hi %s,', 'bocs-wordpress-plugin' ), esc_html( $subscription->get_billing_first_name() ) ) . "\n\n";

echo esc_html__( 'Your subscription has been switched successfully. Here are the details of your updated subscription:', 'bocs-wordpress-plugin' ) . "\n\n";

/**
 * Show subscription details
 */
if ( $subscription ) {
    do_action( 'woocommerce_email_order_details', $subscription, $sent_to_admin, $plain_text, $email );
    echo "\n----------------------------------------\n\n";

    do_action( 'woocommerce_email_order_meta', $subscription, $sent_to_admin, $plain_text, $email );
    echo "\n----------------------------------------\n\n";

    do_action( 'woocommerce_email_customer_details', $subscription, $sent_to_admin, $plain_text, $email );
    echo "\n----------------------------------------\n\n";
}

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); 