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
?>

<div id="bocs-subscriptions-accordion" class="woocommerce-subscriptions-wrapper">
    <?php
    if (!empty($subscriptions['data']['data']) && is_array($subscriptions['data']['data'])) {
        foreach ($subscriptions['data']['data'] as $index => $subscription) {
            // Improved subscription name logic
            $subscription_name = '';
            if (!empty(trim($subscription['bocs']['name']))) {
                $subscription_name = $subscription['bocs']['name'];
            } elseif (!empty($subscription['externalSourceParentOrderId'])) {
                $subscription_name = sprintf(
                    esc_html__('Subscription (Order #%s)', 'woocommerce'),
                    $subscription['externalSourceParentOrderId']
                );
            } else {
                $subscription_name = sprintf(
                    esc_html__('Subscription #%s', 'woocommerce'),
                    substr($subscription['id'], 0, 8)
                );
            }
            
            $subscription_url = wc_get_endpoint_url('bocs-view-subscription', $subscription['id'], wc_get_page_permalink('myaccount'));
            $next_payment_date = new DateTime($subscription['nextPaymentDateGmt']);
            $start_date = new DateTime($subscription['startDateGmt']);
            
            $row_class = ($index % 2 == 0) ? 'even' : 'odd';
    ?>
            <div class="wc-subscription <?php echo esc_attr($row_class); ?>">
                <h3 class="accordion-header">
                    <span class="subscription-title"><?php echo esc_html($subscription_name); ?></span>
                </h3>
                <div class="accordion-content">
                    <div class="subscription-header-sticky">
                        <span class="subscription-title"><?php echo esc_html($subscription_name); ?></span>
                        <span class="subscription-status status-<?php echo esc_attr($subscription['status']); ?>">
                            <?php echo ucfirst($subscription['subscriptionStatus']); ?>
                        </span>
                    </div>
                    <div class="subscription-section">
                        <div class="subscription-row">
                            <div class="total-amount">
                                <span class="subscription-label"><?php esc_html_e('Total Amount', 'woocommerce'); ?></span>
                                <span class="woocommerce-Price-amount amount">
                                    <?php if (!empty($subscription['currency'])): ?>
                                        <span class="woocommerce-Price-currencySymbol"><?php echo get_woocommerce_currency_symbol($subscription['currency']); ?></span>
                                    <?php endif; ?>
                                    <?php echo number_format($subscription['total'], 2); ?>
                                </span>
                                <?php if ($subscription['discountTotal'] > 0): ?>
                                    <span class="subscription-discount">
                                        (<?php echo sprintf(esc_html__('Includes %s discount', 'woocommerce'), 
                                            wc_price($subscription['discountTotal'])); ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($subscription['status'] === 'active'): ?>
                                <button 
                                    class="woocommerce-button button bocs-button subscription_renewal_early" 
                                    id="renewal_<?php echo esc_attr($subscription['id']); ?>"
                                    data-subscription-id="<?php echo esc_attr($subscription['id']); ?>"
                                >
                                    <?php esc_html_e('Early Renewal', 'woocommerce'); ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="subscription-row">
                            <div class="delivery-frequency">
                                <span class="subscription-label"><?php esc_html_e('Delivery', 'woocommerce'); ?></span>
                                <span><?php
                                    $frequency = isset($subscription['frequency']['frequency']) ? $subscription['frequency']['frequency'] : 1;
                                    $period = isset($subscription['frequency']['timeUnit']) ? $subscription['frequency']['timeUnit'] : '';
                                    if ($frequency > 1) {
                                        $period = rtrim($period, 's') . 's';
                                    } else {
                                        $period = rtrim($period, 's');
                                    }
                                    echo esc_html(sprintf(__('Every %d %s', 'woocommerce'), $frequency, $period));
                                ?></span>
                            </div>
                            <div class="total-items">
                                <span class="subscription-label"><?php esc_html_e('Total Items', 'woocommerce'); ?></span>
                                <span><?php 
                                    $items_count = isset($subscription['lineItems']) && is_array($subscription['lineItems']) 
                                        ? array_sum(array_column($subscription['lineItems'], 'quantity')) 
                                        : 0;
                                    echo $items_count . ' ' . esc_html(_n('item', 'items', $items_count, 'woocommerce')); 
                                ?></span>
                            </div>
                        </div>

                        <div class="subscription-row">
                            <div class="shipping-address">
                                <span class="subscription-label"><?php esc_html_e('Shipping Address', 'woocommerce'); ?></span>
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
                                    <span><?php esc_html_e('No address provided', 'woocommerce'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="subscription-row">
                            <div class="next-payment">
                                <span class="subscription-label"><?php esc_html_e('Next Payment', 'woocommerce'); ?></span>
                                <time datetime="<?php echo esc_attr($next_payment_date->format('c')); ?>">
                                    <?php echo esc_html($next_payment_date->format('l, j F Y')); ?>
                                </time>
                            </div>
                            <div class="start-date">
                                <span class="subscription-label"><?php esc_html_e('Started On', 'woocommerce'); ?></span>
                                <time datetime="<?php echo esc_attr($start_date->format('c')); ?>">
                                    <?php echo esc_html($start_date->format('l, j F Y')); ?>
                                </time>
                            </div>
                        </div>

                        <div class="subscription-actions">
                            <a href="#" class="woocommerce-button button update-box"><?php esc_html_e('Update My Box', 'woocommerce'); ?></a>
                            <button class="woocommerce-button button alt view-details" 
                                data-subscription-id="<?php echo esc_attr($subscription['id']); ?>">
                                <?php esc_html_e('Edit Details', 'woocommerce'); ?>
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
            <a href="#" class="back-to-subscription"><?php esc_html_e('Back to subscription', 'woocommerce'); ?></a>
            <span class="nav-separator">|</span>
            <span class="nav-item">Dashboard</span>
            <span class="nav-separator">→</span>
            <span class="nav-item subscription-name"></span>
        </div>
        <div class="header-actions">
            <button class="woocommerce-button button alt pay-now">
                <?php esc_html_e('Early Renewal', 'woocommerce'); ?>
            </button>
        </div>
    </div>
    
    <div class="subscription-details-content">
        <div class="content-header">
            <h2><?php esc_html_e('Box Content', 'woocommerce'); ?></h2>
            <button class="woocommerce-button button edit-box">
                <?php esc_html_e('Edit the Box', 'woocommerce'); ?>
            </button>
        </div>
        <div class="box-items">
            <?php foreach ($subscription['lineItems'] as $item): ?>
                <div class="box-item" 
                    data-product-id="<?php echo esc_attr($item['externalSourceId']); ?>"
                    data-price="<?php echo esc_attr($item['price']); ?>"
                    data-bocs-product-id="<?php echo esc_attr($item['productId']); ?>"
                    data-quantity="<?php echo esc_attr($item['quantity']); ?>"
                    data-total="<?php echo esc_attr($item['total']); ?>">
                    <?php 
                    $product = wc_get_product($item['externalSourceId']);
                    $image = $product ? wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'thumbnail') : '';
                    ?>
                    <div class="item-image">
                        <?php if ($image): ?>
                            <img src="<?php echo esc_url($image[0]); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="item-details">
                        <h4><?php echo esc_html($product ? $product->get_name() : 'Product'); ?></h4>
                        <div class="item-quantity">
                            <?php echo esc_html($item['quantity']); ?> × 
                            <?php echo wc_price($item['price']); ?>
                        </div>
                    </div>
                    <div class="item-total">
                        <?php echo wc_price($item['total']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="subscription-totals">
            <div class="total-row">
                <span><?php esc_html_e('Subtotal:', 'woocommerce'); ?></span>
                <span><?php echo wc_price($subscription['total']); ?></span>
            </div>
            <div class="total-row">
                <span><?php esc_html_e('Delivery:', 'woocommerce'); ?></span>
                <span><?php echo wc_price($subscription['shippingTotal']); ?></span>
            </div>
            <div class="total-row total">
                <span><?php esc_html_e('Total:', 'woocommerce'); ?></span>
                <span><?php echo wc_price($subscription['total']); ?></span>
            </div>
        </div>

        <div class="subscription-details-sections">
            <div class="details-section">
                <div class="section-header">
                    <h3><?php esc_html_e('Frequency', 'woocommerce'); ?></h3>
                    <button class="edit-link"><?php esc_html_e('Edit', 'woocommerce'); ?></button>
                </div>
                <?php
                // Get frequencies from BOCS widget
                $bocs_id = $subscription['bocs']['id'] ?? '';
                $frequencies = [];
                
                if ($bocs_id) {
                    $widget_url = BOCS_LIST_WIDGETS_URL . $bocs_id . '/bocs';
                    
                    // Use existing BOCS headers from options
                    $headers = array(
                        'Store' => $options['bocs_headers']['store'],
                        'Organization' => $options['bocs_headers']['organization'],
                        'Authorization' => $options['bocs_headers']['authorization'],
                        'Content-Type' => 'application/json'
                    );
                    
                    $response = wp_remote_get($widget_url, array(
                        'headers' => $headers
                    ));
                    
                    if (!is_wp_error($response)) {
                        $widget_data = json_decode(wp_remote_retrieve_body($response), true);
                        
                        // Get frequencies from priceAdjustment.adjustments
                        if (isset($widget_data['data']['priceAdjustment']['adjustments'])) {
                            $frequencies = $widget_data['data']['priceAdjustment']['adjustments'];
                        }
                    }
                }

                // Current frequency display
                $current_frequency = $subscription['frequency'];
                $frequency_text = sprintf(
                    __('Every %d %s', 'woocommerce'),
                    $current_frequency['frequency'],
                    $current_frequency['frequency'] > 1 ? $current_frequency['timeUnit'] : rtrim($current_frequency['timeUnit'], 's')
                );

                // Calculate discount amount for current frequency
                $discount_percent = 0;
                foreach ($frequencies as $freq) {
                    if ($freq['id'] === $current_frequency['id']) {
                        $discount_percent = $freq['discount'];
                        break;
                    }
                }
                ?>
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
                        <h3><?php esc_html_e('Frequency', 'woocommerce'); ?></h3>
                        <button class="cancel-edit"><?php esc_html_e('Cancel', 'woocommerce'); ?></button>
                    </div>
                    
                    <div class="frequency-options">
                        <?php 
                        if (!empty($frequencies)):
                            foreach ($frequencies as $freq): 
                                $freq_name = sprintf(
                                    __('Every %d %s', 'woocommerce'),
                                    $freq['frequency'],
                                    $freq['frequency'] > 1 ? $freq['timeUnit'] : rtrim($freq['timeUnit'], 's')
                                );
                                if ($freq['discount'] > 0) {
                                    $freq_name .= sprintf(' (%d%% off)', $freq['discount']);
                                }
                        ?>
                            <label class="frequency-option">
                                <input type="radio" name="frequency" 
                                    value="<?php echo esc_attr($freq['id']); ?>" 
                                    data-name="<?php echo esc_attr($freq_name); ?>"
                                    data-discount="<?php echo esc_attr($freq['discount']); ?>"
                                    data-time-unit="<?php echo esc_attr($freq['timeUnit']); ?>"
                                    data-frequency="<?php echo esc_attr($freq['frequency']); ?>"
                                    <?php checked($current_frequency['id'] === $freq['id']); ?>>
                                <span class="radio-label"><?php echo esc_html($freq_name); ?></span>
                            </label>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>

                    <button class="save-frequency">
                        <?php esc_html_e('Save', 'woocommerce'); ?>
                    </button>
                </div>
            </div>

            <div class="details-section">
                <div class="section-header">
                    <h3><?php esc_html_e('Instructions', 'woocommerce'); ?></h3>
                    <button class="edit-link"><?php esc_html_e('Edit', 'woocommerce'); ?></button>
                </div>
                <p><?php echo esc_html($subscription['shipping']['instructions'] ?? ''); ?></p>
            </div>

            <div class="details-section">
                <div class="section-header">
                    <h3><?php esc_html_e('Pause or Skip', 'woocommerce'); ?></h3>
                    <button class="edit-link"><?php esc_html_e('Edit', 'woocommerce'); ?></button>
                </div>
                <div class="notice-box">
                    <?php esc_html_e('You can pause or skip your subscription easily, select the next date you would like your payment debited, plus your desired delivery date.', 'woocommerce'); ?>
                </div>
                <div class="date-info">
                    <p class="date-label"><?php esc_html_e('Next Payment Date', 'woocommerce'); ?></p>
                    <p class="date-value"><?php 
                        $next_payment_date = new DateTime($subscription['nextPaymentDateGmt']);
                        echo esc_html($next_payment_date->format('l j F Y')); 
                    ?></p>
                </div>

                <!-- Add new schedule editor -->
                <div class="schedule-editor" style="display: none;">
                    <div class="schedule-header">
                        <h3><?php esc_html_e('Adjust Schedule', 'woocommerce'); ?></h3>
                        <button class="cancel-edit"><?php esc_html_e('Cancel', 'woocommerce'); ?></button>
                    </div>
                    
                    <div class="schedule-options">
                        <div class="schedule-option">
                            <div class="pause-duration">
                                <?php 
                                if (!empty($frequencies)):
                                    foreach ($frequencies as $freq): 
                                        $pause_text = sprintf(
                                            _n('Pause for %d Month', 'Pause for %d Months', $freq['frequency'], 'woocommerce'),
                                            $freq['frequency']
                                        );
                                ?>
                                    <label class="frequency-option">
                                        <input type="radio" name="pause_duration" 
                                            value="<?php echo esc_attr($freq['id']); ?>" 
                                            data-frequency="<?php echo esc_attr($freq['frequency']); ?>"
                                            data-time-unit="<?php echo esc_attr($freq['timeUnit']); ?>"
                                            >
                                        <span class="radio-label"><?php echo esc_html($pause_text); ?></span>
                                    </label>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                                <label class="frequency-option">
                                    <input type="radio" name="pause_duration" value="custom_date">
                                    <span class="radio-label">Skip to Date</span>
                                </label>
                                <input type="date" name="new_date"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo $next_payment_date->format('Y-m-d'); ?>">
                            </div>
                        </div>

                        <!-- Add next payment date preview -->
                        <div class="next-payment-preview" style="display: none;">
                            <div class="preview-header">
                                <?php esc_html_e('Preview of Changes', 'woocommerce'); ?>
                            </div>
                            <div class="preview-content">
                                <span class="preview-label"><?php esc_html_e('Next Payment Date:', 'woocommerce'); ?></span>
                                <span class="preview-date"></span>
                            </div>
                        </div>

                        <button class="save-schedule woocommerce-button button alt">
                            <?php esc_html_e('Save Changes', 'woocommerce'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="details-section">
                <div class="section-header">
                    <h3><?php esc_html_e('Promos', 'woocommerce'); ?></h3>
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
                    <?php esc_html_e('Back to Subscription', 'woocommerce'); ?>
                </button>
                <button class="cancel-button">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Cancel', 'woocommerce'); ?>
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
    justify-content: space-between;
    align-items: center;
    margin: 0;
    padding: 1em;
    border: 1px solid var(--wc-secondary);
    cursor: pointer;
    min-height: 3.5em; /* Ensures consistent height */
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
            
            if (!subscriptionId) {
                console.error('No subscription ID found');
                return;
            }

            try {
                // Show loading state
                button.prop('disabled', true).addClass('button-loading');
                helpers.showNotification('Fetching subscription details...', 'loading');

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

                const subscriptionData = response.data;
                const detailsView = $('#subscription-details-view');
                const boxItemsContainer = detailsView.find('.box-items');
                
                // Clear existing items and show loading message
                boxItemsContainer.empty().append(`
                    <div class="box-items-loading">
                        <div class="loading-spinner"></div>
                        <p>Loading subscription items...</p>
                    </div>
                `);

                // Update subscription details
                detailsView.find('.subscription-name').text(
                    `Subscription (Order #${subscriptionData.externalSourceParentOrderId})`
                );

                // Process and display line items
                if (subscriptionData.lineItems && Array.isArray(subscriptionData.lineItems)) {
                    // First, collect all product IDs
                    const productIds = subscriptionData.lineItems.map(item => item.externalSourceId);
                    
                    // Fetch product details from WooCommerce
                    $.ajax({
                        url: wc_add_to_cart_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'get_product_details',
                            product_ids: productIds,
                            nonce: '<?php echo wp_create_nonce("get_product_details"); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                const products = response.data;
                                boxItemsContainer.empty()
                                // Now process line items with product details
                                subscriptionData.lineItems.forEach(item => {
                                    const productDetails = products[item.externalSourceId] || {};
                                    const productName = productDetails.name || `Product ID: ${item.externalSourceId}`;
                                    const productSku = productDetails.sku ? ` (SKU: ${productDetails.sku})` : '';
                                    
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
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error fetching product details:', error);
                        }
                    });
                }

                // Update frequency display
                if (subscriptionData.frequency) {
                    const freq = subscriptionData.frequency.frequency;
                    const unit = subscriptionData.frequency.timeUnit;
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
                const calculations = subscriptionData.lineItems.reduce((acc, item) => {
                    const itemSubtotal = parseFloat(item.price) * parseInt(item.quantity);
                    const itemTotal = parseFloat(item.total);
                    return {
                        subtotal: acc.subtotal + itemSubtotal,
                        totalAfterDiscount: acc.totalAfterDiscount + itemTotal
                    };
                }, { subtotal: 0, totalAfterDiscount: 0 });

                const totalDiscount = calculations.subtotal - calculations.totalAfterDiscount;

                // Update totals display
                helpers.updateTotalsDisplay(calculations.subtotal, totalDiscount, subscriptionData.total);

                // Hide subscriptions list and show details
                $('#bocs-subscriptions-accordion').hide();
                detailsView.show();

                // Hide loading message on success
                helpers.hideNotification();

            } catch (error) {
                console.error('Error fetching subscription details:', error);
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
        .on('click', '.details-section:has(.schedule-editor) .edit-link', eventHandlers.editSchedule);

    $('.save-schedule').on('click', async function(e) {
        e.preventDefault();
        const button = $(this);
        const originalButtonText = button.html();
        
        const selectedOption = $('input[name="pause_duration"]:checked');
        if (!selectedOption.length) {
            helpers.showNotification('Please select a pause duration or custom date', 'error');
            return;
        }

        try {
            // Show loading state
            button.prop('disabled', true)
                  .addClass('button-loading')
                  .html('<span class="loading-spinner"></span> Updating schedule...');
            
            helpers.showNotification('Updating subscription schedule...', 'loading');

            // Calculate the next payment date
            let nextPaymentDate = new Date('<?php echo $next_payment_date->format('Y-m-d'); ?>');

            if (selectedOption.val() === 'custom_date') {
                const customDate = $('input[name="new_date"]').val();
                if (!customDate) {
                    throw new Error('Please select a valid date');
                }
                nextPaymentDate = new Date(customDate);
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

            // Prepare the payload
            const updatePayload = {
                nextPaymentDateGmt: nextPaymentDate.toISOString()
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
                // Update the displayed next payment date in the subscription details
                const formattedDate = nextPaymentDate.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                $('.date-value').text(formattedDate);

                // Update the date in the subscription list if visible
                const subscriptionInList = $(`#bocs-subscriptions-accordion .view-details[data-subscription-id="${activeSubscriptionId}"]`)
                    .closest('.wc-subscription');
                if (subscriptionInList.length) {
                    subscriptionInList.find('.next-payment time').text(formattedDate);
                }

                // Hide the schedule editor
                $('.schedule-editor').hide();
                $('.edit-link').show();

                helpers.showNotification('Schedule updated successfully', 'success');
            } else {
                throw new Error(response.message || 'Failed to update schedule');
            }
        } catch (error) {
            console.error('Error updating subscription schedule:', error);
            helpers.showNotification(error.message || 'Failed to update schedule. Please try again.', 'error');
        } finally {
            // Reset button state
            button.prop('disabled', false)
                  .removeClass('button-loading')
                  .html(originalButtonText);
        }
    });

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
});
</script>