<?php
/**
 * Bandcamp player wrapper.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed_html           = wp_kses_allowed_html( 'post' );
$allowed_html['iframe'] = array(
	'allow'             => true,
	'allowfullscreen'   => true,
	'allowtransparency' => true,
	'class'             => true,
	'frameborder'       => true,
	'height'            => true,
	'loading'           => true,
	'name'              => true,
	'referrerpolicy'    => true,
	'scrolling'         => true,
	'seamless'          => true,
	'src'               => true,
	'style'             => true,
	'title'             => true,
	'width'             => true,
);
?>
<div class="mds-bandcamp-player-wrapper"><?php echo wp_kses( $shortcode_html, $allowed_html ); ?></div>
