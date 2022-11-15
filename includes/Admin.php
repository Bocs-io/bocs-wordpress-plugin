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

        add_settings_field( 'bocs_plugin_setting_api_key', 'Public API Key', [$this, 'bocs_plugin_setting_api_key'], 'bocs_plugin', 'api_settings' );

    }

    public function bocs_plugin_section_text(){
        echo '<p>Here you can set all the options for using the API</p>';
    }

    public function bocs_plugin_setting_api_key() {
        $options = get_option( 'bocs_plugin_options' );
        echo "<input id='bocs_plugin_setting_api_key' name='bocs_plugin_options[api_key]' type='text' value='" . esc_attr( $options['api_key'] ) . "' />";
    }


    public function bocs_plugin_options_validate( $input ) {
        $newinput['api_key'] = trim( $input['api_key'] );
        if ( ! preg_match( '/^[-a-z0-9]{36}$/i', $newinput['api_key'] ) ) {
            $newinput['api_key'] = '';
        }

        return $newinput;
    }

}