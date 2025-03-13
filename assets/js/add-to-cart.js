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
 * @param {string} params.bocsId - BOCS identifier
 * @param {string} params.collectionId - Collection identifier
 */
async function bocs_add_to_cart({price, discount, selectedFrequency: frequency, selectedProducts: products, total, bocsId, collectionId}) {
	// Initialize cart button and disable during processing
	const buttonCart = jQuery('div#bocs-widget button.ant-btn');
	buttonCart.prop('disabled', true);
	buttonCart.html('Processing...');
	
	try {
		// Get frequency details
		const bocsFrequencyId = frequency.id;
		const discountType = frequency.discountType ?? 'fixed_cart';
		
		// Get proper ID based on collection
		let id = jQuery('div#bocs-widget').data('id');
		if (collectionId != null && typeof collectionId !== 'undefined' && collectionId !== '') {
			id = bocsId;
		}

		// Clear cart first
		await clearCart();
		
		// Add all products to cart
		await addProductsToCart(products);
		
		// Apply coupon if discount exists
		if (discount > 0) {
			await applyDiscount(discount, frequency, discountType);
		}
		
		// Set cookies for session data
		setCookies({
			id,
			collectionId,
			bocsFrequencyId,
			frequency,
			total,
			discount,
			price
		});
		
		// Prepare redirect
		buttonCart.html('Redirecting to Cart...');
		
		// Build cart URL with parameters
		const redirectUrl = buildRedirectUrl({
			id: bocsId || id,
			collectionId,
			bocsFrequencyId,
			total,
			discount,
			price
		});
		
		// Redirect to cart
		window.location.href = escapeUrl(redirectUrl);
	} catch (error) {
		// Silently handle error and restore button state
		buttonCart.prop('disabled', false);
		buttonCart.html('Add to Cart');
	}
}

/**
 * Clears the current cart via API
 * @async
 * @returns {Promise} Promise representing the cart clearing operation
 */
async function clearCart() {
	return jQuery.ajax({
		url: '/wp-json/wc/store/v1/cart/items',
		method: 'DELETE',
		beforeSend: function(xhr) {
			xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
		}
	});
}

/**
 * Adds multiple products to the cart
 * @async
 * @param {Array} products - Products to add to cart
 */
async function addProductsToCart(products) {
	for (const product of products) {
		if (!product.externalSourceId) continue;
		
		let wcProductId = product.externalSourceId;
		
		// Handle product variations if they exist
		if (product.variations && product.variations.length > 0) {
			const variationIds = await getVariationIds(product.variations);
			
			if (variationIds.length > 0) {
				// Get the minimum variation ID as the default product ID
				wcProductId = Math.min(...variationIds);
			}
		}
		
		// Add product to cart
		await addSingleProductToCart(wcProductId, product.quantity, product.price);
	}
}

/**
 * Gets all variation IDs for a product
 * @async
 * @param {Array} variations - Variation IDs to fetch
 * @returns {Array} Array of external variation IDs
 */
async function getVariationIds(variations) {
	const variationIds = [];
	
	for (const bocsVariationId of variations) {
		try {
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
			if (variation && variation.data && variation.data.externalSourceId) {
				variationIds.push(variation.data.externalSourceId);
			}
		} catch (error) {
			// Silently handle error
		}
	}
	
	return variationIds;
}

/**
 * Adds a single product to the cart
 * @async
 * @param {number} productId - WooCommerce product ID
 * @param {number} quantity - Quantity to add
 * @param {number} price - Price of the product
 */
async function addSingleProductToCart(productId, quantity, price) {
	// Basic cart data
	const data = {
		id: productId,
		quantity: quantity
	};
	
	// Store the price in a product-specific cookie
	document.cookie = `__bocs_price_${productId}=${price}; path=/`;
	
	return jQuery.ajax({
		url: '/wp-json/wc/store/v1/cart/add-item',
		method: 'POST',
		data: data,
		beforeSend: function(xhr) {
			xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
		}
	}).catch(error => {
		// Silently handle error
	});
}

