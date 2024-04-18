
async function bocs_add_to_cart({ id, selectedFrequency: frequency, selectedProducts: products }) {

	console.log(frequency);

	const bocsId = id;
	const buttonCart = jQuery('div#bocs-widget button.ant-btn');

	await jQuery.ajax({
		url: '/wp-json/wc/store/v1/cart/items',
		method: 'DELETE',
		beforeSend: function (xhr) {
			xhr.setRequestHeader('Nonce', ajax_object.cartNonce);
		}
	});

	buttonCart.prop('disabled', true);
	buttonCart.html('Processing...');

	/* adding the equivalent WooCommerce Product ID of the Bocs Product */

	let wooCommerceProductId = 0;

	let bocsType = '';
	let bocsSku = '';
	let boxPrice = 0;
	let bocsName = '';

	// we will get the details first regarding the bocsid
	const bocsData = await jQuery.ajax({
		url: ajax_object.bocsGetUrl + bocsId,
		type: 'GET',
		beforeSend: function (xhr) {
			xhr.setRequestHeader("Accept", "application/json");
			xhr.setRequestHeader("Organization", ajax_object.orgId);
			xhr.setRequestHeader("Store", ajax_object.storeId);
			xhr.setRequestHeader("Authorization", ajax_object.authId);
		}
	});

	if (bocsData) {
		if (bocsData.data) {

			if (bocsData.data.name) {

				bocsType = bocsData.data.type;
				bocsSku = bocsData.data.sku;
				boxPrice = bocsData.data.boxPrice;
				bocsName = bocsData.data.name;

				// then we will search the product on WooCommerce
				const params = {
					action: 'search_product',
					nonce: ajax_object.search_nonce,   // The AJAX nonce value
					name: bocsName + ' (' + frequency.frequency + ' ' + frequency.timeUnit + ')',
					bocs_frequency_id: frequency.id, // frequency id
					bocs_bocs_id: bocsId,
					bocs_sku: bocsSku,
					is_bocs: 1
				};

				// in case that the product does not exist, then we will search according to bocs_product_id or product name
				const searchProduct = await jQuery.ajax({
					url: ajax_object.ajax_url,
					type: 'POST',
					data: params
				});

				if (searchProduct) {
					if (searchProduct > 0) {
						// there exists a product
						wooCommerceProductId = searchProduct;
					}
				}
			}
		}
	}

	// we will create the product
	if (wooCommerceProductId === 0) {

		// we will attempt to create the product
		const createdProduct = await jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'create_product',   // The AJAX action name to be handled by the server
				nonce: ajax_object.nonce,   // The AJAX nonce value
				title: bocsName + ' (' + frequency.frequency + ' ' + frequency.timeUnit + ')',        // Set the product title
				price: '0',             // Set the product price
				bocs_product_discount: frequency.discount,
				bocs_product_discount_type: frequency.discountType,
				bocs_product_interval: frequency.timeUnit,
				bocs_product_interval_count: frequency.frequency,
				sku: frequency.sku,
				bocs_frequency_id: frequency.id,
				type: 'bocs',
				bocs_bocs_id: bocsId,
				// Add more product data as needed
				bocs_type: bocsType,
				bocs_sku: bocsSku,
				bocs_price: boxPrice

			}
		});

		if (createdProduct) {

			wooCommerceProductId = createdProduct;

		} else {

			buttonCart.html('There is no WooCommerce Product found...');
			buttonCart.addClass('ant-btn-dangerous');
			buttonCart.removeAttr('disabled');
			return;
		}
	}


	if (wooCommerceProductId !== 0) {

		// add to cart

		var data = {
			id: wooCommerceProductId,
			quantity: 1,
		};

		await jQuery.ajax({
			url: '/wp-json/wc/store/v1/cart/add-item',
			method: 'POST',
			data: data,
			beforeSend: function (xhr) {
				xhr.setRequestHeader('Nonce', ajax_object.cartNonce);
			},
			success: function (response) {
				// console.success('Product added to cart:', response);
			},
			error: function (error) {
				console.error('Error adding product to cart:', error);
			},
		});
	}

	// then we will loop on the selected products

	// Loop through the products array and add each product to the cart
	for (const product of products) {

		let wcProductId = 0;

		// we will search for the product according to the bocs_product_id and title
		const searchProduct = await jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'search_product',
				nonce: ajax_object.search_nonce,   // The AJAX nonce value
				name: product.name,
				bocs_product_id: product.id,
				is_bocs: 0
			}
		});

		if (searchProduct) {
			if (searchProduct > 0) {
				// there exists a product
				wcProductId = searchProduct;
			}
		}

		if (wcProductId === 0) {

			// we will attempt to create the product
			const createdProduct = await jQuery.ajax({
				url: ajax_object.ajax_url,
				type: 'POST',
				data: {
					action: 'create_product',   // The AJAX action name to be handled by the server
					nonce: ajax_object.nonce,   // The AJAX nonce value
					title: product.name,        // Set the product title
					price: product.price,             // Set the product price
					sku: product.sku,
					bocs_product_id: product.id,
					type: 'product'
					// Add more product data as needed
				}
			});

			if (createdProduct) {
				wcProductId = createdProduct;
			} else {

				buttonCart.html('There is no WooCommerce Product found...');
				buttonCart.addClass('ant-btn-dangerous');
				buttonCart.removeAttr('disabled');
				return;
			}

		}

		if (wcProductId !== 0) {

			// before adding to cart, we will check if it has variations
			if (product.variations.length > 0) {
				console.error('Error adding product to cart. Product with Variations is NOT WORKING as of now...');
				// buttonCart.html('Product with Variations is NOT WORKING as of now...');
			}

			/* if( product.variations.length > 0 ){
				// wcProductId = product.variations[0].externalSourceId;

				// search first for each of the variations if there is already an existing tied record
				let variationId = 0;
				let selectedVariation = false;

				for (const variation of product.variations) {

					const searchVariation = await jQuery.ajax({
						url: ajax_object.ajax_url,
						type: 'POST',
						data: {
							action: 'search_product',
							nonce: ajax_object.search_nonce,
							name: product.name,
							bocs_product_id: variation.id,
							is_bocs: 0
						}
					});

					if (searchVariation){
						if (searchVariation > 0){
							// there exists a product
							variationId = searchVariation;
						}
					}

					if (variationId !== 0 && selectedVariation === false){
						wcProductId = variationId;
						selectedVariation = true;
					}

					if( variationId === 0 ){
						// we will create this product variation
						const createdVariation = await jQuery.ajax({
							url: ajax_object.ajax_url,
							type: 'POST',
							data: {
								action: 'create_product',   // The AJAX action name to be handled by the server
								nonce: ajax_object.nonce,   // The AJAX nonce value
								title: product.name,        // Set the product title
								price: product.price,             // Set the product price
								sku: product.sku,
								bocs_product_id: variation.id,
								type: 'variation',
								parent_id: product.id,
								option: variation.option
								// Add more product data as needed
							}
						});
					}
				}
			} */

			// add to cart

			var data = {
				id: wcProductId,
				quantity: product.quantity,
			};

			await jQuery.ajax({
				url: '/wp-json/wc/store/v1/cart/add-item',
				method: 'POST',
				data: data,
				beforeSend: function (xhr) {
					xhr.setRequestHeader('Nonce', ajax_object.cartNonce);
				},
				success: function (response) {
					// console.log('Product added to cart:', response);
				},
				error: function (error) {
					console.error('Error adding product to cart:', error);
				},
			});
		}

		// we will update the woocommerce product in case the bocs_product_id was not in the meta
		if (wcProductId !== 0 && product.id) {
			if (product.id !== "") {
				// we will try to update the bocs_product_id on the meta key
				await jQuery.ajax({
					url: ajax_object.ajax_url,
					type: 'POST',
					data: {
						action: 'update_product',   // The AJAX action name to be handled by the server
						nonce: ajax_object.update_product_nonce,   // The AJAX nonce value
						id: wcProductId,
						bocs_product_id: product.id
					}
				});
			}
		}

	}

	// then we will try to add the coupon if there is a discount
	if (frequency.discount > 0) {

		let discountType = "percent";
		const amount = frequency.discount;
		let couponCode = "bocs-" + amount;

		if (frequency.discountType === 'dollar') {
			discountType = "fixed_cart";
		}

		if (discountType === "percent") {
			couponCode = couponCode + "percent";
		} else {
			couponCode = couponCode + "off";
		}

		couponCode = couponCode + "-" + (Math.random() + 1).toString(36).substring(7);
		const now = Date.now();
		couponCode = couponCode + "-" + now;

		// then we will try to add this coupon
		var data = {
			action: 'create_coupon',
			nonce: ajax_object.couponNonce,
			coupon_code: couponCode,
			discount_type: discountType,
			amount: amount
		}

		const createdCoupon = await jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'create_coupon',
				nonce: ajax_object.couponNonce,
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
					xhr.setRequestHeader('Nonce', ajax_object.cartNonce);
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
	window.location.href = ajax_object.cartURL;
}