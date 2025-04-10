<?php
/**
 * BOCS Subscriptions Account Template
 * 
 * This template handles the display and management of BOCS subscriptions in the user's account area.
 * It provides functionality for viewing subscription details, editing frequencies, and managing subscription settings.
 *
 * @package BOCS
 * @subpackage Templates
 */

// Initialize required WordPress/WooCommerce resources
wp_enqueue_script('jquery-ui-accordion');
wp_enqueue_style('wp-jquery-ui-dialog');

// Add custom JavaScript for accordion initialization
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
        (function($) {
            // Function to initialize accordion
            function initializeAccordion() {
                // First hide all content
                $('.accordion-content').hide();
                
                // Remove any existing accordion
                if ($("#bocs-subscriptions-accordion").hasClass('ui-accordion')) {
                    $("#bocs-subscriptions-accordion").accordion('destroy');
                }
                
                // Initialize accordion
                $("#bocs-subscriptions-accordion").accordion({
                    collapsible: true,
                    active: false,
                    heightStyle: "content",
                    header: "> div > h3.accordion-header",
                    animate: 200,
                    beforeActivate: function(event, ui) {
                        // Toggle arrow direction
                        if (ui.newHeader.length > 0) {
                            ui.oldHeader.find('.accordion-arrow').html('&#9660;');
                            ui.newHeader.find('.accordion-arrow').html('&#9650;');
                        } else {
                            ui.oldHeader.find('.accordion-arrow').html('&#9660;');
                        }
                    }
                });
                
                // Add arrow indicators if they don't exist
                $('.accordion-header').each(function() {
                    if ($(this).find('.accordion-arrow').length === 0) {
                        $(this).append('<span class="accordion-arrow">&#9660;</span>');
                    }
                });
            }

            // Initialize on document ready
            $(document).ready(function() {
                initializeAccordion();
            });

            // Initialize again after a short delay to ensure all content is loaded
            $(window).on('load', function() {
                setTimeout(initializeAccordion, 100);
            });
        })(jQuery);
    </script>
    <style>
        /* Base Font and Typography */
        :root {
            --bocs-font-primary: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            --bocs-font-secondary: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --bocs-font-mono: 'SF Mono', SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace;
            --bocs-primary: #0065A9;
            --bocs-secondary: #00A5B5;
            --bocs-accent: #FFCC00;
            --bocs-text: #333333;
            --bocs-text-light: #666666;
            --bocs-light-bg: #F9FAFB;
            --bocs-border: #E5E7EB;
            --bocs-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --bocs-error: #E53E3E;
            --bocs-success: #38A169;
            --bocs-radius: 8px;
            --bocs-transition: all 0.3s ease;
        }

        .woocommerce-subscriptions-wrapper {
            font-family: var(--bocs-font-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.6;
            color: var(--bocs-text);
            letter-spacing: -0.011em;
        }

        /* Typography Improvements */
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--bocs-font-secondary);
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 1rem;
            color: var(--bocs-text);
        }

        .subscription-title {
            font-family: var(--bocs-font-secondary);
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--bocs-primary);
            letter-spacing: -0.02em;
        }

        .subscription-amount {
            font-family: var(--bocs-font-mono);
            font-weight: 500;
            color: var(--bocs-secondary);
        }

        /* Button Typography */
        .woocommerce-button.button,
        .bocs-button {
            font-family: var(--bocs-font-primary);
            font-size: 0.9375rem;
            font-weight: 500;
            letter-spacing: -0.01em;
            text-transform: none;
        }

        /* Status Labels */
        .subscription-status {
            font-family: var(--bocs-font-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        /* Form Elements */
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        select,
        textarea {
            font-family: var(--bocs-font-primary);
            font-size: 1rem;
            line-height: 1.5;
            color: var(--bocs-text);
            padding: 0.75rem 1rem;
            border: 1px solid var(--bocs-border);
            border-radius: var(--bocs-radius);
            transition: var(--bocs-transition);
        }

        /* Labels */
        label {
            font-family: var(--bocs-font-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--bocs-text-light);
            margin-bottom: 0.5rem;
        }

        /* Modal Typography */
        .bocs-modal-content {
            font-family: var(--bocs-font-primary);
            background-color: #ffffff;
        }

        .bocs-modal h3 {
            font-family: var(--bocs-font-secondary);
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--bocs-primary);
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .bocs-modal p {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--bocs-text-light);
            margin-bottom: 1.5rem;
        }

        /* Notification Typography */
        .bocs-notification {
            font-family: var(--bocs-font-primary);
            font-size: 0.9375rem;
            font-weight: 500;
        }

        /* Responsive Typography */
        @media screen and (max-width: 768px) {
            .subscription-title {
                font-size: 1.125rem;
            }
            
            .woocommerce-button.button,
            .bocs-button {
                font-size: 0.875rem;
            }
            
            .bocs-modal h3 {
                font-size: 1.25rem;
            }
        }

        /* High Contrast & Accessibility */
        @media (prefers-contrast: high) {
            :root {
                --bocs-text: #000000;
                --bocs-text-light: #333333;
            }
            
            .subscription-title,
            .subscription-amount,
            .subscription-status {
                font-weight: 700;
            }
        }

        /* Print Styles */
        @media print {
            .woocommerce-subscriptions-wrapper {
                font-family: Georgia, serif;
                line-height: 1.5;
            }
            
            .subscription-title {
                font-size: 14pt;
                font-weight: bold;
            }
            
            .subscription-amount {
                font-family: "Courier New", monospace;
            }
        }

        :root {
            --bocs-primary: #0065A9;
            --bocs-secondary: #00A5B5;
            --bocs-accent: #FFCC00;
            --bocs-text: #333333;
            --bocs-light-bg: #F9FAFB;
            --bocs-border: #E5E7EB;
            --bocs-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --bocs-error: #E53E3E;
            --bocs-success: #38A169;
            --bocs-radius: 8px;
            --bocs-transition: all 0.3s ease;
        }

        .woocommerce-subscriptions-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
        }

        .wc-subscription {
            background: #ffffff;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            margin-bottom: 16px;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .wc-subscription:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .accordion-header {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            cursor: pointer;
            gap: 16px;
        }

        .subscription-info {
            display: flex;
            align-items: center;
            gap: 24px;
            flex: 1;
        }

        .subscription-title {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            flex: 1;
        }

        .subscription-amount {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0065A9;
            white-space: nowrap;
        }

        .subscription-frequency,
        .subscription-next-payment {
            color: #6B7280;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .subscription-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .status-active {
            background: #DCFCE7;
            color: #166534;
        }

        .status-cancelled {
            background: #FEE2E2;
            color: #991B1B;
        }

        .status-paused {
            background: #FEF3C7;
            color: #92400E;
        }

        .accordion-content {
            padding: 20px;
            border-top: 1px solid #E5E7EB;
            background: #F9FAFB;
        }

        .subscription-section {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .subscription-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #E5E7EB;
        }

        .subscription-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .subscription-label {
            font-size: 0.875rem;
            color: #6B7280;
            margin-bottom: 4px;
            display: block;
        }

        .subscription-value {
            font-size: 0.9375rem;
            color: #111827;
        }

        .subscription-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }

        .woocommerce-button.button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            background: #F3F4F6;
            color: #111827;
        }

        .woocommerce-button.button:hover {
            background: #E5E7EB;
        }

        .update-box-link {
            background: #0065A9 !important;
            color: white !important;
        }

        .update-box-link:hover {
            background: #0056a1 !important;
        }

        .switch-bocs {
            background: #00A5B5 !important;
            color: white !important;
        }

        .switch-bocs:hover {
            background: #008a99 !important;
        }

        .edit-payment-method {
            background: #ffffff !important;
            color: #111827 !important;
            border: 1px solid #E5E7EB !important;
        }

        .edit-payment-method:hover {
            background: #F9FAFB !important;
            border-color: #D1D5DB !important;
        }

        /* Responsive Adjustments */
        @media screen and (max-width: 768px) {
            .accordion-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .subscription-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                width: 100%;
            }

        .subscription-status {
                align-self: flex-start;
            }

            .subscription-actions {
                grid-template-columns: 1fr;
            }

            .woocommerce-button.button {
                width: 100%;
            }
        }

        /* Loading States */
        .button-loading {
            position: relative;
            color: transparent !important;
        }

        .button-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin: -8px 0 0 -8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Modal Styles */
        .bocs-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(17, 24, 39, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .bocs-modal-content {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .bocs-modal h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
        }

        .bocs-modal p {
            color: #6B7280;
            margin-bottom: 20px;
            font-size: 0.9375rem;
            line-height: 1.5;
        }

        .bocs-modal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Notifications */
        .bocs-notification {
            position: fixed;
            top: 16px;
            right: 16px;
            padding: 12px 16px;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            max-width: 380px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .bocs-notification.success {
            border-left: 4px solid #059669;
        }

        .bocs-notification.error {
            border-left: 4px solid #DC2626;
        }

        .bocs-notification.loading {
            border-left: 4px solid #0065A9;
        }

        /* Accessibility Improvements */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }

        .screen-reader-text {
            border: 0;
            clip: rect(1px, 1px, 1px, 1px);
            clip-path: inset(50%);
            height: 1px;
            margin: -1px;
            overflow: hidden;
            padding: 0;
            position: absolute;
            width: 1px;
            word-wrap: normal !important;
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            :root {
                --bocs-text: #333333;
                --bocs-text-light: #666666;
                --bocs-border: #E5E7EB;
                --bocs-light-bg: #ffffff;
            }

            .wc-subscription,
            .accordion-header,
            .subscription-section,
            .bocs-modal-content {
                background: #ffffff;
            }

            .edit-payment-method {
                background: #ffffff;
                border-color: #E5E7EB;
                color: #333333;
            }

        .status-active {
                background: #065F46;
                color: #34D399;
        }

        .status-cancelled {
                background: #991B1B;
                color: #FCA5A5;
        }

        .status-paused {
                background: #92400E;
                color: #FDBA74;
            }
        }

        /* Brand Logo */
        .bocs-brand {
            margin-bottom: 24px;
            display: flex;
            align-items: center;
        }

        .bocs-logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--bocs-primary);
            display: flex;
            align-items: center;
        }

        .bocs-logo:before {
            content: "□";
            display: inline-block;
            color: var(--bocs-accent);
            margin-right: 8px;
            transform: rotate(45deg);
        }

        /* Enhanced Card Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .wc-subscription {
            animation: fadeIn 0.3s ease-out;
            animation-fill-mode: both;
        }

        .wc-subscription:nth-child(1) { animation-delay: 0.1s; }
        .wc-subscription:nth-child(2) { animation-delay: 0.2s; }
        .wc-subscription:nth-child(3) { animation-delay: 0.3s; }
        .wc-subscription:nth-child(4) { animation-delay: 0.4s; }
        .wc-subscription:nth-child(5) { animation-delay: 0.5s; }

        /* Enhanced Subscription Header */
        .subscription-header-sticky {
            position: sticky;
            top: 0;
            background: white;
            padding: 1em;
            border-bottom: 1px solid var(--bocs-border);
            margin: -1.5em -1.5em 1.5em;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            min-height: 3.5em;
        }

        /* Enhanced Button States */
        .bocs-button {
            position: relative;
            overflow: hidden;
        }

        .bocs-button:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, .5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .bocs-button:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            20% {
                transform: scale(25, 25);
                opacity: 0.3;
            }
            100% {
                opacity: 0;
                transform: scale(40, 40);
            }
        }

        /* Enhanced Loading States */
        .loading-state {
            position: relative;
            pointer-events: none;
        }

        .loading-state:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            animation: loading-shimmer 1.5s infinite;
        }

        @keyframes loading-shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Enhanced Notification System */
        .bocs-notification {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: var(--bocs-radius);
            background: white;
            box-shadow: var(--bocs-shadow);
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .bocs-notification.show {
            transform: translateX(0);
        }

        .bocs-notification:before {
            content: '';
            width: 20px;
            height: 20px;
            background-position: center;
            background-repeat: no-repeat;
            background-size: contain;
        }

        .bocs-notification.success:before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2338A169'%3E%3Cpath fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd'/%3E%3C/svg%3E");
        }

        .bocs-notification.error:before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23E53E3E'%3E%3Cpath fill-rule='evenodd' d='M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
        }

        /* Enhanced Form Interactions */
        .form-row input:focus {
            transform: scale(1.02);
        }

        .form-row label {
            transform-origin: left;
            transition: transform 0.2s ease;
        }

        .form-row input:focus + label,
        .form-row input:not(:placeholder-shown) + label {
            transform: translateY(-20px) scale(0.85);
            color: var(--bocs-primary);
        }

        /* Enhanced Modal Interactions */
        .bocs-modal {
            backdrop-filter: blur(5px);
            transition: opacity 0.3s ease;
        }

        .bocs-modal-content {
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: #ffffff;
        }

        .bocs-modal.show .bocs-modal-content {
            transform: scale(1);
            opacity: 1;
            background-color: #ffffff;
        }

        /* Enhanced Status Indicators */
        .subscription-status {
            position: relative;
            overflow: hidden;
        }

        .subscription-status:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            transform: translateX(-100%);
            animation: status-shine 2s infinite;
        }

        @keyframes status-shine {
            100% { transform: translateX(100%); }
        }

        /* Enhanced Accordion Interactions */
        .accordion-header {
            position: relative;
            overflow: hidden;
        }

        .accordion-header:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transform: translateX(-100%);
        }

        .accordion-header:hover:after {
            animation: shine 0.5s forwards;
        }

        @keyframes shine {
            100% { transform: translateX(100%); }
        }

        /* Floating Action Button for Mobile */
        .bocs-fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--bocs-primary);
            color: white;
            box-shadow: var(--bocs-shadow);
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.3s ease;
            z-index: 100;
        }

        .bocs-fab:hover {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .bocs-fab {
                display: flex;
            }
        }

        /* Accessibility Improvements */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        .screen-reader-text {
            border: 0;
            clip: rect(1px, 1px, 1px, 1px);
            clip-path: inset(50%);
            height: 1px;
            margin: -1px;
            overflow: hidden;
            padding: 0;
            position: absolute;
            width: 1px;
            word-wrap: normal !important;
        }

        /* High Contrast Mode Support */
        @media (prefers-contrast: high) {
            :root {
                --bocs-primary: #000000;
                --bocs-secondary: #000000;
                --bocs-accent: #000000;
                --bocs-text: #000000;
                --bocs-border: #000000;
            }

            .subscription-status {
                outline: 2px solid currentColor;
            }

            .bocs-button {
                border: 2px solid currentColor !important;
            }
        }

        .woocommerce-button.button,
        .bocs-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background: #333333;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            line-height: 1.5;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 160px;
            text-align: center;
        }

        .woocommerce-button.button:hover,
        .bocs-button:hover {
            background: #444444;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .woocommerce-button.button.update-box-link {
            background: #0065A9;
        }

        .woocommerce-button.button.update-box-link:hover {
            background: #005293;
        }

        .woocommerce-button.button.switch-bocs {
            background: #00A5B5;
        }

        .woocommerce-button.button.switch-bocs:hover {
            background: #008A99;
        }

        .woocommerce-button.button.view-details {
            background: #333333;
        }

        .woocommerce-button.button.view-details:hover {
            background: #444444;
        }

        .woocommerce-button.button.edit-payment-method {
            background: #F9FAFB;
            color: #333333;
            border: 1px solid #E5E7EB;
        }

        .woocommerce-button.button.edit-payment-method:hover {
            background: white;
            border-color: #333333;
        }

        .woocommerce-button.button.subscription_renewal_early {
            background: #38A169;
        }

        .woocommerce-button.button.subscription_renewal_early:hover {
            background: #2F855A;
        }

        .woocommerce-button.button.subscription_activate {
            background: #0065A9;
        }

        .woocommerce-button.button.subscription_activate:hover {
            background: #005293;
        }

        /* Loading State */
        .woocommerce-button.button.loading,
        .bocs-button.loading {
            position: relative;
            pointer-events: none;
            color: transparent !important;
        }

        .woocommerce-button.button.loading::after,
        .bocs-button.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: button-loading-spinner 1s ease infinite;
        }

        @keyframes button-loading-spinner {
            from { transform: rotate(0turn); }
            to { transform: rotate(1turn); }
        }

        /* Focus States */
        .woocommerce-button.button:focus,
        .bocs-button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 101, 169, 0.3);
        }

        /* Disabled State */
        .woocommerce-button.button:disabled,
        .woocommerce-button.button.disabled,
        .bocs-button:disabled,
        .bocs-button.disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Button Group Layout */
        .subscription-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }

        /* Responsive Adjustments */
        @media screen and (max-width: 768px) {
            .woocommerce-button.button,
            .bocs-button {
                width: 100%;
                min-width: unset;
                padding: 10px 20px;
                font-size: 14px;
            }

            .subscription-actions {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }

        /* Payment Method Modal Styles */
        .payment-method-item {
            padding: 15px;
            background: var(--bocs-white);
            border: 1px solid var(--bocs-border);
            margin-bottom: 10px;
            border-radius: var(--bocs-radius);
            transition: var(--bocs-transition);
        }

        .payment-method-item:hover {
            border-color: var(--bocs-primary);
            box-shadow: var(--bocs-shadow);
        }

        .payment-method-option {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .payment-method-option input[type="radio"] {
            margin-right: 10px;
        }

        .payment-method-details {
            font-weight: 500;
        }

        .add-new .payment-method-details {
            color: var(--bocs-primary);
        }

        .add-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            line-height: 18px;
            text-align: center;
            background: var(--bocs-primary);
            color: white;
            border-radius: 50%;
            margin-right: 5px;
            font-style: normal;
        }

        .payment-method-message {
            text-align: center;
            padding: 20px;
        }

        .payment-method-message.error {
            color: var(--bocs-error);
        }

        .payment-methods-container {
            margin: 20px 0;
        }

        .bocs-loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: var(--bocs-primary);
            animation: bocs-spin 0.8s linear infinite;
            margin: 0 auto 10px;
        }

        .loading-state {
            text-align: center;
            padding: 20px;
        }

        @keyframes bocs-spin {
            to { transform: rotate(360deg); }
        }

        /* Make sure the modal shows correctly */
        .bocs-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .bocs-modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .bocs-modal-content {
            background: #ffffff;
            border-radius: var(--bocs-radius);
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--bocs-shadow);
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.3s forwards;
        }

        .bocs-modal.show .bocs-modal-content {
            opacity: 1;
            transform: translateY(0);
            background-color: #ffffff;
        }

        .bocs-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Enhanced Notification System */
    </style>

    <!-- Add Floating Action Button HTML -->
    <div class="bocs-fab" role="button" aria-label="Quick Actions">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
    </div>
    <?php
});

