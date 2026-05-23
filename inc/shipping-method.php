<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_shipping_method extends WC_Shipping_Method {
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->id                 = 'shippo_live_rate';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Shippo Live Rates', 'shippo' );
		$this->method_description = __( 'Real-time shipping rates from Shippo', 'shippo' );
		$this->init();
	}

	public function init() {
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled  = $this->get_option( 'enabled' );
		$this->title    = $this->get_option( 'title', __( 'Shippo Live Rates', 'shippo' ) );
		$this->supports = [ 'shipping-zones' ];
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function calculate_shipping($package = array()) {
		if ( ! session_id() ) {
			@session_start();
		}

		if ( empty( $_SESSION['shippo_shippment'][0] ) ) {
			return;
		}

		$opt   = get_option( 'shippo_options' );
		$rates = $_SESSION['shippo_shippment'][0];

		foreach ( $rates as $index => $rate ) {
			$price = empty( $rate->amount_local ) ? $rate->amount : $rate->amount_local;
			$currency = empty( $rate->currency_local ) ? $rate->currency : $rate->currency_local;
			if ( ! empty( $opt['ex_amount'] ) ) {
				$price += absint( $opt['ex_amount'] );
			}
			$this->add_rate( array(
				'id'        => $this->id . ':' . $index,
				'label'     => wp_kses_post( $rate->title ),
				'cost'      => floatval( $price ),
				'meta_data' => array(
					'shippo_title'       => wp_kses_post( $rate->title ),
					'shippo_description' => wp_kses_post( $rate->description ),
				),
			) );
		}
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable', 'shippo' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Shippo live rates', 'shippo' ),
				'default' => 'yes'
			),
		);
	}

}