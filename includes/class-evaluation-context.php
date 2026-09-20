<?php
/**
 * Lazy request context shared by conditions.
 *
 * @package WCCPA
 */

namespace WCCPA;

defined( 'ABSPATH' ) || exit;

final class Evaluation_Context {
	/** @var int|null */
	private $post_id = null;

	/** @var \WP_Post|null|false */
	private $post = false;

	/** @var mixed */
	private $product = false;

	/**
	 * Current singular post ID.
	 *
	 * @return int
	 */
	public function post_id() {
		if ( null === $this->post_id ) {
			$this->post_id = is_singular() ? (int) get_queried_object_id() : 0;
		}

		return $this->post_id;
	}

	/**
	 * Current singular post.
	 *
	 * @return \WP_Post|null
	 */
	public function post() {
		if ( false === $this->post ) {
			$this->post = $this->post_id() ? get_post( $this->post_id() ) : null;
		}

		return $this->post;
	}

	/**
	 * Current WooCommerce product, when available.
	 *
	 * @return mixed|null
	 */
	public function product() {
		if ( false !== $this->product ) {
			return $this->product;
		}

		$this->product = null;

		if ( function_exists( 'wc_get_product' ) && $this->post_id() && 'product' === get_post_type( $this->post_id() ) ) {
			$product = wc_get_product( $this->post_id() );
			if ( $product ) {
				$this->product = $product;
			}
		}

		return $this->product;
	}
}

