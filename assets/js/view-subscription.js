jQuery(document).ready(function ($) {
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
                    $('p#subscriptionStatus').text('Cancelled');
                },
                error: function (error) {
                    console.error('Error cancel subscription', error);
                    buttonElement.text('Cancel');
                    buttonElement.removeClass('disabled');
                },
            });
        }

    });

    $('a.bocs-button.subscription_pause').on('click', function () {
        $(this).addClass('disabled');
        var buttonElement = $(this);

        if (buttonElement.text() == 'Pause') {

            $('p#next-payment-date-wrapper').show();

            /*buttonElement.text('Processing...');
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
            });*/
        }
    });

    $('input#next-payment-date-cancel').on('click', function () {
        $('p#next-payment-date-wrapper').hide();
        $('a.bocs-button.subscription_pause').removeClass('disabled');
    });

    $('input#next-payment-date-confirm').on('click', async function () {
        // get the date and time
        var dateTimeValue = $('input#next-payment-date').val();
        console.log(dateTimeValue);
        dateTimeValue = dateTimeValue + '.000Z';
        var buttonElement = $('a.bocs-button.subscription_pause');
        // hide the date
        $('p#next-payment-date-wrapper').hide();

        // update the next payment date
        await $.ajax({
            url: viewSubscriptionObject.updateSubscriptionUrl + '/pause',
            method: 'PUT',
            data: {
                nextPaymentDateGmt: dateTimeValue
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('Store', viewSubscriptionObject.storeId);
                xhr.setRequestHeader('Organization', viewSubscriptionObject.orgId);
                xhr.setRequestHeader('Authorization', viewSubscriptionObject.authId);
            },
            success: function (response) {
                console.log('Pause response', response);
                buttonElement.text('Paused');
                $('p#subscriptionStatus').text('Paused');
            },
            error: function (error) {
                console.error('Error pause subscription', error);
                buttonElement.text('Pause');
                buttonElement.removeClass('disabled');
            },
        });

        // re show the button
        $('a.bocs-button.subscription_pause').removeClass('disabled');
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