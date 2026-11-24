<?php
/**
 * Elementor Bandcamp Miniplayer widget.
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
 * Displays a compact Bandcamp Miniplayer in Elementor.
 *
 * @since 1.0.2
 */
final class MiniplayerWidget extends Widget_Base {
	/**
	 * Get the widget identifier.
	 *
	 * @since 1.0.2
	 *
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'mds_bandcamp_miniplayer';
	}

	/**
	 * Get the widget display title.
	 *
	 * @since 1.0.2
	 *
	 * @return string Translated title.
	 */
	public function get_title(): string {
		return __( 'Bandcamp Miniplayer', 'mds-bandcamp-integration' );
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
			'bandcamp_miniplayer_content',
			array(
				'label' => __( 'Bandcamp Miniplayer', 'mds-bandcamp-integration' ),
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
			'trackNumber',
			array(
				'label'       => __( 'Track Number', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Use 0 for the featured track, or leave blank to inherit site default.', 'mds-bandcamp-integration' ),
			)
		);

		$toggles = array(
			'display_track_title'  => __( 'Track Title', 'mds-bandcamp-integration' ),
			'display_album_artist' => __( 'Artist Name', 'mds-bandcamp-integration' ),
			'display_album_title'  => __( 'Album Title', 'mds-bandcamp-integration' ),
			'display_album_link'   => __( 'Bandcamp Link Button', 'mds-bandcamp-integration' ),
			'display_progress_bar' => __( 'Progress Bar', 'mds-bandcamp-integration' ),
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
			'bandcamp_miniplayer_style',
			array(
				'label' => __( 'Artwork & Colors', 'mds-bandcamp-integration' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'aspectRatioWidth',
			array(
				'label'       => __( 'Aspect Ratio Width', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 99,
				'placeholder' => '1',
			)
		);

		$this->add_control(
			'aspectRatioHeight',
			array(
				'label'       => __( 'Aspect Ratio Height', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 99,
				'placeholder' => '1',
			)
		);

		$this->add_control(
			'artOpacity',
			array(
				'label'       => __( 'Artwork Opacity', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 1,
				'step'        => 0.05,
				'placeholder' => '0.5',
			)
		);

		$this->add_control(
			'artOpacityHover',
			array(
				'label'       => __( 'Artwork Hover Opacity', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 1,
				'step'        => 0.05,
				'placeholder' => '0.2',
			)
		);

		$colors = array(
			'backgroundColor'  => __( 'Background Color', 'mds-bandcamp-integration' ),
			'textColor'        => __( 'Text Color', 'mds-bandcamp-integration' ),
			'buttonColor'      => __( 'Play Button Color', 'mds-bandcamp-integration' ),
			'progressBarColor' => __( 'Progress Bar Color', 'mds-bandcamp-integration' ),
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
		if ( isset( $settings['trackNumber'] ) && '' !== (string) $settings['trackNumber'] ) {
			$attributes['trackNumber'] = (string) absint( $settings['trackNumber'] );
		}

		foreach ( array( 'display_track_title', 'display_album_artist', 'display_album_title', 'display_album_link', 'display_progress_bar' ) as $key ) {
			if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
				$attributes[ $key ] = 'yes' === $settings[ $key ];
			}
		}

		if ( isset( $settings['aspectRatioWidth'] ) && '' !== (string) $settings['aspectRatioWidth'] ) {
			$attributes['aspectRatioWidth'] = (string) max( 1, absint( $settings['aspectRatioWidth'] ) );
		}
		if ( isset( $settings['aspectRatioHeight'] ) && '' !== (string) $settings['aspectRatioHeight'] ) {
			$attributes['aspectRatioHeight'] = (string) max( 1, absint( $settings['aspectRatioHeight'] ) );
		}
		if ( isset( $settings['artOpacity'] ) && '' !== (string) $settings['artOpacity'] && is_numeric( $settings['artOpacity'] ) ) {
			$attributes['artOpacity'] = (float) $settings['artOpacity'];
		}
		if ( isset( $settings['artOpacityHover'] ) && '' !== (string) $settings['artOpacityHover'] && is_numeric( $settings['artOpacityHover'] ) ) {
			$attributes['artOpacityHover'] = (float) $settings['artOpacityHover'];
		}

		foreach ( array( 'backgroundColor', 'textColor', 'buttonColor', 'progressBarColor' ) as $color_key ) {
			if ( ! empty( $settings[ $color_key ] ) ) {
				$attributes[ $color_key ] = sanitize_text_field( (string) $settings[ $color_key ] );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BlockRenderer::render_miniplayer() escapes all dynamic output.
		echo BlockRenderer::render_miniplayer( $attributes, null, false );
	}
}
