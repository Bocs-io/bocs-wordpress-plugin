<?php
$wpnonce = wp_create_nonce("bocs_plugin_options");
$user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
?>
<p></p>
<h3>Bocs Integration</h3>
<table class="form-table">
	<tr>
		<th><label for="forceSyncContactsFromBocs">Force sync from Bocs</label></th>
		<td>
			<p>
				<button id="forceSyncContactsFromBocs" type="button" class="btn btn-primary" 
					data-user-id="<?php echo esc_attr($user_id) ?>" 
					data-wp-nonce="<?php echo esc_attr($wpnonce)?>">Sync Now</button>
			</p>
			<p id="forceSyncContactsFromBocs-response"></p>
		</td>
	</tr>
	<tr>
		<th><label for="forceSyncContactsToBocs">Force sync to Bocs</label></th>
		<td>
			<p>
				<button id="forceSyncContactsToBocs" type="button" class="btn btn-primary" 
					data-user-id="<?php echo esc_attr($user_id) ?>" 
					data-wp-nonce="<?php echo esc_attr($wpnonce) ?>">Sync Now</button>
			</p>
			<p id="forceSyncContactsToBocs-response"></p>
		</td>
	</tr>
</table>