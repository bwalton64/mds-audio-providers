<?php
/**
 * Server-side rendering for the Bandcamp embed block.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$align = !empty( $attributes['align'] ) ? esc_attr( $attributes['align'] ) : '';
$anchor = !empty( $attributes['anchor'] ) ? esc_attr( $attributes['anchor'] ) : esc_attr('wp-block-' . wp_rand());
$blockID = !empty( $attributes['blockID'] ) ? esc_attr( $attributes['blockID'] ) : esc_attr('wp-block-' . wp_rand());
$className = !empty( $attributes['className'] ) ? esc_attr( $attributes['className'] ) : '';
$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && $_REQUEST['context'] === 'edit';
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$textAlign = $attributes['textAlign'] ?? 'center';
$embedType = $attributes['embedType'] ?? 'shortcode';
$code = $attributes['code'] ?? false;
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$wrapper_attributes = get_block_wrapper_attributes([
	'id' => $anchor,
	'style' => 'text-align:'.$textAlign.';'
]);
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

if (!$code) :
	
	echo wp_kses(MDS_Bandcamp_API::render_error_html([
		'error'=>true,
		'message'=>__('EMBED CODE REQUIRED','mds-bandcamp-integration')
	]), 'post');
	return;

endif;

if ($embedType == 'url') :

	$data = MDS_Bandcamp_API::fetch(['endpoint'=>'embed','id'=>$code]);

	if (!empty($data['error']) ) {
		echo wp_kses(MDS_Bandcamp_API::render_error_html($data), 'post');
		return;
	}

	if (empty($data)) :
		echo wp_kses(MDS_Bandcamp_API::render_error_html([
			'error'=>true,
			'message'=>__('BANDCAMP URL IS NOT VALID','mds-bandcamp-integration')
		]), 'post');
		return;
	endif;

	if ($is_editor && !empty($data['cached'])) {
		echo '<span style="position:absolute;z-index:99;top:10px;right:10px;background:#1da0c3;color:#fff;font-size:10px;padding:3px 10px;border-radius:20px;display:inline-block;">' . esc_html__( 'CACHE', 'mds-bandcamp-integration' ) . '</span>';
	}
	
	if (!empty($data['item_type']) && $data['item_type'] == 't') {
		$data['item_type'] = 'track';
	} else {
		$data['item_type'] = 'album';
	}

	$embed_url = 'https://bandcamp.com/EmbeddedPlayer/' . rawurlencode( (string) $data['item_type'] ) . '=' . rawurlencode( (string) $data['item_id'] ) . '/size=large/bgcol=ffffff/linkcol=111111/tracklist=false/artwork=small/transparent=true/';
	$output    = '
	<iframe style="border:0;width:100%;max-width:700px;height:120px;margin:0px;" src="' . esc_url( $embed_url ) . '" seamless>
		<a href="' . esc_url( $code ) . '">' . esc_html__( 'Listen on Bandcamp', 'mds-bandcamp-integration' ) . '</a>
	</iframe>';

elseif ($embedType == 'shortcode') :

	$output = do_shortcode($code);

elseif ($embedType == 'iframe') :

	$output = html_entity_decode($code);

endif; 
?>

<div <?php echo wp_kses_post(!$is_editor ? $wrapper_attributes : 'style="text-align:'.esc_attr($textAlign).'"'); ?>>
	<div id="<?php echo esc_attr($blockID); ?>" class="bandcamp-embed">
		<?php echo wp_kses( $output, 'post'); ?>
	</div>
</div>