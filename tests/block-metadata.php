<?php
/**
 * Standalone validation for the plugin's six block metadata definitions.
 *
 * Run with: php tests/block-metadata.php
 */

$blocks = array(
	'bandcamp-album',
	'bandcamp-discography',
	'bandcamp-miniplayer',
);

foreach ( $blocks as $block ) {
	$path = __DIR__ . '/../blocks/' . $block . '/block.json';
	if ( ! is_file( $path ) ) {
		throw new RuntimeException( 'Block metadata file is missing: ' . $path );
	}

	$json = json_decode( (string) file_get_contents( $path ), true );
	$block_directory = dirname( $path );

	if ( JSON_ERROR_NONE !== json_last_error() ) {
		throw new RuntimeException( 'Invalid block metadata JSON: ' . $path );
	}

	if (
		! is_array( $json )
		|| 'mds-bandcamp/' . $block !== ( $json['name'] ?? '' )
		|| 3 !== ( $json['apiVersion'] ?? null )
		|| 'mds-bandcamp-integration' !== ( $json['textdomain'] ?? '' )
		|| 'file:./render.php' !== ( $json['render'] ?? '' )
		|| ! is_array( $json['attributes'] ?? null )
	) {
		throw new RuntimeException( 'Block metadata contract changed: ' . $block );
	}

	if ( ! is_file( $block_directory . '/render.php' ) ) {
		throw new RuntimeException( 'Block render entrypoint is missing: ' . $block );
	}

	foreach ( array( 'editorScript', 'editorStyle', 'script', 'style', 'viewScript', 'viewStyle' ) as $asset_key ) {
		if ( empty( $json[ $asset_key ] ) || 0 !== strpos( $json[ $asset_key ], 'file:' ) ) {
			continue;
		}

		$asset_path = $block_directory . DIRECTORY_SEPARATOR . substr( $json[ $asset_key ], 5 );
		if ( ! is_file( $asset_path ) ) {
			throw new RuntimeException( 'Block asset is missing: ' . $block . ' (' . $asset_key . ') ' . $asset_path );
		}

		if ( 'editorScript' === $asset_key && 'index.js' === basename( $asset_path ) && ! is_file( $block_directory . '/index.asset.php' ) ) {
			throw new RuntimeException( 'Editor script dependency manifest is missing: ' . $block );
		}
	}

	$editor_script = (string) file_get_contents( $block_directory . '/index.js' );
	if ( ! preg_match( '/registerBlockType\s*\(\s*([\'"])(.*?)\1/', $editor_script, $matches ) || $json['name'] !== $matches[2] ) {
		throw new RuntimeException( 'Editor script block name does not match metadata: ' . $block );
	}

	$known_attributes = array_merge( array_keys( $json['attributes'] ), array( 'anchor', 'align', 'className', 'style' ) );
	preg_match_all( '/\battributes\.([A-Za-z_$][A-Za-z0-9_$]*)/', $editor_script, $attribute_references );
	preg_match_all( '/setAttributes\s*\(\s*\{([^}]*)\}/s', $editor_script, $attribute_updates );
	$updated_attributes = array();
	foreach ( $attribute_updates[1] as $attribute_update ) {
		preg_match_all( '/(?:^|,)\s*([A-Za-z_$][A-Za-z0-9_$]*)\s*:/', $attribute_update, $updated_attribute_names );
		$updated_attributes = array_merge( $updated_attributes, $updated_attribute_names[1] );
	}

	$unknown_attributes = array_diff(
		array_unique( array_merge( $attribute_references[1], $updated_attributes ) ),
		$known_attributes
	);
	if ( ! empty( $unknown_attributes ) ) {
		throw new RuntimeException( 'Editor script uses attributes absent from block metadata: ' . $block . ' (' . implode( ', ', $unknown_attributes ) . ')' );
	}
}

echo "Block metadata validation passed.\n";
