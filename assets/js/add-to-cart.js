/**
 * @global
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
async function bocs_add_to_cart({price, discount, selectedFrequency: frequency, selectedProducts: products, total }) {
	// { bocsId:id, collectionId, selectedFrequency: frequency, selectedProducts: products }
	// console.log('bocs_add_to_cart params', params);
	
	let bocsFrequencyId = frequency.id;
	var id = jQuery('div#bocs-widget').data('id');
	
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

	console.log('products', products);

	// Loop through the products array and add each product to the cart
	for (const product of products) {

		if(product.externalSource != "WP" || product.externalSourceId == 0) continue;

		let wcProductId = product.externalSourceId;

		if(product.variations){
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

			 // Get the minimum variation ID as the default product ID
			 wcProductId = Math.min(...variationIds);

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
				console.log('Product added to cart:', response);
			},
			error: function (error) {
				console.error('Error adding product to cart:', error);
			},
		});

	}

	// then we will try to add the coupon if there is a discount
	if (discount > 0) {

		let discountType = "fixed_cart";
		let amount = discount;

		if (frequency && !isNaN(parseFloat(frequency.discount))) {
			amount = parseFloat(frequency.discount);
		}
		
		let couponCode = "bocs-" + amount;

		if (frequency && frequency.discountType !== '') {
			discountType = frequency.discountType;
		}

		if (discountType === "percent") {
			couponCode = couponCode + "percent";
			discountType = "percent";
		} else {
			couponCode = couponCode + "off";
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
			var data = {
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
	if( id == null) id = '';
	const collectionId = '';
	// if(collectionId == null) collectionId = '';

	// create cookie
	document.cookie = "__bocs_id="+id+"; path=/";
	if( collectionId != '' ) document.cookie = "__bocs_collection_id="+collectionId+"; path=/";
	document.cookie = "__bocs_frequency_id="+bocsFrequencyId+"; path=/";
	document.cookie = "__bocs_frequency_time_unit="+frequency.timeUnit+"; path=/";
	document.cookie = "__bocs_frequency_interval="+frequency.frequency+"; path=/";
	document.cookie = "__bocs_discount_type="+frequency.discountType+"; path=/";
	document.cookie = "__bocs_total="+total+"; path=/";
	document.cookie = "__bocs_discount="+discount+"; path=/";
	document.cookie = "__bocs_subtotal="+price+"; path=/";

	// Check if user is logged in
	const isLoggedIn = bocsAjaxObject.isLoggedIn === '1' || bocsAjaxObject.isLoggedIn === true;
	const redirectUrl = bocsAjaxObject.cartURL+'?bocs='+id+'&collection='+collectionId+'&frequency='+bocsFrequencyId+'&total='+total+'&discount='+discount+'&price='+price;
	
	if (!isLoggedIn) {
		window.location.href = bocsAjaxObject.loginURL + 
			'?redirect_to=' + encodeURIComponent(redirectUrl) + 
			'&login_message=' + encodeURIComponent('Please log in to purchase Bocs subscription products.');
	} else {
		window.location.href = redirectUrl;
	}
}