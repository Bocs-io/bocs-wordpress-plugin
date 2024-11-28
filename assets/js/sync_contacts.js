jQuery(document).ready(function ($){
	/**
	 *
	 */
	$('body').on('click', '#syncContactsToBocs', function (e){
		e.preventDefault();

		var currentButton = $(this);
		var wpnonce = currentButton.data('wp-nonce');
		currentButton.removeAttr('disabled');
		currentButton.attr('disabled','disabled');
		$('#syncContactsToBocs-response').html('syncing....');

		$.ajax({
			type: "POST",
			dataType: "json",
			url: bocsAjaxObject.ajax_url,
			data: {
				'action': 'sync_contacts_to_bocs',
				'sync_enabled': bocsAjaxObject.syncEnabled,
				'_nonce' : wpnonce
			},
			success: function(response){
				$('#syncContactsToBocs-response').html(response);
				currentButton.removeAttr('disabled');
			}
		});
	});

	$('body').on('click', '#forceSyncContactsToBocs', function(e){

		e.preventDefault();

		var currentButton = $(this);
		var userID = currentButton.data('user-id');
		var wpnonce = currentButton.data('wp-nonce');
		currentButton.removeAttr('disabled');
		currentButton.attr('disabled','disabled');
		$('#forceSyncContactsToBocs-response').html('syncing....');

		$.ajax({
			type: "POST",
			dataType: "json",
			url: bocsAjaxObject.ajax_url,
			data: {
				'action': 'force_sync_contact_to_bocs',
				'sync_enabled': bocsAjaxObject.syncEnabled,
				'user_id': userID,
				'_nonce' : wpnonce
			},
			success: function(response){
				$('#forceSyncContactsToBocs-response').html(response);
				currentButton.removeAttr('disabled');
			},
			error : function(error){
				console.error(error);
			}
		});
	});
});