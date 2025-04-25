/**
 * BOCS Order Line Items Component JS
 * 
 * Handles the interactive functionality for the order line items component.
 */

(function($) {
    'use strict';

    var BocsOrderLineItems = {
        init: function() {
            console.log('BocsOrderLineItems: Initializing');
            
            // Check if required data is available
            if (typeof bocs_data === 'undefined') {
                console.error('BocsOrderLineItems: bocs_data is not defined');
                return;
            }
            
            console.log('BocsOrderLineItems: bocs_data available', {
                ajax_url: bocs_data.ajax_url || 'Not available',
                nonce: bocs_data.nonce ? 'Available' : 'Not available'
            });
            
            // Initialize component functionality
            this.setupEventHandlers();
            
            // Expose methods for external use
            window.BocsOrderLineItems = {
                refresh: this.refreshLineItems.bind(this),
                getLineItemsFor: this.getLineItemsForSubscription.bind(this)
            };
            
            console.log('BocsOrderLineItems: Initialized successfully');
        },
        
        setupEventHandlers: function() {
            // For future interactions with the line items component
            $(document).on('bocs:refresh-line-items', function(e, data) {
                if (data && data.componentId) {
                    BocsOrderLineItems.refreshLineItems(data.componentId, data.subscriptionId);
                }
            });
        },
        
        /**
         * Refresh line items for a specific component
         * 
         * @param {string} componentId The ID of the component to refresh
         * @param {string} subscriptionId The subscription ID to fetch data for
         */
        refreshLineItems: function(componentId, subscriptionId) {
            console.log('BocsOrderLineItems: Refreshing line items', { componentId, subscriptionId });
            
            if (!componentId || !subscriptionId) {
                console.error('Missing componentId or subscriptionId for refreshing line items');
                return;
            }
            
            // Get the container
            const $container = $('#' + componentId);
            if (!$container.length) {
                console.error('Line items container not found: ' + componentId);
                return;
            }
            
            console.log('BocsOrderLineItems: Container found, sending AJAX request');
            
            // Hide the order table before refreshing
            $container.find('.bocs-order-table').hide();
            // $container.find('.bocs-order-table-loading-spinner').show();
            
            // Add loading state
            $container.addClass('loading');
            
            // Fetch data via AJAX
            $.ajax({
                url: bocs_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'bocs_get_subscription_line_items',
                    subscription_id: subscriptionId,
                    nonce: bocs_data.nonce
                },
                success: function(response) {
                    console.log('BocsOrderLineItems: AJAX success', response);
                    
                    if (response.success && response.data) {
                        // Replace the container content with the new HTML
                        $container.replaceWith(response.data.html);
                        $(this).find('.bocs-order-table-loading-spinner').hide();
                        console.log('BocsOrderLineItems: Container updated with new HTML');
                        
                        // Get the new container since the old one was replaced
                        const $newContainer = $('#' + componentId);
                        
                        // Show the order table and order details after the container is replaced
                        $newContainer.find('.bocs-order-table').fadeIn(300);
                        $newContainer.find('.bocs-order-details').fadeIn(300);
                        $newContainer.find('.bocs-order-table-loading-spinner').hide();
                    } else {
                        console.error('Failed to refresh line items', response);
                        $container.removeClass('loading');
                        // Show the table and order details again if there was an error
                        $container.find('.bocs-order-table').show();
                        $container.find('.bocs-order-details').show();
                        $container.find('.bocs-order-table-loading-spinner').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error refreshing line items', { xhr, status, error });
                    $container.removeClass('loading');
                    // Show the table and order details again if there was an error
                    $container.find('.bocs-order-table').show();
                    $container.find('.bocs-order-details').show();
                    $container.find('.bocs-order-table-loading-spinner').hide();
                }
            });
        },
        
        /**
         * Fetch line items data for a subscription
         * 
         * @param {string} subscriptionId The subscription ID to fetch data for
         * @param {Function} callback Callback function to process the data
         */
        getLineItemsForSubscription: function(subscriptionId, callback) {
            if (!subscriptionId) {
                console.error('Missing subscriptionId for fetching line items');
                if (typeof callback === 'function') {
                    callback({ success: false, error: 'Missing subscription ID' });
                }
                return;
            }
            
            // Fetch data via AJAX
            $.ajax({
                url: bocs_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'bocs_get_subscription_line_items_data',
                    subscription_id: subscriptionId,
                    nonce: bocs_data.nonce
                },
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error fetching line items data', error);
                    if (typeof callback === 'function') {
                        callback({ success: false, error: error });
                    }
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BocsOrderLineItems.init();
    });
    
})(jQuery); 