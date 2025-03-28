	/**
	 * Register our custom email classes with WooCommerce
	 *
	 * @param array $email_classes Array of email class objects
	 * @return array Modified array of email class objects
	 */
	public function register_email_classes( $email_classes ) {
		// Include our custom email classes
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-subscription-confirmation.php';
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-new-customer-subscription.php';
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-subscription-switched.php';
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-failed-payment-retry.php';
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-renewal-order-confirmation.php';
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-upcoming-renewal-reminder.php';
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-subscription-cancelled.php';
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-subscription-paused.php';
		include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/class-bocs-email-subscription-reactivated.php';

		// Add our email classes to the list of email classes that WooCommerce loads
		$email_classes['Bocs_Email_Subscription_Confirmation'] = new Bocs_Email_Subscription_Confirmation();
		$email_classes['Bocs_Email_New_Customer_Subscription'] = new Bocs_Email_New_Customer_Subscription();
		$email_classes['Bocs_Email_Subscription_Switched'] = new Bocs_Email_Subscription_Switched();
		$email_classes['Bocs_Email_Failed_Payment_Retry'] = new Bocs_Email_Failed_Payment_Retry();
		$email_classes['Bocs_Email_Renewal_Order_Confirmation'] = new Bocs_Email_Renewal_Order_Confirmation();
		$email_classes['Bocs_Email_Upcoming_Renewal_Reminder'] = new Bocs_Email_Upcoming_Renewal_Reminder();
		$email_classes['Bocs_Email_Subscription_Cancelled'] = new Bocs_Email_Subscription_Cancelled();
		$email_classes['Bocs_Email_Subscription_Paused'] = new Bocs_Email_Subscription_Paused();
		$email_classes['Bocs_Email_Subscription_Reactivated'] = new Bocs_Email_Subscription_Reactivated();

		return $email_classes;
	} 