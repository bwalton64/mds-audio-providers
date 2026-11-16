<?php

/**
 * Admin settings for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers and handles the Bandcamp release settings.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- Global scope is required for this plugin.
final class MdsBandcampAdmin
{
    /**
     * Register the Bandcamp meta box for release posts.
     *
     * @param object $post Post being processed.
     * @return void
     */
    public static function registerMetaBox($post): void
    {
        if (!MdsBandcampDependencies::isMdsActive()) {
            return;
        }

        if (!$post || 'release' !== $post->post_type) {
            return;
        }

        add_meta_box(
            'mds_bandcamp_settings',
            'Bandcamp Integration',
            'mds_bandcamp_meta_box_render',
            'release',
            'normal',
            'default'
        );
    }

    /**
     * Render the Bandcamp meta box.
     *
     * @param object $post Post being processed.
     * @return void
     */
    public static function renderMetaBox($post): void
    {
        $shortcode = self::getShortcode($post->ID);
        $is_bandcamp_active = MdsBandcampDependencies::isBandcampActive();

        $description = $is_bandcamp_active
            ? __('Paste your Bandcamp shortcode or data here.', 'mds-bandcamp-integration')
            : __(
                'Only available if the Blocks for Bandcamp plugin is installed and active.',
                'mds-bandcamp-integration'
            );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes dynamic values.
        echo mds_bandcamp_get_template(
            'admin-settings.html',
            array(
                'nonce_field'       => wp_nonce_field('mds_bandcamp_save', 'mds_bandcamp_nonce', false, false),
                'label'             => esc_html__('Bandcamp Data / Shortcode', 'mds-bandcamp-integration'),
                'shortcode'         => esc_attr($shortcode),
                'disabled'          => esc_attr($is_bandcamp_active ? '' : 'disabled'),
                'description_class' => esc_attr($is_bandcamp_active ? '' : 'mds-bandcamp-dependency-notice'),
                'description'       => esc_html($description),
            )
        );
    }

    /**
     * Save the Bandcamp shortcode when the request is valid.
     *
     * @param int   $post_id   Post ID.
     * @param array $post_data Submitted post data.
     * @return void
     */
    public static function saveMeta($post_id, $post_data): void
    {
        if (!MdsBandcampDependencies::isMdsActive() || !self::hasValidNonce($post_data)) {
            return;
        }

        self::saveShortcode($post_id, $post_data);
    }

    /**
     * Read the shortcode from post meta, using an empty string when unset.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    private static function getShortcode($post_id): string
    {
        $shortcode = get_post_meta($post_id, 'bandcamp_shortcode', true);

        if (!is_scalar($shortcode) || !$shortcode) {
            return '';
        }

        return (string) $shortcode;
    }

    /**
     * Check the save nonce in submitted post data.
     *
     * @param array $post_data Submitted post data.
     * @return bool
     */
    private static function hasValidNonce($post_data): bool
    {
        return isset($post_data['mds_bandcamp_nonce'])
            && wp_verify_nonce($post_data['mds_bandcamp_nonce'], 'mds_bandcamp_save');
    }

    /**
     * Sanitize and save the shortcode when it was submitted.
     *
     * @param int   $post_id   Post ID.
     * @param array $post_data Submitted post data.
     * @return void
     */
    private static function saveShortcode($post_id, $post_data): void
    {
        if (! isset($post_data['bandcamp_shortcode'])) {
            return;
        }

        update_post_meta(
            $post_id,
            'bandcamp_shortcode',
            sanitize_text_field(wp_unslash($post_data['bandcamp_shortcode']))
        );
    }
}
