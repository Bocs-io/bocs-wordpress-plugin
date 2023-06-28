
async function bocs_add_to_cart(products, frequency) {

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

	let bocsType = '';
	let bocsSku = '';
	let boxPrice = 0;
	let bocsName = '';
	const bocsId = jQuery("div#bocs-widget").data("id");

	// in case that there is none, then show warning/notification
	if (wooCommerceProductId === 0){

		if(bocsId){
			if (bocsId !== ""){

				// we will get the details first regarding the bocsid
				const bocsData = await jQuery.ajax({
					url: ajax_object.bocsGetUrl + jQuery("div#bocs-widget").data("id"),
					type: 'GET',
					beforeSend: function (xhr) {
						xhr.setRequestHeader("Accept", "application/json");
						xhr.setRequestHeader("Organization", ajax_object.orgId);
						xhr.setRequestHeader("Store", ajax_object.storeId);
						xhr.setRequestHeader("Authorization", ajax_object.authId);
					}
				});

				if (bocsData){
					if (bocsData.data){

						if ( bocsData.data.name){

							bocsType = bocsData.data.type;
							bocsSku = bocsData.data.sku;
							boxPrice = bocsData.data.boxPrice;
							bocsName = bocsData.data.name;

							// then we will search the product on WooCommerce

							// in case that the product does not exist, then we will search according to bocs_product_id or product name
							const searchProduct = await jQuery.ajax({
								url: ajax_object.ajax_url,
								type: 'POST',
								data: {
									action: 'search_product',
									nonce: ajax_object.search_nonce,   // The AJAX nonce value
									name: bocsName +  '(' + frequency.frequency + ' ' + frequency.timeUnit + ')',
									bocs_frequency_id: frequency.id, // frequency id
									bocs_bocs_id: bocsId,
									bocs_sku: bocsSku,
									is_bocs: 1
								}
							});

							if (searchProduct){
								if (searchProduct > 0){
									// there exists a product
									wooCommerceProductId = searchProduct;
								}
							}
						}
					}
				}
			}
		}

	}

	// we will create the product
	if (wooCommerceProductId === 0){

		// we will attempt to create the product
		const createdProduct = await jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'create_product',   // The AJAX action name to be handled by the server
				nonce: ajax_object.nonce,   // The AJAX nonce value
				title: bocsName +  '(' + frequency.frequency + ' ' + frequency.timeUnit + ')',        // Set the product title
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

		if (createdProduct){

			wooCommerceProductId = createdProduct;

		} else {

			buttonCart.html('There is no WooCommerce Product found...');
			buttonCart.addClass('ant-btn-dangerous');
			buttonCart.removeAttr('disabled');
			return;
		}
	}


	if (wooCommerceProductId !== 0){
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
			// we will search for the product according to the bocs_product_id and title
			const searchProduct = await jQuery.ajax({
				url: ajax_object.ajax_url,
				type: 'POST',
				data: {
					action: 'search_product',
					nonce: ajax_object.search_nonce,   // The AJAX nonce value
					name: product.name,
					bocs_product_id: product.productId,
					is_bocs: 0
				}
			});

			if (searchProduct){
				if (searchProduct > 0){
					// there exists a product
					wcProductId = searchProduct;
				}
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
					bocs_product_id: product.productId,
					type: 'product'
					// Add more product data as needed
				}
			});

			if (createdProduct){
				wcProductId = createdProduct;
			} else {

				buttonCart.html('There is no WooCommerce Product found...');
				buttonCart.addClass('ant-btn-dangerous');
				buttonCart.removeAttr('disabled');
				return;
			}

		}

		if (wcProductId != 0){
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