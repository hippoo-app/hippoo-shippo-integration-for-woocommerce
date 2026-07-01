jQuery( document ).ready(
	function () {
		jQuery('select.country_select, select.state_select').select2();

		jQuery( '.pkchk' ).click(
			function () {
				if (jQuery( this ).is( ':checked' )) {
					jQuery( '.pkchk' ).not( this ).prop( 'checked', false );
				}
			}
		);

		jQuery(document).on('input','.number',function () {
			this.value = this.value
				.replace(/[^0-9.]/g, '')
				.replace(/^\./, '0.')
				.replace(/(\..*?)\./g, '$1')
				.replace(/^0+(?=\d)/, '')
				.replace(/^$/, '0')
		});

		/**
		 * add ajax state for country dropdown list
		 */
		jQuery(document).on('change', '.country_select', function () {
			var $wrapper = jQuery(this).closest('fieldset, td, .item');
			var $stateFieldset = $wrapper.parent().find('.state_select').closest('.sh_select2, td, fieldset');

			var country = jQuery(this).val();
			
			$stateFieldset.find('.state_select').prop('disabled', true);

			jQuery.post(
				shippo.ajaxurl,
				{action:'ship_state_list',country:country,nonce:shippo.nonce},
				function (resp) {
					if (resp.status == 1 && resp.data.includes('<option')) {
						$stateFieldset.empty().html(
							'<select name="state" class="state_select form-control">' + resp.data + '</select>'
						);
						$stateFieldset.find( '.state_select' ).select2();
					} else {
						$stateFieldset.empty().html(
							'<input type="text" name="state" class="state_select form-control" value=""/>'
						);
					}
					$stateFieldset.find( '.state_select' ).prop( 'disabled', false );
				}
			);
		});

		jQuery(document).on('click', '.shippo-admin-tracking', function(e){
			e.preventDefault();

			let orderId = jQuery(this).data('id');

			tb_show(
				"Shipping updates",
				"#TB_inline?width=600&height=400&inlineId=shippo-admin-track-" + orderId
			);
		});
	}
);