/**
 * Creates and applies a discount coupon
 * @async
 * @param {number} discount - Discount amount
 * @param {Object} frequency - Frequency object with discount information
 * @param {string} discountType - Type of discount (fixed_cart or percent)
 */
async function applyDiscount(discount, frequency, discountType) {
	// Determine actual discount amount
	let amount = discount;
	if (frequency && !isNaN(parseFloat(frequency.discount))) {
		amount = parseFloat(frequency.discount);
	}
	
	// Generate unique coupon code
	const isPercentage = discountType.toLowerCase().includes('percent');
	const randomString = (Math.random() + 1).toString(36).substring(7);
	const timestamp = Date.now();
	
	let couponCode = `bocs-${amount}`;
	couponCode += isPercentage ? "-percent" : "-dollar-off";
	couponCode += `-${randomString}-${timestamp}`;
	
	// Set final discount type
	if (isPercentage) {
		discountType = "percent";
	}
	
	// Create coupon
	try {
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
			// Apply coupon to cart
			await jQuery.ajax({
				url: '/wp-json/wc/store/v1/cart/apply-coupon',
				method: 'POST',
				data: { code: couponCode },
				beforeSend: function(xhr) {
					xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
				}
			});
		}
	} catch (error) {
		// Silently handle error
	}
}

/**
 * Sets cookies with BOCS session data
 * @param {Object} params - Cookie parameters
 */
function setCookies(params) {
	const cookieData = {
		"__bocs_id": params.id,
		"__bocs_collection_id": params.collectionId,
		"__bocs_frequency_id": params.bocsFrequencyId,
		"__bocs_frequency_time_unit": params.frequency.timeUnit,
		"__bocs_frequency_interval": params.frequency.frequency,
		"__bocs_discount_type": params.frequency.discountType,
		"__bocs_total": params.total,
		"__bocs_discount": params.discount,
		"__bocs_subtotal": params.price
	};
	
	// Set each cookie if value exists
	for (const [key, value] of Object.entries(cookieData)) {
		if (value != null && typeof value !== 'undefined' && value !== '') {
			document.cookie = `${key}=${value}; path=/`;
		}
	}
}

/**
 * Builds redirect URL with query parameters
 * @param {Object} params - URL parameters
 * @returns {string} Fully formed redirect URL
 */
function buildRedirectUrl(params) {
	const queryParams = [];
	
	// Add parameters to URL if they exist
	if (params.id) queryParams.push(`bocs=${params.id}`);
	if (params.collectionId) queryParams.push(`collection=${params.collectionId}`);
	if (params.bocsFrequencyId) queryParams.push(`frequency=${params.bocsFrequencyId}`);
	if (params.total) queryParams.push(`total=${params.total}`);
	if (params.discount) queryParams.push(`discount=${params.discount}`);
	if (params.price) queryParams.push(`price=${params.price}`);
	
	// Build final URL
	return bocsAjaxObject.cartURL + (queryParams.length ? '?' + queryParams.join('&') : '');
}

/**
 * Safely escapes and encodes URLs with query parameters
 * @param {string} url - The URL to escape (can include query parameters)
 * @returns {string} The properly encoded URL with escaped parameters
 */
function escapeUrl(url) {
	try {
		const [baseUrl, params] = url.split('?');
		
		// If no query parameters exist, encode entire URL as is
		if (!params) return encodeURI(url);
		
		// Process each query parameter separately
		const sanitizedParams = params.split('&')
			.map(param => {
				const [key, value] = param.split('=');
				return `${encodeURIComponent(key)}=${encodeURIComponent(value || '')}`;
			})
			.join('&');
			
		// Combine encoded base URL with encoded parameters
		return `${encodeURI(baseUrl)}?${sanitizedParams}`;
	} catch (e) {
		// Silently handle error
		return '';
	}
}