// Retrieve BOCS plugin settings
$options = get_option('bocs_plugin_options');

// Initialize variables at the start of the file or before line 204
$discount_percent = 0; // or whatever default value is appropriate
$frequency_text = ''; // or whatever default value is appropriate

?>

<div id="bocs-subscriptions-accordion" class="woocommerce-subscriptions-wrapper">
    <?php
    if (!empty($subscriptions['data']['data']) && is_array($subscriptions['data']['data'])) {
        foreach ($subscriptions['data']['data'] as $index => $subscription) {
            // Improved subscription name logic
            $subscription_name = '';
            if (!empty(trim($subscription['bocs']['name']))) {
                $subscription_name = sprintf(
                    esc_html__($subscription['bocs']['name'] . ' (Order #%s)', 'bocs-wordpress'),
                    $subscription['externalSourceParentOrderId']
                );
            } elseif (!empty($subscription['externalSourceParentOrderId'])) {
                $subscription_name = sprintf(
                    esc_html__('Subscription (Order #%s)', 'bocs-wordpress'),
                    $subscription['externalSourceParentOrderId']
                );
            } else {
                $subscription_name = sprintf(
                    esc_html__('Subscription #%s', 'bocs-wordpress'),
                    substr($subscription['id'], 0, 8)
                );
            }
            
            $subscription_url = wc_get_endpoint_url('bocs-view-subscription', $subscription['id'], wc_get_page_permalink('myaccount'));
            $next_payment_date = new DateTime($subscription['nextPaymentDateGmt']);
            $start_date = new DateTime($subscription['startDateGmt']);
            
            $row_class = ($index % 2 == 0) ? 'even' : 'odd';

            // Then later in the code where these variables should be set
            if (isset($subscription['frequency']) && isset($subscription['timeUnit'])) {
                $frequency_text = sprintf(
                    'Every %d %s',
                    $subscription['frequency'],
                    $subscription['frequency'] > 1 ? $subscription['timeUnit'] : rtrim($subscription['timeUnit'], 's')
                );
            }

            if (isset($subscription['discount'])) {
                $discount_percent = floatval($subscription['discount']);
            }
    ?>
            <div class="wc-subscription <?php echo esc_attr($row_class); ?>">
                <h3 class="accordion-header">
                    <span class="subscription-title">
                        <?php echo esc_html($subscription_name); ?>
                    </span>
                    <span class="divider">|</span>
                    <span class="subscription-amount">
                        <span class="woocommerce-Price-currencySymbol"><?php echo get_woocommerce_currency_symbol(); ?></span>
                        <?php echo number_format($subscription['total'], 2); ?>
                    </span>
                    <span class="divider">|</span>
                    <span class="subscription-frequency">
                        <?php
                            $frequency = isset($subscription['frequency']['frequency']) ? $subscription['frequency']['frequency'] : 1;
                            $period = isset($subscription['frequency']['timeUnit']) ? $subscription['frequency']['timeUnit'] : '';
                            if ($frequency > 1) {
                                $period = rtrim($period, 's') . 's';
                            } else {
                                $period = rtrim($period, 's');
                            }
                            echo esc_html(sprintf(__('Every %d %s', 'bocs-wordpress'), $frequency, $period));
                        ?>
                    </span>
                    <span class="divider">|</span>
                    <span class="subscription-next-payment" data-next-payment="<?php echo $subscription['nextPaymentDateGmt']; ?>">
                        <?php 
                            echo esc_html(sprintf(
                                __('Next payment: %s', 'bocs-wordpress'),
                                $next_payment_date->format('l, j F Y')
                            )); 
                        ?>
                    </span>
                </h3>
                <div class="accordion-content">
                    <div class="subscription-header-sticky">
                        <span class="subscription-title"></span>
                        <span class="subscription-status status-<?php echo esc_attr(strtolower($subscription['subscriptionStatus']) ); ?>">
                            <?php echo ucfirst($subscription['subscriptionStatus']); ?>
                        </span>
                    </div>
                    <div class="subscription-section">
                        <div class="subscription-row">
                            <div class="total-amount">
                                <span class="subscription-label"><?php esc_html_e('Total Amount', 'bocs-wordpress'); ?></span>
                                <span class="woocommerce-Price-amount amount">
                                    <?php if (!empty($subscription['currency'])): ?>
                                        <span class="woocommerce-Price-currencySymbol"><?php echo get_woocommerce_currency_symbol(); ?></span>
                                    <?php endif; ?>
                                    <?php echo number_format($subscription['total'], 2); ?>
                                </span>
                                <?php if ($subscription['discountTotal'] > 0): ?>
                                    <span class="subscription-discount">
                                        (<?php echo sprintf(esc_html__('Includes %s discount', 'bocs-wordpress'), 
                                            wc_price($subscription['discountTotal'])); ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php 
                            $status = strtolower($subscription['subscriptionStatus']);
                            if ($status === 'active'): ?>
                                <button 
                                    class="woocommerce-button button bocs-button subscription_renewal_early" 
                                    id="renewal_<?php echo esc_attr($subscription['id']); ?>"
                                    data-subscription-id="<?php echo esc_attr($subscription['id']); ?>"
                                >
                                    <?php esc_html_e('Early Renewal', 'bocs-wordpress'); ?>
                                </button>
                            <?php else: ?>
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_<?php echo esc_attr($subscription['id']); ?>"
                                    data-subscription-id="<?php echo esc_attr($subscription['id']); ?>"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="subscription-row">
                            <div class="delivery-frequency">
                                <span class="subscription-label"><?php esc_html_e('Delivery', 'bocs-wordpress'); ?></span>
                                <span><?php
                                    $frequency = isset($subscription['frequency']['frequency']) ? $subscription['frequency']['frequency'] : 1;
                                    $period = isset($subscription['frequency']['timeUnit']) ? $subscription['frequency']['timeUnit'] : '';
                                    if ($frequency > 1) {
                                        $period = rtrim($period, 's') . 's';
                                    } else {
                                        $period = rtrim($period, 's');
                                    }
                                    echo esc_html(sprintf(__('Every %d %s', 'bocs-wordpress'), $frequency, $period));
                                ?></span>
                            </div>
                            <div class="total-items">
                                <span class="subscription-label"><?php esc_html_e('Total Items', 'bocs-wordpress'); ?></span>
                                <span><?php 
                                    $items_count = isset($subscription['lineItems']) && is_array($subscription['lineItems']) 
                                        ? array_sum(array_column($subscription['lineItems'], 'quantity')) 
                                        : 0;
                                    echo $items_count . ' ' . esc_html(_n('item', 'items', $items_count, 'bocs-wordpress')); 
                                ?></span>
                            </div>
                        </div>

                        <div class="subscription-row">
                            <div class="billing-address">
                                <span class="subscription-label">
                                    <?php esc_html_e('Billing Address', 'bocs-wordpress'); ?>
                                    <button class="edit-address-link" data-type="billing">
                                        <?php esc_html_e('Edit', 'bocs-wordpress'); ?>
                                    </button>
                                </span>
                                <?php if (!empty($subscription['billing'])): ?>
                                    <address>
                                        <?php
                                        $billing = $subscription['billing'];
                                        echo esc_html(implode(', ', array_filter([
                                            $billing['firstName'] . ' ' . $billing['lastName'],
                                            $billing['address1'],
                                            $billing['address2'],
                                            $billing['city'],
                                            $billing['state'],
                                            $billing['postcode'],
                                            $billing['country']
                                        ])));
                                        ?>
                                    </address>
                                <?php else: ?>
                                    <span><?php esc_html_e('No address provided', 'bocs-wordpress'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="subscription-row">
                            <div class="shipping-address">
                                <span class="subscription-label">
                                    <?php esc_html_e('Shipping Address', 'bocs-wordpress'); ?>
                                    <button class="edit-address-link" data-type="shipping">
                                        <?php esc_html_e('Edit', 'bocs-wordpress'); ?>
                                    </button>
                                </span>
                                <?php if (!empty($subscription['shipping'])): ?>
                                    <address>
                                        <?php
                                        $shipping = $subscription['shipping'];
                                        echo esc_html(implode(', ', array_filter([
                                            $shipping['firstName'] . ' ' . $shipping['lastName'],
                                            $shipping['address1'],
                                            $shipping['address2'],
                                            $shipping['city'],
                                            $shipping['state'],
                                            $shipping['postcode'],
                                            $shipping['country']
                                        ])));
                                        ?>
                                    </address>
                                <?php else: ?>
                                    <span><?php esc_html_e('No address provided', 'bocs-wordpress'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="subscription-row">
                            <div class="next-payment">
                                <span class="subscription-label"><?php esc_html_e('Next Payment', 'bocs-wordpress'); ?></span>
                                <time datetime="<?php echo esc_attr($next_payment_date->format('c')); ?>">
                                    <?php echo esc_html($next_payment_date->format('l, j F Y')); ?>
                                </time>
                            </div>
                            <div class="start-date">
                                <span class="subscription-label"><?php esc_html_e('Started On', 'bocs-wordpress'); ?></span>
                                <time datetime="<?php echo esc_attr($start_date->format('c')); ?>">
                                    <?php echo esc_html($start_date->format('l, j F Y')); ?>
                                </time>
                            </div>
                        </div>

                        <div class="subscription-actions">
                            <?php if ($subscription['subscriptionStatus'] === 'active' || $subscription['subscriptionStatus'] === 'paused' || $subscription['subscriptionStatus'] === 'unpaid' || $subscription['subscriptionStatus'] === 'pending-cancel') : ?>
                                <a 
                                    href="#" 
                                    data-subscription-id="<?php echo esc_attr($subscription['id']); ?>"
                                    class="woocommerce-button button update-box update-box-link">
                                    <?php esc_html_e('Update My Box', 'bocs-wordpress'); ?>
                                </a>
                                <a 
                                    href="#" 
                                    data-subscription-id="<?php echo esc_attr($subscription['id']); ?>"
                                    class="woocommerce-button button switch-bocs">
                                    <?php esc_html_e('Switch Bocs', 'bocs-wordpress'); ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(rtrim(wc_get_account_endpoint_url('bocs-edit-details'), '/') . '/' . $subscription['id']); ?>" 
                                class="woocommerce-button button alt view-details"
                                onclick="event.preventDefault(); event.stopPropagation(); window.location.href='<?php echo esc_url(rtrim(wc_get_account_endpoint_url('bocs-edit-details'), '/') . '/' . $subscription['id']); ?>';">
                                <?php esc_html_e('Edit Details', 'bocs-wordpress'); ?>
                            </a>
                            <button type="button" class="woocommerce-button button edit-payment-method" 
                                data-subscription-id="<?php echo esc_attr($subscription['id']); ?>">
                                <?php esc_html_e('Edit Payment Method', 'bocs-wordpress'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
    <?php
        }
    }
    ?>
</div>

<!-- Subscription Details View Container -->
<div id="subscription-details-view" style="display: none;">
    <div class="subscription-details-header">
        <div class="subscription-navigation">
            <a href="#" class="back-to-subscription"><?php esc_html_e('Back to subscription', 'bocs-wordpress'); ?></a>
            <span class="nav-separator">|</span>
            <span class="nav-item">Dashboard</span>
            <span class="nav-separator">→</span>
            <span class="nav-item subscription-name"></span>
        </div>
        <div class="header-actions">
            <button class="woocommerce-button button alt pay-now">
                <?php esc_html_e('Early Renewal', 'bocs-wordpress'); ?>
            </button>
        </div>
    </div>
    
    <div class="subscription-details-content">
        <div class="content-header">
            <h2><?php esc_html_e('Box Content', 'bocs-wordpress'); ?></h2>
            <button class="woocommerce-button button edit-box">
                <?php esc_html_e('Edit the Box', 'bocs-wordpress'); ?>
            </button>
        </div>
        <div class="box-items"></div>

        <!-- Add product selection interface -->
        <div id="product-selection-interface" style="display: none; margin-top: 20px; background: white; padding: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="interface-header">
                <h3><?php esc_html_e('Update Your Box Contents', 'bocs-wordpress'); ?></h3>
                <a href="#" class="back-to-subscriptions" onclick="return false;"><?php esc_html_e('Back to subscriptions', 'bocs-wordpress'); ?></a>
                </div>
            
            <div class="subscription-info" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <div class="info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="info-item">
                        <span class="label" style="font-weight: bold;"><?php esc_html_e('Next Payment:', 'bocs-wordpress'); ?></span>
                        <span class="value next-payment-date"></span>
                    </div>
                    <div class="info-item">
                        <span class="label" style="font-weight: bold;"><?php esc_html_e('Frequency:', 'bocs-wordpress'); ?></span>
                        <span class="value frequency-text"></span>
                        </div>
                </div>
            </div>

            <div class="products-section">
                <div class="products-loading" style="text-align: center; padding: 20px;">
                    <div class="loading-spinner"></div>
                    <p><?php esc_html_e('Loading products...', 'bocs-wordpress'); ?></p>
                </div>
                <div class="products-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;"></div>
            </div>

            <div class="box-summary" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <h4><?php esc_html_e('Box Summary', 'bocs-wordpress'); ?></h4>
                <div class="summary-items"></div>
                <div class="summary-totals">
                    <div class="total-row" style="display: flex; justify-content: space-between; padding: 5px 0;">
                        <span><?php esc_html_e('Subtotal:', 'bocs-wordpress'); ?></span>
                        <span class="subtotal-amount"></span>
                </div>
                    <div class="total-row" style="display: flex; justify-content: space-between; padding: 5px 0;">
                        <span><?php esc_html_e('Discount:', 'bocs-wordpress'); ?></span>
                        <span class="discount-amount"></span>
                </div>
                    <div class="total-row total" style="display: flex; justify-content: space-between; padding: 5px 0; font-weight: bold;">
                        <span><?php esc_html_e('Total:', 'bocs-wordpress'); ?></span>
                        <span class="total-amount"></span>
                </div>
                            </div>
                        </div>

            <div class="actions" style="margin-top: 20px; text-align: right;">
                <button type="button" class="button button-secondary cancel-product-selection"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                <button type="button" class="button button-primary save-box-changes">
                    <span class="button-text"><?php esc_html_e('Save Changes', 'bocs-wordpress'); ?></span>
                    <span class="loading-spinner" style="display: none;"></span>
                        </button>
                </div>
            </div>

        <div class="subscription-totals"></div>
                </div>
            </div>

<!-- JavaScript Implementation -->
<script>
/**
 * BOCS Subscription Management JavaScript
 * Handles all interactive functionality for subscription management
 */
jQuery(document).ready(function($) {
    console.log('BOCS Subscription Management initialized');
    
    // Track currently active subscription for state management
    let activeSubscriptionId = null;

    // Add click handler for Edit Details link
    $(document).on('click', 'a.view-details', function(e) {
        e.preventDefault();
        e.stopPropagation();
        // Manually navigate to the href
        window.location.href = $(this).attr('href');
    });
    
    // Initialize jQuery UI Accordion
    $('#bocs-subscriptions-accordion').accordion({
        collapsible: true,
        active: false,
        heightStyle: "content"
    });
    console.log('Accordion initialized');

    // Add click event logging for all buttons
    $('.update-box, .view-details, .edit-payment-method').on('click', function() {
        console.log('Button clicked:', $(this).text().trim(), 'with subscription ID:', $(this).data('subscription-id'));
    });

    // Add click handler for the Update Box button
    $('.update-box-link').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent the event from bubbling up to parent elements
        
        // Get the subscription ID from the clicked button
        let subscriptionId = $(this).data('subscription-id');
        
        // If we're in an accordion panel, make sure we have the right subscription ID
        if (activeSubscriptionId) {
            subscriptionId = activeSubscriptionId;
        }
        
        console.log('Redirecting to update box with subscription ID:', subscriptionId);
        
        if (subscriptionId) {
            // Immediate redirect without affecting the UI
            window.location.href = '<?php echo esc_url(wc_get_account_endpoint_url('bocs-update-box')); ?>' + subscriptionId + '/';
        }
    });

    // Add click handler for the Switch Bocs button
    $('.switch-bocs').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent the event from bubbling up to parent elements
        
        // Get the subscription ID from the clicked button
        let subscriptionId = $(this).data('subscription-id');
        
        // If we're in an accordion panel, make sure we have the right subscription ID
        if (activeSubscriptionId) {
            subscriptionId = activeSubscriptionId;
        }
        
        console.log('Redirecting to switch bocs with subscription ID:', subscriptionId);
        
        if (subscriptionId) {
            // Immediate redirect without affecting the UI
            window.location.href = '<?php echo esc_url(wc_get_account_endpoint_url('bocs-switch-bocs')); ?>' + subscriptionId + '/';
        }
    });

    /**
     * Event Handler Object
     * Contains all event handling functions for subscription interactions
     */
    const eventHandlers = {
        // Add subscription data storage at class level
        subscriptionData: null,

        /**
         * View Details Handler
         * Fetches and displays detailed subscription information
         * 
         * @param {Event} e - Click event object
         */
        viewDetails: async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const button = $(this);
            const subscriptionId = button.data('subscription-id');
            activeSubscriptionId = subscriptionId;
            
            console.log('View details clicked for subscription:', subscriptionId);
            
            if (!subscriptionId) {
                console.error('No subscription ID found');
                return;
            }

            try {
                // Show loading state
                button.prop('disabled', true).addClass('button-loading');
                helpers.showNotification('Fetching subscription details...', 'loading');

                const subscriptionResponse = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}`,
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (!subscriptionResponse.data) {
                    throw new Error(bocsTranslations.invalidResponseStructure);
                }

                // Store subscription data for other handlers to use
                this.subscriptionData = subscriptionResponse.data;

                const detailsView = $('#subscription-details-view');
                const boxItemsContainer = detailsView.find('.box-items');
                const earlyRenewButton = detailsView.find('.header-actions button');
                
                if (subscriptionResponse.data.subscriptionStatus === 'active') {
                    earlyRenewButton.show();
                } else {
                    earlyRenewButton.hide();
                }
                
                // Clear existing items and show loading message
                boxItemsContainer.empty().append(`
                    <div class="box-items-loading">
                        <div class="loading-spinner"></div>
                        <p><?php esc_html_e('Loading subscription items...', 'bocs-wordpress'); ?></p>
                    </div>
                `);

                // Update subscription details
                detailsView.find('.subscription-name').text(
                    `<?php esc_html_e('Subscription (Order #', 'bocs-wordpress'); ?>${subscriptionResponse.data.externalSourceParentOrderId})`
                );

                // Process and display line items
                if (subscriptionResponse.data.lineItems && Array.isArray(subscriptionResponse.data.lineItems)) {
                    // First, collect all product IDs
                    const productIds = subscriptionResponse.data.lineItems.map(item => item.externalSourceId);
                    
                    // Store line items for later use
                    const subscriptionLineItems = subscriptionResponse.data.lineItems;
                    
                    // Fetch product details from WooCommerce
                    $.ajax({
                        url: wc_add_to_cart_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'get_product_details',
                            product_ids: productIds,
                            nonce: '<?php echo wp_create_nonce("get_product_details"); ?>'
                        },
                        success: function(productResponse) {
                            if (productResponse.success && productResponse.data) {
                                const products = productResponse.data;
                                boxItemsContainer.empty();
                                
                                // Now process line items with product details
                                subscriptionLineItems.forEach(item => {
                                    const productDetails = products[item.externalSourceId] || {};
                                    const productName = productDetails.name || `<?php esc_html_e('Product ID:', 'bocs-wordpress'); ?> ${item.externalSourceId}`;
                                    const productSku = productDetails.sku ? ` (<?php esc_html_e('SKU:', 'bocs-wordpress'); ?> ${productDetails.sku})` : '';
                                    
                                    const itemHtml = `
                                        <div class="box-item" 
                                             data-product-id="${item.externalSourceId}"
                                             data-bocs-product-id="${item.productId}"
                                             data-quantity="${item.quantity}"
                                             data-price="${item.price}"
                                             data-total="${item.total}">
                                            <div class="item-details">
                                                <span class="item-name">${productName}${productSku}</span>
                                                <span class="item-price">
                                                    <span class="woocommerce-Price-amount amount">
                                                        <bdi>
                                                            <span class="woocommerce-Price-currencySymbol">$</span>${parseFloat(item.price).toFixed(2)}
                                                        </bdi>
                                                    </span>
                                                </span>
                                                <span class="item-quantity">× ${item.quantity}</span>
                                                <span class="item-total">
                                                    <span class="woocommerce-Price-amount amount">
                                                        <bdi>
                                                            <span class="woocommerce-Price-currencySymbol">$</span>${parseFloat(item.total).toFixed(2)}
                                                        </bdi>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    `;
                                    boxItemsContainer.append(itemHtml);
                                });
                            } else {
                                boxItemsContainer.html(`<p><?php esc_html_e('Error loading product details', 'bocs-wordpress'); ?></p>`);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error fetching product details:', error);
                            boxItemsContainer.html(`<p><?php esc_html_e('Failed to load product details', 'bocs-wordpress'); ?></p>`);
                        }
                    });
                } else {
                    boxItemsContainer.html(`<p><?php esc_html_e('No items found in this subscription', 'bocs-wordpress'); ?></p>`);
                }

                // Update frequency display
                if (subscriptionResponse.data.frequency) {
                    const freq = subscriptionResponse.data.frequency.frequency;
                    const unit = subscriptionResponse.data.frequency.timeUnit;
                    if (freq && unit) {
                        detailsView.find('.current-frequency').text(
                            `Every ${freq} ${unit}`
                        );
                        
                        // Pre-select the matching radio button
                        detailsView.find(`input[name="frequency"][data-frequency="${freq}"]`)
                            .prop('checked', true)
                            .closest('.frequency-option')
                            .siblings()
                            .find('input[name="frequency"]')
                            .prop('checked', false);
                    }
                }

                // Calculate and update totals
                const calculations = subscriptionResponse.data.lineItems.reduce((acc, item) => {
                    const itemSubtotal = parseFloat(item.price) * parseInt(item.quantity);
                    const itemTotal = parseFloat(item.total);
                    return {
                        subtotal: acc.subtotal + itemSubtotal,
                        totalAfterDiscount: acc.totalAfterDiscount + itemTotal
                    };
                }, { subtotal: 0, totalAfterDiscount: 0 });

                const totalDiscount = calculations.subtotal - calculations.totalAfterDiscount;

                // Update totals display
                helpers.updateTotalsDisplay(calculations.subtotal, totalDiscount, subscriptionResponse.data.total);

                // Hide subscriptions list and show details
                $('#bocs-subscriptions-accordion').hide();
                detailsView.show();

                // Hide loading message on success
                helpers.hideNotification();

                // After successful subscription data fetch, load frequencies
                if (subscriptionResponse.data.bocs && subscriptionResponse.data.bocs.id) {
                    console.log('Loading frequencies for BOCS ID:', subscriptionResponse.data.bocs.id);
                    await helpers.loadFrequencyOptions(subscriptionResponse.data.bocs.id, subscriptionResponse.data.frequency);
                } else {
                    console.warn('No BOCS ID found in subscription data:', subscriptionResponse.data);
                }

            } catch (error) {
                console.error('Error in viewDetails:', error);
                helpers.showNotification('Failed to load subscription details. Please try again.', 'error');
            } finally {
                button.prop('disabled', false).removeClass('button-loading');
            }
        },

        /**
         * Edit Frequency Handler
         * Shows the frequency editing interface
         * 
         * @param {Event} e - Click event object
         */
        editFrequency: function(e) {
            e.preventDefault();
            const button = $(this);
            const detailsSection = button.closest('.details-section');
            
            // Hide the edit button
            button.hide();
            
            // Show the frequency editor
            detailsSection.find('.frequency-editor').show();
            
            // Get current frequency from subscription data and select the matching radio button
            const currentFrequencyText = detailsSection.find('.current-frequency').text();
            const frequencyMatch = currentFrequencyText.match(/Every (\d+) Month/);
            
            if (frequencyMatch) {
                const currentFrequency = parseInt(frequencyMatch[1]);
                detailsSection.find(`input[name="frequency"][data-frequency="${currentFrequency}"]`).prop('checked', true);
            }
        },

        /**
         * Cancel Edit Handler
         * Cancels frequency editing and returns to view mode
         * 
         * @param {Event} e - Click event object
         */
        cancelEdit: function(e) {
            e.preventDefault();
            const button = $(this);
            const frequencyEditor = button.closest('.frequency-editor');
            
            // Hide the frequency editor
            frequencyEditor.hide();
            
            // Show the edit button
            frequencyEditor.closest('.details-section').find('.edit-link').show();
        },

        /**
         * Save Frequency Handler
         * Processes and saves frequency updates to the subscription
         * 
         * @param {Event} e - Click event object
         */
        saveFrequency: async function(e) {
            e.preventDefault();
            const button = $(this);
            const originalButtonText = button.html();
            const frequencyEditor = button.closest('.frequency-editor');
            
            // Get selected frequency
            const selectedFrequency = frequencyEditor.find('input[name="frequency"]:checked');
            if (!selectedFrequency.length) {
                console.error('No frequency selected');
                helpers.showNotification('Please select a frequency', 'error');
                return;
            }

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Updating frequency...');
                
                helpers.showNotification('Updating subscription frequency...', 'loading');

                // Get frequency data
                const frequencyData = {
                    id: selectedFrequency.val(),
                    discount: parseInt(selectedFrequency.data('discount')),
                    discountType: "percent",
                    timeUnit: selectedFrequency.data('time-unit'),
                    frequency: parseInt(selectedFrequency.data('frequency')),
                    price: 0
                };

                // Get current subscription data
                const currentItems = [];
                $('.box-items .box-item').each(function() {
                    const item = $(this);
                    currentItems.push({
                        externalSourceId: item.data('product-id').toString(),
                        productId: item.data('bocs-product-id'),
                        quantity: parseInt(item.data('quantity')),
                        price: parseFloat(item.data('price')),
                        total: 0,
                        taxes: [],
                        metaData: []
                    });
                });

                if (!currentItems.length) {
                    throw new Error('No line items found');
                }

                // Calculate new totals
                const discountMultiplier = 1 - (frequencyData.discount / 100);
                let subtotal = 0;
                currentItems.forEach(item => {
                    const itemSubtotal = item.price * item.quantity;
                    subtotal += itemSubtotal;
                    item.total = itemSubtotal * discountMultiplier;
                });

                const discountTotal = subtotal * (frequencyData.discount / 100);
                const total = subtotal - discountTotal;

                // Prepare update payload
                const updatePayload = {
                    frequency: {
                        ...frequencyData,
                        price: parseFloat((0).toFixed(2))
                    },
                    billingInterval: frequencyData.frequency,
                    billingPeriod: frequencyData.timeUnit.toLowerCase(),
                    discountTotal: parseFloat(discountTotal.toFixed(2)),
                    total: parseFloat(total.toFixed(2)),
                    totalTax: parseFloat((0).toFixed(2)),
                    lineItems: currentItems.map(item => ({
                        ...item,
                        price: parseFloat(item.price.toFixed(2)),
                        total: parseFloat(item.total.toFixed(2)),
                        taxes: [],
                        metaData: []
                    }))
                };

                // Update subscription
                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${activeSubscriptionId}`,
                    method: 'PUT',
                    data: JSON.stringify(updatePayload),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update frequency display
                    const frequencyText = `Every ${frequencyData.frequency} ${frequencyData.timeUnit}`;
                    $('.current-frequency').text(frequencyText);

                    // Update line items display with new totals
                    currentItems.forEach(item => {
                        const itemElement = $(`.box-item[data-product-id="${item.externalSourceId}"]`);
                        if (itemElement.length) {
                            // Update the total display
                            itemElement.find('.item-total .woocommerce-Price-amount bdi').html(
                                `<span class="woocommerce-Price-currencySymbol">$</span>${parseFloat(item.total).toFixed(2)}`
                            );
                            
                            // Update the data attributes
                            itemElement.attr('data-total', item.total.toFixed(2));
                        }
                    });

                    // Update totals display
                    helpers.updateTotalsDisplay(subtotal, discountTotal, total);

                    // Update subscription in list
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${activeSubscriptionId}"]`)
                        .closest('.wc-subscription');
                        
                    if (subscriptionInList.length) {
                        subscriptionInList.find('.delivery-frequency span:last').text(frequencyText);
                        subscriptionInList.find('.total-amount').html(
                            `<span class="woocommerce-Price-amount amount">
                                <bdi>
                                    <span class="woocommerce-Price-currencySymbol">$</span>${total.toFixed(2)}
                                </bdi>
                            </span>`
                        );
                    }

                    // Hide frequency editor
                    frequencyEditor.hide();
                    frequencyEditor.closest('.details-section').find('.edit-link').show();

                    helpers.showNotification('Frequency updated successfully', 'success');
                } else {
                    throw new Error(response.message || 'Failed to update frequency');
                }
            } catch (error) {
                console.error('Error updating subscription frequency:', error);
                helpers.showNotification('Failed to update frequency. Please try again.', 'error');
            } finally {
                // Reset button state
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        },

        /**
         * Back to Subscription Handler
         * Returns to the main subscription list view
         * 
         * @param {Event} e - Click event object
         */
        backToSubscription: function(e) {
            e.preventDefault();
            
            // Hide the details view
            $('#subscription-details-view').hide();
            
            // Show the subscriptions list/accordion
            $('#bocs-subscriptions-accordion').show();
            
            // Reset any open editors
            $('.frequency-editor').hide();
            $('.edit-link').show();
            
            // Find and open the accordion panel for the current subscription
            if (activeSubscriptionId) {
                const subscriptionHeader = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${activeSubscriptionId}"]`)
                    .closest('.wc-subscription')
                    .find('h3');
                    
                // Get the index of the subscription panel
                const panelIndex = $('#bocs-subscriptions-accordion > div > h3').index(subscriptionHeader);
                
                // Activate the accordion panel
                if (panelIndex !== -1) {
                    $('#bocs-subscriptions-accordion').accordion('option', 'active', panelIndex);
                }
            }
        },

        editSchedule: function(e) {
            e.preventDefault();
            const button = $(this);
            const detailsSection = button.closest('.details-section');
            
            // Hide the edit button and notice box
            button.hide();
            
            // Show the schedule editor
            detailsSection.find('.schedule-editor').show();
        },

        /**
         * Edit Address Handler
         * Shows the address editing interface
         */
        editAddress: async function(e) {
            e.preventDefault();
            const button = $(this);
            const addressType = button.data('type');
            const addressContainer = button.closest(`.${addressType}-address`);
            
            // Add loading state to button
            const originalButtonText = button.html();
            button.prop('disabled', true)
                  .addClass('button-loading')
                  .html('<span class="loading-spinner"></span> Loading...');
            
            // Find the subscription section and get the subscription ID
            const subscriptionSection = addressContainer.closest('.subscription-section');
            const subscriptionId = subscriptionSection.find('.subscription_renewal_early').data('subscription-id');
            
            if (!subscriptionId) {
                console.error('No subscription ID found');
                // Reset button state
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
                return;
            }
            
            try {
                if (!eventHandlers.subscriptionData || eventHandlers.subscriptionData.id !== subscriptionId) {
                    await eventHandlers.fetchSubscriptionData(subscriptionId);
                }
                
                eventHandlers.renderAddressEditor(addressType, addressContainer);
            } catch (error) {
                console.error('Error in editAddress:', error);
                helpers.showNotification('Failed to load address details. Please try again.', 'error');
                // Reset button on error
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        },

        /**
         * Fetch Subscription Data
         * Retrieves subscription data from the API
         */
        fetchSubscriptionData: async function(subscriptionId) {
            try {
                helpers.showNotification('Loading subscription details...', 'loading');
                
                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}`,
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (!response.data) {
                    throw new Error('Invalid API response structure');
                }

                eventHandlers.subscriptionData = response.data;
                helpers.hideNotification();
                
                return response.data;
            } catch (error) {
                console.error('Error fetching subscription data:', error);
                helpers.showNotification('Failed to load subscription details. Please try again.', 'error');
                throw error;
            }
        },

        /**
         * Render Address Editor
         * Creates and displays the address editing interface
         */
        renderAddressEditor: function(addressType, addressContainer) {
            if (!addressContainer.find('.address-editor').length) {
                const addressData = addressType === 'billing' ? 
                    eventHandlers.subscriptionData.billing : 
                    eventHandlers.subscriptionData.shipping;

                const editorHtml = `
                    <div class="address-editor">
                        <form class="edit-address-form" data-type="${addressType}">
                            <div class="form-row">
                                <label for="${addressType}_first_name"><?php esc_html_e('First Name', 'bocs-wordpress'); ?></label>
                                <input type="text" id="${addressType}_first_name" name="firstName" value="${addressData.firstName}" required>
                            </div>
                            <div class="form-row">
                                <label for="${addressType}_last_name"><?php esc_html_e('Last Name', 'bocs-wordpress'); ?></label>
                                <input type="text" id="${addressType}_last_name" name="lastName" value="${addressData.lastName}" required>
                            </div>
                            <div class="form-row full-width">
                                <label for="${addressType}_address1"><?php esc_html_e('Address Line 1', 'bocs-wordpress'); ?></label>
                                <input type="text" id="${addressType}_address1" name="address1" value="${addressData.address1}" required>
                            </div>
                            <div class="form-row full-width">
                                <label for="${addressType}_address2"><?php esc_html_e('Address Line 2', 'bocs-wordpress'); ?></label>
                                <input type="text" id="${addressType}_address2" name="address2" value="${addressData.address2 || ''}">
                            </div>
                            <div class="form-row">
                                <label for="${addressType}_city"><?php esc_html_e('City', 'bocs-wordpress'); ?></label>
                                <input type="text" id="${addressType}_city" name="city" value="${addressData.city}" required>
                            </div>
                            <div class="form-row">
                                <label for="${addressType}_state"><?php esc_html_e('State', 'bocs-wordpress'); ?></label>
                                <input type="text" id="${addressType}_state" name="state" value="${addressData.state}" required>
                            </div>
                            <div class="form-row">
                                <label for="${addressType}_postcode"><?php esc_html_e('Postcode', 'bocs-wordpress'); ?></label>
                                <input type="text" id="${addressType}_postcode" name="postcode" value="${addressData.postcode}" required>
                            </div>
                            <div class="form-row">
                                <label for="${addressType}_country"><?php esc_html_e('Country', 'bocs-wordpress'); ?></label>
                                <input type="text" id="${addressType}_country" name="country" value="${addressData.country}" required>
                            </div>
                            <div class="button-group">
                                <button type="button" class="cancel-address-edit"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                                <button type="submit" class="save-address woocommerce-button button alt">
                                    <?php esc_html_e('Save Address', 'bocs-wordpress'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                `;
                addressContainer.append(editorHtml);
            }
            
            // Show editor and hide button
            addressContainer.find('.address-editor').show();
            addressContainer.find('.edit-address-link').hide();
        },

        /**
         * Save Address Handler
         * Handles the submission of address updates
         */
        saveAddress: async function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            const form = $(this);
            const formId = form.data('type') + '-form'; // Create unique identifier
            
            // Check if this form is already being processed
            if (window.processingForms && window.processingForms[formId]) {
                console.log('Form submission already in progress');
                return;
            }
            
            // Initialize processing forms tracker if it doesn't exist
            window.processingForms = window.processingForms || {};
            window.processingForms[formId] = true;
            
            const button = form.find('.save-address');
            const addressType = form.data('type');
            let originalButtonText = button.html();
            
            // Get subscription ID from the subscription section
            const subscriptionSection = form.closest('.subscription-section');
            const subscriptionId = subscriptionSection.find('.subscription_renewal_early').data('subscription-id');
            
            if (!subscriptionId || subscriptionId === 'null' || subscriptionId === 'undefined') {
                console.error('Invalid subscription ID:', subscriptionId);
                helpers.showNotification('Could not find subscription details', 'error');
                delete window.processingForms[formId];
                return;
            }

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Saving...');

                // Collect form data into the correct format
                const addressData = {
                    firstName: form.find(`[name="firstName"]`).val().trim(),
                    lastName: form.find(`[name="lastName"]`).val().trim(),
                    country: form.find(`[name="country"]`).val().trim(),
                    address1: form.find(`[name="address1"]`).val().trim(),
                    address2: form.find(`[name="address2"]`).val().trim() || '',
                    city: form.find(`[name="city"]`).val().trim(),
                    state: form.find(`[name="state"]`).val().trim(),
                    postcode: form.find(`[name="postcode"]`).val().trim(),
                    company: '',
                    phone: '',
                    email: addressType === 'billing' ? '<?php echo esc_js(wp_get_current_user()->user_email); ?>' : ''
                };

                // Validate required fields
                const requiredFields = ['firstName', 'lastName', 'country', 'address1', 'city', 'state', 'postcode'];
                const missingFields = requiredFields.filter(field => !addressData[field]);
                
                if (missingFields.length > 0) {
                    throw new Error(`Missing required fields: ${missingFields.join(', ')}`);
                }

                const updatePayload = {
                    [addressType]: addressData
                };

                console.log(`Updating ${addressType} address for subscription:`, subscriptionId, updatePayload);

                // Single AJAX request with explicit error handling
                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}`,
                    method: 'PUT',
                    data: JSON.stringify(updatePayload),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                // Validate response
                if (!response || typeof response !== 'object') {
                    throw new Error('Invalid API response format');
                }

                if (response.code === 200) {
                    // Update stored subscription data
                    if (response.data) {
                        eventHandlers.subscriptionData = response.data;
                    }

                    // Update displayed address
                    const addressContainer = form.closest(`.${addressType}-address`);
                    const addressDisplay = addressContainer.find('address');
                    const formattedAddress = [
                        `${addressData.firstName} ${addressData.lastName}`,
                        addressData.address1,
                        addressData.address2,
                        addressData.city,
                        addressData.state,
                        addressData.postcode,
                        addressData.country
                    ].filter(Boolean).join(', ');

                    addressDisplay.text(formattedAddress);

                    // Hide editor and show edit button
                    form.closest('.address-editor').hide();
                    addressContainer.find('.edit-address-link').show();

                    helpers.showNotification('Address updated successfully', 'success');
                } else {
                    throw new Error(response.message || 'Failed to update address');
                }
            } catch (error) {
                console.error('Error updating address:', error);
                helpers.showNotification(
                    error.message || 'Failed to update address. Please try again.',
                    'error'
                );
            } finally {
                // Reset form processing state
                delete window.processingForms[formId];
                
                // Reset button state
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText || 'Save Address');
            }
        }
    };

    /**
     * Helper Functions Object
     * Contains utility functions for common operations
     */
    const helpers = {
        /**
         * Show Notification
         * Displays a toast notification to the user
         * 
         * @param {string} message - The message to display
         * @param {string} type - The notification type (success/error/loading)
         */
        showNotification: function(message, type = 'success') {
            const notificationElement = $('.bocs-notification');
            
            // Remove any existing notifications
            notificationElement.remove();
            
            // Create notification HTML based on type
            let notificationHtml = `
                <div class="bocs-notification ${type}">
                    ${type === 'loading' ? '<div class="loading-spinner"></div>' : ''}
                    <span class="message">${message}</span>
                </div>
            `;
            
            // Add notification to the page
            $('body').append(notificationHtml);
            
            // Don't auto-hide loading notifications
            if (type !== 'loading') {
                setTimeout(helpers.hideNotification, 3000);
            }
        },

        /**
         * Hide Notification
         * Removes active notifications from the display
         */
        hideNotification: function() {
            $('.bocs-notification').remove();
        },

        /**
         * Update Totals Display
         * Updates the subscription totals display with new values
         * 
         * @param {number} subtotal - The subscription subtotal
         * @param {number} discountTotal - The total discount amount
         * @param {number} total - The final total amount
         */
        updateTotalsDisplay: function(subtotal, discountTotal, total) {
            const totalsHtml = `
                <div class="subscription-totals">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>
                            <span class="woocommerce-Price-amount amount">
                                <bdi>
                                    <span class="woocommerce-Price-currencySymbol">$</span>${subtotal.toFixed(2)}
                                </bdi>
                            </span>
                        </span>
                    </div>
                    <div class="total-row">
                        <span>Discount:</span>
                        <span>
                            <span class="woocommerce-Price-amount amount">
                                <bdi>
                                    -<span class="woocommerce-Price-currencySymbol">$</span>${discountTotal.toFixed(2)}
                                </bdi>
                            </span>
                        </span>
                    </div>
                    <div class="total-row">
                        <span>Delivery:</span>
                        <span>
                            <span class="woocommerce-Price-amount amount">
                                <bdi>
                                    <span class="woocommerce-Price-currencySymbol">$</span>0.00
                                </bdi>
                            </span>
                        </span>
                    </div>
                    <div class="total-row total">
                        <span>Total:</span>
                        <span>
                            <span class="woocommerce-Price-amount amount">
                                <bdi>
                                    <span class="woocommerce-Price-currencySymbol">$</span>${total.toFixed(2)}
                                </bdi>
                            </span>
                        </span>
                    </div>
                </div>
            `;

            // Update the totals section
            $('.subscription-totals').replaceWith(totalsHtml);
        },

        /**
         * Load Frequency Options
         * Fetches and populates frequency options for both editors
         * 
         * @param {string} bocsId - The BOCS widget ID
         * @param {Object} currentFrequency - The current frequency settings
         */
        loadFrequencyOptions: async function(bocsId, currentFrequency) {
            console.log('Loading frequency options...', {
                bocsId: bocsId,
                currentFrequency: currentFrequency
            });

            const loadingElements = $('.frequency-options-loading, .pause-options-loading');
            const contentElements = $('.frequency-options-content, .pause-options-content');
            
            try {
                loadingElements.show();
                contentElements.empty();

                console.log('Fetching frequency data from API...');

                // Function to generate HTML for frequency options
                const generateFrequencyHtml = (frequencies) => {
                    const frequencyOptionsHtml = frequencies.map(freq => {
                        const freqName = `Every ${freq.frequency} ${freq.frequency > 1 ? freq.timeUnit : freq.timeUnit.replace(/s$/, '')}`;
                        const discountText = freq.discount > 0 ? ` (${freq.discount}% off)` : '';
                        const isChecked = currentFrequency && currentFrequency.id === freq.id;
                        
                        return `
                            <label class="frequency-option">
                                <input type="radio" name="frequency" 
                                    value="${freq.id}" 
                                    data-name="${freqName}${discountText}"
                                    data-discount="${freq.discount}"
                                    data-time-unit="${freq.timeUnit}"
                                    data-frequency="${freq.frequency}"
                                    ${isChecked ? 'checked' : ''}>
                                <span class="radio-label">${freqName}${discountText}</span>
                            </label>
                        `;
                    }).join('');

                    const pauseOptionsHtml = frequencies.map(freq => {
                        const pauseText = `Pause for ${freq.frequency} ${freq.frequency > 1 ? freq.timeUnit : freq.timeUnit.replace(/s$/, '')}`;
                        return `
                            <label class="frequency-option">
                                <input type="radio" name="pause_duration" 
                                    value="${freq.id}" 
                                    data-frequency="${freq.frequency}"
                                    data-time-unit="${freq.timeUnit}">
                                <span class="radio-label">${pauseText}</span>
                            </label>
                        `;
                    }).join('');

                    $('.frequency-options-content').html(frequencyOptionsHtml);
                    $('.pause-options-content').html(pauseOptionsHtml);
                };

                // Try first API endpoint
                let response = await $.ajax({
                    url: '<?php echo BOCS_LIST_WIDGETS_URL; ?>' + bocsId + '/bocs',
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                        xhr.setRequestHeader('Content-Type', ' application/json');
                    }
                });

                // If first endpoint doesn't have adjustments, try second endpoint
                if (!(response.data?.priceAdjustment?.adjustments)) {
                    response = await new Promise(resolve => setTimeout(async () => {
                        const result = await $.ajax({
                            url: '<?php echo BOCS_LIST_WIDGETS_URL; ?>' + bocsId,
                            method: 'GET',
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                                xhr.setRequestHeader('Content-Type', ' application/json');
                            }
                        });
                        resolve(result);
                    }, 5000)); // 5000 milliseconds = 5 seconds

                    // Try to parse body data if available
                    if (response.data?.body) {
                        try {
                            const bodyData = JSON.parse(response.data.body);
                            if (bodyData.content?.[0]?.props?.selected?.[0]?.priceAdjustment?.adjustments) {
                                response.data.priceAdjustment = bodyData.content[0].props.selected[0].priceAdjustment;
                            }
                        } catch (e) {
                            console.error('Error parsing body JSON:', e);
                        }
                    }
                }

                // Generate HTML if we have frequency data
                if (response.data?.priceAdjustment?.adjustments) {
                    generateFrequencyHtml(response.data.priceAdjustment.adjustments);
                } else {
                    console.warn('No frequency data found in response:', response);
                    contentElements.html('<p class="error">No frequency options available.</p>');
                }
            } catch (error) {
                console.error('Error fetching frequency data:', error);
                contentElements.html('<p class="error">Failed to load frequency options.</p>');
            } finally {
                loadingElements.hide();
            }
        }
    };

    /**
     * Event Bindings
     * Connects DOM elements to their respective event handlers
     */
    $(document)
        .on('click', 'button.view-details', eventHandlers.viewDetails)
        .on('click', '.edit-link', eventHandlers.editFrequency)
        .on('click', '.cancel-edit', eventHandlers.cancelEdit)
        .on('click', '.save-frequency', eventHandlers.saveFrequency)
        .on('click', '.back-to-subscription-button, .back-to-subscription', eventHandlers.backToSubscription)
        .on('click', '.subscription_renewal_early, .pay-now', async function(e) {
            e.preventDefault();
            const button = $(this);
            const subscriptionId = button.data('subscription-id') || activeSubscriptionId;
            const originalButtonText = button.html();

            if (!subscriptionId) {
                console.error('No subscription ID found');
                return;
            }

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        // For example, update next payment date if available in response
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                if (!response?.data?.paymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        })
        .on('change', 'input[name="schedule_action"]', function() {
            // Enable/disable corresponding inputs
            const selectedValue = $(this).val();
            
            // Disable all inputs first
            $('select[name="pause_duration"], input[name="new_date"]').prop('disabled', true);
            
            // Enable the relevant input based on selection
            if (selectedValue === 'pause') {
                $('select[name="pause_duration"]').prop('disabled', false);
            } else if (selectedValue === 'date') {
                $('input[name="new_date"]').prop('disabled', false);
            }
        })
        .on('click', '.details-section:has(.schedule-editor) .edit-link', eventHandlers.editSchedule)
        .on('click', '.edit-address-link', eventHandlers.editAddress)
        .on('click', '.cancel-address-edit', function(e) {
            e.preventDefault();
            const editor = $(this).closest('.address-editor');
            editor.hide();
            editor.closest('.billing-address, .shipping-address').find('.edit-address-link').show();
        })
        .on('submit', '.edit-address-form', async function(e) {
            e.preventDefault();
            const form = $(this);
            const addressType = form.data('type');
            const submitButton = form.find('.save-address');
            const originalButtonText = submitButton.html();

            try {
                submitButton.prop('disabled', true)
                           .addClass('button-loading')
                           .html('<span class="loading-spinner"></span> Saving...');
                
                helpers.showNotification('Updating address...', 'loading');

                // Collect form data
                const formData = {};
                form.serializeArray().forEach(item => {
                    formData[item.name] = item.value;
                });

                // Prepare update payload
                const updatePayload = {
                    [addressType]: formData
                };

                // Send update to API
                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${activeSubscriptionId}`,
                    method: 'PUT',
                    data: JSON.stringify(updatePayload),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update displayed address
                    const addressText = Object.values(formData).filter(Boolean).join(', ');
                    form.closest(`.${addressType}-address`).find('address').text(addressText);

                    // Hide editor and show edit button
                    form.closest('.address-editor').hide();
                    form.closest(`.${addressType}-address`).find('.edit-address-link').show();

                    helpers.showNotification('Address updated successfully', 'success');
                } else {
                    throw new Error(response.message || 'Failed to update address');
                }
            } catch (error) {
                console.error('Error updating address:', error);
                helpers.showNotification('Failed to update address. Please try again.', 'error');
            } finally {
                submitButton.prop('disabled', false)
                           .removeClass('button-loading')
                           .html(originalButtonText);
            }
        })
        .on('submit', '.edit-address-form', eventHandlers.saveAddress);

    // Function to calculate and display next payment date
    function updateNextPaymentPreview() {
        const selectedOption = $('input[name="pause_duration"]:checked');
        if (!selectedOption.length) {
            $('.next-payment-preview').hide();
            return;
        }

        // Get next payment date from the data attribute instead of PHP echo
        let nextPaymentDate = new Date($('.subscription-next-payment').data('next-payment'));

        if (selectedOption.val() === 'custom_date') {
            const customDate = $('input[name="new_date"]').val();
            if (customDate) {
                nextPaymentDate = new Date(customDate);
            }
        } else {
            const frequency = parseInt(selectedOption.data('frequency'));
            const timeUnit = selectedOption.data('time-unit');
            
            if (timeUnit.toLowerCase() === 'month' || timeUnit.toLowerCase() === 'months') {
                nextPaymentDate.setMonth(nextPaymentDate.getMonth() + frequency);
            } else if (timeUnit.toLowerCase() === 'week' || timeUnit.toLowerCase() === 'weeks') {
                nextPaymentDate.setDate(nextPaymentDate.getDate() + (frequency * 7));
            } else if (timeUnit.toLowerCase() === 'day' || timeUnit.toLowerCase() === 'days') {
                nextPaymentDate.setDate(nextPaymentDate.getDate() + frequency);
            } else if (timeUnit.toLowerCase() === 'year' || timeUnit.toLowerCase() === 'years') {
                nextPaymentDate.setFullYear(nextPaymentDate.getFullYear() + frequency);
            }
        }

        // Format and display the date
        const formattedDate = nextPaymentDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        $('.preview-date').text(formattedDate);
        $('.next-payment-preview').show();
    }

    // Update preview when pause duration changes
    $('input[name="pause_duration"]').on('change', updateNextPaymentPreview);
    
    // Update preview when custom date changes
    $('input[name="new_date"]').on('change', function() {
        if ($('input[name="pause_duration"][value="custom_date"]').is(':checked')) {
            updateNextPaymentPreview();
        }
    });

    // Remove any existing handlers first
    $(document).off('submit', '.edit-address-form');
    
    // Add form submission handler with namespace to prevent multiple bindings
    $(document).on('submit.addressUpdate', '.edit-address-form', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // Stop any other handlers from firing
        
        // Call saveAddress with proper context
        return eventHandlers.saveAddress.call(this, e);
    });
    
    // Update cancel button handler
    $(document).off('click', '.cancel-address-edit');
    $(document).on('click.addressCancel', '.cancel-address-edit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const editor = $(this).closest('.address-editor');
        const addressContainer = editor.closest('.billing-address, .shipping-address');
        
        // Hide editor and show edit button
        editor.hide();
        addressContainer.find('.edit-address-link').show();
    });

    /**
     * Cancel Subscription Handler
     */
    // Remove any existing handlers first
    $('.cancel-button').off('click');
    
    // Bind new handler
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Remove any existing handlers from modal buttons
        $('#cancel-subscription-modal .modal-cancel, #cancel-subscription-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    /**
     * Modal Helper Functions
     */
    const modalHelpers = {
        show: function(modalId) {
            $(`#${modalId}`).css('display', 'flex').hide().fadeIn(200);
        },
        hide: function(modalId) {
            $(`#${modalId}`).fadeOut(200);
        }
    };

    /**
     * Early Renewal Handler
     */
    // Remove any existing handlers first
    $(document).off('click', '.subscription_renewal_early, .pay-now');
    
    // Bind new handler
    $(document).on('click', '.subscription_renewal_early, .pay-now', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id') || activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('early-renewal-modal');

        // Remove any existing handlers from modal buttons
        $('#early-renewal-modal .modal-cancel, #early-renewal-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#early-renewal-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('early-renewal-modal');
        });

        $('#early-renewal-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('early-renewal-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response?.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Check if we have a successful response with payment URL
                const hasPaymentUrl = response?.code === 200 && response?.data?.paymentUrl;
                
                if (!hasPaymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    /**
     * Cancel Subscription Handler
     */
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    /**
     * Modal Helper Functions
     */
    const modalHelpers = {
        show: function(modalId) {
            $(`#${modalId}`).css('display', 'flex').hide().fadeIn(200);
        },
        hide: function(modalId) {
            $(`#${modalId}`).fadeOut(200);
        }
    };

    /**
     * Early Renewal Handler
     */
    // Remove any existing handlers first
    $(document).off('click', '.subscription_renewal_early, .pay-now');
    
    // Bind new handler
    $(document).on('click', '.subscription_renewal_early, .pay-now', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id') || activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('early-renewal-modal');

        // Remove any existing handlers from modal buttons
        $('#early-renewal-modal .modal-cancel, #early-renewal-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#early-renewal-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('early-renewal-modal');
        });

        $('#early-renewal-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('early-renewal-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response?.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Check if we have a successful response with payment URL
                const hasPaymentUrl = response?.code === 200 && response?.data?.paymentUrl;
                
                if (!hasPaymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    /**
     * Cancel Subscription Handler
     */
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    /**
     * Modal Helper Functions
     */
    const modalHelpers = {
        show: function(modalId) {
            $(`#${modalId}`).css('display', 'flex').hide().fadeIn(200);
        },
        hide: function(modalId) {
            $(`#${modalId}`).fadeOut(200);
        }
    };

    /**
     * Early Renewal Handler
     */
    // Remove any existing handlers first
    $(document).off('click', '.subscription_renewal_early, .pay-now');
    
    // Bind new handler
    $(document).on('click', '.subscription_renewal_early, .pay-now', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id') || activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('early-renewal-modal');

        // Remove any existing handlers from modal buttons
        $('#early-renewal-modal .modal-cancel, #early-renewal-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#early-renewal-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('early-renewal-modal');
        });

        $('#early-renewal-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('early-renewal-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response?.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Check if we have a successful response with payment URL
                const hasPaymentUrl = response?.code === 200 && response?.data?.paymentUrl;
                
                if (!hasPaymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    /**
     * Cancel Subscription Handler
     */
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    /**
     * Modal Helper Functions
     */
    const modalHelpers = {
        show: function(modalId) {
            $(`#${modalId}`).css('display', 'flex').hide().fadeIn(200);
        },
        hide: function(modalId) {
            $(`#${modalId}`).fadeOut(200);
        }
    };

    /**
     * Early Renewal Handler
     */
    // Remove any existing handlers first
    $(document).off('click', '.subscription_renewal_early, .pay-now');
    
    // Bind new handler
    $(document).on('click', '.subscription_renewal_early, .pay-now', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id') || activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('early-renewal-modal');

        // Remove any existing handlers from modal buttons
        $('#early-renewal-modal .modal-cancel, #early-renewal-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#early-renewal-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('early-renewal-modal');
        });

        $('#early-renewal-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('early-renewal-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response?.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Check if we have a successful response with payment URL
                const hasPaymentUrl = response?.code === 200 && response?.data?.paymentUrl;
                
                if (!hasPaymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    /**
     * Cancel Subscription Handler
     */
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    /**
     * Modal Helper Functions
     */
    const modalHelpers = {
        show: function(modalId) {
            $(`#${modalId}`).css('display', 'flex').hide().fadeIn(200);
        },
        hide: function(modalId) {
            $(`#${modalId}`).fadeOut(200);
        }
    };

    /**
     * Early Renewal Handler
     */
    // Remove any existing handlers first
    $(document).off('click', '.subscription_renewal_early, .pay-now');
    
    // Bind new handler
    $(document).on('click', '.subscription_renewal_early, .pay-now', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id') || activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('early-renewal-modal');

        // Remove any existing handlers from modal buttons
        $('#early-renewal-modal .modal-cancel, #early-renewal-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#early-renewal-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('early-renewal-modal');
        });

        $('#early-renewal-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('early-renewal-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response?.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Check if we have a successful response with payment URL
                const hasPaymentUrl = response?.code === 200 && response?.data?.paymentUrl;
                
                if (!hasPaymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    /**
     * Cancel Subscription Handler
     */
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    /**
     * Modal Helper Functions
     */
    const modalHelpers = {
        show: function(modalId) {
            $(`#${modalId}`).css('display', 'flex').hide().fadeIn(200);
        },
        hide: function(modalId) {
            $(`#${modalId}`).fadeOut(200);
        }
    };

    /**
     * Early Renewal Handler
     */
    // Remove any existing handlers first
    $(document).off('click', '.subscription_renewal_early, .pay-now');
    
    // Bind new handler
    $(document).on('click', '.subscription_renewal_early, .pay-now', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id') || activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('early-renewal-modal');

        // Remove any existing handlers from modal buttons
        $('#early-renewal-modal .modal-cancel, #early-renewal-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#early-renewal-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('early-renewal-modal');
        });

        $('#early-renewal-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('early-renewal-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response?.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Check if we have a successful response with payment URL
                const hasPaymentUrl = response?.code === 200 && response?.data?.paymentUrl;
                
                if (!hasPaymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    /**
     * Cancel Subscription Handler
     */
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    /**
     * Modal Helper Functions
     */
    const modalHelpers = {
        show: function(modalId) {
            $(`#${modalId}`).css('display', 'flex').hide().fadeIn(200);
        },
        hide: function(modalId) {
            $(`#${modalId}`).fadeOut(200);
        }
    };

    /**
     * Early Renewal Handler
     */
    // Remove any existing handlers first
    $(document).off('click', '.subscription_renewal_early, .pay-now');
    
    // Bind new handler
    $(document).on('click', '.subscription_renewal_early, .pay-now', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id') || activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('early-renewal-modal');

        // Remove any existing handlers from modal buttons
        $('#early-renewal-modal .modal-cancel, #early-renewal-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#early-renewal-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('early-renewal-modal');
        });

        $('#early-renewal-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('early-renewal-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response?.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Check if we have a successful response with payment URL
                const hasPaymentUrl = response?.code === 200 && response?.data?.paymentUrl;
                
                if (!hasPaymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    /**
     * Cancel Subscription Handler
     */
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    /**
     * Modal Helper Functions
     */
    const modalHelpers = {
        show: function(modalId) {
            $(`#${modalId}`).css('display', 'flex').hide().fadeIn(200);
        },
        hide: function(modalId) {
            $(`#${modalId}`).fadeOut(200);
        }
    };

    /**
     * Early Renewal Handler
     */
    // Remove any existing handlers first
    $(document).off('click', '.subscription_renewal_early, .pay-now');
    
    // Bind new handler
    $(document).on('click', '.subscription_renewal_early, .pay-now', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id') || activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('early-renewal-modal');

        // Remove any existing handlers from modal buttons
        $('#early-renewal-modal .modal-cancel, #early-renewal-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#early-renewal-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('early-renewal-modal');
        });

        $('#early-renewal-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('early-renewal-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Processing early renewal...', 'loading');

                // Send early renewal request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/renew`,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200 && response.data) {
                    if (response.data.paymentUrl) {
                        // Update UI to show success
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // If there's a payment URL, create a payment button
                        const paymentButton = $(`
                            <a href="${response.data.paymentUrl}" 
                               class="woocommerce-button button bocs-button pay-now"
                               target="_blank">
                                <?php esc_html_e('Complete Payment', 'bocs-wordpress'); ?>
                            </a>
                        `);
                        
                        // Replace the early renewal button with the payment button
                        button.replaceWith(paymentButton);
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        
                        // Update any relevant UI elements without refresh
                        if (response.data.nextPaymentDate) {
                            $('.next-payment-date').text(response.data.nextPaymentDate);
                        }
                    }
                } else {
                    throw new Error(response?.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Check if we have a successful response with payment URL
                const hasPaymentUrl = response?.code === 200 && response?.data?.paymentUrl;
                
                if (!hasPaymentUrl) {
                    // Only reset button if we didn't replace it with a payment button
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    /**
     * Cancel Subscription Handler
     */
    $('.cancel-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const subscriptionId = activeSubscriptionId;

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('cancel-subscription-modal');

        // Handle modal actions
        $('#cancel-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('cancel-subscription-modal');
        });

        $('#cancel-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('cancel-subscription-modal');
            const originalButtonText = button.html();

            try {
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Canceling...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Canceling subscription...', 'loading');

                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}/cancel`,
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update status in both list and detail views
                    const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${subscriptionId}"]`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status text
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const renewalButton = subscriptionInList.find('.subscription_renewal_early');
                        if (renewalButton.length) {
                            renewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-active')
                            .addClass('status-cancelled')
                            .text('Cancelled');

                        // Replace Early Renewal button with Activate button
                        const detailRenewalButton = detailView.find('.subscription_renewal_early');
                        if (detailRenewalButton.length) {
                            detailRenewalButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_activate" 
                                    id="activate_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Reactivate', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription cancelled successfully', 'success');
                    setTimeout(() => {
                        $('.back-to-subscription').trigger('click');
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to cancel subscription');
                }
            } catch (error) {
                console.error('Error canceling subscription:', error);
                helpers.showNotification('Failed to cancel subscription. Please try again.', 'error');
            } finally {
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
            }
        });
    });

    // Close modal when clicking outside
    $('.bocs-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });

    /**
     * Activate Subscription Handler
     */
    $(document).off('click', '.subscription_activate');
    
    $(document).on('click', '.subscription_activate', function(e) {
        e.preventDefault();
        const button = $(this);
        const subscriptionId = button.data('subscription-id');

        if (!subscriptionId) {
            console.error('No subscription ID found');
            helpers.showNotification('Could not identify subscription', 'error');
            return;
        }

        // Show confirmation modal
        modalHelpers.show('activate-subscription-modal');

        // Remove any existing handlers from modal buttons
        $('#activate-subscription-modal .modal-cancel, #activate-subscription-modal .modal-confirm').off('click');

        // Handle modal actions
        $('#activate-subscription-modal .modal-cancel').one('click', function() {
            modalHelpers.hide('activate-subscription-modal');
        });

        $('#activate-subscription-modal .modal-confirm').one('click', async function() {
            modalHelpers.hide('activate-subscription-modal');
            const originalButtonText = button.html();
            let response = null;

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> <?php esc_js(_e('Activating...', 'bocs-wordpress')); ?>');

                helpers.showNotification('Activating subscription...', 'loading');

                // Send activation request
                response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}`,
                    method: 'PUT',
                    data: JSON.stringify({
                        subscriptionStatus: 'active'
                    }),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (response.code === 200) {
                    // Update UI to reflect activated status
                    const subscriptionInList = $(`#bocs-subscriptions-accordion #activate_${subscriptionId}`)
                        .closest('.wc-subscription');
                    
                    if (subscriptionInList.length) {
                        // Update status in the list view
                        subscriptionInList.find('.subscription-status')
                            .removeClass('status-cancelled status-paused')
                            .addClass('status-active')
                            .text('Active');

                        // Replace activate button with early renewal button
                        button.replaceWith(`
                            <button 
                                class="woocommerce-button button bocs-button subscription_renewal_early" 
                                id="renewal_${subscriptionId}"
                                data-subscription-id="${subscriptionId}"
                            >
                                <?php esc_html_e('Early Renewal', 'bocs-wordpress'); ?>
                            </button>
                        `);
                    }

                    // Update the detail view if it exists
                    const detailView = $('.subscription-details');
                    if (detailView.length) {
                        // Update status
                        detailView.find('.subscription-status')
                            .removeClass('status-cancelled status-paused')
                            .addClass('status-active')
                            .text('Active');

                        // Replace activate button with early renewal button in detail view
                        const detailActivateButton = detailView.find('.subscription_activate');
                        if (detailActivateButton.length) {
                            detailActivateButton.replaceWith(`
                                <button 
                                    class="woocommerce-button button bocs-button subscription_renewal_early" 
                                    id="renewal_${subscriptionId}"
                                    data-subscription-id="${subscriptionId}"
                                >
                                    <?php esc_html_e('Early Renewal', 'bocs-wordpress'); ?>
                                </button>
                            `);
                        }
                    }

                    helpers.showNotification('Subscription activated successfully', 'success');
                } else {
                    throw new Error(response.message || 'Failed to activate subscription');
                }
            } catch (error) {
                console.error('Error activating subscription:', error);
                helpers.showNotification('Failed to activate subscription. Please try again.', 'error');
            } finally {
                if (!response?.code === 200) {
                    // Only reset button if activation failed
                    button.prop('disabled', false)
                          .removeClass('button-loading')
                          .html(originalButtonText);
                }
            }
        });
    });

    // Update Box Handler
    $(document).on('click', '.update-box', function(e) {
        e.preventDefault();
        const subscriptionId = $(this).data('subscription-id');
        if (subscriptionId) {
            window.location.href = `<?php echo esc_url(wc_get_endpoint_url('bocs-update-box', '')); ?>${subscriptionId}`;
        }
    });

    // Remove all product selection related handlers
    $(document).off('click', '.back-to-subscriptions, .cancel-product-selection');
    
    // Rest of your existing code...

    // Add click handler for Edit Payment Method button
    $('.edit-payment-method').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const subscriptionId = button.data('subscription-id');
        
        if (!subscriptionId) {
            console.error('No subscription ID found for payment method edit');
            return;
        }
        
        console.log('Opening payment method modal for subscription ID:', subscriptionId);
        
        // Create payment method modal if it doesn't exist
        if ($('#payment-method-modal').length === 0) {
            $('body').append(`
                <div id="payment-method-modal" class="bocs-modal">
                    <div class="bocs-modal-content">
                        <h3><?php esc_html_e('Edit Payment Method', 'bocs-wordpress'); ?></h3>
                        <p><?php esc_html_e('Select a payment method to use for this subscription:', 'bocs-wordpress'); ?></p>
                        <div class="payment-methods-container">
                            <div class="loading-state">
                                <div class="bocs-loading-spinner"></div>
                                <p><?php esc_html_e('Loading payment methods...', 'bocs-wordpress'); ?></p>
                            </div>
                            <div class="payment-methods-list" style="display:none;"></div>
                        </div>
                        <div class="bocs-modal-actions">
                            <button class="woocommerce-button button cancel-payment-edit"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                            <button class="woocommerce-button button update-payment-method"><?php esc_html_e('Update', 'bocs-wordpress'); ?></button>
                        </div>
                    </div>
                </div>
            `);
            
            // Add event handlers for the modal
            $('.cancel-payment-edit').on('click', function() {
                $('#payment-method-modal').removeClass('show');
            });
        }
        
        // Show modal
        $('#payment-method-modal').addClass('show');
        
        // Load payment methods
        loadPaymentMethods(subscriptionId);
    });
    
    // Function to load payment methods
    function loadPaymentMethods(subscriptionId) {
        const container = $('.payment-methods-list');
        const loadingElement = $('.payment-methods-container .loading-state');
        
        container.hide();
        loadingElement.show();
        
        // Ajax call to fetch payment methods
        $.ajax({
            url: wc_add_to_cart_params.ajax_url,
            type: 'POST',
            data: {
                action: 'bocs_get_payment_methods',
                subscription_id: subscriptionId,
                nonce: '<?php echo wp_create_nonce("bocs_get_payment_methods"); ?>'
            },
            success: function(response) {
                loadingElement.hide();
                
                if (response.success && response.data && response.data.length > 0) {
                    // Display payment methods
                    container.empty();
                    
                    response.data.forEach(function(method) {
                        container.append(`
                            <div class="payment-method-item">
                                <label class="payment-method-option">
                                    <input type="radio" name="payment_method" 
                                        value="${method.id}" 
                                        data-last4="${method.last4 || ''}"
                                        data-brand="${method.brand || ''}">
                                    <span class="payment-method-details">
                                        ${method.brand || ''} ${method.last4 ? '•••• ' + method.last4 : ''}
                                        ${method.expiry ? ' (Expires: ' + method.expiry + ')' : ''}
                                    </span>
                                </label>
                            </div>
                        `);
                    });
                    
                    // Add option to add a new payment method
                    container.append(`
                        <div class="payment-method-item add-new">
                            <label class="payment-method-option">
                                <input type="radio" name="payment_method" value="new">
                                <span class="payment-method-details">
                                    <i class="add-icon">+</i> <?php esc_html_e('Add a new payment method', 'bocs-wordpress'); ?>
                                </span>
                            </label>
                        </div>
                    `);
                    
                    container.show();
                } else {
                    // Show message if no payment methods
                    container.html(`
                        <div class="payment-method-message">
                            <p><?php esc_html_e('No payment methods found. Please add a new payment method.', 'bocs-wordpress'); ?></p>
                            <a href="<?php echo esc_url(wc_get_account_endpoint_url('payment-methods')); ?>" class="woocommerce-button button">
                                <?php esc_html_e('Add Payment Method', 'bocs-wordpress'); ?>
                            </a>
                        </div>
                    `);
                    container.show();
                }
            },
            error: function() {
                loadingElement.hide();
                container.html(`
                    <div class="payment-method-message error">
                        <p><?php esc_html_e('Failed to load payment methods. Please try again.', 'bocs-wordpress'); ?></p>
                    </div>
                `);
                container.show();
            }
        });
    }
    
    // Handle payment method update
    $(document).on('click', '.update-payment-method', function() {
        const button = $(this);
        const selectedMethod = $('input[name="payment_method"]:checked');
        
        if (!selectedMethod.length) {
            alert('<?php esc_html_e("Please select a payment method", "bocs-wordpress"); ?>');
            return;
        }
        
        const methodId = selectedMethod.val();
        const subscriptionId = $('.edit-payment-method').data('subscription-id');
        
        if (methodId === 'new') {
            // Redirect to add payment method page
            window.location.href = '<?php echo esc_url(wc_get_account_endpoint_url('payment-methods')); ?>';
            return;
        }
        
        // Show loading state
        button.prop('disabled', true)
              .addClass('button-loading')
              .html('<span class="loading-spinner"></span> Updating...');
        
        // Update payment method
        $.ajax({
            url: wc_add_to_cart_params.ajax_url,
            type: 'POST',
            data: {
                action: 'bocs_update_payment_method',
                subscription_id: subscriptionId,
                payment_method_id: methodId,
                nonce: '<?php echo wp_create_nonce("bocs_update_payment_method"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    helpers.showNotification('Payment method updated successfully', 'success');
                    
                    // Close modal
                    $('#payment-method-modal').removeClass('show');
                } else {
                    // Show error message
                    alert(response.data || '<?php esc_html_e("Failed to update payment method", "bocs-wordpress"); ?>');
                }
                
                // Reset button
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html('<?php esc_html_e("Update", "bocs-wordpress"); ?>');
            },
            error: function() {
                // Show error message
                alert('<?php esc_html_e("Failed to update payment method. Please try again.", "bocs-wordpress"); ?>');
                
                // Reset button
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html('<?php esc_html_e("Update", "bocs-wordpress"); ?>');
            }
        });
    });

    // Debug and fix for Edit Payment Method modal
    $(document).on('click', '.edit-payment-method', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Get the subscription ID
        const subscriptionId = $(this).data('subscription-id');
        console.log('Edit Payment Method clicked for subscription ID:', subscriptionId);
        
        // Check if payment-method-modal exists and create it if not
        if ($('#payment-method-modal').length === 0) {
            console.log('Creating payment method modal - it was missing');
            
            // Create the modal with proper z-index and styling
            $('body').append(`
                <div id="payment-method-modal" class="bocs-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:99999;">
                    <div class="bocs-modal-content" style="background:#ffffff; padding:35px; max-width:550px; width:90%; margin:5% auto; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.4);">
                        <h3 style="font-size:22px; margin-top:0; margin-bottom:20px; color:#333; border-bottom:2px solid #6c5ce7; padding-bottom:8px;"><?php esc_html_e('Edit Payment Method', 'bocs-wordpress'); ?></h3>
                        <p style="font-size:16px; color:#444; margin-bottom:20px;"><?php esc_html_e('Select a payment method for this subscription:', 'bocs-wordpress'); ?></p>
                        <div class="payment-methods-container">
                            <div class="loading-spinner" style="text-align:center; padding:20px;">
                                <div style="display:inline-block; width:30px; height:30px; border:3px solid #f3f3f3; border-top:3px solid #6c5ce7; border-radius:50%; animation:spin 1s linear infinite;"></div>
                                <p style="color:#444; margin-top:10px;"><?php esc_html_e('Loading payment methods...', 'bocs-wordpress'); ?></p>
                            </div>
                            <div class="payment-methods-list" style="display:none;"></div>
                        </div>
                        <div style="margin-top:25px; text-align:right;">
                            <button class="button cancel-payment-edit" style="background:#f1f1f1; color:#555; border:1px solid #ddd; padding:10px 20px; border-radius:5px; font-size:15px; cursor:pointer; font-weight:500; margin-right:10px;"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                            <button class="button update-payment" style="background:#6c5ce7; color:white; border:1px solid #6c5ce7; padding:10px 20px; border-radius:5px; font-size:15px; cursor:pointer; font-weight:500;"><?php esc_html_e('Update', 'bocs-wordpress'); ?></button>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    .bocs-modal {
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0,0,0,0.7);
                        z-index: 99999;
                        justify-content: center;
                        align-items: center;
                    }
                    .bocs-modal.show {
                        display: flex !important;
                    }
                    .bocs-modal-content {
                        background: #ffffff;
                        padding: 35px;
                        max-width: 550px;
                        width: 90%;
                        margin: 5% auto;
                        border-radius: 10px;
                        box-shadow: 0 10px 25px rgba(0,0,0,0.4);
                        position: relative;
                    }
                    .bocs-modal-content h3 {
                        font-size: 22px;
                        margin-top: 0;
                        margin-bottom: 20px;
                        color: #333;
                        border-bottom: 2px solid #6c5ce7;
                        padding-bottom: 8px;
                    }
                    .bocs-modal-content p {
                        font-size: 16px;
                        color: #444;
                        margin-bottom: 20px;
                    }
                    .payment-methods-list {
                        margin: 15px 0;
                    }
                    .payment-methods-list div {
                        padding: 15px;
                        margin-bottom: 12px;
                        border: 1px solid #ddd;
                        border-radius: 8px;
                        background: #f9f9f9;
                        transition: all 0.2s ease;
                    }
                    .payment-methods-list div:hover {
                        background: #f0f0f0;
                        border-color: #bbb;
                    }
                    .payment-methods-list label {
                        display: flex;
                        align-items: center;
                        cursor: pointer;
                        font-size: 16px;
                        color: #333;
                    }
                    .payment-methods-list input[type="radio"] {
                        margin-right: 12px;
                        transform: scale(1.2);
                    }
                    .payment-methods-list span {
                        flex: 1;
                    }
                    .bocs-modal-content .button {
                        padding: 10px 20px;
                        border-radius: 5px;
                        font-size: 15px;
                        cursor: pointer;
                        font-weight: 500;
                        transition: all 0.2s ease;
                    }
                    .bocs-modal-content .cancel-payment-edit {
                        background: #f1f1f1;
                        color: #555;
                        border: 1px solid #ddd;
                        margin-right: 10px;
                    }
                    .bocs-modal-content .cancel-payment-edit:hover {
                        background: #e5e5e5;
                        color: #333;
                    }
                    .bocs-modal-content .update-payment {
                        background: #6c5ce7;
                        color: white;
                        border: 1px solid #6c5ce7;
                    }
                    .bocs-modal-content .update-payment:hover {
                        background: #5d4ed6;
                    }
                    .loading-spinner div {
                        border-color: #f3f3f3;
                        border-top-color: #6c5ce7;
                    }
                </style>
            `);
            
            // Add event handlers for the modal
            $(document).on('click', '.cancel-payment-edit', function() {
                $('#payment-method-modal').removeClass('show').hide();
            });
        }
        
        // Force show the modal with both methods
        $('#payment-method-modal').addClass('show').show();
        
        // For testing - populate with dummy data
        setTimeout(function() {
            $('.payment-methods-container .loading-spinner').hide();
            
            // AJAX call to fetch both BOCS payment methods and WooCommerce payment methods
            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'bocs_get_payment_methods',
                    subscription_id: subscriptionId,
                    nonce: '<?php echo wp_create_nonce("bocs_get_payment_methods"); ?>'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        let html = '';
                        let hasPaymentMethods = false;
                        
                        // If BOCS payment methods exist
                        if (response.data.bocs_methods && response.data.bocs_methods.length > 0) {
                            response.data.bocs_methods.forEach(function(method, index) {
                                hasPaymentMethods = true;
                                html += `
                                    <div style="padding:15px; margin-bottom:12px; border:1px solid #ddd; border-radius:8px; background:#f9f9f9;">
                                        <label>
                                            <input type="radio" name="payment_method" value="${method.id}" ${index === 0 ? 'checked' : ''}> 
                                            <span style="font-size:16px; color:#333;">${method.card_type} •••• ${method.last4} (expires ${method.exp_month}/${method.exp_year})</span>
                                        </label>
                                    </div>
                                `;
                            });
                        }
                        
                        // If WooCommerce payment methods exist
                        if (response.data.wc_methods && response.data.wc_methods.length > 0) {
                            if (hasPaymentMethods) {
                                html += `<h4 style="margin-top:20px; font-size:16px; color:#333;">Other Payment Methods</h4>`;
                            }
                            
                            response.data.wc_methods.forEach(function(method) {
                                hasPaymentMethods = true;
                                html += `
                                    <div style="padding:15px; margin-bottom:12px; border:1px solid #ddd; border-radius:8px; background:#f9f9f9;">
                                        <label>
                                            <input type="radio" name="payment_method" value="wc_${method.token}" ${!html ? 'checked' : ''}> 
                                            <span style="font-size:16px; color:#333;">${method.method.brand} •••• ${method.method.last4} (expires ${method.method.exp_month}/${method.method.exp_year})</span>
                                        </label>
                                    </div>
                                `;
                            });
                        }
                        
                        // Add "add new payment method" option
                        html += `
                            <div style="padding:15px; margin-bottom:12px; border:1px solid #ddd; border-radius:8px; background:#f9f9f9;">
                                <label>
                                    <input type="radio" name="payment_method" value="new" ${!hasPaymentMethods ? 'checked' : ''}> 
                                    <span style="font-size:16px; color:#333;">+ Add new payment method</span>
                                </label>
                            </div>
                        `;
                        
                        // If no payment methods were found, show a message
                        if (!hasPaymentMethods) {
                            html = `
                                <div style="padding:15px; margin-bottom:20px; border:1px solid #f5c6cb; border-radius:8px; background:#f8d7da; color:#721c24;">
                                    <p style="margin:0; font-size:16px;">No payment methods found for this subscription.</p>
                                    <p style="margin:5px 0 0; font-size:14px;">Please add a new payment method below.</p>
                                </div>
                                <div style="padding:20px; margin-bottom:12px; border:2px solid #6c5ce7; border-radius:8px; background:#f9f9f9;">
                                    <label style="display:flex; align-items:center;">
                                        <input type="radio" name="payment_method" value="new" style="transform:scale(1.3); margin-right:15px;"> 
                                        <span style="font-size:18px; color:#333; font-weight:600;">
                                            <span style="color:#6c5ce7; margin-right:5px;">+</span> Add new payment method
                                        </span>
                                    </label>
                                    <p style="margin:10px 0 0 30px; font-size:14px; color:#666;">
                                        You will be redirected to the payment methods page to add a new card.
                                    </p>
                                </div>
                            `;
                        } else {
                            // Add "add new payment method" option as normal
                            html += `
                                <div style="padding:15px; margin-bottom:12px; border:1px solid #ddd; border-radius:8px; background:#f9f9f9;">
                                    <label>
                                        <input type="radio" name="payment_method" value="new"> 
                                        <span style="font-size:16px; color:#333;">+ Add new payment method</span>
                                    </label>
                                </div>
                            `;
                        }
                        
                        // If no payment methods were found, show a message
                        if (!hasPaymentMethods) {
                            html = `
                                <div style="padding:15px; margin-bottom:20px; border:1px solid #f5c6cb; border-radius:8px; background:#f8d7da; color:#721c24;">
                                    <p style="margin:0; font-size:16px;">No payment methods found for this subscription.</p>
                                    <p style="margin:5px 0 0; font-size:14px;">Please add a new payment method below.</p>
                                </div>
                                <div style="padding:20px; margin-bottom:12px; border:2px solid #6c5ce7; border-radius:8px; background:#f9f9f9;">
                                    <label style="display:flex; align-items:center;">
                                        <input type="radio" name="payment_method" value="new" style="transform:scale(1.3); margin-right:15px;"> 
                                        <span style="font-size:18px; color:#333; font-weight:600;">
                                            <span style="color:#6c5ce7; margin-right:5px;">+</span> Add new payment method
                                        </span>
                                    </label>
                                    <p style="margin:10px 0 0 30px; font-size:14px; color:#666;">
                                        You will be redirected to the payment methods page to add a new card.
                                    </p>
                                </div>
                            `;
                        } else {
                            // Add "add new payment method" option as normal
                            html += `
                                <div style="padding:15px; margin-bottom:12px; border:1px solid #ddd; border-radius:8px; background:#f9f9f9;">
                                    <label>
                                        <input type="radio" name="payment_method" value="new"> 
                                        <span style="font-size:16px; color:#333;">+ Add new payment method</span>
                                    </label>
                                </div>
                            `;
                        }
                        
                        $('.payment-methods-list').html(html).show();
                    } else {
                        // Error handling
                        $('.payment-methods-list').html(`
                            <div style="padding:15px; margin-bottom:12px; border:1px solid #f5c6cb; border-radius:8px; background:#f8d7da; color:#721c24;">
                                <p style="margin:0;">Error loading payment methods.</p>
                            </div>
                            <div style="padding:15px; margin-bottom:12px; border:1px solid #ddd; border-radius:8px; background:#f9f9f9;">
                                <label>
                                    <input type="radio" name="payment_method" value="new"> 
                                    <span style="font-size:16px; color:#333;">+ Add new payment method</span>
                                </label>
                            </div>
                        `).show();
                    }
                },
                error: function() {
                    // Fallback to just showing the add new option
                    $('.payment-methods-list').html(`
                        <div style="padding:15px; margin-bottom:12px; border:1px solid #f5c6cb; border-radius:8px; background:#f8d7da; color:#721c24;">
                            <p style="margin:0;">Could not connect to the server. Please try again.</p>
                        </div>
                        <div style="padding:15px; margin-bottom:12px; border:1px solid #ddd; border-radius:8px; background:#f9f9f9;">
                            <label>
                                <input type="radio" name="payment_method" value="new"> 
                                <span style="font-size:16px; color:#333;">+ Add new payment method</span>
                            </label>
                        </div>
                    `).show();
                }
            });
        }, 500);
    });
    
    // Add update payment handler
    $(document).on('click', '.update-payment', function() {
        const selectedMethod = $('input[name="payment_method"]:checked').val();
        
        if (selectedMethod === 'new') {
            // Show payment form in the modal instead of redirecting
            const subscriptionId = $('.edit-payment-method').data('subscription-id');
            
            // Hide the payment methods list and show a form
            $('.payment-methods-list').hide();
            
            // If the form doesn't exist yet, create it
            if ($('.payment-form-container').length === 0) {
                $('.payment-methods-container').append(`
                    <div class="payment-form-container" style="padding:20px; border:1px solid #ddd; border-radius:8px; background:white;">
                        <h4 style="margin-top:0; font-size:18px; color:#333; margin-bottom:15px;">Add Payment Method</h4>
                        <form id="add-payment-method-form">
                            <div style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:5px; font-weight:500; color:#555;">Card Number</label>
                                <input type="text" id="card_number" placeholder="1234 5678 9012 3456" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;" required>
                            </div>
                            <div style="display:flex; gap:15px; margin-bottom:15px;">
                                <div style="flex:1;">
                                    <label style="display:block; margin-bottom:5px; font-weight:500; color:#555;">Expiry Date</label>
                                    <input type="text" id="card_expiry" placeholder="MM/YY" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;" required>
                                </div>
                                <div style="flex:1;">
                                    <label style="display:block; margin-bottom:5px; font-weight:500; color:#555;">CVV</label>
                                    <input type="text" id="card_cvc" placeholder="123" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;" required>
                                </div>
                            </div>
                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:5px; font-weight:500; color:#555;">Name on Card</label>
                                <input type="text" id="card_name" placeholder="John Doe" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;" required>
                            </div>
                            <div style="text-align:right;">
                                <button type="button" class="back-to-methods" style="background:#f1f1f1; color:#555; border:1px solid #ddd; padding:10px 15px; border-radius:5px; font-size:14px; cursor:pointer; margin-right:10px;">Back</button>
                                <button type="submit" style="background:#6c5ce7; color:white; border:1px solid #6c5ce7; padding:10px 15px; border-radius:5px; font-size:14px; cursor:pointer;">Save Payment Method</button>
                            </div>
                        </form>
                    </div>
                `);
                
                // Add event handler for back button
                $(document).on('click', '.back-to-methods', function() {
                    $('.payment-form-container').hide();
                    $('.payment-methods-list').show();
                });
                
                // Add event handler for the form submission
                $('#add-payment-method-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    const submitButton = $(this).find('button[type="submit"]');
                    const originalButtonText = submitButton.text();
                    submitButton.prop('disabled', true).text('Processing...');
                    
                    // Create payment method via AJAX
                    $.ajax({
                        url: wc_add_to_cart_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'bocs_add_payment_method',
                            subscription_id: subscriptionId,
                            card_number: $('#card_number').val(),
                            card_expiry: $('#card_expiry').val(),
                            card_cvc: $('#card_cvc').val(),
                            card_name: $('#card_name').val(),
                            nonce: '<?php echo wp_create_nonce("bocs_add_payment_method"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Show success message
                                alert('Payment method added successfully.');
                                $('#payment-method-modal').removeClass('show').hide();
                                
                                // Optionally refresh the page to show the new payment method
                                window.location.reload();
                            } else {
                                // Show error message
                                alert(response.data.message || 'Failed to add payment method. Please try again.');
                                
                                // Reset button state
                                submitButton.prop('disabled', false).text(originalButtonText);
                            }
                        },
                        error: function() {
                            alert('Could not connect to the server. Please try again.');
                            
                            // Reset button state
                            submitButton.prop('disabled', false).text(originalButtonText);
                        }
                    });
                });
            } else {
                // Just show the existing form
                $('.payment-form-container').show();
            }
            
            // Update the action of the modal's update button
            $('.update-payment').hide();
        } else if (selectedMethod) {
            // Show loading state
            $(this).prop('disabled', true).html('<span style="display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 1s linear infinite;margin-right:5px;"></span> Updating...');
            
            // Determine if it's a WooCommerce payment method
            const isWooCommerceMethod = selectedMethod.startsWith('wc_');
            const methodId = isWooCommerceMethod ? selectedMethod.substring(3) : selectedMethod;
            const subscriptionId = $('.edit-payment-method').data('subscription-id');
            
            // AJAX call to update payment method
            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'bocs_update_payment_method',
                    subscription_id: subscriptionId,
                    payment_method: methodId,
                    is_wc_method: isWooCommerceMethod ? 1 : 0,
                    nonce: '<?php echo wp_create_nonce("bocs_update_payment_method"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert('Payment method updated successfully.');
                        $('#payment-method-modal').removeClass('show').hide();
                    } else {
                        // Show error message
                        alert(response.data.message || 'Failed to update payment method. Please try again.');
                    }
                },
                error: function() {
                    alert('Could not connect to the server. Please try again.');
                },
                complete: function() {
                    // Reset button state
                    $('.update-payment').prop('disabled', false).html('<?php esc_html_e('Update', 'bocs-wordpress'); ?>');
                }
            });
        } else {
            alert('Please select a payment method.');
        }
    });

    // Force show the modal with both methods
    $('#payment-method-modal').addClass('show').show();
    
    // Handle changes to the payment method radio buttons
    $(document).on('change', 'input[name="payment_method"]', function() {
        if ($(this).val() === 'new') {
            // Show Stripe form for new payment method
            showStripeForm();
        }
    });
    
    // Function to show Stripe form
    function showStripeForm() {
        // Create form container if it doesn't exist
        if ($('#stripe-form-container').length === 0) {
            $('.payment-methods-list').after(`
                <div id="stripe-form-container" style="padding:20px; border:1px solid #ddd; border-radius:8px; background:white; margin-top:15px;">
                    <h4 style="margin-top:0; font-size:18px; color:#333; margin-bottom:15px;">Add Payment Method</h4>
                    <div id="card-element" style="padding:15px; border:1px solid #ddd; border-radius:4px; background:#f9f9f9; min-height:40px;">
                        <!-- Stripe Elements will be inserted here -->
                    </div>
                    <div id="card-errors" role="alert" style="color:#E53E3E; margin:10px 0; font-size:14px;"></div>
                </div>
            `);
            
            // Initialize Stripe if available
            if (typeof Stripe !== 'undefined') {
                const stripe = Stripe('<?php echo esc_js(get_option("bocs_stripe_publishable_key", "")); ?>');
                const elements = stripe.elements();
                const card = elements.create('card');
                card.mount('#card-element');
                
                // Handle validation errors
                card.addEventListener('change', function(event) {
                    const displayError = document.getElementById('card-errors');
                    if (event.error) {
                        displayError.textContent = event.error.message;
                    } else {
                        displayError.textContent = '';
                    }
                });
            } else {
                $('#card-element').html('<p style="text-align:center;">Stripe payment form is not available</p>');
            }
        } else {
            // Just show the existing form
            $('#stripe-form-container').show();
        }
    }
    
    // Show form immediately if "new" is selected
    if ($('input[name="payment_method"]:checked').val() === 'new') {
        showStripeForm();
    }
});
</script>

