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

	// console.log(params);

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
	
	// Get error display container
	const errorList = document.querySelector('.mt-2');
	
	try {
		// Validate products stock and purchasability before proceeding
		const productsValid = await validateProductStock(products);
		if (!productsValid) {
			buttonCart.prop('disabled', false);
			buttonCart.html('Add to Cart');
			return; // Don't proceed if validation fails
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
		} else {
			// Handle case where products couldn't be added
			buttonCart.prop('disabled', false);
			buttonCart.html('Add to Cart');
			
			// Display error if errorList exists
			if (errorList) {
				const errorMessage = `<li class="text-sm product-error text-red-600">* One or more products could not be added to the cart</li>`;
				// Check if the error already exists
				if (!errorList.innerHTML.includes(errorMessage)) {
					errorList.innerHTML += errorMessage;
				}
			}
		}
	} catch (error) {
		// Handle errors that may occur during the process
		buttonCart.prop('disabled', false);
		buttonCart.html('Add to Cart');
		
		// Display error if errorList exists
		if (errorList) {
			let errorMessage = "";
			
			// Try to parse the error response for more detailed information
			if (error.responseJSON && error.responseJSON.message) {
				// Extract error message from API response
				const message = error.responseJSON.message.replace(/&quot;/g, '"');
				errorMessage = `<li class="text-sm product-error text-red-600">* ${message}</li>`;
			} else {
				// Generic error message as fallback
				errorMessage = `<li class="text-sm product-error text-red-600">* There was an error processing your request. Please try again.</li>`;
			}
			
			// Check if the error already exists
			if (!errorList.innerHTML.includes(errorMessage)) {
				errorList.innerHTML += errorMessage;
			}
		}
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
	const errorList = document.querySelector('.mt-2');
	
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
			if (!addResult.success) {
				allSuccess = false;
				
				// Add error to the error list if it exists
				if (errorList) {
					const errorMessage = `<li class="text-sm product-error text-red-600">* ${addResult.errorMessage}</li>`;
					// Check if error already exists
					if (!errorList.innerHTML.includes(errorMessage)) {
						errorList.innerHTML += errorMessage;
					}
				}
				
				// Update button text to show error temporarily
				buttonCart.html('Error: Could not add product');
				setTimeout(() => {
					buttonCart.html('Add to Cart');
					buttonCart.prop('disabled', false);
				}, 3000);
				return false; // Stop processing if any product fails
			}
		} else {
			allSuccess = false;
			
			// Add error to the error list if it exists
			if (errorList) {
				const errorMessage = `<li class="text-sm stock-error text-red-600">* ${stockCheckResult.message}</li>`;
				// Check if error already exists
				if (!errorList.innerHTML.includes(errorMessage)) {
					errorList.innerHTML += errorMessage;
				}
			}
			
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
 * @returns {Promise<Object>} Result object with success status and error details if applicable
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
		const response = await jQuery.ajax({
			url: '/wp-json/wc/store/v1/cart/add-item',
			method: 'POST',
			data: data,
			beforeSend: function(xhr) {
				xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
			}
		});
		return { success: true, response };
	} catch (error) {
		// Get product name for better error reporting
		let productName = `Product #${productId}`;
		try {
			const productResponse = await jQuery.ajax({
				url: `/wp-json/wc/store/v1/products/${productId}`,
				method: 'GET',
				beforeSend: function(xhr) {
					if (window.bocsAjaxObject && bocsAjaxObject.cartNonce) {
						xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
					}
				}
			});
			
			if (productResponse && productResponse.name) {
				productName = productResponse.name;
			}
		} catch (nameError) {
			// Silently handle error getting product name
		}
		
		// Parse error message if available
		let errorMessage = `Could not add ${productName} to cart`;
		if (error.responseJSON && error.responseJSON.message) {
			errorMessage = error.responseJSON.message.replace(/&quot;/g, '"');
		}
		
		return { 
			success: false, 
			error,
			productName,
			errorMessage
		};
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
	// Get the subscription button and error containers
	const subscriptionButton = document.querySelector('.create-subscription-btn');
	const errorList = document.querySelector('.mt-2');
	const subscriptionErrorList = document.querySelector('.subscription-errors');
	
	if (!subscriptionButton || !errorList) return true;
	
	let allProductsValid = true;
	let errorMessages = [];
	let unavailableProducts = [];
	
	// Reset error lists
	let defaultErrors = Array.from(errorList.querySelectorAll('li'))
		.filter(item => !item.classList.contains('stock-error') && !item.classList.contains('product-error'))
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
		
		// Get the product item element
		const productItem = document.querySelector(`[data-product-id="${wcProductId}"]`)?.closest('.product-list-item');
		
		// Try to get product information
		try {
			const productResponse = await jQuery.ajax({
				url: `/wp-json/wc/store/v1/products/${wcProductId}`,
				method: 'GET',
				beforeSend: function(xhr) {
					if (window.bocsAjaxObject && bocsAjaxObject.cartNonce) {
						xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
					}
				}
			});
			
			const productName = productResponse.name || `Product #${wcProductId}`;
			
			// Check if product is available for purchase
			if (!productResponse.is_purchasable) {
				allProductsValid = false;
				const errorMessage = `<li class="text-sm product-error text-red-600">* ${productName} is not available for purchase (may be in draft status)</li>`;
				unavailableProducts.push(productName);
				
				if (!errorMessages.includes(errorMessage)) {
					errorMessages.push(errorMessage);
				}
				
				// Disable product controls
				if (productItem) {
					disableProductControls(productItem, "Product not available");
				}
				
				continue;
			}
			
			// Check if product is in stock
			if (!productResponse.is_in_stock) {
				allProductsValid = false;
				const errorMessage = `<li class="text-sm stock-error text-red-600">* ${productName} is out of stock</li>`;
				unavailableProducts.push(productName);
				
				if (!errorMessages.includes(errorMessage)) {
					errorMessages.push(errorMessage);
				}
				
				// Disable product controls
				if (productItem) {
					disableProductControls(productItem, "Out of stock");
				}
				
				continue;
			}
			
			// Check stock quantity if available
			if (productResponse.has_options === false) {
				let stockQuantity = productResponse.stock_quantity;
				
				// If stock quantity is undefined, fetch it from product meta
				if (stockQuantity === undefined || stockQuantity === null) {
					try {
						const metaResponse = await jQuery.ajax({
							url: bocsAjaxObject.ajax_url,
							method: 'POST',
							data: {
								action: 'get_product_stock',
								product_id: wcProductId,
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
					if (stockQuantity < product.quantity) {
						allProductsValid = false;
						const errorMessage = `<li class="text-sm stock-error text-red-600">* ${productName}: Only ${stockQuantity} available</li>`;
						unavailableProducts.push(`${productName} (Limited stock: ${stockQuantity})`);
						
						if (!errorMessages.includes(errorMessage)) {
							errorMessages.push(errorMessage);
						}
						
						// Update product controls to show limited stock
						if (productItem) {
							updateProductStockLimit(productItem, stockQuantity);
						}
					}
				}
			}
			
			// Enable product controls if everything is valid
			if (productItem && !unavailableProducts.includes(productName)) {
				enableProductControls(productItem);
			}
			
		} catch (error) {
			// Handle API errors
			allProductsValid = false;
			let errorMessage = "";
			
			// Try to parse the error response
			if (error.responseJSON && error.responseJSON.message) {
				const message = error.responseJSON.message.replace(/&quot;/g, '"');
				errorMessage = `<li class="text-sm product-error text-red-600">* ${message}</li>`;
				unavailableProducts.push(`Product #${wcProductId} (Not found)`);
			} else {
				errorMessage = `<li class="text-sm product-error text-red-600">* Product #${wcProductId} cannot be purchased at this time</li>`;
				unavailableProducts.push(`Product #${wcProductId} (Unavailable)`);
			}
			
			if (!errorMessages.includes(errorMessage)) {
				errorMessages.push(errorMessage);
			}
			
			// Disable product controls
			if (productItem) {
				disableProductControls(productItem, "Product not found");
			}
		}
	}
	
	// Update UI based on validation
	updateSubscriptionUI(allProductsValid, errorMessages, unavailableProducts);
	
	return allProductsValid;
}

/**
 * Disables product controls and shows error message
 * @param {Element} productItem - Product list item element
 * @param {string} message - Error message to display
 */
function disableProductControls(productItem, message) {
	// Disable quantity input
	const quantityInput = productItem.querySelector('input[name="quantity"]');
	if (quantityInput) {
		quantityInput.value = "0";
		quantityInput.disabled = true;
	}
	
	// Disable plus/minus buttons
	const buttons = productItem.querySelectorAll('button');
	buttons.forEach(button => {
		button.disabled = true;
		button.classList.remove('bg-teal-600', 'hover:bg-teal-500');
		button.classList.add('bg-gray-400');
	});
	
	// Add error message
	let errorDiv = productItem.querySelector('.product-error-message');
	if (!errorDiv) {
		errorDiv = document.createElement('div');
		errorDiv.className = 'product-error-message text-sm text-red-600 mt-2';
		productItem.querySelector('.shrink-0').appendChild(errorDiv);
	}
	errorDiv.textContent = message;
}

/**
 * Enables product controls and removes error message
 * @param {Element} productItem - Product list item element
 */
function enableProductControls(productItem) {
	// Enable quantity input
	const quantityInput = productItem.querySelector('input[name="quantity"]');
	if (quantityInput) {
		quantityInput.disabled = false;
	}
	
	// Enable buttons
	const buttons = productItem.querySelectorAll('button');
	buttons.forEach(button => {
		button.disabled = false;
		button.classList.remove('bg-gray-400');
		if (button.classList.contains('rounded-full')) {
			button.classList.add('bg-teal-600', 'hover:bg-teal-500');
		}
	});
	
	// Remove error message
	const errorDiv = productItem.querySelector('.product-error-message');
	if (errorDiv) {
		errorDiv.remove();
	}
}

/**
 * Updates product controls to show stock limit
 * @param {Element} productItem - Product list item element
 * @param {number} stockLimit - Maximum available stock
 */
function updateProductStockLimit(productItem, stockLimit) {
	const quantityInput = productItem.querySelector('input[name="quantity"]');
	if (quantityInput) {
		quantityInput.max = stockLimit;
		if (parseInt(quantityInput.value) > stockLimit) {
			quantityInput.value = stockLimit;
		}
	}
	
	// Add stock limit message
	let errorDiv = productItem.querySelector('.product-error-message');
	if (!errorDiv) {
		errorDiv = document.createElement('div');
		errorDiv.className = 'product-error-message text-sm text-orange-600 mt-2';
		productItem.querySelector('.shrink-0').appendChild(errorDiv);
	}
	errorDiv.textContent = `Limited stock: ${stockLimit} available`;
}

/**
 * Updates subscription UI based on validation results
 * @param {boolean} isValid - Whether all products are valid
 * @param {Array} errorMessages - List of error messages
 * @param {Array} unavailableProducts - List of unavailable products
 */
function updateSubscriptionUI(isValid, errorMessages, unavailableProducts) {
	const subscriptionButton = document.querySelector('.create-subscription-btn');
	const errorList = document.querySelector('.mt-2');
	
	// Update button state
	if (subscriptionButton) {
		if (isValid) {
			subscriptionButton.disabled = false;
			subscriptionButton.classList.remove('bg-gray-400');
			subscriptionButton.classList.add('bg-teal-600', 'hover:bg-teal-700');
		} else {
			subscriptionButton.disabled = true;
			subscriptionButton.classList.remove('bg-teal-600', 'hover:bg-teal-700');
			subscriptionButton.classList.add('bg-gray-400');
		}
	}
	
	// Update error messages
	if (errorList) {
		// Add product availability errors
		errorList.innerHTML += errorMessages.join('');
		
		// Add subscription-specific errors
		if (unavailableProducts.length > 0) {
			errorList.innerHTML += `<li class="text-sm text-red-600">*Cannot proceed: Some products are unavailable</li>`;
		}
	}
}

/**
 * Initializes each product in the list
 * @param {Element} widget - The BOCS widget element
 */
async function initializeProducts(widget) {
	const productItems = widget.querySelectorAll('.product-list-item');
	const productChecks = [];

	for (const item of productItems) {
		productChecks.push(validateProductItem(item));
	}

	// Wait for all product validations to complete
	await Promise.all(productChecks);
}

/**
 * Validates a single product item
 * @param {Element} productItem - The product list item element
 */
async function validateProductItem(productItem) {
	try {
		// Get product ID from the item
		const productId = productItem.querySelector('[data-product-id]')?.dataset.productId;
		if (!productId) return;

		// Get product name
		const productName = productItem.querySelector('h3')?.textContent?.trim() || `Product #${productId}`;

		// Check product status
		const response = await jQuery.ajax({
			url: `/wp-json/wc/store/v1/products/${productId}`,
			method: 'GET',
			beforeSend: function(xhr) {
				if (window.bocsAjaxObject && bocsAjaxObject.cartNonce) {
					xhr.setRequestHeader('Nonce', bocsAjaxObject.cartNonce);
				}
			}
		});

		// Handle different product states
		if (!response.is_purchasable) {
			disableProductControls(productItem, "Product not available");
			addProductError(productItem, `${productName} is not available for purchase`);
			return;
		}

		if (!response.is_in_stock) {
			disableProductControls(productItem, "Out of stock");
			addProductError(productItem, `${productName} is out of stock`);
			return;
		}

		// Check stock quantity if available
		if (response.has_options === false && response.stock_quantity !== null) {
			if (response.stock_quantity <= 0) {
				disableProductControls(productItem, "Out of stock");
				addProductError(productItem, `${productName} is out of stock`);
				return;
			}

			if (response.stock_quantity < 10) { // Example threshold
				updateProductStockLimit(productItem, response.stock_quantity);
				addProductWarning(productItem, `Only ${response.stock_quantity} available`);
				return;
			}
		}

		// If we get here, product is available
		enableProductControls(productItem);
		
	} catch (error) {
		// Handle product not found or API error
		disableProductControls(productItem, "Product not found");
		addProductError(productItem, "This product is not available");
	}
}

/**
 * Adds an error message to the product item
 * @param {Element} productItem - The product list item element
 * @param {string} message - Error message to display
 */
function addProductError(productItem, message) {
	const errorContainer = getOrCreateMessageContainer(productItem, 'product-error');
	errorContainer.innerHTML = `<p class="text-sm text-red-600">${message}</p>`;
	errorContainer.classList.add('mt-2', 'text-center');
}

/**
 * Adds a warning message to the product item
 * @param {Element} productItem - The product list item element
 * @param {string} message - Warning message to display
 */
function addProductWarning(productItem, message) {
	const warningContainer = getOrCreateMessageContainer(productItem, 'product-warning');
	warningContainer.innerHTML = `<p class="text-sm text-orange-600">${message}</p>`;
	warningContainer.classList.add('mt-2', 'text-center');
}

/**
 * Gets or creates a message container in the product item
 * @param {Element} productItem - The product list item element
 * @param {string} className - Class name for the container
 * @returns {Element} The message container element
 */
function getOrCreateMessageContainer(productItem, className) {
	let container = productItem.querySelector(`.${className}`);
	if (!container) {
		container = document.createElement('div');
		container.className = className;
		// Insert after the quantity controls
		const quantityControls = productItem.querySelector('.shrink-0');
		quantityControls.parentNode.insertBefore(container, quantityControls.nextSibling);
	}
	return container;
}

/**
 * Waits for the BOCS widget to be fully loaded
 * @returns {Promise} Resolves when widget is loaded
 */
function waitForBocsWidget() {
	return new Promise((resolve) => {
		// Check if widget already exists and is fully loaded
		const checkWidget = () => {
			const widget = document.querySelector('div#bocs-widget');
			if (widget) {
				// Check for essential child elements
				const hasProducts = widget.querySelector('.product-list');
				const hasSubscriptionButton = widget.querySelector('.create-subscription-btn');
				const hasQuantityInputs = widget.querySelectorAll('input[name="quantity"]').length > 0;
				
				if (hasProducts && hasSubscriptionButton && hasQuantityInputs) {
					resolve(widget);
					return true;
				}
			}
			return false;
		};
		
		// If widget is not ready, set up observer
		if (!checkWidget()) {
			const observer = new MutationObserver((mutations, obs) => {
				if (checkWidget()) {
					obs.disconnect(); // Stop observing once widget is ready
				}
			});
			
			// Start observing document for widget load
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
			
			// Fallback timeout after 10 seconds
			setTimeout(() => {
				observer.disconnect();
				resolve(null); // Resolve with null if widget doesn't load
			}, 10000);
		}
	});
}

/**
 * Initialize stock validation for subscription forms
 */
async function initializeStockValidation() {
	try {
		// Wait for widget to be loaded
		const widget = await waitForBocsWidget();
		
		if (!widget) {
			console.warn('BOCS widget did not load within timeout period');
			return;
		}

		// Initialize all products first
		await initializeProducts(widget);
		
		// Find the subscription form and button
		const subscriptionForm = widget.querySelector('.create-subscription-btn')?.closest('form');
		const subscriptionButton = widget.querySelector('.create-subscription-btn');
		
		if (!subscriptionForm || !subscriptionButton) {
			return;
		}
		
		// Temporarily disable the button until validation completes
		subscriptionButton.disabled = true;

		// Function to get selected products from the form
		const getSelectedProducts = () => {
			const products = [];
			
			// Find all product inputs in the form
			const productItems = widget.querySelectorAll('.product-list-item');
			
			productItems.forEach(item => {
				const productId = item.querySelector('[data-product-id]')?.dataset.productId;
				const quantityInput = item.querySelector('input[name="quantity"]');
				const quantity = quantityInput ? parseInt(quantityInput.value || "0") : 0;
				
				if (productId && quantity > 0) {
					products.push({
						externalSourceId: productId,
						quantity: quantity
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
		
		// Set up mutation observer for dynamic changes
		const observer = new MutationObserver(async (mutations) => {
			const products = getSelectedProducts();
			if (products.length > 0) {
				await validateProductStock(products);
			}
		});
		
		// Observe changes to quantity inputs and product list
		observer.observe(widget.querySelector('.product-list'), {
			subtree: true,
			attributes: true,
			attributeFilter: ['value', 'disabled'],
			childList: true
		});
		
		// Run initial validation
		await validateInitialStock();
		
	} catch (error) {
		console.error('Error initializing stock validation:', error);
	}
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializeStockValidation);
} else {
	initializeStockValidation();
}