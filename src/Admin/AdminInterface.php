<?php
/**
 * Admin settings interface for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Admin;

use MDS\Bandcamp\Core\Cache;
use MDS_RM_Release_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the plugin settings page and interface.
 *
 * @since 1.0.0
 */
final class AdminInterface {
	/**
	 * Settings option prefix preserved for backwards compatibility with `mds_bandcamp__settings`.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static string $plugin_key = 'mds_bandcamp';

	/**
	 * Register settings and admin menu hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_menu', array( self::class, 'add_settings_menu' ) );
		add_filter( 'mds_rm_site_settings_tabs', array( self::class, 'register_release_manager_tab' ) );
	}

	/**
	 * Register the plugin settings option and Release Manager extension meta.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		if ( class_exists( 'MDS_RM_Release_Meta' ) && method_exists( 'MDS_RM_Release_Meta', 'register_extension_meta' ) ) {
			MDS_RM_Release_Meta::register_extension_meta( 'bandcamp_album_id' );
			MDS_RM_Release_Meta::register_extension_meta(
				'bandcamp_album_url',
				array( 'sanitize_callback' => 'esc_url_raw' )
			);
			MDS_RM_Release_Meta::register_extension_meta(
				'bandcamp_artist_url',
				array( 'sanitize_callback' => 'esc_url_raw' ),
				'artist'
			);
			MDS_RM_Release_Meta::register_extension_meta(
				'bandcamp_artist_subdomain',
				array(),
				'artist'
			);
		}

		register_setting(
			self::$plugin_key,
			self::$plugin_key . '__settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize the plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $settings Submitted settings.
	 * @return array{transient?: int} Sanitized settings.
	 */
	public static function sanitize_settings( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$transient = (int) ( $settings['transient'] ?? 60 );
		$transient = max( 1, min( 1440, $transient ) );

		return array(
			'transient' => $transient,
		);
	}

	/**
	 * Add the standalone settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function add_settings_menu(): void {
		add_submenu_page(
			'options-general.php',
			__( 'MDS Bandcamp Integration', 'mds-bandcamp-integration' ),
			__( 'MDS Bandcamp Integration', 'mds-bandcamp-integration' ),
			'manage_options',
			self::$plugin_key,
			array( self::class, 'render_settings_page' ),
			99
		);
	}

	/**
	 * Render the standalone settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MDS Bandcamp Integration: Settings', 'mds-bandcamp-integration' ); ?></h1>
			<p><?php esc_html_e( 'Configure caching and inspect cached Bandcamp data below.', 'mds-bandcamp-integration' ); ?></p>
			<?php self::render_settings_content(); ?>
		</div>
		<?php
	}

	/**
	 * Add the Bandcamp tab to MDS Release Manager settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing settings tabs.
	 * @return array<string, array<string, mixed>> Settings tabs including Bandcamp.
	 */
	public static function register_release_manager_tab( array $tabs ): array {
		$tabs['bandcamp'] = array(
			'label'  => __( 'Bandcamp Integration', 'mds-bandcamp-integration' ),
			'render' => array( self::class, 'render_settings_content' ),
		);
		return $tabs;
	}

