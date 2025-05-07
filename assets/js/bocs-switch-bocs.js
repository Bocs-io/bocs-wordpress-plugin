/**
 * BOCS Switch Box JavaScript
 * 
 * Handles the functionality for the box switching interface.
 */

(function($) {
    'use strict';
    
    // Main controller object
    const BocsSwitchBox = {
        // State variables
        state: {
            selectedBocsId: null,
            selectedBocsName: null,
            selectedFrequencyId: null,
            selectedFrequency: null,
            currentBocsProducts: [],
            selectedProducts: [],
            bocsById: {},
            frequencyOptions: [],
            isCustomBox: false
        },
        
        // Initialize the module
        init: function() {
            // Cache the box data by ID for quick access
            this.cacheBocsByID();
            
            // Setup event handlers
            this.setupEventHandlers();
            
            // Set up modals
            this.setupModals();
            
            console.log('BOCS Switch Box initialized');
        },
        
        // Cache box data by ID for quick access
        cacheBocsByID: function() {
            // If we're using AJAX to fetch box data, this would process the response
            // For now, assume we have box data available on page load
            if (typeof bocsSwitchData !== 'undefined') {
                // Already set up in the PHP template
                this.state.currentBocsId = bocsSwitchData.currentBocsId;
            }
        },
        
        // Set up event handlers
        setupEventHandlers: function() {
            // Box selection
            $('.select-bocs-button').on('click', this.handlers.selectBox.bind(this));
            
            // Modal handlers
            $(document).on('click', '.bocs-modal-close, .bocs-modal .cancel', this.handlers.closeModal);
            
            // Frequency selection handlers
            $(document).on('click', '.frequency-option', this.handlers.selectFrequency.bind(this));
            $(document).on('click', '.confirm-frequency', this.handlers.confirmFrequency.bind(this));
            
            // Product selection handlers
            $(document).on('click', '#confirm-products', this.handlers.confirmProducts.bind(this));
            
            // Confirmation dialog handlers
            $(document).on('click', '.confirm-switch', this.handlers.confirmSwitch.bind(this));
            
            // Set up click handlers for the BOCS selection
            $('.bocs-switch-item').on('click', function(e) {
                e.preventDefault();
                
                // Get BOCS ID and name
                const bocsId = $(this).data('bocs-id');
                const bocsName = $(this).find('.bocs-name').text();
                const bocsData = $(this).data('bocs-data');
                
                // Store selected BOCS info
                BocsSwitchBox.state.selectedBocsId = bocsId;
                BocsSwitchBox.state.selectedBocsName = bocsName;
                BocsSwitchBox.state.selectedBocsData = bocsData;
                
                // Display confirmation modal
                const confirmationMessage = `Are you sure you want to switch to the ${bocsName}?`;
                $('#bocs-switch-confirmation-message').text(confirmationMessage);
                BocsSwitchBox.showModal('#bocs-switch-confirmation');
            });
        },
        
        // Set up modals
        setupModals: function() {
            // No need for jQuery UI dialog, using custom modals
        },
        
        // Load frequency options
        loadFrequencyOptions: function(bocsId, bocsName) {
            // Show loading overlay
            $('.bocs-loading-overlay').fadeIn(200);
            
            // Set the bocs name in the frequency dialog
            $('#frequency-bocs-name').text(bocsName);
            
            const frequencyContainer = $('.frequency-options');
            frequencyContainer.empty();
            
            // Get selected BOCS with adjustments (frequencies)
            const availableBoxes = typeof bocsSwitchData !== 'undefined' ? bocsSwitchData.availableBoxes : [];
            const selectedBox = availableBoxes.find(box => box.id === bocsId);
            
            console.log('Selected BOCS:', selectedBox);
            console.log('Is custom box flag:', this.state.isCustomBox);
            
            if (!selectedBox || !selectedBox.priceAdjustment || !selectedBox.priceAdjustment.adjustments || selectedBox.priceAdjustment.adjustments.length === 0) {
                // Hide loading overlay
                $('.bocs-loading-overlay').fadeOut(200);
                
                // Show error message if no frequency options available
                this.showErrorMessage('No frequency options available for this box.');
            } else {
                // We have frequency options in the BOCS data, use them
                const frequencies = selectedBox.priceAdjustment.adjustments;
                
                // Hide loading overlay
                $('.bocs-loading-overlay').fadeOut(200);
                
                // Set frequency options
                this.state.frequencyOptions = frequencies;
                
                // Populate frequency options
                this.populateFrequencyOptions(frequencies);
                
                // Show the frequency dialog
                this.showModal('#frequency-selection-dialog');
            }
        },
        
        // Load product options for custom box
        loadProductOptions: function(bocsId) {
            // Show loading overlay
            $('.bocs-loading-overlay').fadeIn(200);
            
            const productContainer = $('#product-selection-content');
            productContainer.empty();
            this.state.selectedProducts = [];
            
            // Get the BOCS details
            const availableBoxes = typeof bocsSwitchData !== 'undefined' ? bocsSwitchData.availableBoxes : [];
            const selectedBox = availableBoxes.find(box => box.id === bocsId);
            
            if (!selectedBox) {
                $('.bocs-loading-overlay').fadeOut(200);
                this.showErrorMessage('Box details not found.');
                return;
            }
            
            // Set min/max products based on range
            let minProducts = 6; // Default minimum
            let maxProducts = 12; // Default maximum
            
            if (selectedBox.range && Array.isArray(selectedBox.range) && selectedBox.range.length >= 2) {
                minProducts = parseInt(selectedBox.range[0]) || 6;
                maxProducts = parseInt(selectedBox.range[1]) || 12;
            }
            
            $("#min-products").text(minProducts);
            $("#max-products").text(maxProducts);
            
            // Update header info
            // Set title to reflect the required range
            $("#product-selection-dialog h3").text(`Select Products (${minProducts}-${maxProducts})`);
            
            // Fetch products for the box via AJAX
            $.ajax({
                url: bocsSwitchData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bocs_get_box_products',
                    bocs_id: bocsId,
                    nonce: bocsSwitchData.nonce
                },
                success: (response) => {
                    $('.bocs-loading-overlay').fadeOut(200);
                    
                    if (response.success && response.data && response.data.products) {
                        const products = response.data.products;
                        
                        if (products.length === 0) {
                            productContainer.html('<p>No products available for this box.</p>');
                            return;
                        }
                        
                        // Build product options
                        products.forEach(product => {
                            // Initialize product in selected products array
                            this.state.selectedProducts.push({
                                id: product.id,
                                name: product.name,
                                price: parseFloat(product.price) || 0,
                                quantity: 0
                            });
                            
                            // Get image URL or use placeholder
                            const productImage = product.images && product.images.length > 0 && product.images[0].url
                                ? product.images[0].url
                                : '';
                            
                            // Create product option element
                            const productOption = $(`
                                <div class="product-option" data-product-id="${product.id}">
                                    <div class="product-info">
                                        ${productImage ? `<div class="product-image"><img src="${productImage}" alt="${product.name}"></div>` : ''}
                                        <div class="product-details">
                                            <h4 class="product-name">${product.name}</h4>
                                            <div class="product-price">$${parseFloat(product.price).toFixed(2)}</div>
                                            ${product.description ? `<p class="product-description">${product.description}</p>` : ''}
                                        </div>
                                    </div>
                                    <div class="product-quantity">
                                        <button class="quantity-btn minus" data-product-id="${product.id}">-</button>
                                        <input type="number" class="quantity-input" value="0" min="0" max="99" data-product-id="${product.id}">
                                        <button class="quantity-btn plus" data-product-id="${product.id}">+</button>
                                    </div>
                                </div>
                            `);
                            
                            productContainer.append(productOption);
                        });
                        
                        // Add event handlers for quantity buttons
                        $('.quantity-btn.minus').on('click', function() {
                            const productId = $(this).data('product-id');
                            const input = $(`.quantity-input[data-product-id="${productId}"]`);
                            let value = parseInt(input.val()) || 0;
                            
                            if (value > 0) {
                                value--;
                                input.val(value);
                                BocsSwitchBox.updateProductQuantity(productId, value);
                            }
                        });
                        
                        $('.quantity-btn.plus').on('click', function() {
                            const productId = $(this).data('product-id');
                            const input = $(`.quantity-input[data-product-id="${productId}"]`);
                            let value = parseInt(input.val()) || 0;
                            
                            // Calculate current total selected products count
                            const totalSelected = BocsSwitchBox.state.selectedProducts.reduce((total, product) => 
                                total + product.quantity, 0);
                            
                            // Don't allow adding more products if already at max
                            const maxProducts = parseInt($("#max-products").text());
                            if (totalSelected >= maxProducts) {
                                BocsSwitchBox.showErrorMessage(`You can select a maximum of ${maxProducts} products.`);
                                return;
                            }
                            
                            value++;
                            input.val(value);
                            BocsSwitchBox.updateProductQuantity(productId, value);
                        });
                        
                        $('.quantity-input').on('change', function() {
                            const productId = $(this).data('product-id');
                            let value = parseInt($(this).val()) || 0;
                            
                            if (value < 0) {
                                value = 0;
                                $(this).val(value);
                            }
                            
                            // Calculate how many products would be selected with this change
                            const currentProductQuantity = BocsSwitchBox.state.selectedProducts.find(p => p.id === productId)?.quantity || 0;
                            const otherProductsCount = BocsSwitchBox.state.selectedProducts.reduce((total, product) => 
                                total + (product.id !== productId ? product.quantity : 0), 0);
                            const newTotal = otherProductsCount + value;
                            
                            // Check if it exceeds max
                            const maxProducts = parseInt($("#max-products").text());
                            if (newTotal > maxProducts) {
                                value = maxProducts - otherProductsCount;
                                $(this).val(value);
                                BocsSwitchBox.showErrorMessage(`You can select a maximum of ${maxProducts} products.`);
                            }
                            
                            BocsSwitchBox.updateProductQuantity(productId, value);
                        });
                        
                        // Show the product selection dialog
                        BocsSwitchBox.showModal('#product-selection-dialog');
                        
                        // Update product count display
                        BocsSwitchBox.updateProductCount();
                    } else {
                        const message = response.data && response.data.message 
                            ? response.data.message 
                            : 'Failed to load products. Please try again.';
                        BocsSwitchBox.showErrorMessage(message);
                    }
                },
                error: (xhr) => {
                    $('.bocs-loading-overlay').fadeOut(200);
                    BocsSwitchBox.showErrorMessage('Failed to load products. Please try again.');
                }
            });
        },
        
        // Update product quantity
        updateProductQuantity: function(productId, quantity) {
            const index = this.state.selectedProducts.findIndex(p => p.id === productId);
            if (index !== -1) {
                this.state.selectedProducts[index].quantity = quantity;
                this.updateProductCount();
            }
        },
        
        // Update product count display
        updateProductCount: function() {
            const count = this.state.selectedProducts.reduce((total, product) => total + product.quantity, 0);
            $("#product-count").text(count);
            
            // Check if we have minimum required products and don't exceed maximum
            const minProducts = parseInt($("#min-products").text());
            const maxProducts = parseInt($("#max-products").text());
            const confirmBtn = $("#confirm-products");
            
            if (count >= minProducts && count <= maxProducts) {
                confirmBtn.prop('disabled', false);
            } else {
                confirmBtn.prop('disabled', true);
            }
            
            // Update the product selection info display
            if (count < minProducts) {
                $(".product-selection-info").addClass("insufficient").removeClass("excessive");
            } else if (count > maxProducts) {
                $(".product-selection-info").removeClass("insufficient").addClass("excessive");
            } else {
                $(".product-selection-info").removeClass("insufficient excessive");
            }
        },
        
        // Event handlers
        handlers: {
            // Select box handler
            selectBox: function(e) {
                e.preventDefault();
                
                const bocsId = $(e.currentTarget).data('bocs-id');
                const bocsName = $(e.currentTarget).data('bocs-name');
                const bocsType = $(e.currentTarget).data('bocs-type');
                
                this.state.selectedBocsId = bocsId;
                this.state.selectedBocsName = bocsName;
                this.state.isCustomBox = (bocsType === 'custom');
                
                console.log('Selected box:', bocsId, bocsName, 'Type:', bocsType, 'Is Custom:', this.state.isCustomBox);
                
                // Load frequency options and show frequency dialog
                BocsSwitchBox.loadFrequencyOptions(bocsId, bocsName);
            },
            
            // Select frequency handler
            selectFrequency: function(e) {
                const $option = $(e.currentTarget);
                
                // Remove selected class from all options
                $('.frequency-option').removeClass('selected');
                
                // Add selected class to clicked option
                $option.addClass('selected');
                
                // Store selected frequency data
                this.state.selectedFrequencyId = $option.data('frequency-id');
                this.state.selectedFrequency = $option.data('frequency-text');
            },
            
            // Confirm frequency selection
            confirmFrequency: function() {
                // Check if a frequency is selected
                if (!this.state.selectedFrequencyId) {
                    BocsSwitchBox.showErrorMessage('Please select a frequency option.');
                    return;
                }
                
                // Close frequency dialog
                BocsSwitchBox.closeModal();
                
                // Check if this is a custom box
                console.log('Confirming frequency - isCustomBox:', BocsSwitchBox.state.isCustomBox);
                
                // If this is a custom box, show product selection dialog
                if (BocsSwitchBox.state.isCustomBox) {
                    console.log('Loading product options for custom box:', this.state.selectedBocsId);
                    // Load product options and show product dialog
                    BocsSwitchBox.loadProductOptions(this.state.selectedBocsId);
                } else {
                    console.log('Not a custom box, proceeding to confirmation');
                    // Not a custom box, proceed to confirmation dialog
                    // Set the target bocs name and frequency in confirmation dialog
                    $('#target-bocs-name').text(this.state.selectedBocsName);
                    $('#target-frequency').text(this.state.selectedFrequency);
                    
                    // Show confirmation dialog
                    BocsSwitchBox.showModal('#switch-confirmation-dialog');
                }
            },
            
            // Confirm product selection
            confirmProducts: function() {
                // Validate product count before proceeding
                const count = BocsSwitchBox.state.selectedProducts.reduce((total, product) => total + product.quantity, 0);
                const minProducts = parseInt($("#min-products").text());
                const maxProducts = parseInt($("#max-products").text());
                
                if (count < minProducts) {
                    BocsSwitchBox.showErrorMessage(`Please select at least ${minProducts} products.`);
                    return;
                }
                
                if (count > maxProducts) {
                    BocsSwitchBox.showErrorMessage(`Please select no more than ${maxProducts} products.`);
                    return;
                }
                
                // Close product dialog
                BocsSwitchBox.closeModal();
                
                // Set the target bocs name and frequency in confirmation dialog
                $('#target-bocs-name').text(this.state.selectedBocsName);
                $('#target-frequency').text(this.state.selectedFrequency);
                
                // Show confirmation dialog
                BocsSwitchBox.showModal('#switch-confirmation-dialog');
            },
            
            // Confirm switch handler
            confirmSwitch: function() {
                // Close confirmation dialog
                BocsSwitchBox.closeModal();
                
                // Show loading overlay
                $('.bocs-loading-overlay').fadeIn(200);
                
                // Prepare request data
                const requestData = {
                    bocsId: this.state.selectedBocsId,
                    frequencyId: this.state.selectedFrequencyId
                };
                
                // Add selected products if this is a custom box
                if (BocsSwitchBox.state.isCustomBox) {
                    requestData.products = BocsSwitchBox.state.selectedProducts
                        .filter(product => product.quantity > 0)
                        .map(product => ({
                            id: product.id,
                            quantity: product.quantity
                        }));
                }
                
                // Log the request data for debugging
                console.log('API Request Data:', requestData);
                
                // Make API request through WordPress AJAX to avoid CORS issues
                $.ajax({
                    url: bocsSwitchData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bocs_update_subscription',
                        subscription_id: bocsSwitchData.subscriptionId,
                        bocs_id: this.state.selectedBocsId,
                        frequency_id: this.state.selectedFrequencyId,
                        products: BocsSwitchBox.state.isCustomBox ? JSON.stringify(BocsSwitchBox.state.selectedProducts.filter(product => product.quantity > 0)) : JSON.stringify([]),
                        nonce: bocsSwitchData.nonce
                    },
                    success: (response) => {
                        // Hide loading overlay
                        $('.bocs-loading-overlay').fadeOut(200);
                        
                        // Check if successful
                        if (response.success) {
                            // Show success message
                            const successMessage = 'Your subscription has been successfully switched to ' + 
                                this.state.selectedBocsName + '. ' +
                                'You will be redirected to your subscriptions in a few seconds.';
                            
                            BocsSwitchBox.showSuccessMessage(successMessage);
                            
                            // Redirect after delay
                            setTimeout(() => {
                                window.location.href = BocsSwitchBox.getSubscriptionsUrl();
                            }, 3000);
                        } else {
                            // Show error message from API
                            const errorMsg = response.data && response.data.message 
                                ? response.data.message 
                                : 'There was an error processing your request. Please try again.';
                            
                            BocsSwitchBox.showErrorMessage(errorMsg);
                        }
                    },
                    error: (xhr) => {
                        // Hide loading overlay
                        $('.bocs-loading-overlay').fadeOut(200);
                        
                        // Error handling
                        let errorMsg = 'There was an error processing your request. Please try again.';
                        
                        // Try to get more specific error message from response
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            try {
                                const errorData = JSON.parse(xhr.responseText);
                                if (errorData.message) {
                                    errorMsg = errorData.message;
                                }
                            } catch (e) {
                                // Parsing error, use default message
                            }
                        }
                        
                        BocsSwitchBox.showErrorMessage(errorMsg);
                    }
                });
            },
            
            // Generic close modal handler
            closeModal: function() {
                $('.bocs-modal').fadeOut(200);
            }
        },
        
        // Helper methods
        
        // Show modal
        showModal: function(selector) {
            $(selector).fadeIn(200);
        },
        
        // Close modal
        closeModal: function() {
            $('.bocs-modal').fadeOut(200);
        },
        
        // Populate frequency options
        populateFrequencyOptions: function(options) {
            const $container = $('.frequency-options');
            $container.empty();
            
            options.forEach(option => {
                // Create option element
                const $option = $(`
                    <div class="frequency-option" data-frequency-id="${option.id}" data-frequency-text="${option.frequency} ${option.timeUnit}">
                        <div class="frequency-info">
                            <div class="frequency-text">${option.frequency} ${option.timeUnit}</div>
                            ${option.discount > 0 ? `<div class="frequency-discount">${option.discountType === 'DOLLAR' ? '$' + option.discount : option.discount + '%'} discount</div>` : ''}
                        </div>
                        <div class="dashicons dashicons-yes-alt"></div>
                    </div>
                `);
                $container.append($option);
            });
        },
        
        // Show success message
        showSuccessMessage: function(message) {
            // Remove any existing messages
            $('.woocommerce-message, .woocommerce-error').remove();
            
            // Create success element
            const $success = $(`
                <div class="woocommerce-message">
                    ${message}
                </div>
            `);
            
            // Add to page
            $('.bocs-switch-container').prepend($success);
            
            // Scroll to success
            $('html, body').animate({
                scrollTop: $success.offset().top - 100
            }, 500);
        },
        
        // Show error message
        showErrorMessage: function(message) {
            // Remove any existing messages
            $('.woocommerce-error').remove();
            
            // Create error element
            const $error = $(`
                <div class="woocommerce-error">
                    ${message}
                </div>
            `);
            
            // Add to page
            $('.bocs-switch-container').prepend($error);
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $error.offset().top - 100
            }, 500);
        },
        
        // Get subscriptions URL
        getSubscriptionsUrl: function() {
            // Return to the same page (refresh with the same subscription ID)
            return '/my-account/bocs-switch-bocs/' + bocsSwitchData.subscriptionId + '/';
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        BocsSwitchBox.init();
    });
    
})(jQuery);