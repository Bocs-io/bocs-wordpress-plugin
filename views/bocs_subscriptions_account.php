<?php
// Enqueue jQuery UI accordion
wp_enqueue_script('jquery-ui-accordion');
wp_enqueue_style('wp-jquery-ui-dialog'); // This includes basic jQuery UI styles

// Get BOCS options
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

<!-- Add the detailed view container -->
<div id="subscription-details-view" style="display: none;">
    <div class="subscription-details-header">
        <div class="subscription-navigation">
            <a href="#" class="back-to-list"><?php esc_html_e('Back to list of subscriptions', 'woocommerce'); ?></a>
            <span class="nav-separator">|</span>
            <span class="nav-item">Dashboard</span>
            <span class="nav-separator">→</span>
            <span class="nav-item subscription-name"></span>
        </div>
        <div class="header-actions">
            <button class="woocommerce-button button alt pay-now">
                <?php esc_html_e('Pay Now', 'woocommerce'); ?>
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
                <div class="box-item">
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
                <div class="date-info">
                    <p class="date-label"><?php esc_html_e('Next Delivery Date', 'woocommerce'); ?></p>
                    <p class="date-value"><?php 
                        $delivery_date = new DateTime($subscription['nextPaymentDateGmt']);
                        $delivery_date->modify('+3 days'); // Adjust as needed
                        echo esc_html($delivery_date->format('l j F Y')); 
                    ?></p>
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

<style>
/**
 * BOCS Enhanced UI/UX Styles
 * Modern, accessible, and interactive button and link styles
 */

/* ==========================================================================
   Base Button Styles - Enhanced
   ========================================================================== */

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

/* ==========================================================================
   Primary Buttons - Enhanced
   ========================================================================== */

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

/* ==========================================================================
   Secondary Buttons - Enhanced
   ========================================================================== */

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

/* ==========================================================================
   Outline Buttons - Enhanced
   ========================================================================== */

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

/* ==========================================================================
   Danger Buttons - Enhanced
   ========================================================================== */

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

/* ==========================================================================
   Button Loading States
   ========================================================================== */

.button-loading {
    position: relative;
    color: transparent !important;
    pointer-events: none;
}

.button-loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin: -10px 0 0 -10px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: button-loading-spinner 0.8s linear infinite;
}

@keyframes button-loading-spinner {
    to {
        transform: rotate(360deg);
    }
}

/* ==========================================================================
   Link Styles - Enhanced
   ========================================================================== */

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

/* ==========================================================================
   Focus & Active States - Enhanced
   ========================================================================== */

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

/* ==========================================================================
   Button Groups - Enhanced
   ========================================================================== */

.subscription-actions {
    display: flex;
    gap: 1.2em;
    margin-top: 2em;
    flex-wrap: wrap;
    align-items: center;
}

/* ==========================================================================
   Responsive Enhancements
   ========================================================================== */

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

/* ==========================================================================
   High Contrast & Reduced Motion
   ========================================================================== */

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

.woocommerce-subscriptions-wrapper {
    margin-bottom: 2em;
}

/* Alternating colors for subscription cards */
.wc-subscription:nth-child(odd) .accordion-header {
    background-color: var(--wc-secondary-light);
}

.wc-subscription:nth-child(even) .accordion-header {
    background-color: #f7f7f7;
}

/* Hover state for both odd and even headers */
.wc-subscription .accordion-header:hover {
    background-color: #eaeaea;
}

/* Active state from jQuery UI - this will override the alternating colors when active */
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

/* Add margin between subscription cards */
.wc-subscription {
    margin-bottom: 0.5em;
    border-radius: 4px;
    overflow: hidden;
}

/* Hover effect for better interactivity */
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

/* Add a sticky header inside the accordion content */
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
</style>

<script>
jQuery(function($) {
    $("#bocs-subscriptions-accordion").accordion({
        header: "h3",
        collapsible: true,
        heightStyle: "content",
        active: false // Start with all panels collapsed
    });
});

