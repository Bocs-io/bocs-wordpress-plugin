<?php
/**
 * Email Footer
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-footer.php.
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

                                                                        <!-- Footer section -->
                                                                        <div style="padding: 0 12px; max-width: 100%; margin-top: 30px;">
                                                                            <h2 style="display: block; font-size: 18px; font-weight: bold; line-height: 130%; margin: 16px 0 8px; text-align: left;">
                                                                                <?php esc_html_e('Visit your customer portal:', 'bocs-wordpress'); ?>
                                                                            </h2>
                                                                            
                                                                            <ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 20px;">
                                                                                <li style="margin-bottom: 10px;">
                                                                                    <strong><?php esc_html_e('Update your box:', 'bocs-wordpress'); ?></strong>
                                                                                    <?php esc_html_e('Change products, quantities or swap your box.', 'bocs-wordpress'); ?>
                                                                                </li>
                                                                                <li style="margin-bottom: 10px;">
                                                                                    <strong><?php esc_html_e('Change your schedule:', 'bocs-wordpress'); ?></strong>
                                                                                    <?php esc_html_e('Change your dates or frequency of delivery.', 'bocs-wordpress'); ?>
                                                                                </li>
                                                                                <li style="margin-bottom: 10px;">
                                                                                    <strong><?php esc_html_e('Edit your details:', 'bocs-wordpress'); ?></strong>
                                                                                    <?php esc_html_e('Update your payment methods, or personal details.', 'bocs-wordpress'); ?>
                                                                                </li>
                                                                            </ul>

                                                                            <!-- Buttons -->
                                                                            <div style="margin-bottom: 30px;">
                                                                                <table cellspacing="0" cellpadding="0" border="0">
                                                                                    <tr>
                                                                                        <td style="padding-right: 15px;">
                                                                                            <a href="<?php echo esc_url(wc_get_account_endpoint_url('my-subscriptions')); ?>" style="background-color: #f0f0f0; border: 1px solid #dddddd; border-radius: 4px; color: <?php echo esc_attr($base_color); ?>; display: inline-block; font-weight: bold; line-height: 100%; padding: 10px 15px; text-align: center; text-decoration: none;">
                                                                                                <?php esc_html_e('Edit Your Box', 'bocs-wordpress'); ?>
                                                                                            </a>
                                                                                        </td>
                                                                                        <td>
                                                                                            <a href="<?php echo esc_url(wc_get_account_endpoint_url('dashboard')); ?>" style="background-color: #f0f0f0; border: 1px solid #dddddd; border-radius: 4px; color: <?php echo esc_attr($base_color); ?>; display: inline-block; font-weight: bold; line-height: 100%; padding: 10px 15px; text-align: center; text-decoration: none;">
                                                                                                <?php esc_html_e('My Account', 'bocs-wordpress'); ?>
                                                                                            </a>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </div>
                                                                        </div>


                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                        <!-- End Content -->
                                                    </td>
                                                </tr>
                                            </table>
                                            <!-- End Body -->
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
        </tr>
    </table>
</body>
</html> 