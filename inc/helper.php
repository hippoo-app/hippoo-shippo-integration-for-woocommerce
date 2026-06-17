<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_helper {

	public static function format_shippo_date( $raw_date ) {
		$timestamp = strtotime( $raw_date );
    
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		
		$formatted = wp_date( $date_format . ' — ' . $time_format, $timestamp );
		
		return $formatted;
	}

	public static function admin_notice( $msg, $type = 'error' ) {
		add_action(
			'admin_notices',
			function () use ( $msg, $type ) {
				// notice-success
				?>
		<div class="notice notice-<?php $type; ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
				<?php
			}
		);
	}

	public static function get_parcel() {
		$opt = get_option( 'shippo_options' );
		if ( empty( $opt['pack']['active'] ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'Active parcel not found.',
				)
			);
		}
		if ( $parcels = ( new hippshipp_api() )->list_parcel() ) {
			foreach ( $parcels as $parcel ) {
				if ( $opt['pack']['active'] == $parcel->object_id ) {
					return (array) $parcel;
				}
			}
		}
	}

	public static function update_active_parcel( $parcel_id ) {
		if ( empty( $parcel_id ) ) {
			return array( 'status' => false, 'msg' => 'Parcel ID is required' );
		}

		$opt = get_option( 'shippo_options', array() );
		$shippo_api = new hippshipp_api();
		$parcels = $shippo_api->list_parcel();

		$is_in_list = false;
		foreach ( $parcels as $parcel ) {
			if ( $parcel->object_id === $parcel_id ) {
				$is_in_list = true;
				break;
			}
		}

		if ( $is_in_list ) {
			if ( empty( $opt['pack']['active'] ) ) {
				$opt['pack']['active'] = $parcel_id;
				update_option( 'shippo_options', $opt );
				return array( 'status' => true, 'msg' => 'Active parcel set to new ID: ' . $parcel_id );
			} else {
				return array( 'status' => false, 'msg' => 'Active parcel already exists' );
			}
		} else {
			if ( ! empty( $opt['pack']['active'] ) && $opt['pack']['active'] === $parcel_id ) {
				if ( ! empty( $parcels ) && count( $parcels ) > 0 ) {
					$new_active = $parcels[0]->object_id;
					$opt['pack']['active'] = $new_active;
					update_option( 'shippo_options', $opt );
					return array( 'status' => true, 'msg' => 'Active parcel updated to: ' . $new_active );
				} else {
					$opt['pack']['active'] = '';
					update_option( 'shippo_options', $opt );
					return array( 'status' => true, 'msg' => 'No parcels left; active cleared' );
				}
			} else {
				return array( 'status' => false, 'msg' => 'This parcel was not active' );
			}
		}
	}

	public static function get_shippment( $address = array() ) {
		$opt = get_option( 'shippo_options' );
		if ( empty( $opt['en_shippo'] ) or empty( $opt['shipping_rate'] ) ) {
			return '<div class="shippo"></div>';
		}

		$return = ( new hippshipp_api() )->shippment( $address );
		if ( empty( $return->object_id ) or empty( $return->rates ) ) {
			return '<div class="shippo-wrapper"></div>';
		}
		$total = WC()->cart->get_subtotal();
		WC()->session->set( 'shippo_shippment', $return );

		$out = '<div class="shippo-wrapper">';
		// phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
		foreach ( $return->rates as $i => $rate ) {
			$amount = $rate->amount + absint( $opt['ex_amount'] );
			$out   .= "
			<div class='shippo-inner' data-total='$total'>
				<div>
					<img src='" . esc_url( $rate->provider_image_75 ) . "' alt=''/>
				</div>
				<div class='data'>
					<input type='radio' name='shipp_rate' value='" . esc_attr( $rate->object_id ) . "' data-price='" . esc_attr( $amount ) . "'/>
					<label>" . esc_html( $rate->provider ) . '</label>
					' . ( empty( $rate->estimated_days ) ? '' : '<span>Delivery time: ' . esc_html( $rate->estimated_days ) . ' days</span>' ) . '
				</div>
				<div>
					' . esc_html( $amount ) . ' ' . esc_html( $rate->currency ) . '
				</div>
			</div>';
		}
		// phpcs:enable
		return "$out</div>";
	}

	public static function get_order_rate( $to_address ) {
		$items     = WC()->cart->get_cart();
		$line_item = array();
		foreach ( $items as $item => $values ) {
			$product     = $values['data'];
			$weight      = $product->get_weight();
			$line_item[] = array(
				'currency'    => get_option( 'woocommerce_currency' ),
				'quantity'    => $values['quantity'],
				'sku'         => $product->get_sku(),
				'title'       => $product->get_name(),
				'total_price' => (string) ( $values['line_total'] / $values['quantity'] ),
				'weight'      => ( empty( $weight ) ? '1' : $weight ),
				'weight_unit' => get_option( 'woocommerce_weight_unit' ),
			);
		}
		$result = ( new hippshipp_api() )->live_rate( $to_address, $line_item );
		if ( ! empty( $result->results ) ) {
			WC()->session->set( 'shippo_shippment', array( $result->results, $to_address ) );
			return $result->results;
		}
	}

	public static function get_live_rate_param( $meta ) {
		if ( isset( $meta['ship_to_different_address'] ) ) {
			return array(
				'name'    => "{$meta['shipping_first_name']} {$meta['shipping_last_name']}",
				'company' => ( empty( $meta['shipping_company'] ) ? '' : $meta['shipping_company'] ),
				'street1' => ( empty( $meta['shipping_address_1'] ) ? '' : $meta['shipping_address_1'] ),
				'street2' => ( empty( $meta['shipping_address_2'] ) ? '' : $meta['shipping_address_2'] ),
				'city'    => ( empty( $meta['shipping_city'] ) ? '' : $meta['shipping_city'] ),
				'state'   => ( empty( $meta['shipping_state'] ) ? '' : $meta['shipping_state'] ),
				'zip'     => ( empty( $meta['shipping_postcode'] ) ? '' : $meta['shipping_postcode'] ),
				'country' => ( empty( $meta['shipping_country'] ) ? '' : $meta['shipping_country'] ),
				'phone'   => ( empty( $meta['billing_phone'] ) ? '' : $meta['billing_phone'] ),
				'email'   => ( empty( $meta['billing_email'] ) ? '' : $meta['billing_email'] ),
			);
		} else {
			return array(
				'name'    => "{$meta['billing_first_name']} {$meta['billing_last_name']}",
				'company' => ( empty( $meta['billing_company'] ) ? '' : $meta['billing_company'] ),
				'street1' => ( empty( $meta['billing_address_1'] ) ? '' : $meta['billing_address_1'] ),
				'street2' => ( empty( $meta['billing_address_2'] ) ? '' : $meta['billing_address_2'] ),
				'city'    => ( empty( $meta['billing_city'] ) ? '' : $meta['billing_city'] ),
				'state'   => ( empty( $meta['billing_state'] ) ? '' : $meta['billing_state'] ),
				'zip'     => ( empty( $meta['billing_postcode'] ) ? '' : $meta['billing_postcode'] ),
				'country' => ( empty( $meta['billing_country'] ) ? '' : $meta['billing_country'] ),
				'phone'   => ( empty( $meta['billing_phone'] ) ? '' : $meta['billing_phone'] ),
				'email'   => ( empty( $meta['billing_email'] ) ? '' : $meta['billing_email'] ),
			);
		}
	}

	public static function get_order_shipping_address( $order_id ) {
		$shipp = self::get_order_meta( $order_id, 'shippment' );

		if ( ! empty( $shipp[1] ) && is_array( $shipp[1] ) ) {
			$addr = $shipp[1];
			if ( ! empty( $addr['name'] ) && ! empty( $addr['street1'] ) && ! empty( $addr['zip'] ) &&
				! empty( $addr['city'] ) && ! empty( $addr['state'] ) && ! empty( $addr['country'] ) ) {
				return $addr;
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array();
		}

		$order_data = $order->get_data();

		$meta = array(
			'ship_to_different_address' => ! empty( $order_data['shipping']['address_1'] ) && $order_data['shipping']['address_1'] !== $order_data['billing']['address_1'],
			'shipping_first_name'  => $order_data['shipping']['first_name']  ?: $order_data['billing']['first_name'],
			'shipping_last_name'   => $order_data['shipping']['last_name']   ?: $order_data['billing']['last_name'],
			'shipping_company'     => $order_data['shipping']['company']     ?: $order_data['billing']['company'],
			'shipping_address_1'   => $order_data['shipping']['address_1']   ?: $order_data['billing']['address_1'],
			'shipping_address_2'   => $order_data['shipping']['address_2']   ?: $order_data['billing']['address_2'],
			'shipping_city'        => $order_data['shipping']['city']        ?: $order_data['billing']['city'],
			'shipping_state'       => $order_data['shipping']['state']       ?: $order_data['billing']['state'],
			'shipping_postcode'    => $order_data['shipping']['postcode']    ?: $order_data['billing']['postcode'],
			'shipping_country'     => $order_data['shipping']['country']     ?: $order_data['billing']['country'],

			'billing_phone'        => $order_data['billing']['phone']        ?? '',
			'billing_email'        => $order_data['billing']['email']        ?? '',
			'billing_first_name'   => $order_data['billing']['first_name']   ?? '',
			'billing_last_name'    => $order_data['billing']['last_name']    ?? '',
			'billing_company'      => $order_data['billing']['company']      ?? '',
			'billing_address_1'    => $order_data['billing']['address_1']    ?? '',
			'billing_address_2'    => $order_data['billing']['address_2']    ?? '',
			'billing_city'         => $order_data['billing']['city']         ?? '',
			'billing_state'        => $order_data['billing']['state']        ?? '',
			'billing_postcode'     => $order_data['billing']['postcode']     ?? '',
			'billing_country'      => $order_data['billing']['country']      ?? '',
		);

		return self::get_live_rate_param( $meta );
	}

	public static function verify_esc_sql( $item ) {
		return is_array( $item ) ? array_map( 'esc_sql', $item ) : esc_sql( $item );
	}

	public static function create_shipping_rate( $order_id ) {
		$rates = hippshipp_helper::get_order_meta( $order_id, 'shipping_rate_list' );
		$check = hippshipp_helper::get_order_meta( $order_id, 'live_rate_id' );
		if ( empty( $rates ) ) {
			return;
		}

		$out = '<ul>';
		// phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
		foreach ( $rates as $rate ) {
			$checked = $rate->object_id == $check ? ' checked="checked"' : '';
			$out    .= "
			<li>
				<div class='radio'>
					<input type='radio' name='label' class='shipp_rate' data-id='" . esc_attr( $order_id ) . "' value='" . esc_attr( $rate->object_id ) . "'$checked/>
			</div>
			<div class='desc'>
				<label>" . esc_html( $rate->provider ) . "</label>
				<img src='" . esc_url( $rate->provider_image_75 ) . "' width='24' height='24'/>
				<br><small>" . esc_html( $rate->duration_terms ) . "</small>
			</div>
			<div class='currency'>
				<strong>" . esc_html( $rate->amount_local ) . ' ' . esc_html( $rate->currency_local ) . '</strong>
			</div>
			</li>';
		}
		// phpcs:enable
		return $out . '</ul>
			<div class="sh_under">
				<button type="submit" name="create_label" class="button label_btn" >Create Label</button>
				<span class="back">Back</span>
			</div>';
	}

	public static function get_order_meta( $order_id, $meta_key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hippshipp_order_meta';

		// phpcs:disable WordPress.DB
		$meta_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `meta_value` FROM `$table_name` WHERE `order_id` = %d AND `meta_key` = %s",
				sanitize_key( $order_id ),
				$meta_key
			)
		);
		// phpcs:enable

		return maybe_unserialize( $meta_value );
	}

	public static function update_order_meta( $order_id, $meta_key, $meta_value ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hippshipp_order_meta';

		// phpcs:disable WordPress.DB
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE order_id = %d AND meta_key = %s",
				$order_id,
				$meta_key
			)
		);

		$data = array(
			'order_id'  => sanitize_key( $order_id ),
			'meta_key'  => $meta_key,
			'meta_value' => maybe_serialize( $meta_value ),
		);
		$format = array( '%d', '%s', '%s' );

		if ( $exists ) {
			return $wpdb->update(
				$table_name,
				$data,
				array( 'order_id' => $order_id, 'meta_key' => $meta_key ),
				$format,
				array( '%d', '%s' )
			);
		} else {
			return $wpdb->insert( $table_name, $data, $format );
		}
		// phpcs:enable
	}

	public static function delete_order_meta( $order_id, $meta_key = null, $exclude_meta_keys = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hippshipp_order_meta';

		// phpcs:disable WordPress.DB
		$where = array( 'order_id' => sanitize_key( $order_id ) );
		$where_format = array( '%d' );

		if ( ! is_null( $meta_key ) ) {
			$where['meta_key'] = $meta_key;
			$where_format[] = '%s';
		}

		if ( ! empty( $exclude_meta_keys ) ) {
			$exclude_meta_keys = array_map( 'esc_sql', (array) $exclude_meta_keys );
			$placeholders = implode( ',', array_fill( 0, count( $exclude_meta_keys ), '%s' ) );
			$where_sql = "meta_key NOT IN ($placeholders)";
			$where_format = array_merge( $where_format, $exclude_meta_keys );
			$where_clause = $wpdb->prepare( $where_sql, $exclude_meta_keys );
			$where_sql = $wpdb->prepare( "order_id = %d", $order_id );
			if ( ! is_null( $meta_key ) ) {
				$where_sql .= $wpdb->prepare( " AND meta_key = %s", $meta_key );
			}
			$where_sql .= " AND $where_clause";
			$result = $wpdb->query( "DELETE FROM $table_name WHERE $where_sql" );
		} else {
			$result = $wpdb->delete(
				$table_name,
				$where,
				$where_format
			);
		}

		return $result;
		// phpcs:enable
	}

	public static function extract_error_message( $error ) {
		if ( empty( $error ) ) {
			return '';
		}

		if ( is_string( $error ) ) {
			return $error;
		}

		if ( is_array( $error ) || is_object( $error ) ) {
			$messages = [];
			$error = (array) $error;

			foreach ( $error as $key => $value ) {
				if ( is_string( $value ) ) {
					$messages[] = $value;
				} elseif ( is_array( $value ) || is_object( $value ) ) {
					$nested_message = self::extract_error_message( $value );
					if ( ! empty( $nested_message ) ) {
						$prefix = ( $key !== '__all__' && ! is_numeric( $key ) ) ? "$key: " : '';
						$messages[] = $prefix . $nested_message;
					}
				}
			}

			if ( empty( $messages ) ) {
				return '';
			}

			return implode( ', ', $messages );
		}

		return '';
	}
}