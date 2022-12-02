<?php

class Admin
{

	public function bocs_add_settings_page() {
		add_options_page( 'Bocs', 'Bocs', 'manage_options', 'bocs-plugin', [$this, 'bocs_render_plugin_settings_page'] );
	}

	public function bocs_render_plugin_settings_page() {
		?>
		<h2>Bocs Settings</h2>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'bocs_plugin_options' );
			do_settings_sections( 'bocs_plugin' ); ?>
			<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
		</form>
		<?php
	}

	public function bocs_register_settings() {
		register_setting( 'bocs_plugin_options', 'bocs_plugin_options', [$this, 'bocs_plugin_options_validate'] );
		add_settings_section( 'api_settings', 'API Settings', [$this, 'bocs_plugin_section_text'], 'bocs_plugin' );

		// value for the API Key
		add_settings_field( 'bocs_plugin_setting_api_key', 'Public API Key', [$this, 'bocs_plugin_setting_api_key'], 'bocs_plugin', 'api_settings' );

		// enable/disable the sync from wordpress to bocs
		add_settings_field('bocs_plugin_setting_sync_contacts_to_bocs', 'Sync Contacts to Bocs', [$this, 'bocs_plugin_setting_sync_contacts_to_bocs'], 'bocs_plugin', 'api_settings' );

		// enable/disable the sync from wordpress to bocs
		add_settings_field('bocs_plugin_setting_sync_contacts_from_bocs', 'Sync Contacts From Bocs', [$this, 'bocs_plugin_setting_sync_contacts_from_bocs'], 'bocs_plugin', 'api_settings' );

	}

	public function bocs_plugin_section_text(){
		echo '<p>Here you can set all the options for using the API</p>';
	}

	/**
	 * API Key setting
	 *
	 * @return void
	 */
	public function bocs_plugin_setting_api_key() {

		$options = get_option( 'bocs_plugin_options' );

		echo "<input id='bocs_plugin_setting_api_key' name='bocs_plugin_options[api_key]' type='text' value='" . esc_attr( $options['api_key'] ) . "' />";
	}

	/**
	 * Option for enabling/disabling the sync from wordpress to bocs
	 *
	 * @return void
	 */
	public function bocs_plugin_setting_sync_contacts_to_bocs(){

		$options = get_option( 'bocs_plugin_options' );

		$options['sync_contact_to_bocs'] = $options['sync_contact_to_bocs'] ?? 0;

		$html = '<input type="radio" name="bocs_plugin_options[sync_contact_to_bocs]" value="1"';

		$html .= $options['sync_contact_to_bocs'] == 1 ? ' checked' : '';

		$html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;<input type="radio" name="bocs_plugin_options[sync_contact_to_bocs]" value="0"';

		$html .= $options['sync_contact_to_bocs'] != 1 ? ' checked' : '';

		$html .= '><label for="0">No</label>';

		echo $html;
	}

	/**
	 * Option for enabling/disabling the sync from wordpress to bocs
	 *
	 * @return void
	 */
	public function bocs_plugin_setting_sync_contacts_from_bocs(){

		$options = get_option( 'bocs_plugin_options' );

		$options['sync_contact_from_bocs'] = $options['sync_contact_from_bocs'] ?? 0;

		$html = '<input type="radio" name="bocs_plugin_options[sync_contact_from_bocs]" value="1"';

		$html .= $options['sync_contact_from_bocs'] == 1 ? ' checked' : '';

		$html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;<input type="radio" name="bocs_plugin_options[sync_contact_from_bocs]" value="0"';

		$html .= $options['sync_contact_from_bocs'] != 1 ? ' checked' : '';

		$html .= '><label for="0">No</label>';

		echo $html;
	}

    /**
     * @param $input
     * @return array
     */
	public function bocs_plugin_options_validate( $input ) {
		$newinput['api_key'] = trim( $input['api_key'] );
		if ( ! preg_match( '/^[-a-z0-9]{36}$/i', $newinput['api_key'] ) ) {
			$newinput['api_key'] = '';
		}

		return $newinput;
	}

}