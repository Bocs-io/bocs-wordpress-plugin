<?php
$options = get_option( 'bocs_plugin_options' );
$options['sync_contacts_to_bocs'] = isset($options['sync_contacts_to_bocs']) ? $options['sync_contacts_to_bocs'] : 0;
$options['sync_contacts_from_bocs'] = isset($options['sync_contacts_from_bocs']) ? $options['sync_contacts_from_bocs'] : 0;
$options['sync_daily_contacts_to_bocs'] = isset($options['sync_daily_contacts_to_bocs']) ? $options['sync_daily_contacts_to_bocs'] : 0;
$options['sync_daily_contacts_from_bocs'] = isset($options['sync_daily_contacts_from_bocs']) ? $options['sync_daily_contacts_from_bocs'] : 0;
?>
<div class="bocs">
	<h2><?php echo ( get_admin_page_title() ? esc_html( get_admin_page_title()) : 'Bocs Contacts Settings'); ?></h2>
	<form method="post">
		<div class="form-group row">
			<label class="col-sm-3 col-form-label">Sync Contacts to Bocs</label>
			<div class="col-sm-9">
				<input class="form-check-input" type="radio" name="bocs_plugin_options[sync_contacts_to_bocs]" id="bocs_plugin_setting_sync_contacts_to_bocs" value="1" <?php echo $options['sync_contacts_to_bocs'] == 1 ? "checked" : "" ?>> Yes
				<input class="form-check-input" type="radio" name="bocs_plugin_options[sync_contacts_to_bocs]" id="bocs_plugin_setting_sync_contacts_to_bocs_no" value="0" <?php echo $options['sync_contacts_to_bocs'] == 0 ? "checked" : "" ?>> No <br />
				<p>
					<button id="syncContactsToBocs" type="button" class="btn btn-primary btn-sm" data-wp-nonce="<?php echo wp_create_nonce("bocs_plugin_options"); ?>">Sync Now</button>
				</p>
				<p id="syncContactsToBocs-response"></p>
			</div>
		</div>

		<div class="form-group row">
			<label class="col-sm-3 col-form-label">Sync Contacts from Bocs</label>
			<div class="col-sm-9">
				<input class="form-check-input" type="radio" name="bocs_plugin_options[sync_contacts_from_bocs]" id="bocs_plugin_setting_sync_contacts_from_bocs" value="1" <?php echo $options['sync_contacts_from_bocs'] == 1 ? "checked" : "" ?>> Yes
				<input class="form-check-input" type="radio" name="bocs_plugin_options[sync_contacts_from_bocs]" id="bocs_plugin_setting_sync_contacts_from_bocs_no" value="0" <?php echo $options['sync_contacts_from_bocs'] == 0 ? "checked" : "" ?>> No
			</div>
		</div>

		<div class="form-group row">
			<label class="col-sm-3 col-form-label">Daily Autosync Contacts to Bocs</label>
			<div class="col-sm-9">
				<input class="form-check-input" type="radio" name="bocs_plugin_options[sync_daily_contacts_to_bocs]" id="bocs_plugin_setting_sync_daily_contacts_to_bocs" value="1" <?php echo $options['sync_daily_contacts_to_bocs'] == 1 ? "checked" : "" ?>> Yes
				<input class="form-check-input" type="radio" name="bocs_plugin_options[sync_daily_contacts_to_bocs]" id="bocs_plugin_setting_sync_daily_contacts_to_bocs_no" value="0" <?php echo $options['sync_daily_contacts_to_bocs'] == 0 ? "checked" : "" ?>> No
			</div>
		</div>

		<div class="form-group row">
			<label class="col-sm-3 col-form-label">Daily Autosync Contacts from Bocs</label>
			<div class="col-sm-9">
				<input class="form-check-input" type="radio" name="bocs_plugin_options[sync_daily_contacts_from_bocs]" id="bocs_plugin_setting_sync_daily_contacts_from_bocs" value="1" <?php echo $options['sync_daily_contacts_from_bocs'] == 1 ? "checked" : "" ?>> Yes
				<input class="form-check-input" type="radio" name="bocs_plugin_options[sync_daily_contacts_from_bocs]" id="bocs_plugin_setting_sync_daily_contacts_from_bocs_no" value="0" <?php echo $options['sync_daily_contacts_from_bocs'] == 0 ? "checked" : "" ?>> No
			</div>
		</div>
		<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo wp_create_nonce("bocs_plugin_options"); ?>">
		<input type="hidden" name="action" value="update">
		<input type="hidden" name="option_page" value="bocs_plugin_options">
		<button type="submit" class="btn btn-primary">Submit</button>
	</form>
</div>