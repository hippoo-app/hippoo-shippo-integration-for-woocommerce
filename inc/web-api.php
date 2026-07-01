<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_web_api {

	function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_filter( 'woocommerce_rest_is_request_to_rest_api', [ $this, 'rest_use_wc_authentication' ] );
		add_filter( 'woocommerce_rest_prepare_shop_order_object', [ $this, 'prepare_shop_order_response' ], 10, 3 );
	}

	public function register_rest_routes() {
		register_rest_route( 'hippoo-shippo/v1', 'orders/(?P<order_id>\d+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_order' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_order' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			],
		] );

		register_rest_route( 'hippoo-shippo/v1', 'orders/(?P<order_id>\d+)/customs', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_customs' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_customs' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			],
		] );

		register_rest_route( 'hippoo-shippo/v1', 'orders/(?P<order_id>\d+)/rates', [
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'get_rates' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			],
		] );

		register_rest_route( 'hippoo-shippo/v1', 'orders/(?P<order_id>\d+)/shipment', [
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'get_label' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			],
		] );

		register_rest_route( 'hippoo-shippo/v1', 'templates', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_templates' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
				'args'                => [
					'page'     => [
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
					],
					'per_page' => [
						'type'        => 'integer',
						'default'     => 10,
						'minimum'     => 1,
						'maximum'     => 100,
					],
				],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_template' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			],
		] );

		register_rest_route( 'hippoo-shippo/v1', 'templates/(?P<template_id>\w+)', [
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_template' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
			],
		] );
	}

	public function rest_permission_check( $request ) {
        return current_user_can( 'manage_options' );
    }

	public function rest_use_wc_authentication( $condition ) {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		
		// Allow the plugin use wc authentication methods.
		$hippoo = ( false !== strpos( $request_uri, $rest_prefix . 'hippoo-shippo/v1' ) );
		
		return $condition || $hippoo;
    }

	public function prepare_shop_order_response( $response, $order, $request ) {
		if ( empty( $response->data ) ) {
			return $response;
		}

		$order_id = $order->get_id();
		$label = hippshipp_helper::get_order_meta( $order_id, 'retrive_label' );

		$response->data['shippo_url'] = 'https://hippoo.app/webapp/auth?redirect=shippoOrder&orderId=' . $order_id;
		$response->data['shippo_label_generated'] = $label ? true : false;

		return $response;
	}

	public function get_order( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', [ 'status' => 404 ] );
		}

		$units = hippshipp_helper::get_default_shipping_units();
		$shipp = hippshipp_helper::get_order_meta( $order_id, 'shippment' );
		$check = hippshipp_helper::get_order_meta( $order_id, 'retrive_label' );
		$is_inter = hippshipp_helper::is_international_shipment( $order_id );
		$address  = hippshipp_helper::get_order_shipping_address( $order_id );
		$template = hippshipp_helper::prepare_shippo_template( $shipp[2]['tplbox'] ?? '' );

		$response = [
			'is_international' => $is_inter,
			'currency'         => get_option( 'woocommerce_currency' ),
			'weight_unit'      => $units['weight_unit'],
			'dimension_unit'   => $units['distance_unit'],
			'label_generated'  => !!$check,
			'label_url'        => $check ? $check->label_url : null,
			'address'          => [
				'recipient_name' => $address['name'] ?? '',
				'street'         => $address['street1'] ?? '',
				'city'           => $address['city'] ?? '',
				'state'          => $address['state'] ?? '',
				'zip'            => $address['zip'] ?? '',
				'country'        => $address['country'] ?? '',
			],
			'package'          => [
				'id'          => $template['object_id'] ?? '',
				'name'        => $template['name'] ?? '',
				'weight'      => $template['weight'] ?? '',
				'weight_unit' => $template['weight_unit'] ?? '',
				'dimensions'   => [
					'length'  => $template['length'] ?? '',
					'width'   => $template['width'] ?? '',
					'height'  => $template['height'] ?? '',
					'unit'    => $template['distance_unit'] ?? '',
				]
			],
		];

		return new WP_REST_Response( $response, 200 );
	}
	
	public function update_order( $request ) {
		$order_id = $request->get_param( 'order_id' );
		$address  = $request->get_param( 'address' );
		$package  = $request->get_param( 'package' );
		$is_inter = $request->get_param( 'is_international' );

		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', [ 'status' => 404 ] );
		}

		if ( empty( $address ) || empty( $package ) ) {
			return new WP_Error( 'invalid_input_data', 'Address and Package are required.', [ 'status' => 400 ] );
		}

		$shipp = hippshipp_helper::get_order_meta( $order_id, 'shippment' );
		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declare' );

		$is_inter = filter_var( $is_inter ?? false, FILTER_VALIDATE_BOOLEAN );
		if ( $is_inter && empty( $custome->object_id ) ) {
			return new WP_Error( 'order_custome_not_found', 'First you need to complete the customs information.', [ 'status' => 400 ] );
		}

		$meta = hippshipp_helper::get_order_shipping_address( $order_id );
		$address_to = hippshipp_helper::prepare_shippo_address( $address, $meta );

		$template = $package['template'] ?? $package ?? $shipp[2]['tplbox'] ?? '';
		
		$shipp =  $shipp ?: [ [], [], [] ];
		$shipp[1] = array_merge( $shipp[1] ?? [], $address_to );
		$shipp[2]['internat'] = $is_inter;
		$shipp[2]['tplbox'] = $template;

		$update = hippshipp_helper::update_order_meta( $order_id, 'shippment', $shipp );

		if ( false === $update ) {
			return new WP_Error( 'order_update_failure', 'Failed to update the order.', [ 'status' => 400 ] );
		}

		$response = 'Order updated successfully.';

		return new WP_REST_Response( $response, 200 );
	}

	public function get_customs( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', [ 'status' => 404 ] );
		}

		$custom = hippshipp_helper::get_order_meta( $order_id, 'custome_declare' );
		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declarex' );

		$custome['items'] = $custome['items'] ?? $order->get_items() ?? [];
		$custome = hippshipp_helper::prepare_shippo_custome_declare( $custome );

		$response = array_merge( [ 'id' => $custom->object_id ?? '' ], $custome );

		return new WP_REST_Response( $response, 200 );
	}

	public function update_customs( $request ) {
		$order_id = $request->get_param( 'order_id' );
		$params   = $request->get_params();
		
		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', [ 'status' => 404 ] );
		}

		$custome = hippshipp_helper::prepare_shippo_custome_declare( $params );
		$result = hippshipp_api::custome_declare( $custome );

		if ( empty( $result->object_id ) ) {
			return new WP_Error( 'update_custome_failure', hippshipp_helper::extract_error_message( $result ), [ 'status' => 400 ] );
		}

		hippshipp_helper::update_order_meta( $order_id, 'custome_declare', $result );
		hippshipp_helper::update_order_meta( $order_id, 'custome_declarex', $custome );

		$response = [
			'custom_id' => $result->object_id,
		];

		return new WP_REST_Response( $response, 200 );
	}

	public function get_rates( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', [ 'status' => 404 ] );
		}

		$shipp = hippshipp_helper::get_order_meta( $order_id, 'shippment' );
		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declare' );
		$is_inter = hippshipp_helper::is_international_shipment( $order_id );

		if ( $is_inter && empty( $custome->object_id ) ) {
			return new WP_Error( 'rate_custome_not_found', 'First you need to complete the customs information.', [ 'status' => 400 ] );
		}

		$meta = hippshipp_helper::get_order_shipping_address( $order_id );
		$address_to = hippshipp_helper::prepare_shippo_address( $shipp[1], $meta );
		$parcel = hippshipp_helper::prepare_shippo_parcel( $shipp[2]['tplbox'] );
		$custom = $custome->object_id ?? null;

		$result = hippshipp_api::shipments( $address_to, $parcel, $custom );

		if ( empty( $result->object_id ) ) {
			return new WP_Error( 'get_rates_failure', hippshipp_helper::extract_error_message( $result ), [ 'status' => 400 ] );
		}

		if ( empty( $result->rates ) ) {
			return new WP_Error( 'get_rates_failure', $result->messages[0]->text ?? 'No rates available', [ 'status' => 400 ] );
		}

		hippshipp_helper::update_order_meta( $order_id, 'shipping_rate_list', $result->rates );

		$response = [];
		foreach ( $result->rates as $rate ) {
			$response[] = [
				'id'            => $rate->object_id,
				'carrier'       => $rate->provider ?? '',
				'service'       => $rate->servicelevel->display_name ?? '',
				'logo_url'      => $rate->provider_image_200 ?? '',
				'rate'          => $rate->amount_local ?? $rate->amount ?? '' ,
				'currency'      => $rate->currency_local ?? $rate->currency ?? '',
				'delivery_time' => $rate->duration_terms ?? '',
			];
		}

		return new WP_REST_Response( $response, 200 );
	}

	public function get_label( $request ) {
		$order_id = $request->get_param( 'order_id' );
		$rate_id  = $request->get_param( 'selected_rate_id' );

		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', [ 'status' => 404 ] );
		}

		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declare' );
		$is_inter = hippshipp_helper::is_international_shipment( $order_id );

		if ( $is_inter && empty( $custome->object_id ) ) {
			return new WP_Error( 'label_custome_not_found', 'First you need to complete the customs information.', [ 'status' => 400 ] );
		}

		$result = hippshipp_api::transactions( $rate_id );

		if ( ! isset( $result->tracking_number ) ) {
			return new WP_Error( 'get_label_failure', hippshipp_helper::extract_error_message( $result ), [ 'status' => 400 ] );
		}

		if ( empty( $result->tracking_number ) ) {
			return new WP_Error( 'get_label_failure', $result->messages[0]->text ?? 'No label available', [ 'status' => 400 ] );
		}

		$rates = hippshipp_helper::get_order_meta( $order_id, 'shipping_rate_list' );
		if ( ! empty( $rate_id ) && ! empty( $rates ) && is_array( $rates ) ) {
			foreach ( $rates as $rate ) {
				if ( isset( $rate->object_id ) && $rate_id === $rate->object_id ) {
					$result->carrier = ( $rate->provider ?? '' );
					$result->service = ( $rate->servicelevel->display_name ?? '' );
					break;
				}
			}
		}

		hippshipp_helper::update_order_meta( $order_id, 'retrive_label', $result );

		$opt = get_option( 'shippo_options', [] );
		
		if ( empty( $opt['tracking_code'] ) ) {
			$order->add_order_note( "Tracking number is: $result->tracking_number", 0 );
		} else {
			$order->add_order_note( "Tracking number is: $result->tracking_number", 1 );
		}

		if ( ! empty( $opt['auto_status_change'] ) && ! empty( $opt['auto_status'] ) ) {
			$order->update_status( $opt['auto_status'], 'Shippo label created successfully.' );
		}

		$response = [
			'shipment_id' => $result->object_id,
			'label_url' => $result->label_url ?? '',
			'tracking_number' => $result->tracking_number ?? '',
			'carrier' => $result->carrier ?? '',
			'service' => $result->service ?? '',
		];

		return new WP_REST_Response( $response, 200 );
	}

	public function get_templates( $request ) {
		$parcels = hippshipp_api::list_parcel();

		if ( empty( $parcels ) ) {
			return new WP_Error( 'templates_not_found', 'No templates found.', [ 'status' => 404 ] );
		}

		$page = max( 1, $request['page'] );
		$per_page = max( 1, min( 100, $request['per_page'] ) );
		$offset = ( $page - 1 ) * $per_page;

		$total = count( $parcels );
		$paginated_parcels = array_slice( $parcels, $offset, $per_page );

		$templates = [];
		foreach ( $paginated_parcels as $parcel ) {
			$templates[] = [
				'id'          => $parcel->object_id ?? '',
				'name'        => $parcel->name ?? '',
				'weight'      => $parcel->weight ?? '',
				'weight_unit' => $parcel->weight_unit ?? '',
				'dimensions' => [
					'length'  => $parcel->length ?? '',
					'width'   => $parcel->width ?? '',
					'height'  => $parcel->height ?? '',
					'unit'    => $parcel->distance_unit ?? '',
				],
			];
		}

		$response = rest_ensure_response( $templates );

		$response->header( 'X-WP-Total', (int)$total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}

	public function create_template( $request ) {
		$params = $request->get_params();
		
		$parcel = hippshipp_helper::prepare_shippo_template( $params );
		$template = hippshipp_api::add_parcel( $parcel );

		if( ! isset( $template->object_id ) ) {
			return new WP_Error( 'create_template_failure', 'Template is not valid.', [ 'status' => 400 ] );
		}

		hippshipp_helper::update_active_parcel( $template->object_id );

		$response = [
			'id'          => $template->object_id,
			'name'        => $parcel['name'],
			'weight'      => $parcel['weight'],
			'weight_unit' => $parcel['weight_unit'],
			'dimensions' => [
				'length'  => $parcel['length'],
				'width'   => $parcel['width'],
				'height'  => $parcel['height'],
				'unit'    => $parcel['distance_unit'],
			],
		];

		return new WP_REST_Response( $response, 200 );
	}

	public function delete_template( $request ) {
		$template_id = $request->get_param( 'template_id' );

		$template = hippshipp_api::delete_parcel( $template_id );

		if ( isset( $template->detail ) ) {
			return new WP_Error( 'delete_template_failure', $template->detail, [ 'status' => 400 ] );
		}

		hippshipp_helper::update_active_parcel( $template_id );

		$response = 'Template deleted successfully.';

		return new WP_REST_Response( $response, 200 );
	}

}

new hippshipp_web_api();