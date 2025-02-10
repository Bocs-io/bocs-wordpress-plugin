<?php
use Automattic\Jetpack\Constants;

defined('ABSPATH') || exit();

global $wpdb;

function bocs_output_plugins_info($plugins, $untested_plugins)
{
    $wc_version = Constants::get_constant('WC_VERSION');

    if ('major' === Constants::get_constant('WC_SSR_PLUGIN_UPDATE_RELEASE_VERSION_TYPE')) {
        $wc_version = $wc_version[0] . '.0';
    }

    foreach ($plugins as $plugin) {
        if (! empty($plugin['name'])) {
            $plugin_name = esc_html($plugin['name']);
            if (! empty($plugin['url'])) {
                $plugin_name = sprintf(
                    '<a href="%1$s" aria-label="%2$s" target="_blank">%3$s</a>',
                    esc_url($plugin['url']),
                    esc_attr__('Visit plugin homepage', 'bocs-wordpress'),
                    $plugin_name
                );
            }

            $version_string = $plugin['version'];
            $network_string = '';
            
            if (strstr($plugin['url'], 'woothemes.com') || strstr($plugin['url'], 'woocommerce.com')) {
                if (! empty($plugin['version_latest']) && version_compare($plugin['version_latest'], $plugin['version'], '>')) {
                    $version_string = sprintf(
                        /* translators: 1: current version 2: latest version */
                        __('%1$s (update to version %2$s is available)', 'bocs-wordpress'),
                        $plugin['version'],
                        $plugin['version_latest']
                    );
                }

                if (false !== $plugin['network_activated']) {
                    $network_string = sprintf(
                        ' &ndash; <strong style="color: black;">%s</strong>',
                        esc_html__('Network enabled', 'bocs-wordpress')
                    );
                }
            }

            $untested_string = '';
            if (array_key_exists($plugin['plugin'], $untested_plugins)) {
                $untested_string = sprintf(
                    ' &ndash; <strong style="color: #a00;">%s</strong>',
                    sprintf(
                        /* translators: %s: WooCommerce version */
                        esc_html__('Installed version not tested with active version of WooCommerce %s', 'bocs-wordpress'),
                        $wc_version
                    )
                );
            }
            ?>
            <tr>
                <td><?php echo wp_kses_post($plugin_name); ?></td>
                <td class="help">&nbsp;</td>
                <td>
                    <?php
                    printf(
                        /* translators: %s: plugin author */
                        esc_html__('by %s', 'bocs-wordpress'),
                        esc_html($plugin['author_name'])
                    );
                    echo ' &ndash; ' . esc_html($version_string) . $untested_string . $network_string;
                    ?>
                </td>
            </tr>
            <?php
        }
    }
}

$report = wc()->api->get_endpoint_data('/wc/v3/system_status');
$environment = $report['environment'];
$database = $report['database'];

$post_type_counts = isset($report['post_type_counts']) ? $report['post_type_counts'] : array();
$active_plugins = $report['active_plugins'];

$inactive_plugins = $report['inactive_plugins'];
$dropins_mu_plugins = $report['dropins_mu_plugins'];
$theme = $report['theme'];
$security = $report['security'];
$settings = $report['settings'];
$wp_pages = $report['pages'];
$plugin_updates = new WC_Plugin_Updates();
$untested_plugins = $plugin_updates->get_untested_plugins(WC()->version, Constants::get_constant('WC_SSR_PLUGIN_UPDATE_RELEASE_VERSION_TYPE'));

$options = get_option('bocs_plugin_options');
$options['bocs_headers'] = $options['bocs_headers'] ?? array();
$options['developer_mode'] = $options['developer_mode'] ?? 'off';
$stripe_settings = $options['stripe'] ?? array();
$is_test_mode = ($stripe_settings['test_mode'] ?? 'no') === 'yes';
$publishable_key = $stripe_settings['publishable_key'] ?? '';
$secret_key = $stripe_settings['secret_key'] ?? '';
?>

