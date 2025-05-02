/**
 * BOCS Button Loading State Handler
 * Improves the loading state experience for subscription buttons
 */

(function() {
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        initSubscriptionButtonHandlers();
    });

    // Also try on window load for dynamically loaded content
    window.addEventListener('load', function() {
        initSubscriptionButtonHandlers();
    });

    /**
     * Initialize subscription button handlers
     */
    function initSubscriptionButtonHandlers() {
        // Find all subscription buttons
        const subscriptionButtons = document.querySelectorAll('.create-subscription-btn');

        if (subscriptionButtons.length === 0) {
            // Try again in 500ms if buttons aren't found (they might be loaded dynamically)
            setTimeout(initSubscriptionButtonHandlers, 500);
            return;
        }

        // Add click handlers to each button
        subscriptionButtons.forEach(button => {
            // Skip if already initialized
            if (button.dataset.initialized === 'true') return;

            // Mark as initialized
            button.dataset.initialized = 'true';

            // Store original text
            const originalText = button.textContent || 'Create my subscription';
            button.dataset.originalText = originalText;

            // Add click handler
            button.addEventListener('click', function(e) {
                // Only handle if not already loading
                if (!button.classList.contains('loading') && !button.disabled) {
                    // Add loading state
                    setButtonLoading(button, true);

                    // We don't prevent default here to allow the form submission to proceed
                }
            });
        });

        // Also handle form submissions
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const subscriptionButton = form.querySelector('.create-subscription-btn');
            if (subscriptionButton && !form.dataset.bocsHandlerAttached) {
                form.dataset.bocsHandlerAttached = 'true';

                form.addEventListener('submit', function() {
                    setButtonLoading(subscriptionButton, true);
                });
            }
        });
    }

    /**
     * Set button loading state
     * @param {HTMLElement} button - The button element
     * @param {boolean} isLoading - Whether to set loading state or remove it
     */
    function setButtonLoading(button, isLoading) {
        if (isLoading) {
            // Check if button has text content
            const buttonText = button.querySelector('.button-text');

            // If no .button-text element exists, wrap the text content in a span
            if (!buttonText && !button.querySelector('svg')) {
                const originalText = button.innerHTML;
                button.innerHTML = `<span class="button-text">${originalText}</span>`;
            }

            // Check for existing spinner
            let spinner = button.querySelector('svg');

            // If no spinner exists, create one
            if (!spinner) {
                const spinnerSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                spinnerSvg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
                spinnerSvg.setAttribute('width', '24');
                spinnerSvg.setAttribute('height', '24');
                spinnerSvg.setAttribute('viewBox', '0 0 24 24');
                spinnerSvg.setAttribute('fill', 'none');
                spinnerSvg.setAttribute('stroke', 'currentColor');
                spinnerSvg.setAttribute('stroke-width', '2');
                spinnerSvg.setAttribute('stroke-linecap', 'round');
                spinnerSvg.setAttribute('stroke-linejoin', 'round');
                spinnerSvg.setAttribute('class', 'bocs-spinner');

                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', 'M21 12a9 9 0 1 1-6.219-8.56');

                spinnerSvg.appendChild(path);
                button.appendChild(spinnerSvg);
            }

            // Add loading class
            button.classList.add('loading');

            // Disable button
            button.disabled = true;

            // Set aria attributes for accessibility
            button.setAttribute('aria-busy', 'true');
        } else {
            // Remove loading class
            button.classList.remove('loading');

            // Re-enable button
            button.disabled = false;

            // Update aria attributes
            button.setAttribute('aria-busy', 'false');
        }
    }

    // Expose function globally for other scripts to use
    window.bocsSetButtonLoading = setButtonLoading;
})();
