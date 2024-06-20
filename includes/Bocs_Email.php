<?php
class Bocs_Email
{
    public function add_bocs_processing_renewal_email_class($email_classes)
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-processing-renewal-order.php';

        // Add the custom email class to the list of email classes
        $email_classes['WC_Custom_Email'] = new WC_Bocs_Email_Processing_Renewal_Order();

        return $email_classes;
    }
}
