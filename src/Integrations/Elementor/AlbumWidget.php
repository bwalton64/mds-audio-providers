<?php
/**
 * Elementor Bandcamp Album widget.
 *
 * @package MDS\Bandcamp
 * @since   1.0.2
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use MDS\Bandcamp\Blocks\BlockRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays a Bandcamp Album player and tracklist in Elementor.
 *
 * @since 1.0.2
 */
final class AlbumWidget extends Widget_Base {
	/**
	 * Get the widget identifier.
	 *
	 * @since 1.0.2
	 *
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'mds_bandcamp_album';
	}

	/**
	 * Get the widget display title.
	 *
	 * @since 1.0.2
	 *
	 * @return string Translated title.
	 */
	public function get_title(): string {
		return __( 'Bandcamp Album', 'mds-bandcamp-integration' );
	}

	/**
	 * Get the widget icon classes.
	 *
	 * @since 1.0.2
	 *
	 * @return string Icon classes.
	 */
	public function get_icon(): string {
		return 'dashicons dashicons-album';
	}

	/**
	 * Get the widget categories.
	 *
	 * @since 1.0.2
	 *
	 * @return string[] Category slugs.
	 */
	public function get_categories(): array {
		return array( 'general' );
	}

	/**
	 * Register widget content and style controls.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$inherit_bool_options = array(
			''    => __( 'Inherit site default', 'mds-bandcamp-integration' ),
			'yes' => __( 'Show', 'mds-bandcamp-integration' ),
			'no'  => __( 'Hide', 'mds-bandcamp-integration' ),
		);

		$this->start_controls_section(
			'bandcamp_album_content',
			array(
				'label' => __( 'Bandcamp Album', 'mds-bandcamp-integration' ),
			)
		);

		$this->add_control(
			'album_id',
			array(
				'label'       => __( 'Bandcamp Album ID Override', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Leave blank to use the current release or UPC context.', 'mds-bandcamp-integration' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'upc',
			array(
				'label'       => __( 'Release UPC/EAN Override', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Optional UPC/EAN used to resolve a linked release.', 'mds-bandcamp-integration' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'embed_type',
			array(
				'label'   => __( 'Player Type', 'mds-bandcamp-integration' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''         => __( 'Inherit site default', 'mds-bandcamp-integration' ),
					'custom'   => __( 'Custom HTML Player', 'mds-bandcamp-integration' ),
					'bandcamp' => __( 'Bandcamp Embed', 'mds-bandcamp-integration' ),
				),
			)
		);

		$toggles = array(
			'display_album_title'  => __( 'Album Title', 'mds-bandcamp-integration' ),
			'display_album_artist' => __( 'Artist Name', 'mds-bandcamp-integration' ),
			'display_album_art'    => __( 'Album Artwork', 'mds-bandcamp-integration' ),
			'display_album_link'   => __( 'Bandcamp Link Button', 'mds-bandcamp-integration' ),
			'display_audio'        => __( 'Audio Player', 'mds-bandcamp-integration' ),
			'display_playlist'     => __( 'Tracklist', 'mds-bandcamp-integration' ),
			'display_merch'        => __( 'Merchandise', 'mds-bandcamp-integration' ),
		);

		foreach ( $toggles as $key => $label ) {
			$this->add_control(
				$key,
				array(
					'label'   => $label,
					'type'    => Controls_Manager::SELECT,
					'default' => '',
					'options' => $inherit_bool_options,
				)
			);
		}

		$this->end_controls_section();

		$this->start_controls_section(
			'bandcamp_album_style',
			array(
				'label' => __( 'Colors', 'mds-bandcamp-integration' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$colors = array(
			'backgroundColor'     => __( 'Background Color', 'mds-bandcamp-integration' ),
			'textColor'           => __( 'Text Color', 'mds-bandcamp-integration' ),
			'trackColor'          => __( 'Tracklist Color', 'mds-bandcamp-integration' ),
			'buttonColor'         => __( 'Button Color', 'mds-bandcamp-integration' ),
			'progressBarColor'    => __( 'Progress Bar Color', 'mds-bandcamp-integration' ),
			'playerControlsColor' => __( 'Player Controls Color', 'mds-bandcamp-integration' ),
		);

		foreach ( $colors as $key => $label ) {
			$this->add_control(
				$key,
				array(
					'label' => $label,
					'type'  => Controls_Manager::COLOR,
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * Render the widget output on the frontend and editor.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$attributes = array();

		if ( ! empty( $settings['album_id'] ) ) {
			$attributes['albumID'] = sanitize_text_field( (string) $settings['album_id'] );
		}
		if ( ! empty( $settings['upc'] ) ) {
			$attributes['upc'] = sanitize_text_field( (string) $settings['upc'] );
		}
		if ( ! empty( $settings['embed_type'] ) ) {
			$attributes['embedType'] = sanitize_text_field( (string) $settings['embed_type'] );
		}

		foreach ( array( 'display_album_title', 'display_album_artist', 'display_album_art', 'display_album_link', 'display_audio', 'display_playlist', 'display_merch' ) as $key ) {
			if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
				$attributes[ $key ] = 'yes' === $settings[ $key ];
			}
		}

		foreach ( array( 'backgroundColor', 'textColor', 'trackColor', 'buttonColor', 'progressBarColor', 'playerControlsColor' ) as $color_key ) {
			if ( ! empty( $settings[ $color_key ] ) ) {
				$attributes[ $color_key ] = sanitize_text_field( (string) $settings[ $color_key ] );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BlockRenderer::render_album() escapes all dynamic output.
		echo BlockRenderer::render_album( $attributes, null, false );
	}
}
