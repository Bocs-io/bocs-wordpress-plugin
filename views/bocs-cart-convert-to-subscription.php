<div class="subscription-options">
    <h2>Make Your Cart a Subscription</h2>
    <p>Select a subscription plan:</p>
    <form id="subscription-options-form" method="post">
    <?php
    foreach ($bocs_options as $bocs_item) {
        foreach ($bocs_item['priceAdjustment']['adjustments'] as $option) {
            ?>
            <label>
                <input type="radio" name="subscription_frequency" value="<?php

            echo $bocs_item['id'] . '_' . $option['id']?>" required> <?php

            echo $bocs_item['name'] . ' - ';

            echo '$' . $total . " / " . $option['frequency'] . ' ' . $option['timeUnit'];

            ?>
            </label>
            <?php
        }
    }
    ?>
        <button type="submit" name="update_cart_to_subscription" value="1">Update to Subscription</button>
    </form>
</div>