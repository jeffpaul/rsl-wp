<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<img src="<?php echo esc_url( RSL_PLUGIN_URL . 'admin/images/rsl-logo.png' ); ?>" 
			alt="RSL" class="rsl-admin-icon">
		<?php esc_html_e( 'RSL Settings', 'rsl-wp' ); ?>
	</h1>
	<hr class="wp-header-end">
	
	<?php
	// Display admin notices
	if ( function_exists( 'settings_errors' ) ) {
		settings_errors();
	}
	do_action( 'admin_notices' );
	?>
	
	
	<form method="post" action="options.php">
		<?php
		settings_fields( 'rsl_settings' );
		do_settings_sections( 'rsl_settings' );
		?>
		
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="rsl_global_license_id"><?php esc_html_e( 'Global License', 'rsl-wp' ); ?></label>
				</th>
				<td>
					<select name="rsl_global_license_id" id="rsl_global_license_id">
						<option value="0"><?php esc_html_e( 'No global license', 'rsl-wp' ); ?></option>
						<?php foreach ( $licenses as $license ) : ?>
							<option value="<?php echo esc_attr( $license['id'] ); ?>" 
									<?php selected( $global_license_id, $license['id'] ); ?>>
								<?php echo esc_html( $license['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select a license to apply site-wide. Individual posts/pages can override this.', 'rsl-wp' ); ?>
					</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'HTML Injection', 'rsl-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rsl_enable_html_injection" value="1" 
								<?php checked( get_option( 'rsl_enable_html_injection', 1 ) ); ?>>
						<?php esc_html_e( 'Enable HTML head injection', 'rsl-wp' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Automatically inject RSL license information into HTML head section.', 'rsl-wp' ); ?>
					</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'HTTP Headers', 'rsl-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rsl_enable_http_headers" value="1" 
								<?php checked( get_option( 'rsl_enable_http_headers', 1 ) ); ?>>
						<?php esc_html_e( 'Enable HTTP Link headers', 'rsl-wp' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Add RSL license information to HTTP response headers.', 'rsl-wp' ); ?>
					</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'robots.txt Integration', 'rsl-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rsl_enable_robots_txt" value="1" 
								<?php checked( get_option( 'rsl_enable_robots_txt', 1 ) ); ?>>
						<?php esc_html_e( 'Enable robots.txt license directive', 'rsl-wp' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Add License directive to robots.txt file.', 'rsl-wp' ); ?>
					</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'RSS Feed Integration', 'rsl-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rsl_enable_rss_feed" value="1" 
								<?php checked( get_option( 'rsl_enable_rss_feed', 1 ) ); ?>>
						<?php esc_html_e( 'Enable RSS feed RSL integration', 'rsl-wp' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Add RSL licensing information to RSS feeds.', 'rsl-wp' ); ?>
					</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'Media Metadata', 'rsl-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rsl_enable_media_metadata" value="1" 
								<?php checked( get_option( 'rsl_enable_media_metadata', 1 ) ); ?>>
						<?php esc_html_e( 'Enable media file metadata embedding', 'rsl-wp' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Embed RSL license information in uploaded media files.', 'rsl-wp' ); ?>
					</p>
				</td>
			</tr>
			
		</table>
		
		<?php submit_button(); ?>
	</form>
</div>