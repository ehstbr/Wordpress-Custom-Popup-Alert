<?php
/**
 * Shared modal shell renderer.
 *
 * @package WCCPA
 */

namespace WCCPA;

defined( 'ABSPATH' ) || exit;

final class Renderer {
	/**
	 * Render one shared shell and the eligible alert payload.
	 *
	 * @param array $alerts Alerts.
	 * @return void
	 */
	public function render( array $alerts ) {
		if ( empty( $alerts ) ) {
			return;
		}

		do_action( 'wccpa_alerts_before_render', $alerts );
		?>
		<div id="wccpa-root" class="wccpa-root" hidden aria-hidden="true">
			<div class="wccpa-overlay" data-wccpa-overlay>
				<div class="wccpa-dialog" role="dialog" aria-modal="true" aria-labelledby="wccpa-title" aria-describedby="wccpa-content" tabindex="-1">
					<div class="wccpa-topbar" data-wccpa-topbar>
						<span class="wccpa-icon" data-wccpa-icon aria-hidden="true"></span>
						<h2 class="wccpa-title" id="wccpa-title"></h2>
						<button type="button" class="wccpa-close" data-wccpa-close aria-label="<?php esc_attr_e( 'Close alert', 'wordpress-custom-popup-alert' ); ?>">
							<svg class="wccpa-close-progress" data-wccpa-close-progress viewBox="0 0 36 36" aria-hidden="true" hidden>
								<circle class="wccpa-close-progress__track" cx="18" cy="18" r="15.5" pathLength="100"></circle>
								<circle class="wccpa-close-progress__value" data-wccpa-close-progress-value cx="18" cy="18" r="15.5" pathLength="100"></circle>
							</svg>
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="wccpa-body" id="wccpa-content"></div>
					<div class="wccpa-footer" data-wccpa-footer hidden>
						<p class="wccpa-countdown" data-wccpa-countdown aria-live="polite" hidden></p>
						<a class="wccpa-button wccpa-button--secondary" data-wccpa-secondary href="#"></a>
						<a class="wccpa-button wccpa-button--primary" data-wccpa-primary href="#"></a>
					</div>
					<div class="wccpa-progress" data-wccpa-progress role="progressbar" aria-label="<?php esc_attr_e( 'Countdown progress', 'wordpress-custom-popup-alert' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden>
						<span data-wccpa-progress-bar></span>
					</div>
				</div>
			</div>
		</div>
		<script type="application/json" id="wccpa-data"><?php echo wp_json_encode( array_values( $alerts ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
		<?php
		do_action( 'wccpa_alerts_after_render', $alerts );
	}
}
