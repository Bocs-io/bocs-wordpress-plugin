<?php

class Bocs_Cart
{

    public function add_subscription_options_to_cart()
    {
        ?>
    <div class="subscription-options">
        <h2>Make Your Cart a Subscription</h2>
        <p>Select a frequency for your subscription:</p>
        <form id="subscription-options-form" method="post">
            <label>
                <input type="radio" name="subscription_frequency" value="weekly" required> Weekly
            </label>
            <label>
                <input type="radio" name="subscription_frequency" value="monthly" required> Monthly
            </label>
            <label>
                <input type="radio" name="subscription_frequency" value="yearly" required> Yearly
            </label>
            <button type="submit" name="update_cart_to_subscription" value="1">Update to Subscription</button>
        </form>
    </div>
    <?php
    }
}