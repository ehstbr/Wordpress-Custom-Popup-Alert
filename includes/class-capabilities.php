<?php
/**
 * Capabilities.
 *
 * @package WCCPA
 */

namespace WCCPA;

defined( 'ABSPATH' ) || exit;

final class Capabilities {
	const CAPABILITY = 'manage_popup_alerts';

	/**
	 * Grant plugin management to administrators and, when present, shop managers.
	 *
	 * @return void
	 */
	public static function ensure() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
			}
		}
	}
}

