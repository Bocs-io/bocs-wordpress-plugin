<div class="account-info-section">
    <div class="account-info-header">
        <h2>ACCOUNT INFO</h2>
        <a href="#" class="edit-link">Edit</a>
    </div>
    <p class="account-description">This account information is tied directly to your subscriptions.</p>
    
    <div class="account-details">
        <div class="account-detail-group">
            <div class="detail-label">Name</div>
            <div class="detail-value"><?php echo esc_html($customer['name'] ?? ''); ?></div>
        </div>
        
        <div class="account-detail-group">
            <div class="detail-label">Email address</div>
            <div class="detail-value"><?php echo esc_html($customer['email'] ?? ''); ?></div>
        </div>
        
        <div class="account-detail-group">
            <div class="detail-label">Phone number</div>
            <div class="detail-value"><?php echo esc_html($customer['phone'] ?? ''); ?></div>
        </div>
    </div>
</div>

<div class="my_account_subscriptions my_account_orders woocommerce-orders-table">
    <!-- Header Row -->
    <div class="subscription-header-row">
        <div class="subscription-header-cell">
            <span class="nobr">Subscription</span>
        </div>
        <div class="subscription-header-cell">
            <span class="nobr">Status</span>
        </div>
        <div class="subscription-header-cell">
            <span class="nobr">Next payment</span>
        </div>
        <div class="subscription-header-cell">
            <span class="nobr">Total</span>
        </div>
        <div class="subscription-header-cell">
            &nbsp;
        </div>
    </div>

    <!-- Subscription Rows -->
    <?php if (count($subscriptions['data']['data'])) : ?>
        <?php foreach ($subscriptions['data']['data'] as $subscription) : ?>
            <div class="subscription-row status-<?php echo esc_attr($subscription['status']); ?>">
                <div class="subscription-cell" data-title="ID">
                    <a href="<?php echo esc_url(wc_get_endpoint_url('bocs-view-subscription', $subscription['id'], wc_get_page_permalink('myaccount'))); ?>">
                        <?php echo esc_html(!empty(trim($subscription['bocs']['name'])) ? $subscription['bocs']['name'] : 'Subscription #' . $subscription['subscriptionNumber']); ?>
                    </a>
                </div>
                <div class="subscription-cell" data-title="Status">
                    <?php echo esc_html(ucfirst($subscription['subscriptionStatus'])); ?>
                </div>
                <div class="subscription-cell" data-title="Next Payment">
                    <?php 
                    $date = new DateTime($subscription['nextPaymentDateGmt']);
                    echo esc_html($date->format('F j, Y')); 
                    ?>
                </div>
                <div class="subscription-cell" data-title="Total">
                    <span class="woocommerce-Price-amount amount">
                        <span class="woocommerce-Price-currencySymbol">$</span><?php echo esc_html($subscription['total']); ?>
                    </span>
                    every 
                    <?php
                    $frequency = isset($subscription['frequency']['frequency']) ? $subscription['frequency']['frequency'] : '';
                    if (!empty($subscription['billingInterval']) && $frequency == '') {
                        $frequency = $subscription['billingInterval'];
                    }
                    $period = isset($subscription['frequency']['timeUnit']) ? $subscription['frequency']['timeUnit'] : '';
                    if (!empty($subscription['billingPeriod']) && $period == '') {
                        $period = $subscription['billingPeriod'];
                    }

                    if ($frequency > 1) {
                        $period = rtrim($period, 's') . 's';
                    } else {
                        $period = rtrim($period, 's');
                        $frequency = '';
                    }

                    echo esc_html($frequency . ' ' . $period);
                    ?>
                </div>
                <div class="subscription-cell" data-title="Actions">
                    <a href="<?php echo esc_url(wc_get_endpoint_url('bocs-view-subscription', $subscription['id'], wc_get_page_permalink('myaccount'))); ?>" class="woocommerce-button button view">View</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.my_account_subscriptions {
    width: 100%;
    margin-bottom: 2em;
}

.subscription-header-row {
    display: flex;
    background-color: #f8f8f8;
    padding: 1em;
    font-weight: 600;
    border-bottom: 2px solid #ececec;
}

.subscription-row {
    display: flex;
    padding: 1em;
    border-bottom: 1px solid #ececec;
    transition: background-color 0.2s ease;
}

.subscription-row:hover {
    background-color: #fafafa;
}

.subscription-header-cell,
.subscription-cell {
    flex: 1;
    padding: 0.5em;
}

/* Responsive styles */
@media screen and (max-width: 768px) {
    .subscription-header-row {
        display: none;
    }

    .subscription-row {
        flex-direction: column;
        padding: 1em 0;
    }

    .subscription-cell {
        padding: 0.5em 1em;
    }

    .subscription-cell:before {
        content: attr(data-title);
        font-weight: 600;
        display: inline-block;
        min-width: 120px;
    }
}

.account-info-section {
    background: #fff;
    padding: 2em;
    margin-bottom: 2em;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.account-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5em;
}

.account-info-header h2 {
    margin: 0;
    font-size: 1.5em;
}

.edit-link {
    color: #6366f1;
    text-decoration: none;
}

.account-description {
    color: #6b7280;
    margin-bottom: 2em;
}

.account-details {
    display: flex;
    gap: 2em;
    flex-wrap: wrap;
}

.account-detail-group {
    flex: 1;
    min-width: 200px;
}

.detail-label {
    font-weight: 600;
    margin-bottom: 0.5em;
}

.detail-value {
    color: #6b7280;
}

@media screen and (max-width: 768px) {
    .account-detail-group {
        flex: 100%;
        margin-bottom: 1em;
    }
}
</style>