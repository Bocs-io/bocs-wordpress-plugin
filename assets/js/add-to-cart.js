
async function bocs_add_to_cart(products, frequency) {

	console.log('products', products);
	console.log('frequency', frequency);

	const buttonCart = jQuery('div#bocs-widget button.ant-btn');

	console.log('button', buttonCart );

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

	if (frequency.externalSourceId){
		wooCommerceProductId = frequency.externalSourceId;
	} else if(frequency.sku) {
		// search by sku
		const bocsProduct = await jQuery.ajax(
			{
				url: '/wp-json/wc/store/v1/products?sku=' + encodeURIComponent(frequency.sku) ,
				method: 'GET',
				data: data,
				beforeSend: function (xhr) {
					xhr.setRequestHeader('Nonce', ajax_object.cartNonce);
				}
			}
		);

		if( bocsProduct.length ){
			wooCommerceProductId = bocsProduct[0].id;
		}

	}

	// in case that there is none, then show warning/notification
	if (wooCommerceProductId === 0){

		// we will attempt to create the product
		const createdProduct = await jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'create_product',   // The AJAX action name to be handled by the server
				nonce: ajax_object.nonce,   // The AJAX nonce value
				title: frequency.sku,        // Set the product title
				price: '0',             // Set the product price
				bocs_product_discount: frequency.discount,
				bocs_product_discount_type: frequency.discountType,
				bocs_product_interval: frequency.timeUnit,
				bocs_product_interval_count: frequency.frequency,
				sku: frequency.sku,
				bocs_id: frequency.id,
				type: 'bocs',
				bocs_bocs_id: jQuery("div#bocs-widget").data("id")
				// Add more product data as needed
			}
		});

		if (createdProduct){
			var data = {
				id: createdProduct,
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
					console.log('Product added to cart:', response);
				},
				error: function (error) {
					console.log('Error adding product to cart:', error);
				},
			});

		} else {

			buttonCart.html('There is no WooCommerce Product found...');
			buttonCart.addClass('ant-btn-dangerous');
			buttonCart.removeAttr('disabled');
			return;
		}

	} else {
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
				console.log('Product added to cart:', response);
			},
			error: function (error) {
				console.log('Error adding product to cart:', error);
			},
		});
	}

	// then we will loop on the selected products

	// Loop through the products array and add each product to the cart
	for (const product of products) {

		let wcProductId = 0;

		if (product.externalSourceId && product.externalSourceId !== ""){
			wcProductId = product.externalSourceId;
		} else if (product.sku && product.sku !== "") {
			// we will check via sku
			const wooProduct = await jQuery.ajax(
				{
					url: '/wp-json/wc/store/v1/products?sku=' + encodeURIComponent(product.sku) ,
					method: 'GET',
					data: data,
					beforeSend: function (xhr) {
						xhr.setRequestHeader('Nonce', ajax_object.cartNonce);
					}
				}
			);

			if( wooProduct.length ){
				wcProductId = wooProduct[0].id;
			}
		}

		if (wcProductId == 0){

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
					bocs_id: product.productId,
					type: 'product'
					// Add more product data as needed
				}
			});

			if (createdProduct){
				var data = {
					id: createdProduct,
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
						console.log('Product added to cart:', response);
					},
					error: function (error) {
						console.log('Error adding product to cart:', error);
					},
				});

			} else {

				buttonCart.html('There is no WooCommerce Product found...');
				buttonCart.addClass('ant-btn-dangerous');
				buttonCart.removeAttr('disabled');
				return;
			}

		} else {
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
					console.log('Product added to cart:', response);
				},
				error: function (error) {
					console.log('Error adding product to cart:', error);
				},
			});
		}

	}

	buttonCart.html('Redirecting to Cart...');
	window.location.href = ajax_object.cartURL;
}