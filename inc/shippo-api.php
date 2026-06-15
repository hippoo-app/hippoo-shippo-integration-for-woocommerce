<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_api {

	private $http_code;

	private function request( $url, $data = array(), $request = 'GET' ) {
		$opt  = get_option( 'shippo_options', array() );
		$api  = $opt['live_api'];
		$head = array( 'Authorization' => "ShippoToken $api" );
		if ( is_string( $data ) ) {
			$head['Content-Type'] = 'application/json';
		}

		$args = array(
			'method'    => ! empty( $data ) ? 'POST' : $request,
			'headers'   => $head,
			'body'      => ! empty( $data ) ? $data : null,
			'timeout'   => 45,
			'sslverify' => false,
		);

		$response = wp_remote_request( "https://api.goshippo.com/$url", $args );

		if ( is_wp_error( $response ) ) {
			return (object) array( 'error' => $response->get_error_message() );
		}

		$this->http_code = wp_remote_retrieve_response_code( $response );
		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	public function address( $data ) {
		$result = self::request( 'addresses', $data );
		return $result;
	}

	public function address_verify( $object_id ) {
		$result = self::request( "addresses/$object_id/validate", array() );
		return $result;
	}

	public function carrier() {
		$out  = array();
		$page = 0;
		do {
			$result = self::request( 'carrier_accounts' . ( ++$page == 1 ? '' : "?page=$page" ) );
			$out    = array_merge( $out, $result->results );
		} while ( ! empty( $result->next ) );
		return $out;
	}

	public function shippment( $to_adder, $carrie = array(), $custom = '' ) {
		$parcel = hippshipp_helper::get_parcel();
		$frm    = get_option( 'shippo_from' );
		$args = array(
			'address_from' => $frm->object_id,
			'address_to'   => $to_adder,
			'parcels'      => array(
				'width'         => $parcel['width'],
				'height'        => $parcel['height'],
				'length'        => $parcel['length'],
				'weight'        => $parcel['weight'],
				'mass_unit'     => $parcel['weight_unit'] ?? get_option( 'woocommerce_weight_unit' ),
				'distance_unit' => $parcel['distance_unit'] ?? get_option( 'woocommerce_dimension_unit' ),
			),
			'async'        => false,
		);

		if ( ! empty( $carrie ) ) {
			$args['carrier_accounts'] = $carrie;
		}

		if ( ! empty( $custom ) ) {
			$args['customs_declaration'] = $custom;
		}
		
		$val = self::request( 'shipments', json_encode( $args ) );
		return $val;
	}

	public function shippment_simple( $to_address, $parcel = '', $custom = '' ) {
		$frm = get_option( 'shippo_from' );
		$opt = get_option( 'shippo_options', array() );
		
		if ( is_array( $parcel ) ) {
			$parcel['distance_unit'] = $parcel['distance_unit'] ?? get_option( 'woocommerce_dimension_unit' );
			$parcel['mass_unit']     = $parcel['weight_unit'] ?? get_option( 'woocommerce_weight_unit' );
		} else {
			$parobj = hippshipp_helper::get_parcel();
			$parcel = array(
				'width'         => $parobj['width'],
				'height'        => $parobj['height'],
				'length'        => $parobj['length'],
				'weight'        => $parobj['weight'],
				'mass_unit'     => $parobj['weight_unit'] ?? get_option( 'woocommerce_weight_unit' ),
				'distance_unit' => $parobj['distance_unit'] ?? get_option( 'woocommerce_dimension_unit' ),
			);
		}

		$parcel = empty( $parcel ) ? $opt['pack']['active'] : $parcel;
		$args   = array(
			'address_from' => $frm->object_id,
			'address_to'   => $to_address,
			'parcels'      => array( $parcel ),
			'async'        => false,
		);

		if ( ! empty( $custom ) ) {
			$args['customs_declaration'] = $custom;
		}

		$val = self::request( 'shipments', json_encode( $args ) );
		return $val;
	}

	public function transactions( $rate, $custom = '', $file_type = 'PDF' ) {
		$data = array(
			'rate'            => $rate,
			'label_file_type' => $file_type,
			'async'           => false,
			'test'            => true,
		);

		if ( ! empty( $custom ) ) {
			$data['custom'] = $custom;
		}

		$val = self::request( 'transactions', json_encode( $data ) );
		return $val;
	}

	public function transactions_simple( $rate ) {
		$val = self::request( "transactions/$rate", array() );
		return $val;
	}

	public function add_parcel( $args ) {
		if ( isset( $args['weight_unit'] ) ) {
			$args['weight_unit'] = strtolower( trim( $args['weight_unit'] ) );
		}

		if ( isset( $args['distance_unit'] ) ) {
			$args['distance_unit'] = strtolower( trim( $args['distance_unit'] ) );
		}

		$resp = self::request( 'user-parcel-templates', json_encode( $args ) );
		return $resp;
	}

	public function list_parcel() {
		$resp = self::request( 'user-parcel-templates' );
		return isset( $resp->results ) ? $resp->results : false;
	}

	public function delete_parcel( $parcel_id ) {
		return self::request( "user-parcel-templates/$parcel_id", '', 'DELETE' );
	}

	public function live_rate( $to_address, $line_item ) {
		$frm = get_option( 'shippo_from' );
		$opt = get_option( 'shippo_options', array() );

		return self::request(
			'live-rates',
			json_encode(
				array(
					'address_from' => $frm->object_id,
					'address_to'   => $to_address,
					'line_items'   => $line_item,
					'parcel'       => $opt['pack']['active'],
				)
			)
		);
	}

	public function custome_declare( $params ) {
		return self::request( 'customs/declarations', json_encode( $params ) );
	}

	public function track_shipment( $carrier, $tracking_number ) {
		$carrier         = trim( (string) $carrier );
		$tracking_number = trim( (string) $tracking_number );

		if ( empty( $carrier ) || empty( $tracking_number ) ) {
			return (object) array(
				'error' => 'Missing carrier or tracking number.',
			);
		}

		$opt = get_option( 'shippo_options', array() );
		$api = $opt['live_api'];

		if ( stripos( $api, 'test' ) !== false ) {
			$carrier = 'shippo';
			$tracking_number = 'SHIPPO_DELIVERED';
		}

		$carrier         = rawurlencode( strtolower( $carrier ) );
		$tracking_number = rawurlencode( $tracking_number );

		return self::request( "tracks/$carrier/$tracking_number" );
	}

}