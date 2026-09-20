<?php
/**
 * Alert custom post type.
 *
 * @package WCCPA
 */

namespace WCCPA;

defined( 'ABSPATH' ) || exit;

final class Post_Type {
	const POST_TYPE = 'wccpa_alert';

	public function __construct() {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
	}

	/**
	 * Register the private administrative post type.
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'                   => __( 'Popup Alerts', 'wordpress-custom-popup-alert' ),
			'singular_name'          => __( 'Popup Alert', 'wordpress-custom-popup-alert' ),
			'menu_name'              => __( 'Popup Alerts', 'wordpress-custom-popup-alert' ),
			'name_admin_bar'         => __( 'Popup Alert', 'wordpress-custom-popup-alert' ),
			'add_new'                => __( 'Add New', 'wordpress-custom-popup-alert' ),
			'add_new_item'           => __( 'Add New Popup Alert', 'wordpress-custom-popup-alert' ),
			'new_item'               => __( 'New Popup Alert', 'wordpress-custom-popup-alert' ),
			'edit_item'              => __( 'Edit Popup Alert', 'wordpress-custom-popup-alert' ),
			'view_item'              => __( 'View Popup Alert', 'wordpress-custom-popup-alert' ),
			'all_items'              => __( 'All Popup Alerts', 'wordpress-custom-popup-alert' ),
			'search_items'           => __( 'Search Popup Alerts', 'wordpress-custom-popup-alert' ),
			'not_found'              => __( 'No popup alerts found.', 'wordpress-custom-popup-alert' ),
			'not_found_in_trash'     => __( 'No popup alerts found in Trash.', 'wordpress-custom-popup-alert' ),
			'item_published'         => __( 'Popup alert activated.', 'wordpress-custom-popup-alert' ),
			'item_updated'           => __( 'Popup alert updated.', 'wordpress-custom-popup-alert' ),
			'item_reverted_to_draft' => __( 'Popup alert deactivated and saved as a draft.', 'wordpress-custom-popup-alert' ),
		);

		$capabilities = array_fill_keys(
			array(
				'edit_post',
				'read_post',
				'delete_post',
				'edit_posts',
				'edit_others_posts',
				'publish_posts',
				'read_private_posts',
				'delete_posts',
				'delete_private_posts',
				'delete_published_posts',
				'delete_others_posts',
				'edit_private_posts',
				'edit_published_posts',
				'create_posts',
			),
			Capabilities::CAPABILITY
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-warning',
				'menu_position'       => 58,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capabilities'        => $capabilities,
				'map_meta_cap'        => false,
			)
		);
	}

	/**
	 * Clarify that the WordPress title is administrative.
	 *
	 * @param string   $text Placeholder.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function title_placeholder( $text, $post ) {
		if ( $post && self::POST_TYPE === $post->post_type ) {
			return __( 'Internal alert name', 'wordpress-custom-popup-alert' );
		}

		return $text;
	}

	/**
	 * Use the traditional WordPress WYSIWYG editor for popup alerts.
	 *
	 * @param bool   $use_block_editor Whether the block editor should be used.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function disable_block_editor( $use_block_editor, $post_type ) {
		return self::POST_TYPE === $post_type ? false : $use_block_editor;
	}
}
