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
                            <a href="<?php echo esc_url($subscription_url); ?>" class="woocommerce-button button alt"><?php esc_html_e('Edit Details', 'woocommerce'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
    <?php
        }
    }
    ?>
</div>

<style>
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
    margin-top: 1.5em;
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
});
</script>