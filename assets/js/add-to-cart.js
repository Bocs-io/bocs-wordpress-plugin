
async function bocs_add_to_cart(products, frequency) {

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
		buttonCart.html('There is no WooCommerce Product found...');
		buttonCart.addClass('ant-btn-dangerous');
		buttonCart.removeAttr('disabled');
		return;

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

		if (wcProductId === 0){
			buttonCart.html('There is no WooCommerce Product found for ' + product.name);
			buttonCart.addClass('ant-btn-dangerous');
			buttonCart.removeAttr('disabled');
			return;

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
	// window.location.href = ajax_object.cartURL;
}