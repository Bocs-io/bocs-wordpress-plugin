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
                        <tr>
                            <td align="center" valign="top">
                                <!-- Footer -->
                                <table border="0" cellpadding="10" cellspacing="0" width="600" id="template_footer">
                                    <tr>
                                        <td valign="top">
                                            <table border="0" cellpadding="10" cellspacing="0" width="100%">
                                                <tr>
                                                    <td colspan="2" valign="middle" id="credit" style="border-top: 1px solid #E5E5E5; color: #8a8a8a; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; line-height: 150%; text-align: center; padding: 20px 0;">
                                                        <?php
                                                        // Custom BOCS footer
                                                        $site_title = get_bloginfo('name');
                                                        echo '<p>' . wp_kses_post(sprintf(__('&copy; %1$s %2$s. Powered by <a href="%3$s" style="color: #3C7B7C; text-decoration: none;">Bocs</a>.', 'bocs-wordpress'), date('Y'), $site_title, 'https://bocs.io')) . '</p>';
                                                        ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                <!-- End Footer -->
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