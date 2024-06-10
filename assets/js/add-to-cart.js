	
async function bocs_add_to_cart({ bocsId:id, collectionId, selectedFrequency: frequency, selectedProducts: products }) {

	let bocsFrequencyId = frequency.id;
	
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

		let wcProductId = 0;

		// we will search for the product according to the bocs_product_id and title
		const searchProduct = await jQuery.ajax({
			url: bocsAjaxObject.ajax_url,
			type: 'POST',
			data: {
				action: 'search_product',
				nonce: bocsAjaxObject.search_nonce,   // The AJAX nonce value
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
				url: bocsAjaxObject.ajax_url,
				type: 'POST',
				data: {
					action: 'create_product',   // The AJAX action name to be handled by the server
					nonce: bocsAjaxObject.nonce,   // The AJAX nonce value
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
	if(collectionId == null) collectionId = '';
	// create cookie
	document.cookie = "__bocs_id="+id+"; path=/";
	if( collectionId != '' ) document.cookie = "__bocs_collection_id="+collectionId+"; path=/";
	document.cookie = "__bocs_frequency_id="+bocsFrequencyId+"; path=/";
	console.log(id, collectionId, bocsFrequencyId);
	window.location.href = bocsAjaxObject.cartURL+'?bocs='+id+'&collection='+collectionId+'&frequency='+bocsFrequencyId;
}

function getCookie(cname) {
  let name = cname + "=";
  let decodedCookie = decodeURIComponent(document.cookie);
  let ca = decodedCookie.split(';');
  for(let i = 0; i <ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) == ' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}