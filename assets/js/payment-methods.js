let stripe = null;
let elements = null;
let setupData = null; // Store setup data globally for access in different functions
let paymentElement = null;
let card = null;

// Helper function to ensure Stripe is loaded
function ensureStripeLoaded(callback) {
    // If Stripe is already available, call the callback immediately
    if (typeof Stripe !== 'undefined') {
        console.log('Stripe already loaded');
        callback();
        return;
    }
    
    console.log('Stripe not loaded, loading script...');
    
    // Create a script element to load Stripe.js
    const script = document.createElement('script');
    script.src = 'https://js.stripe.com/v3/';
    script.onload = () => {
        console.log('Stripe script loaded successfully');
        callback();
    };
    script.onerror = () => {
        console.error('Failed to load Stripe script');
    };
    document.head.appendChild(script);
}

// Helper function to get user billing details
async function getUserBillingDetails() {
    try {
        const response = await jQuery.ajax({
            url: bocsPaymentData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_user_billing_details',
                nonce: bocsPaymentData.nonce
            }
        });
        
        if (response.success) {
            console.log('Fetched user billing details:', response.data);
            // Store the Stripe customer ID globally if it exists
            if (response.data.stripe_customer_id) {
                window.stripeCustomerId = response.data.stripe_customer_id;
                console.log('Found existing Stripe customer ID:', window.stripeCustomerId);
            }
            return response.data.billing_details;
        } else {
            console.error('Error fetching user billing details:', response.data?.message);
            // Return default billing details
            return {
                name: 'Customer',
                email: '',
                phone: '',
                address: {
                    country: 'AU',
                    postal_code: '2000',
                    line1: '',
                    line2: '', // Make sure line2 is included
                    city: '',
                    state: ''
                }
            };
        }
    } catch (error) {
        console.error('AJAX Error fetching user billing details:', error);
        // Return default billing details
        return {
            name: 'Customer',
            email: '',
            phone: '',
            address: {
                country: 'AU',
                postal_code: '2000',
                line1: '',
                line2: '', // Make sure line2 is included
                city: '',
                state: ''
            }
        };
    }
}

