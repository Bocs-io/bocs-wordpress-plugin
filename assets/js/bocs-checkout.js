/**
 * BOCS (Bundle of Subscription) Checkout Handler
 * This script manages the display of subscription information on the WooCommerce checkout page.
 * It adds recurring total information and formats dates and frequencies for subscription products.
 */

// Event listener for when the window fully loads
jQuery(window).on('load', function() {
	console.log(bocsCheckoutObject);
	
	// Check if checkout page elements exist and BOCS data is available
	if (jQuery('div.wp-block-woocommerce-checkout-order-summary-totals-block').length > 0 
		&& typeof bocsCheckoutObject !== 'undefined' 
		&& bocsCheckoutObject !== null 
		&& typeof bocsCheckoutObject.bocs !== 'undefined' 
		&& bocsCheckoutObject.bocs !== null 
		&& Array.isArray(bocsCheckoutObject.bocs['products']) 
		&& bocsCheckoutObject.bocs['products'].length > 0) {
		
		// Date formatting setup
		var today = new Date();
		var monthNames = [
			"January", "February", "March", "April", "May", "June",
			"July", "August", "September", "October", "November", "December"
		];
		
		// Extract and format the current date
		var day = today.getDate();
		var month = monthNames[today.getMonth()];
		var year = today.getFullYear();
		var formattedDate = month + " " + day + ", " + year;
		
		// Calculate total for fixed-type subscriptions
		let total = 0;
		if(bocsCheckoutObject.bocs['type'] == 'fixed'){
			for ( var i = 0, l = bocsCheckoutObject.bocs['products'].length; i < l; i++ ) {
				total += bocsCheckoutObject.bocs['products'][ i ]['quantity'] * bocsCheckoutObject.bocs['products'][ i ]['regularPrice'];
			}
		}
		if(bocsCheckoutObject.frequency){
			// Format the recurring frequency text
			recurringFreq = formatFrequency( bocsCheckoutObject.frequency['frequency'], bocsCheckoutObject.frequency['timeUnit'] );
		} else {
			// Get frequency ID from cookie if available
			frequencyId = '';
			if (isset($_COOKIE['__bocs_frequency_id'])) {
				frequencyId = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
			}
			if(frequencyId){
			for ( var i = 0, l = bocsCheckoutObject.bocs['priceAdjustment']['adjustments'].length; i < l; i++ ) {
				if(bocsCheckoutObject.bocs['priceAdjustment']['adjustments'][i]['id'] == frequencyId){
					recurringFreq = formatFrequency( bocsCheckoutObject.bocs['priceAdjustment']['adjustments'][i]['frequency'], bocsCheckoutObject.bocs['priceAdjustment']['adjustments'][i]['timeUnit'] );
					break;
				}
			}
		}
		
		
		// Create HTML element for recurring total display
		var htmlElement = '<div data-block-name="woocommerce/checkout-order-summary-totals-block" class="wp-block-woocommerce-checkout-order-summary-totals-block">' +
			'<div class="wp-block-woocommerce-checkout-order-summary-subtotal-block wc-block-components-totals-wrapper">' +
			'<div class="wc-block-components-totals-item">' +
			'<span class="wc-block-components-totals-item__label">Recurring total '+recurringFreq+'</span>' +
			'<span class="wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value">$' + total + '</span>' +
			'<div class="wc-block-components-totals-item__description"><span>Starting: ' + formattedDate + '</span></div>' +
			'</div></div>' +
			'<div class="wp-block-woocommerce-checkout-order-summary-discount-block wc-block-components-totals-wrapper"></div>' +
			'<div class="wp-block-woocommerce-checkout-order-summary-fee-block wc-block-components-totals-wrapper"></div></div>';
		
		// Update DOM elements
		jQuery('div.wc-block-components-totals-footer-item span.wc-block-components-totals-item__label').text("Total due today");
		jQuery( jQuery(htmlElement) ).insertBefore( jQuery("div.wp-block-woocommerce-checkout-order-summary-totals-block") );
	}
});

/**
 * Formats the frequency of subscription into human-readable text
 * @param {number} frequency - The numerical frequency (e.g., 1, 2, 3)
 * @param {string} timeUnit - The time unit (e.g., 'days', 'months', 'years')
 * @returns {string} Formatted frequency string (e.g., 'monthly', 'every 2nd month')
 */
function formatFrequency(frequency, timeUnit) {
	if (frequency === 1) {
		// Handle single unit frequencies (e.g., monthly, yearly)
		switch (timeUnit) {
			case "months":
			case "month":
				return "monthly";
			case "year":
			case "years":
				return "yearly";
			case "days":
			case "day":
				return "daily";
			case "weeks":
			case "week":
				return "weekly";
			default:
				return "every " + timeUnit;
		}
	} else {
		// Handle multiple unit frequencies (e.g., every 2nd month)
		const suffix = getOrdinalSuffix(frequency);
		switch (timeUnit) {
			case "days":
			case "day":
				return "every " + suffix + " day";
			case "weeks":
			case "week":
				return "every " + suffix + " week";
			case "months":
			case "month":
				return "every " + suffix + " month";
			case "year":
			case "years":
				return "every " + suffix + " year";
			default:
				return "every " + suffix + " " + timeUnit;
		}
	}
}

/**
 * Generates the ordinal suffix for a number (1st, 2nd, 3rd, 4th, etc.)
 * @param {number} number - The number to generate a suffix for
 * @returns {string} The number with its ordinal suffix
 */
function getOrdinalSuffix(number) {
	const j = number % 10,
		  k = number % 100;
	if (j === 1 && k !== 11) {
		return number + "st";
	}
	if (j === 2 && k !== 12) {
		return number + "nd";
	}
	if (j === 3 && k !== 13) {
		return number + "rd";
	}
	return number + "th";
}