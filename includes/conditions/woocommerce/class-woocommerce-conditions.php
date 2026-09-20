<?php
/**
 * WooCommerce conditions. These classes are safe to load without WooCommerce;
 * the editor hides them while the integration is inactive.
 *
 * @package WCCPA
 */

namespace WCCPA\Conditions\WooCommerce;

use WCCPA\Conditions\Abstract_Condition;
use WCCPA\Evaluation_Context;

defined( 'ABSPATH' ) || exit;

final class Page_Condition extends Abstract_Condition {
	public function definition() {
		return array(
			'key'        => 'woocommerce_page',
			'label'      => __( 'WooCommerce page', 'wordpress-custom-popup-alert' ),
			'group'      => __( 'WooCommerce', 'wordpress-custom-popup-alert' ),
			'operators'  => array(
				'in'     => __( 'is any of', 'wordpress-custom-popup-alert' ),
				'not_in' => __( 'is none of', 'wordpress-custom-popup-alert' ),
			),
			'value_type' => 'select',
			'multiple'   => true,
			'options'    => array(
				'cart'     => __( 'Cart', 'wordpress-custom-popup-alert' ),
				'checkout' => __( 'Checkout', 'wordpress-custom-popup-alert' ),
			),
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$actual = array();

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$actual[] = 'cart';
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$actual[] = 'checkout';
		}

		return $this->compare_sets( $actual, $this->values( $condition ), $condition['operator'] );
	}
}

final class Product_ID_Condition extends Abstract_Condition {
	public function definition() {
		return array(
			'key'         => 'product_id',
			'label'       => __( 'Specific product', 'wordpress-custom-popup-alert' ),
			'group'       => __( 'WooCommerce', 'wordpress-custom-popup-alert' ),
			'operators'   => array(
				'in'     => __( 'is any of', 'wordpress-custom-popup-alert' ),
				'not_in' => __( 'is none of', 'wordpress-custom-popup-alert' ),
			),
			'value_type'  => 'ajax',
			'ajax_source' => 'product',
			'multiple'    => true,
			'placeholder' => __( 'Search by name, ID, or SKU', 'wordpress-custom-popup-alert' ),
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$product = $context->product();
		return $product ? $this->compare_sets( array( $product->get_id() ), $this->values( $condition ), $condition['operator'] ) : false;
	}
}

abstract class Product_Taxonomy_Condition extends Abstract_Condition {
	/** @var string */
	protected $taxonomy;

	/** @var string */
	protected $key;

	/** @var string */
	protected $label;

	public function definition() {
		return array(
			'key'         => $this->key,
			'label'       => $this->label,
			'group'       => __( 'WooCommerce', 'wordpress-custom-popup-alert' ),
			'operators'   => array(
				'in'      => __( 'belongs to any', 'wordpress-custom-popup-alert' ),
				'all'     => __( 'belongs to all', 'wordpress-custom-popup-alert' ),
				'not_in'  => __( 'does not belong to any', 'wordpress-custom-popup-alert' ),
				'not_all' => __( 'does not belong to all', 'wordpress-custom-popup-alert' ),
			),
			'value_type'  => 'ajax',
			'ajax_source' => 'term',
			'taxonomy'    => $this->taxonomy,
			'multiple'    => true,
			'placeholder' => __( 'Search terms', 'wordpress-custom-popup-alert' ),
		);
	}

	/**
	 * Product taxonomy IDs.
	 *
	 * @param mixed $product Product.
	 * @return int[]
	 */
	abstract protected function term_ids( $product );

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$product = $context->product();
		return $product ? $this->compare_sets( $this->term_ids( $product ), $this->values( $condition ), $condition['operator'] ) : false;
	}
}

final class Product_Category_Condition extends Product_Taxonomy_Condition {
	protected $taxonomy = 'product_cat';
	protected $key      = 'product_category';

	public function __construct() {
		$this->label = __( 'Product category', 'wordpress-custom-popup-alert' );
	}

	public function definition() {
		$definition          = parent::definition();
		$definition['extra'] = array(
			array(
				'key'     => 'include_children',
				'type'    => 'checkbox',
				'label'   => __( 'Include subcategories', 'wordpress-custom-popup-alert' ),
				'default' => true,
			),
		);
		return $definition;
	}

	protected function term_ids( $product ) {
		return array_map( 'intval', $product->get_category_ids() );
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$product = $context->product();
		if ( ! $product ) {
			return false;
		}

		$wanted           = array_map( 'intval', $this->values( $condition ) );
		$include_children = ! empty( $condition['extra']['include_children'] );
		$families         = null;

		if ( $include_children && taxonomy_exists( 'product_cat' ) ) {
			$families = array();
			foreach ( $wanted as $term_id ) {
				$children   = get_term_children( $term_id, 'product_cat' );
				$children   = is_wp_error( $children ) ? array() : array_map( 'intval', $children );
				$families[] = array_merge( array( $term_id ), $children );
			}
		}

		return $this->compare_sets( $this->term_ids( $product ), $wanted, $condition['operator'], $families );
	}
}

final class Product_Tag_Condition extends Product_Taxonomy_Condition {
	protected $taxonomy = 'product_tag';
	protected $key      = 'product_tag';

