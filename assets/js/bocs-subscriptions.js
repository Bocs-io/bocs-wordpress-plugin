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
                this.setupDirectHandlers();
                this.extractAndSetSubscriptionId();

                console.log('BocsSubscriptions: Initialization complete');
            } catch (error) {
                console.error('BocsSubscriptions: Error during initialization', error);
            }
        },

        // Extract subscription ID from URL and set it on all relevant elements
        extractAndSetSubscriptionId: function() {
            // Extract subscription ID from URL
            var urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
            if (urlMatch && urlMatch[1]) {
                var subscriptionId = urlMatch[1];
                console.log('IMMEDIATE: Found subscription ID in URL:', subscriptionId);

                // Function to set this ID on an element if it exists
                function setIdOnElement(selector) {
                    var element = document.querySelector(selector);
                    if (element) {
                        element.setAttribute('data-subscription-id', subscriptionId);
                        console.log('IMMEDIATE: Set subscription ID on ' + selector);
                    }
                }

                // Set ID on all relevant elements
                setIdOnElement('#bocs-pause-subscription-modal');
                setIdOnElement('#pause-confirm-button');
                setIdOnElement('#pause-button');
                setIdOnElement('#bocs-edit-schedule-modal');

                // Also store in a global variable that can be accessed by any script
                window.bocsCurrentSubscriptionId = subscriptionId;
                console.log('IMMEDIATE: Stored subscription ID in global variable');
            } else {
                console.log('IMMEDIATE: No subscription ID found in URL');
            }
        },

        // Setup direct handlers for subscription-related elements
        setupDirectHandlers: function() {
            // Set up direct handler for pause confirm button
            const pauseConfirmButton = document.getElementById('pause-confirm-button');
            if (pauseConfirmButton) {
                pauseConfirmButton.addEventListener('click', this.handlers.handlePauseConfirmClick);
                console.log('Added direct click handler to pause-confirm-button');
            } else {
                console.warn('Could not find pause-confirm-button to add direct handler');
            }

            // Set subscription ID from modal script
            this.setScheduleModalSubscriptionId();

            // Setup DOM ready handlers for setting subscription ID
            document.addEventListener('DOMContentLoaded', this.handlers.domContentLoadedHandler);
        },

        // Set subscription ID on the edit schedule modal and related elements
        setScheduleModalSubscriptionId: function() {
            // Try to get from global variable first
            if (window.bocsCurrentSubscriptionId) {
                document.getElementById('bocs-edit-schedule-modal')?.setAttribute('data-subscription-id', window.bocsCurrentSubscriptionId);
                document.getElementById('pause-button')?.setAttribute('data-subscription-id', window.bocsCurrentSubscriptionId);
                console.log('EDIT MODAL SCRIPT: Set subscription ID from global variable:', window.bocsCurrentSubscriptionId);
            } else {
                // Try to extract from URL
                var urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                if (urlMatch && urlMatch[1]) {
                    var subscriptionId = urlMatch[1];
                    document.getElementById('bocs-edit-schedule-modal')?.setAttribute('data-subscription-id', subscriptionId);
                    document.getElementById('pause-button')?.setAttribute('data-subscription-id', subscriptionId);
                    console.log('EDIT MODAL SCRIPT: Set subscription ID from URL:', subscriptionId);

                    // Also set global variable
                    window.bocsCurrentSubscriptionId = subscriptionId;
                }
            }
        },

        // Initialize the accordion functionality
        initAccordion: function() {
            try {
                console.log('BocsSubscriptions: Initializing accordion');

                // Don't hide all accordions on page load - we'll control this more carefully
                // $('.bocs-subscription-details').hide();

                // Reset any active states that might be present
                $('.bocs-subscription-item').removeClass('active');
                $('.bocs-subscription-item').removeClass('open');
                $('.bocs-toggle-icon').removeClass('open');

                // Force the first accordion open immediately to prevent flicker
                if ($('.bocs-subscription-item').length > 0) {
                    const firstItem = $('.bocs-subscription-item').first();
                    const firstDetails = firstItem.find('.bocs-subscription-details');
                    const firstToggle = firstItem.find('.bocs-toggle-icon');

                    // Show the first item's details immediately
                    firstDetails.show();
                    
                    // Add active and open classes
                    firstItem.addClass('active open');
                    firstToggle.addClass('open');
                    firstDetails.addClass('active');

                    // Store the active subscription ID
                    BocsSubscriptions.activeSubscriptionId = firstItem.data('subscription-id');
                    console.log('BocsSubscriptions: Immediately activated first subscription', BocsSubscriptions.activeSubscriptionId);
                    
                    // Hide all other accordions if there are multiple
                    if ($('.bocs-subscription-item').length > 1) {
                        $('.bocs-subscription-item').not(firstItem).find('.bocs-subscription-details').hide();
                    }
                }

                // Also set up a delayed check to ensure the accordion stays open (sometimes CSS or other scripts might interfere)
                setTimeout(function() {
                    if ($('.bocs-subscription-item').length > 0) {
                        const firstItem = $('.bocs-subscription-item').first();
                        const firstDetails = firstItem.find('.bocs-subscription-details');
                        
                        // Check if it's already visible
                        if (firstDetails.is(':hidden')) {
                            console.log('BocsSubscriptions: First accordion was hidden, forcing display');
                            
                            // Show the first item's details with animation
                            firstDetails.slideDown(300);
                            
                            // Add active and open classes
                            firstItem.addClass('active open');
                            firstItem.find('.bocs-toggle-icon').addClass('open');
                            firstDetails.addClass('active');
                            
                            // Store the active subscription ID
                            BocsSubscriptions.activeSubscriptionId = firstItem.data('subscription-id');
                        } else {
                            console.log('BocsSubscriptions: First accordion already visible');
                        }
                    }
                }, 500); // Increased delay to ensure DOM is fully ready

                // Set up toggle functionality with improved handling
                $('.bocs-subscription-header').off('click').on('click', function() {
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

                        // Store the active subscription ID when opening
                        if (subscriptionItem.hasClass('active')) {
                            BocsSubscriptions.activeSubscriptionId = subscriptionItem.data('subscription-id');
                            console.log('BocsSubscriptions: Activated subscription', BocsSubscriptions.activeSubscriptionId);
                        }

                        // If we have more than one subscription, close other open items
                        if ($('.bocs-subscription-item').length > 1) {
                            $('.bocs-subscription-item').not(subscriptionItem).removeClass('active')
                                .removeClass('open')
                                .find('.bocs-subscription-details').slideUp(300).removeClass('active');
                            $('.bocs-subscription-item').not(subscriptionItem)
                                .find('.bocs-toggle-icon').removeClass('open');
                        }
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
            $('.edit-contents').on('click', this.handlers.editContents);
            $('.change-box').on('click', this.handlers.changeBox);

            // Pause subscription button
            $('#pause-button').on('click', this.handlers.pauseSubscription);

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

            // Country change handler for address modal
            $('#country').on('change', function() {
                const country = $(this).val();
                const stateSelect = $('#state');

                // Save current state value
                const currentState = stateSelect.val();

                // Reset state options based on country
                stateSelect.empty();

                if (country === 'AU') {
                    // Australian states
                    stateSelect.append(new Option('Victoria', 'VIC'));
                    stateSelect.append(new Option('New South Wales', 'NSW'));
                    stateSelect.append(new Option('Queensland', 'QLD'));
                    stateSelect.append(new Option('Western Australia', 'WA'));
                    stateSelect.append(new Option('South Australia', 'SA'));
                    stateSelect.append(new Option('Tasmania', 'TAS'));
                    stateSelect.append(new Option('Australian Capital Territory', 'ACT'));
                    stateSelect.append(new Option('Northern Territory', 'NT'));
                } else if (country === 'US') {
                    // US states (abbreviated list for example)
                    stateSelect.append(new Option('California', 'CA'));
                    stateSelect.append(new Option('New York', 'NY'));
                    stateSelect.append(new Option('Texas', 'TX'));
                    stateSelect.append(new Option('Florida', 'FL'));
                    // Add more US states as needed
                } else if (country === 'NZ') {
                    // New Zealand regions
                    stateSelect.append(new Option('Auckland', 'Auckland'));
                    stateSelect.append(new Option('Wellington', 'Wellington'));
                    stateSelect.append(new Option('Canterbury', 'Canterbury'));
                    // Add more NZ regions as needed
                } else if (country === 'GB') {
                    // UK counties/regions
                    stateSelect.append(new Option('England', 'England'));
                    stateSelect.append(new Option('Scotland', 'Scotland'));
                    stateSelect.append(new Option('Wales', 'Wales'));
                    stateSelect.append(new Option('Northern Ireland', 'Northern Ireland'));
                    // Add more UK counties as needed
                }

                // Try to restore previous selection or default to first option
                if (stateSelect.find(`option[value="${currentState}"]`).length) {
                    stateSelect.val(currentState);
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

                        // Handle specific error codes
                        if (response.status === 502) {
                            // For Bad Gateway errors, try to use the AJAX fallback
                            console.log('API returned 502 Bad Gateway, will try AJAX fallback');

                            // For pause subscription, we'll handle this specially
                            if (endpoint.includes('/pause')) {
                                console.log('This is a pause subscription request, will use AJAX fallback silently');
                                // Return a special object that indicates we should use the fallback
                                return {
                                    useFallback: true,
                                    message: 'API server error (502). Using fallback method...'
                                };
                            } else {
                                throw new Error('API server error (502). Trying fallback method...');
                            }
                        } else if (response.status === 401 || response.status === 403) {
                            throw new Error('Authentication error. Please refresh the page and try again.');
                        } else {
                            throw new Error(responseData.message || 'API request failed');
                        }
                    }

                    return responseData;
                } catch (error) {
                    console.error('API Request Error:', error);

                    // Special handling for pause subscription requests
                    if (endpoint.includes('/pause')) {
                        // For pause subscription, we'll handle 502 errors specially
                        if (error.message && (error.message.includes('502') || error.message.includes('Internal server error'))) {
                            console.log('API Request Error for pause subscription, will use fallback silently');
                            // Return a special object that indicates we should use the fallback
                            return {
                                useFallback: true,
                                message: 'API server error. Using fallback method...'
                            };
                        }
                    }

                    // Only show notification for non-502 errors (we'll handle 502 with fallback)
                    if (!error.message || (!error.message.includes('502') && !error.message.includes('Internal server error'))) {
                        BocsSubscriptions.helpers.showNotification(error.message || 'Request failed', 'error');
                    }

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
            pauseSubscription: function(subscriptionId) {
                // Log the headers for debugging
                console.log('Pause subscription headers:', {
                    'Store': bocsSubscriptionsData.headers.store,
                    'Organization': bocsSubscriptionsData.headers.organization,
                    'Authorization': 'HIDDEN'
                });

                return this.request(`subscriptions/${subscriptionId}/pause`, 'PUT')
                    .then(response => {
                        // Check if this is a special response indicating we should use the fallback
                        if (response && response.useFallback) {
                            console.log('Received fallback response from API, will use AJAX fallback silently');

                            // Use the WordPress AJAX fallback
                            return this.pauseSubscriptionAjaxFallback(subscriptionId);
                        }

                        return response;
                    });
            },

            // AJAX fallback for pause subscription
            pauseSubscriptionAjaxFallback: function(subscriptionId) {
                console.log('Using AJAX fallback for pause subscription');

                // Get the WordPress AJAX URL
                const ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';

                // Create form data
                const formData = new FormData();
                formData.append('action', 'bocs_pause_subscription');
                formData.append('subscription_id', subscriptionId);

                // Get the nonce from the global variable
                const nonce = typeof bocs_ajax_nonce !== 'undefined' ? bocs_ajax_nonce : '';
                console.log('Using nonce for AJAX fallback:', nonce);
                formData.append('nonce', nonce);

                // Return a promise
                return new Promise((resolve, reject) => {
                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(response => {
                        if (response.success) {
                            console.log('AJAX fallback successful');
                            resolve({ success: true, message: 'Subscription paused successfully' });
                        } else {
                            console.error('AJAX fallback failed:', response);
                            reject(new Error(response.data ? response.data.message : 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('AJAX fallback error:', error);
                        reject(error);
                    });
                });
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

                // Set the subscription ID on both modals and the pause button as a data attribute
                $('#bocs-edit-schedule-modal').attr('data-subscription-id', subscriptionId);
                $('#bocs-pause-subscription-modal').attr('data-subscription-id', subscriptionId);
                $('#pause-button').attr('data-subscription-id', subscriptionId);
                $('#pause-confirm-button').attr('data-subscription-id', subscriptionId);

                // Also store in global variable
                window.bocsCurrentSubscriptionId = subscriptionId;

                console.log('Set subscription ID on edit schedule modal:', subscriptionId);
                console.log('Set subscription ID on pause modal:', subscriptionId);
                console.log('Set subscription ID on pause button:', subscriptionId);
                console.log('Set subscription ID on pause confirm button:', subscriptionId);
                console.log('Set subscription ID in global variable:', subscriptionId);

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

                    // *** START OF NEW CODE - UPDATE PRICE CALCULATIONS ***
                    
                    // Update order details section with new discount
                    const orderDetails = $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"] .bocs-order-details-content`);
                    
                    if (orderDetails.length) {
                        // Update discount display in order table
                        const discountRow = orderDetails.find('.bocs-discount-row');
                        
                        if (discountRow.length) {
                            // Get the subtotal to calculate discount amount
                            const subtotalText = orderDetails.find('.bocs-subtotal-row td').text();
                            const subtotal = parseFloat(subtotalText.replace(/[^0-9.]/g, '')) || 0;
                            
                            // Calculate new discount amount
                            let discountAmount = 0;
                            if (discountType === 'percent' && parseInt(discount) > 0) {
                                discountAmount = subtotal * (parseInt(discount) / 100);
                            } else if (discountType === 'fixed' && parseInt(discount) > 0) {
                                discountAmount = parseInt(discount);
                            }
                            
                            // Format discount amount for display
                            const formattedDiscount = '-$' + discountAmount.toFixed(2);
                            
                            // Update discount row text
                            discountRow.find('th').text(`Subscription Discount (${discountType === 'percent' ? discount + '%' : '$' + discount} discount)`);
                            discountRow.find('td').text(formattedDiscount);
                            
                            // Update the total
                            const shippingText = orderDetails.find('.bocs-shipping-row td').text() || '$0.00';
                            const shipping = parseFloat(shippingText.replace(/[^0-9.]/g, '')) || 0;
                            
                            // Get the tax from the tax row - but we should NOT recalculate it
                            // Tax should remain the same regardless of discount
                            // The tax is in the row with class bocs-total-row, which actually contains the Tax
                            const taxText = orderDetails.find('.bocs-total-row td').text() || '$0.00';
                            const tax = parseFloat(taxText.replace(/[^0-9.]/g, '')) || 0;
                            
                            console.log('Tax text found:', taxText);
                            
                            // We should NOT update the displayed tax amount as it should remain the same
                            
                            // Calculate new total - subtotal less discount plus shipping plus tax
                            const newTotal = subtotal - discountAmount + shipping + tax;
                            const formattedTotal = '$' + newTotal.toFixed(2);
                            
                            console.log('Total calculation:', {
                                subtotal: subtotal,
                                discountAmount: discountAmount,
                                shipping: shipping,
                                tax: tax,
                                newTotal: newTotal
                            });
                            
                            // Update total row in the final row, not the tax row
                            orderDetails.find('.bocs-final-total-row td').text(formattedTotal);
                            
                            // Update the price in the subscription header
                            const headerPrice = $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"] .bocs-subscription-price`);
                            if (headerPrice.length) {
                                headerPrice.text(`$${newTotal.toFixed(2)} ${frequencyFormatted}`);
                            }
                            
                            // *** NEW CODE - UPDATE INDIVIDUAL PRODUCT DISCOUNTS ***
                            // Update individual product discounts if they exist
                            const productRows = orderDetails.find('tbody tr');
                            
                            if (productRows.length && parseInt(discount) > 0) {
                                productRows.each(function() {
                                    const row = $(this);
                                    const productPrice = row.find('td[data-title="Price"]').text();
                                    const quantity = row.find('td[data-title="Quantity"]').text();
                                    
                                    // Parse price and quantity
                                    const price = parseFloat(productPrice.replace(/[^0-9.]/g, '')) || 0;
                                    const qty = parseInt(quantity) || 1;
                                    
                                    // Calculate new product discount
                                    let productDiscountAmount = 0;
                                    if (discountType === 'percent') {
                                        productDiscountAmount = (price * qty) * (parseInt(discount) / 100);
                                    } else {
                                        // For fixed discounts, distribute proportionally across products
                                        // This is an approximation as fixed discounts are usually applied to the entire order
                                        productDiscountAmount = (price * qty / subtotal) * parseInt(discount);
                                    }
                                    
                                    // Find and update the discount text span
                                    const discountText = row.find('.discount-text');
                                    if (discountText.length) {
                                        // Update discount text with new amount
                                        if (discountType === 'percent') {
                                            discountText.text(`$${productDiscountAmount.toFixed(2)} discount`);
                                        } else {
                                            discountText.text(`$${productDiscountAmount.toFixed(2)} discount`);
                                        }
                                    }
                                    
                                    // Update product total if needed
                                    const productTotalElem = row.find('td[data-title="Total"]');
                                    if (productTotalElem.length) {
                                        // Get the total text without the discount span
                                        const totalText = productTotalElem.contents().filter(function() {
                                            return this.nodeType === 3; // Text nodes only
                                        }).text();
                                        
                                        // Parse the total from the text
                                        const totalValue = parseFloat(totalText.replace(/[^0-9.]/g, '')) || 0;
                                        
                                        // We don't update the main total display here since it's already 
                                        // the correct price - we only update the discount text
                                    }
                                });
                            }
                            
                            // Update the final total discount text if it exists
                            const finalDiscount = orderDetails.find('.bocs-final-total-row .discount-text');
                            if (finalDiscount.length && discount > 0) {
                                // Calculate the discount amount for display
                                let finalDiscountAmount = 0;
                                if (discountType === 'percent') {
                                    finalDiscountAmount = subtotal * (parseInt(discount) / 100);
                                } else {
                                    finalDiscountAmount = parseInt(discount);
                                }
                                
                                finalDiscount.text(`$${finalDiscountAmount.toFixed(2)} discount`);
                            }
                        }
                    }
                    
                    // If there are subscription discount elements, update them too
                    $(`.bocs-subscription-item[data-subscription-id="${subscriptionId}"]`)
                        .find('.subscription-discount')
                        .text(discountType === 'percent' ? `${discount}%` : `$${discount}`);
                    
                    // *** NEW CODE - UPDATE INDIVIDUAL PRODUCT DISCOUNTS ***
                    // Update individual product discounts if they exist
                    const productRows = orderDetails.find('tbody tr');
                    
                    if (productRows.length && parseInt(discount) > 0) {
                        productRows.each(function() {
                            const row = $(this);
                            const productPrice = row.find('td[data-title="Price"]').text();
                            const quantity = row.find('td[data-title="Quantity"]').text();
                            
                            // Parse price and quantity
                            const price = parseFloat(productPrice.replace(/[^0-9.]/g, '')) || 0;
                            const qty = parseInt(quantity) || 1;
                            
                            // Calculate new product discount
                            let productDiscountAmount = 0;
                            if (discountType === 'percent') {
                                productDiscountAmount = (price * qty) * (parseInt(discount) / 100);
                            } else {
                                // For fixed discounts, distribute proportionally across products
                                // This is an approximation as fixed discounts are usually applied to the entire order
                                productDiscountAmount = (price * qty / subtotal) * parseInt(discount);
                            }
                            
                            // Find and update the discount text span
                            const discountText = row.find('.discount-text');
                            if (discountText.length) {
                                // Update discount text with new amount
                                if (discountType === 'percent') {
                                    discountText.text(`$${productDiscountAmount.toFixed(2)} discount`);
                                } else {
                                    discountText.text(`$${productDiscountAmount.toFixed(2)} discount`);
                                }
                            }
                            
                            // Update product total if needed
                            const productTotalElem = row.find('td[data-title="Total"]');
                            if (productTotalElem.length) {
                                // Get the total text without the discount span
                                const totalText = productTotalElem.contents().filter(function() {
                                    return this.nodeType === 3; // Text nodes only
                                }).text();
                                
                                // Parse the total from the text
                                const totalValue = parseFloat(totalText.replace(/[^0-9.]/g, '')) || 0;
                                
                                // We don't update the main total display here since it's already 
                                // the correct price - we only update the discount text
                            }
                        });
                    }
                    
                    // Update the final total discount text if it exists
                    const finalDiscount = orderDetails.find('.bocs-final-total-row .discount-text');
                    if (finalDiscount.length && discount > 0) {
                        // Calculate the discount amount for display
                        let finalDiscountAmount = 0;
                        if (discountType === 'percent') {
                            finalDiscountAmount = subtotal * (parseInt(discount) / 100);
                        } else {
                            finalDiscountAmount = parseInt(discount);
                        }
                        
                        finalDiscount.text(`$${finalDiscountAmount.toFixed(2)} discount`);
                    }
                    // *** END NEW CODE ***
                    
                    // *** END OF NEW CODE ***

                    // ***NEW - TRIGGER EMAIL NOTIFICATION***
                    // Send an AJAX request to trigger the subscription switched email
                    $.ajax({
                        url: bocsSubscriptionsData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'bocs_trigger_subscription_switched_email',
                            nonce: bocsSubscriptionsData.nonce,
                            subscription_id: subscriptionId,
                            frequency_id: frequencyId,
                            frequency_data: JSON.stringify(frequencyData)
                        },
                        success: function(emailResponse) {
                            console.log('Email notification response:', emailResponse);
                            // No user-facing action needed for email trigger success/failure
                        },
                        error: function(xhr, status, error) {
                            console.error('Failed to trigger email notification:', error);
                            // Don't show this error to the user as it's not critical
                        }
                    });
                    // ***END NEW EMAIL TRIGGER CODE***

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

                        // Handle select fields - state and country
                        const stateSelect = $('#state');
                        const countrySelect = $('#country');
                        const state = subscription.shipping.state || '';
                        const country = subscription.shipping.country || 'AU';

                        // Set the country value - we need to check if option exists
                        if (countrySelect.find(`option[value="${country}"]`).length) {
                            countrySelect.val(country);
                        } else {
                            countrySelect.val('AU'); // Default to Australia if not found
                        }

                        // Set the state value - we need to check if option exists
                        if (stateSelect.find(`option[value="${state}"]`).length) {
                            stateSelect.val(state);
                        } else {
                            // If state doesn't exist in dropdown, check if we need to add it
                            // This is important for international addresses
                            if (state && state !== '') {
                                const stateOption = new Option(state, state);
                                stateSelect.append(stateOption);
                                stateSelect.val(state);
                            }
                        }

                        $('#postcode').val(subscription.shipping.postcode || '');
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

                        // Handle select fields - state and country
                        const stateSelect = $('#state');
                        const countrySelect = $('#country');
                        const state = subscription.billing.state || '';
                        const country = subscription.billing.country || 'AU';

                        // Set the country value
                        if (countrySelect.find(`option[value="${country}"]`).length) {
                            countrySelect.val(country);
                        } else {
                            countrySelect.val('AU'); // Default to Australia if not found
                        }

                        // Set the state value
                        if (stateSelect.find(`option[value="${state}"]`).length) {
                            stateSelect.val(state);
                        } else {
                            // If state doesn't exist in dropdown, check if we need to add it
                            if (state && state !== '') {
                                const stateOption = new Option(state, state);
                                stateSelect.append(stateOption);
                                stateSelect.val(state);
                            }
                        }

                        $('#postcode').val(subscription.billing.postcode || '');
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
                $('#bocs-edit-payment-modal').show();
                console.log('BOCS DEBUG: Modal shown with loading message');

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
                                // Track unique payment methods by last4 + brand (case-insensitive)
                                const uniqueMethods = new Map();

                                // First pass - organize by signature (last4 + lowercase brand)
                                response.data.payment_methods.forEach(function(method) {
                                    if (method.method && method.method.last4 && method.method.brand) {
                                        const signature = method.method.last4 + '_' + method.method.brand.toLowerCase();

                                        // If we haven't seen this card before, or this is default (prefer default)
                                        if (!uniqueMethods.has(signature) || method.is_default) {
                                            uniqueMethods.set(signature, method);
                                        }
                                    } else {
                                        // For methods without complete info, use token ID as key
                                        if (method.token_id) {
                                            uniqueMethods.set('token_' + method.token_id, method);
                                        } else if (method.method && method.method.id) {
                                            uniqueMethods.set('id_' + method.method.id, method);
                                        }
                                    }
                                });

                                // Second pass - add unique methods to dropdown
                                uniqueMethods.forEach(function(method) {
                                    const methodLabel = method.method.brand +
                                        (method.method.last4 ? (' ending in ' + method.method.last4) : '');

                                    // Mark as selected if it's the default (is_default=1) payment method
                                    const isSelected = method.is_default ? 'selected' : '';

                                    $('#payment-method').append(
                                        `<option value="${method.method.id}" ${isSelected}>
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
                                return_url: window.location.origin + window.location.pathname +
                                    '?subscription_id=' + encodeURIComponent(subscriptionId),
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

                // Check if modal exists
                const modal = $('#bocs-early-renewal-modal');
                console.log('Early renewal modal exists:', modal.length > 0);

                // Show the early renewal modal
                modal.css('display', 'flex');
                console.log('Modal display style after show:', modal.css('display'));

                // Set up confirm button handler
                $('#bocs-early-renewal-modal .modal-confirm').off('click').on('click', async function() {
                    const confirmButton = $(this);

                    try {
                        // Show loading state
                        confirmButton.addClass('loading').prop('disabled', true);
                        BocsSubscriptions.helpers.showNotification('Processing early renewal...', 'loading');

                        const response = await BocsSubscriptions.api.earlyRenewal(subscriptionId);

                        // Hide the modal
                        $('#bocs-early-renewal-modal').css('display', 'none');

                        BocsSubscriptions.helpers.showNotification('Early renewal successful', 'success');

                        // In a real implementation, we might want to reload the page
                        // or update the UI with new subscription details
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
                $('#bocs-early-renewal-modal .modal-cancel').off('click').on('click', function() {
                    $('#bocs-early-renewal-modal').css('display', 'none');
                });
            },

            // Edit contents handler (placeholder)
            editContents: function(e) {
                e.preventDefault();
                e.stopPropagation();

                const subscriptionId = $(this).closest('.bocs-subscription-item').data('subscription-id') || $(this).data('sub-id');

                // Check if we have a subscription ID
                if (!subscriptionId) {
                    console.error('No subscription ID found for edit contents');
                    return;
                }

                // Navigate to the edit details page
                window.location.href = bocsSubscriptionsData.orderEndpoint + subscriptionId;
            },

            // Change box handler (placeholder)
            changeBox: function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const subscriptionId = $(this).closest('.bocs-subscription-item').data('subscription-id') || $(this).data('sub-id');
                
                // Check if we have a subscription ID
                if (!subscriptionId) {
                    console.error('No subscription ID found for change box');
                    return;
                }
                
                // Navigate to the switch bocs page
                window.location.href = bocsSubscriptionsData.updateBoxEndpoint + subscriptionId;
            },

            // Pause subscription handler
            pauseSubscription: function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Try to get subscription ID from multiple sources
                let subscriptionId = null;

                // First check global variable
                if (window.bocsCurrentSubscriptionId) {
                    subscriptionId = window.bocsCurrentSubscriptionId;
                    console.log('Got subscription ID from global variable:', subscriptionId);
                }

                // If not found, try to get from the edit schedule modal
                if (!subscriptionId) {
                    const editScheduleModal = $('#bocs-edit-schedule-modal');
                    if (editScheduleModal.length && editScheduleModal.attr('data-subscription-id')) {
                        subscriptionId = editScheduleModal.attr('data-subscription-id');
                        console.log('Got subscription ID from edit schedule modal:', subscriptionId);
                    }
                }

                // If not found, try the active subscription ID
                if (!subscriptionId) {
                    subscriptionId = BocsSubscriptions.activeSubscriptionId;
                    console.log('Got subscription ID from active subscription:', subscriptionId);
                }

                if (!subscriptionId) {
                    BocsSubscriptions.helpers.showNotification('No subscription selected', 'error');
                    return;
                }

                // Set the subscription ID on the pause modal
                $('#bocs-pause-subscription-modal').attr('data-subscription-id', subscriptionId);
                console.log('Set subscription ID on pause modal:', subscriptionId);

                // Show the pause subscription modal
                $('#bocs-pause-subscription-modal').css('display', 'flex');
            },

            // DOM content loaded handler for setting subscription IDs
            domContentLoadedHandler: function() {
                // Set subscription ID on all elements from global variable if available
                if (window.bocsCurrentSubscriptionId) {
                    var globalId = window.bocsCurrentSubscriptionId;
                    console.log('Document ready: Setting subscription ID from global variable:', globalId);

                    // Set subscription ID on all relevant elements
                    function setIdOnElement(selector) {
                        var element = document.querySelector(selector);
                        if (element) {
                            element.setAttribute('data-subscription-id', globalId);
                            console.log('Set subscription ID on ' + selector + ':', globalId);
                        }
                    }

                    setIdOnElement('#bocs-pause-subscription-modal');
                    setIdOnElement('#pause-confirm-button');
                    setIdOnElement('#pause-button');
                    setIdOnElement('#bocs-edit-schedule-modal');
                }

                // When the pause button is clicked, set the subscription ID on the modal
                var pauseButton = document.getElementById('pause-button');
                if (pauseButton) {
                    pauseButton.addEventListener('click', function() {
                        // Try to get the subscription ID from various sources
                        var subscriptionId = window.bocsCurrentSubscriptionId;

                        // First try to get from the edit schedule modal
                        var editScheduleModal = document.getElementById('bocs-edit-schedule-modal');
                        if (editScheduleModal && editScheduleModal.getAttribute('data-subscription-id')) {
                            subscriptionId = editScheduleModal.getAttribute('data-subscription-id');
                            console.log('Got subscription ID from edit schedule modal:', subscriptionId);
                        }

                        // Try to get from BocsSubscriptions
                        if (!subscriptionId && typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.activeSubscriptionId) {
                            subscriptionId = BocsSubscriptions.activeSubscriptionId;
                            console.log('Got subscription ID from BocsSubscriptions:', subscriptionId);
                        }

                        // Try to get from URL
                        if (!subscriptionId) {
                            var urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                            if (urlMatch && urlMatch[1]) {
                                subscriptionId = urlMatch[1];
                                console.log('Got subscription ID from URL:', subscriptionId);
                            }
                        }

                        // Set the subscription ID on the modal and buttons if found
                        if (subscriptionId) {
                            // Set subscription ID on all relevant elements
                            function setIdOnElement(selector) {
                                var element = document.querySelector(selector);
                                if (element) {
                                    element.setAttribute('data-subscription-id', subscriptionId);
                                    console.log('Set subscription ID on ' + selector + ':', subscriptionId);
                                }
                            }

                            setIdOnElement('#bocs-pause-subscription-modal');
                            setIdOnElement('#pause-confirm-button');
                            setIdOnElement('#pause-button');
                        } else {
                            console.error('Could not determine subscription ID for pause modal');
                        }
                    });
                }
            },

            // Direct handler for pause confirm button
            handlePauseConfirmClick: function(event) {
                console.log('Direct pause confirm button click handler');

                // Prevent default button behavior
                event.preventDefault();

                // Get the subscription ID - first try global variable
                var subscriptionId = window.bocsCurrentSubscriptionId;

                // If not found in global variable, use the helper function
                if (!subscriptionId) {
                    subscriptionId = BocsSubscriptions.helpers.getSubscriptionId();
                }

                // If still not found, try one last direct URL check
                if (!subscriptionId) {
                    var urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                    if (urlMatch && urlMatch[1]) {
                        subscriptionId = urlMatch[1];
                        console.log('Direct URL check in handler found subscription ID:', subscriptionId);
                    }
                }

                if (!subscriptionId) {
                    console.error('Could not determine subscription ID');
                    alert('Error: Could not determine which subscription to pause');
                    return;
                }

                console.log('Pausing subscription:', subscriptionId);

                // Debug all possible sources of subscription ID
                console.log('Debug subscription ID sources:');
                console.log('- Pause modal attr:', document.getElementById('bocs-pause-subscription-modal') ? document.getElementById('bocs-pause-subscription-modal').getAttribute('data-subscription-id') : 'not found');
                console.log('- Edit schedule modal attr:', document.getElementById('bocs-edit-schedule-modal') ? document.getElementById('bocs-edit-schedule-modal').getAttribute('data-subscription-id') : 'not found');
                console.log('- Pause button attr:', document.getElementById('pause-button') ? document.getElementById('pause-button').getAttribute('data-subscription-id') : 'not found');
                console.log('- Pause confirm button attr:', document.getElementById('pause-confirm-button') ? document.getElementById('pause-confirm-button').getAttribute('data-subscription-id') : 'not found');
                console.log('- BocsSubscriptions.activeSubscriptionId:', typeof BocsSubscriptions !== 'undefined' ? BocsSubscriptions.activeSubscriptionId : 'undefined');

                // URL check
                var urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                console.log('- URL match:', urlMatch ? urlMatch[1] : 'no match');

                // DOM attribute check
                console.log('- Pause modal DOM attr:', document.getElementById('bocs-pause-subscription-modal') ? document.getElementById('bocs-pause-subscription-modal').getAttribute('data-subscription-id') : 'not found');
                console.log('- Edit schedule modal DOM attr:', document.getElementById('bocs-edit-schedule-modal') ? document.getElementById('bocs-edit-schedule-modal').getAttribute('data-subscription-id') : 'not found');
                console.log('- Pause button DOM attr:', document.getElementById('pause-button') ? document.getElementById('pause-button').getAttribute('data-subscription-id') : 'not found');
                console.log('- Pause confirm button DOM attr:', document.getElementById('pause-confirm-button') ? document.getElementById('pause-confirm-button').getAttribute('data-subscription-id') : 'not found');

                // Show loading state
                var button = document.getElementById('pause-confirm-button');
                if (button) {
                    button.classList.add('loading');
                    button.disabled = true;
                }

                // Try to use the BocsSubscriptions API directly
                if (typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.api && BocsSubscriptions.api.pauseSubscription) {
                    console.log('Using BocsSubscriptions.api.pauseSubscription directly with ID:', subscriptionId);

                    // Call the API function directly
                    BocsSubscriptions.api.pauseSubscription(subscriptionId)
                        .then(function(response) {
                            console.log('Pause subscription API response:', response);

                            // Hide the modal
                            var modal = document.getElementById('bocs-pause-subscription-modal');
                            if (modal) {
                                modal.style.display = 'none';
                            }

                            // Show success message
                            if (BocsSubscriptions.helpers && BocsSubscriptions.helpers.showNotification) {
                                BocsSubscriptions.helpers.showNotification('Subscription paused successfully', 'success');
                            } else {
                                alert('Subscription paused successfully');
                            }

                            // Reload the page after a short delay
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        })
                        .catch(function(error) {
                            console.error('Error pausing subscription:', error);

                            // Check if this is a 502 error or Internal server error (API server error)
                            if (error.message && (error.message.includes('502') || error.message.includes('Internal server error'))) {
                                console.log('Received API error, will try AJAX fallback silently');
                                // Don't show an error message, just try the AJAX fallback
                                // The AJAX fallback will be handled by the pause-button-fix.js script

                                // Remove any existing error notifications
                                var notifications = document.querySelectorAll('.bocs-notification');
                                notifications.forEach(function(notification) {
                                    notification.remove();
                                });
                            } else {
                                // For other errors, show an error message
                                alert('Failed to pause subscription: ' + (error.message || 'Unknown error'));
                            }

                            // Reset button state
                            if (button) {
                                button.classList.remove('loading');
                                button.disabled = false;
                            }
                        });
                } else {
                    // Fallback to WordPress AJAX if the API is not available
                    console.log('BocsSubscriptions API not available, falling back to AJAX');

                    var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
                    var nonce = (typeof bocs_ajax_nonce !== 'undefined') ? bocs_ajax_nonce : '';

                    // Debug nonce value
                    console.log('AJAX nonce value:', nonce);
                    console.log('AJAX URL:', ajaxUrl);

                    // Create form data
                    var formData = new FormData();
                    formData.append('action', 'bocs_pause_subscription');
                    formData.append('subscription_id', subscriptionId);
                    formData.append('nonce', nonce);

                    // Debug form data
                    console.log('Form data:');
                    for (var pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }

                    // Double-check the subscription ID one last time
                    if (!subscriptionId) {
                        // Last resort - extract directly from URL
                        var lastResortMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                        if (lastResortMatch && lastResortMatch[1]) {
                            subscriptionId = lastResortMatch[1];
                            console.log('LAST RESORT: Got subscription ID directly from URL:', subscriptionId);
                            formData.set('subscription_id', subscriptionId);
                        }
                    }

                    // Make the request
                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(response) {
                        if (response.success) {
                            // Hide the modal
                            var modal = document.getElementById('bocs-pause-subscription-modal');
                            if (modal) {
                                modal.style.display = 'none';
                            }

                            // Reload the page after a short delay
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            throw new Error(response.data ? response.data.message : 'Unknown error');
                        }
                    })
                    .catch(function(error) {
                        console.error('Error pausing subscription:', error);
                        alert('Failed to pause subscription: ' + (error.message || 'Unknown error'));

                        // Reset button state
                        if (button) {
                            button.classList.remove('loading');
                            button.disabled = false;
                        }
                    });
                }
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
            },

            // Get subscription ID from various sources
            getSubscriptionId: function() {
                var subscriptionId = null;

                // First check for our global variable
                if (window.bocsCurrentSubscriptionId) {
                    subscriptionId = window.bocsCurrentSubscriptionId;
                    console.log('Got subscription ID from global variable:', subscriptionId);
                    return subscriptionId;
                }

                // Try to get from pause modal data attribute using vanilla JS
                var pauseModal = document.getElementById('bocs-pause-subscription-modal');
                if (pauseModal && pauseModal.getAttribute('data-subscription-id')) {
                    subscriptionId = pauseModal.getAttribute('data-subscription-id');
                    console.log('Got subscription ID from pause modal:', subscriptionId);
                    return subscriptionId;
                }

                // Try to get from edit schedule modal data attribute using vanilla JS
                var editScheduleModal = document.getElementById('bocs-edit-schedule-modal');
                if (editScheduleModal && editScheduleModal.getAttribute('data-subscription-id')) {
                    subscriptionId = editScheduleModal.getAttribute('data-subscription-id');
                    console.log('Got subscription ID from edit schedule modal:', subscriptionId);
                    return subscriptionId;
                }

                // Try to get from pause button using vanilla JS
                var pauseButton = document.getElementById('pause-button');
                if (pauseButton && pauseButton.getAttribute('data-subscription-id')) {
                    subscriptionId = pauseButton.getAttribute('data-subscription-id');
                    console.log('Got subscription ID from pause button:', subscriptionId);
                    return subscriptionId;
                }

                // Try to get from pause confirm button using vanilla JS
                var pauseConfirmButton = document.getElementById('pause-confirm-button');
                if (pauseConfirmButton && pauseConfirmButton.getAttribute('data-subscription-id')) {
                    subscriptionId = pauseConfirmButton.getAttribute('data-subscription-id');
                    console.log('Got subscription ID from pause confirm button:', subscriptionId);
                    return subscriptionId;
                }

                // Try to get from BocsSubscriptions
                if (typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.activeSubscriptionId) {
                    subscriptionId = BocsSubscriptions.activeSubscriptionId;
                    console.log('Got subscription ID from BocsSubscriptions:', subscriptionId);
                    return subscriptionId;
                }

                // Try to get from URL
                var urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                if (urlMatch && urlMatch[1]) {
                    subscriptionId = urlMatch[1];
                    console.log('Got subscription ID from URL:', subscriptionId);
                    return subscriptionId;
                }

                return null;
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        BocsSubscriptions.init();

        // Handle legacy Early Renewal button that might be from WooCommerce Subscriptions
        // or other templates not using our class system
        setTimeout(function() {
            setupEarlyRenewalHandlers();
        }, 1000); // Short delay to ensure page is fully loaded

        // Set up pause subscription modal confirm button handler
        setupPauseSubscriptionHandler();

        // Add a fallback for the pause button in case it's added to the DOM later
        // This uses event delegation to handle clicks on the pause button regardless of when it's added
        $(document).on('click', '#pause-confirm-button', function(e) {
            console.log("Pause button clicked via document delegation");
            // Check if the button already has a direct handler
            if (!$(this).data('has-handler')) {
                e.preventDefault();
                e.stopPropagation();

                const subscriptionId = BocsSubscriptions.activeSubscriptionId;
                if (subscriptionId) {
                    // Call the pause subscription API
                    BocsSubscriptions.api.pauseSubscription(subscriptionId)
                        .then(function(response) {
                            // Hide the modal
                            $('#bocs-pause-subscription-modal').css('display', 'none');
                            // Success is indicated by the page reload
                            // Reload the page after a short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        })
                        .catch(function(error) {
                            BocsSubscriptions.helpers.showNotification('Failed to pause subscription: ' + (error.message || 'Unknown error'), 'error');
                        });
                } else {
                    console.error("No active subscription ID found for pausing");
                    BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                }
            }
        });

        // Country change handler for address modal
        $('#country').on('change', function() {
            const country = $(this).val();
            const stateSelect = $('#state');

            // Save current state value
            const currentState = stateSelect.val();

            // Reset state options based on country
            stateSelect.empty();

            if (country === 'AU') {
                // Australian states
                stateSelect.append(new Option('Victoria', 'VIC'));
                stateSelect.append(new Option('New South Wales', 'NSW'));
                stateSelect.append(new Option('Queensland', 'QLD'));
                stateSelect.append(new Option('Western Australia', 'WA'));
                stateSelect.append(new Option('South Australia', 'SA'));
                stateSelect.append(new Option('Tasmania', 'TAS'));
                stateSelect.append(new Option('Australian Capital Territory', 'ACT'));
                stateSelect.append(new Option('Northern Territory', 'NT'));
            } else if (country === 'US') {
                // US states (abbreviated list for example)
                stateSelect.append(new Option('California', 'CA'));
                stateSelect.append(new Option('New York', 'NY'));
                stateSelect.append(new Option('Texas', 'TX'));
                stateSelect.append(new Option('Florida', 'FL'));
                // Add more US states as needed
            } else if (country === 'NZ') {
                // New Zealand regions
                stateSelect.append(new Option('Auckland', 'Auckland'));
                stateSelect.append(new Option('Wellington', 'Wellington'));
                stateSelect.append(new Option('Canterbury', 'Canterbury'));
                // Add more NZ regions as needed
            } else if (country === 'GB') {
                // UK counties/regions
                stateSelect.append(new Option('England', 'England'));
                stateSelect.append(new Option('Scotland', 'Scotland'));
                stateSelect.append(new Option('Wales', 'Wales'));
                stateSelect.append(new Option('Northern Ireland', 'Northern Ireland'));
                // Add more UK counties as needed
            }

            // Try to restore previous selection or default to first option
            if (stateSelect.find(`option[value="${currentState}"]`).length) {
                stateSelect.val(currentState);
            }
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

        // Set up confirm button handler for early renewal modal
        setupEarlyRenewalModalHandlers();

        // Look for all types of early renewal buttons across different interfaces
        handleLegacyRenewalButtons();
        handleNativeWooCommerceRenewalButtons();
    }

    /**
     * Set up the handlers for the early renewal modal buttons
     */
    function setupEarlyRenewalModalHandlers() {
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
        $('#bocs-early-renewal-modal .modal-cancel').off('click').on('click', function() {
            $('#bocs-early-renewal-modal').css('display', 'none');
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
     * Set up the pause subscription modal handler
     */
    function setupPauseSubscriptionHandler() {
        console.log("Setting up pause subscription handler");

        // Debug: Log the elements found by our selectors
        console.log("Modal confirm buttons found:", $('#bocs-pause-subscription-modal .modal-confirm').length);
        console.log("Pause confirm button found by ID:", $('#pause-confirm-button').length);

        // More specific selector that targets both the class and ID
        const pauseButtons = $('#bocs-pause-subscription-modal .modal-confirm, #pause-confirm-button');
        console.log("Total pause buttons found:", pauseButtons.length);

        // Set up confirm button handler for pause subscription modal - use a more robust selector
        pauseButtons.off('click').on('click', async function() {
            console.log("Pause button clicked:", this);
            const confirmButton = $(this);
            // Mark this button as having a handler to prevent duplicate handling
            confirmButton.data('has-handler', true);
            const subscriptionId = BocsSubscriptions.activeSubscriptionId;

            if (!subscriptionId) {
                console.error("No active subscription ID found for pausing");
                BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                return;
            }

            console.log('Processing pause for subscription:', subscriptionId);

            try {
                // Show loading state
                confirmButton.addClass('loading').prop('disabled', true);
                BocsSubscriptions.helpers.showNotification('Pausing subscription...', 'loading');

                // Call the API to pause the subscription
                const response = await BocsSubscriptions.api.pauseSubscription(subscriptionId);

                // Check if this is a special response indicating we should use the fallback
                if (response && response.useFallback) {
                    console.log('Received fallback response from API, AJAX fallback will handle this silently');
                    // Don't show any notification, the AJAX fallback will handle it
                    // Just hide the loading notification
                    $('.bocs-notification').remove();
                    return;
                }

                // Hide the modal
                $('#bocs-pause-subscription-modal').css('display', 'none');

                // Success is indicated by the page reload

                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } catch (error) {
                // Check if this is a 502 error (API server error)
                if (error.message && (error.message.includes('502') || error.message.includes('Internal server error'))) {
                    console.log('Received 502 error, AJAX fallback will handle this silently');
                    // Don't show an error message, just hide the loading notification
                    $('.bocs-notification').remove();
                } else {
                    // For other errors, show an error message
                    BocsSubscriptions.helpers.showNotification('Failed to pause subscription: ' + (error.message || 'Unknown error'), 'error');
                }
            } finally {
                // Reset button state
                confirmButton.removeClass('loading').prop('disabled', false);
            }
        });

        // Set up cancel button handler
        $('#bocs-pause-subscription-modal .modal-cancel').off('click').on('click', function() {
            $('#bocs-pause-subscription-modal').css('display', 'none');
        });
    }

    // Make BocsSubscriptions available globally
    window.BocsSubscriptions = BocsSubscriptions;
})(jQuery);