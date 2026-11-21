<?php
/**
 * Admin settings interface for MDS Bandcamp Integration.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the plugin settings page and interface.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Admin_Interface {
	private static $plugin_key = 'mds_bandcamp';

	public static function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_menu', array( self::class, 'add_settings_menu' ) );
		add_filter( 'mds_rm_site_settings_tabs', array( self::class, 'register_release_manager_tab' ) );
	}

	public static function register_settings(): void {
		register_setting(
			self::$plugin_key,
			self::$plugin_key . '__settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
			)
		);
	}

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

	public static function add_settings_menu(): void {
		add_submenu_page(
			'options-general.php',
			'MDS Bandcamp Integration',
			'MDS Bandcamp Integration',
			'manage_options',
			self::$plugin_key,
			array( self::class, 'render_settings_page' ),
			99
		);
	}

	public static function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1>MDS Bandcamp Integration: Settings</h1>
			<p>Configure caching and inspect cached Bandcamp data below.</p>
			<?php self::render_settings_content(); ?>
		</div>
		<?php
	}

	public static function register_release_manager_tab( array $tabs ): array {
		$tabs['bandcamp'] = array(
			'label'  => __( 'Bandcamp', 'mds-bandcamp-integration' ),
			'render' => array( self::class, 'render_settings_content' ),
		);
		return $tabs;
	}

	public static function render_settings_content(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Bandcamp settings.', 'mds-bandcamp-integration' ) );
		}

		$settings = get_option( self::$plugin_key . '__settings', array() );
		?>
		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">Transient Cache Length</th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::$plugin_key ); ?>__settings[transient]" value="<?php echo esc_attr( $settings['transient'] ?? 60 ); ?>" min="1" max="1440" step="1">
							<p class="description">Choose a length (in minutes) for the Bandcamp data to be cached as a transient. Shorter lengths update data more frequently but use more calls; longer lengths are ideal for high-traffic sites or data that doesn't change often.</p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php settings_fields( self::$plugin_key ); ?>
			<button class="gf-plugin-button button" type="submit">Save Settings</button>
		</form>
		<hr style="margin: 2em 0;">
		<?php self::section_cache(); ?>
		<?php
	}

	private static function section_cache(): void {
		$cached_transients = MDS_Bandcamp_Cache::get_transients_list( self::$plugin_key );
		$total_bytes = 0;

		if ( ! empty( $cached_transients ) ) {
			$transients = json_decode( $cached_transients, true );
			$total_bytes = is_array( $transients ) ? array_sum( array_column( $transients, 'size_bytes' ) ) : 0;
		}
		?>
		<div class="gf-settings-header">
			<h3>CACHED DATA</h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'gf_clear_cache_action', 'gf_clear_cache_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::$plugin_key ); ?>_clear_cache">
				<button name="gf_clear_cache" value="true" class="gf-plugin-button button">Clear Cache</button>
			</form>
		</div>
		<div class="gf-table-wrapper">
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th style="width:80px">Size</th>
						<th style="width:180px">Expires</th>
						<th>Key</th>
						<th>Endpoint</th>
						<th>ID</th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( $cached_transients ) {
						$transients = json_decode( $cached_transients, true );
						if ( is_array( $transients ) ) {
							$transients = array_reverse( $transients );
							foreach ( $transients as $transient ) {
								echo '<tr><td>' . esc_html( $transient['size_human'] ) . '</td><td>' . esc_html( $transient['expires'] ) . '</td><td>' . esc_html( $transient['key'] ) . '</td><td>' . esc_html( $transient['endpoint'] ) . '</td><td>' . esc_html( $transient['id'] ) . '</td></tr>';
							}
						} else {
							echo '<tr><td colspan="5">Invalid JSON structure in transients array.</td></tr>';
						}
					} else {
						echo '<tr><td colspan="5">No Transients</td></tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<p><strong>Current Cache Size:</strong> <?php echo esc_html( size_format( $total_bytes ) ); ?></p>
		<?php
	}
}