	public function __construct() {
		$this->label = __( 'Product tag', 'wordpress-custom-popup-alert' );
	}

	protected function term_ids( $product ) {
		return array_map( 'intval', $product->get_tag_ids() );
	}
}

final class Shipping_Class_Condition extends Abstract_Condition {
	public function definition() {
		return array(
			'key'         => 'shipping_class',
			'label'       => __( 'Shipping class', 'wordpress-custom-popup-alert' ),
			'group'       => __( 'WooCommerce', 'wordpress-custom-popup-alert' ),
			'operators'   => array(
				'in'     => __( 'is any of', 'wordpress-custom-popup-alert' ),
				'not_in' => __( 'is none of', 'wordpress-custom-popup-alert' ),
			),
			'value_type'  => 'ajax',
			'ajax_source' => 'term',
			'taxonomy'    => 'product_shipping_class',
			'multiple'    => true,
			'placeholder' => __( 'Search shipping classes', 'wordpress-custom-popup-alert' ),
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$product = $context->product();
		return $product ? $this->compare_sets( array( (int) $product->get_shipping_class_id() ), $this->values( $condition ), $condition['operator'] ) : false;
	}
}

final class On_Sale_Condition extends Abstract_Condition {
	public function definition() {
		return array(
			'key'        => 'product_on_sale',
			'label'      => __( 'Product on sale', 'wordpress-custom-popup-alert' ),
			'group'      => __( 'WooCommerce', 'wordpress-custom-popup-alert' ),
			'operators'  => array( 'equals' => __( 'is', 'wordpress-custom-popup-alert' ) ),
			'value_type' => 'boolean',
			'multiple'   => false,
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$product = $context->product();
		return $product ? ( (bool) $product->is_on_sale() === (bool) $condition['value'] ) : false;
	}
}

final class Stock_Status_Condition extends Abstract_Condition {
	public function definition() {
		$options = function_exists( 'wc_get_product_stock_status_options' )
			? wc_get_product_stock_status_options()
			: array(
				'instock'     => __( 'In stock', 'wordpress-custom-popup-alert' ),
				'outofstock'  => __( 'Out of stock', 'wordpress-custom-popup-alert' ),
				'onbackorder' => __( 'On backorder', 'wordpress-custom-popup-alert' ),
			);

		return array(
			'key'        => 'stock_status',
			'label'      => __( 'Stock status', 'wordpress-custom-popup-alert' ),
			'group'      => __( 'WooCommerce', 'wordpress-custom-popup-alert' ),
			'operators'  => array(
				'in'     => __( 'is any of', 'wordpress-custom-popup-alert' ),
				'not_in' => __( 'is none of', 'wordpress-custom-popup-alert' ),
			),
			'value_type' => 'select',
			'multiple'   => true,
			'options'    => $options,
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$product = $context->product();
		return $product ? $this->compare_sets( array( $product->get_stock_status() ), $this->values( $condition ), $condition['operator'] ) : false;
	}
}

final class Product_Type_Condition extends Abstract_Condition {
	public function definition() {
		$options = function_exists( 'wc_get_product_types' )
			? wc_get_product_types()
			: array(
				'simple'   => __( 'Simple', 'wordpress-custom-popup-alert' ),
				'variable' => __( 'Variable', 'wordpress-custom-popup-alert' ),
				'grouped'  => __( 'Grouped', 'wordpress-custom-popup-alert' ),
				'external' => __( 'External/Affiliate', 'wordpress-custom-popup-alert' ),
			);

		return array(
			'key'        => 'product_type',
			'label'      => __( 'Product type', 'wordpress-custom-popup-alert' ),
			'group'      => __( 'WooCommerce', 'wordpress-custom-popup-alert' ),
			'operators'  => array(
				'in'     => __( 'is any of', 'wordpress-custom-popup-alert' ),
				'not_in' => __( 'is none of', 'wordpress-custom-popup-alert' ),
			),
			'value_type' => 'select',
			'multiple'   => true,
			'options'    => $options,
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$product = $context->product();
		return $product ? $this->compare_sets( array( $product->get_type() ), $this->values( $condition ), $condition['operator'] ) : false;
	}
}

final class Sku_Condition extends Abstract_Condition {
	public function definition() {
		return array(
			'key'        => 'product_sku',
			'label'      => __( 'SKU', 'wordpress-custom-popup-alert' ),
			'group'      => __( 'WooCommerce', 'wordpress-custom-popup-alert' ),
			'operators'  => array(
				'equals'       => __( 'is', 'wordpress-custom-popup-alert' ),
				'not_equals'   => __( 'is not', 'wordpress-custom-popup-alert' ),
				'contains'     => __( 'contains', 'wordpress-custom-popup-alert' ),
				'not_contains' => __( 'does not contain', 'wordpress-custom-popup-alert' ),
				'starts_with'  => __( 'starts with', 'wordpress-custom-popup-alert' ),
				'ends_with'    => __( 'ends with', 'wordpress-custom-popup-alert' ),
			),
			'value_type' => 'text',
			'multiple'   => false,
			'placeholder'=> __( 'Product SKU', 'wordpress-custom-popup-alert' ),
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$product = $context->product();
		return $product ? $this->compare_text( $product->get_sku(), (string) $condition['value'], $condition['operator'] ) : false;
	}
}
