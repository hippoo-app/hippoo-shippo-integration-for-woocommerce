<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_web_api {

	protected $namespace = 'hippoo-shippo/v1';

	protected $shippo_options = array();

	function __construct() {
		add_filter( 'woocommerce_rest_is_request_to_rest_api', array( $this, 'is_request_to_rest_api' ) );
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'prepare_shop_order_response' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		$this->shippo_options = get_option( 'shippo_options', array() );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, 'orders/(?P<order_id>\d+)', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_order' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_order' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, 'orders/(?P<order_id>\d+)/customs', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_customs' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_customs' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, 'orders/(?P<order_id>\d+)/rates', array(
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'get_rates' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, 'orders/(?P<order_id>\d+)/shipment', array(
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'get_label' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, 'templates', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_templates' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'page'     => array(
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
					),
					'per_page' => array(
						'type'        => 'integer',
						'default'     => 10,
						'minimum'     => 1,
						'maximum'     => 100,
					),
				),
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_template' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, 'templates/(?P<template_id>\w+)', array(
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_template' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
		) );
	}

	public function get_order( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$shipp = hippshipp_helper::get_order_meta( $order_id, 'shippment' );

		if ( empty( $shipp ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', array( 'status' => 404 ) );
		}

		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declarex' );

		$is_inter = false;
		if ( isset( $shipp[2]['internat'] ) && $shipp[2]['internat'] && ! empty( $custome ) ) {
			$is_inter = true;
		} elseif ( ! isset( $shipp[2]['internat'] ) ) {
			$is_inter = ( ! empty( $shipp[1] ) && $this->shippo_options['country'] !== $shipp[1]['country'] );
		}

		$address = array();
		if ( count( $shipp ) > 1 ) {
			$address = $shipp[1];
		}

		$template = array();
		if ( isset( $shipp[2]['tplbox'] ) ) {
			if ( is_array( $shipp[2]['tplbox'] ) ) {
				$template = array(
					'weight' => $shipp[2]['tplbox']['weight'] ?? '',
					'length' => $shipp[2]['tplbox']['length'] ?? '',
					'width'  => $shipp[2]['tplbox']['width'] ?? '',
					'height' => $shipp[2]['tplbox']['height'] ?? '',
					'weight_unit'   => $shipp[2]['tplbox']['weight_unit'] ?? get_option( 'woocommerce_weight_unit' ),
					'distance_unit' => $shipp[2]['tplbox']['distance_unit'] ?? get_option( 'woocommerce_dimension_unit' ),
				);
			} else {
				$template = $this->get_template_by_id( $shipp[2]['tplbox'] );
			}
		} elseif ( $default_tpl = $this->shippo_options['pack']['active'] ) {
			$template = $this->get_template_by_id( $default_tpl );
		}

		$check = hippshipp_helper::get_order_meta( $order_id, 'retrive_label' );

		$response = array(
			'is_international' => $is_inter,
			'currency'         => get_option( 'woocommerce_currency' ),
			'weight_unit'      => get_option( 'woocommerce_weight_unit' ),
			'dimension_unit'   => get_option( 'woocommerce_dimension_unit' ),
			'label_generated'  => !!$check,
			'label_url'        => $check ? $check->label_url : null,
			'address'          => array(
				'recipient_name' => $address['name'] ?? '',
				'street'         => $address['street1'] ?? '',
				'city'           => $address['city'] ?? '',
				'state'          => $address['state'] ?? '',
				'zip'            => $address['zip'] ?? '',
				'country'        => $address['country'] ?? '',
			),
			'package'          => array(
				'id'          => $template['object_id'] ?? '',
				'name'        => $template['name'] ?? '',
				'weight'      => $template['weight'] ?? '',
				'weight_unit' => $template['weight_unit'] ?? '',
				'dimensions'   => array(
					'length'  => $template['length'] ?? '',
					'width'   => $template['width'] ?? '',
					'height'  => $template['height'] ?? '',
					'unit'    => $template['distance_unit'] ?? '',
				)
			),
		);

		return new WP_REST_Response( $response, 200 );
	}
	
	public function update_order( $request ) {
		$order_id = $request->get_param( 'order_id' );
		$address  = $request->get_param( 'address' );
		$package  = $request->get_param( 'package' );
		$is_inter = $request->get_param( 'is_international' );

		$shipp = hippshipp_helper::get_order_meta( $order_id, 'shippment' );
		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declare' );

		if ( empty( $shipp ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', array( 'status' => 404 ) );
		}

		if ( $is_inter && empty( $custome ) ) {
			return new WP_Error( 'custome_not_found', 'First you need to complete the customs information.', array( 'status' => 400 ) );
		}

		if ( empty( $address ) || empty( $package ) ) {
			return new WP_Error( 'invalid_input_data', 'Address and Package are required.', array( 'status' => 400 ) );
		}

		if ( ! empty( $package['template'] ) ) {
			$template = $this->get_template_by_id( $package['template'] );

			if ( empty( $template ) ) {
				return new WP_Error( 'invalid_template', 'Template not found.', array( 'status' => 400 ) );
			}
		}

		$shipp[1]['name']    = $address['recipient_name'] ?? $shipp[1]['name'];
		$shipp[1]['street1'] = $address['street'] ?? $shipp[1]['street1'];
		$shipp[1]['city']    = $address['city'] ?? $shipp[1]['city'];
		$shipp[1]['state']   = $address['state'] ?? $shipp[1]['state'];
		$shipp[1]['zip']     = $address['zip'] ?? $shipp[1]['zip'];
		$shipp[1]['country'] = $address['country'] ?? $shipp[1]['country'];

		$shipp[2]['tplbox']  = $package['template'] ?? $shipp[2]['tplbox'];

		if ( empty( $package['template'] ) ) {
			$shipp[2]['tplbox'] = array(
				'weight'        => $package['weight'] ?? $shipp[2]['tplbox']['weight'],
				'weight_unit'   => $package['weight_unit'] ?? $shipp[2]['tplbox']['weight_unit'],
				'length'        => $package['dimensions']['length'] ?? $shipp[2]['tplbox']['length'],
				'width'         => $package['dimensions']['width'] ?? $shipp[2]['tplbox']['width'],
				'height'        => $package['dimensions']['height'] ?? $shipp[2]['tplbox']['height'],
				'distance_unit' => $package['dimensions']['unit'] ?? $shipp[2]['tplbox']['distance_unit'],
			);
		}

		$shipp[2]['internat'] = $is_inter ?? $shipp[2]['internat'];

		$update = hippshipp_helper::update_order_meta( $order_id, 'shippment', $shipp );

		if ( false === $update ) {
			return new WP_Error( 'order_update_failure', 'Failed to update the order.', array( 'status' => 400 ) );
		}

		$response = 'Order updated successfully.';

		return new WP_REST_Response( $response, 200 );
	}

	public function get_customs( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$custom = hippshipp_helper::get_order_meta( $order_id, 'custome_declare' );
		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declarex' );

		$response = array(
			'id'                  => $custom->object_id ?? '',
			'items'               => $this->get_customs_order_items( $order_id ),
			'certify'             => ( isset( $custome['ch_cert'] ) && $custome['ch_cert'] ) ? true : false,
			'certify_signer'      => $custome['cert_name'] ?? '',
			'eel_pfc'             => $custome['eel_pfc'] ?? '',
			'contents_type'       => $custome['document'] ?? '',
			'non_delivery_option' => $custome['delivery'] ?? '',
			'incoterm'            => $custome['incoterm'] ?? '',
			'shipment_purpose'    => $custome['shipment_purpose'] ?? 'sale',
		);

		return new WP_REST_Response( $response, 200 );
	}

	public function update_customs( $request ) {
		$order_id = $request->get_param( 'order_id' );
		$params   = $request->get_params();
		
		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', array( 'status' => 404 ) );
		}

		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declarex' );

		if ( ! empty( $params['items'] ) ) {
			foreach ( $params['items'] as &$param ) {
				$param['value_amount']   = $param['value'];
				$param['net_weight']     = $param['weight'];
				$param['mass_unit']      = $param['weight_unit'] ?? get_option( 'woocommerce_weight_unit' );
				$param['value_currency'] = $param['currency'] ?? get_option( 'woocommerce_currency' );

				unset( $param['value'], $param['weight'], $param['currency'], );
			}
		}

		$data = array(
			'items'            => $params['items'] ?? $custome['items'] ?? array(),
			'ch_cert'          => $params['certify'] ?? $custome['ch_cert'] ?? '',
			'cert_name'        => $params['certify_signer'] ?? $custome['cert_name'] ?? '',
			'eel_pfc'          => strtoupper( $params['eel_pfc'] ?? $custome['eel_pfc'] ?? '' ),
			'document'         => strtoupper( $params['contents_type'] ?? $custome['document'] ?? '' ),
			'delivery'         => strtoupper( $params['non_delivery_option'] ?? $custome['delivery'] ?? '' ),
			'incoterm'         => strtoupper( $params['incoterm'] ?? $custome['incoterm'] ?? '' ),
			'shipment_purpose' => $params['shipment_purpose'] ?? $custome['shipment_purpose'] ?? 'sale',
		);

		$declare = ( new hippshipp_api() )->custome_declare( 
			array(
				'items'               => $data['items'],
				'certify'             => $data['ch_cert'],
				'certify_signer'      => $data['cert_name'],
				'eel_pfc'             => $data['eel_pfc'],
				'contents_type'       => $data['document'],
				'non_delivery_option' => $data['delivery'],
				'incoterm'            => $data['incoterm'],
				'shipment_purpose'    => $data['shipment_purpose'],
			)
		);

		if( ! isset( $declare->object_id ) ) {
			$error_message = hippshipp_helper::extract_error_message( reset( $declare ) );
			return new WP_Error( 'update_custome_failure', $error_message ?: 'Unknown error', array( 'status' => 400 ) );
		}

		hippshipp_helper::update_order_meta( $order_id, 'custome_declare', $declare );
		hippshipp_helper::update_order_meta( $order_id, 'custome_declarex', $data );

		$response = array(
			'custom_id' => $declare->object_id,
		);

		return new WP_REST_Response( $response, 200 );
	}

	public function get_rates( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$shipp = hippshipp_helper::get_order_meta( $order_id, 'shippment' );
		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declare' );

		if ( empty( $shipp ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', array( 'status' => 404 ) );
		}

		$is_inter = false;
		if ( isset( $shipp[2]['internat'] ) && $shipp[2]['internat'] && ! empty( $custome ) ) {
			$is_inter = true;
		} elseif ( ! isset( $shipp[2]['internat'] ) ) {
			$is_inter = ( ! empty( $shipp[1] ) && $this->shippo_options['country'] !== $shipp[1]['country'] );
		}

		if ( $is_inter && empty( $custome ) ) {
			return new WP_Error( 'custome_not_found', 'First you need to complete the customs information.', array( 'status' => 400 ) );
		}

		$shipp[2]['tplbox'] = $shipp[2]['tplbox'] ?? $this->shippo_options['pack']['active'] ?? '';

		$validation_result = $this->validate_shippment_required_fields( $shipp );
		
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$custom = ( ! empty( $custome ) ) ? $custome->object_id : '';

		$shippment = ( new hippshipp_api() )->shippment_simple( $shipp[1], $shipp[2]['tplbox'] ?? '', $custom );

		if ( ! isset( $shippment->object_id ) ) {
			$error_message = hippshipp_helper::extract_error_message( $shippment );
			return new WP_Error( 'shippment_simple_failure', $error_message ?: 'Unknown error', array( 'status' => 400 ) );
		}

		$rates = $shippment->rates;

		if ( empty( $rates ) ) {
			$error_message = hippshipp_helper::extract_error_message( $shippment->messages[0]->text ?? 'No rates available' );
			return new WP_Error( 'shippment_rates_failure', $error_message, array( 'status' => 400 ) );
		}

		hippshipp_helper::update_order_meta( $order_id, 'shipping_rate_list', $rates );

		if ( empty( $rates ) ) {
			return new WP_Error( 'rates_not_found', 'No rates data found.', array( 'status' => 404 ) );
		}

		$response = array();

		foreach ( $rates as $rate ) {
			$response[] = array(
				'id'            => $rate->object_id,
				'carrier'       => $rate->provider,
				'service'       => $rate->servicelevel->display_name,
				'logo_url'      => $rate->provider_image_75,
				'rate'          => empty( $rate->amount_local ) ? $rate->amount : $rate->amount_local,
				'currency'      => empty( $rate->currency_local ) ? $rate->currency : $rate->currency_local,
				'delivery_time' => $rate->duration_terms,
			);
		}

		return new WP_REST_Response( $response, 200 );
	}

	public function get_label( $request ) {
		$order_id   = $request->get_param( 'order_id' );
		$rate_id    = $request->get_param( 'selected_rate_id' );
		$customs_id = $request->get_param( 'customs_id' ) ?? '';

		$shipp = hippshipp_helper::get_order_meta( $order_id, 'shippment' );
		$rates = hippshipp_helper::get_order_meta( $order_id, 'shipping_rate_list' );
		
		if ( empty( $shipp ) ) {
			return new WP_Error( 'order_not_found', 'No order data found.', array( 'status' => 404 ) );
		}

		if ( isset( $shipp[2]['internat'] ) && $shipp[2]['internat'] && empty( $customs_id ) ) {
			return new WP_Error( 'invalid_customs_id', 'The customs_id parameter is required.', array( 'status' => 404 ) );
		}

		hippshipp_helper::update_order_meta( $order_id, 'live_rate_id', $rate_id );

		$label = ( new hippshipp_api() )->transactions( $rate_id, $customs_id );

		if ( ! isset( $label->tracking_number ) ) {
			$error_message = hippshipp_helper::extract_error_message( reset( $label )[0] );
			return new WP_Error( 'get_label_failure', $error_message ?: 'Unknown error', array( 'status' => 400 ) );
		}

		if ( empty( $label->tracking_number ) ) {
			$error_message = hippshipp_helper::extract_error_message( $shippment->messages[0]->text ?? 'No label available' );
			return new WP_Error( 'get_label_failure', $error_message, array( 'status' => 400 ) );
		}

		// hippshipp_helper::delete_order_meta( $order_id, null, array( 'shippment' ) );
		hippshipp_helper::update_order_meta( $order_id, 'retrive_label', $label );

		foreach ( $rates as $rte ) {
			if ( $rte->object_id === $rate_id ) {
				$rate = $rte;
				break;
			}
		}

		$response = array(
			'shipment_id' => $label->object_id,
			'label_url' => $label->label_url,
			'tracking_number' => $label->tracking_number,
			'carrier' => $rate->provider ?? '',
			'service' => $rate->servicelevel->display_name ?? '',
		);

		$order = wc_get_order( $order_id );
		$order->add_order_note( json_encode( $response ), 1 );

		return new WP_REST_Response( $response, 200 );
	}

	public function get_templates( $request ) {
		$parcels = ( new hippshipp_api() )->list_parcel();

		if ( empty( $parcels ) ) {
			return new WP_Error( 'templates_not_found', 'No templates found.', array( 'status' => 404 ) );
		}

		$page = max( 1, $request['page'] );
		$per_page = max( 1, min( 100, $request['per_page'] ) );
		$offset = ( $page - 1 ) * $per_page;

		$total = count( $parcels );
		$paginated_parcels = array_slice( $parcels, $offset, $per_page );

		$templates = array();
		
		foreach ( $paginated_parcels as $parcel ) {
			$templates[] = array(
				'id'          => $parcel->object_id ?? '',
				'name'        => $parcel->name ?? '',
				'weight'      => $parcel->weight ?? '',
				'weight_unit' => $parcel->weight_unit ?? '',
				'dimensions' => array(
					'length'  => $parcel->length ?? '',
					'width'   => $parcel->width ?? '',
					'height'  => $parcel->height ?? '',
					'unit'    => $parcel->distance_unit ?? '',
				),
				'description' => $parcel->description ?? '',
			);
		}

		$response = rest_ensure_response( $templates );

		$response->header( 'X-WP-Total', (int) $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}

	public function create_template( $request ) {
		$name        = $request->get_param( 'name' );
		$weight      = $request->get_param( 'weight' );
		$weight_unit = $request->get_param( 'weight_unit' );
		$dimensions  = $request->get_param( 'dimensions' );
		$description = $request->get_param( 'description' );

		$parcel = array(
			'name'          => $name ?? '',
			'description'   => $description ?? '',
			'length'        => $dimensions['length'] ?? '',
			'width'         => $dimensions['width'] ?? '',
			'height'        => $dimensions['height'] ?? '',
			'distance_unit' => $dimensions['unit'] ?? get_option( 'woocommerce_dimension_unit' ),
		);

		if ( isset( $weight ) ) {
			$parcel['weight'] = $weight;
			$parcel['weight_unit'] = $weight_unit ?? get_option( 'woocommerce_weight_unit' );
		}
		
		$template = ( new hippshipp_api() )->add_parcel( $parcel );

		if( ! isset( $template->object_id ) ) {
			return new WP_Error( 'create_template_failure', 'Template is not valid.', array( 'status' => 400 ) );
		}

		hippshipp_helper::update_active_parcel( $template->object_id );

		$response = array(
			'id'          => $template->object_id,
			'name'        => $parcel['name'],
			'weight'      => $parcel['weight'],
			'weight_unit' => $parcel['weight_unit'],
			'dimensions' => array(
				'length'  => $parcel['length'],
				'width'   => $parcel['width'],
				'height'  => $parcel['height'],
				'unit'    => $parcel['distance_unit'],
			),
			'description' => $parcel['description'],
		);

		return new WP_REST_Response( $response, 200 );
	}

	public function delete_template( $request ) {
		$template_id = $request->get_param( 'template_id' );

		$template = ( new hippshipp_api() )->delete_parcel( $template_id );

		if ( isset( $template->detail ) ) {
			return new WP_Error( 'delete_template_failure', $template->detail, array( 'status' => 400 ) );
		}

		$response = 'Template deleted successfully.';

		return new WP_REST_Response( $response, 200 );
	}

	public function validate_shippment_required_fields( $shipp ) {
		$required_address_fields = array( 'name', 'street1', 'city', 'state', 'zip', 'country' );
		$required_package_fields = array( 'weight', 'length', 'width', 'height' );

		foreach ( $required_address_fields as $field ) {
			if ( empty( $shipp[1][$field] ) ) {
				return new WP_Error( 'missing_field', sprintf( 'The %s field is required in the address.', $field ), array( 'status' => 400 ) );
			}
		}

		if ( is_array( $shipp[2]['tplbox'] ) ) {
			foreach ( $required_package_fields as $field ) {
				if ( empty( $shipp[2]['tplbox'][$field] ) ) {
					return new WP_Error( 'missing_field', sprintf( 'The %s field is required in the package.', $field ), array( 'status' => 400 ) );
				}
			}
		} else {
			if ( empty( $shipp[2]['tplbox'] ) ) {
				return new WP_Error( 'missing_field', sprintf( 'The template field is required in the package.' ), array( 'status' => 400 ) );
			}
		}

		return true;
	}

	public function get_customs_order_items( $order_id ) {
		$custome = hippshipp_helper::get_order_meta( $order_id, 'custome_declarex' );

		$items = array();
		if ( empty( $custome ) ) {
			$order = wc_get_order( $order_id );
			foreach ( $order->get_items() as $key => $item ) {
				$product = $item->get_product();

				$items[] = array(
					'description'    => $product->get_name(),
					'quantity'       => $item->get_quantity(),
					'value'          => $product->get_price(),
					'weight'         => $product->get_weight(),
					'origin_country' => get_post_meta( $product->get_id(), '_country_of_origin', true ),
					'hs_tariff_number' => '',
				);
			}
		} else {
			foreach ( $custome['items'] as $product ) {
				$items[] = array(
					'description'      => $product['description'] ?? '',
					'quantity'         => $product['quantity'] ?? '',
					'value'            => $product['value_amount'] ?? '',
					'weight'           => $product['net_weight'] ?? '',
					'origin_country'   => $product['origin_country'] ?? '',
					'hs_tariff_number' => $product['hs_tariff_number'] ?? '',
				);
			}
		}

		return $items;
	}

	public function get_template_by_id( $template_id ) {
		$templates = ( new hippshipp_api() )->list_parcel();

		$template = array();
		if ( ! empty( $templates ) ) {
			foreach ( $templates as $tpl ) {
				if ( $tpl->object_id === $template_id ) {
					$template = (array) $tpl;
					break;
				}
			}
		}

		return $template;
	}

	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	public function is_request_to_rest_api( $condition ) {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		
		// Allow the plugin use wc authentication methods.
		$hippoo = ( false !== strpos( $request_uri, $rest_prefix . $this->namespace ) );
		
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
}

new hippshipp_web_api();