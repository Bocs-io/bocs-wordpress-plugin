/**
 * Custom Stripe Checkout Functionality
 * This script handles the automatic checking and state management of the "Save Card Info" checkbox
 * in the WooCommerce Stripe checkout form.
 */
jQuery(document).ready(function ($) {
    // Wait for Stripe Elements to be fully loaded
    const waitForStripeElements = setInterval(function() {
        if (typeof stripe !== 'undefined' && typeof elements !== 'undefined') {
            clearInterval(waitForStripeElements);
            
            // Monitor all input fields for changes
            const cardNumberInput = $('input[name="number"]');
            const expiryInput = $('input[name="expiry"]');
            const cvcInput = $('input[name="cvc"]');
            const saveCardCheckbox = $('#checkbox-control-1');

            console.log('saveCardCheckbox', saveCardCheckbox);
            console.log('cardNumberInput', cardNumberInput);
            console.log('expiryInput', expiryInput);
            console.log('cvcInput', cvcInput);
            
            // Function to check if all fields are filled correctly
            const checkAllFieldsFilled = () => {
                const cardNumberFilled = cardNumberInput.val().replace(/\s/g, '').length === 16;
                const expiryFilled = expiryInput.val().match(/^\d{2}\s\/\s\d{2}$/);
                const cvcFilled = cvcInput.val().length >= 3;

                console.log('cardNumberFilled', cardNumberFilled);
                console.log('expiryFilled', expiryFilled);
                console.log('cvcFilled', cvcFilled);
                
                if (cardNumberFilled && expiryFilled && cvcFilled) {
                    saveCardCheckbox.prop('checked', true);
                    console.log('saveCardCheckbox', saveCardCheckbox);
                }
            };

            // Add change listeners to all inputs
            cardNumberInput.on('input change', checkAllFieldsFilled);
            expiryInput.on('input change', checkAllFieldsFilled);
            cvcInput.on('input change', checkAllFieldsFilled);
        }
    }, 500);

    // Only check if no saved payment methods exist
    setTimeout(function () {
        if ($("input[name='radio-control-wc-payment-method-saved-tokens']").length === 0) {
            $('#checkbox-control-1').prop('checked', true);
            console.log('checkbox-control-1', $('#checkbox-control-1'));
        }
    }, 2000);
});