	/**
	 * Render settings content for either settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_settings_content(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Bandcamp settings.', 'mds-bandcamp-integration' ) );
		}

		$settings = get_option( self::$plugin_key . '__settings', array() );
		?>
		<form id="mds-bc-settings-form" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
			<div class="mds-panel">
				<h2><?php esc_html_e( 'Cache Settings', 'mds-bandcamp-integration' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="mds-bc-transient-length"><?php esc_html_e( 'Transient Cache Length', 'mds-bandcamp-integration' ); ?></label></th>
							<td>
								<input id="mds-bc-transient-length" type="number" name="<?php echo esc_attr( self::$plugin_key ); ?>__settings[transient]" value="<?php echo esc_attr( (string) ( $settings['transient'] ?? 60 ) ); ?>" min="1" max="1440" step="1">
								<p class="description"><?php esc_html_e( "Choose a length (in minutes) for the Bandcamp data to be cached as a transient. Shorter lengths update data more frequently but use more calls; longer lengths are ideal for high-traffic sites or data that doesn't change often.", 'mds-bandcamp-integration' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php settings_fields( self::$plugin_key ); ?>
				<div class="mds-admin-save-actions mds-bc-form-actions">
					<button id="mds-bc-save-settings-btn" class="gf-plugin-button button button-primary" type="submit"><?php esc_html_e( 'Save Settings', 'mds-bandcamp-integration' ); ?></button>
					<span id="mds-bc-save-status" class="mds-bc-status-message" role="status" aria-live="polite"></span>
				</div>
			</div>
		</form>
		<?php self::section_cache(); ?>
		<?php
	}

	/**
	 * Render cached transient information and cache controls.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function section_cache(): void {
		$cached_transients = Cache::get_transients_list( self::$plugin_key );
		$total_bytes       = 0;

		if ( ! empty( $cached_transients ) ) {
			$transients  = json_decode( $cached_transients, true );
			$total_bytes = is_array( $transients ) ? (int) array_sum( array_column( $transients, 'size_bytes' ) ) : 0;
		}
		?>
		<div class="mds-panel">
			<div class="gf-settings-header mds-bc-cache-header">
				<h2><?php esc_html_e( 'Cached Data', 'mds-bandcamp-integration' ); ?></h2>
				<form id="mds-bc-clear-cache-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'mds_bc_clear_cache_action', 'mds_bc_clear_cache_nonce' ); ?>
					<input type="hidden" name="action" value="mds_bc_clear_cache">
					<button id="mds-bc-clear-cache-btn" name="mds_bc_clear_cache" value="true" class="gf-plugin-button button" type="submit"><?php esc_html_e( 'Clear Cache', 'mds-bandcamp-integration' ); ?></button>
					<span id="mds-bc-cache-status" class="mds-bc-status-message" role="status" aria-live="polite"></span>
				</form>
			</div>
			<div class="gf-table-wrapper">
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th style="width:80px"><?php esc_html_e( 'Size', 'mds-bandcamp-integration' ); ?></th>
							<th style="width:180px"><?php esc_html_e( 'Expires', 'mds-bandcamp-integration' ); ?></th>
							<th><?php esc_html_e( 'Key', 'mds-bandcamp-integration' ); ?></th>
							<th><?php esc_html_e( 'Endpoint', 'mds-bandcamp-integration' ); ?></th>
							<th><?php esc_html_e( 'ID', 'mds-bandcamp-integration' ); ?></th>
						</tr>
					</thead>
					<tbody id="mds-bc-cache-table-body">
						<?php
						if ( $cached_transients ) {
							$transients = json_decode( $cached_transients, true );
							if ( is_array( $transients ) && ! empty( $transients ) ) {
								$transients = array_reverse( $transients );
								foreach ( $transients as $transient ) {
									echo '<tr><td>' . esc_html( (string) ( $transient['size_human'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $transient['expires'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $transient['key'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $transient['endpoint'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $transient['id'] ?? '' ) ) . '</td></tr>';
								}
							} elseif ( is_array( $transients ) ) {
								echo '<tr><td colspan="5">' . esc_html__( 'No Transients', 'mds-bandcamp-integration' ) . '</td></tr>';
							} else {
								echo '<tr><td colspan="5">' . esc_html__( 'Invalid JSON structure in transients array.', 'mds-bandcamp-integration' ) . '</td></tr>';
							}
						} else {
							echo '<tr><td colspan="5">' . esc_html__( 'No Transients', 'mds-bandcamp-integration' ) . '</td></tr>';
						}
						?>
					</tbody>
				</table>
			</div>
			<p><strong><?php esc_html_e( 'Current Cache Size:', 'mds-bandcamp-integration' ); ?></strong> <span id="mds-bc-cache-total-size"><?php echo esc_html( (string) size_format( $total_bytes ) ); ?></span></p>
		</div>
		<?php
	}
}
