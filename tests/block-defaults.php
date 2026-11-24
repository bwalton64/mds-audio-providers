<?php
/**
 * Standalone checks for centralized Bandcamp block defaults and legacy SVG removal.
 *
 * Run with: php tests/block-defaults.php
 */

define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../vendor/autoload.php';

$test_options = array();

function get_option( $name, $default = false ) {
	global $test_options;
	return array_key_exists( $name, $test_options ) ? $test_options[ $name ] : $default;
}

function sanitize_hex_color( $color ) {
	if ( is_string( $color ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $color ) ) {
		return $color;
	}
	return '';
}

function rest_sanitize_boolean( $value ) {
	return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

// 1. Sanitize valid and invalid defaults.
$sanitized = MDS\Bandcamp\Blocks\BlockDefaults::sanitize(
	array(
		'bandcamp-album'       => array(
			'embedType'         => 'bandcamp',
			'backgroundColor'   => '#112233',
			'textColor'         => 'not-a-color',
			'display_merch'     => false,
			'unknown_attribute' => 'ignored',
		),
		'bandcamp-discography' => array(
			'layout_columns'        => 99,
			'layout_columns_mobile' => 0,
			'layout_grid_gap'       => '1.5rem',
			'release_titles_align'  => 'center',
		),
		'bandcamp-miniplayer'  => array(
			'artOpacity'       => 1.8,
			'aspectRatioWidth' => 16,
			'trackNumber'      => '0',
		),
		'bandcamp-embed'       => array(
			'url' => 'ignored',
		),
	)
);

if (
	'bandcamp' !== ( $sanitized['bandcamp-album']['embedType'] ?? '' )
	|| '#112233' !== ( $sanitized['bandcamp-album']['backgroundColor'] ?? '' )
	|| isset( $sanitized['bandcamp-album']['textColor'] )
	|| false !== ( $sanitized['bandcamp-album']['display_merch'] ?? true )
	|| isset( $sanitized['bandcamp-album']['unknown_attribute'] )
	|| 6 !== ( $sanitized['bandcamp-discography']['layout_columns'] ?? 0 )
	|| 1 !== ( $sanitized['bandcamp-discography']['layout_columns_mobile'] ?? 0 )
	|| '1.5rem' !== ( $sanitized['bandcamp-discography']['layout_grid_gap'] ?? '' )
	|| 1.0 !== ( $sanitized['bandcamp-miniplayer']['artOpacity'] ?? 0.0 )
	|| '16' !== ( $sanitized['bandcamp-miniplayer']['aspectRatioWidth'] ?? '' )
	|| '0' !== ( $sanitized['bandcamp-miniplayer']['trackNumber'] ?? '' )
	|| isset( $sanitized['bandcamp-embed'] )
) {
	throw new RuntimeException( 'BlockDefaults::sanitize() returned unexpected values.' );
}

// 2. Resolve merges schema <- site defaults <- explicit attributes.
$test_options[ MDS\Bandcamp\Blocks\BlockDefaults::OPTION_KEY ] = $sanitized;

$resolved = MDS\Bandcamp\Blocks\BlockDefaults::resolve(
	'bandcamp-album',
	array(
		'buttonColor' => '#abcdef',
	)
);

if (
	'#112233' !== $resolved['backgroundColor']
	|| '#abcdef' !== $resolved['buttonColor']
	|| '#ffffff' !== $resolved['textColor']
	|| false !== $resolved['display_merch']
) {
	throw new RuntimeException( 'BlockDefaults::resolve() did not layer defaults properly.' );
}

// 3. apply_to_block resolves attributes for Bandcamp blocks and ignores unrelated blocks.
$block = MDS\Bandcamp\Blocks\BlockDefaults::apply_to_block(
	array(
		'blockName' => 'mds-bandcamp/bandcamp-album',
		'attrs'     => array( 'albumID' => '42' ),
	)
);
if ( '42' !== $block['attrs']['albumID'] || '#112233' !== $block['attrs']['backgroundColor'] ) {
	throw new RuntimeException( 'BlockDefaults::apply_to_block() failed for bandcamp-album.' );
}

// 4. Verify deprecated block folders are removed and no legacy Bandcamp SVG path remains.
foreach ( array( 'bandcamp-embed', 'bandcamp-form', 'bandcamp-merch' ) as $removed ) {
	if ( is_dir( __DIR__ . '/../blocks/' . $removed ) ) {
		throw new RuntimeException( 'Deprecated block directory still exists: ' . $removed );
	}
}

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/..' ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}
	$path = str_replace( '\\', '/', $file->getPathname() );
	if ( false !== strpos( $path, '/vendor/' ) || false !== strpos( $path, '/.git/' ) || false !== strpos( $path, '/tests/' ) ) {
		continue;
	}
	$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'php', 'js', 'css', 'json' ), true ) ) {
		continue;
	}
	$contents = (string) file_get_contents( $file->getPathname() );
	if ( false !== strpos( $contents, 'M10.1,8.9l-3.3,6h7l3.3-6H10.1z' ) ) {
		throw new RuntimeException( 'Legacy Bandcamp SVG icon still present in: ' . $path );
	}
}

echo "Bandcamp block defaults and legacy SVG removal tests passed.\n";
