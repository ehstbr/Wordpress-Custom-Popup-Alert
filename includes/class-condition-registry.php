<?php
/**
 * Registrable condition catalogue.
 *
 * @package WCCPA
 */

namespace WCCPA;

use WCCPA\Conditions\Condition;
use WCCPA\Conditions\WordPress\Author_Condition;
use WCCPA\Conditions\WordPress\Category_Condition;
use WCCPA\Conditions\WordPress\Keyword_Condition;
use WCCPA\Conditions\WordPress\Post_ID_Condition;
use WCCPA\Conditions\WordPress\Post_Type_Condition;
use WCCPA\Conditions\WordPress\Tag_Condition;
use WCCPA\Conditions\WooCommerce\On_Sale_Condition;
use WCCPA\Conditions\WooCommerce\Page_Condition as WC_Page_Condition;
use WCCPA\Conditions\WooCommerce\Product_Category_Condition;
use WCCPA\Conditions\WooCommerce\Product_ID_Condition as WC_Product_ID_Condition;
use WCCPA\Conditions\WooCommerce\Product_Tag_Condition;
use WCCPA\Conditions\WooCommerce\Product_Type_Condition;
use WCCPA\Conditions\WooCommerce\Shipping_Class_Condition;
use WCCPA\Conditions\WooCommerce\Sku_Condition;
use WCCPA\Conditions\WooCommerce\Stock_Status_Condition;

defined( 'ABSPATH' ) || exit;

final class Condition_Registry {
	/** @var array<string,Condition>|null */
	private $conditions = null;

	/**
	 * Get registered conditions.
	 *
	 * @return array<string,Condition>
	 */
	public function all() {
		if ( null !== $this->conditions ) {
			return $this->conditions;
		}

		$conditions = array();
		$built_ins  = array(
			new Post_Type_Condition(),
			new Post_ID_Condition(),
			new Category_Condition(),
			new Tag_Condition(),
			new Author_Condition(),
			new Keyword_Condition(),
		);

		// Keep WooCommerce conditions known to the engine even while WooCommerce is
		// inactive. The admin catalogue hides them, but saved rules remain intact.
		$built_ins = array_merge(
			$built_ins,
			array(
				new WC_Page_Condition(),
				new WC_Product_ID_Condition(),
				new Product_Category_Condition(),
				new Product_Tag_Condition(),
				new Shipping_Class_Condition(),
				new On_Sale_Condition(),
				new Stock_Status_Condition(),
				new Product_Type_Condition(),
				new Sku_Condition(),
			)
		);

		foreach ( $built_ins as $condition ) {
			$definition = $condition->definition();
			$conditions[ $definition['key'] ] = $condition;
		}

		/**
		 * Filter condition instances keyed by their unique condition key.
		 *
		 * @param array<string,Condition> $conditions Registered conditions.
		 */
		$conditions = apply_filters( 'wccpa_registered_conditions', $conditions );

		$this->conditions = array_filter(
			$conditions,
			static function ( $condition ) {
				return $condition instanceof Condition;
			}
		);

		return $this->conditions;
	}

	/**
	 * Retrieve one condition.
	 *
	 * @param string $key Key.
	 * @return Condition|null
	 */
	public function get( $key ) {
		$all = $this->all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Definitions safe to send to the administrative builder.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function definitions( $for_admin = false ) {
		$definitions = array();
		$woocommerce_keys = array( 'woocommerce_page', 'product_id', 'product_category', 'product_tag', 'shipping_class', 'product_on_sale', 'stock_status', 'product_type', 'product_sku' );
		foreach ( $this->all() as $key => $condition ) {
			$definition = $condition->definition();
			if ( $for_admin && ! $this->has_woocommerce() && in_array( $key, $woocommerce_keys, true ) ) {
				continue;
			}
			$definitions[ $key ] = $definition;
		}

		return $definitions;
	}

	/**
	 * Whether WooCommerce rules are currently available.
	 *
	 * @return bool
	 */
	public function has_woocommerce() {
		return function_exists( 'wc_get_product' );
	}
}
