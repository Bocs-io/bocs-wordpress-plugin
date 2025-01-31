/**
 * Global BOCS configuration object injected by WordPress
 * @typedef {Object} bocsAjaxObject
 * @property {string} cartNonce - Cart nonce for authentication
 * @property {string} productUrl - URL for product endpoints
 * @property {string} orgId - Organization ID
 * @property {string} storeId - Store ID
 * @property {string} authId - Authorization ID
 * @property {string} ajax_url - AJAX URL
 * @property {string} couponNonce - Coupon nonce for authentication
 * @property {string} cartURL - Cart URL
 * @property {string} loginURL - Login URL
 * @property {string|boolean} isLoggedIn - User login status
 */
/* global bocsAjaxObject */

/**
 * Adds products to WooCommerce cart with subscription frequency and discount handling
 * @async
 * @param {Object} params - Cart parameters
 * @param {number} params.price - Subtotal price before discount
 * @param {number} params.discount - Discount amount to apply
 * @param {Object} params.selectedFrequency - Subscription frequency details
 * @param {Array} params.selectedProducts - Array of products to add to cart
 * @param {number} params.total - Total price after discount
 */
async function bocs_add_to_cart({price, discount, selectedFrequency: frequency, selectedProducts: products, total, bocsId, collectionId }) {

	let bocsFrequencyId = frequency.id;
	var discountType = frequency.discountType ?? 'fixed_cart';

	var id = bocsId
	
	const buttonCart = jQuery('div#bocs-widget button.ant-btn');

	await jQuery.ajax({
		url: '/wp-json/wc/store/v1/cart/items',
		method: 'DELETE',
		beforeSend: function (xhr) {
			xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
		}
	});

	buttonCart.prop('disabled', true);
	buttonCart.html('Processing...');

	// Loop through the products array and add each product to the cart
	for (const product of products) {

		if (!product.externalSourceId) continue;

		let wcProductId = product.externalSourceId;

		if(product.variations && product.variations.length > 0){
			// we will get the variations
			let variationIds = [];
			for (const bocsVariationId of product.variations) {
				// get the details of the variation
				const variation = await jQuery.ajax({
					url: bocsAjaxObject.productUrl + bocsVariationId,
					type: "GET",
					contentType: "application/json; charset=utf-8",
					headers: {
						'Organization': bocsAjaxObject.orgId,
						'Store': bocsAjaxObject.storeId,
						'Authorization': bocsAjaxObject.authId
					}
				});

				// Add the variation's externalSourceId to the array
				variationIds.push(variation.data.externalSourceId);
				
			}

			if(variationIds.length > 0){
				// Get the minimum variation ID as the default product ID
				wcProductId = Math.min(...variationIds);
			}
		}

		var data = {
			id: wcProductId,
			quantity: product.quantity
		};

		await jQuery.ajax({
			url: '/wp-json/wc/store/v1/cart/add-item',
			method: 'POST',
			data: data,
			beforeSend: function (xhr) {
				xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
			},
			success: function (response) {
				// console.log('Product added to cart:', response);
			},
			error: function (error) {
				console.error('Error adding product to cart:', error);
			},
		});

	}

	// then we will try to add the coupon if there is a discount
	if (discount > 0) {

		let amount = discount;

		if (frequency && !isNaN(parseFloat(frequency.discount))) {
			amount = parseFloat(frequency.discount);
		}
		
		let couponCode = "bocs-" + amount;

		if (discountType.toLowerCase().includes('percent')) {
			couponCode = couponCode + "-percent";
			discountType = "percent";
		} else {
			couponCode = couponCode + "-dollar-off";
		}

		couponCode = couponCode + "-" + (Math.random() + 1).toString(36).substring(7);
		const now = Date.now();
		couponCode = couponCode + "-" + now;

		// then we will try to add this coupon
		const createdCoupon = await jQuery.ajax({
			url: bocsAjaxObject.ajax_url,
			type: 'POST',
			data: {
				action: 'create_coupon',
				nonce: bocsAjaxObject.couponNonce,
				coupon_code: couponCode,
				discount_type: discountType,
				amount: amount
			}
		});

		if (createdCoupon) {
			// add to cart the created coupon
			data = {
				code: couponCode
			};

			await jQuery.ajax({
				url: '/wp-json/wc/store/v1/cart/apply-coupon',
				method: 'POST',
				data: data,
				beforeSend: function (xhr) {
					xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
				},
				success: function (response) {
					// console.log('Product added to cart:', response);
				},
				error: function (error) {
					console.error('Error adding product to cart:', error);
				},
			});
		}
	}

	buttonCart.html('Redirecting to Cart...');
	// create cookie
	document.cookie = "__bocs_id="+id+"; path=/";
	if (collectionId && collectionId !== 'undefined' && collectionId !== '') {
		document.cookie = "__bocs_collection_id="+collectionId+"; path=/";
	}
	document.cookie = "__bocs_frequency_id="+bocsFrequencyId+"; path=/";
	document.cookie = "__bocs_frequency_time_unit="+frequency.timeUnit+"; path=/";
	document.cookie = "__bocs_frequency_interval="+frequency.frequency+"; path=/";
	document.cookie = "__bocs_discount_type="+frequency.discountType+"; path=/";
	document.cookie = "__bocs_total="+total+"; path=/";
	document.cookie = "__bocs_discount="+discount+"; path=/";
	document.cookie = "__bocs_subtotal="+price+"; path=/";

	// Check if user is logged in - accepts both string '1' and boolean true values
	// This dual check handles different ways WordPress might return the login status
	const isLoggedIn = bocsAjaxObject.isLoggedIn === '1' || bocsAjaxObject.isLoggedIn === true;

	// Construct the cart URL with all necessary parameters for subscription processing
	// Parameters include:
	// - bocs: bundle/box ID
	// - collection: collection identifier
	// - frequency: subscription frequency ID
	// - total: final price after discounts
	// - discount: applied discount amount
	// - price: original price before discounts
	const redirectUrl = bocsAjaxObject.cartURL+'?bocs='+id+'&collection='+collectionId+'&frequency='+bocsFrequencyId+'&total='+total+'&discount='+discount+'&price='+price;
	window.location.href = escapeUrl(redirectUrl);

	/*if (!isLoggedIn) {
		// For non-logged in users:
		// 1. Redirect to login page
		// 2. Include the cart URL as redirect_to parameter for post-login redirect
		// 3. Add a friendly login message
		// 4. Ensure all parameters are properly URL encoded
		window.location.href = escapeUrl(bocsAjaxObject.loginURL + 
			'?redirect_to=' + encodeURIComponent(redirectUrl) + 
			'&login_message=' + encodeURIComponent('Please log in to purchase Bocs subscription products.'));
	} else {
		// For logged in users:
		// Directly redirect to cart page with subscription parameters
		window.location.href = escapeUrl(redirectUrl);
	}*/
}

