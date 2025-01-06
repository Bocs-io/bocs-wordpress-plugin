/**
 * @typedef {Object} BocsProduct
 * @property {number} quantity - The quantity of the product
 * @property {number} regularPrice - The regular price of the product
 */

/**
 * @typedef {Object} BocsFrequency
 * @property {number} frequency - The frequency number (e.g., 1, 2, 3)
 * @property {string} timeUnit - The time unit (e.g., 'days', 'weeks', 'months', 'years')
 */

/**
 * @typedef {Object} bocsCartObject
 * @property {Object} bocs - The main BOCS (Buy Once, Subscribe) configuration
 * @property {string} bocs.type - The type of subscription (e.g., 'fixed')
 * @property {BocsProduct[]} bocs.products - Array of products in the cart
 * @property {BocsFrequency} frequency - The subscription frequency settings
 * @property {number} [bocsConversionTotal] - Optional conversion total amount
 * @property {string} [bocsConversion] - Optional conversion details
 */
/* global bocsCartObject */

jQuery(window).on('load', function() {
	// Check if bocsCartObject exists in the global scope
	if (typeof window.bocsCartObject === 'undefined') {
		console.warn('bocsCartObject is not defined');
		return;
	}
	
	if(jQuery('div.wc-block-components-totals-wrapper').length > 0 && typeof bocsCartObject.bocs !== 'undefined' && typeof bocsCartObject.bocs !== "undefined" ){
		if( typeof bocsCartObject.bocs['products'] !== 'undefined' && typeof bocsCartObject.bocs['products'] !== "undefined"){			if(bocsCartObject.bocs['products'].length > 0){
				// Create a new Date object for today's date
		        var today = new Date();

		        // Create arrays for month names
		        var monthNames = [
		            "January", "February", "March", "April", "May", "June",
		            "July", "August", "September", "October", "November", "December"
		        ];

		        // Extract day, month, and year
		        var day = today.getDate();
		        var month = monthNames[today.getMonth()];
		        var year = today.getFullYear();

		        // Format the date
		        var formattedDate = month + " " + day + ", " + year;
				
				let total = 0;
						
				if(bocsCartObject.bocs['type'] == 'fixed'){
		            for ( var i = 0, l = bocsCartObject.bocs['products'].length; i < l; i++ ) {
						total += bocsCartObject.bocs['products'][ i ]['quantity'] * bocsCartObject.bocs['products'][ i ]['regularPrice'];
					}
				}
				
				var recurringFreq = formatFrequency( bocsCartObject.frequency['frequency'], bocsCartObject.frequency['timeUnit'] );
				
				var htmlElement = '<div class="wc-block-components-totals-wrapper"><div class="wc-block-components-totals-item wc-block-components-totals-footer-item"><span class="wc-block-components-totals-item__label">' + recurringFreq + ' recurring total</span><div class="wc-block-components-totals-item__value"><span class="wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-footer-item-tax-value">$' + total + '</span></div><div class="wc-block-components-totals-item__description"><span>Starting: ' + formattedDate + '</span></div></div></div>';
				jQuery('div.wc-block-components-totals-footer-item span.wc-block-components-totals-item__label').text("Total due today");
				jQuery("div.wp-block-woocommerce-cart-order-summary-block").append( jQuery(htmlElement) );
			}
		}
	}
	
	if (bocsCartObject.bocsConversionTotal !== undefined && bocsCartObject.bocsConversion !== undefined) {
		console.log(bocsCartObject.bocsConversionTotal, bocsCartObject.bocsConversion);
	}
});


function formatFrequency(frequency, timeUnit) {
    if (frequency === 1) {
        // Handle the special case where frequency is 1
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
        // Handle cases where frequency is greater than 1
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

function getCookieValue(cookieName) {
    var allCookies = document.cookie;
    var cookieStart = allCookies.indexOf(cookieName + "=");
    if (cookieStart === -1) {
        return "";
    }
    var valueStart = cookieStart + cookieName.length + 1;
    var valueEnd = allCookies.indexOf(";", valueStart);
    if (valueEnd === -1) {
        valueEnd = allCookies.length;
    }
    var cookieValue = allCookies.substring(valueStart, valueEnd);
    return decodeURIComponent(cookieValue);
}