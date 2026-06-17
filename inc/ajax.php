<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_ajax {
	function __construct() {
		add_action( 'wp_ajax_ship_state_list', array( $this, 'ship_state_list' ) );
		add_action( 'wp_ajax_shippo_get_shipping', array( $this, 'shippo_get_shipping' ) );
		add_action( 'wp_ajax_nopriv_shippo_get_shipping', array( $this, 'shippo_get_shipping' ) );
		// validate modal order shippment address
		add_action( 'wp_ajax_ship_validate_address', array( $this, 'ship_validate_address' ) );
		// create shippment in admin order list
		add_action( 'wp_ajax_ship_create_shippment', array( $this, 'ship_create_shippment' ) );
		// create label in admin order list
		add_action( 'wp_ajax_ship_create_label', array( $this, 'ship_create_label' ) );
		// save shipping international declare custome order list
		add_action( 'wp_ajax_shippo_declare_custome', array( $this, 'shippo_declare_custome' ) );
	}

	function ship_state_list() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'nonce is not valid',
				)
			);
		}
		if ( empty( $_POST['country'] ) ) {
			wp_send_json( array( 'state' => 0 ) );
		}
		
		global $woocommerce;
		$opt = get_option( 'shippo_options' );
		$out = '';
		if ( $states = $woocommerce->countries->get_states( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) ) {
			$st_select = isset( $opt['state'] ) ? $opt['state'] : '';
			foreach ( $states as $st_name => $st_val ) {
				$out .= "<option value='$st_name' " . selected( $st_select, $st_name, false ) . ">$st_val</option>";
			}
		}
		wp_send_json(
			array(
				'state' => 1,
				'data'  => $out,
			)
		);
	}

	function shippo_get_shipping() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'nonce is not valid',
				)
			);
		}

		$args = array(
			'name'    => ( isset( $_POST['fname'] ) ? sanitize_text_field( wp_unslash( $_POST['fname'] ) ) : '' ) .
						( isset( $_POST['lname'] ) ? ' ' . sanitize_text_field( wp_unslash( $_POST['lname'] ) ) : '' ),
			'company' => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '',
			'street1' => isset( $_POST['adder'] ) ? sanitize_text_field( wp_unslash( $_POST['adder'] ) ) : '',
			'street2' => isset( $_POST['adder2'] ) ? sanitize_text_field( wp_unslash( $_POST['adder2'] ) ) : '',
			'city'    => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'state'   => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
			'zip'     => isset( $_POST['zipcd'] ) ? sanitize_text_field( wp_unslash( $_POST['zipcd'] ) ) : '',
			'country' => isset( $_POST['cntry'] ) ? sanitize_text_field( wp_unslash( $_POST['cntry'] ) ) : '',
			'phone'   => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'email'   => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		);

		if ( ! empty( $args['name'] ) && ! empty( $args['street1'] ) && ! empty( $args['zip'] ) &&
			! empty( $args['city'] ) && ! empty( $args['state'] ) && ! empty( $args['country'] ) &&
			! empty( $args['phone'] ) && ! empty( $args['email'] ) ) {
			$rates = hippshipp_helper::get_order_rate( $args );
			
			wp_send_json( array() );
		}
		die();
	}

	function ship_validate_address() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'nonce is not valid',
				)
			);
		}

		$shipp_api = new hippshipp_api();
		$validate  = $shipp_api->address(
			array(
				'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'street1' => isset( $_POST['adder'] ) ? sanitize_text_field( wp_unslash( $_POST['adder'] ) ) : '',
				'street2' => isset( $_POST['adder2'] ) ? sanitize_text_field( wp_unslash( $_POST['adder2'] ) ) : '',
				'city'    => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
				'state'   => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
				'zip'     => isset( $_POST['zip'] ) ? sanitize_text_field( wp_unslash( $_POST['zip'] ) ) : '',
				'country' => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '',
			)
		);
		if ( isset( $validate->object_id ) ) {
			wp_send_json(
				array(
					'status' => 1,
					'msg'    => 'Address is valid',
				)
			);
		} else {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'Address is invalid',
				)
			);
		}
	}

	function ship_create_shippment() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'nonce is not valid',
				)
			);
		}
		if ( ! isset( $_POST['post_id'] ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'post_id is not valid',
				)
			);
		}
		$data = array(
			'fname'    => isset( $_POST['fname'] ) ? sanitize_text_field( wp_unslash( $_POST['fname'] ) ) : '',
			'adder'    => isset( $_POST['adder'] ) ? sanitize_text_field( wp_unslash( $_POST['adder'] ) ) : '',
			'adder2'   => isset( $_POST['adder2'] ) ? sanitize_text_field( wp_unslash( $_POST['adder2'] ) ) : '',
			'city'     => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'state'    => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
			'zip'      => isset( $_POST['zip'] ) ? sanitize_text_field( wp_unslash( $_POST['zip'] ) ) : '',
			'country'  => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '',
			'parcel'   => isset( $_POST['parcel'] ) ? sanitize_text_field( wp_unslash( $_POST['parcel'] ) ) : '',
			'tplbox' => isset( $_POST['tplbox'] )
				? (
					is_array( $_POST['tplbox'] )
						? array_map( 'sanitize_text_field', wp_unslash( $_POST['tplbox'] ) )
						: sanitize_text_field( wp_unslash( $_POST['tplbox'] ) )
				)
				: '',
			'internat' => isset( $_POST['internat'] ) ? filter_var( wp_unslash( $_POST['internat'] ), FILTER_VALIDATE_BOOLEAN ) : '',
		);
		$postid = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '';
		$shipp  = hippshipp_helper::get_order_meta( $postid, 'shippment' );
		$meta   = get_post_meta( $postid );
		// if(empty($shipp[1]))
		// wp_send_json(['status'=>0,'msg'=>'This order is not valid ID']);
		$custom = '';
		if ( $x = hippshipp_helper::get_order_meta( $postid, 'custome_declare' ) ) {
			$custom = $x->object_id;
		}
		$shipp_api = new hippshipp_api();
		$args      = array(
			'name'    => $data['fname'],
			'street1' => $data['adder'],
			'street2' => $data['adder2'],
			'city'    => $data['city'],
			'state'   => $data['state'],
			'zip'     => $data['zip'],
			'country' => $data['country'],
			'phone'   => ( empty( $shipp[1]['phone'] ) ? $meta['_billing_phone'][0] : $shipp[1]['phone'] ),
			'email'   => ( empty( $shipp[1]['email'] ) ? $meta['_billing_email'][0] : $shipp[1]['email'] ),
		);
		$parcel    = empty( $data['parcel'] ) ? $data['tplbox'] : $data['parcel'];
		$result    = $shipp_api->shippment_simple( $args, $parcel, $custom );
		if ( isset( $result->object_id ) ) {
			if ( empty( $result->rates ) ) {
				wp_send_json(
					array(
						'status' => 0,
						'msg'    => $result->messages[0]->text,
					)
				);
			}
			hippshipp_helper::update_order_meta( $postid, 'shipping_rate_list', $result->rates );
			$address                = hippshipp_helper::get_order_meta( $postid, 'shippment' );
			$address                = empty( $address ) ? array( array(), array(), array() ) : $address;
			$address[1]['name']     = $args['name'];
			$address[1]['street1']  = $args['street1'];
			$address[1]['city']     = $args['city'];
			$address[1]['state']    = $args['state'];
			$address[1]['zip']      = $args['zip'];
			$address[1]['country']  = $args['country'];
			$address[2]['internat'] = $data['internat'];
			$address[2]['tplbox']   = $data['tplbox'];
			hippshipp_helper::update_order_meta( $postid, 'shippment', $address );
			wp_send_json(
				array(
					'status' => 1,
					'msg'    => hippshipp_helper::create_shipping_rate( $postid ),
				)
			);
		} else {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => reset( $result ),
				)
			);
		}
	}

	function ship_create_label() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'nonce is not valid',
				)
			);
		}
		if ( ! isset( $_POST['id'] ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'id is not valid',
				)
			);
		}
		$postid = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$postval = isset( $_POST['val'] ) ? sanitize_text_field( wp_unslash( $_POST['val'] ) ) : '';
		$opt     = get_option( 'shippo_options', array() );
		hippshipp_helper::update_order_meta( $postid, 'live_rate_id', $postval );
		$ship_api = new hippshipp_api();
		$label    = $ship_api->transactions( $postval );
		// $label = $ship_api->transactions_simple($postval);
		if ( ! isset( $label->tracking_number ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => reset( $label )[0],
				)
			);
		}
		if ( empty( $label->tracking_number ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => $label->messages[0]->text,
				)
			);
		}

		$rates = hippshipp_helper::get_order_meta( $postid, 'shipping_rate_list' );
		if ( ! empty( $postval ) && ! empty( $rates ) && is_array( $rates ) ) {
			foreach ( $rates as $rate ) {
				if ( isset( $rate->object_id ) && $rate->object_id === $postval ) {
					$carrier = isset( $rate->provider ) ? $rate->provider : '';
					hippshipp_helper::update_order_meta( $postid, 'live_rate_carrier', $carrier );
					break;
				}
			}
		}

		// hippshipp_helper::delete_order_meta( $postid, null, array( 'shippment' ) );
		hippshipp_helper::update_order_meta( $postid, 'retrive_label', $label );
		
		$order = wc_get_order( $postid );
		if ( empty( $opt['tracking_code'] ) ) {
			$order->add_order_note( "Tracking number is: $label->tracking_number", 0 );
		} else {
			$order->add_order_note( "Tracking number is: $label->tracking_number", 1 );
		}

		wp_send_json(
			array(
				'status'    => 1,
				'msg'       => 'The tracking number is: ' . $label->tracking_number,
				'label_url' => $label->label_url,
			)
		);
	}

	function shippo_declare_custome() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'shippo_nonce' ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'nonce is not valid',
				)
			);
		}
		if ( ! isset( $_POST['id'] ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => 'id is not valid',
				)
			);
		}
		$data = array(
			'ch_cert'   => isset( $_POST['ch_cert'] ) ? sanitize_text_field( wp_unslash( $_POST['ch_cert'] ) ) : '',
			'cert_name' => isset( $_POST['cert_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cert_name'] ) ) : '',
			'eel_pfc'   => isset( $_POST['eel_pfc'] ) ? sanitize_text_field( wp_unslash( $_POST['eel_pfc'] ) ) : '',
			'document'  => isset( $_POST['document'] ) ? sanitize_text_field( wp_unslash( $_POST['document'] ) ) : '',
			'incoterm'  => isset( $_POST['incoterm'] ) ? sanitize_text_field( wp_unslash( $_POST['incoterm'] ) ) : '',
			'delivery'  => isset( $_POST['delivery'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery'] ) ) : '',
			'items'     => isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '',  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);
		$postid = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$items  = array();
		$order  = wc_get_order( $postid );

		$ship_api = new hippshipp_api();
		$declare  = $ship_api->custome_declare(
			array(
				'certify'             => $data['ch_cert'],
				'certify_signer'      => $data['cert_name'],
				'eel_pfc'             => $data['eel_pfc'],
				'contents_type'       => $data['document'],
				'incoterm'            => $data['incoterm'],
				'non_delivery_option' => $data['delivery'],
				'items'               => $data['items'],
			)
		);
		if ( empty( $declare->object_id ) ) {
			wp_send_json(
				array(
					'status' => 0,
					'msg'    => reset( $declare ),
				)
			);
		}

		hippshipp_helper::update_order_meta( $postid, 'custome_declare', $declare );
		hippshipp_helper::update_order_meta( $postid, 'custome_declarex', $data );
		wp_send_json(
			array(
				'status' => 1,
				'msg'    => 'custome declare create success',
			)
		);
	}
}

new hippshipp_ajax();