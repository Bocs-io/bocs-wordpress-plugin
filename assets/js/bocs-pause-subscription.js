/**
 * BOCS Pause Subscription
 *
 * This script handles all pause subscription functionality for BOCS.
 * It consolidates functionality from multiple scripts to ensure the pause
 * button works correctly in all contexts.
 */

(function($) {
    'use strict';

    // Main controller for pause subscription functionality
    const BocsPauseSubscription = {
        // Initialize the pause subscription functionality
        init: function() {
            console.log('BocsPauseSubscription: Initializing');

            // Set up event handlers
            this.bindEvents();

            // Set up MutationObserver to watch for dynamically added buttons
            this.setupMutationObserver();

            console.log('BocsPauseSubscription: Initialization complete');
        },

        // Bind event handlers
        bindEvents: function() {
            console.log('BocsPauseSubscription: Setting up event handlers');

            // Main pause button that opens the modal
            $('#pause-button').on('click', this.handlers.openPauseModal);

            // Pause confirm button in the modal
            this.setupPauseConfirmButton();

            // Legacy pause button in view-subscription.js
            $('a.bocs-button.subscription_pause').on('click', function() {
                $(this).addClass('disabled');
                var buttonElement = $(this);

                if (buttonElement.text() == 'Pause') {
                    $('p#next-payment-date-wrapper').show();
                }
            });

            // Cancel button in the date picker
            $('input#next-payment-date-cancel').on('click', function() {
                $('p#next-payment-date-wrapper').hide();
                $('a.bocs-button.subscription_pause').removeClass('disabled');
            });

            // Confirm button in the date picker
            $('input#next-payment-date-confirm').on('click', async function() {
                // get the date and time
                var dateTimeValue = $('input#next-payment-date').val();
                console.log('Selected date:', dateTimeValue);
                dateTimeValue = dateTimeValue + '.000Z';
                var buttonElement = $('a.bocs-button.subscription_pause');
                
                // hide the date picker
                $('p#next-payment-date-wrapper').hide();

                // update the next payment date
                await $.ajax({
                    url: viewSubscriptionObject.updateSubscriptionUrl + '/pause',
                    method: 'PUT',
                    data: {
                        nextPaymentDateGmt: dateTimeValue
                    },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', viewSubscriptionObject.storeId);
                        xhr.setRequestHeader('Organization', viewSubscriptionObject.orgId);
                        xhr.setRequestHeader('Authorization', viewSubscriptionObject.authId);
                    },
                    success: function(response) {
                        console.log('Pause response', response);
                        buttonElement.text('Paused');
                        $('p#subscriptionStatus').text('Paused');
                    },
                    error: function(error) {
                        console.error('Error pause subscription', error);
                        buttonElement.text('Pause');
                        buttonElement.removeClass('disabled');
                    },
                });

                // re-enable the button
                $('a.bocs-button.subscription_pause').removeClass('disabled');
            });

            // Add a fallback using event delegation for dynamically added buttons
            $(document).on('click', '#pause-confirm-button', function(e) {
                console.log("Pause button clicked via document delegation");
                
                // Check if the button already has a direct handler
                if (!$(this).data('has-handler')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Process the pause action
                    BocsPauseSubscription.handlers.processPause($(this));
                }
            });
        },

        // Set up the pause confirm button
        setupPauseConfirmButton: function() {
            console.log('BocsPauseSubscription: Setting up pause confirm button');

            // Debug: Log the elements found by our selectors
            console.log("Modal confirm buttons found:", $('#bocs-pause-subscription-modal .modal-confirm').length);
            console.log("Pause confirm button found by ID:", $('#pause-confirm-button').length);

            // More specific selector that targets both the class and ID
            const pauseButtons = $('#bocs-pause-subscription-modal .modal-confirm, #pause-confirm-button');
            console.log("Total pause buttons found:", pauseButtons.length);

            // Set up confirm button handler for pause subscription modal
            pauseButtons.off('click').on('click', function(e) {
                console.log("Pause button clicked:", this);
                
                // Mark this button as having a handler to prevent duplicate handling
                $(this).data('has-handler', true);
                
                // Process the pause action
                BocsPauseSubscription.handlers.processPause($(this));
            });

            // Set up cancel button handler
            $('#bocs-pause-subscription-modal .modal-cancel').off('click').on('click', function() {
                $('#bocs-pause-subscription-modal').css('display', 'none');
            });
        },

        // Set up MutationObserver to watch for dynamically added buttons
        setupMutationObserver: function() {
            if (typeof MutationObserver !== 'undefined') {
                console.log('BocsPauseSubscription: Setting up MutationObserver');

                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length) {
                            // Check if any of the added nodes is our button or contains our button
                            for (let i = 0; i < mutation.addedNodes.length; i++) {
                                const node = mutation.addedNodes[i];

                                // Check if the node is an element
                                if (node.nodeType === 1) {
                                    // Check if this is our button
                                    if (node.id === 'pause-confirm-button') {
                                        console.log('BocsPauseSubscription: Pause button added to DOM');
                                        BocsPauseSubscription.setupPauseConfirmButton();
                                        return;
                                    }

                                    // Check if this element contains our button
                                    if (node.querySelector && node.querySelector('#pause-confirm-button')) {
                                        console.log('BocsPauseSubscription: Element containing pause button added to DOM');
                                        BocsPauseSubscription.setupPauseConfirmButton();
                                        return;
                                    }
                                }
                            }
                        }
                    });
                });

                // Start observing the document body for changes
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        },

        // Event handlers
        handlers: {
            // Open the pause modal
            openPauseModal: function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Try to get subscription ID from multiple sources
                let subscriptionId = BocsPauseSubscription.getSubscriptionId();

                if (!subscriptionId) {
                    if (typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.helpers) {
                        BocsSubscriptions.helpers.showNotification('No subscription selected', 'error');
                    } else {
                        alert('Error: No subscription selected');
                    }
                    return;
                }

                // Set the subscription ID on the pause modal
                $('#bocs-pause-subscription-modal').attr('data-subscription-id', subscriptionId);
                console.log('Set subscription ID on pause modal:', subscriptionId);

                // Store in global variable for other scripts to access
                window.bocsCurrentSubscriptionId = subscriptionId;

                // Show the pause subscription modal
                $('#bocs-pause-subscription-modal').css('display', 'flex');
            },

            // Process the pause action
            processPause: function(button) {
                // Show loading state
                button.addClass('loading').prop('disabled', true);

                // Get subscription ID
                const subscriptionId = BocsPauseSubscription.getSubscriptionId();

                if (!subscriptionId) {
                    console.error('BocsPauseSubscription: Could not determine subscription ID');
                    
                    if (typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.helpers) {
                        BocsSubscriptions.helpers.showNotification('Subscription ID not found', 'error');
                    } else {
                        alert('Error: Could not determine which subscription to pause');
                    }
                    
                    button.removeClass('loading').prop('disabled', false);
                    return;
                }

                console.log('BocsPauseSubscription: Processing pause for subscription:', subscriptionId);

                // Store the ID in the global variable
                window.bocsCurrentSubscriptionId = subscriptionId;

                // Try to use BocsSubscriptions API if available
                if (typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.api && BocsSubscriptions.api.pauseSubscription) {
                    console.log('BocsPauseSubscription: Using BocsSubscriptions API');
                    
                    BocsPauseSubscription.showNotification('Pausing subscription...', 'loading');
                    
                    BocsSubscriptions.api.pauseSubscription(subscriptionId)
                        .then(function(response) {
                            console.log('BocsPauseSubscription: API response:', response);

                            // Check if this is a special response indicating we should use the fallback
                            if (response && response.useFallback) {
                                console.log('BocsPauseSubscription: Received fallback response, AJAX fallback will handle this silently');
                                // Don't show any notification, the AJAX fallback will handle it
                                // Just hide the loading notification
                                $('.bocs-notification').remove();
                                return;
                            }

                            // Hide the modal
                            $('#bocs-pause-subscription-modal').css('display', 'none');

                            // Show success message
                            BocsPauseSubscription.showNotification('Subscription paused successfully', 'success');

                            // Reload the page after a short delay
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        })
                        .catch(function(error) {
                            console.error('BocsPauseSubscription: API error:', error);
                            
                            // Check if this is a 502 error (API server error)
                            if (error.message && (error.message.includes('502') || error.message.includes('Internal server error'))) {
                                console.log('BocsPauseSubscription: Received 502 error, using AJAX fallback silently');
                                // Don't show an error message, just hide the loading notification
                                $('.bocs-notification').remove();
                                
                                // Try WordPress AJAX fallback
                                BocsPauseSubscription.useWordPressAjaxFallback(subscriptionId, button);
                            } else {
                                // For other errors, show an error message
                                BocsPauseSubscription.showNotification('Failed to pause subscription: ' + (error.message || 'Unknown error'), 'error');
                                button.removeClass('loading').prop('disabled', false);
                            }
                        });
                } else {
                    // Try direct API call if we have the necessary data
                    BocsPauseSubscription.makeDirectApiCall(subscriptionId, button);
                }
            }
        },

        // Get subscription ID from various sources
        getSubscriptionId: function() {
            let subscriptionId = null;

            // Approach 0: Check for our global variable first
            if (window.bocsCurrentSubscriptionId) {
                subscriptionId = window.bocsCurrentSubscriptionId;
                console.log('BocsPauseSubscription: Got subscription ID from global variable:', subscriptionId);
                return subscriptionId;
            }

            // Approach 1: Get from BocsSubscriptions object
            if (typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.activeSubscriptionId) {
                subscriptionId = BocsSubscriptions.activeSubscriptionId;
                console.log('BocsPauseSubscription: Got subscription ID from BocsSubscriptions:', subscriptionId);
                return subscriptionId;
            }

            // Approach 2: Try to get from closest subscription item in DOM
            const subscriptionItem = $('.bocs-subscription-item.active, [data-subscription-id]').first();
            if (subscriptionItem.length && subscriptionItem.data('subscription-id')) {
                subscriptionId = subscriptionItem.data('subscription-id');
                console.log('BocsPauseSubscription: Got subscription ID from DOM:', subscriptionId);
                return subscriptionId;
            }

            // Approach 3: Try to get from URL
            const urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
            if (urlMatch && urlMatch[1]) {
                subscriptionId = urlMatch[1];
                console.log('BocsPauseSubscription: Got subscription ID from URL:', subscriptionId);
                return subscriptionId;
            }

            // Approach 4: Try to get from modal data attribute
            const modal = $('#bocs-pause-subscription-modal');
            if (modal.length && modal.attr('data-subscription-id')) {
                subscriptionId = modal.attr('data-subscription-id');
                console.log('BocsPauseSubscription: Got subscription ID from modal:', subscriptionId);
                return subscriptionId;
            }

            // Approach 5: Try to get from edit schedule modal data attribute
            const editModal = $('#bocs-edit-schedule-modal');
            if (editModal.length && editModal.attr('data-subscription-id')) {
                subscriptionId = editModal.attr('data-subscription-id');
                console.log('BocsPauseSubscription: Got subscription ID from edit schedule modal:', subscriptionId);
                return subscriptionId;
            }

            // Approach 6: Try to get from the pause button itself
            const pauseButton = $('#pause-button');
            if (pauseButton.length && pauseButton.attr('data-subscription-id')) {
                subscriptionId = pauseButton.attr('data-subscription-id');
                console.log('BocsPauseSubscription: Got subscription ID from pause button:', subscriptionId);
                return subscriptionId;
            }

            // Approach 7: Try to get from the pause confirm button itself
            const confirmButton = $('#pause-confirm-button');
            if (confirmButton.length && confirmButton.attr('data-subscription-id')) {
                subscriptionId = confirmButton.attr('data-subscription-id');
                console.log('BocsPauseSubscription: Got subscription ID from pause confirm button:', subscriptionId);
                return subscriptionId;
            }

            // Approach 8: Try to get from viewSubscriptionObject if available
            if (typeof viewSubscriptionObject !== 'undefined' && viewSubscriptionObject.subscriptionId) {
                subscriptionId = viewSubscriptionObject.subscriptionId;
                console.log('BocsPauseSubscription: Got subscription ID from viewSubscriptionObject:', subscriptionId);
                return subscriptionId;
            }

            console.error('BocsPauseSubscription: Could not determine subscription ID');
            return null;
        },

        // Make direct API call to pause subscription
        makeDirectApiCall: function(subscriptionId, button) {
            console.log('BocsPauseSubscription: Making direct API call');

            // Determine API URL and headers
            let apiUrl = null;
            let headers = null;

            // Approach 1: Get from bocsSubscriptionsData
            if (typeof bocsSubscriptionsData !== 'undefined' &&
                bocsSubscriptionsData.apiUrl &&
                bocsSubscriptionsData.headers) {

                apiUrl = bocsSubscriptionsData.apiUrl;
                headers = {
                    'Content-Type': 'application/json',
                    'Store': bocsSubscriptionsData.headers.store,
                    'Organization': bocsSubscriptionsData.headers.organization,
                    'Authorization': bocsSubscriptionsData.headers.authorization
                };

                console.log('BocsPauseSubscription: Using headers from bocsSubscriptionsData');
            }

            // Approach 2: Try to get from viewSubscriptionObject
            if ((!apiUrl || !headers) && typeof viewSubscriptionObject !== 'undefined') {
                apiUrl = viewSubscriptionObject.updateSubscriptionUrl.replace(/\/[^\/]+$/, '/');
                headers = {
                    'Content-Type': 'application/json',
                    'Store': viewSubscriptionObject.storeId,
                    'Organization': viewSubscriptionObject.orgId,
                    'Authorization': viewSubscriptionObject.authId
                };

                console.log('BocsPauseSubscription: Using headers from viewSubscriptionObject');
            }

            // Approach 3: Try to extract from the page
            if (!apiUrl || !headers) {
                // Look for API URL in script tags or data attributes
                const apiUrlMatch = document.body.innerHTML.match(/BOCS_API_URL\s*=\s*['"]([^'"]+)['"]/);
                if (apiUrlMatch && apiUrlMatch[1]) {
                    apiUrl = apiUrlMatch[1];
                    console.log('BocsPauseSubscription: Found API URL in page:', apiUrl);
                }

                // Look for headers in script tags or data attributes
                const storeMatch = document.body.innerHTML.match(/['"]store['"]\s*:\s*['"]([^'"]+)['"]/i);
                const orgMatch = document.body.innerHTML.match(/['"]organization['"]\s*:\s*['"]([^'"]+)['"]/i);
                const authMatch = document.body.innerHTML.match(/['"]authorization['"]\s*:\s*['"]([^'"]+)['"]/i);

                if (storeMatch && orgMatch && authMatch) {
                    headers = {
                        'Content-Type': 'application/json',
                        'Store': storeMatch[1],
                        'Organization': orgMatch[1],
                        'Authorization': authMatch[1]
                    };
                    console.log('BocsPauseSubscription: Found headers in page');
                }
            }

            // If we have the API URL and headers, make the request directly
            if (apiUrl && headers) {
                console.log('BocsPauseSubscription: Making direct API request');

                // Ensure API URL ends with a slash if needed
                if (!apiUrl.endsWith('/')) {
                    apiUrl += '/';
                }

                BocsPauseSubscription.showNotification('Pausing subscription...', 'loading');

                console.log('BocsPauseSubscription: API URL:', `${apiUrl}subscriptions/${subscriptionId}/pause`);
                console.log('BocsPauseSubscription: Headers:', {...headers, 'Authorization': '[HIDDEN]'});

                fetch(`${apiUrl}subscriptions/${subscriptionId}/pause`, {
                    method: 'PUT',
                    headers: headers
                })
                .then(response => {
                    // Log response status for debugging
                    console.log(`BocsPauseSubscription: API response status: ${response.status}`);

                    // Clone the response so we can log it and still use it
                    const clonedResponse = response.clone();

                    // Log full response for debugging
                    clonedResponse.text().then(text => {
                        console.log('BocsPauseSubscription: API raw response:', text);
                    }).catch(err => {
                        console.error('BocsPauseSubscription: Error logging response text:', err);
                    });

                    if (!response.ok) {
                        // Handle specific error codes
                        if (response.status === 502) {
                            console.log('BocsPauseSubscription: API returned 502 Bad Gateway, will try AJAX fallback silently');
                            // Don't throw an error, just return a special object
                            return {
                                success: false,
                                useFallback: true,
                                message: 'API server error (502). Using fallback method...'
                            };
                        } else {
                            throw new Error(`HTTP error ${response.status}`);
                        }
                    }

                    return response.json();
                })
                .then(function(data) {
                    console.log('BocsPauseSubscription: API response:', data);

                    // Check if this is a special response indicating we should use the fallback
                    if (data && data.useFallback) {
                        console.log('BocsPauseSubscription: Received fallback response, using AJAX fallback silently');
                        return BocsPauseSubscription.useWordPressAjaxFallback(subscriptionId, button);
                    }

                    // Hide the modal
                    $('#bocs-pause-subscription-modal').css('display', 'none');

                    // Show success message
                    BocsPauseSubscription.showNotification('Subscription paused successfully', 'success');

                    // Reload the page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                })
                .catch(function(error) {
                    console.error('BocsPauseSubscription: API error:', error);

                    // Try WordPress AJAX fallback
                    console.log('BocsPauseSubscription: Using WordPress AJAX fallback');
                    BocsPauseSubscription.useWordPressAjaxFallback(subscriptionId, button);
                });
            } else {
                console.log('BocsPauseSubscription: Using WordPress AJAX fallback');
                BocsPauseSubscription.useWordPressAjaxFallback(subscriptionId, button);
            }
        },

        // Use WordPress AJAX fallback
        useWordPressAjaxFallback: function(subscriptionId, button) {
            console.log('BocsPauseSubscription: Using WordPress AJAX fallback');

            BocsPauseSubscription.showNotification('Pausing subscription...', 'loading');

            // Get the WordPress AJAX URL
            const ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';

            // Create form data
            const formData = new FormData();
            formData.append('action', 'bocs_pause_subscription');
            formData.append('subscription_id', subscriptionId);

            // Get the nonce from the global variable
            const nonce = typeof bocs_ajax_nonce !== 'undefined' ? bocs_ajax_nonce : '';
            console.log('BocsPauseSubscription: Using nonce:', nonce);
            formData.append('nonce', nonce);

            // Make the request
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(function(response) {
                if (response.success) {
                    // Hide the modal
                    $('#bocs-pause-subscription-modal').css('display', 'none');

                    // Show success message
                    BocsPauseSubscription.showNotification('Subscription paused successfully', 'success');

                    // Reload the page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    throw new Error(response.data ? response.data.message : 'Unknown error');
                }
            })
            .catch(function(error) {
                console.error('BocsPauseSubscription: WordPress AJAX error:', error);
                BocsPauseSubscription.showNotification('Failed to pause subscription: ' + (error.message || 'Unknown error'), 'error');
                button.removeClass('loading').prop('disabled', false);
            });
        },

        // Show notification
        showNotification: function(message, type = 'info', duration = 3000) {
            // If BocsSubscriptions is available, use its notification system
            if (typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.helpers && BocsSubscriptions.helpers.showNotification) {
                return BocsSubscriptions.helpers.showNotification(message, type, duration);
            }

            // Otherwise, use our own notification system
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
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        BocsPauseSubscription.init();
    });

    // Make BocsPauseSubscription available globally
    window.BocsPauseSubscription = BocsPauseSubscription;

})(jQuery);