jQuery(document).ready(function ($) {
    console.log('Subscription renewal handlers initialized');

    // Disable default behavior for all BOCS buttons
    $('button.bocs-button').on('click', function (e) {
        e.preventDefault();
        console.log('BOCS button clicked - default prevented');
    });

    // Handle early renewal with specific subscription ID
    $('button.subscription_renewal_early').on('click', function () {
        const buttonElement = $(this);
        const subscriptionId = buttonElement.data('subscription-id');
        const specificButton = $(`#renewal_${subscriptionId}`);

        console.log('Early Renewal clicked:', {
            buttonId: buttonElement.attr('id'),
            subscriptionId: subscriptionId,
            buttonText: buttonElement.text(),
            isDisabled: buttonElement.hasClass('disabled')
        });

        if (!subscriptionId || buttonElement.attr('id') !== `renewal_${subscriptionId}`) {
            console.error('Invalid subscription ID or button mismatch:', {
                subscriptionId: subscriptionId,
                buttonId: buttonElement.attr('id'),
                expectedId: `renewal_${subscriptionId}`
            });
            return;
        }

        if (!buttonElement.hasClass('disabled')) {
            console.log('Processing renewal for subscription:', subscriptionId);
            buttonElement.addClass('disabled');
            buttonElement.text('<?php esc_html_e('Processing...', 'woocommerce'); ?>');
            
            $.ajax({
                url: '<?php echo BOCS_API_URL; ?>subscriptions/' + subscriptionId + '/renew',
                method: 'POST',
                beforeSend: function (xhr) {
                    console.log('Sending renewal request for subscription:', subscriptionId);
                    xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                    xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                    xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                },
                success: function (response) {
                    console.log('Renewal successful for subscription:', subscriptionId, {
                        response: response,
                        buttonState: {
                            id: specificButton.attr('id'),
                            text: specificButton.text(),
                            isDisabled: specificButton.hasClass('disabled')
                        }
                    });
                    specificButton.text('<?php esc_html_e('Renewed', 'woocommerce'); ?>');
                },
                error: function (error) {
                    console.error('Renewal failed for subscription:', subscriptionId, {
                        error: error,
                        buttonState: {
                            id: specificButton.attr('id'),
                            text: specificButton.text(),
                            isDisabled: specificButton.hasClass('disabled')
                        }
                    });
                    specificButton.text('<?php esc_html_e('Early Renewal', 'woocommerce'); ?>');
                    specificButton.removeClass('disabled');
                }
            });
        } else {
            console.log('Button not eligible for renewal:', {
                isDisabled: buttonElement.hasClass('disabled'),
                buttonText: buttonElement.text(),
                subscriptionId: subscriptionId
            });
        }
    });

    // Log initial button states
    $('.subscription_renewal_early').each(function() {
        console.log('Found renewal button:', {
            id: $(this).attr('id'),
            subscriptionId: $(this).data('subscription-id'),
            text: $(this).text(),
            isDisabled: $(this).hasClass('disabled')
        });
    });

    // Store the active subscription ID when opening details
    let activeSubscriptionId = null;

    // Handle view details button
    $('.view-details').on('click', function(e) {
        e.preventDefault();
        activeSubscriptionId = $(this).data('subscription-id');
        const subscriptionName = $(this).closest('.wc-subscription')
            .find('.subscription-title').first().text().trim();
        
        // Update the navigation with subscription name
        $('#subscription-details-view .subscription-name').text(subscriptionName);
        
        // Hide the subscriptions list
        $('#bocs-subscriptions-accordion').hide();
        
        // Show the details view
        $('#subscription-details-view').show();
    });

    // Handle back to subscription button
    $('.back-to-subscription-button').on('click', function(e) {
        e.preventDefault();
        
        // Hide the details view
        $('#subscription-details-view').hide();
        
        // Show the subscriptions list and open the specific accordion
        $('#bocs-subscriptions-accordion').show();
        
        if (activeSubscriptionId) {
            // Find and activate the specific accordion
            $(`#renewal_${activeSubscriptionId}`).closest('.wc-subscription')
                .find('.accordion-header').trigger('click');
        }
    });

    // Function to update product prices based on discount
    function updateProductPrices(discount) {
        $('.box-item').each(function() {
            var originalPrice = $(this).data('original-price');
            if (originalPrice) {
                var discountAmount = (originalPrice * discount) / 100;
                var finalPrice = originalPrice - discountAmount;
                
                var priceHtml = '<div class="item-price">' +
                    '<span class="original-price">' + formatPrice(originalPrice) + '</span>';
                
                if (discount > 0) {
                    priceHtml += '<span class="price-discount">-' + formatPrice(discountAmount) + '</span>' +
                        '<span class="final-price">' + formatPrice(finalPrice) + '</span>';
                }
                
                priceHtml += '</div>';
                
                $(this).find('.item-price').replaceWith(priceHtml);
            }
        });
    }

    // Helper function to format price
    function formatPrice(price) {
        return accounting.formatMoney(price, {
            symbol: '<?php echo get_woocommerce_currency_symbol(); ?>',
            decimal: '<?php echo get_option('woocommerce_price_decimal_sep'); ?>',
            thousand: '<?php echo get_option('woocommerce_price_thousand_sep'); ?>',
            precision: <?php echo get_option('woocommerce_price_num_decimals'); ?>,
            format: '%s%v'
        });
    }

    // Show/hide frequency editor
    $('.edit-link').on('click', function() {
        $(this).closest('.details-section').find('.frequency-editor').show();
        $(this).hide();
    });

    $('.cancel-edit').on('click', function() {
        $(this).closest('.frequency-editor').hide();
        $(this).closest('.details-section').find('.edit-link').show();
    });

    // Update prices when frequency changes
    $('.save-frequency').on('click', function() {
        var selectedFreq = $('input[name="frequency"]:checked');
        if (selectedFreq.length) {
            // Update the display with the selected frequency name
            $(this).closest('.details-section')
                .find('.current-frequency')
                .text(selectedFreq.data('name'));
            
            // Update prices with new discount
            updateProductPrices(selectedFreq.data('discount'));
            
            // Hide the editor
            $(this).closest('.frequency-editor').hide();
            
            // Show the edit button again
            $(this).closest('.details-section').find('.edit-link').show();
        }
    });

    // Initial price update
    updateProductPrices(<?php echo $discount_percent; ?>);
});
</script>