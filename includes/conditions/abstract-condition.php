<?php
/**
 * Shared condition comparison helpers.
 *
 * @package WCCPA
 */

namespace WCCPA\Conditions;

defined( 'ABSPATH' ) || exit;

abstract class Abstract_Condition implements Condition {
	/**
	 * Compare two sets.
	 *
	 * @param array        $actual Actual values.
	 * @param array        $wanted Configured values.
	 * @param string       $operator Operator.
	 * @param array[]|null $families Optional selected-value families for hierarchical terms.
	 * @return bool
	 */
	protected function compare_sets( array $actual, array $wanted, $operator, $families = null ) {
		$actual = array_values( array_unique( array_map( 'strval', $actual ) ) );
		$wanted = array_values( array_unique( array_map( 'strval', $wanted ) ) );

		if ( empty( $wanted ) ) {
			return false;
		}

		if ( is_array( $families ) && $families ) {
			$matches = array();
			foreach ( $families as $family ) {
				$matches[] = (bool) array_intersect( $actual, array_map( 'strval', $family ) );
			}

			$any = in_array( true, $matches, true );
			$all = ! in_array( false, $matches, true );
		} else {
			$intersection = array_intersect( $actual, $wanted );
			$any          = ! empty( $intersection );
			$all          = count( $intersection ) === count( $wanted );
		}

		switch ( $operator ) {
			case 'not_in':
				return ! $any;
			case 'all':
				return $all;
			case 'not_all':
				return ! $all;
			case 'in':
			default:
				return $any;
		}
	}

	/**
	 * Compare text using case-insensitive semantics.
	 *
	 * @param string $actual Actual text.
	 * @param string $wanted Wanted text.
	 * @param string $operator Operator.
	 * @return bool
	 */
	protected function compare_text( $actual, $wanted, $operator ) {
		$actual = (string) $actual;
		$wanted = (string) $wanted;

		if ( '' === $wanted && ! in_array( $operator, array( 'empty', 'not_empty' ), true ) ) {
			return false;
		}

		$lower_actual = function_exists( 'mb_strtolower' ) ? mb_strtolower( $actual ) : strtolower( $actual );
		$lower_wanted = function_exists( 'mb_strtolower' ) ? mb_strtolower( $wanted ) : strtolower( $wanted );
		$position     = false;

		if ( function_exists( 'mb_strpos' ) ) {
			$position = mb_strpos( $lower_actual, $lower_wanted );
		} else {
			$position = strpos( $lower_actual, $lower_wanted );
		}

		switch ( $operator ) {
			case 'not_equals':
				return $lower_actual !== $lower_wanted;
			case 'contains':
				return false !== $position;
			case 'not_contains':
				return false === $position;
			case 'starts_with':
				return 0 === $position;
			case 'ends_with':
				if ( '' === $lower_wanted ) {
					return false;
				}
				return substr( $lower_actual, -strlen( $lower_wanted ) ) === $lower_wanted;
			case 'empty':
				return '' === trim( $actual );
			case 'not_empty':
				return '' !== trim( $actual );
			case 'equals':
			default:
				return $lower_actual === $lower_wanted;
		}
	}

	/**
	 * Get a normalized array value.
	 *
	 * @param array $condition Condition.
	 * @return array
	 */
	protected function values( array $condition ) {
		$value = isset( $condition['value'] ) ? $condition['value'] : array();
		return is_array( $value ) ? array_values( $value ) : array( $value );
	}
}

