// Wait for jQuery to be loaded and then execute
(function($) {
    // Wait for the page to be fully loaded
    $(document).ready(function() {
        // Wait for any dynamic content to load using a small delay
        setTimeout(function() {
            // Handle account creation checkbox
            const checkoutForm = $('form[aria-label="Checkout"]');
            const contactFields = checkoutForm.find('#contact-fields');
            
            const textElement = contactFields.find(':contains("Create an account with")').filter(function() {
                return $(this).children().length === 0;
            });

            if (textElement.length > 0) {
                const checkbox = textElement.closest('#contact-fields').find('input[type="checkbox"]');
                
                if (checkbox.length > 0) {
                    if (!checkbox.is(':checked')) {
                        checkbox.prop('checked', true)
                            .trigger('change')
                            .trigger('input')
                            .trigger('click');
                            
                        setTimeout(() => {
                            checkbox.prop('checked', true).trigger('change');
                        }, 1000);
                    }
                }
            }

            // Handle save payment information checkbox
            const paymentMethod = checkoutForm.find('#payment-method');
            const saveCardCheckbox = paymentMethod.find('.wc-block-components-payment-methods__save-card-info input[type="checkbox"]');
            
            if (saveCardCheckbox.length > 0) {
                if (!saveCardCheckbox.is(':checked')) {
                    saveCardCheckbox.prop('checked', true)
                        .trigger('change')
                        .trigger('input')
                        .trigger('click');
                        
                    setTimeout(() => {
                        saveCardCheckbox.prop('checked', true).trigger('change');
                    }, 1000);
                }
                
                if (saveCardCheckbox.is(':checked')) {
                    console.log('Save payment checkbox was successfully checked');
                } else {
                    console.log('Failed to check save payment checkbox');
                }
            } else {
                console.log('Save payment checkbox not found');
            }
        }, 1000);
    });
})(jQuery);