/**
 * Safely escapes and encodes URLs with query parameters
 * @param {string} url - The URL to escape (can include query parameters)
 * @returns {string} The properly encoded URL with escaped parameters
 * @description
 * This function handles URL encoding in two parts:
 * 1. Base URL: Uses encodeURI() which preserves URL special characters
 * 2. Query parameters: Uses encodeURIComponent() for stricter encoding of parameter keys and values
 * 
 * @example
 * // Basic usage
 * escapeUrl('https://example.com?name=John Doe&type=user')
 * // Returns: 'https://example.com?name=John%20Doe&type=user'
 * 
 * @throws {Error} Logs error to console if URL parsing fails
 */
function escapeUrl(url) {
	// Split URL into base and query parameters
	try {
		const [baseUrl, params] = url.split('?');
		
		// If no query parameters exist, encode entire URL as is
		if (!params) return encodeURI(url);
		
		// Process each query parameter separately
		const sanitizedParams = params.split('&')
			.map(param => {
				// Split each parameter into key-value pair
				const [key, value] = param.split('=');
				// Encode both key and value, handling cases where value might be undefined
				return `${encodeURIComponent(key)}=${encodeURIComponent(value || '')}`;
			})
			.join('&');
			
		// Combine encoded base URL with encoded parameters
		return `${encodeURI(baseUrl)}?${sanitizedParams}`;
	} catch (e) {
		// Log any errors during URL processing
		console.error('Error escaping URL:', e);
		return '';
	}
}