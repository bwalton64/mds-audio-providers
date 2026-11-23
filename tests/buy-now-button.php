<?php
/**
 * Standalone checks for the Bandcamp Buy Now button and Digital Download media format seeding.
 *
 * Run with: php tests/buy-now-button.php
 */

define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../vendor/autoload.php';

$is_admin_state       = true;
$taxonomy_exists_flag = true;
$inserted_terms       = array();
$term_meta_store      = array();
$existing_terms       = array();
$active_plugins       = array( 'mds-release-manager/mds-release-manager.php' );

function is_plugin_active( $plugin ) {
	global $active_plugins;
	return in_array( $plugin, $active_plugins, true );
}

function is_admin() {
	global $is_admin_state;
	return $is_admin_state;
}

function taxonomy_exists( $taxonomy ) {
	global $taxonomy_exists_flag;
	return $taxonomy_exists_flag && 'release_media_format' === $taxonomy;
}

function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	unset( $domain );
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function is_wp_error( $thing ) {
	return false;
}

function get_term_by( $field, $value, $taxonomy ) {
	global $existing_terms;
	$key = $taxonomy . ':' . $field . ':' . $value;
	return $existing_terms[ $key ] ?? false;
}

function wp_insert_term( $term, $taxonomy, $args = array() ) {
	global $inserted_terms, $existing_terms;
	$term_id          = count( $inserted_terms ) + 101;
	$inserted_terms[] = array(
		'term_id'  => $term_id,
		'term'     => $term,
		'taxonomy' => $taxonomy,
		'args'     => $args,
	);
	$slug             = $args['slug'] ?? strtolower( str_replace( ' ', '-', $term ) );
	$existing_terms[ $taxonomy . ':slug:' . $slug ] = (object) array(
		'term_id' => $term_id,
		'slug'    => $slug,
		'name'    => $term,
	);
	return array(
		'term_id'          => $term_id,
		'term_taxonomy_id' => $term_id,
	);
}

function get_term_meta( $term_id, $key = '', $single = false ) {
	global $term_meta_store;
	unset( $single );
	return $term_meta_store[ $term_id ][ $key ] ?? '';
}

function update_term_meta( $term_id, $key, $value ) {
	global $term_meta_store;
	$term_meta_store[ $term_id ][ $key ] = $value;
	return true;
}

// 1. Button renders when MDS Release Manager is active.
ob_start();
MDS\Bandcamp\Admin\Admin::render_buy_now_button();
$button_html = ob_get_clean();

$expected_html = '<button type="button" class="button button-secondary" id="mds_use_bandcamp_url_btn" disabled>Use Bandcamp URL</button>';
if ( $button_html !== $expected_html ) {
	throw new RuntimeException( 'Unexpected Buy Now button markup: ' . $button_html );
}

// 2. Button is suppressed when MDS Release Manager is inactive.
$active_plugins = array();
ob_start();
MDS\Bandcamp\Admin\Admin::render_buy_now_button();
$inactive_html = ob_get_clean();
if ( '' !== $inactive_html ) {
	throw new RuntimeException( 'Buy Now button should not render when MDS Release Manager is inactive.' );
}
$active_plugins = array( 'mds-release-manager/mds-release-manager.php' );

// 3. ensure_digital_download_media_format inserts term and sets icon when missing.
MDS\Bandcamp\Admin\Admin::ensure_digital_download_media_format();
if ( 1 !== count( $inserted_terms ) ) {
	throw new RuntimeException( 'Expected digital-download term to be inserted once.' );
}
$created_id = $inserted_terms[0]['term_id'];
if ( 'digital-download' !== ( $inserted_terms[0]['args']['slug'] ?? '' ) ) {
	throw new RuntimeException( 'Expected slug digital-download.' );
}
if ( 'fas fa-download' !== ( $term_meta_store[ $created_id ]['_mds_media_format_icon'] ?? '' ) ) {
	throw new RuntimeException( 'Expected fas fa-download icon to be set on created term.' );
}

// 4. Second run does not insert a duplicate term.
MDS\Bandcamp\Admin\Admin::ensure_digital_download_media_format();
if ( 1 !== count( $inserted_terms ) ) {
	throw new RuntimeException( 'Expected digital-download term not to be duplicated.' );
}

// 5. Existing term without icon receives the default icon.
unset( $term_meta_store[ $created_id ]['_mds_media_format_icon'] );
MDS\Bandcamp\Admin\Admin::ensure_digital_download_media_format();
if ( 'fas fa-download' !== ( $term_meta_store[ $created_id ]['_mds_media_format_icon'] ?? '' ) ) {
	throw new RuntimeException( 'Expected missing icon meta to be backfilled on existing term.' );
}

echo "Bandcamp Buy Now button and media format tests passed.\n";
