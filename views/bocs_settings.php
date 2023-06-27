<?php
$options = get_option( 'bocs_plugin_options' );
$options['bocs_headers'] = $options['bocs_headers'] ?? array();
?>

<div class="bocs">
	<h2>Bocs Settings</h2>
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
						<label>Authentication</label>
					</th>
					<td class="forminp forminp-text">
						<input type="text" name="bocs_plugin_options[bocs_headers][authorization]" id="bocsAuthorization" value="<?php echo $options['bocs_headers']['authorization'] ?? "" ?>" />
					</td>
				</tr>
			</tbody>
		</table>

		<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo wp_create_nonce("bocs_plugin_options"); ?>">
		<input type="hidden" name="action" value="update">
		<input type="hidden" name="option_page" value="bocs_plugin_options">
		<button type="submit" class="button-primary woocommerce-save-button">Submit</button>
	</form>
</div>