<!-- Add this HTML for the modals at the bottom of your file -->
<div id="early-renewal-modal" class="bocs-modal" style="display: none;">
    <div class="bocs-modal-content">
        <h3><?php esc_html_e('Confirm Early Renewal', 'bocs-wordpress'); ?></h3>
        <p><?php esc_html_e('Are you sure you want to process an early renewal for this subscription? This will generate a new order immediately.', 'bocs-wordpress'); ?></p>
        <div class="bocs-modal-actions">
            <button class="button button-secondary modal-cancel"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
            <button class="button button-primary modal-confirm"><?php esc_html_e('Confirm Renewal', 'bocs-wordpress'); ?></button>
        </div>
    </div>
</div>

<div id="cancel-subscription-modal" class="bocs-modal" style="display: none;">
    <div class="bocs-modal-content">
        <h3><?php esc_html_e('Cancel Subscription', 'bocs-wordpress'); ?></h3>
        <p><?php esc_html_e('Are you sure you want to cancel this subscription? This action cannot be undone.', 'bocs-wordpress'); ?></p>
        <div class="bocs-modal-actions">
            <button class="button button-secondary modal-cancel"><?php esc_html_e('Keep Subscription', 'bocs-wordpress'); ?></button>
            <button class="button button-primary modal-confirm"><?php esc_html_e('Yes, Cancel Subscription', 'bocs-wordpress'); ?></button>
        </div>
    </div>
