<?php
/**
 * Bandcamp release and artist settings meta-box template.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo wp_kses(
	$nonce_field,
	array(
		'input' => array(
			'type'  => true,
			'id'    => true,
			'name'  => true,
			'value' => true,
		),
	)
);

$is_artist = ! empty( $is_artist_metabox );
?>
<?php if ( $is_artist ) : ?>
<table class="form-table">
	<tr>
		<th><label for="mds_bandcamp_artist_url"><?php esc_html_e( 'Bandcamp Artist URL', 'mds-bandcamp-integration' ); ?></label></th>
		<td>
			<div style="display: flex; gap: 10px; align-items: flex-start;">
				<input
					type="url"
					id="mds_bandcamp_artist_url"
					name="mds_bandcamp_artist_url"
					value="<?php echo esc_attr( $url ); ?>"
					class="regular-text"
					placeholder="<?php echo esc_attr( 'https://artist.bandcamp.com' ); ?>"
					<?php disabled( ! $is_bandcamp_active ); ?>
				/>
				<button
					type="button"
					id="mds_bandcamp_verify_artist_btn"
					class="button button-secondary"
					<?php disabled( ! $is_bandcamp_active ); ?>
				><?php esc_html_e( 'Verify', 'mds-bandcamp-integration' ); ?></button>
			</div>
			<p class="<?php echo esc_attr( 'description' . ( $is_bandcamp_active ? '' : ' mds-bandcamp-dependency-notice' ) ); ?>">
				<?php
				if ( $is_bandcamp_active ) {
					esc_html_e( 'Enter the Bandcamp Artist URL (for example https://artist.bandcamp.com) used by the Bandcamp Discography block.', 'mds-bandcamp-integration' );
				} else {
					esc_html_e( 'Only available if MDS Bandcamp Integration is active.', 'mds-bandcamp-integration' );
				}
				?>
			</p>
			<div id="mds_bandcamp_status" style="margin-top: 10px; display: none;"></div>
		</td>
	</tr>
	<tr>
		<th><label for="mds_bandcamp_artist_subdomain"><?php esc_html_e( 'Artist Subdomain', 'mds-bandcamp-integration' ); ?></label></th>
		<td>
			<input
				type="text"
				id="mds_bandcamp_artist_subdomain"
				name="mds_bandcamp_artist_subdomain"
				value="<?php echo esc_attr( $artist_subdomain ?? '' ); ?>"
				class="regular-text"
				readonly
			/>
			<p class="description">
				<?php esc_html_e( 'Extracted from the Bandcamp Artist URL and used to fetch the artist’s Bandcamp discography.', 'mds-bandcamp-integration' ); ?>
			</p>
		</td>
	</tr>
</table>
<?php else : ?>
<table class="form-table">
	<tr>
		<th><label for="mds_bandcamp_url"><?php esc_html_e( 'Bandcamp Release URL', 'mds-bandcamp-integration' ); ?></label></th>
		<td>
			<div style="display: flex; gap: 10px; align-items: flex-start;">
				<input
					type="url"
					id="mds_bandcamp_url"
					name="mds_bandcamp_url"
					value="<?php echo esc_attr( $url ); ?>"
					class="regular-text"
					placeholder="<?php echo esc_attr( 'https://artist.bandcamp.com/album/album-name' ); ?>"
					<?php disabled( ! $is_bandcamp_active ); ?>
				/>
				<button
					type="button"
					id="mds_bandcamp_verify_btn"
					class="button button-secondary"
					<?php disabled( ! $is_bandcamp_active ); ?>
				><?php esc_html_e( 'Verify', 'mds-bandcamp-integration' ); ?></button>
			</div>
			<p class="<?php echo esc_attr( 'description' . ( $is_bandcamp_active ? '' : ' mds-bandcamp-dependency-notice' ) ); ?>">
				<?php
				if ( $is_bandcamp_active ) {
					esc_html_e( 'Enter the direct link to your Bandcamp release and click "Verify" to extract the Album ID.', 'mds-bandcamp-integration' );
				} else {
					esc_html_e( 'Only available if MDS Bandcamp Integration is active.', 'mds-bandcamp-integration' );
				}
				?>
			</p>
			<div id="mds_bandcamp_status" style="margin-top: 10px; display: none;"></div>
		</td>
	</tr>
	<tr>
		<th><label for="mds_bandcamp_album_id">Album ID</label></th>
		<td>
			<input
				type="text"
				id="mds_bandcamp_album_id"
				name="mds_bandcamp_album_id"
				value="<?php echo esc_attr( $album_id ); ?>"
				class="regular-text"
				readonly
			/>
			<p class="description">
				<?php esc_html_e( 'Automatically populated when verification succeeds. This ID is used to render the Bandcamp audio player.', 'mds-bandcamp-integration' ); ?>
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>