jQuery(
	function ( $ ) {

		function alert_msg(msg) {
			if (Array.isArray(msg) && msg.every(item => typeof item === 'object' && item !== null)) {
				let errorMessages = msg.flatMap(obj => 
					Object.entries(obj).map(([key, value]) => `${key}: ${value.join(', ')}`)
				).join('\n');
				alert(errorMessages);
			} else {
				alert(msg);
			}
		}

		function check_shipp_create() {
				$( '.shipp_create .sh_left input' ).each(
					function () {
						if ( $( this ).val() == '' ) {
							$( this ).parent().css( 'border-color','red' );
							$( this ).prev().css( 'color','red' );
						} else {
							$( this ).parent().css( 'border-color','#909090' );
							$( this ).prev().css( 'color','#3c434a' );
						}

					}
				);
		}

		function check_international(checkbox) {
			item = checkbox.closest( '#TB_ajaxContent' );
			if (checkbox.is( ':checked' )) {
					item.find( '[data-class="shipp_declare"]' ).show();
					item.find( '.shipp_declare' ).show();
					item.find( '.shipp_inter' ).show();
					item.find( '.shipp_menu[data-class="shipp_declare"]' ).trigger( 'click' );
					item.find( '.shipp_menu' ).removeClass( 'active' );
					item.find( '.shipp_menu' ).removeClass( 'first' );
					item.find( '.shipp_menu[data-class="shipp_declare"]' ).addClass( 'active' );
					item.find( '.shipp_menu[data-class="shipp_declare"]' ).addClass( 'first' );
					$( '.shipp_create .sh_under .back' ).show();
			} else {
				item.find( '[data-class="shipp_declare"]' ).hide();
				item.find( '.shipp_declare' ).hide();
				item.find( '.shipp_inter' ).hide();
				item.find( '.shipp_menu[data-class="shipp_create"]' ).trigger( 'click' );
				item.find( '.shipp_menu' ).removeClass( 'active' );
				item.find( '.shipp_menu' ).removeClass( 'first' );
				item.find( '.shipp_menu[data-class="shipp_create"]' ).addClass( 'active' );
				item.find( '.shipp_menu[data-class="shipp_create"]' ).addClass( 'first' );
				$( '.shipp_create .sh_under .back' ).hide();
			}
		}

		$( '.open-thickbox' ).click(
			function (e) {
				e.preventDefault();
				id = $( this ).data( 'id' );
				tb_show( 'Order #' + id, '#TB_inline?width=780&height=540&inlineId=content-' + id, false );

				item = $( '#TB_ajaxContent' );
				checkbox = item.find( '[name="international"]' );

				if ( checkbox.is( ':checked' ) ) {
					$( '.shipp_menu[data-class="shipp_create"]' ).trigger( 'click' );
					check_international(checkbox);
				} else {
					$( '.shipp_menu[data-class="shipp_create"]' ).trigger( 'click' );
				}
			}
		);

		$( '[name="international"]' ).click(
			function () {
				check_international($( this ));
			}
		);

		$( "body" ).on(
			"click",
			".shipp_create .sh_under .back",
			function () {
				$( '.shipp_menu[data-class="shipp_declare"]' ).trigger( 'click' );
			}
		);

		$( "body" ).on(
			"click",
			".shipp_select .sh_under .back",
			function () {
				$( '.shipp_menu[data-class="shipp_create"]' ).trigger( 'click' );
			}
		);

		$( '.edit_package' ).click(
			function () {
				if ($( '.usr_temp' ).is( ':visible' )) {
						$( '.usr_temp' ).hide();
						$( '.cust_temp' ).show();
				} else {
					$( '.usr_temp' ).show();
					$( '.cust_temp' ).hide();
				}
			}
		);

		$( '.verify_address' ).on(
			'click',
			function () {
				let div   = $( this ).closest( '.domest' );
				let name  = div.find( '.address-fields[name="fname"]' ).val();
				let cntry = div.find( '[name="country"]' ).val();
				let state = div.find( '[name="state"]' ).val();
				let city  = div.find( '[name="city"]' ).val();
				let zip   = div.find( '[name="zip"]' ).val();
				let adder = div.find( '[name="adder"]' ).val();
				let adder2 = div.find( '[name="adder2"]' ).val();
				if (name != '' && cntry != '' && state != '' && city != '' && zip != '' && adder != '') {
						$.post(
							shippo.ajaxurl,
							{action:'ship_validate_address',name:name,country:cntry,state:state,city:city,zip:zip,address:adder,address2:adder2,nonce:shippo.nonce},
							function (resp) {
								check_shipp_create();
								alert_msg( resp.msg );
							}
						);
				} else {
					check_shipp_create();
					alert( 'Please fill all address fields' );
				}

			}
		);

		$( '.shipp_menu' ).click(
			function () {
				if ($( this ).data( 'class' ) == 'shipp_create') {
						$( '.shipp_create' ).show();
						$( '.shipp_select' ).hide();
						$( '.shipp_inter' ).hide();
				} else if ($( this ).data( 'class' ) == 'shipp_select') {
					$( '.shipp_select' ).show();
					$( '.shipp_create' ).hide();
					$( '.shipp_inter' ).hide();
				} else if ($( this ).data( 'class' ) == 'shipp_declare') {
					$( '.shipp_inter' ).show();
					$( '.shipp_create' ).hide();
					$( '.shipp_select' ).hide();
				}

				if ($( '[name="international"]:checked' ).length > 0) {
					if ($( this ).data( 'class' ) == 'shipp_declare') {
						$( '.shipp_menu' ).removeClass( 'active' );
					}
					if ($( this ).data( 'class' ) == 'shipp_create') {
						$( '.shipp_menu[data-class="shipp_select"]' ).removeClass( 'active' );
					}
				} else {
					if ($( this ).data( 'class' ) == 'shipp_create') {
						$( '.shipp_menu' ).removeClass( 'active' );
					}
				}

				$( this ).addClass( 'active' );
			}
		);

		$( '.domest_btn' ).on(
			'click',
			function () {
				let div   = $( this ).closest( '.domest' );
				let prt   = $( this ).closest( '#TB_ajaxContent' );
				let id    = div.data( 'id' );
				let name  = div.find( '[name="fname"]' ).val();
				let cntry = div.find( '[name="country"]' ).val();
				let state = div.find( '[name="state"]' ).val();
				let city  = div.find( '[name="city"]' ).val();
				let zip   = div.find( '[name="zip"]' ).val();
				let adder = div.find( '[name="adder"]' ).val();
				let adder2 = div.find( '[name="adder2"]' ).val();
				if ($( '.cust_temp' ).is( ':visible' )) {
					tplbox = {
						width  : div.find( '[name="width"]' ).val(),
						height : div.find( '[name="height"]' ).val(),
						length : div.find( '[name="length"]' ).val(),
						weight : div.find( '[name="weight"]' ).val(),
					};
				} else {
					tplbox = div.find( '[name="user_template"]' ).val();
				}
				inter = false;
				if ($( this ).closest( '#TB_ajaxContent' ).find( '[name="international"]:checked' ).length > 0) {
					inter = true;
				}
				params = {
					action:'ship_create_shipment',
					id:id,
					fname:name,
					country:cntry,
					state:state,
					city:city,
					zip:zip,
					adder:adder,
					adder2:adder2,
					tplbox:tplbox,
					internat:inter,
					nonce:shippo.nonce
				};

				if (name != '' && cntry != '' && state != '' && city != '' && zip != '' && adder != '') {
						$.post(
							shippo.ajaxurl,
							params,
							function (resp) {
								check_shipp_create();
								console.log(resp);
								
								if (resp.status == 1) {
									let container = $('#shippo-rates-list-' + id);
									let html = '<ul class="shippo-rates-list">';

									resp.rates.forEach(function(rate) {
										html += `
											<li>
												<div class="radio">
													<input type="radio" name="label" class="shipp_rate" 
														data-id="${id}" value="${rate.object_id}" />
												</div>
												<div class="desc">
													<label>${rate.provider}</label>
													<img src="${rate.provider_image_200}" width="24" height="24"/>
													<br><small>${rate.duration_terms}</small>
												</div>
												<div class="currency">
													<strong>${rate.amount_local} ${rate.currency_local}</strong>
												</div>
											</li>`;
									});

									html += '</ul>';
									container.html(html);

									prt.find('[data-class="shipp_select"]').trigger('click');
								} else {
									alert_msg( resp.msg );
								}
							}
						);
				} else {
					check_shipp_create();
					alert( 'Please fill all address fields' );
				}

			}
		);

		// save shipping rate and create label
		$( 'body' ).on(
			'click',
			'.label_btn',
			function () {
				prod_id = $( this ).closest( '.domest' ).data( 'id' );
				radio   = $( this ).closest( '.shipp_select' ).find( '.shipp_rate:checked' );
				if (radio.length > 0) {
						$.post(
							shippo.ajaxurl,
							{
								action:'ship_create_label',
								id:radio.data( 'id' ),
								val:radio.val(),
								nonce:shippo.nonce
							},
							function (resp) {
								alert_msg( resp.msg );
								if (resp.status && resp.label_url) {
									tb_remove();
									$( 'tr#post-' + prod_id ).find( 'a.retrive-label' ).attr( 'href', resp.label_url );
									setTimeout(function() {
										window.open( resp.label_url, '_blank' );
									}, 500);
								}
							}
						);
				} else {
					alert( 'Please select a delivery rate' );
				}
			}
		);

		$( '.declare_btn' ).click(
			function () {
				let prt   = $( this ).closest( '#TB_ajaxContent' );
				let div   = $( this ).closest( '.shipp_inter' );
				prod_id   = $( this ).closest( '.domest' ).data( 'id' );
				ch_cert   = div.find( '[name="certify"]' ).is( ':checked' ) ? 1 : 0;
				cert_name = div.find( '[name="cert_sign"]' ).val();
				eel_pfc   = div.find( '.eel_pfc' ).val();
				doc_type  = div.find( '.content' ).val();
				incoterm  = div.find( '.incoterm' ).val();
				delivery  = div.find( '.non_delivery' ).val();
				items     = [];
				products  = div.find( '.sh_right .products' );
				products.each(
					function (i,j) {
						index                = {};
						index.description    = $( this ).find( '.product' ).val();
						index.quantity       = $( this ).find( '.qtry' ).val();
						index.net_weight     = $( this ).find( '.weight' ).val();
						index.value_amount   = $( this ).find( '.price' ).val();
						index.origin_country = $( this ).find( '.country' ).val();
						index.mass_unit      = shippo.wunit;
						index.value_currency = shippo.currency;
						index.tariff_number  = '';
						items.push( index );
					}
				);
				$.post(
					shippo.ajaxurl,
					{action:'ship_declare_custome',id:prod_id,ch_cert:ch_cert,cert_name:cert_name,eel_pfc:eel_pfc,document:doc_type,incoterm:incoterm,delivery:delivery,items:items,nonce:shippo.nonce},
					function (resp) {
						if (resp.status == 1) {
							prt.find( '[data-class="shipp_create"]' ).trigger( 'click' );
						} else {
							alert_msg( resp.msg );
						}
					}
				);
			}
		);

		$( "body" ).on(
			"click",
			".shipp_select ul li",
			function () {
				$( ".shipp_select ul li" ).removeClass( "selected" );
				$( ".shipp_select ul li input" ).prop( 'checked',false );
				$( this ).addClass( "selected" );
				$( this ).find( "input" ).prop( 'checked',true );
			}
		);

		$( "body" ).on(
			"change",
			"#auto_status_change",
			function() {
			if ($(this).is(':checked')) {
				$("#auto_status_select").show();
			} else {
				$("#auto_status_select").hide();
			}
		});

	}
);