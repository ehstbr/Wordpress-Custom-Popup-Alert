<?php
/**
 * Frontend lifecycle and conditional assets.
 *
 * @package WCCPA
 */

namespace WCCPA\Frontend;

use WCCPA\Alert_Resolver;
use WCCPA\Renderer;

defined( 'ABSPATH' ) || exit;

final class Frontend {
	/** @var Alert_Resolver */
	private $resolver;

	/** @var Renderer */
	private $renderer;

	/** @var array */
	private $alerts = array();

	public function __construct( Alert_Resolver $resolver, Renderer $renderer ) {
		$this->resolver = $resolver;
		$this->renderer = $renderer;

		add_action( 'wp', array( $this, 'prepare' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'output' ), 100 );
	}

	/**
	 * Resolve alerts after the main query is known.
	 *
	 * @return void
	 */
	public function prepare() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
			return;
		}

		$this->alerts = $this->resolver->resolve();
	}

	/**
	 * Load no frontend assets when no alert can match on the server.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( empty( $this->alerts ) ) {
			return;
		}

		wp_enqueue_style(
			'wccpa-popup',
			WCCPA_URL . 'assets/frontend/popup.css',
			array(),
			WCCPA_VERSION
		);
		wp_enqueue_script(
			'wccpa-popup',
			WCCPA_URL . 'assets/frontend/popup.js',
			array(),
			WCCPA_VERSION,
			true
		);
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
	}

	/**
	 * Output the modal shell.
	 *
	 * @return void
	 */
	public function output() {
		$this->renderer->render( $this->alerts );
	}
}
