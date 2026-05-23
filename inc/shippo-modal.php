<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function hippshipp_modal_form( $post_id ) {
	global $woocommerce;

	$opt      = get_option( 'shippo_options', array() );
	$order    = wc_get_order( $post_id );
	$shipp    = hippshipp_helper::get_order_meta( $post_id, 'shippment' );
	$custome  = hippshipp_helper::get_order_meta( $post_id, 'custome_declarex' );
	$declare  = empty( $custome ) ? '' : $custome;
	$is_inter = false;
	if ( isset( $shipp[2]['internat'] ) && $shipp[2]['internat'] && ! empty( $declare ) ) {
		$is_inter = true;
	} elseif ( ! isset( $shipp[2]['internat'] ) ) {
		$is_inter = ( ! empty( $shipp[1] ) && $opt['country'] !== $shipp[1]['country'] );
	}
	$address  = '';
	$dunit    = get_option( 'woocommerce_dimension_unit' );
	$wunit    = get_option( 'woocommerce_weight_unit' );
	$currency = get_option( 'woocommerce_currency' );

	if ( ! empty( $shipp ) && count( $shipp ) > 1 ) {
		$address = $shipp[1];
	} else {
		$order_data = $order->get_data();
		$meta = array(
			'ship_to_different_address' => ! empty( $order_data['shipping']['address_1'] ) && $order_data['shipping']['address_1'] !== $order_data['billing']['address_1'],
			'shipping_first_name' => $order_data['shipping']['first_name'] ?: $order_data['billing']['first_name'],
			'shipping_last_name' => $order_data['shipping']['last_name'] ?: $order_data['billing']['last_name'],
			'shipping_company' => $order_data['shipping']['company'] ?: $order_data['billing']['company'],
			'shipping_address_1' => $order_data['shipping']['address_1'] ?: $order_data['billing']['address_1'],
			'shipping_address_2' => $order_data['shipping']['address_2'] ?: $order_data['billing']['address_2'],
			'shipping_city' => $order_data['shipping']['city'] ?: $order_data['billing']['city'],
			'shipping_state' => $order_data['shipping']['state'] ?: $order_data['billing']['state'],
			'shipping_postcode' => $order_data['shipping']['postcode'] ?: $order_data['billing']['postcode'],
			'shipping_country' => $order_data['shipping']['country'] ?: $order_data['billing']['country'],
			'billing_phone' => $order_data['billing']['phone'],
			'billing_email' => $order_data['billing']['email'],
			'billing_first_name' => $order_data['billing']['first_name'],
			'billing_last_name' => $order_data['billing']['last_name'],
			'billing_company' => $order_data['billing']['company'],
			'billing_address_1' => $order_data['billing']['address_1'],
			'billing_address_2' => $order_data['billing']['address_2'],
			'billing_city' => $order_data['billing']['city'],
			'billing_state' => $order_data['billing']['state'],
			'billing_postcode' => $order_data['billing']['postcode'],
			'billing_country' => $order_data['billing']['country'],
		);
		$address = hippshipp_helper::get_live_rate_param( $meta );
	}

	$countries = $woocommerce->countries->get_countries();
	$selected_country = empty( $address['country'] ) ? '' : $address['country'];
	$states = $woocommerce->countries->get_states( $selected_country );
	$selected_state = empty( $address['state'] ) ? '' : $address['state'];
?>
<div id="content-<?php echo esc_attr( $post_id ); ?>" class="sh_modal">
	<div class="sh_top">
		<input type="checkbox" name="international" value="1" <?php checked( $is_inter, true ); ?>/> <label>International shipment</label>
	</div>


	<div class="sh_mid">
		<div>
			<span class="prgs"></span>
			<a class="shipp_menu" data-class="shipp_declare" href="javascript:void(0)" <?php echo $is_inter == false ? 'style="display:none"' : ''; ?>>Declaration custom</a> 
			<a class="shipp_menu active first" data-class="shipp_create" href="javascript:void(0)">Create shipping</a> 
			<a class="shipp_menu" data-class="shipp_select" href="javascript:void(0)">Select shipping</a>
		</div>
	</div>

	<div class="domest" data-id="<?php echo esc_attr( $post_id ); ?>">
		<div class="shipp_inter" <?php echo $is_inter ? 'style="display:none"' : ''; ?>>
			<div class="sh_bot">
				<div class="sh_left">
					<div style="height:100%;">
						<table>
							<tr>
								<th>
									<label>Certify</label> 
									<input type="checkbox" name="certify" value="1" <?php checked( ( ! empty( $declare ) and $declare['ch_cert'] ), true ); ?>/>
								</th>
								<td>
									<fieldset>
										<legend>Certify Signer</legend>
										<input type="text" name="cert_sign" value="<?php echo isset( $declare['cert_name'] ) ? esc_attr( $declare['cert_name'] ) : ''; ?>"/>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th>EEL / PFC type</th>
								<td>
								<?php $selected = empty( $declare ) ? '' : $declare['eel_pfc']; ?>
									<select class="eel_pfc">
										<option value="" <?php selected( $selected, '' ); ?>></option>
										<option value="NOEEI_30_37_a" <?php selected( $selected, 'NOEEI_30_37_a' ); ?>>NOEEI_30_37_a</option>
										<option value="NOEEI_30_37_h" <?php selected( $selected, 'NOEEI_30_37_h' ); ?>>NOEEI_30_37_h</option>
										<option value="NOEEI_30_37_f" <?php selected( $selected, 'NOEEI_30_37_f' ); ?>>NOEEI_30_37_f</option>
										<option value="NOEEI_30_36" <?php selected( $selected, 'NOEEI_30_36' ); ?>>NOEEI_30_36</option>
										<option value="AES_ITN" <?php selected( $selected, 'AES_ITN' ); ?>>AES_ITN</option>
									</select>
								</td>
							</tr>
							<tr>
								<th>Contents type</th>
								<td>
								<?php $selected = empty( $declare ) ? 'DOCUMENTS' : $declare['document']; ?>
									<select class="content">
										<option value="DOCUMENTS" <?php selected( $selected, 'DOCUMENTS' ); ?>>DOCUMENTS</option>
										<option value="GIFT" <?php selected( $selected, 'GIFT' ); ?>>GIFT</option>
										<option value="SAMPLE" <?php selected( $selected, 'SAMPLE' ); ?>>SAMPLE</option>
										<option value="MERCHANDISE" <?php selected( $selected, 'MERCHANDISE' ); ?>>MERCHANDISE</option>
										<option value="HUMANITARIAN_DONATION" <?php selected( $selected, 'HUMANITARIAN_DONATION' ); ?>>HUMANITARIAN_DONATION</option>
										<option value="RETURN_MERCHANDISE" <?php selected( $selected, 'RETURN_MERCHANDISE' ); ?>>RETURN_MERCHANDISE</option>
										<option value="OTHER" <?php selected( $selected, 'OTHER' ); ?>>OTHER</option>
									</select>
								</td>
							</tr>
							<tr>
								<th>Incoterm</th>
								<td>
								<?php $selected = empty( $declare ) ? 'DDP' : $declare['incoterm']; ?>
									<select class="incoterm">
										<option value="DDP" <?php selected( $selected, 'DDP' ); ?>>DDP</option>
										<option value="DDU" <?php selected( $selected, 'DDU' ); ?>>DDU</option>
										<option value="FCA" <?php selected( $selected, 'FCA' ); ?>>FCA</option>
										<option value="DAP" <?php selected( $selected, 'DAP' ); ?>>DAP</option>
										<option value="eDAP" <?php selected( $selected, 'eDAP' ); ?>>eDAP</option>
									</select>
								</td>
							</tr>
							<tr>
								<th>Non delivery option</th>
								<td>
								<?php $selected = empty( $declare ) ? 'RETURN' : $declare['delivery']; ?>
									<select class="non_delivery">
										<option value="RETURN" <?php selected( $selected, 'RETURN' ); ?>>RETURN</option>
										<option value="ABANDON" <?php selected( $selected, 'ABANDON' ); ?>>ABANDON</option>
									</select>
								</td>
							</tr>
						</table>
					</div>
				</div>
				<div class="sh_right">
					<div style="height:100%;overflow-y:scroll;">
					<?php
					$i = 0;
					if ( empty( $declare ) ) {
						foreach ( $order->get_items() as $key => $item ) {
							$product = $item->get_product();
							if (!$product) continue;
							echo "
							<div class='products' data-id='" . esc_attr( $product->get_id() ) . "'>
								<p><strong>Product #" . esc_attr( ++$i ) . "</strong></p>
								<fieldset class='row'>
									<legend>Description</legend>
									<input type='text' class='product w-100' value='" . esc_attr( $product->get_name() ) . "'/>
								</fieldset>
								<div class='row'>
									<fieldset class='item'>
										<legend>Quantity</legend>
										<input type='text' class='qtry' value='" . esc_attr( $item->get_quantity() ) . "'/>
									</fieldset>
									<fieldset class='item'>
										<legend>Net weight</legend>
										<input type='text' class='weight' value='" . esc_attr( $product->get_weight() ) . "'>
										<span>" . esc_html( $wunit ) . "</span>
									</fieldset>
								</div>
								<div class='row'>
									<fieldset class='item'>
										<legend>Value</legend>
										<input type='text' class='price' value='" . esc_attr( $product->get_price() ) . "'>
										<span>" . esc_html( $currency ) . "</span>
									</fieldset>
									<fieldset class='item'>
										<legend>Orgin country</legend>
										<input type='text' class='country' value='" . esc_attr( get_post_meta( $product->get_id(), '_country_of_origin', true ) ) . "'/>
									</fieldset>
								</div>
							</div>";
						}
					} else {
						foreach ( $declare['items'] as $product ) {
							echo "
							<div class='products'>
								<p style='margin-top:5px;'><strong>Product #" . esc_attr( ++$i ) . "</strong></p>
								<fieldset class='row'>
									<legend>Description</legend>
									<input type='text' class='product w-100' value='" . esc_attr( $product['description'] ) . "'/>
								</fieldset>
								<div class='row'>
									<fieldset class='item'>
										<legend>Quantity</legend>
										<input type='text' class='qtry' value='" . esc_attr( $product['quantity'] ) . "'/>
									</fieldset>
									<fieldset class='item'>
										<legend>Net weight</legend>
										<input type='text' class='weight' value='" . esc_attr( $product['net_weight'] ) . "'/>
										<span>" . esc_html( $product['mass_unit'] ) . "</span>
									</fieldset>
								</div>
								<div class='row'>
									<fieldset class='item'>
										<legend>Value</legend>
										<input type='text' class='price' value='" . esc_attr( $product['value_amount'] ) . "'/>
										<span>" . esc_html( $product['value_currency'] ) . "</span>
									</fieldset>
									<fieldset class='item'>
										<legend>Orgin country</legend>
										<input type='text' class='country' value='" . esc_attr( $product['origin_country'] ) . "'/>
									</fieldset>
								</div>
							</div>";
						}
					}
					?>
					</div>
				</div>
			</div>
			<div class="sh_under">
				<button type="submit" name="declare" class="button declare_btn" >Declare customes</button>
			</div>
		</div>
		<div class="shipp_create" <?php echo $is_inter ? '' : 'style="display:none"'; ?>>
			<div class="sh_bot">
				<div class="sh_left">
					<div style="height:100%;overflow-y:scroll;" class="address-fields">

						<p>Recipieon Address (customer) <a href="javascript:void(0)" class="verify_address">Validate</a> </p>
						<fieldset class="row w-100">
							<legend>Name</legend>
							<input type="text" name="fname" value="<?php echo empty( $address['name'] ) ? '' : esc_attr( $address['name'] ); ?>" class="form-control" />
						</fieldset>
						<div class="row">
							<fieldset class="item">
								<legend>Country</legend>
								<div class="sh_select2">
									<select name="country" class="country_select form-control">
										<option value="">Country</option>
										<?php foreach ( $countries as $code => $name ) : ?>
											<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected_country, $code ); ?>><?php echo esc_html( $name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</fieldset>
							<fieldset class="item">
								<legend>State</legend>
								<div class="sh_select2">
									<?php if ( ! is_array( $states ) ) : ?>
										<input type="text" name="state" value="<?php echo empty( $address['state'] ) ? '' : esc_attr( $address['state'] ); ?>" class="state_select form-control" />
									<?php else : ?>
										<select name="state" class="state_select form-control" <?php echo empty( $address['country'] ) ? 'disabled' : ''; ?>>
											<option value="">Province</option>
											<?php
											foreach ( $states as $st_code => $st_name ) {
												echo '<option value="' . esc_attr( $st_code ) . '" ' . selected( $selected_state, $st_code, false ) . '>' . esc_html( $st_name ) . '</option>';
											}
											?>
										</select>
									<?php endif; ?>
								</div>
							</fieldset>						
						</div>
						<div class="row">
							<fieldset class="item">
								<legend>City</legend>
								<input type="text" name="city" value="<?php echo empty( $address['city'] ) ? '' : esc_attr( $address['city'] ); ?>" class="form-control" />
							</fieldset>
							<fieldset class="item">
								<legend>Zipcode</legend>
								<input type="text" name="zip" value="<?php echo empty( $address['zip'] ) ? '' : esc_attr( $address['zip'] ); ?>" class="form-control" />
							</fieldset>						
						</div>
						<fieldset class="row w-100">
							<legend>Address</legend>
							<input type="text" name="adder" value="<?php echo empty( $address['street1'] ) ? '' : esc_attr( $address['street1'] ); ?>" class="form-control" />
						</fieldset>
						<fieldset class="row w-100">
							<legend>Address2</legend>
							<input type="text" name="adder2" value="<?php echo empty( $address['street2'] ) ? '' : esc_attr( $address['street2'] ); ?>" class="form-control" />
						</fieldset>

					</div>
				</div>
				<div class="sh_right">
					<div style="height:100%;">
						<p>Package details <a href="javascript:void(0)" class="edit_package">Edit</a></p>
						<fieldset class="usr_temp row">
							<legend>User templates</legend>
							<select name="user_template">
							<?php
							if ( $parcels = ( new hippshipp_api() )->list_parcel() ) {
								foreach ( $parcels as $parcel ) {
									$selected = ( ( isset( $opt['pack']['active'] ) and $opt['pack']['active'] == $parcel->object_id ) );
									echo "<option value='" . esc_attr( $parcel->object_id ) . "' " . selected( $selected, true, false ) . '>' . esc_html( $parcel->length ) . 'x' . esc_html( $parcel->width ) . 'x' . esc_html( $parcel->height ) . ' ' . esc_html( $parcel->distance_unit ) . ' - ' . esc_html( $parcel->weight ) . ' ' . esc_html( $parcel->weight_unit ) . '</option>';
								}
							}
							?>
							</select>
						</fieldset>

						<div class="cust_temp" style="display:flex;display:none;">
							<p>Custom package</p>
							<div class="row">
								<fieldset class="item">
									<legend>width (<?php echo esc_html( $dunit ); ?>)</legend>
									<input type="text" name="width"/>
								</fieldset>
								<fieldset class="item">
									<legend>height (<?php echo esc_html( $dunit ); ?>)</legend>
									<input type="text" name="height"/>
								</fieldset>
							</div>
							<div class="row">
								<fieldset class="item">
									<legend>length (<?php echo esc_html( $dunit ); ?>)</legend>
									<input type="text" name="length"/>
								</fieldset>
								<fieldset class="item">
									<legend>weight (<?php echo esc_html( $wunit ); ?>)</legend>
									<input type="text" name="weight"/>
								</fieldset>
							</div>
						</div>

					</div>
				</div>

			</div>
			<div class="sh_under">
				<button type="submit" name="domest" class="button domest_btn" >Create Shippment</button>
				<span class="back" style="display:none">Back</span>
			</div>	
		
		</div>
		<div class="shipp_select" style="display:none">
			<?php echo wp_kses_post( hippshipp_helper::create_shipping_rate( $post_id ) ); ?>
		</div>
	</div>

</div>
<?php
}