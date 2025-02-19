let stripe = null;
let elements = null;
let paymentElement = null;

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
        
        this.bindEvents();
    },

    bindEvents: function() {
        console.log('Binding events...'); // Debug log
        jQuery(document).on('click', '.edit-payment-method', (e) => {
            console.log('Edit payment method clicked'); // Debug log
            this.handleEditPaymentMethod(e);
        });
    },

    handleEditPaymentMethod: async function(e) {
        e.preventDefault();
        console.log('Handling edit payment method...'); // Debug log
        
        // Debug modal styles
        const modal = jQuery('#edit-payment-method-modal');
        console.log('Modal element:', modal.length ? 'found' : 'not found');
        console.log('Modal current styles:', {
            display: modal.css('display'),
            position: modal.css('position'),
            zIndex: modal.css('z-index')
        });
        
        const button = jQuery(e.currentTarget);
        const subscriptionId = button.attr('data-subscription-id'); // Changed from data() to attr()
        
        console.log('Subscription ID:', subscriptionId); // Debug log
        
        if (!subscriptionId) {
            console.error('No subscription ID found');
            return;
        }

        try {
            console.log('Sending AJAX request...'); // Debug log
            const response = await jQuery.ajax({
                url: bocsPaymentData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bocs_get_stripe_setup',
                    subscription_id: subscriptionId,
                    nonce: bocsPaymentData.nonce
                }
            });

            console.log('AJAX response:', response); // Debug log

            if (!response.success) {
                throw new Error(response.data.message || bocsPaymentData.i18n.errorGeneric);
            }

            // Initialize Stripe only once
            if (!stripe) {
                console.log('Initializing Stripe with key:', response.data.publishable_key); // Debug log
                stripe = Stripe(response.data.publishable_key);
            }

            // Show modal and handle payment method selection
            this.showPaymentModal(response.data);

        } catch (error) {
            console.error('Error showing payment method modal:', error);
        }
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
                const response = await jQuery.ajax({
                    url: bocsPaymentData.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'bocs_update_subscription_payment',
                        payment_method: selectedMethod,
                        subscription_id: setupData.subscription_id,
                        nonce: bocsPaymentData.nonce
                    }
                });

                if (!response.success) {
                    throw new Error(response.data.message || bocsPaymentData.i18n.errorGeneric);
                }

                // Show success message and close modal
                jQuery('#edit-payment-method-modal').fadeOut(200);
                
                // Reload the page after success
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }

        } catch (error) {
            console.error('Error:', error);
            errorElement.text(error.message);
            submitButton.prop('disabled', false)
                       .text(bocsPaymentData.i18n.updatePaymentMethod);
        }
    }
};

// Initialize when document is ready
jQuery(document).ready(function() {
    console.log('Document ready, initializing payment methods...'); // Debug log
    bocsPaymentMethods.init();
}); 