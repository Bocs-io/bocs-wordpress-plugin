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
$bocs_primary     = '#3C7B7C';
$bocs_secondary   = '#f8f9fa';
$bocs_accent      = '#3C7B7C';
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
        background-color: <?php echo esc_attr($body); ?>;
        margin: 0;
        padding: 70px 0;
        -webkit-text-size-adjust: none;
        text-size-adjust: none;
        width: 100%;
    }

    #template_container {
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1) !important;
        background-color: <?php echo esc_attr($body); ?>;
        border: 1px solid #e5e5e5;
        border-radius: 3px !important;
        max-width: 600px;
        margin: 0 auto;
    }

    #template_header {
        background-color: <?php echo esc_attr($bocs_primary); ?>;
        border-radius: 3px 3px 0 0 !important;
        color: <?php echo esc_attr($base); ?>;
        border-bottom: 0;
        font-weight: bold;
        line-height: 100%;
        vertical-align: middle;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }

    #template_header h1 {
        color: <?php echo esc_attr($base); ?>;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }

    #template_body {
        background-color: <?php echo esc_attr($body); ?>;
        border-radius: 0 0 3px 3px !important;
    }

    #template_footer {
        border-top: 1px solid #e5e5e5;
        background-color: <?php echo esc_attr($bg); ?>;
        border-radius: 0 0 3px 3px !important;
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
        background-color: <?php echo esc_attr($bocs_primary); ?>;
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
    
    .bocs-header {
        color: <?php echo esc_attr($bocs_primary); ?>;
        font-weight: 600;
        margin-bottom: 15px;
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
    }

    h2 {
        font-size: 20px;
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

    /* Tables */
    table.td {
        color: <?php echo esc_attr($text); ?>;
        border: 1px solid #e5e5e5;
        vertical-align: middle;
        width: 100%;
    }

    table.td th,
    table.td td {
        color: <?php echo esc_attr($text); ?>;
        border: 1px solid #e5e5e5;
        padding: 12px;
        text-align: left;
    }

    table.td th {
        background: #f8f8f8;
        color: <?php echo esc_attr($heading_text); ?>;
        border-top-width: 4px;
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

        .bocs-email-container {
            padding: 15px !important;
        }
    }
</style> 