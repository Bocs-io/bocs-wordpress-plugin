/**
 * BOCS Edit Details JavaScript
 * 
 * Handles the frontend functionality for the BOCS subscription edit details page.
 */

(function($) {
    'use strict';

    var BocsEditDetails = {
        subscriptionId: null,
        isCustomBox: false,
        selectedProducts: {},
        originalProducts: {},

        init: function() {
            // Get data passed from PHP
            if (typeof bocs_edit_details_data !== 'undefined') {
                this.subscriptionId = bocs_edit_details_data.subscription_id || null;
                this.isCustomBox = bocs_edit_details_data.is_custom_box || false;
                this.subscriptionItems = bocs_edit_details_data.subscription_items || [];
                this.allProducts = bocs_edit_details_data.all_products || [];
                this.minProducts = bocs_edit_details_data.min_products || 0;
                this.maxProducts = bocs_edit_details_data.max_products || 0;
            } else {
                console.error('bocs_edit_details_data is undefined');
                return;
            }
            
            // Exit if we don't have a subscription ID
            if (!this.subscriptionId) {
                console.error('No subscription ID found');
                return;
            }
            
            // Set up modals and event handlers
            this.setupModals();
            this.bindEvents();
            
            // Modal debug - emulating the previously inline script
            console.log("Modal debug - Script loaded");
            $(".edit-frequency, .edit-shipping, .edit-billing, .edit-payment").on("click", function() {
                console.log("Modal debug - Edit button clicked:", $(this).attr("class"));
            });
        },

        setupModals: function() {
            // Close modal when clicking outside
            $('.bocs-modal-overlay').on('click', function() {
                $(this).closest('.bocs-modal').removeClass('show');
                $('body').removeClass('modal-open');
            });

            // Close modal when clicking close button
            $('.bocs-modal-close').on('click', function() {
                $(this).closest('.bocs-modal').removeClass('show');
                $('body').removeClass('modal-open');
            });

            // Handle cancel buttons
            $('.bocs-modal-cancel, .cancel').on('click', function() {
                $(this).closest('.bocs-modal').removeClass('show');
                $('body').removeClass('modal-open');
            });

            // Close modal when pressing ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.bocs-modal').removeClass('show');
                    $('body').removeClass('modal-open');
                }
            });

            // Initialize product selection if custom box
            if (this.isCustomBox) {
                this.initializeProductSelection();
            }
        },

        bindEvents: function() {
            const self = this;
            
            // Helper function to safely bind methods
            const safeBind = function(selector, event, method) {
                if (typeof method === 'function') {
                    $(selector).on(event, method.bind(self));
                } else {
                    console.error('Method not found:', method);
                }
            };
            
            // Edit products
            $('.modify-products').on('click', function(e) {
                e.preventDefault();
                self.editProducts();
            });
            
            // Save products
            $('.bocs-save-products-btn').on('click', function(e) {
                e.preventDefault();
                self.updateProducts();
            });
            
            // Quantity buttons
            $(document).on('click', '.quantity-btn.plus', function() {
                // Skip if disabled
                if ($(this).prop('disabled')) {
                    return;
                }
                
                const $input = $(this).siblings('.quantity-input');
                const currentVal = parseInt($input.val(), 10) || 0;
                
                // Calculate new total if we increment this product
                let totalQty = 0;
                $('.product-item').each(function() {
                    const itemId = $(this).data('product-id');
                    const itemQty = parseInt($(this).find('.quantity-input').val(), 10) || 0;
                    totalQty += itemQty;
                });
                
                // Check if adding one more would exceed max
                if (self.maxProducts > 0 && totalQty >= self.maxProducts) {
                    // Show warning message
                    self.showNotification('Maximum quantity of ' + self.maxProducts + ' products reached', 'warning');
                    return;
                }
                
                // Increment the value
                $input.val(currentVal + 1);
                $input.trigger('change');
                self.updateProductCount();
            });
            
            $(document).on('click', '.quantity-btn.minus', function() {
                // Skip if disabled
                if ($(this).prop('disabled')) {
                    return;
                }
                
                const $input = $(this).siblings('.quantity-input');
                const currentVal = parseInt($input.val(), 10) || 0;
                
                if (currentVal <= 0) {
                    return;
                }
                
                // Calculate new total if we decrement this product
                let totalQty = 0;
                $('.product-item').each(function() {
                    const itemId = $(this).data('product-id');
                    const itemQty = parseInt($(this).find('.quantity-input').val(), 10) || 0;
                    totalQty += itemQty;
                });
                
                // Check if removing one would go below min
                if (self.minProducts > 0 && totalQty <= self.minProducts && currentVal === 1) {
                    // Show warning message
                    self.showNotification('Minimum quantity of ' + self.minProducts + ' products required', 'warning');
                    return;
                }
                
                // Decrement the value
                $input.val(currentVal - 1);
                $input.trigger('change');
                self.updateProductCount();
            });
            
            // Quantity input change
            $(document).on('change', '.quantity-input', function() {
                const newValue = parseInt($(this).val(), 10) || 0;
                const $item = $(this).closest('.product-item');
                
                // Calculate total across all products with this new value
                let totalQty = 0;
                $('.product-item').each(function() {
                    const itemId = $(this).data('product-id');
                    // Use the new value for the current item, otherwise use the input value
                    const itemQty = ($item.data('product-id') === itemId) ? 
                        newValue : 
                        (parseInt($(this).find('.quantity-input').val(), 10) || 0);
                    totalQty += itemQty;
                });
                
                // Enforce min/max limits
                if (self.maxProducts > 0 && totalQty > self.maxProducts) {
                    // Reduce the value to stay within max
                    const excess = totalQty - self.maxProducts;
                    const correctedValue = Math.max(0, newValue - excess);
                    $(this).val(correctedValue);
                    self.showNotification('Adjusted quantity to stay within maximum limit of ' + self.maxProducts, 'warning');
                }
                
                // Update counts and button states
                self.updateProductCount();
            });
            
            // Subscription management - Pause
            $('.bocs-pause-subscription-btn').on('click', function(e) {
                e.preventDefault();
                $('#bocs-pause-modal').addClass('show');
                $('body').addClass('modal-open');
            });
            
            // Confirm schedule update
            $('.bocs-confirm-pause-btn').on('click', function() {
                var nextPaymentDate = $('#next-payment-date').val();
                var reason = $('#pause-reason').val();
                
                if (!nextPaymentDate) {
                    self.showNotification('Please select a valid date', 'error');
                    return;
                }
                
                self.updateSchedule(nextPaymentDate, reason);
            });
        },
        
        // Update the product count display
        updateProductCount: function() {
            // Count products with quantity > 0
            let count = 0;
            let totalQuantity = 0;
            
            $('.product-item').each(function() {
                const quantity = parseInt($(this).find('.quantity-input').val(), 10) || 0;
                if (quantity > 0) {
                    count++;
                    totalQuantity += quantity;
                }
            });
            
            // Update all counter displays with total quantity
            // Find any element with the selected-product-count class
            $('.selected-product-count').text(totalQuantity);
            
            // Also update any text element that might have the format "Selected products: X"
            // This catches elements with different structures
            $('[class*="product"]').filter(function() {
                return $(this).text().match(/Selected products\s*:\s*\d+/);
            }).each(function() {
                const text = $(this).text().replace(/\d+/, totalQuantity);
                $(this).text(text);
            });
            
            // Check against min/max requirements
            let isValid = true;
            let validationMessage = '';
            let atMinLimit = this.minProducts > 0 && totalQuantity <= this.minProducts;
            let atMaxLimit = this.maxProducts > 0 && totalQuantity >= this.maxProducts;
            
            if (this.minProducts > 0 && totalQuantity < this.minProducts) {
                isValid = false;
                validationMessage = 'Please select at least ' + this.minProducts + ' products';
            } else if (this.maxProducts > 0 && totalQuantity > this.maxProducts) {
                isValid = false;
                validationMessage = 'Please select no more than ' + this.maxProducts + ' products';
            }
            
            // Show/hide validation message
            if (!isValid) {
                if ($('.product-selection-validation').length === 0) {
                    $('.product-selection-info').after('<div class="product-selection-validation">' + validationMessage + '</div>');
                } else {
                    $('.product-selection-validation').text(validationMessage).show();
                }
            } else {
                // Show warning messages for min/max limits
                if (atMaxLimit) {
                    if ($('.product-selection-validation').length === 0) {
                        $('.product-selection-info').after('<div class="product-selection-validation product-max-warning">Maximum quantity reached (' + this.maxProducts + ' products)</div>');
                    } else {
                        $('.product-selection-validation').text('Maximum quantity reached (' + this.maxProducts + ' products)').removeClass('product-min-warning').addClass('product-max-warning').show();
                    }
                } else if (atMinLimit) {
                    if ($('.product-selection-validation').length === 0) {
                        $('.product-selection-info').after('<div class="product-selection-validation product-min-warning">Minimum quantity reached (' + this.minProducts + ' products)</div>');
                    } else {
                        $('.product-selection-validation').text('Minimum quantity reached (' + this.minProducts + ' products)').removeClass('product-max-warning').addClass('product-min-warning').show();
                    }
                } else {
                    $('.product-selection-validation').hide();
                }
            }
            
            // Enable/disable plus/minus buttons based on min/max limits
            $('.product-item').each(function() {
                const $item = $(this);
                const $minusBtn = $item.find('.quantity-btn.minus');
                const $plusBtn = $item.find('.quantity-btn.plus');
                const currentQty = parseInt($item.find('.quantity-input').val(), 10) || 0;
                
                // For plus button, disable if at max limit
                if (atMaxLimit) {
                    $plusBtn.prop('disabled', true).addClass('disabled');
                    $plusBtn.attr('title', 'Maximum quantity reached');
                } else {
                    $plusBtn.prop('disabled', false).removeClass('disabled');
                    $plusBtn.removeAttr('title');
                }
                
                // For minus button, disable if at min limit and quantity is 1
                // Only disable if removing this item would put us below the minimum
                if (atMinLimit && currentQty === 1) {
                    $minusBtn.prop('disabled', true).addClass('disabled');
                    $minusBtn.attr('title', 'Minimum quantity reached');
                } else {
                    $minusBtn.prop('disabled', false).removeClass('disabled');
                    $minusBtn.removeAttr('title');
                }
            });
            
            // Enable/disable save button
            $('.bocs-save-products-btn').prop('disabled', count === 0 || !isValid);
            
            // Log the count for debugging
            console.log('Product count updated: ' + count + ' different products, ' + totalQuantity + ' total quantity, min: ' + this.minProducts + ', max: ' + this.maxProducts);
            
            return {
                count: count,
                totalQuantity: totalQuantity,
                isValid: isValid,
                atMinLimit: atMinLimit,
                atMaxLimit: atMaxLimit
            };
        },

        editProducts: function(event) {
            if (event) {
                event.preventDefault();
            }
            
            // Show the product modal
            const $modal = $('#bocs-product-modal');
            
            if ($modal.length === 0) {
                console.error('Product modal element not found');
                return;
            }
            
            // Clear previous content and show modal
            $('.product-selection-list').empty();
            $modal.addClass('show');
            $('body').addClass('modal-open');
            
            // Populate product selection
            this.populateProductSelection();
            
            // Update product count
            this.updateProductCount();
        },
        
        populateProductSelection: function() {
            var $container = $('.product-selection-list');
            if (!$container.length) {
                console.error('Product list container not found.');
                return;
            }
            
            $container.empty();
            
            // Create item lookup directly from API lineItems
            var itemLookup = {};
            
            // Use lineItems from the subscription data directly
            if (bocs_edit_details_data.subscription_items && bocs_edit_details_data.subscription_items.length) {
                bocs_edit_details_data.subscription_items.forEach(function(item) {
                    // BOCS API uses productId property for line items
                    if (item.productId) {
                        itemLookup[item.productId] = {
                            id: item.productId,
                            name: item.name || '',
                            quantity: parseInt(item.quantity) || 0,
                            price: parseFloat(item.price) || 0
                        };
                    }
                });
            }
            
            // Log the lookup table for debugging
            console.log('Product Lookup Table:', itemLookup);
            
            // Prepare sorted products
            var productsToShow = [];
            
            if (this.allProducts && this.allProducts.length) {
                // Sort products alphabetically by name
                productsToShow = this.allProducts.slice().sort(function(a, b) {
                    var nameA = a.name ? a.name.toLowerCase() : '';
                    var nameB = b.name ? b.name.toLowerCase() : '';
                    return nameA.localeCompare(nameB);
                });
            } else if (this.subscriptionItems && this.subscriptionItems.length) {
                // Fallback to use subscription items if no products are available
                productsToShow = this.subscriptionItems;
            }
            
            if (!productsToShow.length) {
                console.error('No products available to display.');
                $container.html('<p>No products available</p>');
                return;
            }
            
            // Build product HTML
            var html = '';
            
            // Map products to subscription items and display
            $.each(productsToShow, function(index, product) {
                var quantity = 0;
                
                // Check if this product is in the subscription using lineItems data
                if (itemLookup[product.id]) {
                    quantity = itemLookup[product.id].quantity;
                }
                
                var productImage = product.image || '';
                var productName = product.name || 'Product ' + (index + 1);
                var productPrice = product.price_html || '$0.00';
                
                html += '<div class="product-item" data-product-id="' + product.id + '">';
                html += '<div class="product-image"><img src="' + productImage + '" alt="' + productName + '"></div>';
                html += '<div class="product-details">';
                html += '<h4>' + productName + '</h4>';
                html += '<p class="product-price">' + productPrice + '</p>';
                html += '</div>';
                html += '<div class="product-quantity">';
                html += '<button type="button" class="quantity-btn minus">-</button>';
                html += '<input type="number" class="quantity-input" value="' + quantity + '" min="0" data-original="' + quantity + '">';
                html += '<button type="button" class="quantity-btn plus">+</button>';
                html += '</div>';
                html += '</div>';
            });
            
            $container.html(html);
        },

        updateProducts: function() {
            var self = this;
            const $button = $('.bocs-save-products-btn');
            
            // Verify product count against min/max requirements
            const productCountInfo = this.updateProductCount();
            if (!productCountInfo.isValid) {
                // Show appropriate error message based on min/max
                let errorMessage = '';
                if (productCountInfo.totalQuantity < this.minProducts) {
                    errorMessage = 'Please select at least ' + this.minProducts + ' products total';
                } else if (productCountInfo.totalQuantity > this.maxProducts) {
                    errorMessage = 'Please select no more than ' + this.maxProducts + ' products total';
                }
                
                this.showNotification(errorMessage, 'error');
                return;
            }
            
            // Disable button and show loading state
            $button.prop('disabled', true);
            $button.find('.loading-spinner').show();
            $button.find('.button-text').text('Updating...');
            
            // Get selected products from the checkboxes and input fields
            const productsToUpdate = [];
            let totalQuantity = 0;
            
            $('.product-item').each(function() {
                const $item = $(this);
                const quantity = parseInt($item.find('.quantity-input').val(), 10) || 0;
                totalQuantity += quantity;
                
                // Only include products with quantity > 0
                if (quantity > 0) {
                    // Get the product ID
                    const productId = $item.data('product-id');
                    
                    // Get the price - either from data attribute or default to 45
                    const price = parseFloat($item.data('price') || 45);
                    
                    // Calculate subtotal (price * quantity)
                    const subtotal = price * quantity;
                    
                    // Get discount if available from bocs_edit_details_data
                    let discountPercent = 0;
                    if (typeof bocs_edit_details_data !== 'undefined' && 
                        bocs_edit_details_data.frequency &&
                        bocs_edit_details_data.frequency.discount) {
                        discountPercent = parseFloat(bocs_edit_details_data.frequency.discount);
                    }
                    
                    // Calculate total with discount
                    const discountFactor = 1 - (discountPercent / 100);
                    const total = subtotal * discountFactor;
                    
                    // Calculate tax - assume 10% for now
                    const taxRate = 0.1;
                    const subtotalTax = subtotal * taxRate;
                    const totalTax = total * taxRate;
                    
                    // Get product name
                    const name = $item.find('.product-details h4').text().trim();
                    
                    // Log what we're adding
                    console.log('Adding product to update:', {
                        id: productId,
                        name: name,
                        price: price,
                        quantity: quantity
                    });
                    
                    // Create complete product object with all required fields
                    // IMPORTANT: Set ALL fields explicitly here
                    productsToUpdate.push({
                        id: productId,
                        productId: productId, // Make sure both id and productId are set
                        quantity: quantity,
                        name: name,
                        price: price,
                        subtotal: subtotal,
                        total: total,
                        taxClass: "", // Required field - empty string
                        taxes: [], // Required field - empty array
                        totalTax: totalTax,
                        subtotalTax: subtotalTax,
                        metaData: [], // Required field - empty array
                        parentName: "", // Required field - empty string
                        variationId: "0", // Required field - set to "0"
                        sku: "", // Required field - empty string
                        externalSourceId: $item.data('external-source-id') || "" // Include if available
                    });
                }
            });
            
            // Check if any products were selected
            if (productsToUpdate.length === 0) {
                this.showNotification('Please select at least one product', 'error');
                $button.prop('disabled', false);
                $button.find('.button-text').text('Save Products');
                $button.find('.loading-spinner').hide();
                return;
            }
            
            // Double-check total quantity against min/max again
            if (this.minProducts > 0 && totalQuantity < this.minProducts) {
                this.showNotification('Please select at least ' + this.minProducts + ' products total', 'error');
                $button.prop('disabled', false);
                $button.find('.button-text').text('Save Products');
                $button.find('.loading-spinner').hide();
                return;
            }
            
            if (this.maxProducts > 0 && totalQuantity > this.maxProducts) {
                this.showNotification('Please select no more than ' + this.maxProducts + ' products total', 'error');
                $button.prop('disabled', false);
                $button.find('.button-text').text('Save Products');
                $button.find('.loading-spinner').hide();
                return;
            }
            
            // Log the products being sent to the API
            console.log('Sending products to API:', productsToUpdate);
            
            // Send AJAX request to update products
            $.ajax({
                url: bocs_edit_details_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'bocs_update_subscription_products',
                    subscription_id: this.subscriptionId,
                    products: JSON.stringify(productsToUpdate),
                    nonce: bocs_edit_details_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success notification
                        self.showNotification('Products updated successfully', 'success');
                        
                        // Close modal
                        $('#bocs-product-modal').removeClass('show');
                        $('body').removeClass('modal-open');
                        
                        // Reload page after delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error notification with detailed message
                        let errorMessage = 'Failed to update products';
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        } else if (response.data) {
                            errorMessage = JSON.stringify(response.data);
                        }
                        self.showNotification(errorMessage, 'error');
                        
                        $button.prop('disabled', false);
                        $button.find('.button-text').text('Save Products');
                        $button.find('.loading-spinner').hide();
                    }
                },
                error: function(xhr) {
                    self.showNotification('Network error. Please try again.', 'error');
                    $button.prop('disabled', false);
                    $button.find('.button-text').text('Save Products');
                    $button.find('.loading-spinner').hide();
                }
            });
        },
        
        updateSchedule: function(nextPaymentDate, reason) {
            var self = this;
            
            // Show loading notification
            this.showNotification('Updating schedule...', 'loading');
            
            // Make AJAX request to update schedule
            $.ajax({
                url: bocs_edit_details_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'bocs_update_subscription',
                    subscription_id: this.subscriptionId,
                    update_type: 'schedule',
                    next_payment_date: nextPaymentDate,
                    reason: reason,
                    nonce: bocs_edit_details_data.edit_details_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('Schedule updated successfully', 'success');
                        
                        // Close modal
                        $('#bocs-pause-modal').removeClass('show');
                        $('body').removeClass('modal-open');
                        
                        // Reload page after success
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        self.showNotification(response.message || 'Failed to update schedule', 'error');
                    }
                },
                error: function(xhr) {
                    self.showNotification('Network error. Please try again.', 'error');
                }
            });
        },

        showNotification: function(message, type = 'info', duration = 3000) {
            // Remove any existing notifications
            $('.bocs-notification').remove();
            
            // Create new notification element
            var $notification = $('<div class="bocs-notification ' + type + '">' + 
                '<span class="message">' + message + '</span>' +
                (type === 'loading' ? '<div class="loading-spinner"><svg class="spinner" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg></div>' : '') +
                '</div>');
            
            // Add to body
            $('body').append($notification);
            
            // Show with animation
            setTimeout(function() {
                $notification.addClass('show');
            }, 10);
            
            // Auto-hide for non-loading notifications
            if (type !== 'loading') {
                setTimeout(function() {
                    $notification.removeClass('show');
                    setTimeout(function() {
                        $notification.remove();
                    }, 300);
                }, duration);
            }
            
            return $notification;
        },

        initializeProductSelection: function() {
            // Initialize any additional product selection functionality
            this.updateProductCount();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BocsEditDetails.init();
    });

})(jQuery);