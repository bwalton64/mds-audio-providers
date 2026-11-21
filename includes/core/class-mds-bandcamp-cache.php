<?php
/**
 * Cache management for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages transient cache for Bandcamp API data.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Cache
{

    /**
     * Plugin key for settings and cache.
     *
     * @since 1.0.0
     * @var string
     */
    private static $plugin_key = 'mds_bandcamp';

    /**
     * Register cache management hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_hooks(): void
    {
        add_action( 'admin_notices', array( self::class, 'show_clear_cache_notice' ) );
        add_action( 'admin_post_' . self::$plugin_key . '_clear_cache', array( self::class, 'handle_clear_cache' ) );
    }

    /**
     * Get a JSON array of cached transients for the settings page.
     *
     * @since 1.0.0
     * @param string $search Optional search filter.
     * @return string JSON-encoded transient information.
     */
    public static function get_transients_list( $search = '' ): string
    {
        global $wpdb;

        $prefix         = '_transient_';
        $timeout_prefix = '_transient_timeout_';

        if ( ! empty( $search ) ) {
            $query = $wpdb->prepare(
                "
                SELECT option_name, option_value
                FROM $wpdb->options
                WHERE option_name LIKE %s
                OR option_name LIKE %s
                ORDER BY option_name ASC
            ",
                '%' . $wpdb->esc_like( $prefix . $search ) . '%',
                '%' . $wpdb->esc_like( $timeout_prefix . $search ) . '%'
            );
        } else {
            $query = $wpdb->prepare(
                "
                SELECT option_name, option_value
                FROM $wpdb->options
                WHERE option_name LIKE %s
                OR option_name LIKE %s
                ORDER BY option_name ASC
            ",
                $wpdb->esc_like( $prefix ) . '%',
                $wpdb->esc_like( $timeout_prefix ) . '%'
            );
        }

        $results    = $wpdb->get_results( $query );
        $transients = array();

        foreach ( $results as $row ) {
            if ( str_starts_with( $row->option_name, $timeout_prefix ) ) {
                $key     = str_replace( $timeout_prefix, '', $row->option_name );
                $expires = (int) $row->option_value;

                if ( ! isset( $transients[ $key ] ) ) {
                    $transients[ $key ] = array( 'key' => $key );
                }

                $transients[ $key ]['expires'] = $expires ? wp_date( 'Y-m-d H:i:s', $expires ) : 'never';
            } elseif ( str_starts_with( $row->option_name, $prefix ) ) {
                $key       = str_replace( $prefix, '', $row->option_name );
                $searchkey = str_replace( $prefix . $search . '_', '', $row->option_name );

                if ( ! isset( $transients[ $key ] ) ) {
                    $transients[ $key ] = array( 'key' => $key );
                }

                $keys = explode( '_', $searchkey, 3 );
                $transients[ $key ]['endpoint'] = $keys[1] ?? '';
                $transients[ $key ]['id']       = $keys[2] ?? '';

                $raw_value  = maybe_serialize( $row->option_value );
                $size_bytes = strlen( $raw_value );

                $transients[ $key ]['size_bytes'] = $size_bytes;
                $transients[ $key ]['size_human'] = size_format( $size_bytes );
            }
        }

        return wp_json_encode( array_values( $transients ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    }

    /**
     * Handle "Clear Cache" button submission.
     *
     * @since 1.0.0
     * @return void
     */
    public static function handle_clear_cache(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user' );
        }

        check_admin_referer( 'gf_clear_cache_action', 'gf_clear_cache_nonce' );

        global $wpdb;
        $like_1 = $wpdb->esc_like( '_transient_' . self::$plugin_key ) . '%';
        $like_2 = $wpdb->esc_like( '_transient_timeout_' . self::$plugin_key ) . '%';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                $like_1,
                $like_2
            )
        );

        wp_cache_flush();

        set_transient( 'gf_clear_cache_notice', true, 30 );

        MDS_Bandcamp_Logging::log( 'info', 'Transient cache manually cleared', array( 'function' => 'handle_clear_cache' ) );

        wp_safe_redirect( add_query_arg( 'page', self::$plugin_key, admin_url( 'options-general.php' ) ) );
        exit;
    }

    /**
     * Show notice after cache is cleared.
     *
     * @since 1.0.0
     * @return void
     */
    public static function show_clear_cache_notice(): void
    {
        if ( ! get_transient( 'gf_clear_cache_notice' ) ) {
            return;
        }

        delete_transient( 'gf_clear_cache_notice' );
        wp_admin_notice( 'Cache cleared successfully!', array( 'type' => 'success', 'dismissible' => true ) );
    }
}
