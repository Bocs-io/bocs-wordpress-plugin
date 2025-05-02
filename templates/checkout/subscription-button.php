<?php
/**
 * Subscription Button Template
 *
 * This template displays the "Create my subscription" button with improved loading state
 *
 * @package BOCS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Default button text
$button_text = apply_filters('bocs_subscription_button_text', __('Create my subscription', 'bocs-wordpress'));

// Button classes
$button_classes = apply_filters('bocs_subscription_button_classes', 'create-subscription-btn w-full rounded-md border border-transparent bg-teal-600 px-4 py-3 text-base font-medium text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 focus:ring-offset-gray-50');

// Button attributes
$button_attrs = apply_filters('bocs_subscription_button_attributes', 'type="submit" name="bocs_create_subscription"');

// Accessibility attributes
$accessibility_attrs = 'aria-busy="false" aria-live="polite"';
?>

<button class="<?php echo esc_attr($button_classes); ?>" <?php echo $button_attrs; ?> <?php echo $accessibility_attrs; ?> data-initialized="false">
    <span class="button-text"><?php echo esc_html($button_text); ?></span>
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="bocs-spinner">
        <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
    </svg>
</button>
