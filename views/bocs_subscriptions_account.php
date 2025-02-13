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
                    <span class="subscription-next-payment">
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
                            <?php if (strtolower($subscription['subscriptionStatus']) === 'active'): ?>
                                <button 
                                    class="woocommerce-button button bocs-button subscription_renewal_early" 
                                    id="renewal_<?php echo esc_attr($subscription['id']); ?>"
                                    data-subscription-id="<?php echo esc_attr($subscription['id']); ?>"
                                >
                                    <?php esc_html_e('Early Renewal', 'bocs-wordpress'); ?>
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
                                <span class="subscription-label"><?php esc_html_e('Billing Address', 'bocs-wordpress'); ?></span>
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
                                    <button class="edit-address-link" data-type="billing">
                                        <?php esc_html_e('Edit Billing Address', 'bocs-wordpress'); ?>
                                    </button>
                                <?php else: ?>
                                    <span><?php esc_html_e('No address provided', 'bocs-wordpress'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="subscription-row">
                            <div class="shipping-address">
                                <span class="subscription-label"><?php esc_html_e('Shipping Address', 'bocs-wordpress'); ?></span>
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
                                    <button class="edit-address-link" data-type="shipping">
                                        <?php esc_html_e('Edit Shipping Address', 'bocs-wordpress'); ?>
                                    </button>
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
                            <button class="woocommerce-button button update-box" id="update_<?php echo esc_attr($subscription['id']); ?>">
                                <?php esc_html_e('Update My Box', 'bocs-wordpress'); ?>
                            </button>
                            <button class="woocommerce-button button alt view-details" 
                                data-subscription-id="<?php echo esc_attr($subscription['id']); ?>">
                                <?php esc_html_e('Edit Details', 'bocs-wordpress'); ?>
                            </button>
                            <button class="woocommerce-button button edit-payment-method" 
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

        <div class="subscription-totals"></div>

        <div class="subscription-details-sections">
            <div class="details-section">
                <div class="section-header">
                    <h3><?php esc_html_e('Frequency', 'bocs-wordpress'); ?></h3>
                    <button class="edit-link"><?php esc_html_e('Edit', 'bocs-wordpress'); ?></button>
                </div>
                <p class="current-frequency">
                    <?php 
                    if ($discount_percent > 0) {
                        echo esc_html($frequency_text . ' (' . $discount_percent . '% off)');
                    } else {
                        echo esc_html($frequency_text);
                    }
                    ?>
                </p>

                <div class="frequency-editor" style="display: none;">
                    <div class="frequency-header">
                        <h3><?php esc_html_e('Frequency', 'bocs-wordpress'); ?></h3>
                        <button class="cancel-edit"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                    </div>
                    
                    <div class="frequency-options">
                        <div class="frequency-options-loading" style="display: none;">
                            <div class="loading-spinner"></div>
                            <p>Loading frequency options...</p>
                        </div>
                        <div class="frequency-options-content"></div>
                    </div>

                    <button class="save-frequency">
                        <?php esc_html_e('Save', 'bocs-wordpress'); ?>
                    </button>
                </div>
            </div>

            <div class="details-section">
                <div class="section-header">
                    <h3><?php esc_html_e('Instructions', 'bocs-wordpress'); ?></h3>
                    <button class="edit-link"><?php esc_html_e('Edit', 'bocs-wordpress'); ?></button>
                </div>
                <p></p>
            </div>

            <div class="details-section">
                <div class="section-header">
                    <h3><?php esc_html_e('Pause or Skip', 'bocs-wordpress'); ?></h3>
                    <button class="edit-link"><?php esc_html_e('Edit', 'bocs-wordpress'); ?></button>
                </div>
                <div class="notice-box">
                    <?php esc_html_e('You can pause or skip your subscription easily, select the next date you would like your payment debited, plus your desired delivery date.', 'bocs-wordpress'); ?>
                </div>
                <div class="date-info">
                    <p class="date-label"><?php esc_html_e('Next Payment Date', 'bocs-wordpress'); ?></p>
                    <p class="date-value"></p>
                </div>

                <!-- Add new schedule editor -->
                <div class="schedule-editor" style="display: none;">
                    <div class="schedule-header">
                        <h3><?php esc_html_e('Adjust Schedule', 'bocs-wordpress'); ?></h3>
                        <button class="cancel-edit"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                    </div>
                    
                    <div class="schedule-options">
                        <div class="schedule-option">
                            <div class="pause-duration">
                                <div class="pause-options-loading" style="display: none;">
                                    <div class="loading-spinner"></div>
                                    <p>Loading pause options...</p>
                                </div>
                                <div class="pause-options-content"></div>
                                <label class="frequency-option">
                                    <input type="radio" name="pause_duration" value="custom_date">
                                    <span class="radio-label">Skip to Date</span>
                                </label>
                                <input type="date" name="new_date"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="">
                            </div>
                        </div>

                        <!-- Add next payment date preview -->
                        <div class="next-payment-preview" style="display: none;">
                            <div class="preview-header">
                                <?php esc_html_e('Preview of Changes', 'bocs-wordpress'); ?>
                            </div>
                            <div class="preview-content">
                                <span class="preview-label"><?php esc_html_e('Next Payment Date:', 'bocs-wordpress'); ?></span>
                                <span class="preview-date"></span>
                            </div>
                        </div>

                        <button class="save-schedule woocommerce-button button alt">
                            <?php esc_html_e('Save Changes', 'bocs-wordpress'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="details-section">
                <div class="section-header">
                    <h3><?php esc_html_e('Promos', 'bocs-wordpress'); ?></h3>
                </div>
                <?php if (!empty($subscription['promos'])): ?>
                    <div class="promo-tag">
                        <?php echo esc_html($subscription['promos'][0] ?? 'FREE PRODUCT FOR LIFE'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="subscription-actions">
                <button class="back-to-subscription-button">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Back to Subscription', 'bocs-wordpress'); ?>
                </button>
                <button class="cancel-button">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Cancel Subscription', 'bocs-wordpress'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS Styles -->
<style>
/**
 * Base Layout Styles
 * Define core layout and spacing for subscription components
 */
.woocommerce-subscriptions-wrapper {
    margin-bottom: 2em;
}

/**
 * Button Component Styles
 * Defines consistent button styling across the subscription interface
 */
.woocommerce-button.button,
.edit-link,
.cancel-edit,
.save-frequency {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.9em 1.8em;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: 2px solid transparent;
    min-height: 44px;
    font-size: 15px;
    line-height: 1.4;
    letter-spacing: 0.3px;
    position: relative;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 100%;
}

/**
 * Primary Buttons
 * Styles for primary action buttons
 */
.woocommerce-button.button.alt,
.save-frequency,
.subscription-renewal_early {
    background: linear-gradient(145deg, var(--wc-primary, #7f54b3), var(--wc-primary-dark, #654497));
    color: #ffffff;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1), 
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.woocommerce-button.button.alt:hover,
.save-frequency:hover,
.subscription-renewal_early:hover {
    background: linear-gradient(145deg, var(--wc-primary-dark, #654497), var(--wc-primary, #7f54b3));
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15), 
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

/**
 * Secondary Buttons
 * Styles for secondary action buttons
 */
.woocommerce-button.button,
.edit-link,
.cancel-edit {
    background: linear-gradient(145deg, #ffffff, #f8f8f8);
    color: #2c3338;
    border: 1px solid #dcdcde;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.woocommerce-button.button:hover,
.edit-link:hover,
.cancel-edit:hover {
    background: linear-gradient(145deg, #f8f8f8, #f2f2f2);
    color: #1d2327;
    border-color: #c3c4c7;
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.08);
}

/**
 * Outline Buttons
 * Styles for outline buttons
 */
.woocommerce-button.button.update-box,
.button.view-details {
    background: transparent;
    color: var(--wc-primary, #7f54b3);
    border: 2px solid currentColor;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.woocommerce-button.button.update-box:hover,
.button.view-details:hover {
    background: rgba(127, 84, 179, 0.05);
    color: var(--wc-primary-dark, #654497);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
}

/**
 * Danger Buttons
 * Styles for danger buttons
 */
.cancel-button {
    background: transparent;
    color: #dc3545;
    border: 2px solid currentColor;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.cancel-button:hover {
    background: rgba(220, 53, 69, 0.05);
    color: #bd2130;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.15);
}

/**
 * Button Loading States
 * Styles for loading states in buttons
 */
.button-loading {
    position: relative;
    color: transparent !important;
    pointer-events: none;
}

.button-loading .loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    margin-right: 8px;
    vertical-align: middle;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.save-frequency.button-loading {
    color: #ffffff !important;
    background-color: #7f54b3;
    opacity: 0.8;
}

.save-frequency.button-loading .loading-spinner {
    border-color: #ffffff;
    border-top-color: transparent;
}

/**
 * Link Styles
 * Styles for clickable links
 */
.back-to-list,
.frequency-link {
    color: var(--wc-primary, #7f54b3);
    text-decoration: none;
    font-weight: 500;
    position: relative;
    padding: 0.2em 0;
    transition: color 0.3s ease;
}

.back-to-list::before,
.frequency-link::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: currentColor;
    transform: scaleX(0);
    transform-origin: right;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.back-to-list:hover::before,
.frequency-link:hover::before {
    transform: scaleX(1);
    transform-origin: left;
}

/**
 * Focus & Active States
 * Styles for focus and active states
 */
.woocommerce-button.button:focus,
.edit-link:focus,
.cancel-edit:focus,
.save-frequency:focus,
.back-to-list:focus {
    outline: none;
    box-shadow: 0 0 0 2px #fff, 
                0 0 0 4px var(--wc-primary, #7f54b3);
}

.woocommerce-button.button:active,
.edit-link:active,
.cancel-edit:active,
.save-frequency:active {
    transform: translateY(1px);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/**
 * Button Groups
 * Styles for button groups
 */
.subscription-actions {
    display: flex;
    gap: 1.2em;
    margin-top: 2em;
    flex-wrap: wrap;
    align-items: center;
}

/**
 * Responsive Design Adjustments
 * Modifies layout and components for smaller screens
 */
@media screen and (max-width: 768px) {
    .subscription-actions {
        flex-direction: column;
        gap: 1em;
    }
    
    .woocommerce-button.button,
    .edit-link,
    .cancel-edit,
    .save-frequency {
        width: 100%;
        justify-content: center;
        padding: 1em 1.8em;
    }
    
    /* Touch-friendly adjustments */
    .back-to-list,
    .frequency-link {
        padding: 0.5em 0;
        margin: 0.5em 0;
    }
}

/**
 * High Contrast & Reduced Motion
 * Styles for high contrast and reduced motion environments
 */
@media (prefers-reduced-motion: reduce) {
    * {
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}

@media (prefers-contrast: high) {
    .woocommerce-button.button,
    .edit-link,
    .cancel-edit,
    .save-frequency {
        border-width: 2px;
    }
}

/**
 * Subscription Card Styles
 * Styles for individual subscription entries in the accordion
 */
.wc-subscription:nth-child(odd) .accordion-header {
    background-color: var(--wc-secondary-light);
}

.wc-subscription:nth-child(even) .accordion-header {
    background-color: #f7f7f7;
}

/**
 * Hover state for both odd and even headers
 */
.wc-subscription .accordion-header:hover {
    background-color: #eaeaea;
}

/**
 * Active state from jQuery UI - this will override the alternating colors when active
 */
.ui-state-active,
.ui-state-active:hover {
    background-color: var(--wc-primary) !important;
    color: #fff;
    border-color: var(--wc-primary);
}

.accordion-header {
    display: flex;
    align-items: center;
    gap: 1.5em;
    padding: 1em 1.5em;
    margin: 0;
    border: 1px solid var(--wc-secondary);
    cursor: pointer;
    background: #fff;
    transition: background-color 0.2s ease;
}

.accordion-header:hover {
    background-color: #f8f8f8;
}

.accordion-header span {
    white-space: nowrap;
    color: #333;
}

.subscription-title {
    flex: 1;
    white-space: normal;
    font-weight: 600;
    min-width: 200px;
}

.subscription-amount {
    font-weight: 600;
    color: var(--wc-primary, #7f54b3);
}

.subscription-frequency,
.subscription-next-payment {
    color: #666;
    font-size: 0.9em;
}

/* Responsive adjustments */
@media screen and (max-width: 1024px) {
    .accordion-header {
        flex-wrap: wrap;
        gap: 0.5em;
    }

    .subscription-title {
        flex: 100%;
        margin-bottom: 0.5em;
    }

    .subscription-amount,
    .subscription-frequency,
    .subscription-next-payment {
        flex: 1;
        min-width: 150px;
    }
}

@media screen and (max-width: 768px) {
    .accordion-header {
        padding: 1em;
    }

    .subscription-amount,
    .subscription-frequency,
    .subscription-next-payment {
        font-size: 0.85em;
    }
}

/**
 * Add margin between subscription cards
 */
.wc-subscription {
    margin-bottom: 0.5em;
    border-radius: 4px;
    overflow: hidden;
}

/**
 * Hover effect for better interactivity
 */
.wc-subscription .accordion-header:hover {
    background-color: #eaeaea;
    transition: background-color 0.2s ease;
}

.subscription-title {
    font-weight: 600;
    flex: 1;
    padding: 0.5em 0;
    font-size: 1.1em;
    line-height: 1.4;
    margin: 0;
    word-break: break-word; /* Ensures long text wraps properly */
}

.subscription-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 0.9em;
    margin-left: 1em;
}

.status-active {
    background-color: #c6e1c6;
    color: #5b841b;
}

.accordion-content {
    padding: 1.5em;
    border: 1px solid var(--wc-secondary);
    border-top: none;
    position: relative; /* Ensure sticky positioning works correctly */
}

/**
 * Add a sticky header inside the accordion content
 */
.subscription-header-sticky {
    position: sticky;
    top: 0;
    background: #fff;
    padding: 1em;
    border-bottom: 1px solid var(--wc-secondary);
    margin: -1.5em -1.5em 1.5em;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 1;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    min-height: 3.5em; /* Match accordion header height */
}

.subscription-header-sticky .subscription-title {
    padding: 0.5em;
    font-size: 1.1em;
    line-height: 1.4;
    margin: 0;
    word-break: break-word;
    flex: 1;
    font-weight: 600;
}

.subscription-header-sticky .subscription-status {
    white-space: nowrap; /* Prevent status from wrapping */
    margin-left: 1em;
}

.subscription-section {
    position: relative;
}

.subscription-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.5em;
    gap: 1em;
}

.subscription-label {
    display: block;
    color: var(--wc-secondary);
    margin-bottom: 0.5em;
}

.subscription-actions {
    display: flex;
    gap: 1em;
    margin-top: 2em;
}

@media screen and (max-width: 768px) {
    .subscription-row,
    .subscription-actions {
        flex-direction: column;
    }
    
    .subscription-actions .button {
        width: 100%;
        text-align: center;
    }
}

.early-renewal {
    background-color: var(--wc-secondary);
    color: #fff;
    padding: 0.5em 1em;
    border-radius: 3px;
    transition: background-color 0.2s ease;
}

.early-renewal:hover {
    background-color: var(--wc-secondary-dark);
}

#subscription-details-view {
    background: #fff;
    padding: 2em;
    margin-top: 1em;
}

.subscription-details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2em;
    padding-bottom: 1em;
    border-bottom: 1px solid #eee;
}

.subscription-navigation {
    display: flex;
    align-items: center;
    gap: 1em;
    color: #515151;
}

.back-to-list {
    text-decoration: none;
    color: var(--wc-primary);
    font-weight: 500;
}

.back-to-list:hover {
    text-decoration: underline;
}

.nav-separator {
    color: #767676;
}

.nav-item {
    font-size: 1.1em;
}

.header-actions {
    display: flex;
    gap: 1em;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2em;
}

.content-header h2 {
    margin: 0;
}

.edit-box {
    background-color: var(--wc-green, #7f9c3d);
    color: white;
}

.edit-box:hover {
    background-color: var(--wc-green-dark, #6b8534);
    color: white;
}

.pay-now {
    background-color: var(--wc-green, #7f9c3d);
    color: white;
}

.pay-now:hover {
    background-color: var(--wc-green-dark, #6b8534);
    color: white;
}

.box-items {
    margin-bottom: 2em;
}

.box-item {
    display: flex;
    align-items: center;
    padding: 1em 0;
    border-bottom: 1px solid #eee;
}

.item-image {
    width: 80px;
    margin-right: 1em;
}

.item-image img {
    max-width: 100%;
    height: auto;
}

.item-details {
    flex: 1;
}

.item-details h4 {
    margin: 0 0 0.5em;
}

.subscription-totals {
    margin-top: 2em;
    border-top: 2px solid #eee;
    padding-top: 1em;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5em 0;
}

.total-row.total {
    border-top: 2px solid #eee;
    font-weight: 600;
    font-size: 1.1em;
}

.subscription-details-sections {
    margin-top: 3em;
}

.details-section {
    margin-bottom: 2em;
    padding: 1.5em;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1em;
}

.section-header h3 {
    margin: 0;
    font-size: 1.2em;
    color: #333;
}

.edit-link {
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
}

.notice-box {
    background: #f7f6f2;
    padding: 1em;
    margin-bottom: 1em;
    border-radius: 4px;
}

.date-info {
    margin-bottom: 1em;
}

.date-label {
    color: #666;
    margin-bottom: 0.3em;
}

.date-value {
    font-weight: 600;
    margin: 0;
}

.promo-tag {
    display: inline-block;
    background: var(--wc-green, #7f9c3d);
    color: white;
    padding: 0.5em 1em;
    border-radius: 20px;
    font-size: 0.9em;
}

.back-to-subscription-button {
    display: flex;
    align-items: center;
    gap: 0.5em;
    background: #f7f7f7;
    color: #515151;
    border: 1px solid #ddd;
    padding: 0.7em 1.5em;
    border-radius: 4px;
    cursor: pointer;
}

.back-to-subscription-button:hover {
    background: #eee;
}

.cancel-button {
    display: flex;
    align-items: center;
    gap: 0.5em;
    background: #dc3232;
    color: white;
    border: none;
    padding: 0.7em 1.5em;
    border-radius: 4px;
    cursor: pointer;
    margin-left: auto; /* This pushes the cancel button to the right */
}

.dashicons {
    font-size: 1.2em;
}

.frequency-editor {
    background: #fff;
    padding: 1.5em;
    border-radius: 4px;
    margin-top: 1em;
}

.frequency-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5em;
}

.frequency-header h3 {
    margin: 0;
    font-size: 1.2em;
}

.cancel-edit {
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    font-size: 1em;
}

.frequency-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 15px 0;
}

.frequency-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.edit-link, .cancel-edit {
    cursor: pointer;
}

.item-price {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.original-price {
    text-decoration: line-through;
    color: #999;
    font-size: 0.9em;
}

.price-discount {
    color: #e2401c;
    font-size: 0.9em;
}

.final-price {
    font-weight: bold;
    font-size: 1.1em;
}

.bocs-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 4px;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 1000;
    max-width: 300px;
}

.bocs-notification.loading {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
}

.bocs-notification.success {
    background: #d4edda;
    border-left: 4px solid #28a745;
}

.bocs-notification.error {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
}

.loading-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #007bff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.box-items-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2em;
    text-align: center;
    background: #f8f9fa;
    border-radius: 4px;
    margin: 1em 0;
}

.box-items-loading .loading-spinner {
    width: 30px;
    height: 30px;
    border: 3px solid #007bff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
    margin-bottom: 1em;
}

.box-items-loading p {
    color: #666;
    margin: 0;
    font-size: 0.9em;
}

.pause-duration {
    margin: 0.8em 0 0 1.8em;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pause-duration .frequency-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.pause-duration input[type="radio"]:disabled + .radio-label {
    color: #999;
    cursor: not-allowed;
}

/* Schedule Editor Styles */
.schedule-editor {
    background: #fff;
    padding: 1.5em;
    border-radius: 4px;
    margin-top: 1em;
    border: 1px solid #eee;
}

.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5em;
    padding-bottom: 1em;
    border-bottom: 1px solid #eee;
}

.schedule-header h3 {
    margin: 0;
    font-size: 1.2em;
    color: #333;
}

.schedule-options {
    display: flex;
    flex-direction: column;
    gap: 1.5em;
    margin: 1.5em 0;
}

.schedule-option {
    padding: 1em;
    background: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #eee;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 0.5em;
    margin-bottom: 0.5em;
    cursor: pointer;
}

.option-description {
    margin: 0.5em 0 0 1.8em;
    color: #666;
    font-size: 0.9em;
}

.pause-duration,
.date-selector {
    margin: 0.8em 0 0 1.8em;
}

.pause-duration select,
.date-selector input {
    width: 100%;
    max-width: 200px;
    padding: 0.5em;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
}

.pause-duration select:disabled,
.date-selector input:disabled {
    background: #f5f5f5;
    cursor: not-allowed;
}

.save-schedule {
    width: 100%;
    margin-top: 1em;
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .schedule-editor {
        padding: 1em;
    }

    .pause-duration select,
    .date-selector input {
        max-width: 100%;
    }
}

.next-payment-preview {
    margin: 1.5em 0;
    padding: 1em;
    background: #f8f9fa;
    border: 1px solid #eee;
    border-radius: 4px;
}

.preview-header {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5em;
}

.preview-content {
    display: flex;
    gap: 0.5em;
    align-items: center;
}

.preview-label {
    color: #666;
}

.preview-date {
    font-weight: 500;
    color: var(--wc-primary, #7f54b3);
}

.edit-address-link {
    display: inline-block;
    margin-top: 0.5em;
    padding: 0.3em 0.8em;
    font-size: 0.9em;
    color: var(--wc-primary, #7f54b3);
    background: transparent;
    border: 1px solid currentColor;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.edit-address-link:hover {
    background: var(--wc-primary, #7f54b3);
    color: #fff;
}

.address-editor {
    display: none;
    margin-top: 1em;
    padding: 1em;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.address-editor form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1em;
}

.address-editor .form-row {
    margin-bottom: 1em;
}

.address-editor .form-row.full-width {
    grid-column: 1 / -1;
}

.address-editor label {
    display: block;
    margin-bottom: 0.5em;
    font-weight: 500;
}

.address-editor input,
.address-editor select {
    width: 100%;
    padding: 0.5em;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.address-editor .button-group {
    grid-column: 1 / -1;
    display: flex;
    gap: 1em;
    justify-content: flex-end;
    margin-top: 1em;
}

@media screen and (max-width: 768px) {
    .address-editor form {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JavaScript Implementation -->
<script>
/**
 * BOCS Subscription Management JavaScript
 * Handles all interactive functionality for subscription management
 */
jQuery(document).ready(function($) {
    // Track currently active subscription for state management
    let activeSubscriptionId = null;

    /**
     * Initialize jQuery UI Accordion
     * Creates collapsible sections for subscription details
     */
    $('#bocs-subscriptions-accordion').accordion({
        collapsible: true,
        active: false,
        heightStyle: "content",
        header: "> div > h3"
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
        .on('click', '.subscription-actions .view-details, button.view-details', eventHandlers.viewDetails)
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
                    // Redirect to checkout if payment URL is provided
                    if (response.data.paymentUrl) {
                        window.location.href = response.data.paymentUrl;
                    } else {
                        helpers.showNotification('Early renewal processed successfully', 'success');
                        // Optionally reload the page after a short delay
                        setTimeout(() => window.location.reload(), 2000);
                    }
                } else {
                    throw new Error(response.message || 'Failed to process early renewal');
                }
            } catch (error) {
                console.error('Error processing early renewal:', error);
                helpers.showNotification('Failed to process early renewal. Please try again.', 'error');
            } finally {
                // Reset button state
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
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

        let nextPaymentDate = new Date('<?php echo $next_payment_date->format('Y-m-d'); ?>');

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

    // Add to the existing event bindings
    $(document)
        .on('click', '.edit-payment-method', async function(e) {
            e.preventDefault();
            const button = $(this);
            const subscriptionId = button.data('subscription-id');
            const originalButtonText = button.html();

            if (!subscriptionId) {
                console.error('No subscription ID found');
                helpers.showNotification('Unable to update payment method - missing subscription ID', 'error');
                return;
            }

            try {
                // Show loading state
                button.prop('disabled', true)
                      .addClass('button-loading')
                      .html('<span class="loading-spinner"></span> Processing...');
                
                helpers.showNotification('Preparing payment method update...', 'loading');

                // First, verify the subscription exists in BOCS
                const bocsResponse = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}`,
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });

                if (!bocsResponse.data) {
                    throw new Error('Subscription not found in BOCS');
                }

                // Then initiate the payment update process
                const response = await $.ajax({
                    url: wc_add_to_cart_params.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'get_payment_update_session',
                        subscription_id: subscriptionId,
                        bocs_subscription: JSON.stringify(bocsResponse.data),
                        security: '<?php echo wp_create_nonce("bocs_update_payment_method"); ?>'
                    },
                    dataType: 'json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                    }
                });

                if (response.success && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    throw new Error(response.data?.message || 'Failed to initialize payment update');
                }

            } catch (error) {
                console.error('Error updating payment method:', error);
                
                // Enhanced error reporting
                const errorMessage = error.responseJSON?.data?.message 
                    || error.responseText 
                    || error.message 
                    || 'Unknown error occurred';
                    
                helpers.showNotification(
                    `Payment update failed: ${errorMessage}. Please try again or contact support.`,
                    'error'
                );

                // Log detailed error information if available
                if (error.responseJSON) {
                    console.log('Detailed error:', error.responseJSON);
                }
            } finally {
                // Reset button state
                button.prop('disabled', false)
                      .removeClass('button-loading')
                      .html(originalButtonText);
                
                // Hide loading notification
                helpers.hideNotification();
            }
        });
});
</script>