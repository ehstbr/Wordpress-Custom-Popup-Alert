<?php
/**
 * Native WordPress administration for alerts.
 *
 * @package WCCPA
 */

namespace WCCPA\Admin;

use WCCPA\Alert_Meta;
use WCCPA\Capabilities;
use WCCPA\Post_Type;
use WCCPA\Rule_Engine;

defined( 'ABSPATH' ) || exit;

final class Admin {
	/** @var Rule_Engine */
	private $engine;

	public function __construct( Rule_Engine $engine ) {
		$this->engine = $engine;

		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wccpa_search_content', array( $this, 'ajax_search_content' ) );
		add_action( 'wp_ajax_wccpa_search_terms', array( $this, 'ajax_search_terms' ) );
		add_action( 'wp_ajax_wccpa_search_users', array( $this, 'ajax_search_users' ) );
		add_action( 'admin_post_wccpa_duplicate_alert', array( $this, 'duplicate' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	/**
	 * Add editing panels using WordPress metaboxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wccpa-presentation',
			__( 'Display and Schedule', 'wordpress-custom-popup-alert' ),
			array( $this, 'render_presentation' ),
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'wccpa-rules',
			__( 'Display Rules', 'wordpress-custom-popup-alert' ),
			array( $this, 'render_rules' ),
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'wccpa-display-control',
			__( 'Frequency and Priority', 'wordpress-custom-popup-alert' ),
			array( $this, 'render_display_control' ),
			Post_Type::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			'wccpa-behavior',
			__( 'Behavior and Buttons', 'wordpress-custom-popup-alert' ),
			array( $this, 'render_behavior' ),
			Post_Type::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			'wccpa-appearance',
			__( 'Appearance', 'wordpress-custom-popup-alert' ),
			array( $this, 'render_appearance' ),
			Post_Type::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Enqueue editor assets only on alert editing screens.
	 *
	 * @param string $hook_suffix Admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wccpa-popup', WCCPA_URL . 'assets/frontend/popup.css', array(), WCCPA_VERSION );
		wp_enqueue_style( 'wccpa-admin', WCCPA_URL . 'assets/admin/admin.css', array( 'wp-color-picker' ), WCCPA_VERSION );
		wp_enqueue_script( 'wccpa-popup', WCCPA_URL . 'assets/frontend/popup.js', array(), WCCPA_VERSION, true );
		wp_enqueue_script( 'wccpa-admin', WCCPA_URL . 'assets/admin/admin.js', array( 'jquery', 'wp-color-picker' ), WCCPA_VERSION, true );

		wp_localize_script(
			'wccpa-popup',
			'wccpaFrontend',
			array(
				'countdown'     => __( 'This message will close in %d seconds.', 'wordpress-custom-popup-alert' ),
				'closeLabel'    => __( 'Close alert', 'wordpress-custom-popup-alert' ),
				'progressLabel' => __( 'Countdown progress', 'wordpress-custom-popup-alert' ),
				'defaultTitle'  => __( 'Alert', 'wordpress-custom-popup-alert' ),
			)
		);
		wp_localize_script(
			'wccpa-admin',
			'wccpaAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'wccpa_admin_search' ),
				'conditions'    => $this->engine->registry()->definitions( true ),
				'maxDepth'      => Rule_Engine::MAX_DEPTH,
				'wooAvailable'  => $this->engine->registry()->has_woocommerce(),
				'i18n'          => array(
					'all'                => __( 'ALL', 'wordpress-custom-popup-alert' ),
					'any'                => __( 'ANY', 'wordpress-custom-popup-alert' ),
					'and'                => __( 'AND', 'wordpress-custom-popup-alert' ),
					'or'                 => __( 'OR', 'wordpress-custom-popup-alert' ),
					'areTrue'            => __( 'are true', 'wordpress-custom-popup-alert' ),
					'showWhen'           => __( 'Show when', 'wordpress-custom-popup-alert' ),
					'group'              => __( 'Group', 'wordpress-custom-popup-alert' ),
					'addCondition'       => __( 'Add condition', 'wordpress-custom-popup-alert' ),
					'addGroup'           => __( 'Add group', 'wordpress-custom-popup-alert' ),
					'remove'             => __( 'Remove', 'wordpress-custom-popup-alert' ),
					'selectCondition'    => __( 'Select condition', 'wordpress-custom-popup-alert' ),
					'yes'                => __( 'Yes', 'wordpress-custom-popup-alert' ),
					'no'                 => __( 'No', 'wordpress-custom-popup-alert' ),
					'noResults'          => __( 'No results found.', 'wordpress-custom-popup-alert' ),
					'searching'          => __( 'Searching…', 'wordpress-custom-popup-alert' ),
					'maxDepthReached'    => __( 'Nested group limit reached.', 'wordpress-custom-popup-alert' ),
					'previewUnavailable' => __( 'Could not start the preview.', 'wordpress-custom-popup-alert' ),
					'unavailableRule'    => __( 'Condition preserved but unavailable while its integration is inactive.', 'wordpress-custom-popup-alert' ),
					'unavailableSuffix'  => __( 'unavailable', 'wordpress-custom-popup-alert' ),
					'defaultTitle'       => __( 'Alert', 'wordpress-custom-popup-alert' ),
					'delayPreview'       => __( 'Delay: %s s', 'wordpress-custom-popup-alert' ),
					'autoClosePreview'   => __( 'Auto-close: %s s', 'wordpress-custom-popup-alert' ),
					'countdownMessage'   => __( 'This message will close in %d seconds.', 'wordpress-custom-popup-alert' ),
					'pauseOnHover'       => __( 'Pause on hover', 'wordpress-custom-popup-alert' ),
					'paused'             => __( 'Paused', 'wordpress-custom-popup-alert' ),
				),
			)
		);
	}

	/**
	 * Presentation fields.
	 *
	 * @param \WP_Post $post Alert.
	 * @return void
	 */
	public function render_presentation( $post ) {
		$meta             = Alert_Meta::get_all( $post->ID );
		$schedule_enabled = ! empty( $meta['display']['schedule_enabled'] );
		wp_nonce_field( 'wccpa_save_alert', 'wccpa_nonce' );
		?>
		<div class="wccpa-field-grid wccpa-field-grid--presentation">
			<div class="wccpa-field">
				<label class="wccpa-field__label" for="wccpa-public-title"><?php esc_html_e( 'Public title', 'wordpress-custom-popup-alert' ); ?></label>
				<input type="text" id="wccpa-public-title" name="wccpa_display[public_title]" value="<?php echo esc_attr( $meta['display']['public_title'] ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Shown in the popup header. The WordPress title above is used only to identify this alert in the dashboard.', 'wordpress-custom-popup-alert' ); ?></p>
			</div>
			<div class="wccpa-schedule" data-wccpa-toggle-group="schedule">
				<?php $this->checkbox( 'wccpa_display[schedule_enabled]', $meta['display']['schedule_enabled'], __( 'Schedule this alert', 'wordpress-custom-popup-alert' ), 'wccpa-schedule-enabled' ); ?>
				<p class="description"><?php esc_html_e( 'When scheduling is off, the alert has no date or time limit.', 'wordpress-custom-popup-alert' ); ?></p>
				<div class="wccpa-field-grid wccpa-toggle-details wccpa-schedule-fields" aria-disabled="<?php echo esc_attr( $schedule_enabled ? 'false' : 'true' ); ?>" <?php if ( ! $schedule_enabled ) : ?>hidden<?php endif; ?>>
					<div class="wccpa-field">
						<label class="wccpa-field__label" for="wccpa-starts-at"><?php esc_html_e( 'Starts at', 'wordpress-custom-popup-alert' ); ?></label>
						<input type="datetime-local" id="wccpa-starts-at" name="wccpa_display[starts_at]" value="<?php echo esc_attr( $meta['display']['starts_at'] ); ?>" <?php disabled( ! $schedule_enabled ); ?>>
					</div>
					<div class="wccpa-field">
						<label class="wccpa-field__label" for="wccpa-ends-at"><?php esc_html_e( 'Ends at', 'wordpress-custom-popup-alert' ); ?></label>
						<input type="datetime-local" id="wccpa-ends-at" name="wccpa_display[ends_at]" value="<?php echo esc_attr( $meta['display']['ends_at'] ); ?>" <?php disabled( ! $schedule_enabled ); ?>>
					</div>
					<p class="description wccpa-field--wide">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: WordPress timezone. */
								__( 'You may leave either limit empty. Site timezone: %s.', 'wordpress-custom-popup-alert' ),
								wp_timezone_string()
							)
						);
						?>
					</p>
				</div>
			</div>
		</div>
		<p class="wccpa-preview-action"><button type="button" class="button button-secondary" id="wccpa-preview-button"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> <?php esc_html_e( 'Preview popup', 'wordpress-custom-popup-alert' ); ?></button></p>
		<?php
	}

	/**
	 * Rule builder host.
	 *
	 * @param \WP_Post $post Alert.
	 * @return void
	 */
	public function render_rules( $post ) {
		$rules = get_post_meta( $post->ID, '_wccpa_rules', true );
		$rules = is_array( $rules ) ? $rules : $this->engine->empty_rules();
		?>
		<p><?php esc_html_e( 'Show this alert when the condition tree below is true.', 'wordpress-custom-popup-alert' ); ?></p>
		<input type="hidden" id="wccpa-rules-json" name="wccpa_rules_json" value="<?php echo esc_attr( wp_json_encode( $rules ) ); ?>">
		<div id="wccpa-rule-builder" class="wccpa-rule-builder" data-empty-message="<?php esc_attr_e( 'Add at least one condition. Alerts without rules are not displayed.', 'wordpress-custom-popup-alert' ); ?>"></div>
		<?php if ( ! $this->engine->registry()->has_woocommerce() ) : ?>
			<p class="description"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> <?php esc_html_e( 'WooCommerce is inactive. Its conditions are hidden from the editor, and previously saved WooCommerce rules are not evaluated.', 'wordpress-custom-popup-alert' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Frequency and priority fields.
	 *
	 * @param \WP_Post $post Alert.
	 * @return void
	 */
	public function render_display_control( $post ) {
		$meta      = Alert_Meta::get_all( $post->ID );
		$frequency = $meta['frequency'];
		?>
		<div class="wccpa-settings-grid">
			<fieldset class="wccpa-settings-card" id="wccpa-frequency-fields">
				<legend><?php esc_html_e( 'Display frequency', 'wordpress-custom-popup-alert' ); ?></legend>
				<label class="wccpa-choice-row"><input type="radio" name="wccpa_frequency[mode]" value="always" <?php checked( $frequency['mode'], 'always' ); ?>> <?php esc_html_e( 'Every time the rules match', 'wordpress-custom-popup-alert' ); ?></label>
				<label class="wccpa-choice-row"><input type="radio" name="wccpa_frequency[mode]" value="session" <?php checked( $frequency['mode'], 'session' ); ?>> <?php esc_html_e( 'Once per browser session', 'wordpress-custom-popup-alert' ); ?></label>
				<label class="wccpa-choice-row wccpa-choice-row--inline"><input type="radio" name="wccpa_frequency[mode]" value="days" <?php checked( $frequency['mode'], 'days' ); ?>> <span><?php esc_html_e( 'Every', 'wordpress-custom-popup-alert' ); ?></span> <input type="number" class="small-text" id="wccpa-frequency-days" name="wccpa_frequency[days]" value="<?php echo esc_attr( $frequency['days'] ); ?>" min="0" max="3650"> <span><?php esc_html_e( 'days', 'wordpress-custom-popup-alert' ); ?></span></label>
				<p class="description"><?php esc_html_e( 'When using days, enter 0 to never show this alert again in the same browser.', 'wordpress-custom-popup-alert' ); ?></p>
			</fieldset>
			<fieldset class="wccpa-settings-card">
				<legend><?php esc_html_e( 'Priority', 'wordpress-custom-popup-alert' ); ?></legend>
				<div class="wccpa-field">
					<label class="wccpa-field__label" for="wccpa-priority-value"><?php esc_html_e( 'Priority value', 'wordpress-custom-popup-alert' ); ?></label>
					<input type="number" id="wccpa-priority-value" name="wccpa_priority" value="<?php echo esc_attr( $meta['priority'] ); ?>" min="1" max="100" class="small-text">
					<p class="description"><?php esc_html_e( 'Higher values are displayed first.', 'wordpress-custom-popup-alert' ); ?></p>
				</div>
				<input type="hidden" name="wccpa_exclusive" value="0">
				<label class="wccpa-choice-row"><input type="checkbox" name="wccpa_exclusive" value="1" <?php checked( $meta['exclusive'] ); ?>> <?php esc_html_e( 'Hide eligible alerts with a lower priority', 'wordpress-custom-popup-alert' ); ?></label>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Behavior fields.
	 *
	 * @param \WP_Post $post Alert.
	 * @return void
	 */
	public function render_behavior( $post ) {
		$behavior = Alert_Meta::get_all( $post->ID )['behavior'];
		?>
		<fieldset class="wccpa-settings-card wccpa-behavior-preview-card">
			<legend><?php esc_html_e( 'Live behavior preview', 'wordpress-custom-popup-alert' ); ?></legend>
			<div class="wccpa-behavior-preview" data-wccpa-behavior-preview role="img" aria-label="<?php esc_attr_e( 'Live behavior preview', 'wordpress-custom-popup-alert' ); ?>">
				<div class="wccpa-behavior-preview__stage">
					<div class="wccpa-behavior-preview__capabilities">
						<span data-wccpa-preview-overlay-click><?php esc_html_e( 'Overlay click', 'wordpress-custom-popup-alert' ); ?></span>
						<span data-wccpa-preview-esc><?php esc_html_e( 'ESC key', 'wordpress-custom-popup-alert' ); ?></span>
					</div>
					<div class="wccpa-behavior-preview__dialog" data-wccpa-preview-dialog>
						<div class="wccpa-behavior-preview__header">
							<strong><?php esc_html_e( 'Alert', 'wordpress-custom-popup-alert' ); ?></strong>
							<span class="wccpa-behavior-preview__close" data-wccpa-preview-close aria-hidden="true"><span>&times;</span></span>
						</div>
						<div class="wccpa-behavior-preview__body"><?php esc_html_e( 'Popup content', 'wordpress-custom-popup-alert' ); ?></div>
						<div class="wccpa-behavior-preview__timing">
							<span data-wccpa-preview-delay></span>
							<span data-wccpa-preview-auto-close hidden></span>
							<span data-wccpa-preview-pause hidden><?php esc_html_e( 'Pause on hover', 'wordpress-custom-popup-alert' ); ?></span>
						</div>
						<div class="wccpa-behavior-preview__footer" data-wccpa-preview-footer>
							<span class="wccpa-behavior-preview__countdown-text" data-wccpa-preview-countdown-text hidden></span>
							<span class="wccpa-behavior-preview__button" data-wccpa-preview-secondary></span>
							<span class="wccpa-behavior-preview__button" data-wccpa-preview-primary></span>
						</div>
						<div class="wccpa-behavior-preview__progress" data-wccpa-preview-progress hidden><span></span></div>
					</div>
				</div>
			</div>
		</fieldset>
		<div class="wccpa-settings-grid">
			<fieldset class="wccpa-settings-card">
				<legend><?php esc_html_e( 'Closing options', 'wordpress-custom-popup-alert' ); ?></legend>
				<div class="wccpa-choice-list">
					<?php $this->checkbox( 'wccpa_behavior[close_x]', $behavior['close_x'], __( 'Show the close (X) button', 'wordpress-custom-popup-alert' ) ); ?>
					<?php $this->checkbox( 'wccpa_behavior[close_overlay]', $behavior['close_overlay'], __( 'Close when clicking the page overlay', 'wordpress-custom-popup-alert' ) ); ?>
					<?php $this->checkbox( 'wccpa_behavior[close_esc]', $behavior['close_esc'], __( 'Close with the ESC key', 'wordpress-custom-popup-alert' ) ); ?>
				</div>
			</fieldset>
			<fieldset class="wccpa-settings-card">
				<legend><?php esc_html_e( 'Opening and animation', 'wordpress-custom-popup-alert' ); ?></legend>
				<div class="wccpa-field-grid">
					<div class="wccpa-field">
						<label class="wccpa-field__label" for="wccpa-delay"><?php esc_html_e( 'Opening delay', 'wordpress-custom-popup-alert' ); ?></label>
						<div class="wccpa-unit-control"><input type="number" id="wccpa-delay" min="0" max="300" step="0.1" name="wccpa_behavior[delay]" value="<?php echo esc_attr( $behavior['delay'] ); ?>" class="small-text"><span><?php esc_html_e( 'seconds', 'wordpress-custom-popup-alert' ); ?></span></div>
					</div>
					<div class="wccpa-field">
						<label class="wccpa-field__label" for="wccpa-animation"><?php esc_html_e( 'Animation', 'wordpress-custom-popup-alert' ); ?></label>
						<select id="wccpa-animation" name="wccpa_behavior[animation]">
							<?php foreach ( $this->animation_options() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $behavior['animation'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="wccpa-auto-close" data-wccpa-toggle-group="auto-close">
					<?php $this->checkbox( 'wccpa_behavior[auto_close]', $behavior['auto_close'], __( 'Close automatically', 'wordpress-custom-popup-alert' ), 'wccpa-auto-close-enabled' ); ?>
					<div class="wccpa-auto-close__details" <?php if ( ! $behavior['auto_close'] ) : ?>hidden<?php endif; ?>>
						<div class="wccpa-field">
							<label class="wccpa-field__label" for="wccpa-auto-close-seconds"><?php esc_html_e( 'Close after', 'wordpress-custom-popup-alert' ); ?></label>
							<div class="wccpa-unit-control"><input type="number" id="wccpa-auto-close-seconds" min="1" max="3600" name="wccpa_behavior[auto_close_seconds]" value="<?php echo esc_attr( $behavior['auto_close_seconds'] ); ?>" class="small-text"><span><?php esc_html_e( 'seconds', 'wordpress-custom-popup-alert' ); ?></span></div>
						</div>
						<div class="wccpa-field">
							<label class="wccpa-field__label" for="wccpa-countdown-style"><?php esc_html_e( 'Countdown display', 'wordpress-custom-popup-alert' ); ?></label>
							<select id="wccpa-countdown-style" name="wccpa_behavior[countdown_style]">
								<?php foreach ( $this->countdown_style_options() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $behavior['countdown_style'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wccpa-auto-close__pause">
							<?php $this->checkbox( 'wccpa_behavior[pause_on_hover]', $behavior['pause_on_hover'], __( 'Pause countdown while the pointer is over the popup', 'wordpress-custom-popup-alert' ), 'wccpa-pause-on-hover' ); ?>
						</div>
						<p class="description wccpa-field--wide"><?php esc_html_e( 'The close (X) and close-button modes require the corresponding control to be enabled.', 'wordpress-custom-popup-alert' ); ?></p>
					</div>
				</div>
			</fieldset>
		</div>
		<h3 class="wccpa-section-heading"><?php esc_html_e( 'Footer buttons', 'wordpress-custom-popup-alert' ); ?></h3>
		<div class="wccpa-settings-grid wccpa-button-settings">
			<?php $this->render_button_settings( 'primary_button', __( 'Primary button', 'wordpress-custom-popup-alert' ), $behavior['primary_button'] ); ?>
			<?php $this->render_button_settings( 'secondary_button', __( 'Secondary button', 'wordpress-custom-popup-alert' ), $behavior['secondary_button'] ); ?>
		</div>
		<?php
	}

	/**
	 * Appearance fields.
	 *
	 * @param \WP_Post $post Alert.
	 * @return void
	 */
	public function render_appearance( $post ) {
		$appearance = Alert_Meta::get_all( $post->ID )['appearance'];
		?>
		<div class="wccpa-appearance-sections">
			<fieldset class="wccpa-settings-card">
				<legend><?php esc_html_e( 'Popup dimensions', 'wordpress-custom-popup-alert' ); ?></legend>
				<div class="wccpa-dimension-editor" data-wccpa-dimension-editor>
					<div class="wccpa-dimension-control wccpa-dimension-control--width">
						<?php
						$this->dimension_field(
							'wccpa_appearance[width]',
							'wccpa_appearance[width_unit]',
							'wccpa-width',
							'wccpa-width-unit',
							__( 'Width', 'wordpress-custom-popup-alert' ),
							$appearance['width'],
							$appearance['width_unit'],
							array( 'px', '%', 'vw' ),
							array(
								'px' => array( 'min' => 280, 'max' => 1600, 'default' => 600 ),
								'%'  => array( 'min' => 20, 'max' => 100, 'default' => 80 ),
								'vw' => array( 'min' => 20, 'max' => 100, 'default' => 80 ),
							)
						);
						?>
						<span class="wccpa-dimension-arrow wccpa-dimension-arrow--horizontal" aria-hidden="true">&#8596;</span>
					</div>
					<div class="wccpa-dimension-control wccpa-dimension-control--height">
						<?php
						$this->dimension_field(
							'wccpa_appearance[max_height]',
							'wccpa_appearance[max_height_unit]',
							'wccpa-max-height',
							'wccpa-max-height-unit',
							__( 'Maximum height', 'wordpress-custom-popup-alert' ),
							$appearance['max_height'],
							$appearance['max_height_unit'],
							array( 'px', '%', 'vh' ),
							array(
								'px' => array( 'min' => 160, 'max' => 2000, 'default' => 640 ),
								'%'  => array( 'min' => 20, 'max' => 100, 'default' => 80 ),
								'vh' => array( 'min' => 20, 'max' => 100, 'default' => 80 ),
							)
						);
						?>
						<span class="wccpa-dimension-arrow wccpa-dimension-arrow--vertical" aria-hidden="true">&#8597;</span>
					</div>
					<div class="wccpa-dimension-canvas">
						<div class="wccpa-dimension-popup">
							<div class="wccpa-dimension-control wccpa-dimension-control--radius">
								<?php $this->number_field( 'wccpa_appearance[radius]', 'wccpa-radius', __( 'Corner radius', 'wordpress-custom-popup-alert' ), $appearance['radius'], 0, 80, 'px' ); ?>
							</div>
							<div class="wccpa-dimension-content">
								<span class="wccpa-dimension-content__label"><?php esc_html_e( 'Popup content', 'wordpress-custom-popup-alert' ); ?></span>
								<div class="wccpa-dimension-control wccpa-dimension-control--padding">
									<?php $this->number_field( 'wccpa_appearance[padding]', 'wccpa-padding', __( 'Content padding', 'wordpress-custom-popup-alert' ), $appearance['padding'], 0, 80, 'px' ); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Choose the unit for width and maximum height. The diagram shows where each setting is applied.', 'wordpress-custom-popup-alert' ); ?></p>
			</fieldset>
			<fieldset class="wccpa-settings-card">
				<legend><?php esc_html_e( 'Colors and depth', 'wordpress-custom-popup-alert' ); ?></legend>
				<div class="wccpa-appearance-card-layout">
					<div class="wccpa-field-grid wccpa-appearance-card-controls">
						<?php $this->color_field( 'wccpa_appearance[background]', __( 'Background color', 'wordpress-custom-popup-alert' ), $appearance['background'] ); ?>
						<?php $this->color_field( 'wccpa_appearance[text_color]', __( 'Text color', 'wordpress-custom-popup-alert' ), $appearance['text_color'] ); ?>
						<div class="wccpa-field"><label class="wccpa-field__label" for="wccpa-shadow"><?php esc_html_e( 'Shadow', 'wordpress-custom-popup-alert' ); ?></label><select id="wccpa-shadow" name="wccpa_appearance[shadow]"><?php foreach ( $this->shadow_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $appearance['shadow'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
					</div>
					<div class="wccpa-live-preview wccpa-live-preview--colors" role="img" aria-label="<?php esc_attr_e( 'Live preview', 'wordpress-custom-popup-alert' ); ?>">
						<div class="wccpa-live-popup" data-wccpa-colors-preview>
							<strong><?php esc_html_e( 'Alert', 'wordpress-custom-popup-alert' ); ?></strong>
							<span><?php esc_html_e( 'Popup content', 'wordpress-custom-popup-alert' ); ?></span>
						</div>
					</div>
				</div>
			</fieldset>
			<div class="wccpa-settings-grid">
				<fieldset class="wccpa-settings-card" data-wccpa-toggle-group="border">
					<legend><?php esc_html_e( 'Border', 'wordpress-custom-popup-alert' ); ?></legend>
					<div class="wccpa-appearance-card-layout wccpa-appearance-card-layout--compact">
						<div class="wccpa-appearance-card-controls">
							<?php $this->checkbox( 'wccpa_appearance[border_enabled]', $appearance['border_enabled'], __( 'Enable border', 'wordpress-custom-popup-alert' ), 'wccpa-border-enabled' ); ?>
							<div class="wccpa-field-grid wccpa-toggle-details">
								<?php $this->number_field( 'wccpa_appearance[border_width]', 'wccpa-border-width', __( 'Thickness', 'wordpress-custom-popup-alert' ), $appearance['border_width'], 0, 10, 'px' ); ?>
								<div class="wccpa-field"><label class="wccpa-field__label" for="wccpa-border-style"><?php esc_html_e( 'Style', 'wordpress-custom-popup-alert' ); ?></label><select id="wccpa-border-style" name="wccpa_appearance[border_style]"><option value="solid" <?php selected( $appearance['border_style'], 'solid' ); ?>><?php esc_html_e( 'Solid', 'wordpress-custom-popup-alert' ); ?></option><option value="dashed" <?php selected( $appearance['border_style'], 'dashed' ); ?>><?php esc_html_e( 'Dashed', 'wordpress-custom-popup-alert' ); ?></option><option value="dotted" <?php selected( $appearance['border_style'], 'dotted' ); ?>><?php esc_html_e( 'Dotted', 'wordpress-custom-popup-alert' ); ?></option></select></div>
								<?php $this->color_field( 'wccpa_appearance[border_color]', __( 'Color', 'wordpress-custom-popup-alert' ), $appearance['border_color'] ); ?>
							</div>
						</div>
						<div class="wccpa-live-preview wccpa-live-preview--border" role="img" aria-label="<?php esc_attr_e( 'Live preview', 'wordpress-custom-popup-alert' ); ?>">
							<div class="wccpa-live-popup" data-wccpa-border-preview><span><?php esc_html_e( 'Popup content', 'wordpress-custom-popup-alert' ); ?></span></div>
						</div>
					</div>
				</fieldset>
				<fieldset class="wccpa-settings-card" data-wccpa-toggle-group="overlay">
					<legend><?php esc_html_e( 'Page overlay', 'wordpress-custom-popup-alert' ); ?></legend>
					<div class="wccpa-appearance-card-layout wccpa-appearance-card-layout--compact">
						<div class="wccpa-appearance-card-controls">
							<?php $this->checkbox( 'wccpa_appearance[overlay_enabled]', $appearance['overlay_enabled'], __( 'Enable page overlay', 'wordpress-custom-popup-alert' ), 'wccpa-overlay-enabled' ); ?>
							<div class="wccpa-field-grid wccpa-toggle-details">
								<?php $this->color_field( 'wccpa_appearance[overlay_color]', __( 'Color', 'wordpress-custom-popup-alert' ), $appearance['overlay_color'] ); ?>
								<?php $this->number_field( 'wccpa_appearance[overlay_opacity]', 'wccpa-overlay-opacity', __( 'Opacity', 'wordpress-custom-popup-alert' ), $appearance['overlay_opacity'], 0, 100, '%' ); ?>
								<?php $this->number_field( 'wccpa_appearance[overlay_blur]', 'wccpa-overlay-blur', __( 'Blur', 'wordpress-custom-popup-alert' ), $appearance['overlay_blur'], 0, 20, 'px' ); ?>
							</div>
						</div>
						<div class="wccpa-live-preview wccpa-live-preview--overlay" role="img" aria-label="<?php esc_attr_e( 'Live preview', 'wordpress-custom-popup-alert' ); ?>" data-wccpa-overlay-preview>
							<div class="wccpa-preview-page-content" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
							<div class="wccpa-preview-overlay-layer" aria-hidden="true"></div>
							<div class="wccpa-preview-overlay-dialog"><?php esc_html_e( 'Alert', 'wordpress-custom-popup-alert' ); ?></div>
						</div>
					</div>
				</fieldset>
			</div>
			<fieldset class="wccpa-settings-card" data-wccpa-toggle-group="topbar">
				<legend><?php esc_html_e( 'Header bar', 'wordpress-custom-popup-alert' ); ?></legend>
				<div class="wccpa-appearance-card-layout">
					<div class="wccpa-appearance-card-controls">
						<?php $this->checkbox( 'wccpa_appearance[topbar]', $appearance['topbar'], __( 'Show header bar', 'wordpress-custom-popup-alert' ), 'wccpa-topbar-enabled' ); ?>
						<div class="wccpa-field-grid wccpa-toggle-details">
							<?php $this->color_field( 'wccpa_appearance[topbar_bg]', __( 'Background color', 'wordpress-custom-popup-alert' ), $appearance['topbar_bg'] ); ?>
							<?php $this->color_field( 'wccpa_appearance[topbar_text]', __( 'Text color', 'wordpress-custom-popup-alert' ), $appearance['topbar_text'] ); ?>
							<div class="wccpa-field"><label class="wccpa-field__label" for="wccpa-icon"><?php esc_html_e( 'Icon', 'wordpress-custom-popup-alert' ); ?></label><select id="wccpa-icon" name="wccpa_appearance[icon]"><?php foreach ( $this->icon_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $appearance['icon'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
						</div>
					</div>
					<div class="wccpa-live-preview wccpa-live-preview--header" role="img" aria-label="<?php esc_attr_e( 'Live preview', 'wordpress-custom-popup-alert' ); ?>" data-wccpa-header-preview>
						<div class="wccpa-preview-header-bar">
							<span class="dashicons dashicons-warning wccpa-preview-header-icon" aria-hidden="true"></span>
							<strong><?php esc_html_e( 'Alert', 'wordpress-custom-popup-alert' ); ?></strong>
							<span class="wccpa-preview-header-close" aria-hidden="true">&times;</span>
						</div>
						<div class="wccpa-preview-header-body"><?php esc_html_e( 'Popup content', 'wordpress-custom-popup-alert' ); ?></div>
					</div>
				</div>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Save all metabox data.
	 *
	 * @param int      $post_id Alert ID.
	 * @param \WP_Post $post Alert.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['wccpa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccpa_nonce'] ) ), 'wccpa_save_alert' ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( Capabilities::CAPABILITY ) ) {
			return;
		}

		$defaults        = Alert_Meta::defaults();
		$current_display = Alert_Meta::get_all( $post_id )['display'];
		$display         = isset( $_POST['wccpa_display'] ) && is_array( $_POST['wccpa_display'] ) ? wp_unslash( $_POST['wccpa_display'] ) : array();
		$display         = array(
			'public_title'     => isset( $display['public_title'] ) ? sanitize_text_field( $display['public_title'] ) : '',
			'schedule_enabled' => ! empty( $display['schedule_enabled'] ),
			'starts_at'        => $this->sanitize_datetime( array_key_exists( 'starts_at', $display ) ? $display['starts_at'] : $current_display['starts_at'] ),
			'ends_at'          => $this->sanitize_datetime( array_key_exists( 'ends_at', $display ) ? $display['ends_at'] : $current_display['ends_at'] ),
		);

		$raw_frequency = isset( $_POST['wccpa_frequency'] ) && is_array( $_POST['wccpa_frequency'] ) ? wp_unslash( $_POST['wccpa_frequency'] ) : array();
		$mode          = isset( $raw_frequency['mode'] ) ? sanitize_key( $raw_frequency['mode'] ) : 'always';
		$frequency     = array(
			'mode' => in_array( $mode, array( 'always', 'session', 'days' ), true ) ? $mode : 'always',
			'days' => min( 3650, max( 0, isset( $raw_frequency['days'] ) ? absint( $raw_frequency['days'] ) : 7 ) ),
		);

		$raw_behavior    = isset( $_POST['wccpa_behavior'] ) && is_array( $_POST['wccpa_behavior'] ) ? wp_unslash( $_POST['wccpa_behavior'] ) : array();
		$animation       = isset( $raw_behavior['animation'] ) ? sanitize_key( $raw_behavior['animation'] ) : 'fade-scale';
		$countdown_style = $this->allowed( $raw_behavior, 'countdown_style', array_keys( $this->countdown_style_options() ), 'none' );
		$behavior        = array(
			'close_x'            => ! empty( $raw_behavior['close_x'] ),
			'close_overlay'      => ! empty( $raw_behavior['close_overlay'] ),
			'close_esc'          => ! empty( $raw_behavior['close_esc'] ),
			'auto_close'         => ! empty( $raw_behavior['auto_close'] ),
			'auto_close_seconds' => min( 3600, max( 1, isset( $raw_behavior['auto_close_seconds'] ) ? absint( $raw_behavior['auto_close_seconds'] ) : 10 ) ),
			'countdown'          => 'none' !== $countdown_style,
			'countdown_style'    => $countdown_style,
			'pause_on_hover'     => ! empty( $raw_behavior['pause_on_hover'] ),
			'delay'              => min( 300, max( 0, isset( $raw_behavior['delay'] ) ? (float) $raw_behavior['delay'] : 0 ) ),
			'animation'          => array_key_exists( $animation, $this->animation_options() ) ? $animation : 'fade-scale',
			'primary_button'     => $this->sanitize_button( isset( $raw_behavior['primary_button'] ) ? $raw_behavior['primary_button'] : array(), $defaults['behavior']['primary_button'] ),
			'secondary_button'   => $this->sanitize_button( isset( $raw_behavior['secondary_button'] ) ? $raw_behavior['secondary_button'] : array(), $defaults['behavior']['secondary_button'] ),
		);

		$raw_appearance   = isset( $_POST['wccpa_appearance'] ) && is_array( $_POST['wccpa_appearance'] ) ? wp_unslash( $_POST['wccpa_appearance'] ) : array();
		$width_unit      = $this->dimension_unit( $raw_appearance, 'width_unit', array( 'px', '%', 'vw' ), 'px' );
		$max_height_unit = $this->dimension_unit( $raw_appearance, 'max_height_unit', array( 'px', '%', 'vh' ), 'vh' );
		$width_bounds    = array(
			'px' => array( 280, 1600, 600 ),
			'%'  => array( 20, 100, 80 ),
			'vw' => array( 20, 100, 80 ),
		);
		$height_bounds   = array(
			'px' => array( 160, 2000, 640 ),
			'%'  => array( 20, 100, 80 ),
			'vh' => array( 20, 100, 80 ),
		);
		$appearance      = array(
			'width'           => $this->clamp( $raw_appearance, 'width', $width_bounds[ $width_unit ][0], $width_bounds[ $width_unit ][1], $width_bounds[ $width_unit ][2] ),
			'width_unit'      => $width_unit,
			'max_height'      => $this->clamp( $raw_appearance, 'max_height', $height_bounds[ $max_height_unit ][0], $height_bounds[ $max_height_unit ][1], $height_bounds[ $max_height_unit ][2] ),
			'max_height_unit' => $max_height_unit,
			'padding'         => $this->clamp( $raw_appearance, 'padding', 0, 80, 24 ),
			'background'      => $this->color( $raw_appearance, 'background', '#ffffff' ),
			'text_color'      => $this->color( $raw_appearance, 'text_color', '#1d2327' ),
			'radius'          => $this->clamp( $raw_appearance, 'radius', 0, 80, 8 ),
			'border_enabled'  => ! empty( $raw_appearance['border_enabled'] ),
			'border_width'    => $this->clamp( $raw_appearance, 'border_width', 0, 10, 1 ),
			'border_style'    => $this->allowed( $raw_appearance, 'border_style', array( 'solid', 'dashed', 'dotted' ), 'solid' ),
			'border_color'    => $this->color( $raw_appearance, 'border_color', '#dcdcde' ),
			'shadow'          => $this->allowed( $raw_appearance, 'shadow', array_keys( $this->shadow_options() ), 'medium' ),
			'overlay_enabled' => ! empty( $raw_appearance['overlay_enabled'] ),
			'overlay_color'   => $this->color( $raw_appearance, 'overlay_color', '#000000' ),
			'overlay_opacity' => $this->clamp( $raw_appearance, 'overlay_opacity', 0, 100, 58 ),
			'overlay_blur'    => $this->clamp( $raw_appearance, 'overlay_blur', 0, 20, 0 ),
			'topbar'          => ! empty( $raw_appearance['topbar'] ),
			'topbar_bg'       => $this->color( $raw_appearance, 'topbar_bg', '#d63638' ),
			'topbar_text'     => $this->color( $raw_appearance, 'topbar_text', '#ffffff' ),
			'icon'            => $this->allowed( $raw_appearance, 'icon', array_keys( $this->icon_options() ), 'warning' ),
		);

		$raw_rules = isset( $_POST['wccpa_rules_json'] ) ? json_decode( wp_unslash( $_POST['wccpa_rules_json'] ), true ) : array();
		$rules     = $this->engine->sanitize( $raw_rules );

		update_post_meta( $post_id, '_wccpa_display', $display );
		update_post_meta( $post_id, '_wccpa_rules', $rules );
		update_post_meta( $post_id, '_wccpa_frequency', $frequency );
		update_post_meta( $post_id, '_wccpa_behavior', $behavior );
		update_post_meta( $post_id, '_wccpa_appearance', $appearance );
		update_post_meta( $post_id, '_wccpa_priority', min( 100, max( 1, isset( $_POST['wccpa_priority'] ) ? absint( $_POST['wccpa_priority'] ) : 50 ) ) );
		update_post_meta( $post_id, '_wccpa_exclusive', ! empty( $_POST['wccpa_exclusive'] ) );
	}

	/**
	 * Alert list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		return array(
			'cb'             => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox">',
			'title'          => __( 'Popup Alert', 'wordpress-custom-popup-alert' ),
			'wccpa_status'  => __( 'Status', 'wordpress-custom-popup-alert' ),
			'wccpa_rules'   => __( 'Rules', 'wordpress-custom-popup-alert' ),
			'wccpa_frequency'=> __( 'Frequency', 'wordpress-custom-popup-alert' ),
			'wccpa_priority'=> __( 'Priority', 'wordpress-custom-popup-alert' ),
			'wccpa_validity'=> __( 'Schedule', 'wordpress-custom-popup-alert' ),
			'date'           => isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'wordpress-custom-popup-alert' ),
		);
	}

	/**
	 * Render one custom list cell.
	 *
	 * @param string $column Column.
	 * @param int    $post_id Alert ID.
	 * @return void
	 */
	public function column_content( $column, $post_id ) {
		$meta = Alert_Meta::get_all( $post_id );

		switch ( $column ) {
			case 'wccpa_status':
				echo 'publish' === get_post_status( $post_id )
					? '<span class="wccpa-status wccpa-status--active">' . esc_html__( 'Active', 'wordpress-custom-popup-alert' ) . '</span>'
					: '<span class="wccpa-status">' . esc_html__( 'Inactive', 'wordpress-custom-popup-alert' ) . '</span>';
				break;
			case 'wccpa_rules':
				echo wp_kses_post( $this->rule_summary( get_post_meta( $post_id, '_wccpa_rules', true ) ) );
				break;
			case 'wccpa_frequency':
				if ( 'session' === $meta['frequency']['mode'] ) {
					esc_html_e( 'Once per session', 'wordpress-custom-popup-alert' );
				} elseif ( 'days' === $meta['frequency']['mode'] ) {
					echo 0 === (int) $meta['frequency']['days']
						? esc_html__( 'Never repeat', 'wordpress-custom-popup-alert' )
						: esc_html( sprintf( _n( 'Every %d day', 'Every %d days', $meta['frequency']['days'], 'wordpress-custom-popup-alert' ), $meta['frequency']['days'] ) );
				} else {
					esc_html_e( 'Always', 'wordpress-custom-popup-alert' );
				}
				break;
			case 'wccpa_priority':
				echo esc_html( $meta['priority'] );
				if ( $meta['exclusive'] ) {
					echo '<br><small>' . esc_html__( 'Exclusive', 'wordpress-custom-popup-alert' ) . '</small>';
				}
				break;
			case 'wccpa_validity':
				echo esc_html( $this->validity_summary( $meta['display'] ) );
				break;
		}
	}

	/**
	 * Add duplicate action.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( Post_Type::POST_TYPE !== $post->post_type || ! current_user_can( Capabilities::CAPABILITY ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wccpa_duplicate_alert&post=' . $post->ID ),
			'wccpa_duplicate_' . $post->ID
		);
		$actions['wccpa_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'wordpress-custom-popup-alert' ) . '</a>';
		return $actions;
	}

	/**
	 * Duplicate an alert into a draft.
	 *
	 * @return void
	 */
	public function duplicate() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'wccpa_duplicate_' . $post_id );

		if ( ! $post_id || ! current_user_can( Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this alert.', 'wordpress-custom-popup-alert' ) );
		}

		$source = get_post( $post_id );
		if ( ! $source || Post_Type::POST_TYPE !== $source->post_type ) {
			wp_die( esc_html__( 'Alert not found.', 'wordpress-custom-popup-alert' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => Post_Type::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => sprintf( __( '%s — Copy', 'wordpress-custom-popup-alert' ), $source->post_title ),
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		foreach ( array( '_wccpa_display', '_wccpa_rules', '_wccpa_frequency', '_wccpa_behavior', '_wccpa_appearance', '_wccpa_priority', '_wccpa_exclusive' ) as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' !== $value ) {
				update_post_meta( $new_id, $key, $value );
			}
		}

		wp_safe_redirect( add_query_arg( 'wccpa_duplicated', '1', get_edit_post_link( $new_id, 'url' ) ) );
		exit;
	}

	/**
	 * Duplicate success notice.
	 *
	 * @return void
	 */
	public function admin_notices() {
		$duplicated = isset( $_GET['wccpa_duplicated'] ) ? sanitize_text_field( wp_unslash( $_GET['wccpa_duplicated'] ) ) : '';
		if ( '1' === $duplicated ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alert duplicated as a draft.', 'wordpress-custom-popup-alert' ) . '</p></div>';
		}
	}

	/**
	 * AJAX content lookup.
	 *
	 * @return void
	 */
	public function ajax_search_content() {
		$this->verify_ajax();
		$search  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$source  = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : 'content';
		$results = array();
		$seen    = array();

		$post_types = 'product' === $source && post_type_exists( 'product' )
			? array( 'product' )
			: array_values(
				array_diff(
					get_post_types( array( 'show_ui' => true ), 'names' ),
					array( Post_Type::POST_TYPE, 'attachment' )
				)
			);

		if ( ctype_digit( $search ) ) {
			$candidate = get_post( absint( $search ) );
			if ( $candidate && in_array( $candidate->post_type, $post_types, true ) ) {
				$results[]                    = $this->post_result( $candidate );
				$seen[ $candidate->ID ] = true;
			}
		}

		if ( 'product' === $source && function_exists( 'wc_get_product_id_by_sku' ) && '' !== $search ) {
			$sku_id = (int) wc_get_product_id_by_sku( $search );
			if ( $sku_id && function_exists( 'wc_get_product' ) ) {
				$sku_product = wc_get_product( $sku_id );
				if ( $sku_product && $sku_product->is_type( 'variation' ) ) {
					$sku_id = (int) $sku_product->get_parent_id();
				}
			}
			if ( $sku_id && empty( $seen[ $sku_id ] ) ) {
				$candidate = get_post( $sku_id );
				if ( $candidate ) {
					$results[]          = $this->post_result( $candidate );
					$seen[ $sku_id ] = true;
				}
			}
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				's'              => $search,
				'orderby'        => '' !== $search ? 'relevance' : 'title',
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $candidate ) {
			if ( empty( $seen[ $candidate->ID ] ) ) {
				$results[] = $this->post_result( $candidate );
			}
		}

		wp_send_json_success( array_slice( $results, 0, 20 ) );
	}

	/**
	 * AJAX term lookup.
	 *
	 * @return void
	 */
	public function ajax_search_terms() {
		$this->verify_ajax();
		$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_success( array() );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'search'     => $search,
				'number'     => 20,
			)
		);
		$results = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$results[] = array(
					'id'   => (int) $term->term_id,
					'text' => sprintf( '%s (#%d)', $term->name, $term->term_id ),
				);
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX user lookup.
	 *
	 * @return void
	 */
	public function ajax_search_users() {
		$this->verify_ajax();
		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$users  = get_users(
			array(
				'number'         => 20,
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
				'fields'         => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => (int) $user->ID,
				'text' => sprintf( '%s (%s)', $user->display_name, $user->user_login ),
			);
		}
		wp_send_json_success( $results );
	}

	/** Helpers below. */
	private function verify_ajax() {
		check_ajax_referer( 'wccpa_admin_search', 'nonce' );
		if ( ! current_user_can( Capabilities::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wordpress-custom-popup-alert' ) ), 403 );
		}
	}

	private function post_result( $post ) {
		$type  = get_post_type_object( $post->post_type );
		$label = $type ? $type->labels->singular_name : $post->post_type;
		$title = $post->post_title ? $post->post_title : __( '(no title)', 'wordpress-custom-popup-alert' );
		return array(
			'id'   => (int) $post->ID,
			'text' => sprintf( '#%1$d — %2$s (%3$s)', $post->ID, $title, $label ),
		);
	}

	private function checkbox( $name, $checked, $label, $id = '' ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		echo '<label class="wccpa-choice-row"><input type="checkbox"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' name="' . esc_attr( $name ) . '" value="1" ' . checked( $checked, true, false ) . '> ' . esc_html( $label ) . '</label>';
	}

	private function number_field( $name, $id, $label, $value, $min, $max, $unit ) {
		?>
		<div class="wccpa-field">
			<label class="wccpa-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<div class="wccpa-unit-control"><input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" class="small-text"><span><?php echo esc_html( $unit ); ?></span></div>
		</div>
		<?php
	}

	private function dimension_field( $name, $unit_name, $id, $unit_id, $label, $value, $unit, array $units, array $bounds ) {
		?>
		<div class="wccpa-field wccpa-dimension-field" data-wccpa-unit-field data-wccpa-unit-bounds="<?php echo esc_attr( wp_json_encode( $bounds ) ); ?>">
			<label class="wccpa-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<div class="wccpa-unit-control">
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text" data-wccpa-dimension-value>
				<select id="<?php echo esc_attr( $unit_id ); ?>" name="<?php echo esc_attr( $unit_name ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Unit for %s', 'wordpress-custom-popup-alert' ), $label ) ); ?>" data-wccpa-dimension-unit>
					<?php foreach ( $units as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $unit, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<?php
	}

	private function color_field( $name, $label, $value ) {
		$id = sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
		?>
		<div class="wccpa-field">
			<label class="wccpa-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="text" id="<?php echo esc_attr( $id ); ?>" class="wccpa-color-field" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
		</div>
		<?php
	}

	private function render_button_settings( $key, $heading, array $button ) {
		$name    = 'wccpa_behavior[' . $key . ']';
		$id_base = 'wccpa-' . str_replace( '_', '-', $key );
		?>
		<fieldset class="wccpa-settings-card wccpa-button-fieldset" data-wccpa-button-settings>
			<legend><?php echo esc_html( $heading ); ?></legend>
			<?php $this->checkbox( $name . '[enabled]', $button['enabled'], __( 'Show this button', 'wordpress-custom-popup-alert' ) ); ?>
			<div class="wccpa-field-grid">
				<div class="wccpa-field">
					<label class="wccpa-field__label" for="<?php echo esc_attr( $id_base . '-label' ); ?>"><?php esc_html_e( 'Label', 'wordpress-custom-popup-alert' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $id_base . '-label' ); ?>" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $button['label'] ); ?>">
				</div>
				<div class="wccpa-field">
					<label class="wccpa-field__label" for="<?php echo esc_attr( $id_base . '-action' ); ?>"><?php esc_html_e( 'Action', 'wordpress-custom-popup-alert' ); ?></label>
					<select id="<?php echo esc_attr( $id_base . '-action' ); ?>" data-wccpa-button-action name="<?php echo esc_attr( $name ); ?>[action]">
						<option value="close" <?php selected( $button['action'], 'close' ); ?>><?php esc_html_e( 'Close popup', 'wordpress-custom-popup-alert' ); ?></option>
						<option value="url" <?php selected( $button['action'], 'url' ); ?>><?php esc_html_e( 'Open URL', 'wordpress-custom-popup-alert' ); ?></option>
					</select>
				</div>
				<div class="wccpa-field wccpa-field--wide wccpa-button-url-fields">
					<label class="wccpa-field__label" for="<?php echo esc_attr( $id_base . '-url' ); ?>"><?php esc_html_e( 'URL', 'wordpress-custom-popup-alert' ); ?></label>
					<input type="url" id="<?php echo esc_attr( $id_base . '-url' ); ?>" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $button['url'] ); ?>" class="regular-text">
					<?php $this->checkbox( $name . '[new_tab]', $button['new_tab'], __( 'Open in a new tab', 'wordpress-custom-popup-alert' ) ); ?>
				</div>
				<div class="wccpa-field">
					<label class="wccpa-field__label" for="<?php echo esc_attr( $id_base . '-style' ); ?>"><?php esc_html_e( 'Style', 'wordpress-custom-popup-alert' ); ?></label>
					<select id="<?php echo esc_attr( $id_base . '-style' ); ?>" name="<?php echo esc_attr( $name ); ?>[style]">
						<option value="primary" <?php selected( $button['style'], 'primary' ); ?>><?php esc_html_e( 'Primary', 'wordpress-custom-popup-alert' ); ?></option>
						<option value="secondary" <?php selected( $button['style'], 'secondary' ); ?>><?php esc_html_e( 'Secondary', 'wordpress-custom-popup-alert' ); ?></option>
					</select>
				</div>
			</div>
		</fieldset>
		<?php
	}

	private function sanitize_button( $raw, array $defaults ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$action = isset( $raw['action'] ) ? sanitize_key( $raw['action'] ) : $defaults['action'];
		$style  = isset( $raw['style'] ) ? sanitize_key( $raw['style'] ) : $defaults['style'];
		return array(
			'enabled' => ! empty( $raw['enabled'] ),
			'label'   => isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : $defaults['label'],
			'action'  => in_array( $action, array( 'close', 'url' ), true ) ? $action : 'close',
			'url'     => isset( $raw['url'] ) ? esc_url_raw( $raw['url'] ) : '',
			'new_tab' => ! empty( $raw['new_tab'] ),
			'style'   => in_array( $style, array( 'primary', 'secondary' ), true ) ? $style : 'primary',
		);
	}

	private function sanitize_datetime( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d\\TH:i' ) === $value ? $value : '';
	}

	private function clamp( array $source, $key, $min, $max, $default ) {
		$value = isset( $source[ $key ] ) && is_numeric( $source[ $key ] ) ? (float) $source[ $key ] : $default;
		$value = min( $max, max( $min, $value ) );
		return (int) $value == $value ? (int) $value : $value;
	}

	private function color( array $source, $key, $default ) {
		$value = isset( $source[ $key ] ) ? sanitize_hex_color( $source[ $key ] ) : '';
		return $value ? $value : $default;
	}

	private function allowed( array $source, $key, array $allowed, $default ) {
		$value = isset( $source[ $key ] ) ? sanitize_key( $source[ $key ] ) : $default;
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	private function dimension_unit( array $source, $key, array $allowed, $default ) {
		$value = isset( $source[ $key ] ) ? sanitize_text_field( $source[ $key ] ) : $default;
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	private function animation_options() {
		return array(
			'none'       => __( 'None', 'wordpress-custom-popup-alert' ),
			'fade'       => __( 'Fade', 'wordpress-custom-popup-alert' ),
			'fade-scale' => __( 'Fade + scale', 'wordpress-custom-popup-alert' ),
			'slide'      => __( 'Slide', 'wordpress-custom-popup-alert' ),
		);
	}

	private function countdown_style_options() {
		return array(
			'none'         => __( 'Do not show countdown', 'wordpress-custom-popup-alert' ),
			'text'         => __( 'Text next to buttons', 'wordpress-custom-popup-alert' ),
			'progress'     => __( 'Progress bar at the bottom', 'wordpress-custom-popup-alert' ),
			'close_x'      => __( 'Around the close (X) button', 'wordpress-custom-popup-alert' ),
			'close_button' => __( 'In close-action button label', 'wordpress-custom-popup-alert' ),
		);
	}

	private function shadow_options() {
		return array(
			'none'   => __( 'None', 'wordpress-custom-popup-alert' ),
			'soft'   => __( 'Soft', 'wordpress-custom-popup-alert' ),
			'medium' => __( 'Medium', 'wordpress-custom-popup-alert' ),
			'strong' => __( 'Strong', 'wordpress-custom-popup-alert' ),
		);
	}

	private function icon_options() {
		return array(
			'none'          => __( 'No icon', 'wordpress-custom-popup-alert' ),
			'info'          => __( 'Information', 'wordpress-custom-popup-alert' ),
			'warning'       => __( 'Warning', 'wordpress-custom-popup-alert' ),
			'alert'         => __( 'Alert', 'wordpress-custom-popup-alert' ),
			'exclamation'   => __( 'Exclamation', 'wordpress-custom-popup-alert' ),
			'error'         => __( 'Error', 'wordpress-custom-popup-alert' ),
			'success'       => __( 'Success', 'wordpress-custom-popup-alert' ),
			'delivery'      => __( 'Delivery', 'wordpress-custom-popup-alert' ),
			'location'      => __( 'Location', 'wordpress-custom-popup-alert' ),
			'compatibility' => __( 'Compatibility', 'wordpress-custom-popup-alert' ),
			'help'          => __( 'Help', 'wordpress-custom-popup-alert' ),
		);
	}

	private function rule_summary( $rules ) {
		if ( ! is_array( $rules ) || empty( $rules['children'] ) ) {
			return '<em>' . esc_html__( 'No rules — will not be displayed', 'wordpress-custom-popup-alert' ) . '</em>';
		}

		$count = $this->count_conditions( $rules );
		if ( $count > 4 ) {
			return esc_html( sprintf( _n( '%d condition configured', '%d conditions configured', $count, 'wordpress-custom-popup-alert' ), $count ) );
		}

		return $this->summarize_group( $rules, true );
	}

	private function count_conditions( array $group ) {
		$count = 0;
		foreach ( isset( $group['children'] ) && is_array( $group['children'] ) ? $group['children'] : array() as $child ) {
			$count += is_array( $child ) && isset( $child['kind'] ) && 'group' === $child['kind'] ? $this->count_conditions( $child ) : 1;
		}
		return $count;
	}

	private function summarize_group( array $group, $root = false ) {
		$parts = array();
		foreach ( $group['children'] as $child ) {
			$parts[] = isset( $child['kind'] ) && 'group' === $child['kind'] ? $this->summarize_group( $child ) : $this->summarize_condition( $child );
		}
		$joiner = 'OR' === ( isset( $group['relation'] ) ? $group['relation'] : 'AND' ) ? ' <strong>' . esc_html__( 'OR', 'wordpress-custom-popup-alert' ) . '</strong> ' : ' <strong>' . esc_html__( 'AND', 'wordpress-custom-popup-alert' ) . '</strong> ';
		$text   = implode( $joiner, array_filter( $parts ) );
		return $root || count( $parts ) < 2 ? $text : '(' . $text . ')';
	}

	private function summarize_condition( array $node ) {
		$condition = isset( $node['type'] ) ? $this->engine->registry()->get( $node['type'] ) : null;
		if ( ! $condition ) {
			return esc_html__( 'Condition unavailable', 'wordpress-custom-popup-alert' );
		}
		$definition = $condition->definition();
		$operator   = isset( $definition['operators'][ $node['operator'] ] ) ? $definition['operators'][ $node['operator'] ] : $node['operator'];
		$value      = isset( $node['value'] ) ? $node['value'] : '';
		$values     = is_array( $value ) ? $value : array( $value );
		$labels     = array();
		foreach ( $values as $item ) {
			$key      = (string) $item;
			$labels[] = isset( $node['labels'][ $key ] ) ? $node['labels'][ $key ] : ( isset( $definition['options'][ $key ] ) ? $definition['options'][ $key ] : $key );
		}
		if ( 'boolean' === $definition['value_type'] ) {
			$labels = array( ! empty( $value ) ? __( 'Yes', 'wordpress-custom-popup-alert' ) : __( 'No', 'wordpress-custom-popup-alert' ) );
		}
		return esc_html( $definition['label'] . ' ' . $operator . ' ' . implode( ', ', $labels ) );
	}

	private function validity_summary( array $display ) {
		if ( empty( $display['schedule_enabled'] ) || ( empty( $display['starts_at'] ) && empty( $display['ends_at'] ) ) ) {
			return __( 'Always', 'wordpress-custom-popup-alert' );
		}
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$parts  = array();
		foreach ( array( 'starts_at' => __( 'From', 'wordpress-custom-popup-alert' ), 'ends_at' => __( 'Until', 'wordpress-custom-popup-alert' ) ) as $key => $label ) {
			if ( ! empty( $display[ $key ] ) ) {
				$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i', $display[ $key ], wp_timezone() );
				if ( $date ) {
					$parts[] = $label . ' ' . wp_date( $format, $date->getTimestamp(), wp_timezone() );
				}
			}
		}
		return implode( ' · ', $parts );
	}
}
