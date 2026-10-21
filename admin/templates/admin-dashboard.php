<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
} ?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<img src="
		<?php
		echo esc_url(
			RSL_PLUGIN_URL . 'admin/images/rsl-logo.png',
		);
		?>
		"
			alt="RSL" class="rsl-admin-icon">
		<?php esc_html_e( 'Really Simple Licensing', 'rsl-wp' ); ?>
	</h1>
	<a href="
	<?php
	echo esc_url(
		admin_url( 'admin.php?page=rsl-add-license' ),
	);
	?>
	" class="page-title-action">
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

	<?php if ( isset( $_GET['settings-updated'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'settings_updated' ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
			<?php
			esc_html_e(
				'Settings saved successfully.',
				'rsl-wp',
			);
			?>
			</p>
		</div>
	<?php endif; ?>

	<div id="rsl-message" class="rsl-hidden"></div>

	<!-- WordPress Native Meta Box Layout -->
	<div class="metabox-holder">

		<!-- At a Glance - Full Width -->
		<div class="postbox-container" style="width: 100%;">
			<div class="meta-box-sortables">
				<div id="rsl-at-a-glance" class="postbox">
					<div class="postbox-header">
						<h2 class="hndle ui-sortable-handle">
						<?php
						esc_html_e(
							'At a Glance',
							'rsl-wp',
						);
						?>
						</h2>
						<div class="handle-actions hide-if-no-js">
							<button type="button" class="handlediv" aria-expanded="true">
								<span class="screen-reader-text">
								<?php
								esc_html_e(
									'Toggle panel: At a Glance',
									'rsl-wp',
								);
								?>
								</span>
								<span class="toggle-indicator" aria-hidden="true"></span>
							</button>
						</div>
					</div>
					<div class="inside">
						<div class="rsl-at-glance-row">
							<!-- Left: Stats -->
							<div class="main">
								<ul>
									<li class="page-count">
										<a href="
										<?php
										echo esc_url(
											admin_url(
												'admin.php?page=rsl-licenses',
											),
										);
										?>
										">
											<?php
											echo esc_html(
												$total_licenses,
											);
											?>
										</a>
										<?php
										echo esc_html(
											_n(
												'License',
												'Licenses',
												$total_licenses,
												'rsl-wp',
											),
										);
										?>
									</li>
									<li class="post-count">
										<?php
										echo esc_html(
											$active_licenses,
										);
										?>
										<?php
										esc_html_e(
											'Active',
											'rsl-wp',
										);
										?>
									</li>
									<li class="comment-count">
										<span class="
										<?php
										echo $global_license_id >
										0
											? 'approved'
											: 'pending';
										?>
											">
											<?php
											echo $global_license_id > 0
												? '✓'
												: '×';
											?>
										</span>
										<?php
										esc_html_e(
											'Global License',
											'rsl-wp',
										);
										?>
									</li>
								</ul>
							</div>

							<!-- Right: Quick Actions -->
							<div class="rsl-quick-actions">
								<div class="rsl-action-buttons">
									<a href="
									<?php
									echo esc_url(
										admin_url(
											'admin.php?page=rsl-add-license',
										),
									);
									?>
									" class="button button-primary">
										<span class="dashicons dashicons-plus-alt"></span>
										<?php
										esc_html_e(
											'Create License',
											'rsl-wp',
										);
										?>
									</a>
									<a href="
									<?php
									echo esc_url(
										admin_url(
											'admin.php?page=rsl-licenses',
										),
									);
									?>
									" class="button">
										<span class="dashicons dashicons-list-view"></span>
										<?php
										esc_html_e(
											'Manage Licenses',
											'rsl-wp',
										);
										?>
									</a>
									<a href="
									<?php
									echo esc_url(
										admin_url(
											'admin.php?page=rsl-settings',
										),
									);
									?>
									" class="button">
										<span class="dashicons dashicons-admin-settings"></span>
										<?php
										esc_html_e(
											'Settings',
											'rsl-wp',
										);
										?>
									</a>
									<a href="https://rslstandard.org" target="_blank" class="button">
										<span class="dashicons dashicons-external"></span>
										<?php
										esc_html_e(
											'RSL Standard',
											'rsl-wp',
										);
										?>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Left Column -->
		<div class="postbox-container" style="width: 49%; margin-right: 2%;">
			<div class="meta-box-sortables">

				<!-- About RSL (Top Left) -->
				<div id="rsl-about" class="postbox">
					<div class="postbox-header">
						<h2 class="hndle ui-sortable-handle">
						<?php
						esc_html_e(
							'About RSL',
							'rsl-wp',
						);
						?>
						</h2>
						<div class="handle-actions hide-if-no-js">
							<button type="button" class="handlediv" aria-expanded="true">
								<span class="screen-reader-text">
								<?php
								esc_html_e(
									'Toggle panel: About RSL',
									'rsl-wp',
								);
								?>
								</span>
								<span class="toggle-indicator" aria-hidden="true"></span>
							</button>
						</div>
					</div>
					<div class="inside">
						<p>
							<?php
							esc_html_e(
								'Really Simple Licensing (RSL) is a machine-readable format for defining licensing terms for digital content. It enables content owners to specify how their content can be used by AI systems, search engines, and other automated tools.',
								'rsl-wp',
							);
							?>
						</p>

						<p>
							<a href="https://rslstandard.org" target="_blank" class="button">
								<span class="dashicons dashicons-external"></span>
								<?php esc_html_e( 'RSL Standard', 'rsl-wp' ); ?>
							</a>
							<a href="https://rslcollective.org" target="_blank" class="button rsl-button-gap">
								<span class="dashicons dashicons-groups"></span>
								<?php esc_html_e( 'RSL Collective', 'rsl-wp' ); ?>
							</a>
						</p>
					</div>
				</div>

				<!-- Integration Status (Bottom Left) -->
				<div id="rsl-integration-status" class="postbox">
					<div class="postbox-header">
						<h2 class="hndle ui-sortable-handle">
							<?php esc_html_e( 'Integration Status', 'rsl-wp' ); ?>
							<a href="
							<?php
							echo esc_url(
								admin_url( 'admin.php?page=rsl-settings' ),
							);
							?>
							" class="rsl-header-link">
								<?php esc_html_e( 'Go to Settings', 'rsl-wp' ); ?>
							</a>
						</h2>
						<div class="handle-actions hide-if-no-js">
							<button type="button" class="handlediv" aria-expanded="true">
								<span class="screen-reader-text">
								<?php
								esc_html_e(
									'Toggle panel: Integration Status',
									'rsl-wp',
								);
								?>
								</span>
								<span class="toggle-indicator" aria-hidden="true"></span>
							</button>
						</div>
					</div>
					<div class="inside">
						<table class="widefat striped">
							<tbody>
								<tr>
									<td>
									<?php
									esc_html_e(
										'HTML Head Injection',
										'rsl-wp',
									);
									?>
									</td>
									<td>
										<span class="
										<?php
										echo get_option(
											'rsl_enable_html_injection',
											1,
										)
											? 'yes'
											: 'no';
										?>
											">
											<?php
											echo get_option(
												'rsl_enable_html_injection',
												1,
											)
													? esc_html__(
														'Enabled',
														'rsl-wp',
													)
												: esc_html__(
													'Disabled',
													'rsl-wp',
												);
											?>
										</span>
									</td>
								</tr>
								<tr>
									<td>
									<?php
									esc_html_e(
										'HTTP Link Headers',
										'rsl-wp',
									);
									?>
									</td>
									<td>
										<span class="
										<?php
										echo get_option(
											'rsl_enable_http_headers',
											1,
										)
											? 'yes'
											: 'no';
										?>
											">
											<?php
											echo get_option(
												'rsl_enable_http_headers',
												1,
											)
													? esc_html__(
														'Enabled',
														'rsl-wp',
													)
												: esc_html__(
													'Disabled',
													'rsl-wp',
												);
											?>
										</span>
									</td>
								</tr>
								<tr>
									<td>
									<?php
									esc_html_e(
										'robots.txt Integration',
										'rsl-wp',
									);
									?>
									</td>
									<td>
										<span class="
										<?php
										echo get_option(
											'rsl_enable_robots_txt',
											1,
										)
											? 'yes'
											: 'no';
										?>
											">
											<?php
											echo get_option(
												'rsl_enable_robots_txt',
												1,
											)
													? esc_html__(
														'Enabled',
														'rsl-wp',
													)
												: esc_html__(
													'Disabled',
													'rsl-wp',
												);
											?>
										</span>
									</td>
								</tr>
								<tr>
									<td>
									<?php
									esc_html_e(
										'RSS Feed Enhancement',
										'rsl-wp',
									);
									?>
									</td>
									<td>
										<span class="
										<?php
										echo get_option(
											'rsl_enable_rss_feed',
											1,
										)
											? 'yes'
											: 'no';
										?>
											">
											<?php
											echo get_option(
												'rsl_enable_rss_feed',
												1,
											)
													? esc_html__(
														'Enabled',
														'rsl-wp',
													)
												: esc_html__(
													'Disabled',
													'rsl-wp',
												);
											?>
										</span>
									</td>
								</tr>
								<tr>
									<td>
									<?php
									esc_html_e(
										'Media Metadata',
										'rsl-wp',
									);
									?>
									</td>
									<td>
										<span class="
										<?php
										echo get_option(
											'rsl_enable_media_metadata',
											1,
										)
											? 'yes'
											: 'no';
										?>
											">
											<?php
											echo get_option(
												'rsl_enable_media_metadata',
												1,
											)
													? esc_html__(
														'Enabled',
														'rsl-wp',
													)
												: esc_html__(
													'Disabled',
													'rsl-wp',
												);
											?>
										</span>
									</td>
								</tr>
								<tr>
									<td>
									<?php
									esc_html_e(
										'WooCommerce',
										'rsl-wp',
									);
									?>
									</td>
									<td>
										<?php
										if (
											class_exists( 'WooCommerce' )
										) :
											?>
											<span class="yes">✓ 
											<?php
											esc_html_e(
												'Active',
												'rsl-wp',
											);
											?>
											</span>
											<?php
											if (
												class_exists( 'WC_Subscriptions' )
											) :
												?>
												<br><small>
												<?php
												esc_html_e(
													'Subscriptions: Available',
													'rsl-wp',
												);
												?>
												</small>
											<?php else : ?>
												<br><small style="color: #856404;">
												<?php
												esc_html_e(
													'Subscriptions: Extension needed',
													'rsl-wp',
												);
												?>
												</small>
											<?php endif; ?>
										<?php else : ?>
											<span class="no">✗ 
											<?php
											esc_html_e(
												'Not installed',
												'rsl-wp',
											);
											?>
											</span>
											<br><small>
											<?php
											esc_html_e(
												'Required for paid licensing',
												'rsl-wp',
											);
											?>
											</small>
										<?php endif; ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

			</div>
		</div>

		<!-- Right Column -->
		<div class="postbox-container" style="width: 49%;">
			<div class="meta-box-sortables">

				<!-- Recent Licenses (Top Right) -->
				<div id="rsl-recent-licenses" class="postbox">
					<div class="postbox-header">
						<h2 class="hndle ui-sortable-handle">
						<?php
						esc_html_e(
							'Recent Licenses',
							'rsl-wp',
						);
						?>
						</h2>
						<div class="handle-actions hide-if-no-js">
							<button type="button" class="handlediv" aria-expanded="true">
								<span class="screen-reader-text">
								<?php
								esc_html_e(
									'Toggle panel: Recent Licenses',
									'rsl-wp',
								);
								?>
								</span>
								<span class="toggle-indicator" aria-hidden="true"></span>
							</button>
						</div>
					</div>
					<div class="inside">
						<?php if ( ! empty( $licenses ) ) : ?>
							<?php
							$recent_licenses = array_slice(
								array_reverse( $licenses ),
								0,
								5,
							);
							foreach ( $recent_licenses as $license ) :
								?>
								<div class="rsl-recent-license">
									<a href="
									<?php
									echo esc_url(
										admin_url(
											'admin.php?page=rsl-add-license&edit=' .
												$license['id'],
										),
									);
									?>
									" class="rsl-license-link">
										<div>
											<strong>
											<?php
											echo esc_html(
												$license['name'],
											);
											?>
											</strong>
											<span class="rsl-payment-tag">
												<?php
												echo esc_html(
													ucfirst(
														str_replace(
															'-',
															' ',
															$license['payment_type'],
														),
													),
												);
												?>
											</span>
										</div>
										<div class="rsl-license-content-url">
											<?php
											echo esc_html(
												$license['content_url'],
											);
											?>
										</div>
									</a>
								</div>
								<?php
							endforeach;
							?>
							<p class="rsl-view-all-link">
								<a href="
								<?php
								echo esc_url(
									admin_url( 'admin.php?page=rsl-licenses' ),
								);
								?>
								">
									<?php
									esc_html_e(
										'View all licenses →',
										'rsl-wp',
									);
									?>
								</a>
							</p>
						<?php else : ?>
							<p>
								<?php
								esc_html_e(
									'No licenses created yet.',
									'rsl-wp',
								);
								?>
								<a href="
								<?php
								echo esc_url(
									admin_url( 'admin.php?page=rsl-add-license' ),
								);
								?>
								">
									<?php
									esc_html_e(
										'Create your first license',
										'rsl-wp',
									);
									?>
								</a>
							</p>
						<?php endif; ?>
					</div>
				</div>

				<!-- System Status (Bottom Right) -->
				<div id="rsl-system-status" class="postbox">
					<div class="postbox-header">
						<h2 class="hndle ui-sortable-handle">
						<?php
						esc_html_e(
							'System Status',
							'rsl-wp',
						);
						?>
						</h2>
						<div class="handle-actions hide-if-no-js">
							<button type="button" class="handlediv" aria-expanded="true">
								<span class="screen-reader-text">
								<?php
								esc_html_e(
									'Toggle panel: System Status',
									'rsl-wp',
								);
								?>
								</span>
								<span class="toggle-indicator" aria-hidden="true"></span>
							</button>
						</div>
					</div>
					<div class="inside">
						<table class="widefat">
							<tbody>
								<tr>
									<td>
									<?php
									esc_html_e(
										'WordPress',
										'rsl-wp',
									);
									?>
									</td>
									<td>
									<?php
									echo esc_html(
										get_bloginfo( 'version' ),
									);
									?>
									</td>
								</tr>
								<tr>
									<td>
									<?php
									esc_html_e(
										'PHP',
										'rsl-wp',
									);
									?>
									</td>
									<td>
									<?php
									echo esc_html(
										PHP_VERSION,
									);
									?>
									</td>
								</tr>
								<tr>
									<td>
									<?php
									esc_html_e(
										'RSL Plugin',
										'rsl-wp',
									);
									?>
									</td>
									<td>
									<?php
									echo esc_html(
										RSL_PLUGIN_VERSION,
									);
									?>
									</td>
								</tr>
								<?php if ( function_exists( 'curl_version' ) ) : ?>
								<tr>
									<td>
									<?php
									esc_html_e(
										'cURL Support',
										'rsl-wp',
									);
									?>
									</td>
									<td><span class="yes">✓</span></td>
								</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

			</div>
		</div>

	</div>

	<script>
	jQuery(function($){
		// Initialize WordPress native postbox toggle functionality
		if (typeof postboxes !== 'undefined') {
			postboxes.add_postbox_toggles('<?php echo esc_js( get_current_screen()->id ); ?>');
		}
	});
	</script>

</div>
