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

    public function bocs_cart_totals_before_shipping()
    {
        error_log('bocs_cart_totals_before_shipping');
        // Output the custom message
        ?>
        <div class="wc-block-components-totals-wrapper slot-wrapper">
        	<div class="wc-block-components-order-meta">
        		<div class="wcs-recurring-totals-panel">
        			<div class="wc-block-components-totals-item wcs-recurring-totals-panel__title">
        				<span class="wc-block-components-totals-item__label">Monthly recurring total</span>
        				<span class="wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value">$19.48</span>
        				<div class="wc-block-components-totals-item__description">
        					<span>Starting: July 4, 2024 </span>
    					</div>
					</div>
					<div class="wcs-recurring-totals-panel__details wc-block-components-panel">
						<div>
							<button aria-expanded="false" class="wc-block-components-panel__button">
    							<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" aria-hidden="true" class="wc-block-components-panel__button-icon" focusable="false">
    								<path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
    							</svg>
    							Details
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
        <?php
    }
}