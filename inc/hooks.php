<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_hooks {

	public function __construct() {
		// add option tab to woocomerce shipping configuration
		add_filter( 'woocommerce_get_sections_shipping', array( $this, 'woocommerce_get_sections_shipping' ), 10, 1 );
		add_filter( 'woocommerce_get_settings_shipping', array( $this, 'woocommerce_get_settings_shipping' ), 10, 2 );
		add_action( 'woocommerce_admin_field_shippo_options_table', array( $this, 'woocommerce_admin_field_shippo_options_table' ), 10, 1 );
		add_action( 'woocommerce_update_option_shippo_options_table', array( $this, 'woocommerce_update_option_shippo_options_table' ), 10, 1 );
		// initialize Shippo shipping method
		add_action( 'woocommerce_shipping_init', array( $this, 'woocommerce_shipping_init' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'woocommerce_shipping_methods' ), 10, 1 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'woocommerce_cart_shipping_method_full_label' ), 10, 2 );
		// show live tracking in customer order page
		add_action( 'woocommerce_view_order', array( $this, 'my_account_live_tracking' ), 10, 1 );
		// show list of shippment
		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'woocommerce_review_order_before_submit' ) );
		// save shippment info to order
		add_action( 'woocommerce_new_order', array( $this, 'woocommerce_new_order' ), 10, 1 );
		// add metabox to admin order
		add_action( 'add_meta_boxes', array( $this, 'admin_order_metabox' ), 1 );
		// delete shippo config parcel
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		// add shippo column to admin shop order columns
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_custom_order_column' ), 9999 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'populate_custom_order_column' ), 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_custom_order_column' ), 9999 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'populate_custom_order_column' ), 10, 2 );
		// enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		// handle first-time plugin activation
		add_action( 'activated_plugin', array( $this, 'plugin_activation' ) );
		// create database table on plugin activation
		register_activation_hook( hippshipp__FILE__, array( $this, 'register_activation_hook' ) );
		// add cron job on plugin activation
		register_activation_hook( hippshipp__FILE__, array( $this, 'schedule_weekly_cleanup' ) );
		// add cron job on plugin deactivation
		register_deactivation_hook( hippshipp__FILE__, array( $this, 'unschedule_weekly_cleanup' ) );
		// add cron job action
		add_action( 'hippshipp_weekly_cleanup', array( $this, 'cleanup_unnecessary_metadata' ) );
	}

	function plugin_activation( $plugin ) {
		if ( $plugin === plugin_basename( hippshipp__FILE__ ) ) {
			if ( ! get_option( 'hippshipp_activated' ) ) {
				update_option( 'hippshipp_activated', true );
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=shippo' ) );
				exit;
			}
		}
	}

	function register_activation_hook() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hippshipp_order_meta';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
			`meta_id` mediumint(9) NOT NULL AUTO_INCREMENT,
			`order_id` bigint(20) NOT NULL,
			`meta_key` varchar(191) NOT NULL,
			`meta_value` longtext NOT NULL,
			PRIMARY KEY (`meta_id`),
			UNIQUE KEY `order_meta_unique` (`order_id`, `meta_key`),
			KEY `meta_key_index` (`meta_key`),
			KEY `meta_value_index` (`meta_value`(191))
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	function schedule_weekly_cleanup() {
		if ( ! wp_next_scheduled( 'hippshipp_weekly_cleanup' ) ) {
			wp_schedule_event( time(), 'weekly', 'hippshipp_weekly_cleanup' );
		}
	}

	function unschedule_weekly_cleanup() {
		$timestamp = wp_next_scheduled( 'hippshipp_weekly_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'hippshipp_weekly_cleanup' );
		}
	}

	function cleanup_unnecessary_metadata() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hippshipp_order_meta';
		
		$exclude_meta_keys = array( 'shippment', 'retrive_label' );
		$exclude_meta_keys = array_map( 'esc_sql', (array) $exclude_meta_keys );
		$placeholders = implode( ',', array_fill( 0, count( $exclude_meta_keys ), '%s' ) );

		// phpcs:disable WordPress.DB
		$query = $wpdb->prepare("
			DELETE FROM `$table_name`
			WHERE order_id IN (
				SELECT order_id FROM (
					SELECT DISTINCT order_id 
					FROM `$table_name`
					WHERE meta_key = 'retrive_label' 
					AND meta_value IS NOT NULL 
					AND meta_value != ''
				) AS subquery
			)
			AND meta_key NOT IN ($placeholders)",
			$exclude_meta_keys
		);

		$wpdb->query( $query );
		// phpcs:enable
	}	

	function wp_enqueue_scripts() {
		wp_enqueue_style( 'shp-public', hippshipp_url . 'assets/css/public-style.css', array(), hippshipp_version, 'all' );
		wp_enqueue_script( 'shp-public', hippshipp_url . 'assets/js/public-script.js', array( 'jquery' ), hippshipp_version, true );
		wp_localize_script(
			'shp-public',
			'shippo',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'shippo_nonce' ),
			)
		);
	}

	function admin_enqueue_scripts( $hook ) {
		wp_register_style( 'shp-admin', hippshipp_url . 'assets/css/admin-style.css', array(), hippshipp_version, 'all' );
		wp_register_script( 'shp-admin', hippshipp_url . 'assets/js/admin-script.js', array( 'jquery' ), hippshipp_version, true );
		wp_localize_script(
			'shp-admin',
			'shippo',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'shippo_nonce' ),
				'currency' => get_option( 'woocommerce_currency' ),
				'wunit'    => get_option( 'woocommerce_weight_unit' ),
			)
		);

		if ( 
			( $hook === 'edit.php' && isset( $_GET['post_type'] ) && sanitize_key( $_GET['post_type'] ) === 'shop_order' ) ||
			( $hook === 'post.php' && isset( $_GET['post'] ) && get_post_type( sanitize_key( $_GET['post'] ) ) === 'shop_order' ) ||
			( $hook === 'woocommerce_page_wc-orders' && isset( $_GET['page'] ) && sanitize_key( $_GET['page'] ) === 'wc-orders' ) ||
			( $hook === 'woocommerce_page_wc-settings' && isset( $_GET['section'] ) && sanitize_key( $_GET['section'] ) === 'shippo' )
		) {
			wp_enqueue_script( 'jquery' );
			add_thickbox();
			wp_enqueue_style( 'shp-admin' );
			wp_enqueue_script( 'shp-admin' );
		}
	}

	function admin_init() {
		$shippo_api = new hippshipp_api();
		if ( isset( $_POST['shippo_nonce'] ) and ! wp_verify_nonce( sanitize_key( $_POST['shippo_nonce'] ), 'shippo_action' ) ) {
			return;
		}

		$parcel_id = isset( $_POST['parcel_del'] ) ? key( $_POST['parcel_del'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $parcel_id ) {
			$ret = $shippo_api->delete_parcel( $parcel_id );
			if ( isset( $ret->detail ) ) {
				hippshipp_helper::admin_notice( $ret->detail );
			} else {
				hippshipp_helper::update_active_parcel( $parcel_id );
				hippshipp_helper::admin_notice( 'Parcel delete success' );
			}
		}

		if ( is_admin() and isset( $_POST['domest'] ) ) {
			hippshipp_helper::admin_notice( 'save success', 'success' );
		}
	}

	function woocommerce_get_sections_shipping( $sections ) {
		$sections['shippo'] = 'Shippo';
		return $sections;
	}

	function woocommerce_get_settings_shipping( $settings, $current_section ) {
		if ( $current_section != 'shippo' ) {
			return $settings;
		}

		$custom = array(
			array( 'type' => 'title' ),
			array(
				'type' => 'shippo_options_table',
				'id'   => 'shippo_options_table',
			),
			array(
				'type'  => 'sectionend',
				'class' => 'shippo-options',
			),
		);
		return array_merge( $custom, $settings );
	}

	function woocommerce_admin_field_shippo_options_table( $value ) {
		global $woocommerce;
		$opt = get_option( 'shippo_options', array() );
		wp_nonce_field( 'shippo_action', 'shippo_nonce' );
		?>
		<div class="shippo-banner">
            <div class="logo-wrapper">
                <img src="<?php echo esc_url( hippshipp_url . 'assets/images/icon.png' ); ?>" alt="<?php esc_attr_e( 'Hippoo Logo', 'shippo' ); ?>" class="hippoo-logo">
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
					if ( $country = $woocommerce->countries->get_countries() ) {
						$cn_select = isset( $opt['country'] ) ? $opt['country'] : '';
						foreach ( $country as $cname => $cntry ) {
							echo "<option value='" . esc_attr( $cname ) . "' " . selected( $cn_select, $cname, false ) . '>' . esc_html( $cntry ) . "</option>\n";
						}
					}
					?>
					</select>
				</td>
				<td>
					<select name="state" class="state_select" style="min-width:273px">
						<option value="">Province</option>
						<?php
						if ( ! empty( $cn_select ) ) {
							if ( $states = $woocommerce->countries->get_states( $cn_select ) ) {
								$st_select = isset( $opt['state'] ) ? $opt['state'] : '';
								foreach ( $states as $st_name => $st_val ) {
									echo "<option value='" . esc_attr( $st_name ) . "' " . selected( $st_select, $st_name, false ) . '>' . esc_html( $st_val ) . '</option>';
								}
							}
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td><input type="text" name="city" value="<?php echo isset( $opt['city'] ) ? esc_attr( $opt['city'] ) : ''; ?>" class="form-control" size="32" placeholder="city"/></td>
				<td><input type="text" name="zipcode" value="<?php echo isset( $opt['zipcode'] ) ? esc_attr( $opt['zipcode'] ) : ''; ?>" class="number form-control" size="32" placeholder="Zip code"/></td>
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
			<!--button type="button" class="btn button add_pack" style="background-color:#32435B;color:white;float:<?php echo is_rtl() ? 'left' : 'right'; ?>;">Add new</button-->
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
			if ( $parcels = ( new hippshipp_api() )->list_parcel() ) {
				foreach ( $parcels as $parcel ) {
					echo "
						<tr>
							<td><input type='radio' name='pack[active]' value='" . esc_attr( $parcel->object_id ) . "' " . ( ( isset( $opt['pack']['active'] ) and $opt['pack']['active'] == $parcel->object_id ) ? 'checked="checked"' : '' ) . '></td>
							<td>' . esc_html( $parcel->name ) . '</td>
							<td>' . esc_html( $parcel->length ) . '</td>
							<td>' . esc_html( $parcel->width ) . '</td>
							<td>' . esc_html( $parcel->height ) . '</td>
							<td>' . esc_html( $parcel->distance_unit ) . '</td>
							<td>' . esc_html( $parcel->weight ) . '</td>
							<td>' . esc_html( $parcel->weight_unit ) . "</td>
							<td><button name='parcel_del[" . esc_attr( $parcel->object_id ) . "]' onclick='return confirm(\"Do you want delete this package?\")'><span class='dashicons dashicons-trash del' style='color:red;cursor:pointer;' title='Delete'></span></button></td>
						</tr>";
				}
			}
			?>
			</tbody>
		</table>
		<?php
	}

	function woocommerce_update_option_shippo_options_table( $value ) {
		$shippo_api = new hippshipp_api();

		if ( isset( $_POST['shippo_nonce'] ) and ! wp_verify_nonce( sanitize_key( $_POST['shippo_nonce'] ), 'shippo_action' ) ) {
			return;
		}

		$data = array(
			'en_shippo' => isset( $_POST['en_shippo'] ) ? sanitize_text_field( wp_unslash( $_POST['en_shippo'] ) ) : null,
			'live_api' => isset( $_POST['live_api'] ) ? sanitize_text_field( wp_unslash( $_POST['live_api'] ) ) : '',
			'shipping_rate' => isset( $_POST['shipping_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_rate'] ) ) : null,
			'tracking_code' => isset( $_POST['tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_code'] ) ) : null,
			'live_tracking' => isset( $_POST['live_tracking'] ) ? sanitize_text_field( wp_unslash( $_POST['live_tracking'] ) ) : null,
			'combine_product_ship' => isset( $_POST['combine_product_ship'] ) ? sanitize_text_field( wp_unslash( $_POST['combine_product_ship'] ) ) : null,
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
		);

		if ( ! empty( $_POST['pack'] ) && is_array( $_POST['pack'] ) ) {
			$pack = array(
				'name'          => ! empty( $_POST['pack']['name'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['name'] ) ) : '',
				'length'        => ! empty( $_POST['pack']['length'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['length'] ) ) : '0',
				'height'        => ! empty( $_POST['pack']['height'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['height'] ) ) : '0',
				'width'         => ! empty( $_POST['pack']['width'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['width'] ) ) : '0',
				'distance_unit' => ! empty( $_POST['pack']['distance_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['distance_unit'] ) ) : '',
				'weight'        => ! empty( $_POST['pack']['weight'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['weight'] ) ) : '0',
				'weight_unit'   => ! empty( $_POST['pack']['weight_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['weight_unit'] ) ) : '',
			);

			$data['pack']['active'] = isset( $_POST['pack']['active'] ) ? sanitize_text_field( wp_unslash( $_POST['pack']['active'] ) ) : null;

			if ( ! empty( $pack['name'] ) and ! empty( $pack['length'] ) and ! empty( $pack['height'] ) and ! empty( $pack['width'] )
				and ! empty( $pack['distance_unit'] ) and ! empty( $pack['weight'] ) and ! empty( $pack['weight_unit'] ) ) {
				$ret = $shippo_api->add_parcel( $pack );
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
				! empty( $data['fname'] ) and ! empty( $data['company'] ) and ! empty( $data['address'] ) and
				! empty( $data['city'] ) and ! empty( $data['state'] ) and ! empty( $data['zipcode'] ) and
				! empty( $data['country'] ) and ! empty( $data['phone'] ) and
				$from->name == $data['fname'] and $from->company == $data['company'] and
				$from->street1 == $data['address'] and $from->city == $data['city'] and
				$from->state == $data['state'] and $from->zip == $data['zipcode'] and
				$from->country == $data['country'] and $from->email == $data['email'] and $from->phone == $data['phone'] ) {
				return;
			}
		}
		$adder = $shippo_api->address(
			array(
				'name'    => $data['fname'],
				'company' => $data['company'],
				'street1' => $data['address'],
				'city'    => $data['city'],
				'state'   => $data['state'],
				'zip'     => $data['zipcode'],
				'country' => $data['country'],
				'phone'   => $data['phone'],
				'email'   => $data['email'],
			)
		);
		if ( isset( $adder->object_id ) ) {
			update_option( 'shippo_from', $adder );
		} else {
			hippshipp_helper::admin_notice( 'Address is not valid' );
		}
	}

	function woocommerce_shipping_init() {
		require_once hippshipp_path . 'inc/shipping-method.php';
	}

	function woocommerce_shipping_methods( $methods ) {
		$methods['shippo_live_rate'] = 'hippshipp_shipping_method';
		return $methods;
	}

	function woocommerce_cart_shipping_method_full_label( $label, $method ) {
		$metadata = $method->get_meta_data();
		if ( isset( $metadata['shippo_description'] ) && ! empty( $metadata['shippo_description'] ) ) {
			$label .= '<br><small>' . esc_html( $metadata['shippo_description'] ) . '</small>';
		}
		return $label;
	}

	public function my_account_live_tracking( $order_id ) {
		if ( is_admin() ) {
			return;
		}

		$opt = get_option( 'shippo_options', array() );
		if ( empty( $opt['en_shippo'] ) || empty( $opt['live_tracking'] ) ) {
			return;
		}

		$label = hippshipp_helper::get_order_meta( $order_id, 'retrive_label' );
		$tracking_number = $label->tracking_number ?? '';
		
		if ( empty( $tracking_number ) ) {
			return;
		}

		$carrier = hippshipp_helper::get_order_meta( $order_id, 'live_rate_carrier' );

		$tracking_data = null;
		if ( ! empty( $carrier ) ) {
			$tracking_data = ( new hippshipp_api() )->track_shipment( $carrier, $tracking_number );
		}

		if ( is_object( $tracking_data ) && isset( $tracking_data->error ) ) {
			$tracking_data = null;
		}

		$eta             = $label->eta ?? $tracking_data->eta ?? '';
		$latest_status   = $tracking_data->tracking_status->status ?? '';
		$latest_time     = $tracking_data->tracking_status->status_date ?? '';
		$latest_location = $tracking_data->tracking_status->location ?? '';
		$history         = $tracking_data->tracking_history ?? array();
		?>
		<div class="shippo-live-tracking">
			<h2 class="shippo-title"><?php echo esc_html__( 'Shipping updates', 'shippo' ); ?></h2>
			
			<div class="shippo-tracking-summary">
				<div class="shippo-summary-row shippo-summary-eta">
					<strong><?php echo esc_html__( 'Estimate delivery: ', 'shippo' ); ?></strong>
					<?php echo esc_html( $eta ); ?>
				</div>
				<div class="shippo-summary-row shippo-summary-tracking">
					<strong><?php echo esc_html__( 'Tracking number:', 'shippo' ); ?></strong>
					<?php echo esc_html( $tracking_number ); ?>
				</div>
				<div class="shippo-summary-row shippo-summary-courier">
					<strong><?php echo esc_html__( 'Courier:', 'shippo' ); ?></strong>
					<?php echo esc_html( strtoupper( $carrier ) ); ?>
				</div>
			</div>
				
			<?php if ( empty( $latest_status ) ) : ?>
				<div class="shippo-tracking-item empty">
					<div class="shippo-status-text">
						<?php echo esc_html__( 'No tracking information is available yet.', 'shippo' ); ?>
					</div>
				</div>
			<?php else: ?>
				<div class="shippo-tracking-item shippo-latest-status">
					<div class="shippo-status-header">
						<div class="shippo-status-badge">
							<?php echo esc_html( $latest_status ); ?>
						</div>
						<div class="shippo-status-time">
							<?php echo esc_html( hippshipp_helper::format_shippo_date( $latest_time ) ); ?>
						</div>
					</div>
					<div class="shippo-status-text">
						<?php 
							echo !empty( $tracking_data->tracking_status->status_details )
								? esc_html( $tracking_data->tracking_status->status_details )
								: '';
						?>
					</div>
					<?php if ( ! empty( $latest_location ) ) : ?>
						<div class="shippo-status-location">
							<?php echo esc_html( $latest_location->city . ', ' . $latest_location->country ); ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $history ) ) : ?>
					<div class="shippo-history-box" style="display:none;">
						<?php foreach ( $history as $item ) : ?>
							<div class="shippo-tracking-item shippo-history-item">
								<div class="shippo-status-header">
									<div class="shippo-status-badge">
										<?php echo esc_html( $item->status ); ?>
									</div>
									<div class="shippo-status-time">
										<?php echo esc_html( hippshipp_helper::format_shippo_date( $item->status_date ) ); ?>
									</div>
								</div>
								<div class="shippo-status-text">
									<?php echo esc_html( $item->status_details ); ?>
								</div>
								<div class="shippo-status-location">
									<?php echo isset( $item->location ) ? esc_html( $item->location->city . ', ' . $item->location->country ) : ''; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="shippo-history-toggle">
						<a href="#" class="shippo-show-history" data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php echo esc_html__( 'See tracking history', 'shippo' ); ?>
						</a>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	function woocommerce_review_order_before_submit() {
		if ( isset( $_POST['shippo_nonce'] ) and ! wp_verify_nonce( sanitize_key( $_POST['shippo_nonce'] ), 'shippo_action' ) ) {
			return;
		}

		$opt = get_option( 'shippo_options' );
		$uid   = get_current_user_id();
		$meta  = get_user_meta( $uid );
		$symbl = get_woocommerce_currency_symbol();
		$price = empty( $opt['ex_amount'] ) ? '0.00' : number_format( absint( $opt['ex_amount'] ), 2 );

		if ( empty( $_POST ) && empty( $meta['shipping_first_name'] ) && empty( $meta['shipping_country'] ) &&
			empty( $meta['billing_phone'] ) && empty( $meta['shipping_city'] ) ) {
			echo "
			<tr>
				<th>Shipping</th>
				<td>
					<ul id='shippo-shipping-methods'>" . esc_html( $price ) . ' ' . esc_html( $symbl ) . '</ul>
				</td>
			</tr>';
			return;
		}

		$args = array();
		if ( empty( $_POST ) ) {
			if ( ! empty( $meta['billing_first_name'] ) ) {
				$args = array(
					'name'    => ( ! empty( $meta['billing_first_name'][0] ) ? $meta['billing_first_name'][0] : '' ) . ' ' .
								( ! empty( $meta['billing_last_name'][0] ) ? $meta['billing_last_name'][0] : '' ),
					'company' => ! empty( $meta['billing_company'][0] ) ? $meta['billing_company'][0] : '',
					'street1' => ! empty( $meta['billing_address_1'][0] ) ? $meta['billing_address_1'][0] : '',
					'street2' => ! empty( $meta['billing_address_2'][0] ) ? $meta['billing_address_2'][0] : '',
					'city'    => ! empty( $meta['billing_city'][0] ) ? $meta['billing_city'][0] : '',
					'state'   => ! empty( $meta['billing_state'][0] ) ? $meta['billing_state'][0] : '',
					'zip'     => ! empty( $meta['billing_postcode'][0] ) ? $meta['billing_postcode'][0] : '',
					'country' => ! empty( $meta['billing_country'][0] ) ? $meta['billing_country'][0] : '',
					'phone'   => ! empty( $meta['billing_phone'][0] ) ? $meta['billing_phone'][0] : '',
					'email'   => ! empty( $meta['billing_email'][0] ) ? $meta['billing_email'][0] : '',
				);
			}
		} else {
			$data = ! empty( $_POST['post_data'] ) ? urldecode( wp_unslash( $_POST['post_data'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			parse_str( $data, $meta );
			$args = hippshipp_helper::get_live_rate_param( $meta );
		}

		$rates = array();
		if ( ! empty( $args ) ) {
			$rates = hippshipp_helper::get_order_rate( $args );
		}

		if ( ! empty( $rates ) ) {
			if ( ! session_id() ) {
				@session_start();
			}
			$_SESSION['shippo_shippment'] = array( $rates, $args );
		}
	}

	function woocommerce_new_order( $order_id ) {
		if ( ! session_id() ) {
			@session_start();
		}

		$opt = get_option( 'shippo_options' );
		$order = new \WC_Order( $order_id );

		if ( isset( $opt['en_shippo'] ) && isset( $_SESSION['shippo_shippment'] ) ) {
			$shippment = $_SESSION['shippo_shippment']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$shippment_amount_local = isset( $shippment[0][0]->amount_local ) ? floatval( sanitize_text_field( $shippment[0][0]->amount_local ) ) : 0;
			$shippment_amount = isset( $shippment[0][0]->amount ) ? floatval( sanitize_text_field( $shippment[0][0]->amount ) ) : 0;

			$amount = empty( $shippment_amount_local ) ? $shippment_amount : $shippment_amount_local;
			$amount = floatval( $amount ) + absint( $opt['ex_amount'] );
			
			hippshipp_helper::update_order_meta( $order_id, 'shipp_rate', $opt['ex_amount'] );
			hippshipp_helper::update_order_meta( $order_id, 'shipp_amount', $amount );
			hippshipp_helper::update_order_meta( $order_id, 'shippment', $shippment ); 

			unset( $_SESSION['shippo_shippment'] );
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

			$address = array_map( 'sanitize_text_field', array_filter( $address ) );

			$shippment = array(
				array(),
				$address,
			);
			
			hippshipp_helper::update_order_meta( $order_id, 'shippment', $shippment );
		}
	}

	function admin_order_metabox() {
		add_meta_box(
			'shippo-tracking-mtbox',
			'Shippo Info',
			array( $this, 'shippo_order_meta_box' ),
			wc_get_page_screen_id( 'shop-order' ),
			'side',
			'default'
		);
	}

	function shippo_order_meta_box( $post ) {
		$order_id = is_a( $post, 'WC_Order' ) ? $post->get_id() : $post->ID;

		hippshipp_modal_form( $order_id );

		echo '<button type="button" class="button open-thickbox" data-id="' . esc_attr( $order_id ) . '">Get new shippo label</button>';

		if ( $check = hippshipp_helper::get_order_meta( $order_id, 'retrive_label' ) ) {
			echo '<a href="' . esc_url( $check->label_url ) . '" data-id="' . esc_attr( $order_id ) . '" target="_blank">Retrieve label</a>';
			echo '<a href="#" class="shippo-admin-tracking" data-id="' . esc_attr( $order_id ) . '">Order tracking</a>';
			hippshipp_tracking_modal_form( $order_id );
		}
	}

	function add_custom_order_column( $columns ) {
		$columns['shippo'] = 'Shippo';
		return $columns;
	}
	
	function populate_custom_order_column( $column, $post_id ) {
		if ( $column === 'shippo' ) {
			if ( is_object( $post_id ) ) {
				$post_id = $post_id->get_id();
			}
			
			hippshipp_modal_form( $post_id );
	
			if ( $check = hippshipp_helper::get_order_meta( $post_id, 'retrive_label' ) ) {
				echo '<a href="' . ( isset( $check->label_url ) ? esc_url( $check->label_url ) : '' ) . '" class="retrive-label" data-id="' . esc_attr( $post_id ) . '" target="_blank">Retrieve label</a>';
			}
			echo '<button type="button" class="button open-thickbox generate-label-btn" data-id="' . esc_attr( $post_id ) . '">Generate label</button>';
		}
	}
}

new hippshipp_hooks();