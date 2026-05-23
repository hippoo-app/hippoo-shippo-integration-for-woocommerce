<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function hippshipp_tracking_modal_form( $order_id ) {
	if ( ! is_admin() ) {
		return;
	}

	$label = hippshipp_helper::get_order_meta( $order_id, 'retrive_label' );
	$tracking_number = $label->tracking_number ?? '';
	
	if ( empty( $tracking_number ) ) {
		return;
	}

	$carrier = hippshipp_helper::get_order_meta( $order_id, 'live_rate_carrier' );

	$tracking_data = null;
	if ( ! empty( $carrier ) ) {
		$tracking_data = ( new hippshipp_api() )->track_shipment( $carrier, $tracking_number );
	}

	if ( is_object( $tracking_data ) && isset( $tracking_data->error ) ) {
		$tracking_data = null;
	}

	$eta             = $label->eta ?? $tracking_data->eta ?? '';
	$latest_status   = $tracking_data->tracking_status->status ?? '';
	$latest_time     = $tracking_data->tracking_status->status_date ?? '';
	$latest_location = $tracking_data->tracking_status->location ?? '';
	$history         = $tracking_data->tracking_history ?? array();
	?>
	<div id="shippo-admin-track-<?php echo esc_attr( $order_id ); ?>" style="display:none; padding:20px;">
		<div class="shippo-live-tracking">
			<div class="shippo-tracking-summary">
				<div class="shippo-summary-row shippo-summary-eta">
					<strong><?php echo esc_html__( 'Estimate delivery: ', 'shippo' ); ?></strong>
					<?php echo esc_html( $eta ); ?>
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
							<?php echo esc_html( hippshipp_helper::format_shippo_date( $latest_time ) ); ?>
						</div>
					</div>
					<div class="shippo-status-text">
						<?php 
							echo !empty( $tracking_data->tracking_status->status_details )
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
					<div class="shippo-history-box">
						<?php foreach ( $history as $item ) : ?>
							<div class="shippo-tracking-item shippo-history-item">
								<div class="shippo-status-header">
									<div class="shippo-status-badge">
										<?php echo esc_html( $item->status ); ?>
									</div>
									<div class="shippo-status-time">
										<?php echo esc_html( hippshipp_helper::format_shippo_date( $item->status_date ) ); ?>
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
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}