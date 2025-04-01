/**
 * Main plugin class.
 */
class Bocs {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Add actions and filters
		add_action('plugins_loaded', array($this, 'init'));
		
		// Load WooCommerce integration early
		$this->load_woocommerce_integration();
	}
	
	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Check if WooCommerce is active
		if (!class_exists('WooCommerce')) {
			// WooCommerce is required
			add_action('admin_notices', array($this, 'woocommerce_required_notice'));
			return;
		}
		
		// Register email classes
		add_filter('woocommerce_email_classes', array($this, 'register_email_classes'));
		
		// Rest of the initialization...
	}

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

	/**
	 * Load WooCommerce Integration
	 */
	private function load_woocommerce_integration() {
		// Check if WooCommerce is active
		if (class_exists('WooCommerce')) {
			require_once BOCS_PLUGIN_DIR . 'includes/class-bocs-woocommerce.php';
			new Bocs_WooCommerce();
		}
	}
} 