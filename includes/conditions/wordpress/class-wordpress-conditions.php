<?php
/**
 * Native WordPress conditions.
 *
 * @package WCCPA
 */

namespace WCCPA\Conditions\WordPress;

use WCCPA\Conditions\Abstract_Condition;
use WCCPA\Evaluation_Context;

defined( 'ABSPATH' ) || exit;

final class Post_Type_Condition extends Abstract_Condition {
	public function definition() {
		$options = array();
		$objects = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $objects as $object ) {
			$options[ $object->name ] = $object->labels->singular_name;
		}

		return array(
			'key'        => 'post_type',
			'label'      => __( 'Content type', 'wordpress-custom-popup-alert' ),
			'group'      => __( 'WordPress', 'wordpress-custom-popup-alert' ),
			'operators'  => self::set_operators(),
			'value_type' => 'select',
			'multiple'   => true,
			'options'    => $options,
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$post = $context->post();
		return $post ? $this->compare_sets( array( $post->post_type ), $this->values( $condition ), $condition['operator'] ) : false;
	}

	private static function set_operators() {
		return array(
			'in'     => __( 'is any of', 'wordpress-custom-popup-alert' ),
			'not_in' => __( 'is none of', 'wordpress-custom-popup-alert' ),
		);
	}
}

final class Post_ID_Condition extends Abstract_Condition {
	public function definition() {
		return array(
			'key'         => 'post_id',
			'label'       => __( 'Specific content', 'wordpress-custom-popup-alert' ),
			'group'       => __( 'WordPress', 'wordpress-custom-popup-alert' ),
			'operators'   => array(
				'in'     => __( 'is any of', 'wordpress-custom-popup-alert' ),
				'not_in' => __( 'is none of', 'wordpress-custom-popup-alert' ),
			),
			'value_type'  => 'ajax',
			'ajax_source' => 'content',
			'multiple'    => true,
			'placeholder' => __( 'Search by title or ID', 'wordpress-custom-popup-alert' ),
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		return $context->post_id() ? $this->compare_sets( array( $context->post_id() ), $this->values( $condition ), $condition['operator'] ) : false;
	}
}

abstract class Taxonomy_Condition extends Abstract_Condition {
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
			'group'       => __( 'WordPress', 'wordpress-custom-popup-alert' ),
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

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$post_id = $context->post_id();
		if ( ! $post_id || ! taxonomy_exists( $this->taxonomy ) ) {
			return false;
		}

		$term_ids = wp_get_object_terms( $post_id, $this->taxonomy, array( 'fields' => 'ids' ) );
		return is_wp_error( $term_ids ) ? false : $this->compare_sets( $term_ids, $this->values( $condition ), $condition['operator'] );
	}
}

final class Category_Condition extends Taxonomy_Condition {
	protected $taxonomy = 'category';
	protected $key      = 'post_category';

	public function __construct() {
		$this->label = __( 'Post category', 'wordpress-custom-popup-alert' );
	}
}

final class Tag_Condition extends Taxonomy_Condition {
	protected $taxonomy = 'post_tag';
	protected $key      = 'post_tag';

	public function __construct() {
		$this->label = __( 'Post tag', 'wordpress-custom-popup-alert' );
	}
}

final class Author_Condition extends Abstract_Condition {
	public function definition() {
		return array(
			'key'         => 'post_author',
			'label'       => __( 'Author', 'wordpress-custom-popup-alert' ),
			'group'       => __( 'WordPress', 'wordpress-custom-popup-alert' ),
			'operators'   => array(
				'in'     => __( 'is any of', 'wordpress-custom-popup-alert' ),
				'not_in' => __( 'is none of', 'wordpress-custom-popup-alert' ),
			),
			'value_type'  => 'ajax',
			'ajax_source' => 'user',
			'multiple'    => true,
			'placeholder' => __( 'Search authors', 'wordpress-custom-popup-alert' ),
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$post = $context->post();
		return $post ? $this->compare_sets( array( (int) $post->post_author ), $this->values( $condition ), $condition['operator'] ) : false;
	}
}

final class Keyword_Condition extends Abstract_Condition {
	public function definition() {
		return array(
			'key'        => 'keyword',
			'label'      => __( 'Keyword', 'wordpress-custom-popup-alert' ),
			'group'      => __( 'WordPress', 'wordpress-custom-popup-alert' ),
			'operators'  => array(
				'contains'     => __( 'contains', 'wordpress-custom-popup-alert' ),
				'not_contains' => __( 'does not contain', 'wordpress-custom-popup-alert' ),
			),
			'value_type' => 'text',
			'multiple'   => false,
			'placeholder'=> __( 'Word or phrase', 'wordpress-custom-popup-alert' ),
			'extra'      => array(
				array(
					'key'     => 'scope',
					'type'    => 'select',
					'label'   => __( 'Search in', 'wordpress-custom-popup-alert' ),
					'default' => 'all',
					'options' => array(
						'title'   => __( 'Title', 'wordpress-custom-popup-alert' ),
						'excerpt' => __( 'Excerpt', 'wordpress-custom-popup-alert' ),
						'content' => __( 'Content', 'wordpress-custom-popup-alert' ),
						'all'     => __( 'Title, excerpt, and content', 'wordpress-custom-popup-alert' ),
					),
				),
			),
		);
	}

	public function evaluate( array $condition, Evaluation_Context $context ) {
		$post = $context->post();
		if ( ! $post ) {
			return false;
		}

		$scope = isset( $condition['extra']['scope'] ) ? $condition['extra']['scope'] : 'all';
		switch ( $scope ) {
			case 'title':
				$haystack = $post->post_title;
				break;
			case 'excerpt':
				$haystack = $post->post_excerpt;
				break;
			case 'content':
				$haystack = $post->post_content;
				break;
			default:
				$haystack = implode( "\n", array( $post->post_title, $post->post_excerpt, $post->post_content ) );
		}

		return $this->compare_text( wp_strip_all_tags( $haystack ), (string) $condition['value'], $condition['operator'] );
	}
}