<div class="tabset">
	<!-- Tab 1 -->
	<input type="radio" name="tabset" id="tab1" aria-controls="credentials" checked>
	<label for="tab1"><?php esc_html_e('Credentials / Keys', 'bocs-wordpress'); ?></label>
	<!-- Tab 2 -->
	<input type="radio" name="tabset" id="tab2" aria-controls="sync-logs">
	<label for="tab2"><?php esc_html_e('Sync Logs', 'bocs-wordpress'); ?></label>
	<!-- Tab 3 -->
	<input type="radio" name="tabset" id="tab3" aria-controls="sync-modules">
	<label for="tab3"><?php esc_html_e('Sync Modules', 'bocs-wordpress'); ?></label>
	<!-- Tab 4 -->
	<input type="radio" name="tabset" id="tab4" aria-controls="site-status">
	<label for="tab4"><?php esc_html_e('Site Status', 'bocs-wordpress'); ?></label>
	<!-- Tab 5 -->
	<input type="radio" name="tabset" id="tab5" aria-controls="developer-mode">
	<label for="tab5"><?php esc_html_e('Test', 'bocs-wordpress'); ?></label>

	<div class="tab-panels">
		<section id="credentials" class="tab-panel">
			<h2><?php esc_html_e('Credentials / Keys', 'bocs-wordpress'); ?></h2>
			<form method="post">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row" class="titledesc">
								<label><?php esc_html_e('Store ID', 'bocs-wordpress'); ?></label>
							</th>
							<td class="forminp forminp-text">
								<input class="regular-text" type="text" name="bocs_plugin_options[bocs_headers][store]" id="bocsStore" value="<?php echo esc_attr($options['bocs_headers']['store'] ?? ''); ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row" class="titledesc">
								<label><?php esc_html_e('Organization ID', 'bocs-wordpress'); ?></label>
							</th>
							<td class="forminp forminp-text">
								<input class="regular-text" type="text" name="bocs_plugin_options[bocs_headers][organization]" id="bocsOrganization" value="<?php echo esc_attr($options['bocs_headers']['organization'] ?? ''); ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row" class="titledesc">
								<label><?php esc_html_e('BOCS API Key', 'bocs-wordpress'); ?></label>
							</th>
							<td class="forminp forminp-text">
								<input class="regular-text" type="text" name="bocs_plugin_options[bocs_headers][authorization]" id="bocsAuthorization" value="<?php echo esc_attr($options['bocs_headers']['authorization'] ?? ''); ?>" />
							</td>
						</tr>
					</tbody>
				</table>
				<?php wp_nonce_field('bocs_plugin_options'); ?>
				<input type="hidden" name="action" value="update">
				<input type="hidden" name="option_page" value="bocs_plugin_options">
				
				<p class="submit">
					<button type="submit" class="button-primary woocommerce-save-button">
						<?php esc_html_e('Submit', 'bocs-wordpress'); ?>
					</button>
				</p>
			</form>
		</section>
		<section id="sync-logs" class="tab-panel">
			<h2><?php esc_html_e('Sync Logs', 'bocs-wordpress'); ?></h2>
			<?php
			$table = new Error_Logs_List_Table();
			$table->prepare_items();
			$table->display();
			?>
		</section>
		<section id="sync-modules" class="tab-panel">
			<h2><?php esc_html_e('Sync Modules', 'bocs-wordpress'); ?></h2>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label><?php esc_html_e('All Modules', 'bocs-wordpress'); ?></label>
						</th>
						<td class="forminp">
							<select id="bocs_all_module" name="bocs_all_module">
								<option value="both"><?php esc_html_e('Sync Both Ways', 'bocs-wordpress'); ?></option>
								<option value="b2w"><?php esc_html_e('Sync Bocs to WordPress', 'bocs-wordpress'); ?></option>
								<option value="w2b"><?php esc_html_e('Sync WordPress to Bocs', 'bocs-wordpress'); ?></option>
								<option value="custom"><?php esc_html_e('Custom Module Sync', 'bocs-wordpress'); ?></option>
								<option value="none"><?php esc_html_e('Do Not Sync', 'bocs-wordpress'); ?></option>
							</select>
						</td>
					</tr>
					<?php
					$modules = array(
						'product' => esc_html__('Products', 'bocs-wordpress'),
						'bocs' => esc_html__('Bocs', 'bocs-wordpress'),
						'contact' => esc_html__('Contacts', 'bocs-wordpress'),
						'order' => esc_html__('Orders', 'bocs-wordpress'),
						'invoice' => esc_html__('Invoices', 'bocs-wordpress'),
						'shipping' => esc_html__('Shipping', 'bocs-wordpress'),
						'tax' => esc_html__('Tax', 'bocs-wordpress'),
						'category' => esc_html__('Categories & Tags', 'bocs-wordpress'),
						'subscription' => esc_html__('Subscriptions', 'bocs-wordpress')
					);

					foreach ($modules as $module_id => $module_name) : ?>
						<tr valign="top">
							<th scope="row" class="titledesc">
								<label><?php echo $module_name; ?></label>
							</th>
							<td class="forminp">
								<select id="bocs_<?php echo esc_attr($module_id); ?>_module" 
										name="bocs_<?php echo esc_attr($module_id); ?>_module" 
										disabled>
									<option value="both"><?php esc_html_e('Sync Both Ways', 'bocs-wordpress'); ?></option>
									<option value="b2w"><?php esc_html_e('Sync Bocs to WordPress', 'bocs-wordpress'); ?></option>
									<option value="w2b"><?php esc_html_e('Sync WordPress to Bocs', 'bocs-wordpress'); ?></option>
									<option value="none"><?php esc_html_e('Do Not Sync', 'bocs-wordpress'); ?></option>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<br />
			<?php wp_nonce_field('bocs_sync_options', '_wpnonce'); ?>
			<input type="hidden" name="action" value="update">
			<input type="hidden" name="option_page" value="bocs_sync_options">
			<button type="button" class="button-primary woocommerce-save-button" id="syncNow">
				<?php esc_html_e('Sync Now', 'bocs-wordpress'); ?>
			</button>
		</section>
		<section id="site-status" class="tab-panel">
			<h2><?php esc_html_e('System Status', 'bocs-wordpress'); ?></h2>
			<table class="wc_status_table widefat" cellspacing="0" id="status">
				<thead>
					<tr>
						<th colspan="3" data-export-label="WordPress Environment">
							<h2><?php esc_html_e('WordPress environment', 'bocs-wordpress'); ?></h2>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td data-export-label="WordPress address (URL)">
							<?php esc_html_e('WordPress address (URL)', 'bocs-wordpress'); ?>:
						</td>
						<td class="help">
							<?php echo wc_help_tip(esc_html__('The root URL of your site.', 'bocs-wordpress')); ?>
						</td>
						<td><?php echo esc_html($environment['site_url']); ?></td>
					</tr>
					<tr>
						<td data-export-label="Site address (URL)">
							<?php esc_html_e('Site address (URL)', 'bocs-wordpress'); ?>:
						</td>
						<td class="help">
							<?php echo wc_help_tip(esc_html__('The homepage URL of your site.', 'bocs-wordpress')); ?>
						</td>
						<td><?php echo esc_html($environment['home_url']); ?></td>
					</tr>
					<tr>
						<td data-export-label="WC Version">
							<?php esc_html_e('WooCommerce version', 'bocs-wordpress'); ?>:
						</td>
						<td class="help">
							<?php echo wc_help_tip(esc_html__('The version of WooCommerce installed on your site.', 'bocs-wordpress')); ?>
						</td>
						<td><?php echo esc_html($environment['version']); ?></td>
					</tr>
					<tr>
						<td data-export-label="WP Version">
							<?php esc_html_e('WordPress version', 'bocs-wordpress'); ?>:
						</td>
						<td class="help">
							<?php echo wc_help_tip(esc_html__('The version of WordPress installed on your site.', 'bocs-wordpress')); ?>
						</td>
						<td>
							<?php
							$latest_version = get_transient('woocommerce_system_status_wp_version_check');

							if (false === $latest_version) {
								$version_check = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
								$api_response = json_decode(wp_remote_retrieve_body($version_check), true);

								if ($api_response && isset($api_response['offers'], $api_response['offers'][0], $api_response['offers'][0]['version'])) {
									$latest_version = $api_response['offers'][0]['version'];
								} else {
									$latest_version = $environment['wp_version'];
								}
								set_transient('woocommerce_system_status_wp_version_check', $latest_version, DAY_IN_SECONDS);
							}

							if (version_compare($environment['wp_version'], $latest_version, '<')) {
								printf(
									'<mark class="error"><span class="dashicons dashicons-warning"></span> %s</mark>',
									sprintf(
										/* translators: 1: Current version, 2: New version */
										esc_html__('%1$s - There is a newer version of WordPress available (%2$s)', 'bocs-wordpress'),
										esc_html($environment['wp_version']),
										esc_html($latest_version)
									)
								);
							} else {
								echo '<mark class="yes">' . esc_html($environment['wp_version']) . '</mark>';
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<br />
			<table class="wc_status_table widefat" cellspacing="0">
				<thead>
					<tr>
						<th colspan="3" data-export-label="Server Environment">
							<h2><?php esc_html_e('Server environment', 'bocs-wordpress'); ?></h2>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td data-export-label="Server Info">
							<?php esc_html_e('Server info', 'bocs-wordpress'); ?>:
						</td>
						<td class="help">
							<?php echo wc_help_tip(esc_html__('Information about the web server that is currently hosting your site.', 'bocs-wordpress')); ?>
						</td>
						<td><?php echo esc_html($environment['server_info']); ?></td>
					</tr>
					<tr>
						<td data-export-label="PHP Version">
							<?php esc_html_e('PHP version', 'bocs-wordpress'); ?>:
						</td>
						<td class="help">
							<?php echo wc_help_tip(esc_html__('The version of PHP installed on your hosting server.', 'bocs-wordpress')); ?>
						</td>
						<td>
							<?php echo '<mark class="yes">' . esc_html($environment['php_version']) . '</mark>'; ?>
						</td>
					</tr>
					<?php if (function_exists('ini_get')) : ?>
						<tr>
							<td data-export-label="PHP Post Max Size">
								<?php esc_html_e('PHP post max size', 'bocs-wordpress'); ?>:
							</td>
							<td class="help">
								<?php echo wc_help_tip(esc_html__('The largest filesize that can be contained in one post.', 'bocs-wordpress')); ?>
							</td>
							<td><?php echo esc_html(size_format($environment['php_post_max_size'])); ?></td>
						</tr>
						<tr>
							<td data-export-label="PHP Time Limit">
								<?php esc_html_e('PHP time limit', 'bocs-wordpress'); ?>:
							</td>
							<td class="help">
								<?php echo wc_help_tip(esc_html__('The amount of time (in seconds) that your site will spend on a single operation before timing out (to avoid server lockups)', 'bocs-wordpress')); ?>
							</td>
							<td><?php echo esc_html($environment['php_max_execution_time']); ?></td>
						</tr>
						<tr>
							<td data-export-label="PHP Max Input Vars">
								<?php esc_html_e('PHP max input vars', 'bocs-wordpress'); ?>:
							</td>
							<td class="help">
								<?php echo wc_help_tip(esc_html__('The maximum number of variables your server can use for a single function to avoid overloads.', 'bocs-wordpress')); ?>
							</td>
							<td><?php echo esc_html($environment['php_max_input_vars']); ?></td>
						</tr>
						<tr>
							<td data-export-label="cURL Version">
								<?php esc_html_e('cURL version', 'bocs-wordpress'); ?>:
							</td>
							<td class="help">
								<?php echo wc_help_tip(esc_html__('The version of cURL installed on your server.', 'bocs-wordpress')); ?>
							</td>
							<td><?php echo esc_html($environment['curl_version']); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<br />
			<table class="wc_status_table widefat" cellspacing="0">
				<thead>
					<tr>
						<th colspan="3" data-export-label="Server Environment"><h2><?php

            esc_html_e('Server environment', 'bocs-wordpress');
            ?></h2></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td data-export-label="Server Info"><?php

            esc_html_e('Server info', 'bocs-wordpress');
            ?>:</td>
						<td class="help"><?php

            echo wc_help_tip(esc_html__('Information about the web server that is currently hosting your site.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
						<td><?php

            echo esc_html($environment['server_info']);
            ?></td>
					</tr>
					<tr>
						<td data-export-label="PHP Version"><?php

            esc_html_e('PHP version', 'bocs-wordpress');
            ?>:</td>
						<td class="help"><?php

            echo wc_help_tip(esc_html__('The version of PHP installed on your hosting server.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
						<td>
							<?php
                echo '<mark class="yes">' . esc_html($environment['php_version']) . '</mark>';
                ?>
						</td>
					</tr>
					<?php

            if (function_exists('ini_get')) :
                ?>
						<tr>
							<td data-export-label="PHP Post Max Size"><?php

                esc_html_e('PHP post max size', 'bocs-wordpress');
                ?>:</td>
							<td class="help"><?php

                echo wc_help_tip(esc_html__('The largest filesize that can be contained in one post.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
							<td><?php

                echo esc_html(size_format($environment['php_post_max_size']));
                ?></td>
						</tr>
						<tr>
							<td data-export-label="PHP Time Limit"><?php

                esc_html_e('PHP time limit', 'bocs-wordpress');
                ?>:</td>
							<td class="help"><?php

                echo wc_help_tip(esc_html__('The amount of time (in seconds) that your site will spend on a single operation before timing out (to avoid server lockups)', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
							<td><?php

                echo esc_html($environment['php_max_execution_time']);
                ?></td>
						</tr>
						<tr>
							<td data-export-label="PHP Max Input Vars"><?php

                esc_html_e('PHP max input vars', 'bocs-wordpress');
                ?>:</td>
							<td class="help"><?php

                echo wc_help_tip(esc_html__('The maximum number of variables your server can use for a single function to avoid overloads.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
							<td><?php

                echo esc_html($environment['php_max_input_vars']);
                ?></td>
						</tr>
						<tr>
							<td data-export-label="cURL Version"><?php

                esc_html_e('cURL version', 'bocs-wordpress');
                ?>:</td>
							<td class="help"><?php

                echo wc_help_tip(esc_html__('The version of cURL installed on your server.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
							<td><?php

                echo esc_html($environment['curl_version']);
                ?></td>
						</tr>
						<tr>
							<td data-export-label="SUHOSIN Installed"><?php

                esc_html_e('SUHOSIN installed', 'bocs-wordpress');
                ?>:</td>
							<td class="help"><?php

                echo wc_help_tip(esc_html__('Suhosin is an advanced protection system for PHP installations. It was designed to protect your servers on the one hand against a number of well known problems in PHP applications and on the other hand against potential unknown vulnerabilities within these applications or the PHP core itself. If enabled on your server, Suhosin may need to be configured to increase its data submission limits.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
							<td><?php

                echo $environment['suhosin_installed'] ? '<span class="dashicons dashicons-yes"></span>' : '&ndash;';
                ?></td>
						</tr>
					<?php endif;

            ?>

						<?php

            if ($environment['mysql_version']) :
                ?>
						<tr>
							<td data-export-label="MySQL Version"><?php

                esc_html_e('MySQL version', 'bocs-wordpress');
                ?>:</td>
							<td class="help"><?php

                echo wc_help_tip(esc_html__('The version of MySQL installed on your hosting server.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
							<td>
								<?php
                if (version_compare($environment['mysql_version'], '5.6', '<') && ! strstr($environment['mysql_version_string'], 'MariaDB')) {
                    /* Translators: %1$s: MySQL version, %2$s: Recommended MySQL version. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf(esc_html__('%1$s - We recommend a minimum MySQL version of 5.6. See: %2$s', 'bocs-wordpress'), esc_html($environment['mysql_version_string']), '<a href="https://wordpress.org/about/requirements/" target="_blank">' . esc_html__('WordPress requirements', 'bocs-wordpress') . '</a>') . '</mark>';
                } else {
                    echo '<mark class="yes">' . esc_html($environment['mysql_version_string']) . '</mark>';
                }
                ?>
							</td>
						</tr>
					<?php endif;

            ?>
						<tr>
							<td data-export-label="Max Upload Size"><?php

            esc_html_e('Max upload size', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The largest filesize that can be uploaded to your WordPress installation.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo esc_html(size_format($environment['max_upload_size']));
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Default Timezone is UTC"><?php

            esc_html_e('Default timezone is UTC', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The default timezone for your server.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ('UTC' !== $environment['default_timezone']) {
                    /* Translators: %s: default timezone.. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf(esc_html__('Default timezone is %s - it should be UTC', 'bocs-wordpress'), esc_html($environment['default_timezone'])) . '</mark>';
                } else {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                }
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="fsockopen/cURL"><?php

            esc_html_e('fsockopen/cURL', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Payment gateways can use cURL to communicate with remote servers to authorize payments, other plugins may also use it when communicating with remote services.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ($environment['fsockopen_or_curl_enabled']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                } else {
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__('Your server does not have fsockopen or cURL enabled - PayPal IPN and other scripts which communicate with other servers will not work. Contact your hosting provider.', 'bocs-wordpress') . '</mark>';
                }
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="SoapClient"><?php

            esc_html_e('SoapClient', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Some webservices like shipping use SOAP to get information from remote servers, for example, live shipping quotes from FedEx require SOAP to be installed.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ($environment['soapclient_enabled']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                } else {
                    /* Translators: %s classname and link. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf(esc_html__('Your server does not have the %s class enabled - some gateway plugins which use SOAP may not work as expected.', 'bocs-wordpress'), '<a href="https://php.net/manual/en/class.soapclient.php">SoapClient</a>') . '</mark>';
                }
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="DOMDocument"><?php

            esc_html_e('DOMDocument', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('HTML/Multipart emails use DOMDocument to generate inline CSS in templates.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ($environment['domdocument_enabled']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                } else {
                    /* Translators: %s: classname and link. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf(esc_html__('Your server does not have the %s class enabled - HTML/Multipart emails, and also some extensions, will not work without DOMDocument.', 'bocs-wordpress'), '<a href="https://php.net/manual/en/class.domdocument.php">DOMDocument</a>') . '</mark>';
                }
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="GZip"><?php

            esc_html_e('GZip', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('GZip (gzopen) is used to open the GEOIP database from MaxMind.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ($environment['gzip_enabled']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                } else {
                    /* Translators: %s: classname and link. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf(esc_html__('Your server does not support the %s function - this is required to use the GeoIP database from MaxMind.', 'bocs-wordpress'), '<a href="https://php.net/manual/en/zlib.installation.php">gzopen</a>') . '</mark>';
                }
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Multibyte String"><?php

            esc_html_e('Multibyte string', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Multibyte String (mbstring) is used to convert character encoding, like for emails or converting characters to lowercase.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ($environment['mbstring_enabled']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                } else {
                    /* Translators: %s: classname and link. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf(esc_html__('Your server does not support the %s functions - this is required for better character encoding. Some fallbacks will be used instead for it.', 'bocs-wordpress'), '<a href="https://php.net/manual/en/mbstring.installation.php">mbstring</a>') . '</mark>';
                }
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Remote Post"><?php

            esc_html_e('Remote post', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('PayPal uses this method of communicating when sending back transaction information.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ($environment['remote_post_successful']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                } else {
                    /* Translators: %s: function name. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf(esc_html__('%s failed. Contact your hosting provider.', 'bocs-wordpress'), 'wp_remote_post()') . ' ' . esc_html($environment['remote_post_response']) . '</mark>';
                }
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Remote Get"><?php

            esc_html_e('Remote get', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('WooCommerce plugins may use this method of communication when checking for plugin updates.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ($environment['remote_get_successful']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                } else {
                    /* Translators: %s: function name. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf(esc_html__('%s failed. Contact your hosting provider.', 'bocs-wordpress'), 'wp_remote_get()') . ' ' . esc_html($environment['remote_get_response']) . '</mark>';
                }
                ?>
							</td>
						</tr>
						<?php
            $rows = apply_filters('woocommerce_system_status_environment_rows', array());
            foreach ($rows as $row) {
                if (! empty($row['success'])) {
                    $css_class = 'yes';
                    $icon = '<span class="dashicons dashicons-yes"></span>';
                } else {
                    $css_class = 'error';
                    $icon = '<span class="dashicons dashicons-no-alt"></span>';
                }
                ?>
						<tr>
							<td data-export-label="<?php

                echo esc_attr($row['name']);
                ?>"><?php

                echo esc_html($row['name']);
                ?>:</td>
							<td class="help"><?php

                echo esc_html(isset($row['help']) ? $row['help'] : '');
                ?></td>
							<td>
								<mark class="<?php

                echo esc_attr($css_class);
                ?>">
										<?php

                echo wp_kses_post($icon);
                ?> <?php

                echo wp_kses_data(! empty($row['note']) ? $row['note'] : '');
                ?>
								</mark>
							</td>
						</tr>
						<?php
            }
            ?>
					</tbody>
				</table>
				<br />
				<table id="status-database" class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="Database">
							<h2><?php esc_html_e('Database', 'bocs-wordpress'); ?></h2>
						</th>
					</tr>
					</thead>
					<tbody>
						<tr>
							<td data-export-label="MySQL Version">
								<?php esc_html_e('MySQL version', 'bocs-wordpress'); ?>:
							</td>
							<td class="help">
								<?php echo wc_help_tip(esc_html__('The version of MySQL installed on your hosting server.', 'bocs-wordpress')); ?>
							</td>
							<td>
								<?php
								if (version_compare($environment['mysql_version'], '5.6', '<') && !strstr($environment['mysql_version_string'], 'MariaDB')) {
									printf(
										'<mark class="error"><span class="dashicons dashicons-warning"></span> %s</mark>',
										sprintf(
											/* translators: 1: MySQL version 2: Recommended MySQL version */
											esc_html__('%1$s - We recommend a minimum MySQL version of 5.6. See: %2$s', 'bocs-wordpress'),
											esc_html($environment['mysql_version_string']),
											'<a href="https://wordpress.org/about/requirements/" target="_blank">' . 
											esc_html__('WordPress requirements', 'bocs-wordpress') . 
											'</a>'
										)
									);
								} else {
									echo '<mark class="yes">' . esc_html($environment['mysql_version_string']) . '</mark>';
								}
								?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Max Upload Size">
								<?php esc_html_e('Max upload size', 'bocs-wordpress'); ?>:
							</td>
							<td class="help">
								<?php echo wc_help_tip(esc_html__('The largest filesize that can be uploaded to your WordPress installation.', 'bocs-wordpress')); ?>
							</td>
							<td><?php echo esc_html(size_format($environment['max_upload_size'])); ?></td>
						</tr>
						<tr>
							<td data-export-label="Default Timezone">
								<?php esc_html_e('Default timezone', 'bocs-wordpress'); ?>:
							</td>
							<td class="help">
								<?php echo wc_help_tip(esc_html__('The default timezone for your server.', 'bocs-wordpress')); ?>
							</td>
							<td>
								<?php
								if ('UTC' !== $environment['default_timezone']) {
									printf(
										'<mark class="error"><span class="dashicons dashicons-warning"></span> %s</mark>',
										sprintf(
											/* translators: %s: default timezone */
											esc_html__('Default timezone is %s - it should be UTC', 'bocs-wordpress'),
											esc_html($environment['default_timezone'])
										)
									);
								} else {
									echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
								}
								?>
							</td>
						</tr>
					</tbody>
				</table>
				<br />
				<?php

            if ($post_type_counts) :
                ?>
					<table class="wc_status_table widefat" cellspacing="0">
						<thead>
						<tr>
							<th colspan="3" data-export-label="Post Type Counts"><h2><?php

                esc_html_e('Post Type Counts', 'bocs-wordpress');
                ?></h2></th>
						</tr>
						</thead>
						<tbody>
							<?php
                foreach ($post_type_counts as $ptype) {
                    ?>
								<tr>
									<td><?php

                    echo esc_html($ptype['type']);
                    ?></td>
									<td class="help">&nbsp;</td>
									<td><?php

                    echo absint($ptype['count']);
                    ?></td>
								</tr>
								<?php
                }
                ?>
						</tbody>
					</table>
					<br />
				<?php endif;

                ?>
				<table class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="Security"><h2><?php

            esc_html_e('Security', 'bocs-wordpress');
            ?></h2></th>
					</tr>
					</thead>
					<tbody>
						<tr>
							<td data-export-label="Secure connection (HTTPS)"><?php

            esc_html_e('Secure connection (HTTPS)', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Is the connection to your store secure?', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php

                if ($security['secure_connection']) :
                    ?>
									<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>
								<?php

                else :
                    ?>
									<mark class="error"><span class="dashicons dashicons-warning"></span>
									<?php
                    /* Translators: %s: docs link. */
                    echo wp_kses_post(sprintf(__('Your store is not using HTTPS. <a href="%s" target="_blank">Learn more about HTTPS and SSL Certificates</a>.', 'bocs-wordpress'), 'https://docs.woocommerce.com/document/ssl-and-https/'));
                    ?>
									</mark>
								<?php

                endif;
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Hide errors from visitors"><?php

            esc_html_e('Hide errors from visitors', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Error messages can contain sensitive information about your store environment. These should be hidden from untrusted visitors.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php

                if ($security['hide_errors']) :
                    ?>
									<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>
								<?php

                else :
                    ?>
									<mark class="error"><span class="dashicons dashicons-warning"></span><?php

                    esc_html_e('Error messages should not be shown to visitors.', 'bocs-wordpress');
                    ?></mark>
								<?php

                endif;
                ?>
							</td>
						</tr>
					</tbody>
				</table>
				<br />
				<table class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="Active Plugins (<?php

            echo count($active_plugins);
            ?>)"><h2><?php

            esc_html_e('Active plugins', 'bocs-wordpress');
            ?> (<?php

            echo count($active_plugins);
            ?>)</h2></th>
					</tr>
					</thead>
					<tbody>
						<?php

            bocs_output_plugins_info($active_plugins, $untested_plugins);
            ?>
					</tbody>
				</table>
				<br />
				<table class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="Inactive Plugins (<?php

            echo count($inactive_plugins);
            ?>)"><h2><?php

            esc_html_e('Inactive plugins', 'bocs-wordpress');
            ?> (<?php

            echo count($inactive_plugins);
            ?>)</h2></th>
					</tr>
					</thead>
					<tbody>
						<?php

            bocs_output_plugins_info($inactive_plugins, $untested_plugins);
            ?>
					</tbody>
				</table>
				<br />
				<?php
            if (0 < count($dropins_mu_plugins['dropins'])) :
                ?>
					<table class="wc_status_table widefat" cellspacing="0">
						<thead>
						<tr>
							<th colspan="3" data-export-label="Dropin Plugins (<?php

                echo count($dropins_mu_plugins['dropins']);
                ?>)"><h2><?php

                esc_html_e('Dropin Plugins', 'bocs-wordpress');
                ?> (<?php

                echo count($dropins_mu_plugins['dropins']);
                ?>)</h2></th>
						</tr>
						</thead>
						<tbody>
							<?php
                foreach ($dropins_mu_plugins['dropins'] as $dropin) {
                    ?>
								<tr>
									<td><?php

                    echo wp_kses_post($dropin['plugin']);
                    ?></td>
									<td class="help">&nbsp;</td>
									<td><?php

                    echo wp_kses_post($dropin['name']);
                    ?>
								</tr>
								<?php
                }
                ?>
						</tbody>
					</table>
					<br />
				<?php
            endif;

            if (0 < count($dropins_mu_plugins['mu_plugins'])) :
                ?>
					<table class="wc_status_table widefat" cellspacing="0">
						<thead>
						<tr>
							<th colspan="3" data-export-label="Must Use Plugins (<?php

                echo count($dropins_mu_plugins['mu_plugins']);
                ?>)"><h2><?php

                esc_html_e('Must Use Plugins', 'bocs-wordpress');
                ?> (<?php

                echo count($dropins_mu_plugins['mu_plugins']);
                ?>)</h2></th>
						</tr>
						</thead>
						<tbody>
							<?php
                foreach ($dropins_mu_plugins['mu_plugins'] as $mu_plugin) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                    $plugin_name = esc_html($mu_plugin['name']);
                    if (! empty($mu_plugin['url'])) {
                        $plugin_name = '<a href="' . esc_url($mu_plugin['url']) . '" aria-label="' . esc_attr__('Visit plugin homepage', 'bocs-wordpress') . '" target="_blank">' . $plugin_name . '</a>';
                    }
                    ?>
								<tr>
									<td><?php

                    echo wp_kses_post($plugin_name);
                    ?></td>
									<td class="help">&nbsp;</td>
									<td>
									<?php
                    /* translators: %s: plugin author */
                    printf(esc_html__('by %s', 'bocs-wordpress'), esc_html($mu_plugin['author_name']));
                    echo ' &ndash; ' . esc_html($mu_plugin['version']);
                    ?>
								</tr>
								<?php
                }
                ?>
						</tbody>
					</table>
					<br />
				<?php endif;

                ?>
				<table class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="Settings"><h2><?php

            esc_html_e('Settings', 'bocs-wordpress');
            ?></h2></th>
					</tr>
					</thead>
					<tbody>
						<tr>
							<td data-export-label="API Enabled"><?php

            esc_html_e('API enabled', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Does your site have REST API enabled?', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo $settings['api_enabled'] ? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>' : '<mark class="no">&ndash;</mark>';
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Force SSL"><?php

            esc_html_e('Force SSL', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Does your site force a SSL Certificate for transactions?', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo $settings['force_ssl'] ? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>' : '<mark class="no">&ndash;</mark>';
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Currency"><?php

            esc_html_e('Currency', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('What currency prices are listed at in the catalog and which currency gateways will take payments in.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo esc_html($settings['currency']);
            ?> (<?php

            echo esc_html($settings['currency_symbol']);
            ?>)</td>
						</tr>
						<tr>
							<td data-export-label="Currency Position"><?php

            esc_html_e('Currency position', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The position of the currency symbol.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo esc_html($settings['currency_position']);
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Thousand Separator"><?php

            esc_html_e('Thousand separator', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The thousand separator of displayed prices.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo esc_html($settings['thousand_separator']);
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Decimal Separator"><?php

            esc_html_e('Decimal separator', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The decimal separator of displayed prices.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo esc_html($settings['decimal_separator']);
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Number of Decimals"><?php

            esc_html_e('Number of decimals', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The number of decimal points shown in displayed prices.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo esc_html($settings['number_of_decimals']);
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Taxonomies: Product Types"><?php

            esc_html_e('Taxonomies: Product types', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('A list of taxonomy terms that can be used in regard to order/product statuses.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                $display_terms = array();
                foreach ($settings['taxonomies'] as $slug => $name) {
                    $display_terms[] = strtolower($name) . ' (' . $slug . ')';
                }
                echo implode(', ', array_map('esc_html', $display_terms));
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Taxonomies: Product Visibility"><?php

            esc_html_e('Taxonomies: Product visibility', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('A list of taxonomy terms used for product visibility.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                $display_terms = array();
                foreach ($settings['product_visibility_terms'] as $slug => $name) {
                    $display_terms[] = strtolower($name) . ' (' . $slug . ')';
                }
                echo implode(', ', array_map('esc_html', $display_terms));
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Connected to WooCommerce.com"><?php

            esc_html_e('Connected to WooCommerce.com', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Is your site connected to WooCommerce.com?', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo 'yes' === $settings['woocommerce_com_connected'] ? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>' : '<mark class="no">&ndash;</mark>';
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Enforce Approved Product Download Directories"><?php

            esc_html_e('Enforce Approved Product Download Directories', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Is your site enforcing the use of Approved Product Download Directories?', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo $settings['enforce_approved_download_dirs'] ? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>' : '<mark class="no">&ndash;</mark>';
            ?></td>
						</tr>

						<tr>
							<td data-export-label="HPOS feature screen enabled"><?php

            esc_html_e('HPOS feature screen enabled:', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Is HPOS feature screen enabled?', 'bocs-wordpress'));
            ?></td>
							<td><?php

            echo isset($settings['HPOS_feature_screen_enabled']) ? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>' : '<mark class="no">&ndash;</mark>';
            ?></td>
						</tr>
						<tr>
							<td data-export-label="HPOS feature enabled"><?php

            esc_html_e('HPOS enabled:', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Is HPOS enabled?', 'bocs-wordpress'));
            ?></td>
							<td><?php

            echo $settings['HPOS_enabled'] ? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>' : '<mark class="no">&ndash;</mark>';
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Order datastore"><?php

            esc_html_e('Order datastore:', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Datastore currently in use for orders.', 'bocs-wordpress'));
            ?></td>
							<td><?php

            echo esc_html($settings['order_datastore']);
            ?></td>
						</tr>
						<tr>
							<td data-export-label="HPOS data sync enabled"><?php

            esc_html_e('HPOS data sync enabled:', 'bocs-wordpress');
            ?></td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Is data sync enabled for HPOS?', 'bocs-wordpress'));
            ?></td>
							<td><?php

            echo $settings['HPOS_sync_enabled'] ? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>' : '<mark class="no">&ndash;</mark>';
            ?></td>
						</tr>

					</tbody>
				</table>
				<br />
				<table class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="WC Pages"><h2><?php

            esc_html_e('WooCommerce pages', 'bocs-wordpress');
            ?></h2></th>
					</tr>
					</thead>
					<tbody>
						<?php
            $alt = 1;
            foreach ($wp_pages as $_page) {
                $found_error = false;

                if ($_page['page_id']) {
                    /* Translators: %s: page name. */
                    $page_name = '<a href="' . get_edit_post_link($_page['page_id']) . '" aria-label="' . sprintf(esc_html__('Edit %s page', 'bocs-wordpress'), esc_html($_page['page_name'])) . '">' . esc_html($_page['page_name']) . '</a>';
                } else {
                    $page_name = esc_html($_page['page_name']);
                }

                echo '<tr><td data-export-label="' . esc_attr($page_name) . '">' . wp_kses_post($page_name) . ':</td>';
                /* Translators: %s: page name. */
                echo '<td class="help">' . wc_help_tip(sprintf(esc_html__('The URL of your %s page (along with the Page ID).', 'bocs-wordpress'), $page_name)) . '</td><td>';

                // Page ID check.
                if (! $_page['page_set']) {
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__('Page not set', 'bocs-wordpress') . '</mark>';
                    $found_error = true;
                } elseif (! $_page['page_exists']) {
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__('Page ID is set, but the page does not exist', 'bocs-wordpress') . '</mark>';
                    $found_error = true;
                } elseif (! $_page['page_visible']) {
                    /* Translators: %s: docs link. */
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . wp_kses_post(sprintf(__('Page visibility should be <a href="%s" target="_blank">public</a>', 'bocs-wordpress'), 'https://wordpress.org/support/article/content-visibility/')) . '</mark>';
                    $found_error = true;
                } else {
                    // Shortcode and block check.
                    if ($_page['shortcode_required'] || $_page['block_required']) {
                        if (! $_page['shortcode_present'] && ! $_page['block_present']) {
                            /* Translators: %1$s: shortcode text, %2$s: block slug. */
                            echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . ($_page['block_required'] ? sprintf(esc_html__('Page does not contain the %1$s shortcode or the %2$s block.', 'bocs-wordpress'), esc_html($_page['shortcode']), esc_html($_page['block'])) : sprintf(esc_html__('Page does not contain the %s shortcode.', 'bocs-wordpress'), esc_html($_page['shortcode']))) . '</mark>'; /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                            $found_error = true;
                        }
                    }
                }

                if (! $found_error) {
                    echo '<mark class="yes">#' . absint($_page['page_id']) . ' - ' . esc_html(str_replace(home_url(), '', get_permalink($_page['page_id']))) . '</mark>';
                }

                echo '</td></tr>';
            }
            ?>
					</tbody>
				</table>
				<br />
				<table class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="Theme"><h2><?php

            esc_html_e('Theme', 'bocs-wordpress');
            ?></h2></th>
					</tr>
					</thead>
					<tbody>
						<tr>
							<td data-export-label="Name"><?php

            esc_html_e('Name', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The name of the current active theme.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo esc_html($theme['name']);
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Version"><?php

            esc_html_e('Version', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The installed version of the current active theme.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if (version_compare($theme['version'], $theme['version_latest'], '<')) {
                    /* translators: 1: current version. 2: latest version */
                    echo esc_html(sprintf(__('%1$s (update to version %2$s is available)', 'bocs-wordpress'), $theme['version'], $theme['version_latest']));
                } else {
                    echo esc_html($theme['version']);
                }
                ?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Author URL"><?php

            esc_html_e('Author URL', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('The theme developers URL.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td><?php

            echo esc_html($theme['author_url']);
            ?></td>
						</tr>
						<tr>
							<td data-export-label="Child Theme"><?php

            esc_html_e('Child theme', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Displays whether or not the current theme is a child theme.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if ($theme['is_child_theme']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                } else {
                    /* Translators: %s docs link. */
                    echo '<span class="dashicons dashicons-no-alt"></span> &ndash; ' . wp_kses_post(sprintf(__('If you are modifying WooCommerce on a parent theme that you did not build personally we recommend using a child theme. See: <a href="%s" target="_blank">How to create a child theme</a>', 'bocs-wordpress'), 'https://developer.wordpress.org/themes/advanced-topics/child-themes/'));
                }
                ?>
							</td>
						</tr>
						<?php

            if ($theme['is_child_theme']) :
                ?>
							<tr>
								<td data-export-label="Parent Theme Name"><?php

                esc_html_e('Parent theme name', 'bocs-wordpress');
                ?>:</td>
								<td class="help"><?php

                echo wc_help_tip(esc_html__('The name of the parent theme.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
								<td><?php

                echo esc_html($theme['parent_name']);
                ?></td>
							</tr>
							<tr>
								<td data-export-label="Parent Theme Version"><?php

                esc_html_e('Parent theme version', 'bocs-wordpress');
                ?>:</td>
								<td class="help"><?php

                echo wc_help_tip(esc_html__('The installed version of the parent theme.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
								<td>
									<?php
                echo esc_html($theme['parent_version']);
                if (version_compare($theme['parent_version'], $theme['parent_version_latest'], '<')) {
                    /* translators: %s: parent theme latest version */
                    echo ' &ndash; <strong style="color:red;">' . sprintf(esc_html__('%s is available', 'bocs-wordpress'), esc_html($theme['parent_version_latest'])) . '</strong>';
                }
                ?>
								</td>
							</tr>
							<tr>
								<td data-export-label="Parent Theme Author URL"><?php

                esc_html_e('Parent theme author URL', 'bocs-wordpress');
                ?>:</td>
								<td class="help"><?php

                echo wc_help_tip(esc_html__('The parent theme developers URL.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
                ?></td>
								<td><?php

                echo esc_html($theme['parent_author_url']);
                ?></td>
							</tr>
						<?php endif ?>










						<tr>
							<td data-export-label="WooCommerce Support"><?php

            esc_html_e('WooCommerce support', 'bocs-wordpress');
            ?>:</td>
							<td class="help"><?php

            echo wc_help_tip(esc_html__('Displays whether or not the current active theme declares WooCommerce support.', 'bocs-wordpress')); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */
            ?></td>
							<td>
								<?php
                if (! $theme['has_woocommerce_support']) {
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__('Not declared', 'bocs-wordpress') . '</mark>';
                } else {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
                }
                ?>
							</td>
						</tr>
					</tbody>
				</table>
				<br />
				<table class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="Templates"><h2><?php

            esc_html_e('Templates', 'bocs-wordpress');
            ?><?php

            echo wc_help_tip(esc_html__('This section shows any files that are overriding the default WooCommerce template pages.', 'bocs-wordpress'));
            ?></h2></th>
					</tr>
					</thead>
					<tbody>
						<?php

            if ($theme['has_woocommerce_file']) :
                ?>
							<tr>
								<td data-export-label="Archive Template"><?php

                esc_html_e('Archive template', 'bocs-wordpress');
                ?>:</td>
								<td class="help">&nbsp;</td>
								<td><?php

                esc_html_e('Your theme has a woocommerce.php file, you will not be able to override the woocommerce/archive-product.php custom template since woocommerce.php has priority over archive-product.php. This is intended to prevent display issues.', 'bocs-wordpress');
                ?></td>
							</tr>
						<?php endif ?>










						<?php

            if (! empty($theme['overrides'])) :
                ?>
							<tr>
								<td data-export-label="Overrides"><?php

                esc_html_e('Overrides', 'bocs-wordpress');
                ?></td>
								<td class="help">&nbsp;</td>
								<td>
									<?php
                $total_overrides = count($theme['overrides']);
                for ($i = 0; $i < $total_overrides; $i ++) {
                    $override = $theme['overrides'][$i];
                    if ($override['core_version'] && (empty($override['version']) || version_compare($override['version'], $override['core_version'], '<'))) {
                        $current_version = $override['version'] ? $override['version'] : '-';
                        printf(
            								/* Translators: %1$s: Template name, %2$s: Template version, %3$s: Core version. */
            								esc_html__('%1$s version %2$s is out of date. The core version is %3$s', 'bocs-wordpress'), '<code>' . esc_html($override['file']) . '</code>', '<strong style="color:red">' . esc_html($current_version) . '</strong>', esc_html($override['core_version']));
                    } else {
                        echo esc_html($override['file']);
                    }
                    if ((count($theme['overrides']) - 1) !== $i) {
                        echo ', ';
                    }
                    echo '<br />';
                }
                ?>
								</td>
							</tr>
						<?php

            else :
                ?>
							<tr>
								<td data-export-label="Overrides"><?php

                esc_html_e('Overrides', 'bocs-wordpress');
                ?>:</td>
								<td class="help">&nbsp;</td>
								<td>&ndash;</td>
							</tr>
						<?php

            endif;
            ?>

						<?php

            if (true === $theme['has_outdated_templates']) :
                ?>
							<tr>
								<td data-export-label="Outdated Templates"><?php

                esc_html_e('Outdated templates', 'bocs-wordpress');
                ?>:</td>
								<td class="help">&nbsp;</td>
								<td>
									<mark class="error">
										<span class="dashicons dashicons-warning"></span>
									</mark>
									<a href="https://docs.woocommerce.com/document/fix-outdated-templates-woocommerce/" target="_blank">
										<?php

                esc_html_e('Learn how to update', 'bocs-wordpress');
                ?>
									</a>
								</td>
							</tr>
						<?php endif;

            ?>
					</tbody>
				</table>
				<br />
				<?php

                do_action('woocommerce_system_status_report');
                ?>

				<table class="wc_status_table widefat" cellspacing="0">
					<thead>
					<tr>
						<th colspan="3" data-export-label="Status report information"><h2><?php

            esc_html_e('Status report information', 'bocs-wordpress');
            ?><?php

            echo wc_help_tip(esc_html__('This section shows information about this status report.', 'bocs-wordpress'));
            ?></h2></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td data-export-label="Generated at"><?php

            esc_html_e('Generated at', 'bocs-wordpress');
            ?>:</td>
						<td class="help">&nbsp;</td>
						<td><?php

            echo esc_html(current_time('Y-m-d H:i:s P'));
            ?></td>

					</tr>
					</tbody>
				</table>
			</section>
			<section id="developer-mode" class="tab-panel">
				<h2>Test</h2>
				<div class="notice notice-warning">
					<h3>For Bocs developers only</h3>
					<h4>WARNING: Ensure to re-publish any pages containing the widgets when testing and also when deactivating.</h4>
					<div>
						<form method="post">
							<label>
								<input type="checkbox" id="developer_mode" name="developer_mode" <?php echo $options['developer_mode'] === 'on' ? 'checked' : ''; ?>>
								Developer Mode
							</label>
							<br />
							<br />
							<input type="hidden" name="action" value="update">
							<input type="hidden" name="option_page" value="developer_mode">
							<button type="submit" class="button-primary woocommerce-save-button">Submit</button>
						</form>
						<br />
					</div>
				</div>
			</section>
		</div>
	</div>
	<script>
		jQuery("select#bocs_all_module").change(function() {

			jQuery("select#bocs_product_module").attr("disabled", "disabled");
			jQuery("select#bocs_bocs_module").attr("disabled", "disabled");
			jQuery("select#bocs_contact_module").attr("disabled", "disabled");
			jQuery("select#bocs_order_module").attr("disabled", "disabled");
			jQuery("select#bocs_invoice_module").attr("disabled", "disabled");
			jQuery("select#bocs_shipping_module").attr("disabled", "disabled");
			jQuery("select#bocs_tax_module").attr("disabled", "disabled");
			jQuery("select#bocs_category_module").attr("disabled", "disabled");
			jQuery("select#bocs_subscription_module").attr("disabled", "disabled");

			if (jQuery(this).val() == "custom") {

				jQuery("select#bocs_product_module").removeAttr("disabled");
				jQuery("select#bocs_bocs_module").removeAttr("disabled");
				jQuery("select#bocs_contact_module").removeAttr("disabled");
				jQuery("select#bocs_order_module").removeAttr("disabled");
				jQuery("select#bocs_invoice_module").removeAttr("disabled");
				jQuery("select#bocs_shipping_module").removeAttr("disabled");
				jQuery("select#bocs_tax_module").removeAttr("disabled");
				jQuery("select#bocs_category_module").removeAttr("disabled");
				jQuery("select#bocs_subscription_module").removeAttr("disabled");
			} else {
				jQuery("select#bocs_product_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
				jQuery("select#bocs_bocs_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
				jQuery("select#bocs_contact_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
				jQuery("select#bocs_order_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
				jQuery("select#bocs_invoice_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
				jQuery("select#bocs_shipping_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
				jQuery("select#bocs_tax_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
				jQuery("select#bocs_category_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
				jQuery("select#bocs_subscription_module option[value=" + jQuery(this).val() + "]").attr('selected', 'selected');
			}
		});

		jQuery("#syncNow").click(function() {

			// sync the Bocs
			const bocsValue = jQuery("select#bocs_bocs_module").val();

			if (bocsValue == "both" || bocsValue == "b2w") {
				// we will sync from bocs to wordpress

				// get the list of available bocs
				jQuery.ajax({
					url: "<?php

    echo BOCS_API_URL . "bocs";
    ?>",
					// cache: false,
					type: "GET",
					beforeSend: function(xhr) {
						xhr.setRequestHeader("Accept", "application/json");
						xhr.setRequestHeader("Organization", "<?php

    echo $options['bocs_headers']['organization'];
    ?>");
						xhr.setRequestHeader("Store", "<?php

    echo $options['bocs_headers']['store']?>");
						xhr.setRequestHeader("Authorization", "<?php

    echo $options['bocs_headers']['authorization']?>");
					},
					success: function(response) {
						// we will sync the bocs to wordpress
						if (response.data) {

						}
					},
					error: function(xhr) {
						console.error(xhr);
					}
				});
			}

		});
	</script>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Toggle test/live sections
		$('input[name="bocs_plugin_options[stripe][test_mode]"]').change(function() {
			var isTestMode = $(this).is(':checked');
			
			if (isTestMode) {
				$('.test-keys').removeClass('hidden');
				$('.live-keys').addClass('hidden');
			} else {
				$('.test-keys').addClass('hidden');
				$('.live-keys').removeClass('hidden');
			}
		});

		// Add show/hide password functionality
		$('.test-keys input[type="password"], .live-keys input[type="password"]').each(function() {
			var $input = $(this);
			var $td = $input.closest('td');
			
			// Add show/hide toggle button
			var $toggle = $('<button type="button" class="button button-secondary show-hide-key">Show</button>');
			$td.find('.description').append('<br>').append($toggle);

			$toggle.click(function(e) {
				e.preventDefault();
				if ($input.attr('type') === 'password') {
					$input.attr('type', 'text');
					$toggle.text('Hide');
				} else {
					$input.attr('type', 'password');
					$toggle.text('Show');
				}
			});
		});
	});
	</script>