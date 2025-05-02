/**
 * BOCS Pause Button Fix
 *
 * This script ensures the pause-confirm-button works correctly
 * by adding a direct event handler that doesn't depend on the
 * button's location in the DOM.
 */

(function($) {
    'use strict';

    // Function to set up the pause button handler
    function setupPauseButtonHandler() {
        console.log('BOCS Pause Button Fix: Setting up pause button handler');

        // Check if the button exists
        const pauseButton = $('#pause-confirm-button');
        if (pauseButton.length) {
            console.log('BOCS Pause Button Fix: Found pause button');

            // Add a direct click handler
            pauseButton.off('click.pauseFix').on('click.pauseFix', function(e) {
                console.log('BOCS Pause Button Fix: Pause button clicked');

                // Show loading state
                const button = $(this);
                button.addClass('loading').prop('disabled', true);

                // Try multiple approaches to get the subscription ID
                let subscriptionId = null;

                // Approach 0: Check for our global variable first
                if (window.bocsCurrentSubscriptionId) {
                    subscriptionId = window.bocsCurrentSubscriptionId;
                    console.log('BOCS Pause Button Fix: Got subscription ID from global variable:', subscriptionId);
                }

                // Approach 1: Get from BocsSubscriptions object
                if (!subscriptionId && typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.activeSubscriptionId) {
                    subscriptionId = BocsSubscriptions.activeSubscriptionId;
                    console.log('BOCS Pause Button Fix: Got subscription ID from BocsSubscriptions:', subscriptionId);
                }

                // Approach 2: Try to get from closest subscription item in DOM
                if (!subscriptionId) {
                    const subscriptionItem = button.closest('.bocs-subscription-item, [data-subscription-id]');
                    if (subscriptionItem.length && subscriptionItem.data('subscription-id')) {
                        subscriptionId = subscriptionItem.data('subscription-id');
                        console.log('BOCS Pause Button Fix: Got subscription ID from DOM:', subscriptionId);
                    }
                }

                // Approach 3: Try to get from URL
                if (!subscriptionId) {
                    const urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                    if (urlMatch && urlMatch[1]) {
                        subscriptionId = urlMatch[1];
                        console.log('BOCS Pause Button Fix: Got subscription ID from URL:', subscriptionId);
                    }
                }

                // Approach 4: Try to get from modal data attribute
                if (!subscriptionId) {
                    const modal = $('#bocs-pause-subscription-modal');
                    if (modal.length && modal.attr('data-subscription-id')) {
                        subscriptionId = modal.attr('data-subscription-id');
                        console.log('BOCS Pause Button Fix: Got subscription ID from modal:', subscriptionId);
                    }
                }

                // Approach 5: Try to get from edit schedule modal data attribute
                if (!subscriptionId) {
                    const editModal = $('#bocs-edit-schedule-modal');
                    if (editModal.length && editModal.attr('data-subscription-id')) {
                        subscriptionId = editModal.attr('data-subscription-id');
                        console.log('BOCS Pause Button Fix: Got subscription ID from edit schedule modal:', subscriptionId);
                    }
                }

                // Approach 6: Try to get from the pause button itself
                if (!subscriptionId) {
                    const pauseButton = $('#pause-button');
                    if (pauseButton.length && pauseButton.attr('data-subscription-id')) {
                        subscriptionId = pauseButton.attr('data-subscription-id');
                        console.log('BOCS Pause Button Fix: Got subscription ID from pause button:', subscriptionId);
                    }
                }

                // Approach 7: Try to get from the pause confirm button itself
                if (!subscriptionId) {
                    const confirmButton = $('#pause-confirm-button');
                    if (confirmButton.length && confirmButton.attr('data-subscription-id')) {
                        subscriptionId = confirmButton.attr('data-subscription-id');
                        console.log('BOCS Pause Button Fix: Got subscription ID from pause confirm button:', subscriptionId);
                    }
                }

                if (!subscriptionId) {
                    console.error('BOCS Pause Button Fix: Could not determine subscription ID');
                    alert('Error: Could not determine which subscription to pause');
                    button.removeClass('loading').prop('disabled', false);
                    return;
                }

                console.log('BOCS Pause Button Fix: Processing pause for subscription:', subscriptionId);

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

                    console.log('BOCS Pause Button Fix: Using headers from bocsSubscriptionsData');
                }

                // Approach 2: Try to extract from the page
                if (!apiUrl || !headers) {
                    // Look for API URL in script tags or data attributes
                    const apiUrlMatch = document.body.innerHTML.match(/BOCS_API_URL\s*=\s*['"]([^'"]+)['"]/);
                    if (apiUrlMatch && apiUrlMatch[1]) {
                        apiUrl = apiUrlMatch[1];
                        console.log('BOCS Pause Button Fix: Found API URL in page:', apiUrl);
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
                        console.log('BOCS Pause Button Fix: Found headers in page');
                    }
                }

                // If we still don't have what we need, try to use BocsSubscriptions API directly
                if (typeof BocsSubscriptions !== 'undefined' && BocsSubscriptions.api && BocsSubscriptions.api.pauseSubscription) {
                    console.log('BOCS Pause Button Fix: Using BocsSubscriptions API directly with ID:', subscriptionId);

                    try {
                        // Make sure we have the subscription ID
                        if (!subscriptionId) {
                            // Last resort - try to get from URL
                            const urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                            if (urlMatch && urlMatch[1]) {
                                subscriptionId = urlMatch[1];
                                console.log('BOCS Pause Button Fix: Last resort - got subscription ID from URL:', subscriptionId);
                            } else {
                                throw new Error('Could not determine subscription ID');
                            }
                        }

                        // Store the ID in the global variable
                        window.bocsCurrentSubscriptionId = subscriptionId;

                        // Call the API directly
                        BocsSubscriptions.api.pauseSubscription(subscriptionId)
                            .then(function(response) {
                                console.log('BOCS Pause Button Fix: API response:', response);

                                // Hide the modal
                                $('#bocs-pause-subscription-modal').css('display', 'none');

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
                                console.error('BOCS Pause Button Fix: API error:', error);
                                // alert('Failed to pause subscription: ' + (error.message || 'Unknown error'));
                                button.removeClass('loading').prop('disabled', false);
                            });

                        return; // Exit early since we're handling it with the API
                    } catch (error) {
                        console.error('BOCS Pause Button Fix: Error using BocsSubscriptions API:', error);
                        // Continue to fallback approach
                    }
                }

                // If we have the API URL and headers, make the request directly
                if (apiUrl && headers) {
                    console.log('BOCS Pause Button Fix: Making direct API request');

                    // Ensure API URL ends with a slash if needed
                    if (!apiUrl.endsWith('/')) {
                        apiUrl += '/';
                    }

                    // Make sure we have the subscription ID
                    if (!subscriptionId) {
                        // Last resort - try to get from URL
                        const urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                        if (urlMatch && urlMatch[1]) {
                            subscriptionId = urlMatch[1];
                            console.log('BOCS Pause Button Fix: Last resort - got subscription ID from URL for direct API call:', subscriptionId);
                        } else {
                            console.error('BOCS Pause Button Fix: Could not determine subscription ID for direct API call');
                            useWordPressAjaxFallback();
                            return;
                        }
                    }

                    // Store the ID in the global variable
                    window.bocsCurrentSubscriptionId = subscriptionId;

                    // Make sure we have all required headers
                    if (!headers['Store'] && !headers['store']) {
                        console.log('BOCS Pause Button Fix: Adding Store header');
                        // Try to get store from data attributes or other sources
                        const storeElement = $('[data-store]');
                        if (storeElement.length) {
                            headers['Store'] = storeElement.data('store');
                        } else if (typeof bocsSubscriptionsData !== 'undefined' && bocsSubscriptionsData.headers && bocsSubscriptionsData.headers.store) {
                            headers['Store'] = bocsSubscriptionsData.headers.store;
                        }
                    }

                    console.log('BOCS Pause Button Fix: Making direct API request with headers:', headers);
                    console.log('BOCS Pause Button Fix: API URL:', `${apiUrl}subscriptions/${subscriptionId}/pause`);

                    fetch(`${apiUrl}subscriptions/${subscriptionId}/pause`, {
                        method: 'PUT',
                        headers: headers
                    })
                    .then(response => {
                        // Log response status for debugging
                        console.log(`BOCS Pause Button Fix: API response status: ${response.status}`);

                        // Clone the response so we can log it and still use it
                        const clonedResponse = response.clone();

                        // Log full response for debugging
                        clonedResponse.text().then(text => {
                            console.log('BOCS Pause Button Fix: API raw response:', text);
                        }).catch(err => {
                            console.error('BOCS Pause Button Fix: Error logging response text:', err);
                        });

                        if (!response.ok) {
                            // Handle specific error codes
                            if (response.status === 502) {
                                console.log('BOCS Pause Button Fix: API returned 502 Bad Gateway, will try AJAX fallback silently');
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
                        console.log('BOCS Pause Button Fix: API response:', data);

                        // Check if this is a special response indicating we should use the fallback
                        if (data && data.useFallback) {
                            console.log('BOCS Pause Button Fix: Received fallback response, using AJAX fallback silently');
                            return useWordPressAjaxFallback();
                        }

                        // Hide the modal
                        $('#bocs-pause-subscription-modal').css('display', 'none');

                        // Reload the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    })
                    .catch(function(error) {
                        console.error('BOCS Pause Button Fix: API error:', error);

                        // Try WordPress AJAX fallback
                        console.log('BOCS Pause Button Fix: Using WordPress AJAX fallback');
                        useWordPressAjaxFallback();
                    });
                } else {
                    console.log('BOCS Pause Button Fix: Using WordPress AJAX fallback');
                    useWordPressAjaxFallback();
                }

                // WordPress AJAX fallback function
                function useWordPressAjaxFallback() {
                    console.log('BOCS Pause Button Fix: Using WordPress AJAX fallback');

                    // Make sure we have the subscription ID
                    if (!subscriptionId) {
                        // Last resort - try to get from URL
                        const urlMatch = window.location.href.match(/\/([a-f0-9-]{36})/);
                        if (urlMatch && urlMatch[1]) {
                            subscriptionId = urlMatch[1];
                            console.log('BOCS Pause Button Fix: Last resort - got subscription ID from URL for AJAX fallback:', subscriptionId);
                        } else {
                            console.error('BOCS Pause Button Fix: Could not determine subscription ID for AJAX fallback');
                            alert('Error: Could not determine which subscription to pause');
                            button.removeClass('loading').prop('disabled', false);
                            return;
                        }
                    }

                    // Store the ID in the global variable
                    window.bocsCurrentSubscriptionId = subscriptionId;

                    // Get the WordPress AJAX URL
                    const ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';

                    // Create form data
                    const formData = new FormData();
                    formData.append('action', 'bocs_pause_subscription');
                    formData.append('subscription_id', subscriptionId);

                    // Get the nonce from the global variable
                    const nonce = typeof bocs_ajax_nonce !== 'undefined' ? bocs_ajax_nonce : '';
                    console.log('BOCS Pause Button Fix: Using nonce:', nonce);
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

                            // Reload the page after a short delay
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            throw new Error(response.data ? response.data.message : 'Unknown error');
                        }
                    })
                    .catch(function(error) {
                        console.error('BOCS Pause Button Fix: WordPress AJAX error:', error);
                        // alert('Failed to pause subscription: ' + (error.message || 'Unknown error'));
                        button.removeClass('loading').prop('disabled', false);
                    });
                }
            });

            console.log('BOCS Pause Button Fix: Pause button handler set up successfully');
        } else {
            console.log('BOCS Pause Button Fix: Pause button not found in DOM');
        }
    }

    // Set up the handler when the document is ready
    $(document).ready(function() {
        console.log('BOCS Pause Button Fix: Document ready');

        // Try to set up the handler immediately
        setupPauseButtonHandler();

        // Also try again after a short delay to catch any dynamically added buttons
        setTimeout(setupPauseButtonHandler, 1000);
    });

    // Also set up a MutationObserver to watch for the button being added to the DOM
    if (typeof MutationObserver !== 'undefined') {
        console.log('BOCS Pause Button Fix: Setting up MutationObserver');

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
                                console.log('BOCS Pause Button Fix: Pause button added to DOM');
                                setupPauseButtonHandler();
                                return;
                            }

                            // Check if this element contains our button
                            if (node.querySelector && node.querySelector('#pause-confirm-button')) {
                                console.log('BOCS Pause Button Fix: Element containing pause button added to DOM');
                                setupPauseButtonHandler();
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
})(jQuery);