const bocsPaymentMethods = {
    init: function() {
        console.log('Initializing payment methods...'); // Debug log
        
        // First, ensure the modal HTML exists
        if (!jQuery('#edit-payment-method-modal').length) {
            console.log('Adding modal HTML to page...'); // Debug log
            jQuery('body').append(`
                <div id="edit-payment-method-modal" class="bocs-modal" style="display: none;">
                    <div class="bocs-modal-content"></div>
                </div>
            `);
        }
        
        // Check if we're returning from a Stripe redirect
        this.checkStripeRedirect();
        
        this.bindEvents();
    },
    
    // Check if we're returning from a Stripe redirect flow
    checkStripeRedirect: function() {
        // Check URL parameters for setup_intent and setup_intent_client_secret
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('setup_intent') && urlParams.has('setup_intent_client_secret')) {
            console.log('Detected return from Stripe redirect');
            
            // Find and close any open modals
            jQuery('#payment-method-modal, #edit-payment-method-modal').hide();
            
            // Show a success toast/message at the top of the page
            const successMessage = jQuery(`<div class="bocs-success-toast" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; background-color: #d4edda; color: #155724; padding: 15px 20px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 90%; width: 400px; text-align: center; animation: fadeIn 0.3s ease-in-out;">
                <p style="margin: 0; font-weight: 500;"><span style="margin-right: 8px;">✅</span> Payment method updated successfully</p>
            </div>`);
            
            // Add to body and fade out after a few seconds
            jQuery('body').append(successMessage);
            
            // Fade out and remove after 5 seconds
            setTimeout(() => {
                successMessage.fadeOut(300, function() {
                    successMessage.remove();
                });
            }, 5000);
            
            // Clean up URL parameters without refreshing the page
            if (window.history && window.history.replaceState) {
                const newUrl = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, newUrl);
            }
            
            // Clean up the session storage
            setTimeout(() => {
                if (sessionStorage.getItem('bocs_subscription_id')) {
                    console.log('Cleaning up stored subscription ID');
                    sessionStorage.removeItem('bocs_subscription_id');
                    this.removeCookie('bocs_subscription_id');
                }
            }, 500);
        }
    },
    
    // Set a cookie with the subscription ID (as backup for sessionStorage)
    setCookie: function(name, value, days) {
        let expires = '';
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + (value || '') + expires + '; path=/';
    },
    
    // Remove a cookie
    removeCookie: function(name) {
        document.cookie = name + '=; Max-Age=-99999999; path=/';
    },

    bindEvents: function() {
        console.log('Binding events...'); // Debug log
        jQuery(document).on('click', '.edit-payment-method', (e) => {
            console.log('Edit payment method clicked'); // Debug log
            this.handleEditPaymentMethod(e);
        });
    },

    handleEditPaymentMethod: function(e) {
        e.preventDefault();
        console.log('Handling edit payment method...');
        
        // Find the modal element
        var $modal = $('#payment-method-modal');
        console.log('Modal element:', $modal.length ? 'found' : 'not found');
        
        if (!$modal.length) {
            // Create the modal if it doesn't exist
            $('body').append(`
                <div id="payment-method-modal" style="display:none;" class="bocs-modal">
                    <div class="bocs-modal-content">
                        <span class="bocs-modal-close">&times;</span>
                        <div class="bocs-modal-body">
                            <p class="loading">${bocsPaymentData.i18n.loading || 'Loading payment methods...'}</p>
                        </div>
                    </div>
                </div>
            `);
            $modal = $('#payment-method-modal');
            
            // Add close button handler
            $modal.find('.bocs-modal-close').on('click', function() {
                $modal.hide();
                // Clean up Stripe Elements if they exist
                if (paymentElement) {
                    paymentElement.destroy();
                    paymentElement = null;
                }
                // Clear modal content
                $modal.find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(e) {
                if ($(e.target).is($modal)) {
                    $modal.hide();
                    // Clean up Stripe Elements if they exist
                    if (paymentElement) {
                        paymentElement.destroy();
                        paymentElement = null;
                    }
                    // Clear modal content
                    $modal.find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
                }
            });
        }
        
        // Get subscription ID from the button's data attribute
        var subscriptionId = $(e.currentTarget).data('subscription-id');
        console.log('Subscription ID:', subscriptionId);
        
        // Store subscription ID in a global variable for backup
        window.currentSubscriptionId = subscriptionId;
        
        // Show modal with loading message
        $modal.find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
        
        // Show the modal
        $modal.css('display', 'flex');
        
        // Make an AJAX request to get payment methods for this subscription
        console.log('Sending AJAX request for direct payment methods...');
        $.ajax({
            url: bocsPaymentData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_subscription_payment_method_direct',
                nonce: bocsPaymentData.nonce,
                subscription_id: subscriptionId
            },
            success: function(response) {
                console.log('Payment methods AJAX response:', response);
                if (response.success) {
                    // Replace loading message with payment methods
                    $modal.find('.bocs-modal-body').html(response.data.html);
                    
                    // Add form submission handler
                    $modal.find('form').on('submit', async function(e) {
                        e.preventDefault();
                        console.log('Form submitted');
                        
                        const form = $(this);
                        const submitButton = form.find('button[type="submit"]');
                        const errorElement = $('#payment-element-errors');
                        const selectedMethod = form.find('input[name="payment_method"]:checked').val();
                        
                        console.log('Selected payment method for submission:', selectedMethod);
                        
                        try {
                            submitButton.prop('disabled', true)
                                .html('<span class="loading-spinner"></span> Processing...');
                            
                            if (selectedMethod === 'new') {
                                console.log('Processing new payment method submission');
                                
                                // Ensure Stripe and elements are available
                                if (!stripe || !elements) {
                                    console.error('Stripe or elements not initialized:', { stripe, elements });
                                    
                                    // Try to reinitialize Stripe if it's available in the global scope
                                    if (typeof Stripe !== 'undefined' && window.setupData && window.setupData.publishable_key) {
                                        console.log('Attempting to reinitialize Stripe');
                                        stripe = Stripe(window.setupData.publishable_key);
                                        
                                        // If we still don't have elements but have client_secret, try to create them
                                        if (!elements && window.setupData.client_secret) {
                                            elements = stripe.elements({
                                                clientSecret: window.setupData.client_secret
                                            });
                                        }
                                    }
                                    
                                    // If reinitialization failed, throw error
                                    if (!stripe || !elements) {
                                        throw new Error('Stripe has not been properly initialized. Please reload the page and try again.');
                                    }
                                }
                                
                                // Try multiple ways to get the subscription ID
                                let subscriptionId = null;
                                
                                // First check window.setupData
                                if (window.setupData && window.setupData.subscription_id) {
                                    subscriptionId = window.setupData.subscription_id;
                                    console.log('Using subscription ID from window.setupData:', subscriptionId);
                                } 
                                // Next try data attribute on form
                                else if (form.attr('data-subscription-id')) {
                                    subscriptionId = form.attr('data-subscription-id');
                                    console.log('Using subscription ID from form data attribute:', subscriptionId);
                                }
                                // Lastly, try the button attribute that opened the modal
                                else if ($('.edit-payment-method[data-subscription-id]').length) {
                                    subscriptionId = $('.edit-payment-method[data-subscription-id]').data('subscription-id');
                                    console.log('Using subscription ID from edit button:', subscriptionId);
                                }
                                // Try the global variable we set when opening the modal
                                else if (window.currentSubscriptionId) {
                                    subscriptionId = window.currentSubscriptionId;
                                    console.log('Using subscription ID from global variable:', subscriptionId);
                                }
                                
                                // Ensure we have the subscription ID
                                if (!subscriptionId) {
                                    console.error('Missing subscription data. Could not find subscription ID from any source.');
                                    throw new Error('Missing subscription data. Please try again or reload the page.');
                                }
                                
                                // Store subscription ID for the redirect handler
                                sessionStorage.setItem('bocs_subscription_id', subscriptionId);
                                document.cookie = 'bocs_subscription_id=' + subscriptionId + '; path=/; max-age=86400';
                                
                                console.log('Confirming Stripe setup with subscription ID:', subscriptionId);
                                
                                // Confirm the setup with error handling
                                try {
                                    // Clear any existing errors first
                                    errorElement.text('').hide();
                                    
                                    // Verify we have all the required components
                                    if (!stripe) {
                                        throw new Error('Stripe is not initialized properly.');
                                    }
                                    
                                    if (!elements) {
                                        throw new Error('Stripe Elements is not initialized properly.');
                                    }
                                    
                                    // Before continuing, ensure we've submitted a complete payment form
                                    const element = elements.getElement('payment');
                                    if (!element) {
                                        throw new Error('Payment element not found. Please refresh and try again.');
                                    }
                                    
                                    // We can't check if the form is complete using getValue() as it's not supported
                                    // Instead, we'll just proceed and let Stripe validation handle any issues
                                    
                                    // First we need to fetch the user's billing details
                                    const billingDetails = await getUserBillingDetails();
                                    
                                    // Add extra params to confirmParams
                                    const confirmParams = {
                                        elements,
                                        confirmParams: {
                                            return_url: window.location.href,
                                            payment_method_data: {
                                                billing_details: billingDetails
                                            },
                                            expand: ['payment_method']
                                        }
                                    };
                                    
                                    // Log the complete confirmParams for debugging
                                    console.log('BOCS DEBUG - Full confirmSetup parameters:', JSON.stringify(confirmParams, null, 2));
                                    
                                    // Clear previous error messages
                                    errorElement.hide().empty();
                                    
                                    try {
                                        console.log('Sending confirmSetup request to Stripe...');
                                        const { error, setupIntent } = await stripe.confirmSetup(confirmParams);
                                        
                                        if (error) {
                                            console.error('Stripe confirmation error:', error);
                                            throw error;
                                        }
                                        
                                        console.log('Stripe confirmation response:', setupIntent);
                                        
                                        // Check if additional actions are required (like 3D Secure)
                                        if (setupIntent && setupIntent.status === 'requires_action') {
                                            console.log('Setup requires additional action. Redirecting...');
                                            // The confirmSetup will handle the redirect automatically
                                            return;
                                        }
                                        
                                        // If we got here without a redirect, the setup was successful
                                        if (setupIntent && setupIntent.status === 'succeeded') {
                                            console.log('Setup successful!');
                                            // You can handle success here if not redirected
                                            window.location.href = window.location.href.split('?')[0] + '?payment_updated=success';
                                            return;
                                        }
                                        
                                        console.log('Stripe confirmation successful, waiting for redirect...');
                                    } catch (error) {
                                        console.error('Error confirming Stripe setup:', error);
                                        
                                        // Display detailed error nicely
                                        let errorMessage = error.message || 'Payment verification failed. Please try again.';
                                        
                                        // Add more context if it's a specific Stripe error
                                        if (error.type) {
                                            errorMessage += ` (Error type: ${error.type})`;
                                        }
                                        
                                        // Display the error nicely
                                        errorElement.html(`
                                            <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 10px 0;">
                                                <strong>Error:</strong> ${errorMessage}
                                            </div>
                                        `).show();
                                        
                                        // Re-enable the button and reset text
                                        submitButton.prop('disabled', false).text('Update Payment Method');
                                    }
                                } catch (error) {
                                    console.error('Error confirming Stripe setup:', error);
                                    
                                    // Detailed error display
                                    let errorMessage = error.message || 'Payment verification failed. Please try again.';
                                    
                                    // Add more context if it's a specific Stripe error
                                    if (error.type) {
                                        errorMessage += ` (Error type: ${error.type})`;
                                    }
                                    
                                    // Display the error nicely
                                    errorElement.html(`
                                        <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 10px 0;">
                                            <strong>Error:</strong> ${errorMessage}
                                        </div>
                                    `).show();
                                    
                                    // Re-enable the button and reset text
                                    submitButton.prop('disabled', false).text('Update Payment Method');
                                }
                            } else {
                                console.log('Processing existing payment method selection:', selectedMethod);
                                
                                // Handle existing payment method selection
                                $.ajax({
                                    url: bocsPaymentData.ajaxUrl,
                                    type: 'POST',
                                    data: {
                                        action: 'update_subscription_payment',
                                        nonce: bocsPaymentData.nonce,
                                        payment_method: selectedMethod,
                                        subscription_id: subscriptionId
                                    },
                                    success: function(response) {
                                        console.log('Payment update response:', response);
                                        
                                        if (response.success) {
                                            // Show success message
                                            form.html(`
                                                <div class="success-message">
                                                    <p><strong>Payment method updated successfully!</strong></p>
                                                    <button type="button" class="button close-modal">Close</button>
                                                </div>
                                            `);
                                            
                                            // Add handler for close button
                                            form.find('.close-modal').on('click', function() {
                                                $modal.hide();
                                                // Clean up Stripe Elements if they exist
                                                if (paymentElement) {
                                                    paymentElement.destroy();
                                                    paymentElement = null;
                                                }
                                            });
                                            
                                            // Auto close modal after 2 seconds
                                            setTimeout(() => {
                                                $modal.hide();
                                                
                                                // Show a success toast at the top of the page
                                                const successToast = jQuery(`<div class="bocs-success-toast" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; background-color: #d4edda; color: #155724; padding: 15px 20px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 90%; width: 400px; text-align: center; animation: fadeIn 0.3s ease-in-out;">
                                                        <p style="margin: 0; font-weight: 500;"><span style="margin-right: 8px;">✅</span> Payment method updated successfully</p>
                                                    </div>`);
                                                    
                                                    // Add to body and fade out after a few seconds
                                                    jQuery('body').append(successToast);
                                                    
                                                    // Fade out and remove after 5 seconds
                                                    setTimeout(() => {
                                                        successToast.fadeOut(300, function() {
                                                            successToast.remove();
                                                        });
                                                    }, 5000);
                                            }, 2000);
                                        } else {
                                            console.error('Error updating payment method:', response.data?.message);
                                            errorElement.text(response.data?.message || 'Failed to update payment method.').show();
                                            // Ensure error is visible with inline styling
                                            errorElement.css({
                                                'display': 'block',
                                                'color': '#d63638',
                                                'margin-top': '15px',
                                                'padding': '10px 15px',
                                                'background-color': '#fef8f8',
                                                'border-left': '4px solid #d63638'
                                            });
                                            submitButton.prop('disabled', false).text('Update Payment Method');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('AJAX Error updating payment method:', error);
                                        errorElement.text('Error updating payment method. Please try again.').show();
                                        // Ensure error is visible with inline styling
                                        errorElement.css({
                                            'display': 'block',
                                            'color': '#d63638',
                                            'margin-top': '15px',
                                            'padding': '10px 15px',
                                            'background-color': '#fef8f8',
                                            'border-left': '4px solid #d63638'
                                        });
                                        submitButton.prop('disabled', false).text('Update Payment Method');
                                    }
                                });
                            }
                        } catch (error) {
                            console.error('Error in form submission:', error);
                            errorElement.text(error.message || 'An unexpected error occurred. Please try again.').show();
                            // Ensure error is visible with inline styling
                            errorElement.css({
                                'display': 'block',
                                'color': '#d63638',
                                'margin-top': '15px',
                                'padding': '10px 15px',
                                'background-color': '#fef8f8',
                                'border-left': '4px solid #d63638'
                            });
                            submitButton.prop('disabled', false).text('Update Payment Method');
                        }
                    });
                    
                    // Add handler for radio button changes
                    $modal.find('input[name="payment_method"]').on('change', function() {
                        const selectedValue = $(this).val();
                        console.log('Selected payment method:', selectedValue);
                        
                        if (selectedValue === 'new') {
                            // First check if the container exists
                            let $paymentContainer = $('#new-payment-method');
                            
                            // If the container doesn't exist, create it
                            if ($paymentContainer.length === 0) {
                                console.log('Creating payment container...');
                                const containerHTML = `
                                    <div id="new-payment-method" style="background: #f9f9f9; border-radius: 8px; padding: 20px; margin-top: 20px; margin-bottom: 20px; border: 1px solid #e0e0e0;">
                                        <h4 style="margin-top: 0;">Add a new card</h4>
                                        <div id="payment-element">
                                            <div class="payment-element-loader">
                                                <div class="loading-spinner"></div>
                                                <p style="margin-left: 10px;">Loading payment form...</p>
                                            </div>
                                        </div>
                                        <div id="payment-element-errors" class="stripe-error"></div>
                                    </div>
                                `;
                                
                                // Append the container to the form
                                $(this).closest('form').append(containerHTML);
                                $paymentContainer = $('#new-payment-method');
                                
                                // Hide the original buttons when showing the payment form
                                const $form = $(this).closest('form');
                                const $buttons = $form.find('.modal-buttons, .bocs-modal-actions');
                                $buttons.hide();
                                
                                // Add action buttons to the bottom of our container
                                const $actionButtons = jQuery(`
                                    <div class="form-payment-actions">
                                        <button type="button" class="button cancel-button">Cancel</button>
                                        <button type="submit" class="button button-primary submit-payment">Update Payment Method</button>
                                    </div>
                                `);
                                
                                $paymentContainer.append($actionButtons);
                                
                                // Add click handlers
                                $actionButtons.find('.cancel-button').on('click', function() {
                                    // Hide payment method container
                                    $paymentContainer.hide();
                                    
                                    // Reset radio selection to first option
                                    $form.find('input[name="payment_method"]:first').prop('checked', true);
                                    
                                    // Show original action buttons
                                    $buttons.show();
                                    
                                    // Clean up Stripe Elements if they exist
                                    if (paymentElement) {
                                        try {
                                            paymentElement.destroy();
                                            paymentElement = null;
                                        } catch (e) {
                                            console.error('Error destroying payment element:', e);
                                        }
                                    }
                                    
                                    // Clear any error messages
                                    $paymentContainer.find('#payment-element-errors').text('').hide();
                                });
                                
                                // Submit handler for the form
                                $actionButtons.find('.submit-payment').on('click', function(e) {
                                    e.preventDefault();
                                    console.log('Submit button clicked');
                                    try {
                                        $form.submit();
                                    } catch (error) {
                                        console.error('Error submitting form:', error);
                                        alert('There was an error processing your payment. Please try again.');
                                    }
                                });
                            } else {
                                // Just show the existing container
                                $paymentContainer.show();
                            }
                            
                            // Initialize Stripe for new payment method
                            console.log('Initializing Stripe for new payment method...');
                            // Get a reference to the form for use in the AJAX success callback
                            const formElement = $(this).closest('form');
                            
                            $.ajax({
                                url: bocsPaymentData.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'bocs_get_stripe_setup',
                                    nonce: bocsPaymentData.nonce,
                                    subscription_id: subscriptionId
                                },
                                success: function(setupResponse) {
                                    console.log('Stripe setup response:', setupResponse);
                                    if (setupResponse.success) {
                                        const setupData = setupResponse.data;
                                        
                                        // Store setup data globally 
                                        window.setupData = setupData;
                                        console.log('Stored setup data:', window.setupData);
                                        
                                        // Store subscription ID directly on form as data attribute for backup
                                        formElement.attr('data-subscription-id', setupData.subscription_id);
                                        
                                        // Ensure Stripe is loaded before initializing
                                        ensureStripeLoaded(() => {
                                            // Initialize Stripe if not already done
                                            if (!stripe) {
                                                console.log('Initializing Stripe with key:', setupData.publishable_key);
                                                try {
                                                    stripe = Stripe(setupData.publishable_key);
                                                } catch (error) {
                                                    console.error('Error initializing Stripe:', error);
                                                    jQuery('#payment-element-errors').text('Failed to initialize payment form: ' + error.message).show();
                                                    return;
                                                }
                                            }
                                            
                                            // Create Elements instance
                                            console.log('Creating Stripe Elements with client secret');
                                            try {
                                                elements = stripe.elements({
                                                    clientSecret: setupData.client_secret,
                                                    appearance: {
                                                        theme: 'stripe',
                                                        variables: {
                                                            colorPrimary: '#7f54b3',
                                                            colorBackground: '#ffffff',
                                                            colorText: '#30313d',
                                                            colorDanger: '#df1b41',
                                                            fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                                                            spacingUnit: '4px',
                                                            borderRadius: '4px'
                                                        }
                                                    }
                                                });
                                            } catch (error) {
                                                console.error('Error creating Stripe Elements:', error);
                                                jQuery('#payment-element-errors').text('Failed to create payment form: ' + error.message).show();
                                                return;
                                            }
                                            
                                            // Create and mount the Payment Element
                                            if (paymentElement) {
                                                console.log('Destroying existing payment element');
                                                try {
                                                    paymentElement.destroy();
                                                } catch (error) {
                                                    console.error('Error destroying payment element:', error);
                                                }
                                            }
                                            
                                            console.log('Creating and mounting payment element');
                                            try {
                                                // Create a more robust payment element with necessary options
                                                paymentElement = elements.create('payment', {
                                                    fields: {
                                                        billingDetails: 'never'
                                                    },
                                                    terms: {
                                                        card: 'never'
                                                    },
                                                    wallets: {
                                                        applePay: 'auto',
                                                        googlePay: 'auto'
                                                    },
                                                    layout: {
                                                        type: 'tabs',
                                                        defaultCollapsed: false
                                                    }
                                                });
                                                
                                                const paymentElementContainer = document.getElementById('payment-element');
                                                if (paymentElementContainer) {
                                                    // Clear any existing content
                                                    paymentElementContainer.innerHTML = '';
                                                    // Mount the element
                                                    paymentElement.mount('#payment-element');
                                                    console.log('Payment element mounted successfully');
                                                    
                                                    // Add event listener for change events
                                                    paymentElement.on('change', (event) => {
                                                        const errorElement = document.getElementById('payment-element-errors');
                                                        if (errorElement) {
                                                            if (event.error) {
                                                                errorElement.textContent = event.error.message;
                                                                errorElement.style.display = 'block';
                                                            } else {
                                                                errorElement.textContent = '';
                                                                errorElement.style.display = 'none';
                                                            }
                                                        }
                                                    });
                                                } else {
                                                    console.error('Payment element container not found in DOM');
                                                    // Try to solve by re-creating the container
                                                    const newPaymentMethod = jQuery('#new-payment-method');
                                                    if (newPaymentMethod.length) {
                                                        newPaymentMethod.html(`
                                                            <h4 style="margin-top: 0;">Add a new card</h4>
                                                            <div id="payment-element"></div>
                                                            <div id="payment-element-errors" class="stripe-error"></div>
                                                        `);
                                                        
                                                        // Try mounting again
                                                        setTimeout(() => {
                                                            console.log('Retrying mount after recreating container');
                                                            try {
                                                                paymentElement.mount('#payment-element');
                                                                console.log('Payment element mounted on retry');
                                                                
                                                                // Move the form buttons below the payment element after it's mounted
                                                                try {
                                                                    bocsPaymentMethods.moveButtonsBelowForm();
                                                                } catch (error) {
                                                                    console.error('Error moving buttons on retry:', error);
                                                                }
                                                            } catch (mountError) {
                                                                console.error('Mount retry failed:', mountError);
                                                                const errorDiv = document.getElementById('payment-element-errors');
                                                                if (errorDiv) {
                                                                    errorDiv.textContent = 'Could not load payment form. Please try again or contact support.';
                                                                    errorDiv.style.display = 'block';
                                                                }
                                                            }
                                                        }, 100);
                                                    } else {
                                                        console.error('New payment method container not found');
                                                    }
                                                }
                                            } catch (error) {
                                                console.error('Error creating or mounting payment element:', error);
                                                jQuery('#payment-element-errors').text('Failed to display payment form: ' + error.message).show();
                                            }
                                        });
                                    } else {
                                        console.error('Failed to initialize Stripe:', setupResponse.data.message);
                                        jQuery('#payment-element-errors').text(setupResponse.data.message || 'Failed to initialize payment form.').show();
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('AJAX Error:', error);
                                    jQuery('#payment-element-errors').text('Error setting up payment form. Please try again.').show();
                                }
                            });
                        } else {
                            // Hide the payment element for existing methods
                            jQuery('#new-payment-method').hide();
                            
                            // Clean up Stripe Elements if they exist
                            if (paymentElement) {
                                paymentElement.destroy();
                                paymentElement = null;
                            }
                        }
                    });
                } else {
                    // Show error message
                    $modal.find('.bocs-modal-body').html('<p class="error">' + (response.data.message || 'Error loading payment methods') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $modal.find('.bocs-modal-body').html('<p class="error">Error loading payment methods. Please try again.</p>');
            }
        });
    },

    showPaymentModal: function(setupData) {
        console.log('Showing payment modal...', setupData); // Debug log
        const modal = jQuery('#edit-payment-method-modal');
        
        // First ensure modal is hidden with display: none
        modal.hide();
        
        // Generate and set modal content
        const modalContent = `
            <h3>${bocsPaymentData.i18n.editPaymentMethod}</h3>
            <form id="edit-payment-method-form">
                ${setupData.saved_methods && setupData.saved_methods.length > 0 ? `
                    <div class="saved-payment-methods">
                        <h4>${bocsPaymentData.i18n.savedPaymentMethods}</h4>
                        ${setupData.saved_methods.map((method, index) => `
                            <div class="payment-method-option">
                                <input type="radio" 
                                       name="payment_method" 
                                       id="payment-method-${method.id}" 
                                       value="${method.id}"
                                       ${index === 0 ? 'checked' : ''}>
                                <label for="payment-method-${method.id}">
                                    <span class="card-brand ${method.brand.toLowerCase()}"></span>
                                    **** **** **** ${method.last4}
                                    <span class="card-expiry">
                                        ${bocsPaymentData.i18n.expires} ${method.exp_month}/${method.exp_year}
                                    </span>
                                </label>
                            </div>
                        `).join('')}
                        
                        <div class="payment-method-option">
                            <input type="radio" 
                                   name="payment_method" 
                                   id="payment-method-new" 
                                   value="new">
                            <label for="payment-method-new">
                                ${bocsPaymentData.i18n.addNewPaymentMethod}
                            </label>
                        </div>
                    </div>
                ` : ''}
                
                <div id="new-payment-method" style="display: none;">
                    <div id="payment-element">
                        <div class="payment-element-loader" style="display: flex; justify-content: center; align-items: center; min-height: 150px;">
                            <div style="border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite;"></div>
                            <p style="margin-left: 10px;">Loading payment form...</p>
                        </div>
                    </div>
                </div>
                
                <div id="error-message" class="stripe-error"></div>
                
                <div class="bocs-modal-actions">
                    <button type="button" class="button cancel-modal">
                        ${bocsPaymentData.i18n.cancel}
                    </button>
                    <button type="submit" class="button button-primary">
                        ${bocsPaymentData.i18n.updatePaymentMethod}
                    </button>
                </div>
                
                <!-- Debug Controls -->
                <div class="bocs-debug-controls" style="margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc;">
                    <details>
                        <summary style="cursor: pointer; color: #5a5a5a;">Debug Options</summary>
                        <div style="padding: 10px; background: #f8f8f8; margin-top: 10px;">
                            <p>Use these options to diagnose connection issues:</p>
                            
                            <div style="margin-top: 10px;">
                                <button type="button" id="direct-test-btn" class="button" style="background: #ffe8cc; border-color: #ffb74d;">
                                    Test Endpoint Directly
                                </button>
                                <button type="button" id="debug-log-btn" class="button" style="margin-left: 5px; background: #e8f4ff; border-color: #4d94ff;">
                                    Display Debug Info
                                </button>
                            </div>
                            
                            <div id="debug-output" style="margin-top: 10px; max-height: 150px; overflow: auto; font-family: monospace; font-size: 12px; background: #252525; color: #eee; padding: 8px; display: none;"></div>
                        </div>
                    </details>
                </div>
            </form>
        `;
        modal.find('.bocs-modal-content').html(modalContent);

        // Force modal to be visible with important flags
        modal.css({
            'position': 'fixed',
            'top': '0',
            'left': '0',
            'width': '100%',
            'height': '100%',
            'background-color': 'rgba(0, 0, 0, 0.5)',
            'z-index': '999999',
            'display': 'flex',
            'align-items': 'center',
            'justify-content': 'center'
        }).fadeIn(200);

        console.log('Modal display style after show:', modal.css('display')); // Debug log

        // Initialize Stripe Elements if needed
        if (!elements && setupData.client_secret) {
            console.log('Initializing Stripe Elements...'); // Debug log
            elements = stripe.elements({
                clientSecret: setupData.client_secret
            });
        }

        // Show modal
        modal.css('display', 'flex').hide().fadeIn(200);

        // Handle payment method selection
        jQuery('input[name="payment_method"]').on('change', function() {
            const newPaymentMethod = jQuery('#new-payment-method');
            if (jQuery(this).val() === 'new') {
                newPaymentMethod.slideDown(200);
                if (!paymentElement && elements) {
                    console.log('Mounting payment element...'); // Debug log
                    paymentElement = elements.create('payment');
                    paymentElement.mount('#payment-element');
                }
            } else {
                newPaymentMethod.slideUp(200);
            }
        });

        // Handle form submission
        jQuery('#edit-payment-method-form').on('submit', this.handleFormSubmit.bind(this));

        // Handle modal close
        modal.find('.cancel-modal').on('click', function() {
            modal.fadeOut(200);
        });
        
        // Add debug button handlers
        jQuery('#direct-test-btn').on('click', () => {
            const selectedMethod = jQuery('input[name="payment_method"]:checked').val();
            const debugOutput = jQuery('#debug-output');
            debugOutput.show();
            debugOutput.html('Sending direct test request...');
            
            // Create a form and submit it
            const testForm = document.createElement('form');
            testForm.method = 'POST';
            testForm.action = bocsPaymentData.ajaxUrl;
            testForm.target = '_blank';
            
            // Add fields
            const fields = {
                action: 'update_subscription_payment',
                payment_method: selectedMethod,
                subscription_id: setupData.subscription_id,
                nonce: bocsPaymentData.nonce
            };
            
            Object.entries(fields).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                testForm.appendChild(input);
            });
            
            // Append to body and submit
            document.body.appendChild(testForm);
            testForm.submit();
            
            // Update UI
            debugOutput.html(`
                Direct test initiated in new window.<br>
                Fields sent: ${JSON.stringify(fields)}
            `);
        });
        
        jQuery('#debug-log-btn').on('click', () => {
            const debugOutput = jQuery('#debug-output');
            debugOutput.show();
            
            const debugInfo = {
                'ajaxUrl': bocsPaymentData.ajaxUrl,
                'subscriptionId': setupData.subscription_id,
                'selectedMethod': jQuery('input[name="payment_method"]:checked').val(),
                'nonce': bocsPaymentData.nonce,
                'userAgent': navigator.userAgent,
                'screenSize': `${window.innerWidth}x${window.innerHeight}`,
                'isSecure': window.location.protocol === 'https:',
                'cookiesEnabled': navigator.cookieEnabled
            };
            
            debugOutput.html(JSON.stringify(debugInfo, null, 2));
        });
    },

    handleFormSubmit: async function(e) {
        e.preventDefault();
        console.log('Form submitted...'); // Debug log
        
        const form = jQuery(e.currentTarget);
        const submitButton = form.find('button[type="submit"]');
        const errorElement = form.find('#error-message');
        const selectedMethod = form.find('input[name="payment_method"]:checked').val();
        
        try {
            submitButton.prop('disabled', true)
                       .html(`<span class="loading-spinner"></span> ${bocsPaymentData.i18n.processing}`);
            
            if (selectedMethod === 'new') {
                // Store subscription ID in both sessionStorage and cookie for redundancy
                if (setupData && setupData.subscription_id) {
                    // Store in sessionStorage
                    sessionStorage.setItem('bocs_subscription_id', setupData.subscription_id);
                    
                    // Also set as a cookie (backup method)
                    this.setCookie('bocs_subscription_id', setupData.subscription_id, 1);
                    
                    console.log('Stored subscription ID for redirect:', setupData.subscription_id);
                }
                
                // First we need to fetch the user's billing details
                const billingDetails = await getUserBillingDetails();

                const confirmParams = {
                    elements,
                    confirmParams: {
                        return_url: window.location.href,
                        payment_method_data: {
                            billing_details: billingDetails
                        },
                        expand: ['payment_method']
                    }
                };
                
                // Log the complete confirmParams for debugging
                console.log('BOCS DEBUG - Full confirmSetup parameters:', JSON.stringify(confirmParams, null, 2));

                try {
                    console.log('Sending confirmSetup request to Stripe...');
                    const { error, setupIntent } = await stripe.confirmSetup(confirmParams);
                    
                    if (error) {
                        console.error('Stripe confirmation error:', error);
                        throw error;
                    }
                    
                    console.log('Stripe confirmation response:', setupIntent);
                    
                    // Check if additional actions are required (like 3D Secure)
                    if (setupIntent && setupIntent.status === 'requires_action') {
                        console.log('Setup requires additional action. Redirecting...');
                        // The confirmSetup will handle the redirect automatically
                        return;
                    }
                    
                    // If we got here without a redirect, the setup was successful
                    if (setupIntent && setupIntent.status === 'succeeded') {
                        console.log('Setup successful!');
                        // You can handle success here if not redirected
                        window.location.href = window.location.href.split('?')[0] + '?payment_updated=success';
                        return;
                    }
                    
                    console.log('Stripe confirmation successful, waiting for redirect...');
                } catch (error) {
                    console.error('Error confirming Stripe setup:', error);
                    
                    // Display detailed error nicely
                    let errorMessage = error.message || 'Payment verification failed. Please try again.';
                    
                    // Add more context if it's a specific Stripe error
                    if (error.type) {
                        errorMessage += ` (Error type: ${error.type})`;
                    }
                    
                    // Display the error nicely
                    errorElement.html(`
                        <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 10px 0;">
                            <strong>Error:</strong> ${errorMessage}
                        </div>
                    `).show();
                    
                    // Re-enable the button and reset text
                    submitButton.prop('disabled', false).text('Update Payment Method');
                }
            } else {
                console.log('Using existing payment method:', selectedMethod);
                console.log('Subscription ID:', setupData.subscription_id);
                
                // Debug AJAX data
                console.log('BOCS DEBUG - AJAX request data:', {
                    url: bocsPaymentData.ajaxUrl,
                    action: 'update_subscription_payment',
                    payment_method: selectedMethod,
                    subscription_id: setupData.subscription_id,
                    nonce: bocsPaymentData.nonce
                });

                // Validate subscription ID format
                if (!setupData.subscription_id || typeof setupData.subscription_id !== 'string' || !setupData.subscription_id.match(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i)) {
                    console.error('BOCS DEBUG - Invalid subscription ID format:', setupData.subscription_id);
                }
                
                // Validate payment method format
                if (!selectedMethod || (typeof selectedMethod !== 'string' && typeof selectedMethod !== 'number')) {
                    console.error('BOCS DEBUG - Invalid payment method format:', selectedMethod);
                }
                
                try {
                    console.log('BOCS DEBUG - Starting AJAX request with these parameters:', {
                        url: bocsPaymentData.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'update_subscription_payment',
                            payment_method: selectedMethod,
                            subscription_id: setupData.subscription_id,
                            nonce: bocsPaymentData.nonce
                        }
                    });
                    
                    // Save details for debugging form submission manually if AJAX fails
                    const debugData = {
                        url: bocsPaymentData.ajaxUrl,
                        data: {
                            action: 'update_subscription_payment',
                            payment_method: selectedMethod,
                            subscription_id: setupData.subscription_id,
                            nonce: bocsPaymentData.nonce
                        }
                    };
                    console.log('BOCS DEBUG DATA DUMP:', JSON.stringify(debugData));
                    
                    // Create a debug form to test submission manually
                    const debugForm = document.createElement('form');
                    debugForm.method = 'POST';
                    debugForm.action = bocsPaymentData.ajaxUrl;
                    debugForm.target = '_blank';
                    debugForm.style.display = 'none';
                    
                    // Add fields
                    Object.entries(debugData.data).forEach(([key, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        debugForm.appendChild(input);
                    });
                    
                    // Add to page but don't submit automatically
                    document.body.appendChild(debugForm);
                    console.log('BOCS DEBUG - Created debug form for manual submission if needed');
                    
                    // Use fetch API as fallback to jQuery.ajax for better debugging
                    const fallbackFetch = fetch(bocsPaymentData.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'update_subscription_payment',
                            payment_method: selectedMethod,
                            subscription_id: setupData.subscription_id,
                            nonce: bocsPaymentData.nonce
                        })
                    }).catch(error => {
                        console.error('BOCS DEBUG - Fetch API fallback error:', error);
                    });
                    
                    const response = await jQuery.ajax({
                        url: bocsPaymentData.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'update_subscription_payment',
                            payment_method: selectedMethod,
                            subscription_id: setupData.subscription_id,
                            nonce: bocsPaymentData.nonce
                        },
                        beforeSend: function(jqXHR, settings) {
                            console.log('BOCS DEBUG - Sending AJAX request with settings:', settings);
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('BOCS DEBUG - AJAX request failed:', {
                                status: jqXHR.status,
                                statusText: jqXHR.statusText,
                                responseText: jqXHR.responseText,
                                textStatus: textStatus,
                                errorThrown: errorThrown
                            });
                            
                            // Try to parse error response for more details
                            try {
                                const errorResponse = JSON.parse(jqXHR.responseText);
                                console.error('BOCS DEBUG - Parsed error response:', errorResponse);
                            } catch (parseError) {
                                console.error('BOCS DEBUG - Could not parse error response as JSON:', parseError);
                                console.error('BOCS DEBUG - Raw response text:', jqXHR.responseText);
                            }
                            
                            // Add direct error display in the UI for immediate feedback
                            errorElement.html(`
                                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 10px 0;">
                                    <strong>Debug Error (HTTP ${jqXHR.status}):</strong><br>
                                    ${errorThrown}<br>
                                    ${jqXHR.responseText ? `<details><summary>Response details</summary><pre>${jqXHR.responseText}</pre></details>` : ''}
                                </div>
                            `);
                        }
                    });

                    console.log('BOCS DEBUG - AJAX response:', response);
                    
                    if (!response.success) {
                        console.error('BOCS DEBUG - Error in response:', response.data);
                        throw new Error(response.data.message || bocsPaymentData.i18n.errorGeneric);
                    }

                    // Show success message and close modal
                    const successMessage = $(`<div class="success-message" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; text-align: center; margin-bottom: 20px;">
                        <strong>${bocsPaymentData.i18n.successUpdate}</strong>
                    </div>`);
                    
                    // Replace form with success message
                    form.fadeOut(200, function() {
                        form.parent().prepend(successMessage);
                        
                        // Add close button
                        const closeButton = $(`<button class="button" style="margin-top: 15px;">${bocsPaymentData.i18n.cancel}</button>`);
                        successMessage.append(closeButton);
                        
                        closeButton.on('click', function() {
                            jQuery('#edit-payment-method-modal').fadeOut(200);
                        });
                    });
                    
                    // Reload the page after success
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } catch (error) {
                    console.error('BOCS DEBUG - Error details:', {
                        name: error.name,
                        message: error.message,
                        stack: error.stack
                    });
                    errorElement.text(error.message);
                    submitButton.prop('disabled', false)
                               .text(bocsPaymentData.i18n.updatePaymentMethod);
                }
            }

        } catch (error) {
            console.error('BOCS DEBUG - Error details:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
            errorElement.text(error.message);
            submitButton.prop('disabled', false)
                       .text(bocsPaymentData.i18n.updatePaymentMethod);
        }
    },

    // Helper function to move buttons below the form
    moveButtonsBelowForm: function() {
        console.log('Moving buttons below form');
        const $form = jQuery('#edit-payment-method-form');
        const $buttons = $form.find('.modal-buttons, .bocs-modal-actions');
        
        // If buttons exist, move them after the payment element
        if ($buttons.length) {
            const $newPaymentMethod = jQuery('#new-payment-method');
            
            // Clone buttons and append to the end of payment method container
            const $clonedButtons = $buttons.clone(true);
            
            // Add some styling to the buttons
            $clonedButtons.css({
                'margin-top': '20px',
                'padding-top': '15px',
                'border-top': '1px solid #eee',
                'display': 'flex',
                'justify-content': 'space-between'
            });
            
            // Remove original buttons if we're going to show cloned ones
            if (jQuery('input[name="payment_method"]:checked').val() === 'new') {
                $buttons.hide();
            }
            
            // Append buttons after payment element
            $newPaymentMethod.append($clonedButtons);
            
            // Ensure the Update button submits the form
            $clonedButtons.find('button[type="submit"], button.button-primary').on('click', function(e) {
                e.preventDefault();
                $form.submit();
            });
        }
    }
};

