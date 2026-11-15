<?php
/**
 * Plugin Name: MDS Bandcamp Integration
 * Plugin URI:  
 * Description: Extends MDS Release Manager by integrating Blocks for Bandcamp via the Hook Registry.
 * Version:     1.0.1
 * Author:      MDS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check Dependencies
 */
function mds_bandcamp_is_mds_active() {
    $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
    return in_array( 'mds-release-manager/mds-release-manager.php', $active_plugins, true ) || ( is_multisite() && array_key_exists( 'mds-release-manager/mds-release-manager.php', get_site_option( 'active_sitewide_plugins', array() ) ) );
}

function mds_bandcamp_is_bandcamp_active() {
    $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
    return in_array( 'blocks-for-bandcamp/blocks-for-bandcamp.php', $active_plugins, true ) || ( is_multisite() && array_key_exists( 'blocks-for-bandcamp/blocks-for-bandcamp.php', get_site_option( 'active_sitewide_plugins', array() ) ) );
}

/**
 * Register Custom Meta Box
 * Hooks into 'mds_rm_register_custom_meta_boxes'
 */
add_action( 'mds_rm_register_custom_meta_boxes', 'mds_bandcamp_register_meta_box' );
function mds_bandcamp_register_meta_box( $post ) {
    if ( ! mds_bandcamp_is_mds_active() ) return;
    if ( $post && $post->post_type === 'release' ) {
        add_meta_box( 'mds_bandcamp_settings', 'Bandcamp Integration', 'mds_bandcamp_meta_box_render', 'release', 'normal', 'default' );
    }
}

function mds_bandcamp_meta_box_render( $post ) {
    $shortcode = get_post_meta( $post->ID, 'bandcamp_shortcode', true ) ?: '';
    $is_bandcamp_active = mds_bandcamp_is_bandcamp_active();
    $disabled_attr = $is_bandcamp_active ? '' : 'disabled="disabled"';
    
    wp_nonce_field( 'mds_bandcamp_save', 'mds_bandcamp_nonce' );
    ?>
    <table class="form-table">
        <tr>
            <th><label for="bandcamp_shortcode">Bandcamp Data / Shortcode</label></th>
            <td>
                <input type="text" id="bandcamp_shortcode" name="bandcamp_shortcode" value="<?php echo esc_attr( $shortcode ); ?>" class="regular-text" style="width:100%;" <?php echo $disabled_attr; ?> />
                <?php if ( ! $is_bandcamp_active ) : ?>
                    <p class="description" style="color: #d63638;">Only available if the Blocks for Bandcamp plugin is installed and active.</p>
                <?php else : ?>
                    <p class="description">Paste your Bandcamp shortcode or data here.</p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Save Custom Meta
 * Hooks into 'mds_rm_save_custom_meta'
 */
add_action( 'mds_rm_save_custom_meta', 'mds_bandcamp_save_meta', 10, 2 );
function mds_bandcamp_save_meta( $post_id, $post_data ) {
    if ( ! mds_bandcamp_is_mds_active() ) return;
    if ( ! isset( $post_data['mds_bandcamp_nonce'] ) || ! wp_verify_nonce( $post_data['mds_bandcamp_nonce'], 'mds_bandcamp_save' ) ) return;
    
    if ( isset( $post_data['bandcamp_shortcode'] ) ) {
        update_post_meta( $post_id, 'bandcamp_shortcode', sanitize_text_field( wp_unslash( $post_data['bandcamp_shortcode'] ) ) );
    }
}

/**
 * Render Frontend
 * Hooks into 'mds_rm_after_release_meta'
 */
add_action( 'mds_rm_after_release_meta', 'mds_bandcamp_frontend_render' );
function mds_bandcamp_frontend_render( $post_id ) {
    if ( ! mds_bandcamp_is_mds_active() || ! mds_bandcamp_is_bandcamp_active() ) return;
    
    $shortcode = get_post_meta( $post_id, 'bandcamp_shortcode', true );
    if ( ! empty( $shortcode ) ) {
        echo '<div class="mds-bandcamp-player-wrapper" style="margin-top:20px;">';
        echo do_shortcode( $shortcode );
        echo '</div>';
    }
}
