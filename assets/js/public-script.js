var symbl = '';

jQuery(
	function ( $ ) {
		$( '#place_order' ).on(
			'click',
			function (e) {
				e.preventDefault();
			}
		);

		$( '#billing_first_name,#billing_last_name,#billing_city,#billing_address_1,#billing_address_2,#billing_postcode,#billing_phone,#billing_email' ).on(
			'input',
			function () {
				shippo_request();
			}
		);

		$( '#billing_country, #billing_state' ).on(
			'change',
			function () {
				shippo_request();
			}
		);

		function shippo_request(){
			var retrn = '';
			fname     = $( '#billing_first_name' ).val() || '';
			lname     = $( '#billing_last_name' ).val() || '';
			cntry     = $( '#billing_country' ).val() || '';
			state     = $( '#billing_state' ).val() || '';
			city      = $( '#billing_city' ).val() || '';
			adder     = $( '#billing_address_1' ).val() || '';
			adder2    = $( '#billing_address_2' ).val() || '';
			zipcd     = $( '#billing_postcode' ).val() || '';
			phone     = $( '#billing_phone' ).val() || '';
			email     = $( '#billing_email' ).val() || '';

			if (fname.length < 2 || lname.length < 2 || cntry.length < 2 || state.length < 2 || city.length < 2 ||
				adder.length < 2 || zipcd.length < 2 || phone.length < 2 || email.length < 2) {
					return;
			}

			$.ajax(
				{
					url: shippo.ajaxurl,
					type: "POST",
					data: {action:'shippo_get_shipping',fname:fname,lname:lname,cntry:cntry,state:state,city:city,adder:adder,adder2:adder2,zipcd:zipcd,phone:phone,email:email,nonce:shippo.nonce},
					success: function (data, textStatus, jqXHR) {
					},
				}
			);
		}

		$(document).on('click', '.shippo-show-history', function(e){
			e.preventDefault();

			let $link = $(this);
			let $historyBox = $('.shippo-history-box');

			$historyBox.slideToggle(200);
			$link.toggleClass('open');
		});

	}
);