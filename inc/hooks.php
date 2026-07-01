<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class hippshipp_hooks {

	public function __construct() {
		register_activation_hook( HIPPSHIPP__FILE__, [ $this, 'register_activation_hook' ] );
		register_activation_hook( HIPPSHIPP__FILE__, [ $this, 'schedule_weekly_cleanup' ] );
		register_deactivation_hook( HIPPSHIPP__FILE__, [ $this, 'unschedule_weekly_cleanup' ] );
		add_action( 'hippshipp_weekly_cleanup', [ $this, 'cleanup_unnecessary_metadata' ] );
		add_action( 'activated_plugin', [ $this, 'plugin_activation' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

		add_action( 'woocommerce_shipping_init', [ $this, 'woocommerce_shipping_init' ] );
		add_filter( 'woocommerce_shipping_methods', [ $this, 'woocommerce_shipping_methods' ], 10, 1 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', [ $this, 'woocommerce_cart_shipping_method_full_label' ], 10, 2 );

		add_action( 'woocommerce_view_order', [ $this, 'my_account_live_tracking' ], 10, 1 );

		add_action( 'add_meta_boxes', [ $this, 'admin_order_metabox' ], 1 );
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_custom_order_column' ], 9999 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'populate_custom_order_column' ], 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_custom_order_column' ], 9999 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'populate_custom_order_column' ], 10, 2 );
	}

	public function wp_enqueue_scripts() {
		wp_enqueue_style( 'shp-public', HIPPSHIPP_URL . 'assets/css/public-style.css', [], HIPPSHIPP_VERSION, 'all' );
		wp_enqueue_script( 'shp-public', HIPPSHIPP_URL . 'assets/js/public-script.js', [ 'jquery' ], HIPPSHIPP_VERSION, true );
		wp_localize_script(
			'shp-public',
			'shippo',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'shippo_nonce' ),
			]
		);
	}

	public function admin_enqueue_scripts( $hook ) {
		wp_register_style( 'shp-admin', HIPPSHIPP_URL . 'assets/css/admin-style.css', [], HIPPSHIPP_VERSION, 'all' );
		wp_register_script( 'shp-admin', HIPPSHIPP_URL . 'assets/js/admin-script.js', [ 'jquery' ], HIPPSHIPP_VERSION, true );
		wp_localize_script(
			'shp-admin',
			'shippo',
			[
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'shippo_nonce' ),
				'currency' => get_option( 'woocommerce_currency' ),
				'wunit'    => get_option( 'woocommerce_weight_unit' ),
			]
		);

		if ( 
			( $hook === 'edit.php' && isset( $_GET['post_type'] ) && sanitize_key( $_GET['post_type'] ) === 'shop_order' ) ||
			( $hook === 'post.php' && isset( $_GET['post'] ) && get_post_type( sanitize_key( $_GET['post'] ) ) === 'shop_order' ) ||
			( $hook === 'woocommerce_page_wc-orders' && isset( $_GET['page'] ) && sanitize_key( $_GET['page'] ) === 'wc-orders' ) ||
			( $hook === 'woocommerce_page_wc-settings' && isset( $_GET['section'] ) && sanitize_key( $_GET['section'] ) === 'shippo' )
		) {
			wp_enqueue_script( 'jquery' );
			add_thickbox();
			wp_enqueue_style( 'shp-admin' );
			wp_enqueue_script( 'shp-admin' );
		}
	}

	public function woocommerce_shipping_init() {
		require_once HIPPSHIPP_PATH . 'inc/shipping-method.php';
	}

	public function woocommerce_shipping_methods( $methods ) {
		$methods['shippo_live_rate'] = 'hippshipp_shipping_method';
		return $methods;
	}

	public function woocommerce_cart_shipping_method_full_label( $label, $method ) {
		$metadata = $method->get_meta_data();
		if ( ! empty( $metadata['shippo_description'] ) ) {
			$label .= '<br><small>' . esc_html( $metadata['shippo_description'] ) . '</small>';
		}
		return $label;
	}

	public function my_account_live_tracking( $order_id ) {
		if ( is_admin() ) {
			return;
		}

		$opt = get_option( 'shippo_options', [] );
		if ( empty( $opt['en_shippo'] ) || empty( $opt['live_tracking'] ) ) {
			return;
		}

		$label = hippshipp_helper::get_order_meta( $order_id, 'retrive_label' );
		$tracking_number = $label->tracking_number ?? '';
		
		if ( empty( $tracking_number ) ) {
			return;
		}

		$old_version_carrier = hippshipp_helper::get_order_meta( $order_id, 'live_rate_carrier' );
		$carrier = $label->carrier ?? $old_version_carrier ?? '';

		$tracking_data = null;
		if ( ! empty( $carrier ) ) {
			$tracking_data = hippshipp_api::track_shipment( $carrier, $tracking_number );
		}

		if ( is_object( $tracking_data ) && isset( $tracking_data->error ) ) {
			$tracking_data = null;
		}

		$eta             = $label->eta ?? $tracking_data->eta ?? '';
		$latest_status   = $tracking_data->tracking_status->status ?? '';
		$latest_time     = $tracking_data->tracking_status->status_date ?? '';
		$latest_location = $tracking_data->tracking_status->location ?? '';
		$history         = $tracking_data->tracking_history ?? [];
		?>
		<div class="shippo-live-tracking">
			<h2 class="shippo-title"><?php echo esc_html__( 'Shipping updates', 'shippo' ); ?></h2>
			
			<div class="shippo-tracking-summary">
				<div class="shippo-summary-row shippo-summary-eta">
					<strong><?php echo esc_html__( 'Estimate delivery: ', 'shippo' ); ?></strong>
					<?php echo esc_html( hippshipp_helper::format_date( $eta ) ); ?>
				</div>
				<div class="shippo-summary-row shippo-summary-tracking">
					<strong><?php echo esc_html__( 'Tracking number:', 'shippo' ); ?></strong>
					<?php echo esc_html( $tracking_number ); ?>
				</div>
				<div class="shippo-summary-row shippo-summary-courier">
					<strong><?php echo esc_html__( 'Courier:', 'shippo' ); ?></strong>
					<?php echo esc_html( strtoupper( $carrier ) ); ?>
				</div>
			</div>
				
			<?php if ( empty( $latest_status ) ) : ?>
				<div class="shippo-tracking-item empty">
					<div class="shippo-status-text">
						<?php echo esc_html__( 'No tracking information is available yet.', 'shippo' ); ?>
					</div>
				</div>
			<?php else: ?>
				<div class="shippo-tracking-item shippo-latest-status">
					<div class="shippo-status-header">
						<div class="shippo-status-badge">
							<?php echo esc_html( $latest_status ); ?>
						</div>
						<div class="shippo-status-time">
							<?php echo esc_html( hippshipp_helper::format_date( $latest_time ) ); ?>
						</div>
					</div>
					<div class="shippo-status-text">
						<?php 
							echo ! empty( $tracking_data->tracking_status->status_details )
								? esc_html( $tracking_data->tracking_status->status_details )
								: '';
						?>
					</div>
					<?php if ( ! empty( $latest_location ) ) : ?>
						<div class="shippo-status-location">
							<?php echo esc_html( $latest_location->city . ', ' . $latest_location->country ); ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $history ) ) : ?>
					<div class="shippo-history-box" style="display:none;">
						<?php foreach ( $history as $item ) : ?>
							<div class="shippo-tracking-item shippo-history-item">
								<div class="shippo-status-header">
									<div class="shippo-status-badge">
										<?php echo esc_html( $item->status ); ?>
									</div>
									<div class="shippo-status-time">
										<?php echo esc_html( hippshipp_helper::format_date( $item->status_date ) ); ?>
									</div>
								</div>
								<div class="shippo-status-text">
									<?php echo esc_html( $item->status_details ); ?>
								</div>
								<div class="shippo-status-location">
									<?php echo isset( $item->location ) ? esc_html( $item->location->city . ', ' . $item->location->country ) : ''; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="shippo-history-toggle">
						<a href="#" class="shippo-show-history" data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php echo esc_html__( 'See tracking history', 'shippo' ); ?>
						</a>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public function admin_order_metabox() {
		add_meta_box(
			'shippo-tracking-mtbox',
			'Shippo Info',
			[ $this, 'order_meta_box' ],
			wc_get_page_screen_id( 'shop-order' ),
			'side',
			'default'
		);
	}

	public function order_meta_box( $post ) {
		$order_id = is_a( $post, 'WC_Order' ) ? $post->get_id() : $post->ID;

		hippshipp_modal_form( $order_id );

		echo '<button type="button" class="button open-thickbox" data-id="' . esc_attr( $order_id ) . '">Get new shippo label</button>';

		if ( $check = hippshipp_helper::get_order_meta( $order_id, 'retrive_label' ) ) {
			echo '<a href="' . ( isset( $check->label_url ) ? esc_url( $check->label_url ) : '' ) . '" data-id="' . esc_attr( $order_id ) . '" target="_blank">Retrieve label</a>';
			echo '<a href="#" class="shippo-admin-tracking" data-id="' . esc_attr( $order_id ) . '">Order tracking</a>';
			hippshipp_tracking_modal_form( $order_id );
		}
	}

	public function add_custom_order_column( $columns ) {
		$columns['shippo'] = 'Shippo';
		return $columns;
	}
	
	public function populate_custom_order_column( $column, $post_id ) {
		if ( 'shippo' === $column ) {
			if ( is_object( $post_id ) ) {
				$post_id = $post_id->get_id();
			}
			
			hippshipp_modal_form( $post_id );
	
			if ( $check = hippshipp_helper::get_order_meta( $post_id, 'retrive_label' ) ) {
				echo '<a href="' . ( isset( $check->label_url ) ? esc_url( $check->label_url ) : '' ) . '" class="retrive-label" data-id="' . esc_attr( $post_id ) . '" target="_blank">Retrieve label</a>';
			}
			echo '<button type="button" class="button open-thickbox generate-label-btn" data-id="' . esc_attr( $post_id ) . '">Generate label</button>';
		}
	}

	public function plugin_activation( $plugin ) {
		if ( $plugin === plugin_basename( HIPPSHIPP__FILE__ ) ) {
			if ( ! get_option( 'hippshipp_activated' ) ) {
				update_option( 'hippshipp_activated', true );
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=shippo' ) );
				exit;
			}
		}
	}

	public function register_activation_hook() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hippshipp_order_meta';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
			`meta_id` mediumint(9) NOT NULL AUTO_INCREMENT,
			`order_id` bigint(20) NOT NULL,
			`meta_key` varchar(191) NOT NULL,
			`meta_value` longtext NOT NULL,
			PRIMARY KEY (`meta_id`),
			UNIQUE KEY `order_meta_unique` (`order_id`, `meta_key`),
			KEY `meta_key_index` (`meta_key`),
			KEY `meta_value_index` (`meta_value`(191))
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function schedule_weekly_cleanup() {
		if ( ! wp_next_scheduled( 'hippshipp_weekly_cleanup' ) ) {
			wp_schedule_event( time(), 'weekly', 'hippshipp_weekly_cleanup' );
		}
	}

	public function unschedule_weekly_cleanup() {
		$timestamp = wp_next_scheduled( 'hippshipp_weekly_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'hippshipp_weekly_cleanup' );
		}
	}

	public function cleanup_unnecessary_metadata() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hippshipp_order_meta';
		
		$exclude_meta_keys = [ 'shippment', 'retrive_label' ];
		$exclude_meta_keys = array_map( 'esc_sql', (array)$exclude_meta_keys );
		$placeholders = implode( ',', array_fill( 0, count( $exclude_meta_keys ), '%s' ) );

		// phpcs:disable WordPress.DB
		$query = $wpdb->prepare("
			DELETE FROM `$table_name`
			WHERE order_id IN (
				SELECT order_id FROM (
					SELECT DISTINCT order_id 
					FROM `$table_name`
					WHERE meta_key = 'retrive_label' 
					AND meta_value IS NOT NULL 
					AND meta_value != ''
				) AS subquery
			)
			AND meta_key NOT IN ($placeholders)",
			$exclude_meta_keys
		);

		$wpdb->query( $query );
		// phpcs:enable
	}

}

new hippshipp_hooks();