<?php

$bocs = new Bocs();
$bocs->auto_add_bocs_keys();

$options = get_option( 'bocs_plugin_options' );
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
  <label for="tab3">Dunkles Bock</label>
  
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
  </section>
    <section id="rauchbier" class="tab-panel">
      <h2>Sync Logs</h2>
    </section>
    <section id="dunkles" class="tab-panel">
      <h2>6C. Dunkles Bock</h2>
      <p><strong>Overall Impression:</strong> A dark, strong, malty German lager beer that emphasizes the malty-rich and somewhat toasty qualities of continental malts without being sweet in the finish.</p>
      <p><strong>History:</strong> Originated in the Northern German city of Einbeck, which was a brewing center and popular exporter in the days of the Hanseatic League (14th to 17th century). Recreated in Munich starting in the 17th century. The name “bock” is based on a corruption of the name “Einbeck” in the Bavarian dialect, and was thus only used after the beer came to Munich. “Bock” also means “Ram” in German, and is often used in logos and advertisements.</p>
    </section>
  </div>
</div>