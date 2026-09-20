<?php
/**
 * Resolve server-eligible alerts for the current request.
 *
 * @package WCCPA
 */

namespace WCCPA;

defined( 'ABSPATH' ) || exit;

final class Alert_Resolver {
	/** @var Rule_Engine */
	private $engine;

	public function __construct( Rule_Engine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Resolve and normalize alerts.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function resolve() {
		$query = new \WP_Query(
			array(
				'post_type'              => Post_Type::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'orderby'                => array(
					'menu_order' => 'DESC',
					'ID'         => 'ASC',
				),
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			return array();
		}

		$alerts  = array();
		$context = new Evaluation_Context();

		foreach ( $query->posts as $post ) {
			$meta = Alert_Meta::get_all( $post->ID );
			if ( ! $this->is_in_schedule( $meta['display'] ) ) {
				continue;
			}

			$rules   = get_post_meta( $post->ID, '_wccpa_rules', true );
			$matches = $this->engine->matches( $rules, $context );

			/**
			 * Filter whether an alert matches the current request.
			 *
			 * @param bool               $matches Result.
			 * @param \WP_Post           $post Alert post.
			 * @param Evaluation_Context $context Request context.
			 */
			$matches = (bool) apply_filters( 'wccpa_alert_matches', $matches, $post, $context );
			if ( ! $matches ) {
				continue;
			}

			$content = apply_filters( 'the_content', $post->post_content );
			$content = apply_filters( 'wccpa_alert_content', $content, $post );

			$alerts[] = array(
				'id'         => (int) $post->ID,
				'title'      => $meta['display']['public_title'] ? $meta['display']['public_title'] : __( 'Alert', 'wordpress-custom-popup-alert' ),
				'content'    => wp_kses_post( $content ),
				'priority'   => max( 1, min( 100, (int) $meta['priority'] ) ),
				'exclusive'  => (bool) $meta['exclusive'],
				'frequency'  => $meta['frequency'],
				'behavior'   => $meta['behavior'],
				'appearance' => $meta['appearance'],
			);
		}

		usort(
			$alerts,
			static function ( $left, $right ) {
				if ( $left['priority'] === $right['priority'] ) {
					return $left['id'] <=> $right['id'];
				}
				return $right['priority'] <=> $left['priority'];
			}
		);

		return $alerts;
	}

	/**
	 * Check WordPress-timezone schedule bounds.
	 *
	 * @param array $display Display metadata.
	 * @return bool
	 */
	private function is_in_schedule( array $display ) {
		if ( empty( $display['schedule_enabled'] ) ) {
			return true;
		}

		$now = current_datetime();

		foreach ( array( 'starts_at' => 'after', 'ends_at' => 'before' ) as $key => $direction ) {
			if ( empty( $display[ $key ] ) ) {
				continue;
			}

			$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i', $display[ $key ], wp_timezone() );
			if ( ! $date ) {
				continue;
			}

			if ( 'after' === $direction && $now < $date ) {
				return false;
			}

			if ( 'before' === $direction && $now > $date ) {
				return false;
			}
		}

		return true;
	}
}
