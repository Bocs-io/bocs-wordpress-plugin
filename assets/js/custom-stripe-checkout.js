/**
 * Custom Stripe Checkout Functionality
 * This script handles the automatic checking and state management of the "Save Card Info" checkbox
 * in the WooCommerce Stripe checkout form.
 */
jQuery(document).ready(function ($) {

    // First timeout: Ensure "Save Card Info" checkbox is checked by default
    setTimeout(function () {
        // Check if the save card checkbox exists
        if ($("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").length > 0) {
            // Auto-check the "Save Card Info" checkbox
            $("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").prop('checked', true);
        }
    }, 5000); // Wait 5 seconds for elements to load

    // Second timeout: Handle checkbox state based on saved payment methods
    setTimeout(function () {
        // Check if there are any saved payment tokens
        if ($("input[name='radio-control-wc-payment-method-saved-tokens']").length === 0) {
            // If no saved tokens exist:
            // 1. Check the "Save Card Info" checkbox
            // 2. Disable the checkbox to prevent unchecking
            $("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").prop('checked', true);
            $("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").prop('disabled', true);
        } else {
            // If saved tokens exist, enable the checkbox to allow user choice
            $("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").prop('disabled', false);
        }
    }, 5000); // Wait 5 seconds for elements to load

});