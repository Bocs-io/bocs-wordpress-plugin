<?php
/**
 * Email Styles
 *
 * This template contains the styling for HTML emails sent from Bocs.
 * This can be overridden by copying it to yourtheme/woocommerce/emails/email-styles.php.
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load colors
$bg               = '#f7f7f7';
$body             = '#ffffff';
$base             = '#ffffff';
$base_text        = '#333333';
$text             = '#333333';
$heading_text     = '#333333';
$bocs_primary     = '#3C7B7C'; // Bocs teal color
$bocs_secondary   = '#f8f9fa';
$bocs_accent      = '#FFA500'; // Adding an accent color (orange) for buttons, etc.
?>

<style type="text/css">
    /* Base */
    body {
        margin: 0;
        padding: 0;
        background-color: <?php echo esc_attr($bg); ?>;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        -webkit-text-size-adjust: none;
        text-size-adjust: none;
        color: <?php echo esc_attr($text); ?>;
        line-height: 1.6;
    }

    #wrapper {
        background-color: <?php echo esc_attr($bg); ?>;
        margin: 0;
        padding: 50px 0;
        -webkit-text-size-adjust: none;
        text-size-adjust: none;
        width: 100%;
    }

    #template_container {
        box-shadow: 0 1px 10px rgba(0, 0, 0, 0.1) !important;
        background-color: <?php echo esc_attr($body); ?>;
        border: 1px solid #e5e5e5;
        border-radius: 6px !important;
        max-width: 600px;
        margin: 0 auto;
    }

    #template_header {
        background-color: <?php echo esc_attr($bocs_primary); ?>;
        border-radius: 6px 6px 0 0 !important;
        color: <?php echo esc_attr($base); ?>;
        border-bottom: 0;
        font-weight: bold;
        line-height: 100%;
        vertical-align: middle;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }

    #header_wrapper {
        padding: 36px 48px;
        display: block;
    }

    #template_header h1 {
        color: <?php echo esc_attr($base); ?>;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 30px;
        font-weight: 300;
        line-height: 150%;
        margin: 0;
        text-align: left;
    }

    #template_body {
        background-color: <?php echo esc_attr($body); ?>;
        border-radius: 0 0 6px 6px !important;
    }

    #body_content {
        background-color: <?php echo esc_attr($body); ?>;
    }

    #body_content_inner {
        color: <?php echo esc_attr($text); ?>;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 14px;
        line-height: 150%;
        text-align: left;
        padding: 48px 48px 32px;
    }

    #template_footer {
        border-top: 1px solid #e5e5e5;
        background-color: <?php echo esc_attr($bg); ?>;
        border-radius: 0 0 6px 6px !important;
    }

    #footer_wrapper {
        padding: 24px 48px;
    }

    #credit {
        border-radius: 6px;
        border: 0;
        color: #8a8a8a;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 12px;
        line-height: 150%;
        text-align: center;
        padding: 24px 0;
    }

    /* Content */
    .bocs-email-container {
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        color: <?php echo esc_attr($text); ?>;
        line-height: 1.6;
        padding: 15px 30px;
    }
    
    .bocs-email-content {
        margin-bottom: 30px;
    }
    
    .bocs-button {
        display: inline-block;
        background-color: <?php echo esc_attr($bocs_accent); ?>;
        color: <?php echo esc_attr($base); ?> !important;
        text-decoration: none;
        padding: 12px 24px;
        border-radius: 4px;
        font-weight: 600;
        margin: 20px 0;
    }
    
    .bocs-app-notice {
        background-color: <?php echo esc_attr($bocs_secondary); ?>;
        border-left: 4px solid <?php echo esc_attr($bocs_primary); ?>;
        padding: 15px;
        margin: 20px 0;
    }
    
    .bocs-highlight {
        color: <?php echo esc_attr($bocs_primary); ?>;
        font-weight: 600;
    }
    
    .bocs-link {
        color: <?php echo esc_attr($bocs_primary); ?>;
        text-decoration: underline;
    }
    
    .bocs-logo {
        margin-bottom: 20px;
        text-align: center;
    }

    /* Typography */
    h1, h2, h3, h4 {
        color: <?php echo esc_attr($heading_text); ?>;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-weight: 600;
        margin: 0;
        margin-bottom: 20px;
        text-align: left;
        line-height: 1.4;
    }

    h1 {
        font-size: 22px;
        color: <?php echo esc_attr($bocs_primary); ?>;
    }

    h2 {
        font-size: 20px;
        color: <?php echo esc_attr($bocs_primary); ?>;
        display: block;
        font-weight: bold;
        margin: 0 0 18px;
    }

    h3 {
        font-size: 18px;
    }

    p {
        margin: 0 0 16px;
    }

    a {
        color: <?php echo esc_attr($bocs_primary); ?>;
        text-decoration: underline;
    }

    .product-name {
        font-weight: bold;
    }

    .subscription-details {
        background-color: <?php echo esc_attr($bocs_secondary); ?>;
        padding: 15px;
        margin: 15px 0;
        border-radius: 4px;
    }

    .subscription-status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
        color: #fff;
    }

    .status-active {
        background-color: #5cb85c;
    }

    .status-paused {
        background-color: #f0ad4e;
    }

    .status-cancelled {
        background-color: #d9534f;
    }

    /* Tables */
    table.td {
        color: <?php echo esc_attr($text); ?>;
        border: 1px solid #e5e5e5;
        vertical-align: middle;
        width: 100%;
        font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;
    }

    table.td th,
    table.td td {
        color: <?php echo esc_attr($text); ?>;
        border: 1px solid #e5e5e5;
        padding: 12px;
        text-align: left;
        vertical-align: middle;
    }

    table.td th {
        background: #f8f8f8;
        color: <?php echo esc_attr($heading_text); ?>;
        border-top-width: 4px;
    }

    #addresses {
        width: 100%;
        vertical-align: top;
        margin-bottom: 40px;
        padding: 0;
    }

    #addresses td {
        text-align: left;
        font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;
        border: 0;
        padding: 0;
    }

    #addresses address {
        padding: 12px;
        color: #636363;
        border: 1px solid #e5e5e5;
    }

    .divider {
        color: #ccc;
        padding: 0 6px;
    }

    /* Responsive */
    @media screen and (max-width: 600px) {
        #wrapper {
            width: 100% !important;
            padding: 15px !important;
        }

        #template_container,
        #template_body,
        #template_footer {
            width: 100% !important;
            max-width: 100% !important;
        }

        #body_content_inner {
            padding: 24px !important;
        }

        .bocs-email-container {
            padding: 15px !important;
        }

        #header_wrapper {
            padding: 24px !important;
        }

        #template_header h1 {
            font-size: 24px !important;
        }

        table.td th,
        table.td td {
            padding: 8px !important;
        }

        #addresses td {
            width: 100% !important;
            display: block !important;
        }
    }
</style> 