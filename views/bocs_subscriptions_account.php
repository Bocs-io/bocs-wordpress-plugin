<table class="my_account_subscriptions my_account_orders woocommerce-orders-table woocommerce-MyAccount-subscriptions shop_table shop_table_responsive woocommerce-orders-table--subscriptions">

    <thead>
        <tr>
            <th class="subscription-id order-number woocommerce-orders-table__header woocommerce-orders-table__header-order-number woocommerce-orders-table__header-subscription-id"><span class="nobr">Subscription</span></th>
            <th class="subscription-status order-status woocommerce-orders-table__header woocommerce-orders-table__header-order-status woocommerce-orders-table__header-subscription-status"><span class="nobr">Status</span></th>
            <th class="subscription-next-payment order-date woocommerce-orders-table__header woocommerce-orders-table__header-order-date woocommerce-orders-table__header-subscription-next-payment"><span class="nobr">Next payment</span></th>
            <th class="subscription-total order-total woocommerce-orders-table__header woocommerce-orders-table__header-order-total woocommerce-orders-table__header-subscription-total"><span class="nobr">Total</span></th>
            <th class="subscription-actions order-actions woocommerce-orders-table__header woocommerce-orders-table__header-order-actions woocommerce-orders-table__header-subscription-actions">&nbsp;</th>
        </tr>
    </thead>

    <tbody>
        <?php
        if (!empty($subscriptions['data']['data']) && is_array($subscriptions['data']['data'])) {
            foreach ($subscriptions['data']['data'] as $subscription) {
        ?>
                <tr class="order woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php

                                                                                                        echo $subscription['status'] ?>">
                    <td class="subscription-id order-number woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-id woocommerce-orders-table__cell-order-number" data-title="ID">
                        <a href="<?php

                                    echo wc_get_endpoint_url('bocs-view-subscription', $subscription['id'], wc_get_page_permalink('myaccount'));
                                    ?>"><?php

                echo !empty(trim($subscription['bocs']['name'])) ? $subscription['bocs']['name'] : 'Subscription #' . $subscription['subscriptionNumber'] ?></a>
                    </td>
                    <td class="subscription-status order-status woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-status woocommerce-orders-table__cell-order-status" data-title="Status"><?php

                                                                                                                                                                                                                    echo ucfirst($subscription['subscriptionStatus']) ?></td>
                    <td class="subscription-next-payment order-date woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-next-payment woocommerce-orders-table__cell-order-date" data-title="Next Payment"><?php

                                                                                                                                                                                                                                    $date = new DateTime($subscription['nextPaymentDateGmt']);
                                                                                                                                                                                                                                    echo $date->format('F j, Y') ?></td>
                    <td class="subscription-total order-total woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-total woocommerce-orders-table__cell-order-total" data-title="Total">
                        <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">$</span><?php

                                                                                                                                echo $subscription['total'] ?></span> every <?php
                                                    $frequency = isset($subscription['frequency']['frequency']) ? $subscription['frequency']['frequency'] : '';
                                                    if (!empty($subscription['billingInterval']) && $frequency == '') {
                                                        $frequency = $subscription['billingInterval'];
                                                    }
                                                    $period = isset($subscription['frequency']['timeUnit']) ? $subscription['frequency']['timeUnit'] : '';
                                                    if (!empty($subscription['billingPeriod']) && $period == '') {
                                                        $period = $subscription['billingPeriod'];
                                                    }

                                                    if ($frequency > 1) {
                                                        // Remove any trailing 's' and then add one 's'
                                                        $period = rtrim($period, 's') . 's';
                                                    } else {
                                                        // If frequency is 1, ensure there is no trailing 's'
                                                        $period = rtrim($period, 's');
                                                        $frequency = '';
                                                    }

                                                    echo $frequency . ' ' . $period;
                                                    ?>
                    </td>
                    <td class="subscription-actions order-actions woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-actions woocommerce-orders-table__cell-order-actions">
                        <a href="<?php

                                    echo wc_get_endpoint_url('bocs-view-subscription', $subscription['id'], wc_get_page_permalink('myaccount'));
                                    ?>" class="woocommerce-button button view">View</a>
                    </td>
                </tr>
        <?php
            }
        }
        ?>
    </tbody>

</table>