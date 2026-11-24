<?php
/**
 * Elementor Bandcamp Discography widget.
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
 * Displays a Bandcamp artist discography grid in Elementor.
 *
 * @since 1.0.2
 */
final class DiscographyWidget extends Widget_Base {
	/**
	 * Get the widget identifier.
	 *
	 * @since 1.0.2
	 *
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'mds_bandcamp_discography';
	}

	/**
	 * Get the widget display title.
	 *
	 * @since 1.0.2
	 *
	 * @return string Translated title.
	 */
	public function get_title(): string {
		return __( 'Bandcamp Discography', 'mds-bandcamp-integration' );
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
			'bandcamp_discography_content',
			array(
				'label' => __( 'Bandcamp Discography', 'mds-bandcamp-integration' ),
			)
		);

		$this->add_control(
			'artist_url',
			array(
				'label'       => __( 'Bandcamp Artist URL or Subdomain', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Leave blank to use the current Artist or Release context.', 'mds-bandcamp-integration' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'artist_id',
			array(
				'label'       => __( 'Artist Post ID Override', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'description' => __( 'Optional Artist post ID with a verified Bandcamp URL.', 'mds-bandcamp-integration' ),
			)
		);

		$toggles = array(
			'display_album_title'  => __( 'Album Title', 'mds-bandcamp-integration' ),
			'display_album_artist' => __( 'Artist Name', 'mds-bandcamp-integration' ),
			'display_album_art'    => __( 'Album Artwork', 'mds-bandcamp-integration' ),
			'display_album_link'   => __( 'Bandcamp Link Button', 'mds-bandcamp-integration' ),
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
			'bandcamp_discography_layout',
			array(
				'label' => __( 'Layout & Colors', 'mds-bandcamp-integration' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$col_options = array(
			''  => __( 'Inherit site default', 'mds-bandcamp-integration' ),
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
		);

		$this->add_control(
			'layout_columns',
			array(
				'label'   => __( 'Desktop Columns', 'mds-bandcamp-integration' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => $col_options,
			)
		);

		$this->add_control(
			'layout_columns_mobile',
			array(
				'label'   => __( 'Mobile Columns', 'mds-bandcamp-integration' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => $col_options,
			)
		);

		$this->add_control(
			'layout_grid_gap',
			array(
				'label'       => __( 'Grid Gap', 'mds-bandcamp-integration' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '2rem',
			)
		);

		$this->add_control(
			'release_titles_align',
			array(
				'label'   => __( 'Title Alignment', 'mds-bandcamp-integration' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''        => __( 'Inherit site default', 'mds-bandcamp-integration' ),
					'left'    => __( 'Left', 'mds-bandcamp-integration' ),
					'center'  => __( 'Center', 'mds-bandcamp-integration' ),
					'right'   => __( 'Right', 'mds-bandcamp-integration' ),
					'justify' => __( 'Justify', 'mds-bandcamp-integration' ),
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label' => __( 'Text Color', 'mds-bandcamp-integration' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label' => __( 'Button Color', 'mds-bandcamp-integration' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label' => __( 'Button Text Color', 'mds-bandcamp-integration' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

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

		if ( ! empty( $settings['artist_url'] ) ) {
			$attributes['artist_url'] = sanitize_text_field( (string) $settings['artist_url'] );
		}
		if ( ! empty( $settings['artist_id'] ) ) {
			$attributes['artistId'] = absint( $settings['artist_id'] );
		}

		foreach ( array( 'display_album_title', 'display_album_artist', 'display_album_art', 'display_album_link' ) as $key ) {
			if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
				$attributes[ $key ] = 'yes' === $settings[ $key ];
			}
		}

		if ( ! empty( $settings['layout_columns'] ) ) {
			$attributes['layout_columns'] = max( 1, min( 6, absint( $settings['layout_columns'] ) ) );
		}
		if ( ! empty( $settings['layout_columns_mobile'] ) ) {
			$attributes['layout_columns_mobile'] = max( 1, min( 6, absint( $settings['layout_columns_mobile'] ) ) );
		}

		foreach ( array( 'layout_grid_gap', 'release_titles_align', 'text_color', 'button_color', 'button_text_color' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$attributes[ $key ] = sanitize_text_field( (string) $settings[ $key ] );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BlockRenderer::render_discography() escapes all dynamic output.
		echo BlockRenderer::render_discography( $attributes, null, false );
	}
}
