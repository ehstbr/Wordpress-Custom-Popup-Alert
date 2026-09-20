<?php
/**
 * Recursive AND/OR rule engine.
 *
 * @package WCCPA
 */

namespace WCCPA;

defined( 'ABSPATH' ) || exit;

final class Rule_Engine {
	const MAX_DEPTH = 5;
	const MAX_NODES = 100;

	/** @var Condition_Registry */
	private $registry;

	public function __construct( Condition_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Registry accessor for administrative services.
	 *
	 * @return Condition_Registry
	 */
	public function registry() {
		return $this->registry;
	}

	/**
	 * Evaluate a rule tree.
	 *
	 * @param mixed                   $rules Rule tree.
	 * @param Evaluation_Context|null $context Optional shared context.
	 * @return bool
	 */
	public function matches( $rules, Evaluation_Context $context = null ) {
		if ( ! is_array( $rules ) ) {
			return false;
		}

		$context = $context ? $context : new Evaluation_Context();
		return $this->evaluate_group( $rules, $context, 0 );
	}

	/**
	 * Validate and normalize an untrusted tree.
	 *
	 * @param mixed $rules Raw tree.
	 * @return array<string,mixed>
	 */
	public function sanitize( $rules ) {
		$counter = 0;
		$group   = $this->sanitize_group( is_array( $rules ) ? $rules : array(), 0, $counter );

		return $group ? $group : $this->empty_rules();
	}

	/**
	 * Empty tree.
	 *
	 * @return array<string,mixed>
	 */
	public function empty_rules() {
		return array(
			'kind'     => 'group',
			'relation' => 'AND',
			'children' => array(),
		);
	}

	/**
	 * Evaluate group recursively.
	 *
	 * @param array              $group Group.
	 * @param Evaluation_Context $context Context.
	 * @param int                $depth Depth.
	 * @return bool
	 */
	private function evaluate_group( array $group, Evaluation_Context $context, $depth ) {
		if ( $depth > self::MAX_DEPTH ) {
			return false;
		}

		$children = isset( $group['children'] ) && is_array( $group['children'] ) ? $group['children'] : array();
		if ( empty( $children ) ) {
			return false;
		}

		$relation = isset( $group['relation'] ) && 'OR' === strtoupper( $group['relation'] ) ? 'OR' : 'AND';

		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				$result = false;
			} elseif ( isset( $child['kind'] ) && 'group' === $child['kind'] ) {
				$result = $this->evaluate_group( $child, $context, $depth + 1 );
			} else {
				$result = $this->evaluate_condition( $child, $context );
			}

			if ( 'AND' === $relation && ! $result ) {
				return false;
			}

			if ( 'OR' === $relation && $result ) {
				return true;
			}
		}

