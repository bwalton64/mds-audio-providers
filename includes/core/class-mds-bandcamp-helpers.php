<?php
/**
 * MDS_Bandcamp_Helpers class.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides public utility methods used by Bandcamp integration code.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Helpers
{
    /**
     * Check whether MDS Release Manager is active.
     *
     * @since 1.0.0
     * @return bool Whether MDS Release Manager is active.
     */
    public static function is_mds_active(): bool
    {
        return MDS_Bandcamp_Dependencies::is_mds_active();
    }

    /**
     * Check whether integrated Bandcamp block features are available.
     *
     * @since 1.0.0
     * @return bool Whether Bandcamp block features are available.
     */
    public static function is_bandcamp_active(): bool
    {
        return MDS_Bandcamp_Dependencies::is_bandcamp_active();
    }

    /**
     * Get the contrast color for a given background color.
     *
     * @since 1.0.0
     * @param string $bg_color Background color in hex format.
     * @return string Contrast color.
     */
    public static function get_contrast_color( string $bg_color ): string
    {
        return MDS_Bandcamp_Utilities::get_contrast_color( $bg_color );
    }

    /**
     * Get Bandcamp album ID for a release.
     *
     * @since 1.0.0
     *
     * @param int $post_id Post ID of the release.
     * @return int Album ID, or 0 if not set.
     */
    public static function get_album_id( int $post_id ): int
    {
        $data = get_post_meta( $post_id, 'mds_bandcamp_integration', true );

        if ( ! is_array( $data ) || empty( $data['album_id'] ) ) {
            return 0;
        }

        return absint( $data['album_id'] );
    }

    /**
     * Get Bandcamp release URL for a release.
     *
     * @since 1.0.0
     *
     * @param int $post_id Post ID of the release.
     * @return string Release URL, or empty string if not set.
     */
    public static function get_release_url( int $post_id ): string
    {
        $data = get_post_meta( $post_id, 'mds_bandcamp_integration', true );

        if ( ! is_array( $data ) || empty( $data['url'] ) ) {
            return '';
        }

        return (string) $data['url'];
    }
}
