<?php
/**
 * Rule condition contract.
 *
 * @package WCCPA
 */

namespace WCCPA\Conditions;

use WCCPA\Evaluation_Context;

defined( 'ABSPATH' ) || exit;

interface Condition {
	/**
	 * Condition definition used by both the engine and the editor.
	 *
	 * @return array<string,mixed>
	 */
	public function definition();

	/**
	 * Evaluate a normalized condition node.
	 *
	 * @param array<string,mixed> $condition Condition node.
	 * @param Evaluation_Context  $context Current request context.
	 * @return bool
	 */
	public function evaluate( array $condition, Evaluation_Context $context );
}

