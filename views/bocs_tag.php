<?php
$options = get_option( 'bocs_plugin_options' );
$options['sync_tags_to_bocs'] = isset($options['sync_tags_to_bocs']) ? $options['sync_tags_to_bocs'] : 0;
$options['sync_tags_from_bocs'] = isset($options['sync_tags_from_bocs']) ? $options['sync_tags_from_bocs'] : 0;
$options['sync_daily_tags_to_bocs'] = isset($options['sync_daily_tags_to_bocs']) ? $options['sync_daily_tags_to_bocs'] : 0;
$options['sync_daily_tags_from_bocs'] = isset($options['sync_daily_tags_from_bocs']) ? $options['sync_daily_tags_from_bocs'] : 0;
?>
<div class="bocs">
    <h2><?php echo ( get_admin_page_title() ? esc_html( get_admin_page_title()) : 'Bocs Tags Settings'); ?></h2>
    <form method="post">
        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Sync Tags to Bocs</label>
            <div class="col-sm-9">
                <input class="form-check-input" type="radio" name="bocs_plugin_options[sync_tags_to_bocs]" id="bocs_plugin_setting_sync_tags_to_bocs" value="1" <?php echo $options['sync_tags_to_bocs'] == 1 ? "checked" : "" ?>> Yes
                <input class="form-check-input" type="radio" name="bocs_plugin_options[sync_tags_to_bocs]" id="bocs_plugin_setting_sync_tags_to_bocs_no" value="0" <?php echo $options['sync_tags_to_bocs'] == 0 ? "checked" : "" ?>> No <br />
                <p>
                    <button id="syncTagsToBocs" type="button" class="btn btn-primary btn-sm" data-wp-nonce="<?php echo wp_create_nonce("bocs_plugin_options"); ?>">Sync Now</button>
                </p>
                <p id="syncTagsToBocs-response"></p>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Sync Tags from Bocs</label>
            <div class="col-sm-9">
                <input class="form-check-input" type="radio" name="bocs_plugin_options[sync_tags_from_bocs]" id="bocs_plugin_setting_sync_tags_from_bocs" value="1" <?php echo $options['sync_tags_from_bocs'] == 1 ? "checked" : "" ?>> Yes
                <input class="form-check-input" type="radio" name="bocs_plugin_options[sync_tags_from_bocs]" id="bocs_plugin_setting_sync_tags_from_bocs_no" value="0" <?php echo $options['sync_tags_from_bocs'] == 0 ? "checked" : "" ?>> No <br />
                <p>
                    <button id="syncTagsFromBocs" type="button" class="btn btn-primary btn-sm" data-wp-nonce="<?php echo wp_create_nonce("bocs_plugin_options"); ?>">Sync Now</button>
                </p>
                <p id="syncTagsFromBocs-response"></p>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Daily Autosync Tags to Bocs</label>
            <div class="col-sm-9">
                <input class="form-check-input" type="radio" name="bocs_plugin_options[sync_daily_tags_to_bocs]" id="bocs_plugin_setting_sync_daily_tags_to_bocs" value="1" <?php echo $options['sync_daily_tags_to_bocs'] == 1 ? "checked" : "" ?>> Yes
                <input class="form-check-input" type="radio" name="bocs_plugin_options[sync_daily_tags_to_bocs]" id="bocs_plugin_setting_sync_daily_tags_to_bocs_no" value="0" <?php echo $options['sync_daily_tags_to_bocs'] == 0 ? "checked" : "" ?>> No
            </div>
        </div>

        <div class="form-group row">
            <label class="col-sm-3 col-form-label">Daily Autosync Tags from Bocs</label>
            <div class="col-sm-9">
                <input class="form-check-input" type="radio" name="bocs_plugin_options[sync_daily_tags_from_bocs]" id="bocs_plugin_setting_sync_daily_tags_from_bocs" value="1" <?php echo $options['sync_daily_tags_from_bocs'] == 1 ? "checked" : "" ?>> Yes
                <input class="form-check-input" type="radio" name="bocs_plugin_options[sync_daily_tags_from_bocs]" id="bocs_plugin_setting_sync_daily_tags_from_bocs_no" value="0" <?php echo $options['sync_daily_tags_from_bocs'] == 0 ? "checked" : "" ?>> No
            </div>
        </div>
    </form>
</div>