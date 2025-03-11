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
                                <table border="0" cellpadding="10" cellspacing="0" width="100%" id="template_footer">
                                    <tr>
                                        <td valign="top" id="footer_wrapper">
                                            <table border="0" cellpadding="10" cellspacing="0" width="100%">
                                                <tr>
                                                    <td colspan="2" valign="middle" id="credit">
                                                        <p><?php echo wp_kses_post(wpautop(wptexturize(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text', ''))))); ?></p>
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