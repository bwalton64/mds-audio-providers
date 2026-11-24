<?php
/**
 * Block registration for the MDS Bandcamp Integration plugin.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Bandcamp blocks and their related WordPress hooks.
 *
 * @since 1.0.0
 */
final class Blocks {
	/**
	 * Plugin settings and link metadata.
	 *
	 * @since 1.0.0
	 * @var array{key: string}
	 */
	private static array $plugin_data = array(
		'key' => 'mds_bandcamp',
	);

	/**
	 * Register block-related hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'required_notice' ) );
		add_action( 'init', array( self::class, 'load_blocks' ) );
		add_filter( 'block_categories_all', array( self::class, 'custom_block_category' ) );
		add_filter(
			'plugin_action_links_' . MDS_BC_PLUGIN_BASENAME,
			array( self::class, 'plugin_links' )
		);
	}

	/**
	 * Register the integrated Bandcamp blocks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function load_blocks(): void {
		if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
			return;
		}

		$blocks = array(
			'bandcamp-album',
			'bandcamp-discography',
			'bandcamp-miniplayer',
		);

		foreach ( $blocks as $block ) {
			register_block_type_from_metadata( MDS_BC_PLUGIN_PATH . '/blocks/' . $block );
		}
	}

	/**
	 * Add the shared MDS Release Manager category to the block editor.
	 *
	 * Registered defensively: the category is only appended when no other MDS
	 * plugin (e.g. mds-release-manager) has already added the
	 * `mds-release-manager` slug, so the section appears exactly once
	 * regardless of which MDS plugins are active.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $categories Existing block categories.
	 * @return array<int, array<string, mixed>> Categories with the MDS Release Manager category added.
	 */
	public static function custom_block_category( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && 'mds-release-manager' === $category['slug'] ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => 'mds-release-manager',
			'title' => __( 'MDS Release Manager', 'mds-bandcamp-integration' ),
			'icon'  => 'album',
		);

		return $categories;
	}

	/**
	 * Add a settings link to the plugin action links.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string> Links with the settings link prepended.
	 */
	public static function plugin_links( array $links ): array {
		$settings_url  = add_query_arg( 'page', self::$plugin_data['key'], admin_url( 'options-general.php' ) );
		$settings_link = '<a aria-label="' . esc_attr__( 'View Settings', 'mds-bandcamp-integration' ) . '" href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'mds-bandcamp-integration' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Deactivate the plugin and register a notice if block support is unavailable.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function required_notice(): void {
		if ( function_exists( 'register_block_type_from_metadata' ) ) {
			return;
		}

		deactivate_plugins( MDS_BC_PLUGIN_BASENAME );
		add_action( 'admin_notices', array( self::class, 'show_required_notice' ) );
	}

	/**
	 * Display the notice that block editor support is required.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function show_required_notice(): void {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'MDS Bandcamp Integration requires WordPress block editor support.', 'mds-bandcamp-integration' ) .
			'</p></div>';
	}
}
