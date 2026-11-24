<?php
/**
 * Elementor widget registration for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.2
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Bandcamp Elementor widgets when Elementor is active.
 *
 * @since 1.0.2
 */
final class Elementor {
	/**
	 * Register Elementor integration hooks.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'elementor/widgets/register', array( self::class, 'register_widgets' ) );
	}

	/**
	 * Register the Bandcamp Album, Discography, and Miniplayer widgets.
	 *
	 * @since 1.0.2
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function register_widgets( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		$widgets_manager->register( new AlbumWidget() );
		$widgets_manager->register( new DiscographyWidget() );
		$widgets_manager->register( new MiniplayerWidget() );
	}
}
