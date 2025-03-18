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
async function bocs_add_to_cart(params) {

	console.log(params);

	const {price, discount, selectedFrequency: frequency, selectedProducts: products, total} = params;
	let {bocsId, collectionId} = params;
	
	if (bocsId == null || bocsId === '') {
		// Get BOCS ID only from data-bocs-id attribute
		const $widget = jQuery('div#bocs-widget');
		params.bocsId = $widget.data('bocs-id') || '';
		bocsId = params.bocsId;
	}
	
	// Initialize cart button and disable during processing
	const buttonCart = jQuery('div#bocs-widget button.ant-btn');
	buttonCart.prop('disabled', true);
	buttonCart.html('Processing...');
	
	try {
		// Validate products stock before proceeding
		const stockValid = await validateProductStock(products);
		if (!stockValid) {
			buttonCart.prop('disabled', false);
			buttonCart.html('Add to Cart');
			return; // Don't proceed if stock validation fails
		}
		
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
		const addResult = await addProductsToCart(products);
		
		if (addResult) {
			// Apply coupon if discount exists
			if (discount > 0) {
				await applyDiscount(discount, frequency, discountType);
			}
			
			// Set cookies for session data
			setCookies({
				id,
				bocsId,
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
				id: bocsId || id, // Restored fallback to ensure an ID is used
				collectionId,
				bocsFrequencyId,
				total,
				discount,
				price
			});
			
			// Redirect to cart
			window.location.href = escapeUrl(redirectUrl);
		}
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
 * @returns {Promise<boolean>} Success status of adding products
 */
async function addProductsToCart(products) {
	let allSuccess = true;
	const buttonCart = jQuery('div#bocs-widget button.ant-btn');
	
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
		
		// Check stock before adding to cart
		const stockCheckResult = await checkProductStock(wcProductId, product.quantity);
		
		if (stockCheckResult.success) {
			// Add product to cart if in stock
			const addResult = await addSingleProductToCart(wcProductId, product.quantity, product.price);
			if (!addResult) {
				allSuccess = false;
			}
		} else {
			allSuccess = false;
			// Display error message to user
			buttonCart.html('Error: ' + stockCheckResult.message);
			setTimeout(() => {
				buttonCart.html('Add to Cart');
				buttonCart.prop('disabled', false);
			}, 3000);
			return false; // Stop processing if any product is out of stock
		}
	}
	
	return allSuccess;
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
 * Checks if a product has sufficient stock for the requested quantity
 * @async
 * @param {number} productId - The product ID to check
 * @param {number} requestedQuantity - The quantity requested
 * @returns {Promise<Object>} - Object with success status and message
 */
async function checkProductStock(productId, requestedQuantity) {
	try {
		// Get product data from WooCommerce Store API
		const response = await jQuery.ajax({
			url: `/wp-json/wc/store/v1/products/${productId}`,
			method: 'GET',
			beforeSend: function(xhr) {
				if (window.bocsAjaxObject && bocsAjaxObject.cartNonce) {
					xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
				}
			}
		});
		
		// Check if product is in stock at all
		if (!response.is_in_stock) {
			return { 
				success: false, 
				message: `"${response.name}" is out of stock` 
			};
		}
		
		// If product has stock management
		if (response.has_options === false) {
			// If stock_quantity is undefined, try to get it from product meta
			let stockQuantity = response.stock_quantity;
			
			// If stock quantity is undefined, fetch it from product meta via AJAX
			if (stockQuantity === undefined || stockQuantity === null) {
				try {
					// Get stock from product meta via AJAX
					const metaResponse = await jQuery.ajax({
						url: bocsAjaxObject.ajax_url,
						method: 'POST',
						data: {
							action: 'get_product_stock',
							product_id: productId,
							nonce: bocsAjaxObject.nonce
						}
					});
					
					if (metaResponse.success && metaResponse.data) {
						stockQuantity = parseInt(metaResponse.data, 10);
					}
				} catch (metaError) {
					// Silently handle error
				}
			}
			
			// Check if stock quantity is defined and there's enough stock
			if (stockQuantity !== undefined && stockQuantity !== null) {
				if (stockQuantity < requestedQuantity) {
					return { 
						success: false, 
						message: `Only ${stockQuantity} available` 
					};
				}
			}
		}
		
		// All checks passed, product is available
		return { success: true };
		
	} catch (error) {
		// Error during stock check, default to allowing the add to proceed
		// This ensures the native WooCommerce error handling will still work
		return { success: true };
	}
}

/**
 * Adds a single product to the cart
 * @async
 * @param {number} productId - WooCommerce product ID
 * @param {number} quantity - Quantity to add
 * @param {number} price - Price of the product
 * @returns {Promise<boolean>} Success status
 */
async function addSingleProductToCart(productId, quantity, price) {
	// Basic cart data
	const data = {
		id: productId,
		quantity: quantity
	};
	
	// Store the price in a product-specific cookie
	document.cookie = `__bocs_price_${productId}=${price}; path=/`;
	
	try {
		await jQuery.ajax({
			url: '/wp-json/wc/store/v1/cart/add-item',
			method: 'POST',
			data: data,
			beforeSend: function(xhr) {
				xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
			}
		});
		return true;
	} catch (error) {
		// Silently handle error
		return false;
	}
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
		"__bocs_id": params.bocsId,
		"__bocs_collection_id": params.collectionId,
		"__bocs_frequency_id": params.frequency.id,
		"__bocs_frequency_time_unit": params.frequency.timeUnit,
		"__bocs_frequency_interval": params.frequency.frequency,
		"__bocs_frequency_discount": params.frequency.discount,
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

/**
 * Validates stock for all products in the selection
 * Updates UI based on stock availability
 * @async
 * @param {Array} products - Array of products to validate
 * @returns {Promise<boolean>} True if all products have sufficient stock
 */
async function validateProductStock(products) {
	// Get the subscription button
	const subscriptionButton = document.querySelector('.create-subscription-btn');
	const errorList = document.querySelector('.mt-2');
	
	if (!subscriptionButton || !errorList) return true; // If button not found, just return true
	
	let allProductsInStock = true;
	let errorMessages = [];
	
	// Reset error list to only show default messages
	let defaultErrors = Array.from(errorList.querySelectorAll('li'))
		.filter(item => !item.classList.contains('stock-error'))
		.map(item => item.outerHTML);
	errorList.innerHTML = defaultErrors.join('');
	
	// Check each product's stock
	for (const product of products) {
		if (!product.externalSourceId) continue;
		
		let wcProductId = product.externalSourceId;
		
		// Handle product variations if they exist
		if (product.variations && product.variations.length > 0) {
			const variationIds = await getVariationIds(product.variations);
			
			if (variationIds.length > 0) {
				wcProductId = Math.min(...variationIds);
			}
		}
		
		// Check stock before enabling button
		const stockCheckResult = await checkProductStock(wcProductId, product.quantity);
		
		if (!stockCheckResult.success) {
			allProductsInStock = false;
			
			// Add product-specific error message
			try {
				// Get product name if possible
				const productResponse = await jQuery.ajax({
					url: `/wp-json/wc/store/v1/products/${wcProductId}`,
					method: 'GET',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
					}
				});
				
				const productName = productResponse.name || `Product #${wcProductId}`;
				const errorMessage = `<li class="text-sm stock-error text-red-600">* ${productName}: ${stockCheckResult.message}</li>`;
				
				if (!errorMessages.includes(errorMessage)) {
					errorMessages.push(errorMessage);
				}
			} catch (error) {
				// If we can't get the product name, use a generic error
				const errorMessage = `<li class="text-sm stock-error text-red-600">* Product #${wcProductId}: ${stockCheckResult.message}</li>`;
				if (!errorMessages.includes(errorMessage)) {
					errorMessages.push(errorMessage);
				}
			}
		}
	}
	
	// Update UI based on stock validation
	if (allProductsInStock) {
		// Enable button if all products have sufficient stock
		subscriptionButton.disabled = false;
		subscriptionButton.classList.remove('bg-gray-400');
		subscriptionButton.classList.add('bg-teal-600', 'hover:bg-teal-700');
	} else {
		// Disable button and show error messages
		subscriptionButton.disabled = true;
		subscriptionButton.classList.remove('bg-teal-600', 'hover:bg-teal-700');
		subscriptionButton.classList.add('bg-gray-400');
		
		// Add error messages to the list
		errorList.innerHTML += errorMessages.join('');
	}
	
	return allProductsInStock;
}

/**
 * Initialize stock validation for subscription forms
 * This runs after the page loads to check stock of all products initially
 * and sets up mutation observers to validate stock when the form changes
 */
function initializeStockValidation() {
	// Wait for DOM to be fully loaded
	jQuery(document).ready(function($) {
		// Find the subscription form and button
		const subscriptionForm = document.querySelector('.create-subscription-btn')?.closest('form');
		const subscriptionButton = document.querySelector('.create-subscription-btn');
		
		if (!subscriptionForm || !subscriptionButton) {
			return;
		}
		
		// Temporarily disable the button until validation completes
		subscriptionButton.disabled = true;
		
		// Function to get selected products from the form
		const getSelectedProducts = () => {
			// This needs to be customized based on how your form structure works
			// This is just a placeholder - replace with actual logic to get products
			
			// Example pattern - replace with actual form parsing logic:
			const products = [];
			
			// Find all product inputs in the form (this is just an example)
			const productInputs = subscriptionForm.querySelectorAll('[data-product-id]');
			
			productInputs.forEach(input => {
				const productId = input.getAttribute('data-product-id');
				const quantity = parseInt(input.value || "1");
				
				if (productId) {
					products.push({
						externalSourceId: productId,
						quantity: quantity || 1
					});
				}
			});
			
			return products;
		};
		
		// Initial validation
		const validateInitialStock = async () => {
			const products = getSelectedProducts();
			if (products.length > 0) {
				await validateProductStock(products);
			}
		};
		
		// Set up event listeners for form changes
		subscriptionForm.addEventListener('change', async function(e) {
			const products = getSelectedProducts();
			if (products.length > 0) {
				await validateProductStock(products);
			}
		});
		
		// Run initial validation
		validateInitialStock();
	});
}

// Run stock validation initialization
initializeStockValidation();