<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_api {

	private static function request( $url, $data = [], $request = 'GET' ) {
		$opt = get_option( 'shippo_options', [] );
		$api = $opt['live_api'] ?? '';

		$head = [ 'Authorization' => "ShippoToken $api" ];
		if ( is_string( $data ) ) {
			$head['Content-Type'] = 'application/json';
		}

		$args = [
			'method'    => ! empty( $data ) ? 'POST' : $request,
			'headers'   => $head,
			'body'      => ! empty( $data ) ? $data : null,
			'timeout'   => 45,
			'sslverify' => false,
		];

		$response = wp_remote_request( "https://api.goshippo.com/{$url}", $args );

		if ( is_wp_error( $response ) ) {
			return (object)[ 'error' => $response->get_error_message() ];
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	public static function address( $args ) {
		return self::request( 'addresses', $args );
	}

	public static function address_validate( $object_id ) {
		return self::request( "addresses/$object_id/validate", [] );
	}

	public static function add_parcel( $args ) {
		return self::request( 'user-parcel-templates', json_encode( $args ) );
	}

	public static function list_parcel() {
		$resp = self::request( 'user-parcel-templates' );
		return ! empty( $resp->results ) ? $resp->results : [];
	}

	public static function delete_parcel( $object_id ) {
		return self::request( "user-parcel-templates/$object_id", '', 'DELETE' );
	}

	public static function custome_declare( $args ) {
		return self::request( 'customs/declarations', json_encode( $args ) );
	}

	public static function live_rates( $address_to, $line_item ) {
		$opt = get_option( 'shippo_options', [] );
		$frm = get_option( 'shippo_from' );
		
		$args = [
			'address_from' => $frm->object_id,
			'address_to'   => $address_to,
			'line_items'   => $line_item,
			'parcel'       => $opt['pack']['active'] ?? '',
		];

		$resp = self::request( 'live-rates', json_encode( $args ) );
		return ! empty( $resp->results ) ? $resp->results : [];
	}

	public static function shipments( $address_to, $parcel, $custom = '' ) {
		$frm = get_option( 'shippo_from' );

		$args = [
			'address_from' => $frm->object_id,
			'address_to'   => $address_to,
			'parcels'      => [ $parcel ],
			'async'        => false,
		];

		if ( ! empty( $custom ) ) {
			$args['customs_declaration'] = $custom;
		}

		return self::request( 'shipments', json_encode( $args ) );
	}

	public static function transactions( $rate, $file_type = 'PDF' ) {
		$opt = get_option( 'shippo_options', [] );
		$api = $opt['live_api'] ?? '';

		$args = [
			'rate'            => $rate,
			'label_file_type' => $file_type,
			'test'            => self::is_test_mode(),
			'async'           => false,
		];

		return self::request( 'transactions', json_encode( $args ) );
	}

	public static function track_shipment( $carrier, $tracking_number ) {
		$opt = get_option( 'shippo_options', [] );
		$api = $opt['live_api'] ?? '';

		if ( self::is_test_mode() ) {
			$carrier = 'shippo';
			$tracking_number = 'SHIPPO_DELIVERED';
		}

		$carrier         = rawurlencode( strtolower( trim( (string)$carrier ) ) );
		$tracking_number = rawurlencode( trim( (string)$tracking_number ) );

		return self::request( "tracks/$carrier/$tracking_number" );
	}

	public static function is_test_mode() {
		$opt = get_option( 'shippo_options', [] );
		$api = $opt['live_api'] ?? '';
		return ( stripos( $api, 'test' ) !== false );
	}

}