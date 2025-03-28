<?php
/**
 * Payment methods
 *
 * Shows customer payment methods on the account page.
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/myaccount/payment-methods.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.9.0
 */

defined( 'ABSPATH' ) || exit;

// Get saved methods with error handling
$saved_methods = wc_get_customer_saved_methods_list( get_current_user_id() );
$has_methods   = (bool) $saved_methods;
$types         = wc_get_account_payment_methods_types();

// Log the saved methods for debugging
if (class_exists('Bocs_Log_Handler')) {
    $logger = new Bocs_Log_Handler();
    $logger->insert_log('debug', '[Payment Methods Template] Saved methods data', [
        'saved_methods' => $saved_methods,
        'has_methods' => $has_methods,
        'types' => $types,
        'saved_methods_type' => gettype($saved_methods)
    ]);
}

do_action( 'woocommerce_before_account_payment_methods', $has_methods ); ?>

<?php if ( $has_methods ) : ?>

	<table class="woocommerce-MyAccount-paymentMethods shop_table shop_table_responsive account-payment-methods-table">
		<thead>
			<tr>
				<?php foreach ( wc_get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
					<th class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr( $column_id ); ?> payment-method-<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<?php 
		if (is_array($saved_methods)) {
			foreach ( $saved_methods as $type => $methods ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				if (!is_array($methods)) {
					if (class_exists('Bocs_Log_Handler')) {
						$logger->insert_log('warning', '[Payment Methods Template] Methods for type is not an array', [
							'type' => $type,
							'methods' => $methods,
							'methods_type' => gettype($methods)
						]);
					}
					continue;
				}

				foreach ( $methods as $method ) : 
					// Log the method data for debugging
					if (class_exists('Bocs_Log_Handler')) {
						$logger->insert_log('debug', '[Payment Methods Template] Processing method', [
							'type' => $type,
							'method' => $method,
							'method_type' => gettype($method)
						]);
					}

					// Convert method to array if it's a string
					if (is_string($method)) {
						if (class_exists('Bocs_Log_Handler')) {
							$logger->insert_log('warning', '[Payment Methods Template] Converting string method to array', [
								'type' => $type,
								'method' => $method
							]);
						}
						$method = array(
							'method' => array(
								'last4' => '',
								'brand' => $method
							),
							'expires' => '',
							'is_default' => false,
							'actions' => array()
						);
					}

					// Ensure method is an array
					if (!is_array($method)) {
						if (class_exists('Bocs_Log_Handler')) {
							$logger->insert_log('warning', '[Payment Methods Template] Invalid method format', [
								'type' => $type,
								'method' => $method,
								'method_type' => gettype($method)
							]);
						}
						continue;
					}

					// Ensure method has required structure
					$method = wp_parse_args($method, array(
						'method' => array(
							'last4' => '',
							'brand' => '',
						),
						'expires' => '',
						'is_default' => false,
						'actions' => array(),
					));

					// Ensure method.method is an array
					if (!isset($method['method']) || !is_array($method['method'])) {
						if (class_exists('Bocs_Log_Handler')) {
							$logger->insert_log('warning', '[Payment Methods Template] Method.method is not an array', [
								'type' => $type,
								'method' => $method
							]);
						}
						$method['method'] = array(
							'last4' => '',
							'brand' => '',
						);
					}

					// Ensure method.method has required keys
					$method['method'] = wp_parse_args($method['method'], array(
						'last4' => '',
						'brand' => '',
					));

					// Log the final method structure
					if (class_exists('Bocs_Log_Handler')) {
						$logger->insert_log('debug', '[Payment Methods Template] Final method structure', [
							'type' => $type,
							'method' => $method
						]);
					}

					// Ensure all required keys exist before rendering
					$method_data = array(
						'method' => array(
							'last4' => isset($method['method']['last4']) ? $method['method']['last4'] : '',
							'brand' => isset($method['method']['brand']) ? $method['method']['brand'] : '',
						),
						'expires' => isset($method['expires']) ? $method['expires'] : '',
						'is_default' => isset($method['is_default']) ? $method['is_default'] : false,
						'actions' => array(),
					);

					// Safely handle actions array
					if (isset($method['actions']) && is_array($method['actions'])) {
						foreach ($method['actions'] as $key => $action) {
							if (is_array($action) && isset($action['url']) && isset($action['name'])) {
								$method_data['actions'][$key] = array(
									'url' => $action['url'],
									'name' => $action['name']
								);
							} else {
								if (class_exists('Bocs_Log_Handler')) {
									$logger->insert_log('warning', '[Payment Methods Template] Invalid action format', [
										'type' => $type,
										'key' => $key,
										'action' => $action
									]);
								}
							}
						}
					}
					?>
					<tr class="payment-method<?php echo ! empty( $method_data['is_default'] ) ? ' default-payment-method' : ''; ?>">
						<?php foreach ( wc_get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
							<td class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr( $column_id ); ?> payment-method-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
								<?php
								if ( has_action( 'woocommerce_account_payment_methods_column_' . $column_id ) ) {
									do_action( 'woocommerce_account_payment_methods_column_' . $column_id, $method_data );
								} elseif ( 'method' === $column_id ) {
									if ( ! empty( $method_data['method']['last4'] ) ) {
										/* translators: 1: credit card type 2: last 4 digits */
										echo sprintf( esc_html__( '%1$s ending in %2$s', 'woocommerce' ), esc_html( wc_get_credit_card_type_label( $method_data['method']['brand'] ) ), esc_html( $method_data['method']['last4'] ) );
									} else {
										echo esc_html( wc_get_credit_card_type_label( $method_data['method']['brand'] ) );
									}
								} elseif ( 'expires' === $column_id ) {
									echo esc_html( $method_data['expires'] );
								} elseif ( 'actions' === $column_id ) {
									foreach ( $method_data['actions'] as $key => $action ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
										if (isset($action['url']) && isset($action['name'])) {
											echo '<a href="' . esc_url( $action['url'] ) . '" class="button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>&nbsp;';
										}
									endforeach;
								}
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; 
		} else {
			if (class_exists('Bocs_Log_Handler')) {
				$logger->insert_log('warning', '[Payment Methods Template] Saved methods is not an array', [
					'saved_methods' => $saved_methods,
					'saved_methods_type' => gettype($saved_methods)
				]);
			}
		}
		?>
	</table>

<?php else : ?>

	<?php wc_print_notice( esc_html__( 'No saved methods found.', 'woocommerce' ), 'notice' ); ?>

<?php endif; ?>

<?php do_action( 'woocommerce_after_account_payment_methods', $has_methods ); ?>

<?php if ( WC()->payment_gateways->get_available_payment_gateways() ) : ?>
	<a class="button" href="<?php echo esc_url( wc_get_endpoint_url( 'add-payment-method' ) ); ?>"><?php esc_html_e( 'Add payment method', 'woocommerce' ); ?></a>
<?php endif; ?> 