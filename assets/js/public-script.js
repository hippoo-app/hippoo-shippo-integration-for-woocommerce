jQuery(function ( $ ) {

	function shippo_request() {
		fname  = shippo_getFieldValue('first_name');
		lname  = shippo_getFieldValue('last_name');
		cntry  = shippo_getFieldValue('country');
		state  = shippo_getFieldValue('state');
		city   = shippo_getFieldValue('city');
		adder  = shippo_getFieldValue('address_1');
		adder2 = shippo_getFieldValue('address_2');
		zipcd  = shippo_getFieldValue('postcode');
		phone  = shippo_getFieldValue('phone');
		email  = shippo_getFieldValue('email');

		if (fname.length < 2 || lname.length < 2 || cntry.length < 2 || state.length < 2 || city.length < 2 ||
			adder.length < 2 || zipcd.length < 2 || phone.length < 2 || email.length < 2) {
				return;
		}

		$.ajax({
			url: shippo.ajaxurl,
			type: "POST",
			data: {
				action: 'shippo_get_shipping',
				fname: fname,
				lname: lname,
				cntry: cntry,
				state: state,
				city: city,
				adder: adder,
				adder2: adder2,
				zipcd: zipcd,
				phone: phone,
				email: email,
				nonce: shippo.nonce
			},
			success: function (response) {
				$(document.body).trigger('updated_checkout');
			},
		});
	}

	function shippo_getFieldValue(fieldName) {
		let value = $(`#billing_${fieldName}`).val();
		if (!value) value = $(`#billing-${fieldName}`).val();
		if (!value) value = $(`#shipping_${fieldName}`).val();
		if (!value) value = $(`#shipping-${fieldName}`).val();
		if (!value) value = $(`#${fieldName}`).val();
		return value || '';
	}

	$(document).on('click', '.shippo-show-history', function(e){
		e.preventDefault();

		let $link = $(this);
		let $historyBox = $('.shippo-history-box');

		$historyBox.slideToggle(200);
		$link.toggleClass('open');
	});

	setTimeout(() => {
		if ($('.woocommerce-checkout').length || $('.wc-block-checkout').length) {
			shippo_request();
		}
	}, 1000);

	$(document).on('change input', '#billing_first_name, #billing_last_name, #billing_city, #billing_address_1, #billing_address_2, #billing_postcode, #billing_phone, #billing_email, #billing_country, #billing_state', shippo_request);

	$(document).on('change input', '.wc-block-checkout__shipping-fields, .wc-block-checkout__billing-fields', shippo_request);

});