		return 'AND' === $relation;
	}

	/**
	 * Evaluate one condition.
	 *
	 * @param array              $node Condition.
	 * @param Evaluation_Context $context Context.
	 * @return bool
	 */
	private function evaluate_condition( array $node, Evaluation_Context $context ) {
		$type      = isset( $node['type'] ) ? sanitize_key( $node['type'] ) : '';
		$condition = $this->registry->get( $type );
		$result    = false;

		if ( $condition ) {
			try {
				$result = (bool) $condition->evaluate( $node, $context );
			} catch ( \Throwable $exception ) {
				$result = false;
			}
		}

		/**
		 * Filter the result of one condition.
		 *
		 * @param bool               $result Condition result.
		 * @param array              $node Condition configuration.
		 * @param Evaluation_Context $context Request context.
		 */
		return (bool) apply_filters( 'wccpa_condition_result', $result, $node, $context );
	}

	/**
	 * Sanitize a group.
	 *
	 * @param array $group Group.
	 * @param int   $depth Depth.
	 * @param int   $counter Global node counter.
	 * @return array|null
	 */
	private function sanitize_group( array $group, $depth, &$counter ) {
		if ( $depth > self::MAX_DEPTH || $counter >= self::MAX_NODES ) {
			return null;
		}

		++$counter;
		$clean = array(
			'kind'     => 'group',
			'relation' => isset( $group['relation'] ) && 'OR' === strtoupper( (string) $group['relation'] ) ? 'OR' : 'AND',
			'children' => array(),
		);

		$children = isset( $group['children'] ) && is_array( $group['children'] ) ? $group['children'] : array();
		foreach ( $children as $child ) {
			if ( $counter >= self::MAX_NODES || ! is_array( $child ) ) {
				break;
			}

			if ( isset( $child['kind'] ) && 'group' === $child['kind'] ) {
				$sanitized = $this->sanitize_group( $child, $depth + 1, $counter );
			} else {
				$sanitized = $this->sanitize_condition( $child, $counter );
			}

			if ( $sanitized ) {
				$clean['children'][] = $sanitized;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize one condition according to its registered definition.
	 *
	 * @param array $node Node.
	 * @param int   $counter Counter.
	 * @return array|null
	 */
	private function sanitize_condition( array $node, &$counter ) {
		$type      = isset( $node['type'] ) ? sanitize_key( $node['type'] ) : '';
		$condition = $this->registry->get( $type );
		if ( ! $condition ) {
			return null;
		}

		++$counter;
		$definition = $condition->definition();
		$operators  = isset( $definition['operators'] ) ? array_keys( $definition['operators'] ) : array();
		$operator   = isset( $node['operator'] ) ? sanitize_key( $node['operator'] ) : '';
		if ( ! in_array( $operator, $operators, true ) ) {
			$operator = $operators ? reset( $operators ) : 'equals';
		}

		$value_type = isset( $definition['value_type'] ) ? $definition['value_type'] : 'text';
		$multiple   = ! empty( $definition['multiple'] );
		$raw_value  = isset( $node['value'] ) ? $node['value'] : ( $multiple ? array() : '' );

		if ( $multiple ) {
			$raw_values = is_array( $raw_value ) ? $raw_value : array( $raw_value );
			$value      = array();
			foreach ( array_slice( $raw_values, 0, 50 ) as $item ) {
				$clean_item = $this->sanitize_value( $item, $value_type );
				if ( '' !== $clean_item && null !== $clean_item && ( 'ajax' !== $value_type || 0 !== $clean_item ) ) {
					$value[] = $clean_item;
				}
			}
			$value = array_values( array_unique( $value, SORT_REGULAR ) );
		} else {
			$value = $this->sanitize_value( $raw_value, $value_type );
		}

		$clean = array(
			'kind'     => 'condition',
			'type'     => $type,
			'operator' => $operator,
			'value'    => $value,
		);

		if ( isset( $node['labels'] ) && is_array( $node['labels'] ) ) {
			$clean['labels'] = array();
			foreach ( array_slice( $node['labels'], 0, 50, true ) as $label_key => $label ) {
				$clean['labels'][ sanitize_text_field( (string) $label_key ) ] = sanitize_text_field( (string) $label );
			}
		}

		if ( ! empty( $definition['extra'] ) ) {
			$clean['extra'] = array();
			$raw_extra      = isset( $node['extra'] ) && is_array( $node['extra'] ) ? $node['extra'] : array();
			foreach ( $definition['extra'] as $extra ) {
				$key     = sanitize_key( $extra['key'] );
				$default = isset( $extra['default'] ) ? $extra['default'] : '';
				$raw     = array_key_exists( $key, $raw_extra ) ? $raw_extra[ $key ] : $default;
				if ( 'checkbox' === $extra['type'] ) {
					$clean['extra'][ $key ] = ! empty( $raw );
				} elseif ( 'select' === $extra['type'] ) {
					$options = isset( $extra['options'] ) ? array_keys( $extra['options'] ) : array();
					$raw     = sanitize_key( (string) $raw );
					$clean['extra'][ $key ] = in_array( $raw, $options, true ) ? $raw : $default;
				} else {
					$clean['extra'][ $key ] = sanitize_text_field( (string) $raw );
				}
			}
		}

		return $clean;
	}

	/**
	 * Sanitize a value by its UI/data type.
	 *
	 * @param mixed  $value Value.
	 * @param string $value_type Type.
	 * @return mixed
	 */
	private function sanitize_value( $value, $value_type ) {
		switch ( $value_type ) {
			case 'ajax':
				return absint( $value );
			case 'boolean':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			case 'select':
				return sanitize_key( (string) $value );
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
