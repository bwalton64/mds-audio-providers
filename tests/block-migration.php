<?php
/**
 * Standalone regression tests for legacy block namespace migration.
 *
 * Run with: php tests/block-migration.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'MDS_BC_PLUGIN_URL', 'https://example.test/plugins/mds-bandcamp-integration' );
define( 'MDS_BC_URL', 'https://example.test/plugins/mds-bandcamp-integration/' );
define( 'MDS_BC_PLUGIN_VERSION', '1.0.0' );

$test_options = array();
$enqueued_styles = array();
$enqueued_scripts = array();
$migration_failure_id = 20;

function get_option( $name, $default = false ) {
	global $test_options;
	return array_key_exists( $name, $test_options ) ? $test_options[ $name ] : $default;
}

function update_option( $name, $value ) {
	global $test_options;
	$test_options[ $name ] = $value;
}

function delete_option( $name ) {
	global $test_options;
	unset( $test_options[ $name ] );
}

function is_admin() {
	return false;
}

function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
	global $enqueued_styles;
	$enqueued_styles[] = array( $handle, $src, $deps, $version );
}

function wp_enqueue_script( $handle, $src = '', $deps = array(), $version = false, $in_footer = false ) {
	global $enqueued_scripts;
	$enqueued_scripts[] = array( $handle, $src, $deps, $version, $in_footer );
}

function current_user_can( $capability ) {
	return false;
}

function wp_slash( $value ) {
	return $value;
}

function is_wp_error( $value ) {
	return $value instanceof Block_Migration_Test_Error;
}

function wp_update_post( $post, $wp_error = false ) {
	global $wpdb, $migration_failure_id;
	if ( (int) $post['ID'] === $migration_failure_id ) {
		return new Block_Migration_Test_Error();
	}

	$wpdb->posts_by_id[ (int) $post['ID'] ]->post_content = $post['post_content'];
	return (int) $post['ID'];
}

final class Block_Migration_Test_Error {
	public function get_error_message() {
		return 'Simulated interrupted migration.';
	}
}

final class MDS_Bandcamp_Logging {
	public static function log( $level, $message, $context = array() ) {
		return true;
	}
}

final class Block_Migration_Test_DB {
	public $posts = 'wp_posts';
	public $posts_by_id = array();
	public $prepared_arguments = array();
	public $query_count = 0;

	public function prepare( $query, ...$arguments ) {
		$this->prepared_arguments = $arguments;
		return $query;
	}

	public function esc_like( $value ) {
		return $value;
	}

	public function get_results( $query ) {
		++$this->query_count;
		$cursor = (int) $this->prepared_arguments[0];
		$posts = array();

		foreach ( $this->posts_by_id as $post ) {
			if (
				(int) $post->ID > $cursor
				&& 'revision' !== $post->post_type
				&& false !== strpos( $post->post_content, '<!-- wp:blocks-for-bandcamp/' )
			) {
				$posts[] = $post;
			}
		}

		usort(
			$posts,
			static function ( $first, $second ) {
				return (int) $first->ID <=> (int) $second->ID;
			}
		);

		return array_slice( $posts, 0, 100 );
	}
}

require __DIR__ . '/../vendor/autoload.php';

$attributes = array(
	'albumID' => '123456',
	'url'     => 'https://artist.bandcamp.com/album/example',
);
$legacy_block = array(
	'blockName'    => 'blocks-for-bandcamp/bandcamp-album',
	'attrs'        => $attributes,
	'innerBlocks'  => array(
		array(
			'blockName'   => 'blocks-for-bandcamp/bandcamp-merch',
			'attrs'       => array( 'albumID' => '654321' ),
			'innerBlocks' => array(),
		),
	),
);
$prepared_block = MDS\Bandcamp\Migrations\BlockMigration::prepare_legacy_block( $legacy_block );
if (
	'mds-bandcamp/bandcamp-album' !== $prepared_block['blockName']
	|| $attributes !== $prepared_block['attrs']
	|| 'mds-bandcamp/bandcamp-merch' !== $prepared_block['innerBlocks'][0]['blockName']
	|| array( 'albumID' => '654321' ) !== $prepared_block['innerBlocks'][0]['attrs']
) {
	throw new RuntimeException( 'Legacy render compatibility changed block attributes.' );
}

foreach ( $enqueued_styles as $style ) {
	if ( 'mds-bc-blocks' !== $style[0] || 'https://example.test/plugins/mds-bandcamp-integration/css/blocks.css' !== $style[1] ) {
		throw new RuntimeException( 'Legacy block CSS was registered with an unexpected handle or path.' );
	}
}
foreach ( $enqueued_scripts as $script ) {
	if ( 'mds-bc-blocks' !== $script[0] || 'https://example.test/plugins/mds-bandcamp-integration/js/blocks.js' !== $script[1] ) {
		throw new RuntimeException( 'Legacy block script was registered with an unexpected handle or path.' );
	}
}
if ( empty( $enqueued_styles ) || empty( $enqueued_scripts ) ) {
	throw new RuntimeException( 'Legacy blocks did not enqueue their shared frontend assets.' );
}

$original_album_content = '<!-- wp:blocks-for-bandcamp/bandcamp-album {"albumID":"123456","url":"https://artist.bandcamp.com/album/example"} --><div></div><!-- /wp:blocks-for-bandcamp/bandcamp-album -->';
$original_merch_content = '<!-- wp:blocks-for-bandcamp/bandcamp-merch {"albumID":"654321","single_or_all":"all"} --><div></div><!-- /wp:blocks-for-bandcamp/bandcamp-merch -->';
$wpdb = new Block_Migration_Test_DB();
$wpdb->posts_by_id = array(
	10 => (object) array( 'ID' => 10, 'post_type' => 'post', 'post_content' => $original_album_content ),
	20 => (object) array( 'ID' => 20, 'post_type' => 'post', 'post_content' => $original_merch_content ),
	30 => (object) array( 'ID' => 30, 'post_type' => 'revision', 'post_content' => $original_album_content ),
);

MDS\Bandcamp\Migrations\BlockMigration::run();
if (
	'<!-- wp:mds-bandcamp/bandcamp-album {"albumID":"123456","url":"https://artist.bandcamp.com/album/example"} --><div></div><!-- /wp:mds-bandcamp/bandcamp-album -->' !== $wpdb->posts_by_id[10]->post_content
	|| $original_merch_content !== $wpdb->posts_by_id[20]->post_content
	|| isset( $test_options['mds_bandcamp_block_migration_cursor'] )
	|| isset( $test_options['mds_bandcamp_block_namespace_migrated'] )
) {
	throw new RuntimeException( 'Interrupted migration changed data or checkpoint state unexpectedly.' );
}

$migration_failure_id = 0;
MDS\Bandcamp\Migrations\BlockMigration::run();
if (
	'<!-- wp:mds-bandcamp/bandcamp-merch {"albumID":"654321","single_or_all":"all"} --><div></div><!-- /wp:mds-bandcamp/bandcamp-merch -->' !== $wpdb->posts_by_id[20]->post_content
	|| 20 !== (int) $test_options['mds_bandcamp_block_migration_cursor']
	|| isset( $test_options['mds_bandcamp_block_namespace_migrated'] )
	|| $original_album_content !== $wpdb->posts_by_id[30]->post_content
) {
	throw new RuntimeException( 'Migration resume did not preserve attributes and checkpoint progress.' );
}

MDS\Bandcamp\Migrations\BlockMigration::run();
if (
	1 !== (int) $test_options['mds_bandcamp_block_namespace_migrated']
	|| isset( $test_options['mds_bandcamp_block_migration_cursor'] )
	|| $original_album_content !== $wpdb->posts_by_id[30]->post_content
) {
	throw new RuntimeException( 'Migration completion did not set its flag and clear the cursor safely.' );
}

$query_count = $wpdb->query_count;
MDS\Bandcamp\Migrations\BlockMigration::run();
if ( $query_count !== $wpdb->query_count ) {
	throw new RuntimeException( 'Completed block migration was not idempotent.' );
}

echo "Legacy block namespace migration tests passed.\n";
