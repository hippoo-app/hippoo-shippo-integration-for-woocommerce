<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_helper {

	public static function admin_notice( $msg, $type = 'error' ) {
		add_action( 'admin_notices', function() use ( $msg, $type ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $msg ) );
		});
	}

	public static function format_date( $raw_date ) {
		$timestamp = strtotime( $raw_date );
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		return wp_date( $date_format . ' — ' . $time_format, $timestamp );
	}

	public static function get_countries() {
		global $woocommerce;
		return $woocommerce->countries->get_countries();
	}

	public static function get_states( $country ) {
		global $woocommerce;
		return $woocommerce->countries->get_states( $country ) ?: [];
	}

	public static function normalize_weight_unit( $unit ) {
		$unit = strtolower( trim( (string)$unit ) );
		$map  = [
			'lbs'    => 'lb',
			'pound'  => 'lb',
			'pounds' => 'lb',
			'lb'     => 'lb',
			'kg'     => 'kg',
			'g'      => 'g',
			'oz'     => 'oz',
		];
		return $map[ $unit ] ?? $unit;
	}

	public static function normalize_dimension_unit( $unit ) {
		$unit = strtolower( trim( (string)$unit ) );
		$map  = [
			'cm' => 'cm',
			'in' => 'in',
			'ft' => 'ft',
			'm'  => 'm',
			'mm' => 'mm',
			'yd' => 'yd',
		];
		return $map[ $unit ] ?? $unit;
	}

	public static function get_default_shipping_units() {
		return [
			'weight_unit'   => self::normalize_weight_unit( get_option( 'woocommerce_weight_unit', 'kg' ) ),
			'distance_unit' => self::normalize_dimension_unit( get_option( 'woocommerce_dimension_unit', 'cm' ) ),
		];
	}

	public static function get_order_shipping_address( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return [];
		}

		$shipp = hippshipp_helper::get_order_meta( $order_id, 'shippment' );

		$meta = [
			'name'      => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) 
						?: trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'company'   => $order->get_shipping_company() ?: $order->get_billing_company(),
			'street1'   => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
			'street2'   => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
			'city'      => $order->get_shipping_city() ?: $order->get_billing_city(),
			'state'     => $order->get_shipping_state() ?: $order->get_billing_state(),
			'zip'       => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
			'country'   => $order->get_shipping_country() ?: $order->get_billing_country(),
			'phone'     => $order->get_billing_phone(),
			'email'     => $order->get_billing_email(),
		];

		return self::prepare_shippo_address( $shipp[1] ?? [], $meta );
	}

	public static function prepare_shippo_address( $input, $meta = [] ) {
		return [
			'name'     => $input['recipient_name'] ?? $input['name'] ?? $input['fname'] ?? $meta['name'] ?? '',
			'company'  => $input['company'] ?? $meta['company'] ?? '',
			'street1'  => $input['street'] ?? $input['street1'] ?? $input['adder'] ?? $input['address_1'] ?? $input['address'] ?? $meta['street1'] ?? '',
			'street2'  => $input['street2'] ?? $input['adder2'] ?? $input['address_2'] ?? $meta['street2'] ?? '',
			'city'     => $input['city'] ?? $meta['city'] ?? '',
			'state'    => $input['state'] ?? $meta['state'] ?? '',
			'zip'      => $input['zip'] ?? $input['zipcode'] ?? $input['postcode'] ?? $meta['zip'] ?? '',
			'country'  => $input['country'] ?? $meta['country'] ?? '',
			'phone'    => $input['phone'] ?? $meta['phone'] ?? '',
			'email'    => $input['email'] ?? $meta['email'] ?? '',
		];
	}

	public static function prepare_shippo_template( $input ) {
		$default = self::get_default_shipping_units();

		if ( is_array( $input ) ) {
			return [
				'name'          => $input['name'] ?? '',
				'weight'        => $input['weight'] ?? 0,
				'width'         => $input['dimensions']['width'] ?? $input['width'] ?? 0,
				'height'        => $input['dimensions']['height'] ?? $input['height'] ?? 0,
				'length'        => $input['dimensions']['length'] ?? $input['length'] ?? 0,
				'distance_unit' => self::normalize_dimension_unit( $input['dimensions']['unit'] ?? $input['distance_unit'] ?? $default['distance_unit'] ),
				'weight_unit'   => self::normalize_weight_unit( $input['weight_unit'] ?? $default['weight_unit'] ),
			];
		}

		$parcel = null;

		if ( is_string( $input ) && ! empty( $input ) ) {
			$parcels = hippshipp_api::list_parcel();
			foreach ( $parcels as $p ) {
				if ( $input === $p->object_id ) {
					$parcel = (array)$p;
					break;
				}
			}
		}

		if ( empty( $parcel ) ) {
			$parcel = self::get_active_parcel();
		}

		if ( empty( $parcel ) ) {
			return [];
		}

		$parcel['distance_unit'] = self::normalize_dimension_unit( $parcel['distance_unit'] ?? $default['distance_unit'] );
		$parcel['weight_unit'] = self::normalize_weight_unit( $parcel['weight_unit'] ?? $default['weight_unit'] );

		return $parcel;
	}

	public static function prepare_shippo_parcel( $input = null ) {
		$default = self::get_default_shipping_units();

		if ( is_array( $input ) ) {
			return [
				'weight'        => $input['weight'] ?? 0,
				'width'         => $input['dimensions']['width'] ?? $input['width'] ?? 0,
				'height'        => $input['dimensions']['height'] ?? $input['height'] ?? 0,
				'length'        => $input['dimensions']['length'] ?? $input['length'] ?? 0,
				'distance_unit' => self::normalize_dimension_unit( $input['dimensions']['unit'] ?? $input['distance_unit'] ?? $default['distance_unit'] ),
				'mass_unit'     => self::normalize_weight_unit( $input['weight_unit'] ?? $input['mass_unit'] ?? $default['weight_unit'] ),
			];
		}

		$parcel = null;

		if ( is_string( $input ) && ! empty( $input ) ) {
			$parcels = hippshipp_api::list_parcel();
			foreach ( $parcels as $p ) {
				if ( $input === $p->object_id ) {
					$parcel = (array)$p;
					break;
				}
			}
		}

		if ( empty( $parcel ) ) {
			$parcel = self::get_active_parcel();
		}

		if ( empty( $parcel ) ) {
			return [];
		}

		$parcel['distance_unit'] = self::normalize_dimension_unit( $parcel['distance_unit'] ?? $default['distance_unit'] );
		$parcel['mass_unit'] = self::normalize_weight_unit( $parcel['weight_unit'] ?? $parcel['mass_unit'] ?? $default['weight_unit'] );

		unset( $parcel['weight_unit'] );

		return $parcel;
	}

	public static function prepare_shippo_custome_declare( $input ) {
		$default = self::get_default_shipping_units();

		$items = [];
		if ( ! empty( $input['items'] ) && is_array( $input['items'] ) ) {
			foreach ( $input['items'] as $item ) {
				$items[] = [
					'description'    => sanitize_text_field( $item['description'] ?? '' ),
					'quantity'       => absint( $item['quantity'] ?? 1 ),
					'value_amount'   => floatval( $item['value'] ?? $item['value_amount'] ?? 0 ),
					'net_weight'     => floatval( $item['weight'] ?? $item['net_weight'] ?? 0 ),
					'mass_unit'      => self::normalize_weight_unit( $item['weight_unit'] ?? $item['mass_unit'] ?? $default['weight_unit'] ),
					'value_currency' => $item['currency'] ?? $item['value_currency'] ?? get_option( 'woocommerce_currency' ),
					'origin_country' => strtoupper( sanitize_text_field( $item['origin_country'] ?? '' ) ),
					'tariff_number'  => sanitize_text_field( $item['tariff_number'] ?? '' ),
				];
			}
		}

		return [
			'certify'             => ! empty( $input['ch_cert'] ?? $input['certify'] ?? '' ),
			'certify_signer'      => sanitize_text_field( $input['cert_name'] ?? $input['certify_signer'] ?? '' ),
			'eel_pfc'             => strtoupper( sanitize_text_field( $input['eel_pfc'] ?? '' ) ),
			'contents_type'       => strtoupper( sanitize_text_field( $input['document'] ?? $input['contents_type'] ?? 'DOCUMENTS' ) ),
			'incoterm'            => strtoupper( sanitize_text_field( $input['incoterm'] ?? 'DDP' ) ),
			'non_delivery_option' => strtoupper( sanitize_text_field( $input['delivery'] ?? $input['non_delivery_option'] ?? 'RETURN' ) ),
			'items'               => $items,
		];
	}

	public static function is_international_shipment( $input ) {
		$opt = get_option( 'shippo_options' );
		$shop_country = $opt['country'] ?? '';

		if ( is_numeric( $input ) ) {
			$shipp = self::get_order_meta( $input, 'shippment' );

			if ( ! empty( $shipp[1]['country'] ) ) {
				$to_country = $shipp[1]['country'];
			} else {
				$address = self::get_order_shipping_address( $input );
				$to_country = $address['country'] ?? '';
			}
		} else if ( is_array( $input ) ) {
			$shipp = $input;
			$to_country = $shipp[1]['country'] ?? $shipp['country'] ?? '';
		} else {
			return false;
		}

		if ( ! empty( $shipp[2]['internat'] ) ) {
			return (bool)$shipp[2]['internat'];
		}

		if ( empty( $to_country ) ) {
			return false;
		}

		return strtoupper( $to_country ) !== strtoupper( $shop_country );
	}

	public static function get_active_parcel() {
		$opt = get_option( 'shippo_options' );
		if ( empty( $opt['pack']['active'] ) ) {
			return false;
		}

		$parcels = hippshipp_api::list_parcel();
		foreach ( $parcels as $parcel ) {
			if ( $opt['pack']['active'] === $parcel->object_id ) {
				return (array)$parcel;
			}
		}

		return false;
	}

	public static function update_active_parcel( $parcel_id ) {
		if ( empty( $parcel_id ) ) {
			return [ 'status' => false, 'msg' => 'Parcel ID is required' ];
		}

		$opt = get_option( 'shippo_options', [] );
		$parcels = hippshipp_api::list_parcel();

		$is_in_list = false;
		foreach ( $parcels as $parcel ) {
			if ( $parcel_id === $parcel->object_id ) {
				$is_in_list = true;
				break;
			}
		}

		if ( $is_in_list ) {
			if ( empty( $opt['pack']['active'] ) ) {
				$opt['pack']['active'] = $parcel_id;
				update_option( 'shippo_options', $opt );
				return [ 'status' => true, 'msg' => 'Active parcel set to new ID: ' . $parcel_id ];
			} else {
				return [ 'status' => false, 'msg' => 'Active parcel already exists.' ];
			}
		} else {
			if ( ! empty( $opt['pack']['active'] ) && $opt['pack']['active'] === $parcel_id ) {
				if ( ! empty( $parcels ) && count( $parcels ) > 0 ) {
					$new_active = $parcels[0]->object_id;
					$opt['pack']['active'] = $new_active;
					update_option( 'shippo_options', $opt );
					return [ 'status' => true, 'msg' => 'Active parcel updated to: ' . $new_active ];
				} else {
					$opt['pack']['active'] = '';
					update_option( 'shippo_options', $opt );
					return [ 'status' => true, 'msg' => 'No parcels left; active cleared.' ];
				}
			} else {
				return [ 'status' => false, 'msg' => 'This parcel was not active.' ];
			}
		}
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

		$data = [
			'order_id'  => sanitize_key( $order_id ),
			'meta_key'  => $meta_key,
			'meta_value' => maybe_serialize( $meta_value ),
		];
		$format = [ '%d', '%s', '%s' ];

		if ( $exists ) {
			return $wpdb->update(
				$table_name,
				$data,
				[ 'order_id' => $order_id, 'meta_key' => $meta_key ],
				$format,
				[ '%d', '%s' ]
			);
		} else {
			return $wpdb->insert( $table_name, $data, $format );
		}
		// phpcs:enable
	}

	public static function delete_order_meta( $order_id, $meta_key = null, $exclude_meta_keys = [] ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hippshipp_order_meta';

		// phpcs:disable WordPress.DB
		$where = [ 'order_id' => sanitize_key( $order_id ) ];
		$where_format = [ '%d' ];

		if ( ! is_null( $meta_key ) ) {
			$where['meta_key'] = $meta_key;
			$where_format[] = '%s';
		}

		if ( ! empty( $exclude_meta_keys ) ) {
			$exclude_meta_keys = array_map( 'esc_sql', (array)$exclude_meta_keys );
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
			$error = (array)$error;

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