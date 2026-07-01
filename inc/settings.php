<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_settings {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_shipping', [ $this, 'add_shipping_section' ], 10, 1 );
		add_filter( 'woocommerce_get_settings_shipping', [ $this, 'add_settings_fields' ], 10, 2 );
		add_action( 'woocommerce_settings_shipping', [ $this, 'output_settings_html' ] );
		add_action( 'woocommerce_settings_save_shipping', [ $this, 'save_settings' ] );
		add_action( 'admin_init', [ $this, 'handle_settings_actions' ] );
	}

	public function add_shipping_section( $sections ) {
		$sections['shippo'] = 'Shippo';
		return $sections;
	}

	public function add_settings_fields( $settings, $current_section ) {
		if ( $current_section != 'shippo' ) {
			return $settings;
		}

		$custom = [
			[
				'type' => 'title'
			],
			[
				'type' => 'shippo_options_table',
				'id'   => 'shippo_options_table',
			],
			[
				'type'  => 'sectionend',
				'class' => 'shippo-options',
			],
		];
		return array_merge( $custom, $settings );
	}

	public function output_settings_html() {
		if( empty( $_GET[ 'section' ] ) || 'shippo' !== $_GET[ 'section' ] ) {
			return;
		}

		$opt = get_option( 'shippo_options', [] );
		wp_nonce_field( 'shippo_action', 'shippo_nonce' );
		?>
		<div class="shippo-banner">
            <div class="logo-wrapper">
                <img src="<?php echo esc_url( HIPPSHIPP_URL . 'assets/images/icon.png' ); ?>" alt="<?php esc_attr_e( 'Hippoo Logo', 'shippo' ); ?>" class="hippoo-logo">
            </div>
            <div class="content">
                <h4><?php esc_html_e( 'Hippoo WooCommerce App: Generate Labels On the Go', 'shippo' ); ?></h4>
                <p><?php esc_html_e( 'With the Hippoo WooCommerce app, you can quickly generate shipping labels right inside the app, track orders, and manage your store on mobile', 'shippo' ); ?></p>
            </div>
            <div class="actions">
                <a href="https://hippoo.app" target="_blank" class="button learn-more"><?php esc_html_e( 'Learn more', 'shippo' ); ?></a>
            </div>
        </div>
		<table class="form-table">
			<tr>
				<th>Enable/disable</th>
				<td>
					<input type="checkbox" name="en_shippo" <?php echo isset( $opt['en_shippo'] ) ? 'checked="checked"' : ''; ?>/> 
					<label>Enable shippo</label>
				</td>
			</tr>
		</table>
		<h2>Integration Settings</h2>
		<p>Configure settings required for plugin to properly work with the service.</p>
		<table class="form-table">
			<tr>
				<th>API Token</th>
				<td>
					<input type="text" name="live_api" class="form-control" size="50" value="<?php echo isset( $opt['live_api'] ) ? esc_attr( $opt['live_api'] ) : ''; ?>"/>
					<p><small>For testing purposes, you can use a Shippo test token. Generate a Shippo API test token from your <a href="https://apps.goshippo.com/settings/api" target="_blank">Shippo dashboard.</a></small></p>
				</td>
			</tr>
		</table>
		<h2>General settings</h2>
		<table class="form-table">
			<tr>
				<td>
					<div class="wc-shipp-label">
						<label><input type="checkbox" name="shipping_rate" <?php echo isset( $opt['shipping_rate'] ) ? 'checked="checked"' : ''; ?>/> Display live shipping rates on cart and checkout pages</label>
						<br><small>To show live rates at checkout, please set them up in your Shippo dashboard under <a href="https://apps.goshippo.com/settings/rates-at-checkout" target="_blank">Live Rate Settings.</a></small>
					</div>
					<div class="wc-shipp-label">
						<label><input type="checkbox" name="live_tracking" <?php echo isset( $opt['live_tracking'] ) ? 'checked="checked"' : ''; ?>/> Show live tracking information in the customer’s account (<a href="<?php echo esc_html( wc_get_page_permalink( 'myaccount' ) ); ?>" target="_blank" style="text-decoration:none;">My Account</a>)</label>
					</div>
					<div class="wc-shipp-label">
						<label><input type="checkbox" name="tracking_code" <?php echo isset( $opt['tracking_code'] ) ? 'checked="checked"' : ''; ?>/> Send Tracking code to customer automatically via customer note</label>
					</div>
					<div class="wc-shipp-label">
						<label><input type="checkbox" name="combine_product_ship" <?php echo isset( $opt['combine_product_ship'] ) ? 'checked="checked"' : ''; ?>/> Combine all the products and ship together. Product dimensions and weight will be summed</label>
					</div>
					<div class="wc-shipp-label">
						<label><input type="checkbox" name="auto_status_change" id="auto_status_change" <?php echo isset( $opt['auto_status_change'] ) ? 'checked="checked"' : ''; ?>/> Enable automatic order status change after successful label creation</label>
					</div>
					<div id="auto_status_select" class="wc-shipp-label" style="display: <?php echo isset( $opt['auto_status_change'] ) ? 'flex' : 'none'; ?>;">
						<label>Select new order status</label>
						<select name="auto_status" style="width:200px;">
							<?php
							$statuses = wc_get_order_statuses();
							$selected = $opt['auto_status'] ?? 'wc-completed';
							foreach ( $statuses as $status_key => $status_name ) {
								echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( $selected, $status_key, false ) . '>' . esc_html( $status_name ) . '</option>';
							}
							?>
						</select>
					</div>
				</td>
			</tr>
		</table>
		<h2>Shipping calculation</h2>
		<table class="form-table">
			<tr>
				<th>Extra amount</th>
				<td>
					<input type="text" name="ex_amount" class="number form-control"  value="<?php echo isset( $opt['ex_amount'] ) ? esc_attr( $opt['ex_amount'] ) : ''; ?>" size="6"/> <label style="display:inline-block;margin-top:5px;"><?php echo esc_html( get_woocommerce_currency() ); ?></label>
					<p><small>This amount will be added to the shipping fee displayed to the customer on the checkout page.</small></p>
				</td>
			</tr>
		</table>
		<h2>Sender Address (Your shop address)</h2>
		<table class="wc-shipp-table">
			<tr>
				<td><input type="text" name="fname" value="<?php echo isset( $opt['fname'] ) ? esc_attr( $opt['fname'] ) : ''; ?>" class="form-control" size="32" placeholder="Full name"/></td>
				<td><input type="text" name="company" value="<?php echo isset( $opt['company'] ) ? esc_attr( $opt['company'] ) : ''; ?>" class="form-control" size="32" placeholder="Company"/></td>
			</tr>
			<tr>
				<td>
					<select name="country" class="country_to_state country_select">
					<option value="">Country</option>
					<?php
					if ( $countries = hippshipp_helper::get_countries() ) {
						foreach ( $countries as $code => $name ) {
							echo "<option value='" . esc_attr( $code ) . "' " . selected( ( $opt['country'] ?? '' ), $code, false ) . '>' . esc_html( $name ) . "</option>\n";
						}
					}
					?>
					</select>
				</td>
				<td>
					<select name="state" class="state_select" style="min-width:273px">
						<option value="">Province</option>
						<?php
						if ( ! empty( $opt['country'] ) ) {
							if ( $states = hippshipp_helper::get_states( $opt['country'] ) ) {
								foreach ( $states as $code => $name ) {
									echo "<option value='" . esc_attr( $code ) . "' " . selected( ( $opt['state'] ?? '' ), $code, false ) . '>' . esc_html( $name ) . '</option>';
								}
							}
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td><input type="text" name="city" value="<?php echo isset( $opt['city'] ) ? esc_attr( $opt['city'] ) : ''; ?>" class="form-control" size="32" placeholder="city"/></td>
				<td><input type="text" name="zipcode" value="<?php echo isset( $opt['zipcode'] ) ? esc_attr( $opt['zipcode'] ) : ''; ?>" class="form-control" size="32" placeholder="Zip code"/></td>
			</tr>
			<tr>
				<td colspan="2">
					<textarea name="address" class="form-control" rows="3" cols="70" placeholder="address"><?php echo isset( $opt['address'] ) ? esc_textarea( $opt['address'] ) : ''; ?></textarea>
				</td>
			</tr>
			<tr>
				<td><input type="email" name="email" value="<?php echo isset( $opt['email'] ) ? esc_attr( $opt['email'] ) : esc_attr( get_option( 'admin_email' ) ); ?>" class="form-control" size="32" placeholder="Eamil"/></td>
				<td><input type="text" name="phone" value="<?php echo isset( $opt['phone'] ) ? esc_attr( $opt['phone'] ) : ''; ?>" class="form-control" size="32" placeholder="Phone"/></td>
			</tr>
		</table>
		<div style="margin:50px 0 30px 0;">
			<h2 style="display:inline;">Package templates</h2>
		</div>
		<table class="widefat striped pack_list">
			<thead>
				<th style="width:50px;">Set as default</th>
				<th>Name</th>
				<th>Length</th>
				<th>Width</th>
				<th>Height</th>
				<th>Distance unit</th>
				<th>Weight</th>
				<th>Weight unit</th>
				<th>Actions</th>
			</thead>
			<tbody>
			<tr>
				<td>-</td>
				<td><input type='text' name='pack[name]' /></td>
				<td><input type='text' name='pack[length]' class='number' size='5' /></td>
				<td><input type='text' name='pack[width]' class='number' size='5' /></td>
				<td><input type='text' name='pack[height]' class='number' size='5' /></td>
				<td>
					<select name="pack[distance_unit]">
						<option value="cm">CM</option>
						<option value="in">IN</option>
						<option value="ft">FT</option>
						<option value="m">M</option>
						<option value="mm">MM</option>
						<option value="yd">YD</option>
					</select>
				</td>
				<td><input type='text' name='pack[weight]' class='number' size='5' /></td>
				<td colspan="2">
					<select name="pack[weight_unit]">
						<option value="g">G</option>
						<option value="kg">KG</option>
						<option value="lb">LB</option>
						<option value="oz">OZ</option>
					</select>
				</td>
			</tr>
			<?php
			if ( $parcels = hippshipp_api::list_parcel() ) {
				foreach ( $parcels as $parcel ) {
					echo "
						<tr>
							<td><input type='radio' name='pack[active]' value='" . esc_attr( $parcel->object_id ?? '' ) . "' " . ( ( isset( $opt['pack']['active'] ) && $opt['pack']['active'] == ( $parcel->object_id ?? '' ) ) ? 'checked="checked"' : '' ) . '></td>
							<td>' . esc_html( $parcel->name ?? 'N/A' ) . '</td>
							<td>' . esc_html( $parcel->length ?? '' ) . '</td>
							<td>' . esc_html( $parcel->width ?? '' ) . '</td>
							<td>' . esc_html( $parcel->height ?? '' ) . '</td>
							<td>' . esc_html( $parcel->distance_unit ?? '' ) . '</td>
							<td>' . esc_html( $parcel->weight ?? '' ) . '</td>
							<td>' . esc_html( $parcel->weight_unit ?? '' ) . "</td>
							<td><button name='parcel_del[" . esc_attr( $parcel->object_id ?? '' ) . "]' onclick='return confirm(\"Do you want delete this package?\")'><span class='dashicons dashicons-trash del' style='color:red;cursor:pointer;' title='Delete'></span></button></td>
						</tr>";
				}
			}
			?>
			</tbody>
		</table>
		<?php
	}

	public function save_settings() {
		if( empty( $_GET[ 'section' ] ) || 'shippo' !== $_GET[ 'section' ] ) {
			return;
		}

		if ( isset( $_POST['shippo_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['shippo_nonce'] ), 'shippo_action' ) ) {
			return;
		}

		$data = [
			'en_shippo' => isset( $_POST['en_shippo'] ) ? sanitize_text_field( wp_unslash( $_POST['en_shippo'] ) ) : null,
			'live_api' => isset( $_POST['live_api'] ) ? sanitize_text_field( wp_unslash( $_POST['live_api'] ) ) : '',
			'shipping_rate' => isset( $_POST['shipping_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_rate'] ) ) : null,
			'tracking_code' => isset( $_POST['tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_code'] ) ) : null,
			'live_tracking' => isset( $_POST['live_tracking'] ) ? sanitize_text_field( wp_unslash( $_POST['live_tracking'] ) ) : null,
			'combine_product_ship' => isset( $_POST['combine_product_ship'] ) ? sanitize_text_field( wp_unslash( $_POST['combine_product_ship'] ) ) : null,
			'auto_status_change' => isset( $_POST['auto_status_change'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_status_change'] ) ) : null,
			'auto_status' => isset( $_POST['auto_status'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_status'] ) ) : null,
			'ex_amount' => isset( $_POST['ex_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['ex_amount'] ) ) : '',
			'fname' => isset( $_POST['fname'] ) ? sanitize_text_field( wp_unslash( $_POST['fname'] ) ) : '',
			'company' => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '',
			'address' => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
			'city' => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'state' => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
			'zipcode' => isset( $_POST['zipcode'] ) ? sanitize_text_field( wp_unslash( $_POST['zipcode'] ) ) : '',
			'country' => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '',
			'phone' => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'email' => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : get_option( 'admin_email' ),
		];

		if ( ! empty( $_POST['pack'] ) && is_array( $_POST['pack'] ) ) {
			$pack = hippshipp_helper::prepare_shippo_template( $_POST['pack'] );

			$data['pack']['active'] = isset( $_POST['pack']['active'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['active'] ) ) : null;

			if ( ! empty( $pack['name'] ) && ! empty( $pack['length'] ) && ! empty( $pack['height'] ) && ! empty( $pack['width'] )
				&& ! empty( $pack['distance_unit'] ) && ! empty( $pack['weight'] ) && ! empty( $pack['weight_unit'] ) ) {
				$ret = hippshipp_api::add_parcel( $pack );
				if ( ! isset( $ret->object_id ) ) {
					hippshipp_helper::admin_notice( 'Parcel is not valid' );
				} else {
					$new_parcel_id = $ret->object_id;
				}
			}
		}

		update_option( 'shippo_options', $data );

		if ( ! empty( $new_parcel_id ) ) {
			hippshipp_helper::update_active_parcel( $new_parcel_id );
		}

		if ( $from = get_option( 'shippo_from' ) ) {
			if (
				! empty( $data['fname'] ) && ! empty( $data['company'] ) && ! empty( $data['address'] ) &&
				! empty( $data['city'] ) && ! empty( $data['state'] ) && ! empty( $data['zipcode'] ) &&
				! empty( $data['country'] ) && ! empty( $data['phone'] ) &&
				$from->name == $data['fname'] && $from->company == $data['company'] &&
				$from->street1 == $data['address'] && $from->city == $data['city'] &&
				$from->state == $data['state'] && $from->zip == $data['zipcode'] &&
				$from->country == $data['country'] && $from->email == $data['email'] && $from->phone == $data['phone'] ) {
				return;
			}
		}

		$from_address = hippshipp_helper::prepare_shippo_address( $data );
		$adder = hippshipp_api::address( $from_address );

		if ( isset( $adder->object_id ) ) {
			update_option( 'shippo_from', $adder );
		} else {
			hippshipp_helper::admin_notice( 'Address is not valid' );
		}
	}

	public function handle_settings_actions() {
		if ( isset( $_POST['shippo_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['shippo_nonce'] ), 'shippo_action' ) ) {
			return;
		}

		// Delete Parcel
		$parcel_id = isset( $_POST['parcel_del'] ) ? key( $_POST['parcel_del'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $parcel_id ) {
			$ret = hippshipp_api::delete_parcel( $parcel_id );
			if ( isset( $ret->detail ) ) {
				hippshipp_helper::admin_notice( $ret->detail );
			} else {
				hippshipp_helper::update_active_parcel( $parcel_id );
				hippshipp_helper::admin_notice( 'Parcel deleted successfully.', 'success' );
			}
		}

		// Save Notice
		if ( is_admin() && isset( $_POST['domest'] ) ) {
			hippshipp_helper::admin_notice( 'Settings saved successfully.', 'success' );
		}
	}

}

new hippshipp_settings();