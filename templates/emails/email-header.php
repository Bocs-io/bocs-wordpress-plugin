<?php
/**
 * Email Header
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-header.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo('charset'); ?>" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo get_bloginfo('name', 'display'); ?></title>
    <style type="text/css">
        @media screen and (max-width: 600px){
            #header_wrapper{padding: 27px 36px !important; font-size: 24px;}
            #body_content table > tbody > tr > td{padding: 10px !important;}
            #body_content_inner{font-size: 10px !important;}
        }
    </style>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="background-color: #f7f7f7; padding: 0; text-align: center;">
    <table width="100%" id="outer_wrapper" style="background-color: #f7f7f7;" bgcolor="#f7f7f7">
        <tr>
            <td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
            <td width="600">
                <div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" style="margin: 0 auto; padding: 70px 0; width: 100%; max-width: 600px; -webkit-text-size-adjust: none;">
                    <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
                        <tr>
                            <td align="center" valign="top">
                                <div id="template_header_image">
                                    <?php
                                    $header_image = get_option('woocommerce_email_header_image');
                                    if ($header_image) {
                                        echo '<p style="margin:0;"><img src="' . esc_url($header_image) . '" alt="' . esc_attr(get_bloginfo('name')) . '" /></p>';
                                    }
                                    ?>
                                </div>
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" style="background-color: #fff; border: 1px solid #dedede; box-shadow: 0 1px 4px rgba(0,0,0,.1); border-radius: 3px;" bgcolor="#fff">
                                    <tr>
                                        <td align="center" valign="top">
                                            <!-- Header -->
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" style="background-color: #3C7B7C !important; color: #ffffff; border-bottom: 0; font-weight: bold; line-height: 100%; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border-radius: 3px 3px 0 0;" bgcolor="#3C7B7C">
                                                <tr>
                                                    <td id="header_wrapper" style="padding: 36px 48px; display: block;">
                                                        <h1 style="font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 30px; font-weight: 300; line-height: 150%; margin: 0; text-align: left; color: #ffffff; background-color: inherit; text-shadow: none !important;"><?php echo esc_html($email_heading); ?></h1>
                                                    </td>
                                                </tr>
                                            </table>
                                            <!-- End Header -->
                                        </td>
                                    </tr>
                                    <tr>
                                        <td align="center" valign="top">
                                            <!-- Body -->
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body">
                                                <tr>
                                                    <td valign="top" id="body_content" style="background-color: #fff;" bgcolor="#fff">
                                                        <!-- Content -->
                                                        <table border="0" cellpadding="20" cellspacing="0" width="100%">
                                                            <tr>
                                                                <td valign="top" style="padding: 48px 48px 32px;">
                                                                    <div id="body_content_inner" style="color: #636363; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left;"> 