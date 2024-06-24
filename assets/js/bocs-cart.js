jQuery(window).on('load', function() {
	console.log(bocsCartObject);
	if(jQuery('div.wc-block-components-totals-wrapper').length > 0 && typeof bocsCartObject.bocs !== 'undefined' && typeof bocsCartObject.bocs !== undefined ){
		
		if(bocsCartObject.bocs['products'].length > 0){
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
    if (j == 1 && k != 11) {
        return number + "st";
    }
    if (j == 2 && k != 12) {
        return number + "nd";
    }
    if (j == 3 && k != 13) {
        return number + "rd";
    }
    return number + "th";
}