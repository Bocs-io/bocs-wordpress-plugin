<?php

$bocs = new Bocs();
$bocs->auto_add_bocs_keys();

$options = get_option('bocs_plugin_options');
$options['bocs_headers'] = $options['bocs_headers'] ?? array(); ?>

<div class="tabset">
	<!-- Tab 1 -->
	<input type="radio" name="tabset" id="tab1" aria-controls="marzen" checked>
	<label for="tab1">Credentials / Keys</label>
	<!-- Tab 2 -->
	<input type="radio" name="tabset" id="tab2" aria-controls="rauchbier">
	<label for="tab2">Sync Logs</label>
	<!-- Tab 3 -->
	<input type="radio" name="tabset" id="tab3" aria-controls="dunkles">
	<label for="tab3">Sync Modules</label>

	<div class="tab-panels">
		<section id="marzen" class="tab-panel">
			<h2>Credentials / Keys</h2>
			<form method="post">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row" class="titledesc">
								<label>Store ID</label>
							</th>
							<td class="forminp forminp-text">
								<input type="text" name="bocs_plugin_options[bocs_headers][store]" id="bocsStore" value="<?php echo $options['bocs_headers']['store'] ?? "" ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row" class="titledesc">
								<label>Organization ID</label>
							</th>
							<td class="forminp forminp-text">
								<input type="text" name="bocs_plugin_options[bocs_headers][organization]" id="bocsOrganization" value="<?php echo $options['bocs_headers']['organization'] ?? "" ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row" class="titledesc">
								<label>Authorization</label>
							</th>
							<td class="forminp forminp-text">
								<input type="text" name="bocs_plugin_options[	][authorization]" id="bocsAuthorization" value="<?php echo $options['bocs_headers']['authorization'] ?? "" ?>" />
							</td>
						</tr>
					</tbody>
				</table>

				<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo wp_create_nonce("bocs_plugin_options"); ?>">
				<input type="hidden" name="action" value="update">
				<input type="hidden" name="option_page" value="bocs_plugin_options">
				<button type="submit" class="button-primary woocommerce-save-button">Submit</button>
			</form>
		</section>
		<section id="rauchbier" class="tab-panel">
			<h2>Sync Logs</h2>
			<?php
			$table = new Error_Logs_List_Table();
			$table->prepare_items();
			$table->display();
			?>
		</section>
		<section id="dunkles" class="tab-panel">
			<h2>Sync Modules</h2>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>All Modules</label>
						</th>
						<td class="forminp">
							<select id="bocs_all_module" name="bocs_all_module">
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="custom">Custom Module Sync</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Products</label>
						</th>
						<td class="forminp">
							<select id="bocs_product_module" name="bocs_product_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Bocs</label>
						</th>
						<td class="forminp">
							<select id="bocs_bocs_module" name="bocs_product_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Contacts</label>
						</th>
						<td class="forminp">
							<select id="bocs_contact_module" name="bocs_contact_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Orders</label>
						</th>
						<td class="forminp">
							<select id="bocs_order_module" name="bocs_order_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Invoices</label>
						</th>
						<td class="forminp">
							<select id="bocs_invoice_module" name="bocs_invoice_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Shipping</label>
						</th>
						<td class="forminp">
							<select id="bocs_shipping_module" name="bocs_shipping_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Tax</label>
						</th>
						<td class="forminp">
							<select id="bocs_tax_module" name="bocs_tax_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Categories & Tags</label>
						</th>
						<td class="forminp">
							<select id="bocs_category_module" name="bocs_category_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Subscriptions</label>
						</th>
						<td class="forminp">
							<select id="bocs_subscription_module" name="bocs_subscription_module" disabled>
								<option value="both">Sync Both Ways</option>
								<option value="b2w">Sync Bocs to Wordpress</option>
								<option value="w2b">Sync Wordpress to Bocs</option>
								<option value="none">Do Not Sync</option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
			<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo wp_create_nonce("bocs_sync_options"); ?>">
			<input type="hidden" name="action" value="update">
			<input type="hidden" name="option_page" value="bocs_sync_options">
			<button type="button" class="button-primary woocommerce-save-button" id="syncNow">Sync Now</button>
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
				url: "<?php echo BOCS_API_URL . "bocs"; ?>",
				// cache: false,
				type: "GET",
				beforeSend: function(xhr) {
					xhr.setRequestHeader("Accept", "application/json");
					xhr.setRequestHeader("Organization", "<?php echo $options['bocs_headers']['organization']; ?>");
					xhr.setRequestHeader("Store", "<?php echo $options['bocs_headers']['store'] ?>");
					xhr.setRequestHeader("Authorization", "<?php echo $options['bocs_headers']['authorization'] ?>");
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