// Wait for jQuery to be loaded and then execute
(function($) {
    // Wait for the page to be fully loaded
    $(document).ready(function() {
        // Check if we're on the checkout page
        if (!$('body').hasClass('woocommerce-checkout')) {
            return;
        }
        
        // Function to check and disable save payment method checkbox
        function checkSavePaymentCheckbox() {
            // Find save payment checkbox - handle multiple possible formats for different Stripe versions
            const $savePaymentCheckbox = $(
                // Legacy Stripe
                "input#wc-stripe-new-payment-method, " + 
                // WooCommerce core
                "input.woocommerce-SavedPaymentMethods-saveNew, " + 
                // Stripe checkout
                "input[name=\"wc-stripe-save-to-account-checkbox\"], " + 
                // Generic WooCommerce gateways
                "input[id$=\"save_to_account\"], " + 
                // UPE Stripe
                "input[name$=\"save_payment_method\"], " +
                // UPE with different format
                "input.wc-stripe-save-payment-method-checkbox"
            );
            
            if ($savePaymentCheckbox.length) {
                // Check the box
                $savePaymentCheckbox.prop("checked", true);
                
                // Make it disabled so user can't uncheck it
                $savePaymentCheckbox.prop("disabled", true);
                
                // Add a hidden input to ensure the value is passed even when disabled
                $savePaymentCheckbox.each(function() {
                    const checkboxName = $(this).attr("name");
                    if (checkboxName && !$("#" + checkboxName + "_hidden").length) {
                        $("<input type='hidden' name='" + checkboxName + "' id='" + checkboxName + "_hidden' value='true' />").insertAfter($(this));
                    }
                });
                
                // Add a note about required payment method saving
                $savePaymentCheckbox.each(function() {
                    const $label = $(this).closest("label, .wc-stripe-save-payment-method");
                    if ($label.length && !$label.find(".payment-method-save-notice").length && !$label.next(".payment-method-save-notice").length) {
                        $("<p class='payment-method-save-notice' style='color:#3C7B7C; font-style:italic; margin-top:5px;'>Your payment method will be securely saved for future subscription payments.</p>").insertAfter($label);
                    }
                });
                
                console.log('BOCS: Save payment checkbox was checked and disabled');
            } else {
                console.log('BOCS: Save payment checkbox not found, will try again');
            }
        }
        
        // Handle block-based checkout
        function handleBlocksCheckout() {
            // Target common block-based checkbox selectors
            const saveCheckboxes = document.querySelectorAll(
                ".wc-block-components-payment-methods__save-card-info input[type='checkbox'], " +
                ".wc-block-components-payment-method-label--saved-methods input[type='checkbox'], " +
                ".wc-payment-gateway-method-save-card-info input[type='checkbox']"
            );
            
            if (saveCheckboxes.length) {
                saveCheckboxes.forEach(function(checkbox) {
                    // Check the box
                    checkbox.checked = true;
                    
                    // Make it disabled
                    checkbox.disabled = true;
                    
                    // Add a hidden input
                    if (checkbox.name && !document.getElementById(checkbox.name + "_hidden")) {
                        const hiddenInput = document.createElement("input");
                        hiddenInput.type = "hidden";
                        hiddenInput.name = checkbox.name;
                        hiddenInput.id = checkbox.name + "_hidden";
                        hiddenInput.value = "true";
                        checkbox.parentNode.insertBefore(hiddenInput, checkbox.nextSibling);
                    }
                    
                    // Add notice
                    const label = checkbox.closest("label") || checkbox.parentNode;
                    if (label && !label.querySelector(".payment-method-save-notice")) {
                        const notice = document.createElement("p");
                        notice.className = "payment-method-save-notice";
                        notice.style.color = "#3C7B7C";
                        notice.style.fontStyle = "italic";
                        notice.style.marginTop = "5px";
                        notice.textContent = "Your payment method will be securely saved for future subscription payments.";
                        label.parentNode.insertBefore(notice, label.nextSibling);
                    }
                });
                
                console.log('BOCS: Blocks checkout save payment checkbox was checked and disabled');
            }
        }
        
        // Run our function when payment method is selected or checkout is updated
        $(document.body).on('updated_checkout payment_method_selected', function() {
            checkSavePaymentCheckbox();
        });
        
        // For block-based checkout, set up a mutation observer to detect DOM changes
        if ($('.wc-block-checkout').length) {
            // Run the function immediately
            handleBlocksCheckout();
            
            // Set up a mutation observer to watch for DOM changes
            const observer = new MutationObserver(function(mutations) {
                handleBlocksCheckout();
            });
            
            // Start observing the checkout container
            const checkoutContainer = document.querySelector('.wc-block-checkout');
            if (checkoutContainer) {
                observer.observe(checkoutContainer, { 
                    childList: true,
                    subtree: true 
                });
            }
        }
        
        // Also run on document ready and with a slight delay to catch dynamically loaded elements
        setTimeout(checkSavePaymentCheckbox, 1000);
        setTimeout(checkSavePaymentCheckbox, 2000);
        setTimeout(checkSavePaymentCheckbox, 3000);
    });
})(jQuery);
