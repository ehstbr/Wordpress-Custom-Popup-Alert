<?php
/**
 * Alert metadata defaults and normalization.
 *
 * @package WCCPA
 */

namespace WCCPA;

defined( 'ABSPATH' ) || exit;

final class Alert_Meta {
	/**
	 * Default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'display'   => array(
				'public_title'    => '',
				'schedule_enabled' => false,
				'starts_at'       => '',
				'ends_at'         => '',
			),
			'frequency' => array(
				'mode' => 'always',
				'days' => 7,
			),
			'behavior'  => array(
				'close_x'           => true,
				'close_overlay'     => true,
				'close_esc'         => true,
				'auto_close'        => false,
				'auto_close_seconds'=> 10,
				'countdown'         => false,
				'countdown_style'   => 'none',
				'pause_on_hover'    => false,
				'delay'             => 0,
				'animation'         => 'fade-scale',
				'primary_button'    => array(
					'enabled' => true,
					'label'   => __( 'Got it', 'wordpress-custom-popup-alert' ),
					'action'  => 'close',
					'url'     => '',
					'new_tab' => false,
					'style'   => 'primary',
				),
				'secondary_button'  => array(
					'enabled' => false,
					'label'   => __( 'Close', 'wordpress-custom-popup-alert' ),
					'action'  => 'close',
					'url'     => '',
					'new_tab' => false,
					'style'   => 'secondary',
				),
			),
			'appearance'=> array(
				'width'           => 600,
				'width_unit'      => 'px',
				'max_height'      => 80,
				'max_height_unit' => 'vh',
				'padding'         => 24,
				'background'      => '#ffffff',
				'text_color'      => '#1d2327',
				'radius'          => 8,
				'border_enabled'  => false,
				'border_width'    => 1,
				'border_style'    => 'solid',
				'border_color'    => '#dcdcde',
				'shadow'          => 'medium',
				'overlay_enabled' => true,
				'overlay_color'   => '#000000',
				'overlay_opacity' => 58,
				'overlay_blur'    => 0,
				'topbar'          => true,
				'topbar_bg'       => '#d63638',
				'topbar_text'     => '#ffffff',
				'icon'            => 'warning',
			),
			'priority'  => 50,
			'exclusive' => false,
		);
	}

	/**
	 * Get all settings for an alert with defaults merged recursively.
	 *
	 * @param int $post_id Alert ID.
	 * @return array<string,mixed>
	 */
	public static function get_all( $post_id ) {
		$defaults     = self::defaults();
		$raw_display  = get_post_meta( $post_id, '_wccpa_display', true );
		$raw_behavior = get_post_meta( $post_id, '_wccpa_behavior', true );
		$display      = self::merge( $defaults['display'], $raw_display );
		$behavior     = self::merge( $defaults['behavior'], $raw_behavior );

		// Alerts saved before explicit scheduling was introduced remain scheduled
		// when at least one legacy date is present.
		if ( is_array( $raw_display ) && ! array_key_exists( 'schedule_enabled', $raw_display ) ) {
			$display['schedule_enabled'] = ! empty( $raw_display['starts_at'] ) || ! empty( $raw_display['ends_at'] );
		}

		// Preserve the former countdown checkbox as the text presentation mode.
		if ( is_array( $raw_behavior ) && ! array_key_exists( 'countdown_style', $raw_behavior ) ) {
			$behavior['countdown_style'] = ! empty( $raw_behavior['countdown'] ) ? 'text' : 'none';
		}

		return array(
			'display'    => $display,
			'frequency'  => self::merge( $defaults['frequency'], get_post_meta( $post_id, '_wccpa_frequency', true ) ),
			'behavior'   => $behavior,
			'appearance' => self::merge( $defaults['appearance'], get_post_meta( $post_id, '_wccpa_appearance', true ) ),
			'priority'   => (int) self::scalar_or_default( get_post_meta( $post_id, '_wccpa_priority', true ), $defaults['priority'] ),
			'exclusive'  => (bool) get_post_meta( $post_id, '_wccpa_exclusive', true ),
		);
	}

	/**
	 * Merge known keys only, recursively.
	 *
	 * @param array $defaults Defaults.
	 * @param mixed $value Stored value.
	 * @return array
	 */
	private static function merge( array $defaults, $value ) {
		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $value ) ) {
				continue;
			}

			if ( is_array( $default ) && is_array( $value[ $key ] ) ) {
				$defaults[ $key ] = self::merge( $default, $value[ $key ] );
			} else {
				$defaults[ $key ] = $value[ $key ];
			}
		}

		return $defaults;
	}

	/**
	 * Preserve zero while falling back on empty metadata.
	 *
	 * @param mixed $value Value.
	 * @param mixed $default Default.
	 * @return mixed
	 */
	private static function scalar_or_default( $value, $default ) {
		return '' === $value || null === $value ? $default : $value;
	}
}
