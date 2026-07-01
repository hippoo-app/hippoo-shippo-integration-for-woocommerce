<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_ajax {

	function __construct() {
		add_action( 'wp_ajax_ship_state_list', [ $this, 'state_list' ] );
		add_action( 'wp_ajax_ship_validate_address', [ $this, 'validate_address' ] );
		add_action( 'wp_ajax_ship_declare_custome', [ $this, 'declare_custome' ] );
		add_action( 'wp_ajax_ship_create_shipment', [ $this, 'create_shipment' ] );
		add_action( 'wp_ajax_ship_create_label', [ $this, 'create_label' ] );
	}

	function state_list() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => 'Nonce is not valid' ] );
		}

		$country = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );
		if ( empty( $country ) ) {
			wp_send_json( [ 'status' => 0 ] );
		}

		$states = hippshipp_helper::get_states( $country );

		$out = '';
		foreach ( $states as $code => $name ) {
			$out .= "<option value='" . esc_attr( $code ) . "'>" . esc_html( $name ) . "</option>";
		}

		wp_send_json( [ 'status' => 1, 'data' => $out ] );
	}

	function validate_address() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => 'Nonce is not valid' ] );
		}

		$address = hippshipp_helper::prepare_shippo_address( $_POST );
		$result = hippshipp_api::address( $address );

		if ( isset( $result->object_id ) ) {
			wp_send_json( [ 'status' => 1, 'msg' => 'Address is valid' ] );
		} else {
			wp_send_json( [ 'status' => 0, 'msg' => 'Address is invalid' ] );
		}
	}

	function declare_custome() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => 'Nonce is not valid' ] );
		}

		$postid = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		if ( empty( $postid ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => 'Invalid order ID' ] );
		}

		$custome = hippshipp_helper::prepare_shippo_custome_declare( $_POST );
		$result = hippshipp_api::custome_declare( $custome );

		if ( empty( $result->object_id ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => hippshipp_helper::extract_error_message( $result ) ] );
		}

		hippshipp_helper::update_order_meta( $postid, 'custome_declare', $result );
		hippshipp_helper::update_order_meta( $postid, 'custome_declarex', $custome );
		
		wp_send_json( [ 'status' => 1, 'msg' => 'Customs declaration created successfully.' ] );
	}

	function create_shipment() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => 'Nonce is not valid' ] );
		}
		
		$postid = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		if ( empty( $postid ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => 'Invalid order ID' ] );
		}

		$is_international = filter_var( $_POST['internat'] ?? false, FILTER_VALIDATE_BOOLEAN );

		$custom = null;
		if ( $is_international ) {
			$custom_obj = hippshipp_helper::get_order_meta( $postid, 'custome_declare' );
			$custom = $custom_obj->object_id ?? null;
		}

		$meta = hippshipp_helper::get_order_shipping_address( $postid );
		$address_to = hippshipp_helper::prepare_shippo_address( $_POST, $meta );

		$parcel_input = $_POST['tplbox'] ?? $_POST['parcel'] ?? '';
		$parcel = hippshipp_helper::prepare_shippo_parcel( $parcel_input );

		$shippment = hippshipp_helper::get_order_meta( $postid, 'shippment' ) ?: [ [], [], [] ];
		$shippment[1] = array_merge( $shippment[1] ?? [], $address_to );
		$shippment[2]['internat'] = $is_international;
		$shippment[2]['tplbox'] = $parcel_input;
		hippshipp_helper::update_order_meta( $postid, 'shippment', $shippment );

		$result = hippshipp_api::shipments( $address_to, $parcel, $custom );

		if ( empty( $result->object_id ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => hippshipp_helper::extract_error_message( $result ) ] );
		}

		if ( empty( $result->rates ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => $result->messages[0]->text ?? 'No rates available' ] );
		}

		hippshipp_helper::update_order_meta( $postid, 'shipping_rate_list', $result->rates );

		wp_send_json( [
			'status' => 1,
			'rates' => $result->rates,
			'shipment_id' => $result->object_id
		] );
	}

	function create_label() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => 'Nonce is not valid' ] );
		}
		
		$postid = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$postval = sanitize_text_field( wp_unslash( $_POST['val'] ?? '' ) );

		if ( empty( $postid ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => 'Invalid order ID' ] );
		}
		
		$result = hippshipp_api::transactions( $postval );

		if ( ! isset( $result->tracking_number ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => hippshipp_helper::extract_error_message( $result ) ] );
		}

		if ( empty( $result->tracking_number ) ) {
			wp_send_json( [ 'status' => 0, 'msg' => $result->messages[0]->text ?? 'No label available' ] );
		}

		$rates = hippshipp_helper::get_order_meta( $postid, 'shipping_rate_list' );
		if ( ! empty( $postval ) && ! empty( $rates ) && is_array( $rates ) ) {
			foreach ( $rates as $rate ) {
				if ( isset( $rate->object_id ) && $postval === $rate->object_id ) {
					$result->carrier = ( $rate->provider ?? '' );
					$result->service = ( $rate->servicelevel->display_name ?? '' );
					break;
				}
			}
		}

		hippshipp_helper::update_order_meta( $postid, 'retrive_label', $result );
		
		$opt = get_option( 'shippo_options', [] );

		$order = wc_get_order( $postid );

		if ( empty( $opt['tracking_code'] ) ) {
			$order->add_order_note( "Tracking number is: $result->tracking_number", 0 );
		} else {
			$order->add_order_note( "Tracking number is: $result->tracking_number", 1 );
		}

		if ( ! empty( $opt['auto_status_change'] ) && ! empty( $opt['auto_status'] ) ) {
			$order->update_status( $opt['auto_status'], 'Shippo label created successfully.' );
		}

		wp_send_json( [
			'status'    => 1,
			'msg'       => 'The tracking number is: ' . $result->tracking_number,
			'label_url' => $result->label_url,
		] );
	}

}

new hippshipp_ajax();