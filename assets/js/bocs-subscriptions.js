/**
 * BOCS Subscriptions JavaScript
 * 
 * Handles the frontend functionality for the BOCS subscriptions accordion layout
 * and subscription management features.
 */

(function($) {
    'use strict';

    // Main subscription controller
    const BocsSubscriptions = {
        // Store active subscription ID
        activeSubscriptionId: null,

        // Initialize the subscription functionality
        init: function() {
            try {
                console.log('BocsSubscriptions: Initializing');
                
                // Check if the list exists
                if ($('#bocs-subscriptions-list').length === 0) {
                    console.log('BocsSubscriptions: No subscription list found in DOM');
                    return;
                }
                
                this.initAccordion();
                this.bindEvents();
                this.setupModals();
                
                console.log('BocsSubscriptions: Initialization complete');
            } catch (error) {
                console.error('BocsSubscriptions: Error during initialization', error);
            }
        },

        // Initialize the accordion functionality
        initAccordion: function() {
            try {
                console.log('BocsSubscriptions: Initializing accordion');
                
                // Ensure all accordions are closed on page load
                $('.bocs-subscription-details').hide();
                
                // Reset any active states that might be present
                $('.bocs-subscription-item').removeClass('active');
                $('.bocs-toggle-icon').removeClass('open');

                // Set up toggle functionality
                $('.bocs-subscription-header').on('click', function() {
                    try {
                        const subscriptionItem = $(this).closest('.bocs-subscription-item');
                        const details = subscriptionItem.find('.bocs-subscription-details');
                        const toggleIcon = subscriptionItem.find('.bocs-toggle-icon');
                        
                        // Toggle the details
                        details.slideToggle(300);
                        
                        // Toggle active class and icon
                        subscriptionItem.toggleClass('active');
                        subscriptionItem.toggleClass('open');
                        toggleIcon.toggleClass('open');
                        details.toggleClass('active');

                        // Store the active subscription ID
                        if (subscriptionItem.hasClass('active')) {
                            BocsSubscriptions.activeSubscriptionId = subscriptionItem.data('subscription-id');
                            console.log('BocsSubscriptions: Activated subscription', BocsSubscriptions.activeSubscriptionId);
                        }

                        // Close other open items
                        $('.bocs-subscription-item').not(subscriptionItem).removeClass('active')
                            .removeClass('open')
                            .find('.bocs-subscription-details').slideUp(300).removeClass('active');
                        $('.bocs-subscription-item').not(subscriptionItem)
                            .find('.bocs-toggle-icon').removeClass('open');
                    } catch (error) {
                        console.error('BocsSubscriptions: Error in accordion click handler', error);
                    }
                });
                
                console.log('BocsSubscriptions: Accordion initialized');
            } catch (error) {
                console.error('BocsSubscriptions: Error initializing accordion', error);
            }
        },

        // Bind event handlers
        bindEvents: function() {
            // Edit buttons
            $('.edit-schedule').on('click', this.handlers.editSchedule);
            $('.edit-frequency').on('click', this.handlers.editFrequency);
            $('.edit-address').on('click', this.handlers.editAddress);
            $('.edit-payment').on('click', this.handlers.editPayment);
            
            // Action buttons
            $('.early-renewal').on('click', this.handlers.earlyRenewal);
            // $('.edit-contents').on('click', this.handlers.editContents);
            // $('.change-box').on('click', this.handlers.changeBox);
            
            // Form submissions
            $('#edit-schedule-form').on('submit', this.handlers.saveSchedule);
            $('#edit-frequency-form').on('submit', this.handlers.saveFrequency);
            $('#edit-address-form').on('submit', this.handlers.saveAddress);
            $('#edit-payment-form').on('submit', this.handlers.savePayment);
        },

        // Set up modal functionality
        setupModals: function() {
            // Close modal when clicking the X
            $('.bocs-modal-close').on('click', function() {
                $(this).closest('.bocs-modal').hide();
            });
            
            // Close modal when clicking cancel button
            $('.bocs-modal .cancel').on('click', function() {
                $(this).closest('.bocs-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('bocs-modal')) {
                    $('.bocs-modal').hide();
                }
            });
        },

        // API helper functions
        api: {
            // Make an API request with proper headers
            request: async function(endpoint, method = 'GET', data = null) {
                try {
                    const url = `${bocsSubscriptionsData.apiUrl}${endpoint}`;
                    
                    const options = {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Store': bocsSubscriptionsData.headers.store,
                            'Organization': bocsSubscriptionsData.headers.organization,
                            'Authorization': bocsSubscriptionsData.headers.authorization
                        }
                    };
                    
                    if (data && (method === 'POST' || method === 'PUT')) {
                        options.body = JSON.stringify(data);
                    }
                    
                    const response = await fetch(url, options);
                    const responseData = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(responseData.message || 'API request failed');
                    }
                    
                    return responseData;
                } catch (error) {
                    console.error('API Request Error:', error);
                    BocsSubscriptions.helpers.showNotification(error.message || 'Request failed', 'error');
                    throw error;
                }
            },
            
            // Update subscription frequency
            updateFrequency: function(subscriptionId, frequencyData) {
                return this.request(`subscriptions/${subscriptionId}`, 'PUT', { 
                    frequency: frequencyData 
                });
            },
            
            // Update next payment date
            updateSchedule: function(subscriptionId, nextPaymentDate) {
                return this.request(`subscriptions/${subscriptionId}`, 'PUT', { 
                    nextPaymentDateGmt: nextPaymentDate 
                });
            },
            
            // Update delivery address
            updateAddress: function(subscriptionId, address) {
                return this.request(`subscriptions/${subscriptionId}`, 'PUT', address);
            },
            
            // Update payment method
            updatePaymentMethod: function(subscriptionId, paymentMethodId) {
                return this.request(`subscriptions/${subscriptionId}/payment`, 'PUT', { 
                    payment_method_id: paymentMethodId 
                });
            },
            
            // Cancel subscription
            cancelSubscription: function(subscriptionId) {
                return this.request(`subscriptions/${subscriptionId}/cancel`, 'PUT');
            },
            
            // Process early renewal
            earlyRenewal: function(subscriptionId) {
                return this.request(`subscriptions/${subscriptionId}/renew`, 'POST');
            }
        },

        // Event handlers
        handlers: {
            // Schedule edit handler
            editSchedule: function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const subscriptionItem = $(this).closest('.bocs-subscription-item');
                const subscriptionId = subscriptionItem.data('subscription-id');
                
                // Get current next payment date and populate the form
                const nextPaymentText = subscriptionItem.find('.bocs-section:first .bocs-section-line:first').text();
                const nextPaymentDate = nextPaymentText.replace('Next payment date: ', '');
                
                // Format date for input (YYYY-MM-DD)
                const dateObj = new Date(nextPaymentDate);
                const formattedDate = dateObj.toISOString().split('T')[0];
                
                $('#next-payment-date').val(formattedDate);
                
                // Show the modal
                $('#bocs-edit-schedule-modal').show();
                
                // Store the subscription ID
                BocsSubscriptions.activeSubscriptionId = subscriptionId;
            },
            
            // Save schedule changes
            saveSchedule: async function(e) {
                e.preventDefault();
                
                const subscriptionId = BocsSubscriptions.activeSubscriptionId;
                const nextPaymentDate = $('#next-payment-date').val();
                
                if (!subscriptionId || !nextPaymentDate) {
                    BocsSubscriptions.helpers.showNotification('Missing required information', 'error');
                    return;
                }
                
                try {
                    BocsSubscriptions.helpers.showNotification('Updating schedule...', 'loading');
                    
                    const response = await BocsSubscriptions.api.updateSchedule(subscriptionId, nextPaymentDate);
                    
                    // Update the UI
                    const formattedDate = new Date(nextPaymentDate).toLocaleDateString('en-US', {
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric'
                    });
                    
                    $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"]`)
                        .find('.bocs-section:first .bocs-section-line:first')
                        .text(`Next payment date: ${formattedDate}`);
                    
                    // Hide the modal
                    $('#bocs-edit-schedule-modal').hide();
                    
                    BocsSubscriptions.helpers.showNotification('Schedule updated successfully', 'success');
                } catch (error) {
                    BocsSubscriptions.helpers.showNotification('Failed to update schedule', 'error');
                }
            },
            
            // Frequency edit handler
            editFrequency: async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const subscriptionItem = $(this).closest('.bocs-subscription-item');
                const subscriptionId = subscriptionItem.data('subscription-id');
                
                // Find this subscription in the data
                const subscription = bocsSubscriptionsData.subscriptions.find(sub => sub.id === subscriptionId);
                
                try {
                    BocsSubscriptions.helpers.showNotification('Loading frequency options...', 'loading');
                    
                    // Get the BOCS ID from the subscription
                    const bocsId = subscription?.bocs?.id;
                    
                    if (!bocsId) {
                        throw new Error('BOCS ID not found for this subscription');
                    }
                    
                    // Fetch BOCS details to get frequency options
                    const bocsDetails = await BocsSubscriptions.api.request(`bocs/${bocsId}`, 'GET');
                    
                    // Clear existing options in the dropdown
                    $('#frequency-value').empty();
                    
                    // Check if we have adjustment options
                    if (bocsDetails?.data?.priceAdjustment?.adjustments) {
                        const adjustments = bocsDetails.data.priceAdjustment.adjustments;
                        
                        // Populate the frequency dropdown with available options
                        adjustments.forEach(adjustment => {
                            const frequencyText = adjustment.name || `${adjustment.frequency} ${adjustment.timeUnit}`;
                            $('#frequency-value').append(`<option value="${adjustment.frequency}" 
                                data-id="${adjustment.id}"
                                data-time-unit="${adjustment.timeUnit}" 
                                data-discount="${adjustment.discount}"
                                data-discount-type="${adjustment.discountType}"
                                data-scheduled-payment-date="${adjustment.scheduledPaymentDate}">
                                ${frequencyText}
                            </option>`);
                        });
                        
                        // Set up change handler for the frequency dropdown
                        $('#frequency-value').off('change').on('change', function() {
                            const selectedOption = $(this).find('option:selected');
                            const frequencyId = selectedOption.data('id');
                            const timeUnit = selectedOption.data('time-unit');
                            const discount = selectedOption.data('discount');
                            const discountType = selectedOption.data('discount-type');
                            
                            // Update hidden fields
                            $('#frequency-id').val(frequencyId);
                            $('#time-unit').val(timeUnit);
                            $('#discount').val(discount);
                            $('#discount-type').val(discountType);
                        });
                        
                        // Set current frequency as selected
                        if (subscription && subscription.frequency) {
                            // Find the matching option
                            const matchingOption = $(`#frequency-value option[data-id="${subscription.frequency.id}"]`);
                            
                            if (matchingOption.length) {
                                matchingOption.prop('selected', true);
                                
                                // Trigger change to update the hidden fields
                                $('#frequency-value').trigger('change');
                            }
                        }
                    } else {
                        // Fallback options if no adjustments are available
                        $('#frequency-value').append(`
                            <option value="1" data-time-unit="month" data-discount="0" data-discount-type="percent">1 Month</option>
                            <option value="3" data-time-unit="months" data-discount="0" data-discount-type="percent">3 Months</option>
                            <option value="6" data-time-unit="months" data-discount="0" data-discount-type="percent">6 Months</option>
                            <option value="12" data-time-unit="months" data-discount="0" data-discount-type="percent">12 Months</option>
                        `);
                        
                        // Set up change handler
                        $('#frequency-value').off('change').on('change', function() {
                            const selectedOption = $(this).find('option:selected');
                            const timeUnit = selectedOption.data('time-unit');
                            const discount = selectedOption.data('discount');
                            const discountType = selectedOption.data('discount-type');
                            
                            // Update hidden fields
                            $('#time-unit').val(timeUnit);
                            $('#discount').val(discount);
                            $('#discount-type').val(discountType);
                        });
                        
                        if (subscription && subscription.frequency) {
                            // Set the value and trigger change
                            $('#frequency-value').val(subscription.frequency.frequency || '1').trigger('change');
                        }
                    }
                    
                    // Hide the loading notification
                    $('.bocs-notification').remove();
                    
                    // Show the modal
                    $('#bocs-edit-frequency-modal').show();
                    
                    // Store the subscription ID
                    BocsSubscriptions.activeSubscriptionId = subscriptionId;
                    
                } catch (error) {
                    console.error('Error fetching frequency options:', error);
                    BocsSubscriptions.helpers.showNotification('Error loading frequency options: ' + error.message, 'error');
                }
            },
            
            // Save frequency changes
            saveFrequency: async function(e) {
                e.preventDefault();
                
                const subscriptionId = BocsSubscriptions.activeSubscriptionId;
                const selectedOption = $('#frequency-value option:selected');
                
                // Get data from the selected option's data attributes
                const frequencyValue = selectedOption.val();
                const frequencyId = selectedOption.data('id') || $('#frequency-id').val();
                const timeUnit = selectedOption.data('time-unit') || $('#time-unit').val();
                const discount = selectedOption.data('discount') || $('#discount').val() || '0';
                const discountType = selectedOption.data('discount-type') || $('#discount-type').val() || 'percent';
                const scheduledPaymentDate = selectedOption.data('scheduled-payment-date') || 3;
                
                if (!subscriptionId || !timeUnit || !frequencyValue) {
                    BocsSubscriptions.helpers.showNotification('Missing required information', 'error');
                    return;
                }
                
                try {
                    BocsSubscriptions.helpers.showNotification('Updating frequency...', 'loading');
                    
                    // Construct the frequency object
                    const frequencyData = {
                        id: frequencyId,
                        timeUnit: timeUnit,
                        frequency: parseInt(frequencyValue),
                        discount: parseInt(discount),
                        discountType: discountType,
                        scheduledPaymentDate: parseInt(scheduledPaymentDate)
                    };
                    
                    const response = await BocsSubscriptions.api.updateFrequency(subscriptionId, frequencyData);
                    
                    // Get the display text directly from the selected option
                    let frequencyText = selectedOption.text().trim();
                    
                    // If we don't have display text from the option, construct it
                    if (!frequencyText || frequencyText === '') {
                        frequencyText = `EVERY ${frequencyValue}`;
                        if (timeUnit === 'month' && frequencyValue > 1) {
                            frequencyText += ' months';
                        } else if (timeUnit === 'month') {
                            frequencyText += ' month';
                        } else if (timeUnit === 'months') {
                            frequencyText += ' months';
                        } else if (timeUnit === 'week' && frequencyValue > 1) {
                            frequencyText += ' weeks';
                        } else if (timeUnit === 'week') {
                            frequencyText += ' week';
                        } else if (timeUnit === 'weeks') {
                            frequencyText += ' weeks';
                        }
                    }
                    
                    // Add discount information if available
                    if (parseInt(discount) > 0 && !frequencyText.includes('Discount')) {
                        frequencyText += ` (${discount}% Discount)`;
                    }
                    
                    // Update the frequency display in the subscription section
                    $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"]`)
                        .find('.bocs-section:eq(1) .bocs-section-line')
                        .text(frequencyText);
                    
                    // Update header price frequency display
                    // For consistency, reuse the display text but format for the header
                    const frequencyFormatted = frequencyText.replace(/\(.+\)/, '').trim();
                    
                    const priceElement = $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"]`)
                        .find('.bocs-subscription-price');
                    
                    const priceText = priceElement.text();
                    const price = priceText.split(' ')[0]; // Get the price part
                    
                    priceElement.text(`${price} ${frequencyFormatted}`);
                    
                    // Hide the modal
                    $('#bocs-edit-frequency-modal').hide();
                    
                    BocsSubscriptions.helpers.showNotification('Frequency updated successfully', 'success');
                } catch (error) {
                    console.error('Error updating frequency:', error);
                    BocsSubscriptions.helpers.showNotification('Failed to update frequency: ' + (error.message || 'Unknown error'), 'error');
                }
            },
            
            // Address edit handler
            editAddress: function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const subscriptionItem = $(this).closest('.bocs-subscription-item');
                const subscriptionId = subscriptionItem.data('subscription-id');
                
                // Find this subscription in the data
                const subscription = bocsSubscriptionsData.subscriptions.find(sub => sub.id === subscriptionId);
                
                if (subscription) {
                    // Try to populate with shipping address first
                    if (subscription.shipping && subscription.shipping.address1) {
                        $('#address').val(subscription.shipping.address1);
                        $('#city').val(subscription.shipping.city || '');
                        $('#state').val(subscription.shipping.state || '');
                        $('#postcode').val(subscription.shipping.postcode || '');
                    } 
                    // Fallback to billing address if shipping is empty
                    else if (subscription.billing && subscription.billing.address1) {
                        $('#address').val(subscription.billing.address1);
                        $('#city').val(subscription.billing.city || '');
                        $('#state').val(subscription.billing.state || '');
                        $('#postcode').val(subscription.billing.postcode || '');
                    }
                }
                
                // Show the modal
                $('#bocs-edit-address-modal').show();
                
                // Store the subscription ID
                BocsSubscriptions.activeSubscriptionId = subscriptionId;
            },
            
            // Save address changes
            saveAddress: async function(e) {
                e.preventDefault();
                
                const subscriptionId = BocsSubscriptions.activeSubscriptionId;
                const address = $('#address').val();
                const city = $('#city').val();
                const state = $('#state').val();
                const postcode = $('#postcode').val();
                
                if (!subscriptionId || !address || !city || !state || !postcode) {
                    BocsSubscriptions.helpers.showNotification('Please fill in all address fields', 'error');
                    return;
                }
                
                try {
                    BocsSubscriptions.helpers.showNotification('Updating address...', 'loading');
                    
                    // Format address data properly as shipping object
                    const addressData = {
                        shipping: {
                            address1: address,
                            city: city,
                            state: state,
                            postcode: postcode,
                            country: 'AU' // Default to Australia
                        }
                    };
                    
                    const response = await BocsSubscriptions.api.updateAddress(subscriptionId, addressData);
                    
                    // Update the UI with formatted address
                    const formattedAddress = `${address}, ${city}, ${state} ${postcode}`;
                    
                    $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"]`)
                        .find('.bocs-section:eq(2) .bocs-section-line')
                        .text(formattedAddress);
                    
                    // Hide the modal
                    $('#bocs-edit-address-modal').hide();
                    
                    BocsSubscriptions.helpers.showNotification('Address updated successfully', 'success');
                } catch (error) {
                    BocsSubscriptions.helpers.showNotification('Failed to update address', 'error');
                }
            },
            
            // Payment method edit handler
            editPayment: function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const subscriptionItem = $(this).closest('.bocs-subscription-item');
                const subscriptionId = subscriptionItem.data('subscription-id');
                
                // Show loading state in the modal
                $('#payment-method').html('<option>Loading payment methods...</option>');
                
                // Store the subscription ID
                BocsSubscriptions.activeSubscriptionId = subscriptionId;
                
                // Fetch available payment methods
                $.ajax({
                    url: bocsSubscriptionsData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bocs_get_payment_methods',
                        nonce: bocsSubscriptionsData.nonce,
                        subscription_id: subscriptionId
                    },
                    success: function(response) {
                        if (response.success) {
                            // Clear the dropdown
                            $('#payment-method').empty();
                            
                            // Add the payment methods to the dropdown
                            if (response.data.payment_methods.length > 0) {
                                response.data.payment_methods.forEach(function(method) {
                                    const methodLabel = method.method.brand + 
                                        (method.method.last4 ? (' ending in ' + method.method.last4) : '');
                                    
                                    $('#payment-method').append(
                                        `<option value="${method.method.id}" ${method.is_default ? 'selected' : ''}>
                                            ${methodLabel}
                                        </option>`
                                    );
                                });
                                
                                // Add option to add a new payment method
                                $('#payment-method').append(
                                    '<option value="new">Add new payment method...</option>'
                                );
                            } else {
                                $('#payment-method').html(
                                    '<option value="">No saved payment methods found</option>' +
                                    '<option value="new">Add new payment method...</option>'
                                );
                            }
                            
                            // Show the modal
                            $('#bocs-edit-payment-modal').show();
                            
                            // Handle special behavior when "Add new" is selected
                            $('#payment-method').off('change').on('change', function() {
                                if ($(this).val() === 'new') {
                                    // Show Stripe card element
                                    $('#stripe-payment-element-container').show();
                                    
                                    // Initialize Stripe with setup intent
                                    $.ajax({
                                        url: bocsSubscriptionsData.ajaxUrl,
                                        type: 'POST',
                                        data: {
                                            action: 'bocs_get_stripe_setup',
                                            nonce: bocsSubscriptionsData.nonce,
                                            subscription_id: subscriptionId
                                        },
                                        success: function(setupResponse) {
                                            if (setupResponse.success) {
                                                const setupData = setupResponse.data;
                                                
                                                // Store setup data globally 
                                                window.setupData = setupData;
                                                window.currentSubscriptionId = subscriptionId;
                                                
                                                // Initialize Stripe if needed
                                                if (typeof Stripe !== 'undefined') {
                                                    if (!window.stripe) {
                                                        console.log('Initializing Stripe with key:', setupData.publishable_key);
                                                        window.stripe = Stripe(setupData.publishable_key);
                                                    }
                                                    
                                                    // Create Elements instance
                                                    window.stripeElements = window.stripe.elements({
                                                        clientSecret: setupData.client_secret,
                                                        appearance: {
                                                            theme: 'stripe',
                                                            variables: {
                                                                colorPrimary: '#38b2ac',
                                                                colorBackground: '#ffffff',
                                                                colorText: '#30313d',
                                                                colorDanger: '#df1b41',
                                                                fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                                                                spacingUnit: '4px',
                                                                borderRadius: '4px'
                                                            }
                                                        }
                                                    });
                                                    
                                                    // Create and mount the Payment Element
                                                    if (window.cardElement) {
                                                        window.cardElement.destroy();
                                                    }
                                                    
                                                    // Create a payment element
                                                    window.cardElement = window.stripeElements.create('payment', {
                                                        fields: {
                                                            billingDetails: 'never'
                                                        },
                                                        wallets: {
                                                            applePay: 'auto',
                                                            googlePay: 'auto'
                                                        }
                                                    });
                                                    
                                                    // Mount the element
                                                    window.cardElement.mount('#card-element');
                                                    
                                                    // Add event listener for change events
                                                    window.cardElement.on('change', function(event) {
                                                        const displayError = document.getElementById('card-errors');
                                                        if (event.error) {
                                                            displayError.textContent = event.error.message;
                                                            displayError.style.display = 'block';
                                                        } else {
                                                            displayError.textContent = '';
                                                            displayError.style.display = 'none';
                                                        }
                                                    });
                                                } else {
                                                    console.error('Stripe.js not available');
                                                    $('#card-errors').text('Payment system not available. Please try again later.').show();
                                                }
                                            } else {
                                                console.error('Failed to initialize Stripe:', setupResponse.data.message);
                                                $('#card-errors').text(setupResponse.data.message || 'Could not initialize payment form.').show();
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('AJAX Error:', error);
                                            $('#card-errors').text('Could not initialize payment form. Please try again.').show();
                                        }
                                    });
                                } else {
                                    // Hide Stripe card element for existing methods
                                    $('#stripe-payment-element-container').hide();
                                    
                                    // Clean up any existing elements
                                    if (window.cardElement) {
                                        window.cardElement.destroy();
                                        window.cardElement = null;
                                    }
                                }
                            });
                        } else {
                            $('#payment-method').html(
                                '<option value="">Error loading payment methods</option>'
                            );
                            BocsSubscriptions.helpers.showNotification(
                                response.data.message || 'Error loading payment methods', 
                                'error'
                            );
                        }
                    },
                    error: function() {
                        $('#payment-method').html(
                            '<option value="">Error loading payment methods</option>'
                        );
                        BocsSubscriptions.helpers.showNotification(
                            'Failed to load payment methods', 'error'
                        );
                    }
                });
            },
            
            // Save payment method changes
            savePayment: async function(e) {
                e.preventDefault();
                
                const subscriptionId = BocsSubscriptions.activeSubscriptionId;
                const paymentMethodId = $('#payment-method').val();
                const submitButton = $(e.target).find('button[type="submit"]');
                const errorElement = $('#card-errors');
                
                if (!subscriptionId) {
                    BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                    return;
                }
                
                submitButton.prop('disabled', true).html('Processing...');
                
                try {
                    // For new payment method with Stripe
                    if (paymentMethodId === 'new') {
                        if (!window.stripe || !window.stripeElements) {
                            throw new Error('Stripe is not properly initialized');
                        }
                        
                        BocsSubscriptions.helpers.showNotification('Creating payment method...', 'loading');
                        
                        // Get user billing details
                        let billingDetails = {};
                        try {
                            const resp = await $.ajax({
                                url: bocsSubscriptionsData.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'bocs_get_user_billing_details',
                                    nonce: bocsSubscriptionsData.nonce
                                }
                            });
                            
                            if (resp.success) {
                                // Ensure billing details is properly formatted for Stripe
                                billingDetails = {
                                    name: resp.data.billing_details.name || '',
                                    email: resp.data.billing_details.email || '',
                                    phone: resp.data.billing_details.phone || '',
                                    address: {
                                        line1: resp.data.billing_details.address.line1 || '',
                                        line2: resp.data.billing_details.address.line2 || '',
                                        city: resp.data.billing_details.address.city || '',
                                        state: resp.data.billing_details.address.state || '',
                                        postal_code: resp.data.billing_details.address.postal_code || '',
                                        country: resp.data.billing_details.address.country || 'AU'
                                    }
                                };
                            }
                        } catch (error) {
                            console.warn('Could not fetch billing details, using defaults:', error);
                            // Use default empty billing details
                            billingDetails = {
                                name: '',
                                email: '',
                                address: {
                                    line1: '',
                                    city: '',
                                    state: '',
                                    postal_code: '',
                                    country: 'AU'
                                }
                            };
                        }
                        
                        // Prepare the confirmation parameters
                        const confirmParams = {
                            elements: window.stripeElements,
                            confirmParams: {
                                return_url: window.location.href,
                                payment_method_data: {
                                    billing_details: billingDetails
                                }
                            }
                        };
                        
                        // Store subscription ID in session storage for retrieval after redirect
                        sessionStorage.setItem('bocs_subscription_id', subscriptionId);
                        
                        // Confirm the setup - this might redirect for 3D Secure
                        const { error, setupIntent } = await window.stripe.confirmSetup(confirmParams);
                        
                        if (error) {
                            // Handle errors from Stripe
                            throw error;
                        }
                        
                        // If we reach here without redirect, it means setup was successful
                        if (setupIntent.status === 'succeeded') {
                            // Get the payment method ID from the setup intent
                            const newPaymentMethodId = setupIntent.payment_method;
                            
                            // Use the AJAX endpoint to update the subscription
                            const response = await $.ajax({
                                url: bocsSubscriptionsData.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'bocs_update_payment_method',
                                    nonce: bocsSubscriptionsData.nonce,
                                    subscription_id: subscriptionId,
                                    payment_method_id: newPaymentMethodId,
                                    is_new_method: true
                                }
                            });
                            
                            if (!response.success) {
                                throw new Error(response.data.message || 'Failed to update subscription with new payment method');
                            }
                            
                            // Update the UI and show success message
                            BocsSubscriptions.helpers.showNotification('Payment method updated successfully', 'success');
                            
                            // Refresh the page to show updated payment methods
                            window.location.reload();
                        } else {
                            // For 'requires_action' status, the page will be redirected
                            BocsSubscriptions.helpers.showNotification('Verifying payment method...', 'loading');
                        }
                    } 
                    // For existing payment methods
                    else {
                        BocsSubscriptions.helpers.showNotification('Updating payment method...', 'loading');
                        
                        // Use the AJAX endpoint to update the payment method
                        const response = await $.ajax({
                            url: bocsSubscriptionsData.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'bocs_update_payment_method',
                                nonce: bocsSubscriptionsData.nonce,
                                subscription_id: subscriptionId,
                                payment_method_id: paymentMethodId
                            }
                        });
                        
                        if (!response.success) {
                            throw new Error(response.data.message || 'Failed to update payment method');
                        }
                        
                        // Update the payment method display in the UI
                        const selectedOption = $('#payment-method option:selected');
                        const methodLabel = selectedOption.text().trim();
                        
                        $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"]`)
                            .find('.bocs-section:eq(3) .bocs-section-line')
                            .text(methodLabel);
                        
                        // Hide the modal
                        $('#bocs-edit-payment-modal').hide();
                        
                        BocsSubscriptions.helpers.showNotification('Payment method updated successfully', 'success');
                    }
                } catch (error) {
                    console.error('Error updating payment method:', error);
                    
                    if (errorElement.length) {
                        errorElement.text(error.message || 'Payment update failed').show();
                    } else {
                        BocsSubscriptions.helpers.showNotification('Error: ' + error.message, 'error');
                    }
                } finally {
                    submitButton.prop('disabled', false).html('Save Changes');
                }
            },
            
            // Early renewal handler
            earlyRenewal: async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const subscriptionItem = $(this).closest('.bocs-subscription-item');
                const subscriptionId = subscriptionItem.data('subscription-id');
                
                if (!subscriptionId) {
                    BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                    return;
                }
                
                try {
                    BocsSubscriptions.helpers.showNotification('Processing early renewal...', 'loading');
                    
                    const response = await BocsSubscriptions.api.earlyRenewal(subscriptionId);
                    
                    BocsSubscriptions.helpers.showNotification('Early renewal successful', 'success');
                    
                    // In a real implementation, you might want to reload the page
                    // or update the UI with new subscription details
                } catch (error) {
                    BocsSubscriptions.helpers.showNotification('Failed to process early renewal', 'error');
                }
            },
            
            // Edit contents handler (placeholder)
            editContents: function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const subscriptionId = $(this).closest('.bocs-subscription-item').data('subscription-id');
                
                // This would typically redirect to a product edit page or open a modal
                BocsSubscriptions.helpers.showNotification('Edit contents functionality coming soon', 'info');
            },
            
            // Change box handler (placeholder)
            changeBox: function(e) {
                // We no longer need to prevent default since we're using links now
                // The link will navigate to the switch-bocs page
                // This handler is kept for backward compatibility
                const subscriptionId = $(this).closest('.bocs-subscription-item').data('subscription-id');
                console.log('Navigating to change box page for subscription: ' + subscriptionId);
            }
        },

        // Helper functions
        helpers: {
            // Show a notification message
            showNotification: function(message, type = 'info', duration = 3000) {
                // Remove any existing notifications
                $('.bocs-notification').remove();
                
                // Create notification element
                const notification = $('<div class="bocs-notification">').addClass(`notification-${type}`);
                
                // Add icon based on type
                let icon = '';
                switch (type) {
                    case 'success':
                        icon = '<span class="notification-icon">✓</span>';
                        break;
                    case 'error':
                        icon = '<span class="notification-icon">✕</span>';
                        break;
                    case 'loading':
                        icon = '<span class="notification-icon loading-spinner"></span>';
                        break;
                    default:
                        icon = '<span class="notification-icon">ℹ</span>';
                }
                
                notification.html(`${icon} <span class="notification-message">${message}</span>`);
                
                // Add to page
                $('body').append(notification);
                
                // Show with animation
                notification.addClass('show');
                
                // Hide after duration (except for loading)
                if (type !== 'loading') {
                    setTimeout(() => {
                        notification.removeClass('show');
                        setTimeout(() => {
                            notification.remove();
                        }, 300);
                    }, duration);
                }
                
                return notification;
            },
            
            // Format a date string
            formatDate: function(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            },
            
            // Format currency
            formatCurrency: function(amount) {
                return '$' + parseFloat(amount).toFixed(2);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        BocsSubscriptions.init();
    });

})(jQuery); 