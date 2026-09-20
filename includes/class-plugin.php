<?php
/**
 * Main plugin orchestrator.
 *
 * @package WCCPA
 */

namespace WCCPA;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/** @var Plugin|null */
	private static $instance = null;

	/** @var Rule_Engine */
	private $rule_engine;

	/** @var bool */
	private $booted = false;

	/**
	 * Get the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Start the plugin.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;
		$this->load_dependencies();

		$registry          = new Condition_Registry();
		$this->rule_engine = new Rule_Engine( $registry );

		new Post_Type();

		if ( is_admin() ) {
			new Admin\Admin( $this->rule_engine );
		}

		$resolver = new Alert_Resolver( $this->rule_engine );
		new Frontend\Frontend( $resolver, new Renderer() );

		add_action( 'init', array( Capabilities::class, 'ensure' ), 20 );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once __DIR__ . '/class-capabilities.php';
		require_once __DIR__ . '/class-post-type.php';

		Capabilities::ensure();
		Post_Type::register();
		flush_rewrite_rules();
		update_option( 'wccpa_version', WCCPA_VERSION, false );
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wordpress-custom-popup-alert',
			false,
			dirname( plugin_basename( WCCPA_FILE ) ) . '/languages'
		);
	}

	/**
	 * Declare compatibility with WooCommerce features used around stores.
	 *
	 * @return void
	 */
	public function declare_woocommerce_compatibility() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WCCPA_FILE,
				true
			);
		}
	}

	/**
	 * Require internal classes.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$files = array(
			'class-capabilities.php',
			'class-post-type.php',
			'class-alert-meta.php',
			'conditions/interface-condition.php',
			'conditions/abstract-condition.php',
			'class-evaluation-context.php',
			'conditions/wordpress/class-wordpress-conditions.php',
			'conditions/woocommerce/class-woocommerce-conditions.php',
			'class-condition-registry.php',
			'class-rule-engine.php',
			'class-alert-resolver.php',
			'class-renderer.php',
			'admin/class-admin.php',
			'frontend/class-frontend.php',
		);

		foreach ( $files as $file ) {
			require_once WCCPA_PATH . 'includes/' . $file;
		}
	}
}
