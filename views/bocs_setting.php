<?php

/**
 * Provide a admin area view for the plugin
 * This file is used to markup the admin-facing aspects of the plugin.
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
    <h2><?php echo ( get_admin_page_title() ? esc_html( get_admin_page_title()) : 'Bocs Settings'); ?></h2>
    <form action="options.php" method="post">
        <?php
        settings_fields( 'bocs_settings' );
        do_settings_sections( 'bocs_settings' );
        submit_button();
        ?>
    </form>
</div>