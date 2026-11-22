<?php
/**
 * Standalone checks for plugin and theme template resolution.
 *
 * Run with: php tests/templates.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'MDS_BC_VERSION', '1.0.0' );
define( 'MDS_BC_PATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

$theme_template_calls = array();

function locate_template( $template_name )
{
	global $theme_template_calls;

	$theme_template_calls[] = $template_name;
	return __DIR__ . '/fixtures/theme-override.php';
}

function _doing_it_wrong( $function, $message, $version )
{
	throw new RuntimeException( $message );
}

function esc_html__( $value, $domain )
{
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $value )
{
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $value )
{
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html_e( $value, $domain )
{
	echo esc_html__( $value, $domain );
}

function wp_kses( $html, $allowed_html )
{
	return $html;
}

function disabled( $disabled )
{
	if ( $disabled ) {
		echo ' disabled="disabled"';
	}
}

require dirname( __DIR__ ) . '/src/Core/Templates.php';

$admin_output = MDS\Bandcamp\Core\Templates::render(
	'admin-settings.php',
	array(
		'nonce_field'        => '',
		'url'                => '',
		'album_id'           => '',
		'is_bandcamp_active' => true,
	)
);

if ( ! empty( $theme_template_calls ) || false === strpos( $admin_output, 'mds_bc_url' ) ) {
	throw new RuntimeException( 'The admin view must use the plugin-owned templates/admin template.' );
}

$frontend_output = MDS\Bandcamp\Core\Templates::render( 'frontend-widget.php' );
if (
	array( 'mds-bandcamp-integration/frontend-widget.php' ) !== $theme_template_calls
	|| 'child-theme-template' !== $frontend_output
) {
	throw new RuntimeException( 'Frontend templates must resolve through the theme override path.' );
}

echo "Template resolution tests passed.\n";
