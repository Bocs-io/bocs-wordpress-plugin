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
                        const subscriptionId = subscriptionItem.data('subscription-id');
                        
                        // Toggle the details
                        details.slideToggle(300);
                        
                        // Toggle active class and icon
                        subscriptionItem.toggleClass('active');
                        subscriptionItem.toggleClass('open');
                        toggleIcon.toggleClass('open');
                        details.toggleClass('active');

                        // Store the active subscription ID
                        if (subscriptionItem.hasClass('active')) {
                            BocsSubscriptions.activeSubscriptionId = subscriptionId;
                            console.log('BocsSubscriptions: Activated subscription', BocsSubscriptions.activeSubscriptionId);
                            
                            // Load order line items via AJAX if the subscription is opened
                            const orderComponent = subscriptionItem.find('.bocs-order-details');
                            if (orderComponent.length > 0) {
                                // Get the component ID
                                const componentId = orderComponent.attr('id');
                                
                                // Refresh the line items
                                if (typeof window.BocsOrderLineItems !== 'undefined' && window.BocsOrderLineItems.refresh) {
                                    console.log('BocsSubscriptions: Refreshing line items for', subscriptionId);
                                    window.BocsOrderLineItems.refresh(componentId, subscriptionId);
                                } else {
                                    console.log('BocsSubscriptions: BocsOrderLineItems not available, using event');
                                    // Trigger custom event for refreshing line items
                                    $(document).trigger('bocs:refresh-line-items', {
                                        componentId: componentId,
                                        subscriptionId: subscriptionId
                                    });
                                }
                            }
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
            $('.early-renewal').on('click', function(e) {
                console.log('Early renewal button clicked');
                BocsSubscriptions.handlers.earlyRenewal.call(this, e);
            });
            
            // Form submissions
            $('#edit-schedule-form').on('submit', this.handlers.saveSchedule);
            $('#edit-frequency-form').on('submit', this.handlers.saveFrequency);
            $('#edit-address-form').on('submit', this.handlers.saveAddress);
            $('#edit-payment-form').on('submit', this.handlers.savePayment);
            
            // Modal internal buttons (pausing from schedule modal)
            $(document).on('click', '#pause-button', function(e) {
                console.log('Pause button clicked in schedule modal');
                
                // Get the subscription ID from the active subscription
                const subscriptionId = BocsSubscriptions.activeSubscriptionId;
                console.log('Subscription ID for pause:', subscriptionId);
                
                if (!subscriptionId) {
                    console.error('No subscription ID found for pause button');
                    BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                    return;
                }
                
                // Close schedule modal first
                $('#bocs-edit-schedule-modal').css('display', 'none');
                
                // Create a modified event object with subscription ID
                const modifiedEvent = {
                    ...e,
                    preventDefault: () => e.preventDefault(),
                    stopPropagation: () => e.stopPropagation(),
                    currentTarget: {
                        dataset: {
                            subId: subscriptionId
                        }
                    }
                };
                
                // Call the pause subscription handler with the modified event
                BocsSubscriptions.handlers.pauseSubscription.call({
                    data: function(key) {
                        return key === 'sub-id' ? subscriptionId : null;
                    }
                }, modifiedEvent);
            });
        },

        // Set up modal functionality
        setupModals: function() {
            // Close modal when clicking the X
            $('.bocs-modal-close').on('click', function() {
                $(this).closest('.bocs-modal').css('display', 'none');
            });
            
            // Close modal when clicking cancel button
            $('.bocs-modal .cancel, .bocs-modal .modal-cancel').on('click', function() {
                $(this).closest('.bocs-modal').css('display', 'none');
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('bocs-modal')) {
                    $('.bocs-modal').css('display', 'none');
                }
            });
            
            // Debug which modals exist
            console.log('Available modals:', $('.bocs-modal').map(function() {
                return '#' + $(this).attr('id');
            }).get());
        },

        // API helper functions
        api: {
            // Make an API request with proper headers
            request: async function(endpoint, method = 'GET', data = null) {
                try {
                    const url = `${bocsSubscriptionsData.apiUrl}${endpoint}`;
                    console.log(`API Request: ${method} ${url}`, data ? data : '(no data)');
                    
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
                    
                    console.log('Request options:', {...options, headers: {...options.headers, 'Authorization': '[HIDDEN]'}});
                    console.log('Request body:', options.body || 'No body');
                    
                    const response = await fetch(url, options);
                    
                    // Log response status
                    console.log(`API Response status: ${response.status} ${response.statusText}`);
                    
                    // Clone the response so we can log it and still use it
                    const clonedResponse = response.clone();
                    
                    // Log full response for debugging
                    try {
                        const textResponse = await clonedResponse.text();
                        console.log('API Raw Response:', textResponse);
                    } catch (err) {
                        console.error('Error logging response text:', err);
                    }
                    
                    const responseData = await response.json();
                    console.log('API Response data:', responseData);
                    
                    if (!response.ok) {
                        console.error('API Error:', responseData);
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
                console.log('updateAddress: Called with subscription ID', subscriptionId);
                console.log('updateAddress: Address data', address);
                
                // Ensure the shipping object has the correct property names
                if (address && address.shipping) {
                    // The API might expect different property names than what we're using
                    // Log this for debugging
                    console.log('updateAddress: Shipping data before formatting', address.shipping);
                    
                    // Some APIs expect camelCase and others expect snake_case, let's ensure we're using the right format
                    // This is just a logging step to help identify issues
                }
                
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
            },
            
            // Pause subscription
            pauseSubscription: function(subscriptionId, pauseData) {
                return this.request(`subscriptions/${subscriptionId}/pause`, 'PUT', pauseData);
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
                
                console.log('Edit schedule clicked for subscription:', subscriptionId);
                
                // Store the subscription ID globally
                BocsSubscriptions.activeSubscriptionId = subscriptionId;
                
                // Get current next payment date and populate the form
                const nextPaymentText = subscriptionItem.find('.bocs-section:first .bocs-section-line:first').text();
                const nextPaymentDate = nextPaymentText.replace('Next payment date: ', '');
                
                // Format date for input (YYYY-MM-DD)
                const dateObj = new Date(nextPaymentDate);
                const formattedDate = dateObj.toISOString().split('T')[0];
                
                $('#next-payment-date').val(formattedDate);
                
                // Explicitly log that we're setting the subscription ID for debugging
                console.log('Setting active subscription ID:', subscriptionId);
                
                // Show the modal
                $('#bocs-edit-schedule-modal').show();
            },
            
            // Save schedule changes
            saveSchedule: async function(e) {
                e.preventDefault();
                
                const subscriptionId = BocsSubscriptions.activeSubscriptionId;
                const nextPaymentDate = $('#next-payment-date').val();
                const submitButton = $(e.target).find('button[type="submit"]');
                
                if (!subscriptionId || !nextPaymentDate) {
                    BocsSubscriptions.helpers.showNotification('Missing required information', 'error');
                    return;
                }
                
                try {
                    // Show loading state on button
                    submitButton.addClass('loading').html('<span class="button-text">' + bocsSubscriptionsData.i18n.saveChanges + '</span>').prop('disabled', true);
                    
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
                } finally {
                    // Reset button state
                    submitButton.removeClass('loading').html(bocsSubscriptionsData.i18n.saveChanges).prop('disabled', false);
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
                        console.error('BOCS ID not found for subscription:', subscriptionId);
                        console.log('Subscription data:', subscription);
                        throw new Error('BOCS ID not found for this subscription');
                    }
                    
                    // Fetch BOCS details to get frequency options
                    const bocsDetails = await BocsSubscriptions.api.request(`bocs/${bocsId}`, 'GET');
                    console.log('BOCS details retrieved:', bocsDetails);
                    
                    // Clear existing options in the dropdown
                    $('#frequency-value').empty();
                    
                    // Check if we have adjustment options in the BOCS details
                    if (bocsDetails?.data?.priceAdjustment?.adjustments && 
                        bocsDetails.data.priceAdjustment.adjustments.length > 0) {
                        
                        console.log('Found frequency options in BOCS details:', bocsDetails.data.priceAdjustment.adjustments);
                        const adjustments = bocsDetails.data.priceAdjustment.adjustments;
                        
                        // Populate the frequency dropdown with available options
                        adjustments.forEach(adjustment => {
                            // Create frequency text with discount information included
                            let frequencyText = adjustment.name || `${adjustment.frequency} ${adjustment.timeUnit}`;
                            
                            // Add discount information if available
                            if (adjustment.discount > 0) {
                                if (adjustment.discountType === 'percent') {
                                    frequencyText += ` (${adjustment.discount}% discount)`;
                                } else if (adjustment.discountType === 'fixed') {
                                    frequencyText += ` ($${adjustment.discount} discount)`;
                                }
                            }
                            
                            $('#frequency-value').append(`<option value="${adjustment.frequency}" 
                                data-id="${adjustment.id}"
                                data-time-unit="${adjustment.timeUnit}" 
                                data-discount="${adjustment.discount}"
                                data-discount-type="${adjustment.discountType}"
                                data-scheduled-payment-date="${adjustment.scheduledPaymentDate}">
                                ${frequencyText}
                            </option>`);
                        });
                        
                    } else if (subscription?.bocs?.frequencies && subscription.bocs.frequencies.length > 0) {
                        // Try to get frequencies from subscription data if available
                        console.log('Using frequencies from subscription data:', subscription.bocs.frequencies);
                        
                        subscription.bocs.frequencies.forEach(freq => {
                            let frequencyText = `${freq.frequency} ${freq.timeUnit}`;
                            
                            // Add discount information if available
                            if (freq.discount > 0) {
                                if (freq.discountType === 'percent') {
                                    frequencyText += ` (${freq.discount}% discount)`;
                                } else if (freq.discountType === 'fixed') {
                                    frequencyText += ` ($${freq.discount} discount)`;
                                }
                            }
                            
                            $('#frequency-value').append(`<option value="${freq.frequency}" 
                                data-id="${freq.id || ''}"
                                data-time-unit="${freq.timeUnit}" 
                                data-discount="${freq.discount || 0}"
                                data-discount-type="${freq.discountType || 'percent'}">
                                ${frequencyText}
                            </option>`);
                        });
                    } else {
                        console.error('No frequency options found in BOCS or subscription');
                        console.log('BOCS adjustments:', bocsDetails?.data?.priceAdjustment?.adjustments);
                        console.log('Subscription frequencies:', subscription?.bocs?.frequencies);
                        throw new Error('No frequency options found for this subscription');
                    }
                    
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
                    
                    // Set current frequency as selected if available
                    if (subscription && subscription.frequency) {
                        // Find the matching option
                        const matchingOption = $(`#frequency-value option[data-id="${subscription.frequency.id}"]`);
                        
                        if (matchingOption.length) {
                            matchingOption.prop('selected', true);
                            
                            // Trigger change to update the hidden fields
                            $('#frequency-value').trigger('change');
                        } else {
                            // If no matching option by ID, try to match by frequency value
                            const freqValue = subscription.frequency.frequency;
                            if (freqValue) {
                                const valueOption = $(`#frequency-value option[value="${freqValue}"]`);
                                if (valueOption.length) {
                                    valueOption.prop('selected', true);
                                    $('#frequency-value').trigger('change');
                                }
                            }
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
                const submitButton = $(e.target).find('button[type="submit"]');
                
                // Get data from the selected option's data attributes
                const frequencyValue = selectedOption.val();
                const frequencyId = selectedOption.data('id') || $('#frequency-id').val();
                const timeUnit = selectedOption.data('time-unit') || $('#time-unit').val();
                const discount = selectedOption.data('discount') || $('#discount').val() || '0';
                const discountType = selectedOption.data('discount-type') || $('#discount-type').val() || 'percent';
                const scheduledPaymentDate = selectedOption.data('scheduled-payment-date') || 3;
                
                console.log('Saving frequency with data:', {
                    subscriptionId,
                    frequencyValue,
                    frequencyId,
                    timeUnit,
                    discount,
                    discountType,
                    scheduledPaymentDate
                });
                
                if (!subscriptionId || !timeUnit || !frequencyValue) {
                    console.error('Missing required frequency data:', {
                        subscriptionId,
                        timeUnit,
                        frequencyValue
                    });
                    BocsSubscriptions.helpers.showNotification('Missing required information', 'error');
                    return;
                }
                
                try {
                    // Show loading state on button
                    submitButton.addClass('loading').html('<span class="button-text">' + bocsSubscriptionsData.i18n.saveChanges + '</span>').prop('disabled', true);
                    
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
                    
                    console.log('Sending frequency update request:', frequencyData);
                    const response = await BocsSubscriptions.api.updateFrequency(subscriptionId, frequencyData);
                    console.log('Frequency update response:', response);
                    
                    // Get the display text directly from the selected option
                    let frequencyText = selectedOption.text().trim();
                    
                    // If we don't have display text from the option, construct it
                    if (!frequencyText || frequencyText === '') {
                        frequencyText = `${frequencyValue}`;
                        if (timeUnit === 'month' && parseInt(frequencyValue) > 1) {
                            frequencyText += ' Months';
                        } else if (timeUnit === 'month') {
                            frequencyText += ' Month';
                        } else if (timeUnit === 'months') {
                            frequencyText += ' Months';
                        } else if (timeUnit === 'week' && parseInt(frequencyValue) > 1) {
                            frequencyText += ' Weeks';
                        } else if (timeUnit === 'week') {
                            frequencyText += ' Week';
                        } else if (timeUnit === 'weeks') {
                            frequencyText += ' Weeks';
                        }
                        
                        // Add discount information if available and not already in the text
                        if (parseInt(discount) > 0 && !frequencyText.includes('discount')) {
                            if (discountType === 'percent') {
                                frequencyText += ` (${discount}% discount)`;
                            } else if (discountType === 'fixed') {
                                frequencyText += ` ($${discount} discount)`;
                            }
                        }
                    }
                    
                    // For display in the main subscription section
                    const displayFrequency = frequencyText.charAt(0).toUpperCase() + frequencyText.slice(1);
                    
                    // Update the frequency display in the subscription section
                    $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"]`)
                        .find('.bocs-section:eq(1) .bocs-section-line')
                        .text(displayFrequency);
                    
                    // Update header price frequency display
                    // For consistency, reuse the display text but format for the header
                    // Remove any discount information for the header
                    const frequencyFormatted = frequencyText.replace(/\(.+\)/, '').trim().toLowerCase();
                    
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
                } finally {
                    // Reset button state
                    submitButton.removeClass('loading').html(bocsSubscriptionsData.i18n.saveChanges).prop('disabled', false);
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
                
                console.log('editAddress: Subscription data for ID ' + subscriptionId, subscription);
                
                if (subscription) {
                    console.log('editAddress: Shipping data in subscription', subscription.shipping);
                    
                    // Try to populate with shipping address first - it's at the top level in the subscription object
                    if (subscription.shipping) {
                        console.log('editAddress: Using shipping data', subscription.shipping);
                        $('#first-name').val(subscription.shipping.firstName || '');
                        $('#last-name').val(subscription.shipping.lastName || '');
                        $('#company').val(subscription.shipping.company || '');
                        $('#phone').val(subscription.shipping.phone || '');
                        $('#address').val(subscription.shipping.address1 || '');
                        $('#address2').val(subscription.shipping.address2 || '');
                        $('#city').val(subscription.shipping.city || '');
                        $('#state').val(subscription.shipping.state || '');
                        $('#postcode').val(subscription.shipping.postcode || '');
                        $('#country').val(subscription.shipping.country || 'AU');
                    } 
                    // Fallback to billing address if shipping is empty
                    else if (subscription.billing) {
                        console.log('editAddress: No shipping data, using billing data', subscription.billing);
                        $('#first-name').val(subscription.billing.firstName || '');
                        $('#last-name').val(subscription.billing.lastName || '');
                        $('#company').val(subscription.billing.company || '');
                        $('#phone').val(subscription.billing.phone || '');
                        $('#address').val(subscription.billing.address1 || '');
                        $('#address2').val(subscription.billing.address2 || '');
                        $('#city').val(subscription.billing.city || '');
                        $('#state').val(subscription.billing.state || '');
                        $('#postcode').val(subscription.billing.postcode || '');
                        $('#country').val(subscription.billing.country || 'AU');
                    } else {
                        console.log('editAddress: No shipping or billing data found in subscription');
                    }
                } else {
                    console.error('editAddress: Subscription not found for ID ' + subscriptionId);
                }
                
                // Show the modal
                $('#bocs-edit-address-modal').show();
                
                // Store the subscription ID
                BocsSubscriptions.activeSubscriptionId = subscriptionId;
            },
            
            // Save address changes
            saveAddress: async function(e) {
                e.preventDefault();
                console.log('saveAddress: Starting address save process');
                
                const subscriptionId = BocsSubscriptions.activeSubscriptionId;
                const firstName = $('#first-name').val();
                const lastName = $('#last-name').val();
                const company = $('#company').val(); // Optional field
                const phone = $('#phone').val(); // Optional field
                const address = $('#address').val();
                const address2 = $('#address2').val(); // Optional field
                const city = $('#city').val();
                const state = $('#state').val();
                const postcode = $('#postcode').val();
                const country = $('#country').val() || 'AU';
                const submitButton = $(e.target).find('button[type="submit"]');
                
                console.log('saveAddress: Collected form data', {
                    subscriptionId,
                    firstName,
                    lastName,
                    company,
                    phone,
                    address,
                    address2,
                    city,
                    state,
                    postcode,
                    country
                });
                
                // Only required fields validation
                if (!subscriptionId || !firstName || !lastName || !address || !city || !state || !postcode) {
                    console.error('saveAddress: Validation failed - missing required fields');
                    BocsSubscriptions.helpers.showNotification('Please fill all required fields', 'error');
                    return;
                }
                
                try {
                    // Show loading state on button
                    submitButton.addClass('loading').html('<span class="button-text">' + bocsSubscriptionsData.i18n.saveChanges + '</span>').prop('disabled', true);
                    
                    BocsSubscriptions.helpers.showNotification('Updating address...', 'loading');
                    
                    // Format address data properly as shipping object
                    const addressData = {
                        shipping: {
                            firstName: firstName,
                            lastName: lastName,
                            company: company, // Optional, may be empty
                            phone: phone, // Optional, may be empty
                            address1: address,
                            address2: address2, // Optional, may be empty
                            city: city,
                            state: state,
                            postcode: postcode,
                            country: country
                        }
                    };
                    
                    console.log('saveAddress: Sending address data to API', addressData);
                    
                    const response = await BocsSubscriptions.api.updateAddress(subscriptionId, addressData);
                    console.log('saveAddress: API response received', response);
                    
                    // Update the UI with formatted address - include optional fields only if not empty
                    let formattedAddress = `${firstName} ${lastName}`;
                    if (company) formattedAddress += `, ${company}`;
                    formattedAddress += `, ${address}`;
                    if (address2) formattedAddress += `, ${address2}`;
                    formattedAddress += `, ${city}, ${state} ${postcode}, ${country}`;
                    
                    console.log('saveAddress: Updated address display to:', formattedAddress);
                    
                    $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"]`)
                        .find('.bocs-section:eq(2) .bocs-section-line')
                        .text(formattedAddress);
                    
                    // Hide the modal
                    $('#bocs-edit-address-modal').hide();
                    
                    BocsSubscriptions.helpers.showNotification('Address updated successfully', 'success');
                    console.log('saveAddress: Address update complete');
                } catch (error) {
                    console.error('saveAddress: Error updating address', error);
                    BocsSubscriptions.helpers.showNotification('Failed to update address', 'error');
                } finally {
                    // Reset button state
                    submitButton.removeClass('loading').html(bocsSubscriptionsData.i18n.saveChanges).prop('disabled', false);
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
                                                                colorPrimary: '#0f766d',
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
                
                try {
                    // Show loading state on button
                    submitButton.addClass('loading').html('<span class="button-text">' + bocsSubscriptionsData.i18n.saveChanges + '</span>').prop('disabled', true);
                    
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
                    // Reset button state
                    submitButton.removeClass('loading').html(bocsSubscriptionsData.i18n.saveChanges).prop('disabled', false);
                }
            },
            
            // Early renewal handler
            earlyRenewal: async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Early renewal handler called');
                
                const subscriptionItem = $(this).closest('.bocs-subscription-item');
                const subscriptionId = subscriptionItem.data('subscription-id') || $(this).data('sub-id');
                
                console.log('Subscription ID:', subscriptionId);
                
                if (!subscriptionId) {
                    console.error('No subscription ID found');
                    BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                    return;
                }
                
                // Store the subscription ID
                BocsSubscriptions.activeSubscriptionId = subscriptionId;
                
                // Show the early renewal modal
                $('#bocs-early-renewal-modal').css('display', 'flex');
                console.log('Early renewal modal opened for subscription:', subscriptionId);
            },
            
            // Pause subscription handler
            pauseSubscription: async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Pause subscription handler called');
                console.log('this object:', this);
                console.log('event object:', e);
                
                // Try to get the subscription ID from multiple sources
                let subscriptionId = null;
                
                // 1. Try from data-sub-id attribute via jQuery data method
                if ($(this).data('sub-id')) {
                    subscriptionId = $(this).data('sub-id');
                    console.log('Found subscription ID from data-sub-id:', subscriptionId);
                } 
                // 2. Try from closest subscription item
                else if ($(this).closest('.bocs-subscription-item').length) {
                    subscriptionId = $(this).closest('.bocs-subscription-item').data('subscription-id');
                    console.log('Found subscription ID from closest subscription item:', subscriptionId);
                } 
                // 3. Try from the global active subscription ID
                else if (BocsSubscriptions.activeSubscriptionId) {
                    subscriptionId = BocsSubscriptions.activeSubscriptionId;
                    console.log('Using active subscription ID:', subscriptionId);
                }
                // 4. Check if 'this' has a data function (from our modified call)
                else if (typeof this.data === 'function') {
                    subscriptionId = this.data('sub-id');
                    console.log('Found subscription ID from this.data function:', subscriptionId);
                }
                
                console.log('Final subscription ID for pause:', subscriptionId);
                
                if (!subscriptionId) {
                    console.error('No subscription ID found for pause');
                    BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                    return;
                }
                
                // Store the subscription ID
                BocsSubscriptions.activeSubscriptionId = subscriptionId;
                
                // Clear any previous values
                $('#pause-reason').val('');
                $('#pause-until-date').val('');
                
                // Show the pause subscription modal
                $('#bocs-pause-subscription-modal').css('display', 'flex');
                console.log('Pause subscription modal opened for subscription:', subscriptionId);
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
        
        // Set up early renewal handlers
        setupEarlyRenewalHandlers();
        
        // Immediately run to ensure all product cells with "Unknown product" or "0" are hidden
        $('.bocs-order-table .product-name').each(function() {
            const text = $(this).text().trim();
            const orderTableWrapper = $(this).find('.bocs-order-details-wrapper')[0];
            const orderTable = $(orderTableWrapper).find('.bocs-order-table')[0];
            if (text === 'Unknown product') {
                $(this).addClass('unknown-product');
                orderTable.hide();
            } else if (text === '0') {
                $(this).addClass('zero-product');
                orderTable.hide();
            }
        });
        
        // Set up missing product data handling
        $('.bocs-subscription-item').each(function() {
            const subscriptionId = $(this).data('subscription-id');
            const orderTableWrapper = $(this).find('.bocs-order-details-wrapper')[0];
            
            if (subscriptionId && orderTableWrapper) {
                // Changed from strictly comparing with boolean true to a looser comparison that works with string "true"
                const needsLoading = $(orderTableWrapper).data('needs-loading') == true || $(orderTableWrapper).data('needs-loading') === "true";
                const orderTable = $(orderTableWrapper).find('.bocs-order-table')[0];
                if (needsLoading && orderTable) {
                    // Call function to fill missing data
                    fillMissingProductInfo(subscriptionId, orderTable);
                }
            }
        });
        
        // Early renewal button click (existing code)
        $('.bocs-button.early-renewal').on('click', function() {
            var subscriptionId = $(this).data('sub-id');
            $('#bocs-early-renewal-modal').data('subscription-id', subscriptionId);
            $('#bocs-early-renewal-modal').css('display', 'flex');
        });
    });
    
    /**
     * Set up all early renewal handlers for various button types
     */
    function setupEarlyRenewalHandlers() {
        console.log("Setting up early renewal handlers");
        
        // Ensure the early-renewal-modal exists
        if ($('#bocs-early-renewal-modal').length === 0) {
            const modalHtml = `
            <div id="bocs-early-renewal-modal" class="bocs-modal">
                <div class="bocs-modal-content">
                    <span class="bocs-modal-close">&times;</span>
                    <h3>Early Renewal</h3>
                    <p>This will create an order with all the products in your subscription, and will automatically move your next order date.</p>
                    <div class="bocs-modal-actions">
                        <button class="bocs-button modal-cancel">Cancel</button>
                        <button class="bocs-button primary modal-confirm">Confirm Early Renewal</button>
                    </div>
                </div>
            </div>`;
            $('body').append(modalHtml);
            
            // Re-initialize modal events
            BocsSubscriptions.setupModals();
        }
        
        // Ensure the pause-subscription-modal exists
        if ($('#bocs-pause-subscription-modal').length === 0) {
            const pauseModalHtml = `
            <div id="bocs-pause-subscription-modal" class="bocs-modal">
                <div class="bocs-modal-content">
                    <span class="bocs-modal-close">&times;</span>
                    <h3>Pause Subscription</h3>
                    <p>This will pause your subscription. You won't be charged until you resume your subscription.</p>
                    <div class="bocs-form-row">
                        <label for="pause-reason">Reason for pausing (optional)</label>
                        <select id="pause-reason" name="pause_reason">
                            <option value="">Select a reason...</option>
                            <option value="going_away">Going away/vacation</option>
                            <option value="too_many">Have too many products right now</option>
                            <option value="financial">Financial reasons</option>
                            <option value="other">Other reason</option>
                        </select>
                    </div>
                    <div class="bocs-form-row">
                        <label for="pause-until-date">Resume on (optional)</label>
                        <input type="date" id="pause-until-date" name="pause_until_date">
                    </div>
                    <div class="bocs-modal-actions">
                        <button class="bocs-button modal-cancel">Cancel</button>
                        <button class="bocs-button primary modal-confirm">Confirm Pause</button>
                    </div>
                </div>
            </div>`;
            $('body').append(pauseModalHtml);
            
            // Re-initialize modal events
            BocsSubscriptions.setupModals();
        }
        
        // Set up confirm button handler for early renewal modal
        setupEarlyRenewalModalHandlers();
        
        // Set up confirm button handler for pause subscription modal
        setupPauseModalHandlers();
        
        // Look for all types of early renewal buttons across different interfaces
        handleLegacyRenewalButtons();
        handleNativeWooCommerceRenewalButtons();
    }
    
    /**
     * Set up the handlers for the early renewal modal buttons
     */
    function setupEarlyRenewalModalHandlers() {
        console.log('Setting up early renewal modal handlers');
        
        // Set up confirm button handler
        $('#bocs-early-renewal-modal .modal-confirm').off('click').on('click', async function() {
            const confirmButton = $(this);
            const subscriptionId = BocsSubscriptions.activeSubscriptionId;
            
            if (!subscriptionId) {
                console.error("No active subscription ID found for renewal");
                BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                return;
            }
            
            console.log('Processing early renewal for:', subscriptionId);
            
            try {
                // Show loading state
                confirmButton.addClass('loading').prop('disabled', true);
                BocsSubscriptions.helpers.showNotification('Processing early renewal...', 'loading');
                
                const response = await BocsSubscriptions.api.earlyRenewal(subscriptionId);
                
                // Hide the modal
                $('#bocs-early-renewal-modal').css('display', 'none');
                
                BocsSubscriptions.helpers.showNotification('Early renewal successful', 'success');
                
                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } catch (error) {
                BocsSubscriptions.helpers.showNotification('Failed to process early renewal: ' + (error.message || 'Unknown error'), 'error');
            } finally {
                // Reset button state
                confirmButton.removeClass('loading').prop('disabled', false);
            }
        });
        
        // Set up cancel button handler
        $('#bocs-early-renewal-modal .modal-cancel, #bocs-early-renewal-modal .bocs-modal-close').off('click').on('click', function() {
            $('#bocs-early-renewal-modal').css('display', 'none');
        });
    }
    
    /**
     * Set up the handlers for the pause subscription modal buttons
     */
    function setupPauseModalHandlers() {
        console.log('Setting up pause subscription modal handlers');
        
        // Set up confirm button handler
        $('#bocs-pause-subscription-modal .modal-confirm').off('click').on('click', async function() {
            const confirmButton = $(this);
            const subscriptionId = BocsSubscriptions.activeSubscriptionId;
            
            if (!subscriptionId) {
                console.error("No active subscription ID found for pause");
                BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                return;
            }
            
            // Get pause reason and until date (both optional)
            const pauseReason = $('#pause-reason').val();
            const pauseUntilDate = $('#pause-until-date').val();
            
            console.log('Processing pause for subscription:', subscriptionId, {
                reason: pauseReason,
                until: pauseUntilDate
            });
            
            try {
                // Show loading state
                confirmButton.addClass('loading').prop('disabled', true);
                BocsSubscriptions.helpers.showNotification('Processing pause request...', 'loading');
                
                // Prepare data for API call
                const pauseData = {
                    status: 'paused'
                };
                
                // Add optional fields if provided
                if (pauseReason) {
                    pauseData.pauseReason = pauseReason;
                }
                
                if (pauseUntilDate) {
                    pauseData.resumeDate = pauseUntilDate;
                }
                
                // Call API to pause the subscription
                const response = await BocsSubscriptions.api.pauseSubscription(subscriptionId, pauseData);
                
                // Hide the modal
                $('#bocs-pause-subscription-modal').css('display', 'none');
                
                BocsSubscriptions.helpers.showNotification('Subscription paused successfully', 'success');
                
                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } catch (error) {
                BocsSubscriptions.helpers.showNotification('Failed to pause subscription: ' + (error.message || 'Unknown error'), 'error');
            } finally {
                // Reset button state
                confirmButton.removeClass('loading').prop('disabled', false);
            }
        });
        
        // Set up cancel button handler
        $('#bocs-pause-subscription-modal .modal-cancel, #bocs-pause-subscription-modal .bocs-modal-close').off('click').on('click', function() {
            $('#bocs-pause-subscription-modal').css('display', 'none');
        });
    }
    
    /**
     * Handle legacy early renewal buttons across the interface
     */
    function handleLegacyRenewalButtons() {
        // Look for buttons with "Early Renewal" text that aren't already handled
        // Also look for WooCommerce Subscriptions buttons with specific classes
        $('button:contains("Early Renewal"), a:contains("Early Renewal"), .subscription_renewal_early, .wcs-auto-renew-toggle, .subscription_renewal_button').each(function() {
            const $btn = $(this);
            
            // Skip if it already has our early-renewal class
            if ($btn.hasClass('early-renewal')) {
                return;
            }
            
            console.log('Found legacy Early Renewal button:', $btn);
            
            // Get subscription ID from the button or nearby elements
            let subscriptionId = $btn.data('subscription-id');
            
            // If no subscription ID, try to get it from parent elements
            if (!subscriptionId) {
                const $parent = $btn.closest('[data-subscription-id]');
                if ($parent.length) {
                    subscriptionId = $parent.data('subscription-id');
                }
            }
            
            // If still no subscription ID, try to get it from the URL
            if (!subscriptionId) {
                const urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                if (urlMatch && urlMatch[1]) {
                    subscriptionId = urlMatch[1];
                }
            }
            
            console.log('Subscription ID for legacy button:', subscriptionId);
            
            if (subscriptionId) {
                // Add click handler
                $btn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('Legacy Early Renewal button clicked');
                    
                    // Store the subscription ID
                    BocsSubscriptions.activeSubscriptionId = subscriptionId;
                    
                    // Show our modal
                    $('#bocs-early-renewal-modal').css('display', 'flex');
                });
            }
        });
    }
    
    /**
     * Handle Early Renewal buttons in WooCommerce native subscription view
     * This targets the specific button shown in the user's screenshot
     */
    function handleNativeWooCommerceRenewalButtons() {
        console.log("Adding handler for WooCommerce native Early Renewal buttons");
        
        // Find all Early Renewal buttons in the WooCommerce layout
        $('a.bocs-button, button.bocs-button, .woocommerce-button.button').filter(function() {
            return $(this).text().trim() === 'Early Renewal';
        }).each(function() {
            console.log("Found WooCommerce Early Renewal button to handle:", this);
            
            // Skip if already handled by the legacy handler
            if ($(this).data('bocs-handled')) {
                return;
            }
            
            // Mark as handled to avoid duplicates
            $(this).data('bocs-handled', true);
            
            // Add our handler
            $(this).off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log("Intercepted WooCommerce Early Renewal button click");
                
                // Get subscription ID
                var subscriptionId = $(this).data('subscription-id') || '';
                if (!subscriptionId) {
                    // Try to extract from URL or parent elements
                    var urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                    if (urlMatch && urlMatch[1]) {
                        subscriptionId = urlMatch[1];
                    } else {
                        // Try to get from parent elements
                        var $parent = $(this).closest('[data-subscription-id]');
                        if ($parent.length) {
                            subscriptionId = $parent.data('subscription-id');
                        }
                    }
                }
                
                console.log("Subscription ID for WooCommerce handler:", subscriptionId);
                
                if (!subscriptionId) {
                    console.error("No subscription ID found for this button");
                    return;
                }
                
                // Store the subscription ID
                BocsSubscriptions.activeSubscriptionId = subscriptionId;
                
                // Show our modal
                $('#bocs-early-renewal-modal').css('display', 'flex');
            });
        });
    }

    /**
     * Fill in missing product information by retrieving data from the BOCS
     * @param {string} subscriptionId - The subscription ID
     * @param {HTMLElement} orderTableElement - The order table element to update
     */
    function fillMissingProductInfo(subscriptionId, orderTableElement) {
        // Skip if no subscription ID or table element
        if (!subscriptionId || !orderTableElement) return;
        
        console.log('Filling missing product info for subscription:', subscriptionId);
        
        // Find the subscription in our data
        const subscription = bocsSubscriptionsData.subscriptions.find(sub => sub.id === subscriptionId);
        if (!subscription) {
            console.log('Subscription not found in data');
            showOrderDetails(subscriptionId);
            return;
        }
        
        // Skip if we don't have line items
        if (!subscription.lineItems || subscription.lineItems.length === 0) {
            console.log('No line items found in subscription');
            showOrderDetails(subscriptionId);
            return;
        }
        
        // We need to fetch BOCS details
        const bocsId = subscription.bocs?.id;
        if (!bocsId) {
            console.log('No BOCS ID found for subscription');
            showOrderDetails(subscriptionId);
            return;
        }
        
        console.log('Fetching BOCS details for ID:', bocsId);
        
        // Create the request
        const apiUrl = bocsSubscriptionsData.apiUrl + 'bocs/' + bocsId;
        const headers = {
            'Content-Type': 'application/json',
            'X-BOCS-Organization': bocsSubscriptionsData.headers.organization,
            'X-BOCS-Store': bocsSubscriptionsData.headers.store,
            'Authorization': bocsSubscriptionsData.headers.authorization
        };
        
        // Fetch BOCS details
        fetch(apiUrl, {
            method: 'GET',
            headers: headers
        })
        .then(response => response.json())
        .then(data => {
            // Show the order details regardless of outcome, we've tried our best to get data
            if (data.code !== 200 || !data.data || !data.data.products) {
                console.log('Invalid BOCS data response:', data);
                showOrderDetails(subscriptionId);
                return;
            }
            
            // Process the product data
            processProductData(subscriptionId, orderTableElement, subscription, data.data.products);
            
            // Show the order details after processing
            showOrderDetails(subscriptionId);
        })
        .catch(error => {
            console.error('Error fetching BOCS details:', error);
            showOrderDetails(subscriptionId);
        });
    }
    
    // Helper function to process product data
    function processProductData(subscriptionId, orderTableElement, subscription, bocsProducts) {
        // Map product IDs to product details for quick lookup
        const bocsProductMap = {};
        bocsProducts.forEach(product => {
            bocsProductMap[product.id] = product;
        });
        
        // Get all row elements
        const rows = orderTableElement.querySelectorAll('tbody tr');
        
        // Loop through each row
        rows.forEach((row, index) => {
            // Skip if we don't have a corresponding line item
            if (!subscription.lineItems[index]) return;
            
            const lineItem = subscription.lineItems[index];
            const productCell = row.querySelector('.product-name');
            const priceCell = row.querySelector('td[data-title="Price"]');
            const quantityCell = row.querySelector('td[data-title="Quantity"]');
            const totalCell = row.querySelector('td[data-title="Total"]');
            
            // If product cell is empty and we have a matching product in BOCS
            if ((!productCell.textContent.trim() || productCell.textContent.trim() === '0' || 
                 productCell.textContent.trim() === 'Unknown product') && 
                lineItem.productId && bocsProductMap[lineItem.productId]) {
                
                const bocsProduct = bocsProductMap[lineItem.productId];
                
                // Update the product name
                productCell.textContent = bocsProduct.name;
                
                // Update price if needed
                if (priceCell && (!priceCell.textContent.trim() || priceCell.textContent.trim() === '$0.00')) {
                    if (typeof wc_price === 'function') {
                        priceCell.innerHTML = wc_price(bocsProduct.price);
                    } else {
                        priceCell.textContent = '$' + Number(bocsProduct.price).toFixed(2);
                    }
                }
                
                // Update total if needed
                if (totalCell && (!totalCell.textContent.trim() || totalCell.textContent.trim() === '$0.00')) {
                    const quantity = quantityCell ? parseInt(quantityCell.textContent.trim() || '1', 10) : 1;
                    const totalPrice = bocsProduct.price * quantity;
                    
                    if (typeof wc_price === 'function') {
                        totalCell.innerHTML = wc_price(totalPrice);
                    } else {
                        totalCell.textContent = '$' + Number(totalPrice).toFixed(2);
                    }
                }
            }
        });
    }
    
    /**
     * Shows the order details after loading is complete
     * @param {string} subscriptionId - The subscription ID
     */
    function showOrderDetails(subscriptionId) {
        // Find the order details wrapper
        const wrapper = document.querySelector(`.bocs-order-details-wrapper[data-subscription-id="${subscriptionId}"]`);
        if (!wrapper) {
            console.error('Order details wrapper not found for subscription:', subscriptionId);
            return;
        }
        
        console.log('Showing order details for subscription:', subscriptionId);
        
        // Get content element before hiding loading indicator
        const content = wrapper.querySelector('.bocs-order-details-content');
        
        // Make sure any "Unknown product" or empty cells are handled
        if (content) {
            const productCells = content.querySelectorAll('.product-name');
            productCells.forEach(cell => {
                const text = cell.textContent.trim();
                if (text === 'Unknown product' || text === '0' || text === '') {
                    cell.innerHTML = '<span class="product-placeholder">Product information unavailable</span>';
                }
            });
            
            // Ensure content is displayed
            content.style.display = 'block';
            content.style.opacity = '1';
            content.style.visibility = 'visible';
            
            // Explicitly show the table that was hidden
            $(content).find('table.bocs-order-table').show();
            $(content).find('.bocs-order-table-loading-spinner').hide();
            
            // Show the bocs-order-details div that might be hidden
            $(content).find('.bocs-order-details').fadeIn(300);
        } else {
            console.error('Content element not found in wrapper');
        }
        
        // Hide the loading indicator with a fade out
        const loading = wrapper.querySelector('.bocs-order-details-loading');
        if (loading) {
            loading.style.opacity = '0';
            setTimeout(() => {
                loading.style.display = 'none';
                
                // Show the content with a smooth transition
                if (content) {
                    // Give the browser a moment to process the display change before adding the transition class
                    setTimeout(() => {
                        content.classList.add('loaded');
                        // Make sure table is shown even after transition
                        $(content).find('table.bocs-order-table').show();
                        // Ensure bocs-order-details is visible even after transition
                        $(content).find('.bocs-order-details').fadeIn(300);
                        $(content).find('.bocs-order-table-loading-spinner').hide();
                    }, 50);
                }
            }, 300); // Wait for fade out to complete
        } else {
            // If no loading indicator, just show content
            if (content) {
                setTimeout(() => {
                    content.classList.add('loaded');
                    // Make sure table is shown even after transition
                    $(content).find('table.bocs-order-table').show();
                    // Ensure bocs-order-details is visible even after transition
                    $(content).find('.bocs-order-details').fadeIn(300);
                    $(content).find('.bocs-order-table-loading-spinner').hide();
                }, 50);
            }
        }
        
        console.log('Order details shown for subscription:', subscriptionId);
    }

})(jQuery); 