</div>

<!-- Add this modal HTML alongside the other modals -->
<div id="activate-subscription-modal" class="bocs-modal" style="display: none;">
    <div class="bocs-modal-content">
        <h3><?php esc_html_e('Activate Subscription', 'bocs-wordpress'); ?></h3>
        <p><?php esc_html_e('Are you sure you want to activate this subscription? This will resume your regular billing cycle.', 'bocs-wordpress'); ?></p>
        <div class="bocs-modal-actions">
            <button class="button button-secondary modal-cancel"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
            <button class="button button-primary modal-confirm"><?php esc_html_e('Yes, Activate Subscription', 'bocs-wordpress'); ?></button>
        </div>
    </div>
</div>

<!-- Add this modal HTML alongside the other modals -->
<div id="update-box-modal" class="bocs-modal" style="display: none;">
    <div class="bocs-modal-content update-box-modal">
        <div class="modal-header">
            <h3><?php esc_html_e('Update Your Box', 'bocs-wordpress'); ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="subscription-info">
                <h4><?php esc_html_e('Subscription Details', 'bocs-wordpress'); ?></h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label"><?php esc_html_e('Next Payment:', 'bocs-wordpress'); ?></span>
                        <span class="value next-payment-date"></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><?php esc_html_e('Frequency:', 'bocs-wordpress'); ?></span>
                        <span class="value frequency-text"></span>
                    </div>
                </div>
            </div>
            
            <div class="products-section">
                <h4><?php esc_html_e('Available Products', 'bocs-wordpress'); ?></h4>
                <div class="products-grid">
                    <div class="products-loading">
                        <div class="loading-spinner"></div>
                        <p><?php esc_html_e('Loading products...', 'bocs-wordpress'); ?></p>
                    </div>
                    <div class="products-list"></div>
                </div>
            </div>

            <div class="box-summary">
                <h4><?php esc_html_e('Box Summary', 'bocs-wordpress'); ?></h4>
                <div class="summary-items"></div>
                <div class="summary-totals">
                    <div class="total-row">
                        <span><?php esc_html_e('Subtotal:', 'bocs-wordpress'); ?></span>
                        <span class="subtotal-amount"></span>
                    </div>
                    <div class="total-row">
                        <span><?php esc_html_e('Discount:', 'bocs-wordpress'); ?></span>
                        <span class="discount-amount"></span>
                    </div>
                    <div class="total-row total">
                        <span><?php esc_html_e('Total:', 'bocs-wordpress'); ?></span>
                        <span class="total-amount"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="button button-secondary modal-cancel"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
            <button class="button button-primary save-box-changes">
                <span class="button-text"><?php esc_html_e('Save Changes', 'bocs-wordpress'); ?></span>
                <span class="loading-spinner" style="display: none;"></span>
            </button>
        </div>
    </div>
</div>