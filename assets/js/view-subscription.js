jQuery(document).ready(function ($) {

    console.log(viewSubscriptionObject);

    // disable the a.bocs-button by default
    $('a.bocs-button').on('click', function (e) {
        e.preventDefault();
    });

    $('a.bocs-button.cancel').on('click', async function () {
        // cancels the current subscription
        $(this).addClass('disabled');
        var buttonElement = $(this);

        if (buttonElement.text() == 'Cancel') {
            buttonElement.text('Processing...');
            // cancel the subscription
            await $.ajax({
                url: viewSubscriptionObject.updateSubscriptionUrl + '/cancel',
                method: 'PUT',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('Store', viewSubscriptionObject.storeId);
                    xhr.setRequestHeader('Organization', viewSubscriptionObject.orgId);
                    xhr.setRequestHeader('Authorization', viewSubscriptionObject.authId);
                },
                success: function (response) {
                    console.log('Cancel response', response);
                    buttonElement.text('Cancelled');
                },
                error: function (error) {
                    console.error('Error cancel subscription', error);
                    buttonElement.text('Cancel');
                    buttonElement.removeClass('disabled');
                },
            });
        }

    });

    $('a.bocs-button.subscription_pause').on('click', async function () {
        $(this).addClass('disabled');
        var buttonElement = $(this);

        if (buttonElement.text() == 'Pause') {
            buttonElement.text('Processing...');
            // pause the subscription
            await $.ajax({
                url: viewSubscriptionObject.updateSubscriptionUrl + '/pause',
                method: 'PUT',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('Store', viewSubscriptionObject.storeId);
                    xhr.setRequestHeader('Organization', viewSubscriptionObject.orgId);
                    xhr.setRequestHeader('Authorization', viewSubscriptionObject.authId);
                },
                success: function (response) {
                    console.log('Pause response', response);
                    buttonElement.text('Paused');
                },
                error: function (error) {
                    console.error('Error pause subscription', error);
                    buttonElement.text('Pause');
                    buttonElement.removeClass('disabled');
                },
            });
        }
    });

    $('a.bocs-button.subscription_renewal_early').on('click', async function () {
        $(this).addClass('disabled');
        var buttonElement = $(this);

        if (buttonElement.text() == 'Renew now') {
            buttonElement.text('Processing...');
            // pause the subscription
            await $.ajax({
                url: viewSubscriptionObject.updateSubscriptionUrl + '/renew',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('Store', viewSubscriptionObject.storeId);
                    xhr.setRequestHeader('Organization', viewSubscriptionObject.orgId);
                    xhr.setRequestHeader('Authorization', viewSubscriptionObject.authId);
                },
                success: function (response) {
                    console.log('Renew response', response);
                    buttonElement.text('Renewed');
                },
                error: function (error) {
                    console.error('Error renew subscription', error);
                    buttonElement.text('Renew now');
                    buttonElement.removeClass('disabled');
                },
            });
        }
    });

});