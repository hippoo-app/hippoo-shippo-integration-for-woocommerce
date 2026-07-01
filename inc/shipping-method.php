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
		$this->supports           = [ 'shipping-zones' ];

		$this->init();
	}

	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = $this->get_option( 'enabled' );
		$this->title   = $this->get_option( 'title', __( 'Shippo Live Rates', 'shippo' ) );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	public function calculate_shipping( $package = [] ) {
		$opt = get_option( 'shippo_options' );
		if ( empty( $opt['en_shippo'] ) || empty( $opt['shipping_rate'] ) ) {
			return;
		}

		$destination = $package['destination'] ?? [];
		$contents = $package['contents'] ?? [];

		$address_to = hippshipp_helper::prepare_shippo_address( $destination );

		$line_items = [];
		foreach ( $contents as $item ) {
			$product = $item['data'];
			$line_items[] = [
				'quantity'    => $item['quantity'],
				'total_price' => (string)$item['line_total'],
				'sku'         => $product->get_sku(),
				'title'       => $product->get_name(),
				'weight'      => $product->get_weight() ?? '1',
				'weight_unit' => get_option( 'woocommerce_weight_unit' ),
				'currency'    => get_option( 'woocommerce_currency' ),
			];
		}

		$rates = hippshipp_api::live_rates( $address_to, $line_items );

		foreach ( $rates as $index => $rate ) {
			$cost = empty( $rate->amount_local ) ? $rate->amount : $rate->amount_local;
			$currency = empty( $rate->currency_local ) ? $rate->currency : $rate->currency_local;

			if ( ! empty( $opt['ex_amount'] ) ) {
				$cost += (float)$opt['ex_amount'];
			}

			$this->add_rate( [
				'id'        => $this->id . ':' . $index,
				'label'     => wp_kses_post( $rate->title ),
				'cost'      => (float)$cost,
				'meta_data' => [
					'shippo_title'       => wp_kses_post( $rate->title ),
					'shippo_description' => wp_kses_post( $rate->description ),
				],
			] );
		}
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'   => __( 'Enable', 'shippo' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Shippo live rates', 'shippo' ),
				'default' => 'yes'
			],
		];
	}

}