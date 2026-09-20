<?php
/**
 * Optional cleanup.
 *
 * Data is preserved by default. To remove alert posts, metadata, the plugin
 * version option and assigned capabilities on uninstall, define the following
 * in wp-config.php before deleting the plugin:
 *
 * define( 'WCCPA_REMOVE_DATA', true );
 *
 * @package WCCPA
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'WCCPA_REMOVE_DATA' ) || true !== WCCPA_REMOVE_DATA ) {
	return;
}

$alert_ids = get_posts(
	array(
		'post_type'      => 'wccpa_alert',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

foreach ( $alert_ids as $alert_id ) {
	wp_delete_post( $alert_id, true );
}

delete_option( 'wccpa_version' );

foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		$role->remove_cap( 'manage_popup_alerts' );
	}
}

