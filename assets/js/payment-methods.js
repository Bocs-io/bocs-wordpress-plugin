let stripe = null;
let elements = null;
let setupData = null; // Store setup data globally for access in different functions
let card = null;

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
            // The server-side code will handle the completion, we just need to clean up
            // Clear session storage after redirect is handled
            setTimeout(() => {
                if (sessionStorage.getItem('bocs_subscription_id')) {
                    console.log('Cleaning up stored subscription ID');
                    sessionStorage.removeItem('bocs_subscription_id');
                    this.removeCookie('bocs_subscription_id');
                }
            }, 2000);
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
                // Clear modal content except for loading message
                $modal.find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(e) {
                if ($(e.target).is($modal)) {
                    $modal.hide();
                    // Clear modal content except for loading message
                    $modal.find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
                }
            });
        }
        
        // Get subscription ID from the button's data attribute
        var subscriptionId = $(this).data('subscription-id');
        console.log('Subscription ID:', subscriptionId);
        
        // Show modal with loading message
        $modal.find('.bocs-modal-body').html('<p class="loading">' + (bocsPaymentData.i18n.loading || 'Loading payment methods...') + '</p>');
        console.log('Modal current styles:', {
            display: $modal.css('display'),
            position: $modal.css('position'),
            zIndex: $modal.css('zIndex')
        });
        
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
                    <div id="payment-element"></div>
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
                
                const { error } = await stripe.confirmSetup({
                    elements,
                    confirmParams: {
                        return_url: window.location.href,
                    }
                });

                if (error) {
                    throw error;
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