// Initialize when document is ready
jQuery(document).ready(function($) {
    console.log('Document ready, initializing payment methods...');

    // Add modal styles to the document
    $('head').append(`
        <style>
            /* Modal styles */
            .bocs-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.4);
                justify-content: center;
                align-items: center;
            }
            
            /* Success Toast Animation */
            @keyframes fadeIn {
                from { opacity: 0; transform: translate(-50%, -20px); }
                to { opacity: 1; transform: translate(-50%, 0); }
            }
            
            .bocs-success-toast {
                animation: fadeIn 0.3s ease-out;
            }
            
            .bocs-modal-content {
                background-color: #fefefe;
                margin: 10% auto;
                padding: 20px;
                border: 1px solid #ddd;
                width: 90%;
                max-width: 500px;
                border-radius: 5px;
                position: relative;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            .bocs-modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                position: absolute;
                right: 15px;
                top: 10px;
            }
            
            .bocs-modal-close:hover,
            .bocs-modal-close:focus {
                color: black;
                text-decoration: none;
                cursor: pointer;
            }
            
            .bocs-modal-body {
                margin-top: 0;
                padding: 5px;
            }
            
            .bocs-modal .loading {
                text-align: center;
                padding: 20px;
            }
            
            .bocs-modal .error {
                color: #d63638;
                padding: 10px;
                background-color: #fef8f8;
                border-left: 4px solid #d63638;
            }
            
            .bocs-modal h3 {
                margin-top: 0;
                padding-top: 0;
                margin-bottom: 20px;
                color: #333;
                font-size: 1.5em;
            }
            
            .bocs-modal h4 {
                margin-bottom: 15px;
                color: #555;
                font-size: 1.2em;
            }
            
            .payment-method-option {
                display: block;
                margin-bottom: 10px;
                font-size: 14px;
            }
            
            .payment-method-option input[type="radio"] {
                margin-right: 8px;
            }
            
            .modal-buttons {
                display: flex;
                justify-content: space-between;
                margin-top: 20px;
                border-top: 1px solid #eee;
                padding-top: 15px;
            }
            
            .modal-buttons .button {
                padding: 8px 15px;
                font-size: 14px;
                border-radius: 4px;
                cursor: pointer;
            }
            
            .modal-buttons .cancel-update {
                background-color: #f5f5f5;
                border: 1px solid #ddd;
                color: #333;
            }
            
            .modal-buttons .update-payment-method {
                background-color: #7b68ee;
                border: 1px solid #6a5acd;
                color: white;
            }
            
            .modal-buttons .button:hover {
                opacity: 0.9;
            }
            
            .saved-payment-methods {
                margin-bottom: 20px;
            }
            
            .payment-method-error {
                padding: 10px;
                background: #fef8f8;
                border-left: 4px solid #d63638;
                margin-bottom: 15px;
            }
            
            /* Credit Card Form Styles */
            #new-payment-method {
                background: #f9f9f9;
                border-radius: 8px;
                padding: 20px;
                margin-top: 20px;
                margin-bottom: 20px;
                border: 1px solid #e0e0e0;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            
            #payment-element {
                margin-top: 15px;
                min-height: 250px;
                background: #fff;
                padding: 15px;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            
            #payment-element-errors:not(:empty) {
                display: block !important;
                margin-top: 15px;
                padding: 10px 15px;
                background-color: #fef8f8;
                border-left: 4px solid #d63638;
                color: #d63638;
                font-size: 14px;
            }
            
            /* Fix for stripes hidden elements */
            .StripeElement--webkit-autofill {
                background: transparent !important;
            }
            
            /* Make sure errors are visible */
            .stripe-error {
                display: none;
            }
            
            .stripe-error:not(:empty) {
                display: block !important;
                margin-top: 15px;
                padding: 10px 15px;
                background-color: #fef8f8;
                border-left: 4px solid #d63638;
                color: #d63638;
                font-size: 14px;
            }
            
            .payment-element-loader {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 150px;
                background: #fff;
                border-radius: 4px;
                padding: 20px;
            }
            
            .loading-spinner {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #7f54b3;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-right: 8px;
            }
            
            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }
            
            .success-message {
                background: #d4edda;
                color: #155724;
                padding: 15px;
                border-radius: 4px;
                text-align: center;
                margin: 20px 0;
                border-left: 4px solid #28a745;
            }
            
            .success-message button {
                margin-top: 15px;
            }
            
            .payment-method-error {
                padding: 10px;
                background: #fef8f8;
                border-left: 4px solid #d63638;
                margin-bottom: 15px;
            }
            
            /* Form Button States */
            button[type="submit"] {
                background: #7f54b3;
                color: #fff;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            button[type="submit"]:hover {
                background: #654497;
            }
            
            button[type="submit"]:disabled {
                background: #b5b5b5;
                cursor: not-allowed;
            }
            
            /* Stripe Element Focus State */
            .StripeElement--focus {
                box-shadow: 0 0 0 1px rgba(50, 151, 211, 0.3), 0 1px 1px 0 rgba(0, 0, 0, 0.07), 0 0 0 4px rgba(50, 151, 211, 0.3);
                transition: all 150ms ease;
            }
            
            /* Responsive Styles */
            @media screen and (max-width: 480px) {
                .bocs-modal-content {
                    width: 95%;
                    margin: 5% auto;
                    padding: 15px;
                }
                
                .modal-buttons {
                    flex-direction: column;
                    gap: 10px;
                }
                
                .modal-buttons button {
                    width: 100%;
                }
            }
            
            /* Form Payment Actions Styles */
            .form-payment-actions {
                margin-top: 25px !important;
                padding-top: 15px !important;
                border-top: 1px solid #e0e0e0 !important;
                display: flex !important;
                justify-content: space-between !important;
                width: 100% !important;
                gap: 15px !important;
            }
            
            .form-payment-actions .button {
                padding: 10px 20px !important;
                border-radius: 5px !important;
                font-weight: 500 !important;
                font-size: 15px !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
                min-width: 120px !important;
                text-align: center !important;
            }
            
            .form-payment-actions .cancel-button {
                background-color: #f5f5f5 !important;
                border: 1px solid #ddd !important;
                color: #333 !important;
            }
            
            .form-payment-actions .cancel-button:hover {
                background-color: #e8e8e8 !important;
            }
            
            .form-payment-actions .button-primary,
            .form-payment-actions .submit-payment {
                background-color: #7f54b3 !important;
                border: 1px solid #7f54b3 !important;
                color: white !important;
                font-weight: 600 !important;
            }
            
            .form-payment-actions .button-primary:hover,
            .form-payment-actions .submit-payment:hover {
                background-color: #6c4999 !important;
                border-color: #6c4999 !important;
            }
            
            /* Fix for form field heights */
            .StripeElement {
                min-height: 40px !important;
                padding: 10px 12px !important;
                background-color: white !important;
                border-radius: 4px !important;
            }
            
            /* Payment form styling */
            #new-payment-method {
                background: #f9f9f9 !important;
                border-radius: 8px !important;
                padding: 25px !important;
                margin-top: 20px !important;
                margin-bottom: 20px !important;
                border: 1px solid #e0e0e0 !important;
                box-shadow: 0 3px 10px rgba(0,0,0,0.05) !important;
            }
            
            #payment-element {
                margin-top: 15px !important;
                min-height: 250px !important;
                background: #fff !important;
                padding: 20px !important;
                border-radius: 6px !important;
                border: 1px solid #e5e5e5 !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
            }
            
            @media (max-width: 480px) {
                .form-payment-actions {
                    flex-direction: column-reverse !important;
                }
                
                .form-payment-actions .button {
                    width: 100% !important;
                    margin-bottom: 10px !important;
                }
            }
        </style>
    `);

    // Create modal container if it doesn't exist
    if (!$('#payment-method-modal').length) {
        $('body').append(`
            <div id="payment-method-modal" style="display:none;" class="bocs-modal">
                <div class="bocs-modal-content">
                    <span class="bocs-modal-close">&times;</span>
                    <div class="bocs-modal-body">
                        <p class="loading">${bocsPaymentData.i18n.loading || 'Loading payment methods...'}</p>
                    </div>
                </div>
            </div>
        `);
        
        // Add close button handler
        $('#payment-method-modal').find('.bocs-modal-close').on('click', function() {
            $('#payment-method-modal').hide();
            // Clear modal content except for loading message
            $('#payment-method-modal').find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
        });
        
        // Close modal when clicking outside
        $(window).on('click', function(e) {
            if ($(e.target).is($('#payment-method-modal'))) {
                $('#payment-method-modal').hide();
                // Clear modal content except for loading message
                $('#payment-method-modal').find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
            }
        });
    }

    // Attach click handler to Edit Payment Method buttons - use multiple binding approaches for reliability
    // Method 1: Direct selector
    $('.edit-payment-method').on('click', function(e) {
        e.preventDefault();
        console.log('Edit payment method clicked (direct binding)');
        handleEditPaymentMethod(e, $(this));
    });
    
    // Method 2: Delegated binding
    $('body').on('click', '.edit-payment-method', function(e) {
        e.preventDefault();
        console.log('Edit payment method clicked (delegated binding)');
        handleEditPaymentMethod(e, $(this));
    });

    function handleEditPaymentMethod(e, button) {
        e.preventDefault();
        e.stopPropagation();
        
        // Get subscription ID from the button's data attribute
        var subscriptionId = button.data('subscription-id');
        console.log('Button clicked! Subscription ID:', subscriptionId);
        
        if (!subscriptionId) {
            console.error('No subscription ID provided');
            return;
        }
        
        // Get modal reference
        var $modal = $('#payment-method-modal');
        console.log('Modal element:', $modal.length ? 'found' : 'not found');
        
        // Show modal with loading message
        $modal.find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
        
        // Show the modal
        $modal.css('display', 'flex');
        
        // Regular AJAX request flow
        console.log('Sending AJAX request...');
        $.ajax({
            url: bocsPaymentData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_subscription_payment_method_direct',
                nonce: bocsPaymentData.nonce,
                subscription_id: subscriptionId
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    // Replace loading message with payment methods
                    $modal.find('.bocs-modal-body').html(response.data.html);
                    
                    // Add form submission handler
                    $modal.find('form').on('submit', async function(e) {
                        e.preventDefault();
                        console.log('Form submitted');
                        
                        const form = $(this);
                        const submitButton = form.find('button[type="submit"]');
                        const errorElement = $('#payment-element-errors');
                        const selectedMethod = form.find('input[name="payment_method"]:checked').val();
                        
                        console.log('Selected payment method for submission:', selectedMethod);
                        
                        try {
                            submitButton.prop('disabled', true)
                                .html('<span class="loading-spinner"></span> Processing...');
                            
                            if (selectedMethod === 'new') {
                                console.log('Processing new payment method submission');
                                
                                // Ensure Stripe and elements are available
                                if (!stripe || !elements) {
                                    console.error('Stripe or elements not initialized:', { stripe, elements });
                                    
                                    // Try to reinitialize Stripe if it's available in the global scope
                                    if (typeof Stripe !== 'undefined' && window.setupData && window.setupData.publishable_key) {
                                        console.log('Attempting to reinitialize Stripe');
                                        stripe = Stripe(window.setupData.publishable_key);
                                        
                                        // If we still don't have elements but have client_secret, try to create them
                                        if (!elements && window.setupData.client_secret) {
                                            elements = stripe.elements({
                                                clientSecret: window.setupData.client_secret
                                            });
                                        }
                                    }
                                    
                                    // If reinitialization failed, throw error
                                    if (!stripe || !elements) {
                                        throw new Error('Stripe has not been properly initialized. Please reload the page and try again.');
                                    }
                                }
                                
                                // Try multiple ways to get the subscription ID
                                let subscriptionId = null;
                                
                                // First check window.setupData
                                if (window.setupData && window.setupData.subscription_id) {
                                    subscriptionId = window.setupData.subscription_id;
                                    console.log('Using subscription ID from window.setupData:', subscriptionId);
                                } 
                                // Next try data attribute on form
                                else if (form.attr('data-subscription-id')) {
                                    subscriptionId = form.attr('data-subscription-id');
                                    console.log('Using subscription ID from form data attribute:', subscriptionId);
                                }
                                // Lastly, try the button attribute that opened the modal
                                else if ($('.edit-payment-method[data-subscription-id]').length) {
                                    subscriptionId = $('.edit-payment-method[data-subscription-id]').data('subscription-id');
                                    console.log('Using subscription ID from edit button:', subscriptionId);
                                }
                                // Try the global variable we set when opening the modal
                                else if (window.currentSubscriptionId) {
                                    subscriptionId = window.currentSubscriptionId;
                                    console.log('Using subscription ID from global variable:', subscriptionId);
                                }
                                
                                // Ensure we have the subscription ID
                                if (!subscriptionId) {
                                    console.error('Missing subscription data. Could not find subscription ID from any source.');
                                    throw new Error('Missing subscription data. Please try again or reload the page.');
                                }
                                
                                // Store subscription ID for the redirect handler
                                sessionStorage.setItem('bocs_subscription_id', subscriptionId);
                                document.cookie = 'bocs_subscription_id=' + subscriptionId + '; path=/; max-age=86400';
                                
                                console.log('Confirming Stripe setup with subscription ID:', subscriptionId);
                                
                                // Confirm the setup with error handling
                                try {
                                    // Clear any existing errors first
                                    errorElement.text('').hide();
                                    
                                    // Verify we have all the required components
                                    if (!stripe) {
                                        throw new Error('Stripe is not initialized properly.');
                                    }
                                    
                                    if (!elements) {
                                        throw new Error('Stripe Elements is not initialized properly.');
                                    }
                                    
                                    // Before continuing, ensure we've submitted a complete payment form
                                    const element = elements.getElement('payment');
                                    if (!element) {
                                        throw new Error('Payment element not found. Please refresh and try again.');
                                    }
                                    
                                    // We can't check if the form is complete using getValue() as it's not supported
                                    // Instead, we'll just proceed and let Stripe validation handle any issues
                                    
                                    // First we need to fetch the user's billing details
                                    const billingDetails = await getUserBillingDetails();
                                    
                                    // Add extra params to confirmParams
                                    const confirmParams = {
                                        elements,
                                        confirmParams: {
                                            return_url: window.location.href,
                                            payment_method_data: {
                                                billing_details: billingDetails
                                            },
                                            expand: ['payment_method']
                                        }
                                    };
                                    
                                    // Log the complete confirmParams for debugging
                                    console.log('BOCS DEBUG - Full confirmSetup parameters:', JSON.stringify(confirmParams, null, 2));
                                    
                                    // Clear previous error messages
                                    errorElement.hide().empty();
                                    
                                    try {
                                        console.log('Sending confirmSetup request to Stripe...');
                                        const { error, setupIntent } = await stripe.confirmSetup(confirmParams);
                                        
                                        if (error) {
                                            console.error('Stripe confirmation error:', error);
                                            throw error;
                                        }
                                        
                                        console.log('Stripe confirmation response:', setupIntent);
                                        
                                        // Check if additional actions are required (like 3D Secure)
                                        if (setupIntent && setupIntent.status === 'requires_action') {
                                            console.log('Setup requires additional action. Redirecting...');
                                            // The confirmSetup will handle the redirect automatically
                                            return;
                                        }
                                        
                                        // If we got here without a redirect, the setup was successful
                                        if (setupIntent && setupIntent.status === 'succeeded') {
                                            console.log('Setup successful!');
                                            // You can handle success here if not redirected
                                            window.location.href = window.location.href.split('?')[0] + '?payment_updated=success';
                                            return;
                                        }
                                        
                                        console.log('Stripe confirmation successful, waiting for redirect...');
                                    } catch (error) {
                                        console.error('Error confirming Stripe setup:', error);
                                        
                                        // Display detailed error nicely
                                        let errorMessage = error.message || 'Payment verification failed. Please try again.';
                                        
                                        // Add more context if it's a specific Stripe error
                                        if (error.type) {
                                            errorMessage += ` (Error type: ${error.type})`;
                                        }
                                        
                                        // Display the error nicely
                                        errorElement.html(`
                                            <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 10px 0;">
                                                <strong>Error:</strong> ${errorMessage}
                                            </div>
                                        `).show();
                                        
                                        // Re-enable the button and reset text
                                        submitButton.prop('disabled', false).text('Update Payment Method');
                                    }
                                } catch (error) {
                                    console.error('Error confirming Stripe setup:', error);
                                    
                                    // Detailed error display
                                    let errorMessage = error.message || 'Payment verification failed. Please try again.';
                                    
                                    // Add more context if it's a specific Stripe error
                                    if (error.type) {
                                        errorMessage += ` (Error type: ${error.type})`;
                                    }
                                    
                                    // Display the error nicely
                                    errorElement.html(`
                                        <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 10px 0;">
                                            <strong>Error:</strong> ${errorMessage}
                                        </div>
                                    `).show();
                                    
                                    // Re-enable the button and reset text
                                    submitButton.prop('disabled', false).text('Update Payment Method');
                                }
                            } else {
                                console.log('Processing existing payment method selection:', selectedMethod);
                                
                                // Handle existing payment method selection
                                $.ajax({
                                    url: bocsPaymentData.ajaxUrl,
                                    type: 'POST',
                                    data: {
                                        action: 'update_subscription_payment',
                                        nonce: bocsPaymentData.nonce,
                                        payment_method: selectedMethod,
                                        subscription_id: subscriptionId
                                    },
                                    success: function(response) {
                                        console.log('Payment update response:', response);
                                        
                                        if (response.success) {
                                            // Show success message
                                            form.html(`
                                                <div class="success-message">
                                                    <p><strong>Payment method updated successfully!</strong></p>
                                                    <button type="button" class="button close-modal">Close</button>
                                                </div>
                                            `);
                                            
                                            // Add handler for close button
                                            form.find('.close-modal').on('click', function() {
                                                $modal.hide();
                                                // Clean up Stripe Elements if they exist
                                                if (paymentElement) {
                                                    paymentElement.destroy();
                                                    paymentElement = null;
                                                }
                                            });
                                            
                                            // Auto close modal after 2 seconds
                                            setTimeout(() => {
                                                $modal.hide();
                                                
                                                // Show a success toast at the top of the page
                                                const successToast = jQuery(`<div class="bocs-success-toast" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; background-color: #d4edda; color: #155724; padding: 15px 20px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 90%; width: 400px; text-align: center; animation: fadeIn 0.3s ease-in-out;">
                                                        <p style="margin: 0; font-weight: 500;"><span style="margin-right: 8px;">✅</span> Payment method updated successfully</p>
                                                    </div>`);
                                                    
                                                    // Add to body and fade out after a few seconds
                                                    jQuery('body').append(successToast);
                                                    
                                                    // Fade out and remove after 5 seconds
                                                    setTimeout(() => {
                                                        successToast.fadeOut(300, function() {
                                                            successToast.remove();
                                                        });
                                                    }, 5000);
                                            }, 2000);
                                        } else {
                                            console.error('Error updating payment method:', response.data?.message);
                                            errorElement.text(response.data?.message || 'Failed to update payment method.').show();
                                            // Ensure error is visible with inline styling
                                            errorElement.css({
                                                'display': 'block',
                                                'color': '#d63638',
                                                'margin-top': '15px',
                                                'padding': '10px 15px',
                                                'background-color': '#fef8f8',
                                                'border-left': '4px solid #d63638'
                                            });
                                            submitButton.prop('disabled', false).text('Update Payment Method');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('AJAX Error updating payment method:', error);
                                        errorElement.text('Error updating payment method. Please try again.').show();
                                        // Ensure error is visible with inline styling
                                        errorElement.css({
                                            'display': 'block',
                                            'color': '#d63638',
                                            'margin-top': '15px',
                                            'padding': '10px 15px',
                                            'background-color': '#fef8f8',
                                            'border-left': '4px solid #d63638'
                                        });
                                        submitButton.prop('disabled', false).text('Update Payment Method');
                                    }
                                });
                            }
                        } catch (error) {
                            console.error('Error in form submission:', error);
                            errorElement.text(error.message || 'An unexpected error occurred. Please try again.').show();
                            // Ensure error is visible with inline styling
                            errorElement.css({
                                'display': 'block',
                                'color': '#d63638',
                                'margin-top': '15px',
                                'padding': '10px 15px',
                                'background-color': '#fef8f8',
                                'border-left': '4px solid #d63638'
                            });
                            submitButton.prop('disabled', false).text('Update Payment Method');
                        }
                    });
                    
                    // Add handler for radio button changes
                    $modal.find('input[name="payment_method"]').on('change', function() {
                        const selectedValue = $(this).val();
                        console.log('Selected payment method:', selectedValue);
                        
                        if (selectedValue === 'new') {
                            // First check if the container exists
                            let $paymentContainer = $('#new-payment-method');
                            
                            // If the container doesn't exist, create it
                            if ($paymentContainer.length === 0) {
                                console.log('Creating payment container...');
                                const containerHTML = `
                                    <div id="new-payment-method" style="background: #f9f9f9; border-radius: 8px; padding: 20px; margin-top: 20px; margin-bottom: 20px; border: 1px solid #e0e0e0;">
                                        <h4 style="margin-top: 0;">Add a new card</h4>
                                        <div id="payment-element">
                                            <div class="payment-element-loader">
                                                <div class="loading-spinner"></div>
                                                <p style="margin-left: 10px;">Loading payment form...</p>
                                            </div>
                                        </div>
                                        <div id="payment-element-errors" class="stripe-error"></div>
                                    </div>
                                `;
                                
                                // Append the container to the form
                                $(this).closest('form').append(containerHTML);
                                $paymentContainer = $('#new-payment-method');
                                
                                // Hide the original buttons when showing the payment form
                                const $form = $(this).closest('form');
                                const $buttons = $form.find('.modal-buttons, .bocs-modal-actions');
                                $buttons.hide();
                                
                                // Add action buttons to the bottom of our container
                                const $actionButtons = jQuery(`
                                    <div class="form-payment-actions">
                                        <button type="button" class="button cancel-button">Cancel</button>
                                        <button type="submit" class="button button-primary submit-payment">Update Payment Method</button>
                                    </div>
                                `);
                                
                                $paymentContainer.append($actionButtons);
                                
                                // Add click handlers
                                $actionButtons.find('.cancel-button').on('click', function() {
                                    // Hide payment method container
                                    $paymentContainer.hide();
                                    
                                    // Reset radio selection to first option
                                    $form.find('input[name="payment_method"]:first').prop('checked', true);
                                    
                                    // Show original action buttons
                                    $buttons.show();
                                    
                                    // Clean up Stripe Elements if they exist
                                    if (paymentElement) {
                                        try {
                                            paymentElement.destroy();
                                            paymentElement = null;
                                        } catch (e) {
                                            console.error('Error destroying payment element:', e);
                                        }
                                    }
                                    
                                    // Clear any error messages
                                    $paymentContainer.find('#payment-element-errors').text('').hide();
                                });
                                
                                // Submit handler for the form
                                $actionButtons.find('.submit-payment').on('click', function(e) {
                                    e.preventDefault();
                                    console.log('Submit button clicked');
                                    try {
                                        $form.submit();
                                    } catch (error) {
                                        console.error('Error submitting form:', error);
                                        alert('There was an error processing your payment. Please try again.');
                                    }
                                });
                            } else {
                                // Just show the existing container
                                $paymentContainer.show();
                            }
                            
                            // Initialize Stripe for new payment method
                            console.log('Initializing Stripe for new payment method...');
                            // Get a reference to the form for use in the AJAX success callback
                            const formElement = $(this).closest('form');
                            
                            $.ajax({
                                url: bocsPaymentData.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'bocs_get_stripe_setup',
                                    nonce: bocsPaymentData.nonce,
                                    subscription_id: subscriptionId
                                },
                                success: function(setupResponse) {
                                    console.log('Stripe setup response:', setupResponse);
                                    if (setupResponse.success) {
                                        const setupData = setupResponse.data;
                                        
                                        // Store setup data globally 
                                        window.setupData = setupData;
                                        console.log('Stored setup data:', window.setupData);
                                        
                                        // Store subscription ID directly on form as data attribute for backup
                                        formElement.attr('data-subscription-id', setupData.subscription_id);
                                        
                                        // Ensure Stripe is loaded before initializing
                                        ensureStripeLoaded(() => {
                                            // Initialize Stripe if not already done
                                            if (!stripe) {
                                                console.log('Initializing Stripe with key:', setupData.publishable_key);
                                                try {
                                                    stripe = Stripe(setupData.publishable_key);
                                                } catch (error) {
                                                    console.error('Error initializing Stripe:', error);
                                                    jQuery('#payment-element-errors').text('Failed to initialize payment form: ' + error.message).show();
                                                    return;
                                                }
                                            }
                                            
                                            // Create Elements instance
                                            console.log('Creating Stripe Elements with client secret');
                                            try {
                                                elements = stripe.elements({
                                                    clientSecret: setupData.client_secret,
                                                    appearance: {
                                                        theme: 'stripe',
                                                        variables: {
                                                            colorPrimary: '#7f54b3',
                                                            colorBackground: '#ffffff',
                                                            colorText: '#30313d',
                                                            colorDanger: '#df1b41',
                                                            fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                                                            spacingUnit: '4px',
                                                            borderRadius: '4px'
                                                        }
                                                    }
                                                });
                                            } catch (error) {
                                                console.error('Error creating Stripe Elements:', error);
                                                jQuery('#payment-element-errors').text('Failed to create payment form: ' + error.message).show();
                                                return;
                                            }
                                            
                                            // Create and mount the Payment Element
                                            if (paymentElement) {
                                                console.log('Destroying existing payment element');
                                                try {
                                                    paymentElement.destroy();
                                                } catch (error) {
                                                    console.error('Error destroying payment element:', error);
                                                }
                                            }
                                            
                                            console.log('Creating and mounting payment element');
                                            try {
                                                // Create a more robust payment element with necessary options
                                                paymentElement = elements.create('payment', {
                                                    fields: {
                                                        billingDetails: 'never'
                                                    },
                                                    terms: {
                                                        card: 'never'
                                                    },
                                                    wallets: {
                                                        applePay: 'auto',
                                                        googlePay: 'auto'
                                                    },
                                                    layout: {
                                                        type: 'tabs',
                                                        defaultCollapsed: false
                                                    }
                                                });
                                                
                                                const paymentElementContainer = document.getElementById('payment-element');
                                                if (paymentElementContainer) {
                                                    // Clear any existing content
                                                    paymentElementContainer.innerHTML = '';
                                                    // Mount the element
                                                    paymentElement.mount('#payment-element');
                                                    console.log('Payment element mounted successfully');
                                                    
                                                    // Add event listener for change events
                                                    paymentElement.on('change', (event) => {
                                                        const errorElement = document.getElementById('payment-element-errors');
                                                        if (errorElement) {
                                                            if (event.error) {
                                                                errorElement.textContent = event.error.message;
                                                                errorElement.style.display = 'block';
                                                            } else {
                                                                errorElement.textContent = '';
                                                                errorElement.style.display = 'none';
                                                            }
                                                        }
                                                    });
                                                } else {
                                                    console.error('Payment element container not found in DOM');
                                                    // Try to solve by re-creating the container
                                                    const newPaymentMethod = jQuery('#new-payment-method');
                                                    if (newPaymentMethod.length) {
                                                        newPaymentMethod.html(`
                                                            <h4 style="margin-top: 0;">Add a new card</h4>
                                                            <div id="payment-element"></div>
                                                            <div id="payment-element-errors" class="stripe-error"></div>
                                                        `);
                                                        
                                                        // Try mounting again
                                                        setTimeout(() => {
                                                            console.log('Retrying mount after recreating container');
                                                            try {
                                                                paymentElement.mount('#payment-element');
                                                                console.log('Payment element mounted on retry');
                                                                
                                                                // Move the form buttons below the payment element after it's mounted
                                                                try {
                                                                    bocsPaymentMethods.moveButtonsBelowForm();
                                                                } catch (error) {
                                                                    console.error('Error moving buttons on retry:', error);
                                                                }
                                                            } catch (mountError) {
                                                                console.error('Mount retry failed:', mountError);
                                                                const errorDiv = document.getElementById('payment-element-errors');
                                                                if (errorDiv) {
                                                                    errorDiv.textContent = 'Could not load payment form. Please try again or contact support.';
                                                                    errorDiv.style.display = 'block';
                                                                }
                                                            }
                                                        }, 100);
                                                    } else {
                                                        console.error('New payment method container not found');
                                                    }
                                                }
                                            } catch (error) {
                                                console.error('Error creating or mounting payment element:', error);
                                                jQuery('#payment-element-errors').text('Failed to display payment form: ' + error.message).show();
                                            }
                                        });
                                    } else {
                                        console.error('Failed to initialize Stripe:', setupResponse.data.message);
                                        jQuery('#payment-element-errors').text(setupResponse.data.message || 'Failed to initialize payment form.').show();
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('AJAX Error:', error);
                                    jQuery('#payment-element-errors').text('Error setting up payment form. Please try again.').show();
                                }
                            });
                        } else {
                            // Hide the payment element for existing methods
                            jQuery('#new-payment-method').hide();
                            
                            // Clean up Stripe Elements if they exist
                            if (paymentElement) {
                                paymentElement.destroy();
                                paymentElement = null;
                            }
                        }
                    });
                } else {
                    // Show error message
                    $modal.find('.bocs-modal-body').html('<p class="error">' + (response.data.message || 'Error loading payment methods') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $modal.find('.bocs-modal-body').html('<p class="error">Error loading payment methods. Please try again.</p>');
            }
        });
    }
}); 