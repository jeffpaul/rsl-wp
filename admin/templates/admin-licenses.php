<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<img src="<?php echo esc_url( RSL_PLUGIN_URL . 'admin/images/rsl-logo.png' ); ?>" 
			alt="RSL" class="rsl-admin-icon">
		<?php esc_html_e( 'All Licenses', 'rsl-wp' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-add-license' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New License', 'rsl-wp' ); ?>
	</a>
	<hr class="wp-header-end">
	
	<?php
	// Display admin notices
	if ( function_exists( 'settings_errors' ) ) {
		settings_errors();
	}
	do_action( 'admin_notices' );
	?>
	
	<div id="rsl-message" class="notice rsl-hidden"></div>
	
	<?php if ( empty( $licenses ) ) : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'No licenses found.', 'rsl-wp' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-add-license' ) ); ?>">
					<?php esc_html_e( 'Create your first license', 'rsl-wp' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'rsl-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Content URL', 'rsl-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Payment Type', 'rsl-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Usage Permits', 'rsl-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'rsl-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'rsl-wp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $licenses as $license ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $license['name'] ); ?></strong>
							<?php if ( ! empty( $license['description'] ) ) : ?>
								<br><small><?php echo esc_html( $license['description'] ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$content_url = $license['content_url'];
							if ( strlen( $content_url ) > 50 ) {
								echo esc_html( substr( $content_url, 0, 50 ) . '...' );
							} else {
								echo esc_html( $content_url );
							}
							?>
						</td>
						<td>
							<span class="rsl-payment-type rsl-payment-<?php echo esc_attr( $license['payment_type'] ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '-', ' ', $license['payment_type'] ) ) ); ?>
							</span>
							<?php if ( ! empty( $license['amount'] ) && $license['amount'] > 0 ) : ?>
								<br><small>
								<?php
									$currency_symbol = ! empty( $license['currency'] ) && $license['currency'] !== 'USD' ? $license['currency'] . ' ' : '$';
									echo esc_html( $currency_symbol . number_format( $license['amount'], 2 ) );
								?>
								</small>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$permits = array();
							if ( ! empty( $license['permits_usage'] ) ) {
								$permits[] = 'Usage: ' . $license['permits_usage'];
							}
							if ( ! empty( $license['permits_user'] ) ) {
								$permits[] = 'User: ' . $license['permits_user'];
							}
							if ( ! empty( $license['permits_geo'] ) ) {
								$permits[] = 'Geo: ' . $license['permits_geo'];
							}

							if ( ! empty( $permits ) ) {
								echo '<small>' . esc_html( implode( '; ', $permits ) ) . '</small>';
							} else {
								echo '<em>' . esc_html__( 'All permitted', 'rsl-wp' ) . '</em>';
							}
							?>
						</td>
						<td>
							<?php if ( $license['active'] ) : ?>
								<span class="rsl-status-active"><?php esc_html_e( 'Active', 'rsl-wp' ); ?></span>
							<?php else : ?>
								<span class="rsl-status-inactive"><?php esc_html_e( 'Inactive', 'rsl-wp' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rsl-add-license&edit=' . $license['id'] ), 'edit_license' ) ); ?>"
								class="button button-small">
								<?php esc_html_e( 'Edit', 'rsl-wp' ); ?>
							</a>
							
							<button type="button" class="button button-small rsl-generate-xml" 
									data-license-id="<?php echo esc_attr( $license['id'] ); ?>">
								<?php esc_html_e( 'Generate XML', 'rsl-wp' ); ?>
							</button>
							
							<button type="button" class="button button-small button-link-delete rsl-delete-license" 
									data-license-id="<?php echo esc_attr( $license['id'] ); ?>"
									data-license-name="<?php echo esc_attr( $license['name'] ); ?>">
								<?php esc_html_e( 'Delete', 'rsl-wp' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div id="rsl-xml-modal" class="rsl-modal rsl-hidden">
	<div class="rsl-modal-content">
		<div class="rsl-modal-header">
			<h3><?php esc_html_e( 'Generated RSL XML', 'rsl-wp' ); ?></h3>
			<span class="rsl-modal-close">&times;</span>
		</div>
		<div class="rsl-modal-body">
			<textarea id="rsl-xml-content" rows="20" cols="80" readonly></textarea>
			<p>
				<button type="button" id="rsl-copy-xml" class="button button-primary">
					<?php esc_html_e( 'Copy to Clipboard', 'rsl-wp' ); ?>
				</button>
				<button type="button" id="rsl-download-xml" class="button">
					<?php esc_html_e( 'Download XML', 'rsl-wp' ); ?>
				</button>
			</p>
		</div>
	</div>
</div>

