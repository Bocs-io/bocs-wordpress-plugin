/* global wc_stripe_params, Stripe */

/**
 * Custom Stripe Checkout Handler
 * 
 * This script provides enhanced functionality for the WooCommerce Stripe checkout process
 * by managing two specific checkboxes:
 * 1. "Save Card" checkbox - For storing payment methods for future purchases
 * 2. "Create an account" checkbox - For automatic account creation during checkout
 * 
 * The script automatically checks both checkboxes and hides them from the user interface
 * to streamline the checkout process and ensure consistent behavior.
 * 
 * Dependencies:
 * - jQuery
 * - WooCommerce Stripe integration
 * 
 * @since 1.0.0
 */
jQuery(function($) {
    'use strict';

    /**
     * Handles the automation of checkbox states in the checkout form
     * 
     * This function performs two main tasks:
     * 1. Manages the "Save Card" checkbox (id: checkbox-control-1):
     *    - Programmatically clicks the checkbox
     *    - Ensures it's checked even if initial click fails
     *    - Hides the checkbox and its label from view
     * 
     * 2. Manages the "Create Account" checkbox within the contact section:
     *    - Automatically checks the box using prop()
     *    - Hides the entire account creation section
     *    
     * The function is called both on initial page load and when dynamic content changes occur
     */
    function handleCheckboxes() {
        // Handle Save Card checkbox functionality
        const saveCardCheckbox = $('#checkbox-control-1');
        if (saveCardCheckbox.length) {
            saveCardCheckbox.trigger('click');
            // Double-check to ensure the checkbox is actually checked
            if (!saveCardCheckbox.is(':checked')) {
                saveCardCheckbox.trigger('click');
            }
            // Hide the entire label containing the checkbox
            saveCardCheckbox.closest('label').hide();
        }

        // Handle Create Account checkbox functionality within #contact section
        const contactSection = $('#contact');
        if (contactSection.length) {
            const createAccountCheckbox = contactSection.find('.wc-block-checkout__create-account input[type="checkbox"]');
            if (createAccountCheckbox.length) {
                // Set checkbox state directly and hide the container
                createAccountCheckbox.prop('checked', true);
                createAccountCheckbox.closest('.wc-block-checkout__create-account').hide();
            }
        }
    }

    // Execute initial checkbox handling when DOM is ready
    handleCheckboxes();

    /**
     * MutationObserver Implementation
     * 
     * Watches for dynamic changes to the DOM that might affect our checkout form.
     * This is crucial because WooCommerce/Stripe can reload parts of the checkout
     * form dynamically (e.g., after AJAX updates or payment method changes).
     * 
     * The observer watches for:
     * - New elements being added (childList)
     * - Changes to existing elements (attributes)
     * - Changes to the entire subtree (subtree)
     * 
     * @type {MutationObserver}
     */
    const bodyObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            // Check if the mutation involves the #contact section directly or indirectly
            if (mutation.target.id === 'contact' || 
                $(mutation.target).find('#contact').length || 
                mutation.target.closest('#contact')) {
                handleCheckboxes();
            } else {
                // Run handler anyway to catch other relevant changes
                handleCheckboxes();
            }
        });
    });

    // Initialize the MutationObserver with comprehensive monitoring options
    bodyObserver.observe(document.body, {
        childList: true,  // Watch for changes to direct children
        subtree: true,    // Watch for changes in all descendants
        attributes: true  // Watch for changes to element attributes
    });
});
