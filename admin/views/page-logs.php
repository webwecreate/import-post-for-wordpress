<?php
/**
 * Admin View: Import Logs
 *
 * Displays all import run logs with filters, pagination,
 * and clear-log AJAX actions.
 *
 * @package    CSV_Post_Importer
 * @subpackage CSV_Post_Importer/admin/views
 * @since      1.0.0
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Query params ─────────────────────────────────────────────── */
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$selected_import = isset( $_GET['import_id'] )     ? sanitize_key( wp_unslash( $_GET['import_id'] ) )     : '';
$filter_status   = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : 'all';
$current_page    = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
// phpcs:enable

$per_page = 50;
$offset   = ( $current_page - 1 ) * $per_page;

/* ── Logger instance ──────────────────────────────────────────── */
$import_ids = CPI_Logger::get_import_ids( 50 );
$import_ids = is_array( $import_ids ) ? $import_ids : array();

// Default to latest run if none selected.
if ( empty( $selected_import ) && ! empty( $import_ids ) ) {
	$selected_import = $import_ids[0] ?? '';
}

/* ── Fetch logs ───────────────────────────────────────────────── */
$filter_status_arg = ( 'all' !== $filter_status ) ? $filter_status : null;

$logs    = $selected_import ? CPI_Logger::get_logs( $selected_import, $filter_status_arg, $per_page, $offset ) : array();
$logs    = is_array( $logs ) ? $logs : array();
$summary = $selected_import ? CPI_Logger::get_summary( $selected_import ) : array();
$total   = array_sum( $summary );

/* ── Nonces ───────────────────────────────────────────────────── */
$nonce_clear_run = wp_create_nonce( 'cpi_clear_logs' );
$nonce_clear_all = wp_create_nonce( 'cpi_clear_all_logs' );

/* ── Helpers ──────────────────────────────────────────────────── */
$page_base = admin_url( 'admin.php?page=csv-post-importer-logs' );

/**
 * Return CSS class for a status badge (logs page version).
 *
 * @param string $status
 * @return string
 */
function cpi_logs_badge_class( $status ) {
	$map = array(
		'success'        => 'cpi-badge--success',
		'updated'        => 'cpi-badge--updated',
		'skipped'        => 'cpi-badge--skipped',
		'error'          => 'cpi-badge--error',
		'image_error'    => 'cpi-badge--image-error',
		'category_error' => 'cpi-badge--category-error',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : 'cpi-badge--skipped';
}

/**
 * Return human-readable label for a status (logs page version).
 *
 * @param string $status
 * @return string
 */
function cpi_logs_status_label( $status ) {
	$labels = array(
		'success'        => __( 'Success', 'csv-post-importer' ),
		'updated'        => __( 'Updated', 'csv-post-importer' ),
		'skipped'        => __( 'Skipped', 'csv-post-importer' ),
		'error'          => __( 'Error', 'csv-post-importer' ),
		'image_error'    => __( 'Image Error', 'csv-post-importer' ),
		'category_error' => __( 'Category Error', 'csv-post-importer' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : esc_html( $status );
}
?>

<div class="wrap cpi-wrap cpi-logs-page">

	<!-- ── Page header ─────────────────────────────────────────── -->
	<h1 class="cpi-page-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Import Logs', 'csv-post-importer' ); ?>
	</h1>

	<p class="cpi-page-subtitle">
		<?php esc_html_e( 'Review past import runs. Use filters to drill down into specific statuses.', 'csv-post-importer' ); ?>
	</p>

	<?php if ( empty( $import_ids ) ) : ?>

		<!-- ── Empty state ─────────────────────────────────────── -->
		<div class="cpi-empty-state cpi-empty-state--large">
			<span class="dashicons dashicons-media-spreadsheet cpi-empty-state__icon"></span>
			<h2><?php esc_html_e( 'No import logs yet.', 'csv-post-importer' ); ?></h2>
			<p><?php esc_html_e( 'Logs will appear here after you run an import.', 'csv-post-importer' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=csv-post-importer' ) ); ?>"
			   class="button button-primary">
				<?php esc_html_e( 'Start Import', 'csv-post-importer' ); ?>
			</a>
		</div>

	<?php else : ?>

		<!-- ── Filter bar ──────────────────────────────────────── -->
		<div class="cpi-card cpi-logs-filter-card">
			<form method="get" action="<?php echo esc_url( $page_base ); ?>" class="cpi-logs-filter-form">
				<input type="hidden" name="page" value="csv-post-importer-logs">

				<!-- Import run selector -->
				<label for="cpi-filter-import-id" class="cpi-filter-label">
					<?php esc_html_e( 'Import Run:', 'csv-post-importer' ); ?>
				</label>
				<select id="cpi-filter-import-id" name="import_id" class="cpi-filter-select">
					<?php foreach ( $import_ids as $run_id ) : ?>
						<?php
						$run_id   = (string) $run_id;
						$selected = selected( $selected_import, $run_id, false );
						// Extract date from import_id format: cpi_YYYYMMDD_HHiiss_xxxxxx
						preg_match( '/cpi_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', $run_id, $m );
						$run_date = ! empty( $m ) ? $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6] : $run_id;
						?>
						<option value="<?php echo esc_attr( $run_id ); ?>" <?php echo $selected; // phpcs:ignore ?>>
							<?php echo esc_html( $run_date . ' — ' . $run_id ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<!-- Status filter -->
				<label for="cpi-filter-status" class="cpi-filter-label">
					<?php esc_html_e( 'Status:', 'csv-post-importer' ); ?>
				</label>
				<select id="cpi-filter-status" name="filter_status" class="cpi-filter-select">
					<?php
					$status_options = array(
						'all'                             => __( 'All Statuses', 'csv-post-importer' ),
						'success'        => __( 'Success', 'csv-post-importer' ),
						'updated'        => __( 'Updated', 'csv-post-importer' ),
						'skipped'        => __( 'Skipped', 'csv-post-importer' ),
						'error'          => __( 'Error', 'csv-post-importer' ),
						'image_error'    => __( 'Image Error', 'csv-post-importer' ),
						'category_error' => __( 'Category Error', 'csv-post-importer' ),
					);
					foreach ( $status_options as $val => $label ) :
						?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_status, $val ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<input type="hidden" name="paged" value="1">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Apply Filter', 'csv-post-importer' ); ?>
				</button>
			</form>
		</div><!-- .cpi-logs-filter-card -->

		<?php if ( $selected_import ) : ?>

			<!-- ── Selected run summary mini-cards ─────────────── -->
			<div class="cpi-stat-cards cpi-stat-cards--compact">

				<div class="cpi-stat-card cpi-stat-card--total">
					<span class="cpi-stat-card__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
					<span class="cpi-stat-card__label"><?php esc_html_e( 'Total', 'csv-post-importer' ); ?></span>
				</div>
				<div class="cpi-stat-card cpi-stat-card--success">
					<span class="cpi-stat-card__value"><?php echo esc_html( number_format_i18n( $summary[ 'success' ] ?? 0 ) ); ?></span>
					<span class="cpi-stat-card__label"><?php esc_html_e( 'Created', 'csv-post-importer' ); ?></span>
				</div>
				<div class="cpi-stat-card cpi-stat-card--updated">
					<span class="cpi-stat-card__value"><?php echo esc_html( number_format_i18n( $summary[ 'updated' ] ?? 0 ) ); ?></span>
					<span class="cpi-stat-card__label"><?php esc_html_e( 'Updated', 'csv-post-importer' ); ?></span>
				</div>
				<div class="cpi-stat-card cpi-stat-card--skipped">
					<span class="cpi-stat-card__value"><?php echo esc_html( number_format_i18n( $summary[ 'skipped' ] ?? 0 ) ); ?></span>
					<span class="cpi-stat-card__label"><?php esc_html_e( 'Skipped', 'csv-post-importer' ); ?></span>
				</div>
				<div class="cpi-stat-card cpi-stat-card--error">
					<span class="cpi-stat-card__value"><?php echo esc_html( number_format_i18n( ( $summary[ 'error' ] ?? 0 ) + ( $summary[ 'image_error' ] ?? 0 ) + ( $summary[ 'category_error' ] ?? 0 ) ) ); ?></span>
					<span class="cpi-stat-card__label"><?php esc_html_e( 'Errors', 'csv-post-importer' ); ?></span>
				</div>

			</div>

			<!-- ── Clear buttons ───────────────────────────────── -->
			<div class="cpi-logs-actions">
				<button type="button"
				        id="cpi-btn-clear-run"
				        class="button cpi-btn-clear-run"
				        data-import-id="<?php echo esc_attr( $selected_import ); ?>"
				        data-nonce="<?php echo esc_attr( $nonce_clear_run ); ?>">
					<?php esc_html_e( 'Clear This Run', 'csv-post-importer' ); ?>
				</button>
				<button type="button"
				        id="cpi-btn-clear-all"
				        class="button cpi-btn-clear-all"
				        data-nonce="<?php echo esc_attr( $nonce_clear_all ); ?>">
					<?php esc_html_e( 'Clear All Logs', 'csv-post-importer' ); ?>
				</button>
				<span class="cpi-logs-clear-feedback" id="cpi-clear-feedback" aria-live="polite"></span>
			</div>

			<!-- ── Log table ───────────────────────────────────── -->
			<?php if ( empty( $logs ) ) : ?>

				<div class="cpi-empty-state">
					<span class="dashicons dashicons-search"></span>
					<p><?php esc_html_e( 'No log entries match the selected filters.', 'csv-post-importer' ); ?></p>
				</div>

			<?php else : ?>

				<table class="widefat striped cpi-logs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Row #', 'csv-post-importer' ); ?></th>
							<th><?php esc_html_e( 'Filename', 'csv-post-importer' ); ?></th>
							<th><?php esc_html_e( 'Status', 'csv-post-importer' ); ?></th>
							<th><?php esc_html_e( 'Message', 'csv-post-importer' ); ?></th>
							<th><?php esc_html_e( 'Date', 'csv-post-importer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['row_number'] ?? '—' ); ?></td>
								<td><?php echo esc_html( $log['filename'] ?? '—' ); ?></td>
								<td>
									<span class="cpi-badge <?php echo esc_attr( cpi_logs_badge_class( $log['status'] ) ); ?>">
										<?php echo esc_html( cpi_logs_status_label( $log['status'] ) ); ?>
									</span>
								</td>
								<td class="cpi-log-message">
									<?php
									$msg     = $log['message'] ?? '';
									$decoded = json_decode( $msg, true );
									echo esc_html( ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) ? $decoded['message'] : $msg );
									?>
								</td>
								<td class="cpi-log-date">
									<?php
									if ( ! empty( $log['created_at'] ) ) {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['created_at'] ) ) );
									} else {
										echo '—';
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- ── Pagination ──────────────────────────────── -->
				<?php
				// Count total matching rows for pagination.
				$all_for_count = CPI_Logger::get_logs( $selected_import, $filter_status_arg, 9999, 0 );
				$total_rows    = is_array( $all_for_count ) ? count( $all_for_count ) : 0;
				$total_pages   = $per_page > 0 ? (int) ceil( $total_rows / $per_page ) : 1;

				if ( $total_pages > 1 ) :
					$pagination_base = add_query_arg(
						array(
							'import_id'     => rawurlencode( $selected_import ),
							'filter_status' => rawurlencode( $filter_status ),
						),
						$page_base
					);
					?>
					<div class="cpi-pagination tablenav-pages">
						<?php if ( $current_page > 1 ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $pagination_base ) ); ?>"
							   class="button cpi-pagination__btn">
								&larr; <?php esc_html_e( 'Prev', 'csv-post-importer' ); ?>
							</a>
						<?php endif; ?>

						<span class="cpi-pagination__info">
							<?php
							/* translators: 1: current page, 2: total pages */
							printf( esc_html__( 'Page %1$d of %2$d', 'csv-post-importer' ), $current_page, $total_pages );
							?>
						</span>

						<?php if ( $current_page < $total_pages ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $pagination_base ) ); ?>"
							   class="button cpi-pagination__btn">
								<?php esc_html_e( 'Next', 'csv-post-importer' ); ?> &rarr;
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			<?php endif; ?>

		<?php endif; // $selected_import ?>

	<?php endif; // empty import_ids ?>

</div><!-- .cpi-wrap -->

<!-- ── Inline JS for AJAX clear actions ────────────────────────── -->
<script>
( function () {
	'use strict';

	const ajax = window.ajaxurl || '/wp-admin/admin-ajax.php';

	/* ── Clear This Run ────────────── */
	const btnRun = document.getElementById( 'cpi-btn-clear-run' );
	if ( btnRun ) {
		btnRun.addEventListener( 'click', function () {
			const importId = this.dataset.importId || '';
			const nonce    = this.dataset.nonce    || '';
			if ( ! importId ) { return; }

			const label = <?php echo wp_json_encode( __( 'Clear this run? This cannot be undone.', 'csv-post-importer' ) ); ?>;
			if ( ! window.confirm( label ) ) { return; }

			cpiClearLogs( { action: 'cpi_clear_logs', import_id: importId, nonce: nonce }, btnRun );
		} );
	}

	/* ── Clear All Logs ────────────── */
	const btnAll = document.getElementById( 'cpi-btn-clear-all' );
	if ( btnAll ) {
		btnAll.addEventListener( 'click', function () {
			const nonce = this.dataset.nonce || '';
			const label = <?php echo wp_json_encode( __( 'Clear ALL import logs? This cannot be undone.', 'csv-post-importer' ) ); ?>;
			if ( ! window.confirm( label ) ) { return; }

			cpiClearLogs( { action: 'cpi_clear_all_logs', nonce: nonce }, btnAll );
		} );
	}

	/* ── Shared clear handler ──────── */
	function cpiClearLogs( data, triggerBtn ) {
		const feedback = document.getElementById( 'cpi-clear-feedback' );
		triggerBtn.disabled = true;

		const formData = new FormData();
		Object.keys( data ).forEach( function ( k ) {
			formData.append( k, data[ k ] );
		} );

		fetch( ajax, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res.success ) {
					if ( feedback ) {
						feedback.textContent = res.data && res.data.message
							? res.data.message
							: <?php echo wp_json_encode( __( 'Logs cleared.', 'csv-post-importer' ) ); ?>;
						feedback.className = 'cpi-logs-clear-feedback cpi-logs-clear-feedback--ok';
					}
					// Reload after short delay so user sees feedback.
					setTimeout( function () { window.location.reload(); }, 1200 );
				} else {
					triggerBtn.disabled = false;
					if ( feedback ) {
						feedback.textContent = res.data && res.data.message
							? res.data.message
							: <?php echo wp_json_encode( __( 'Failed to clear logs. Please try again.', 'csv-post-importer' ) ); ?>;
						feedback.className = 'cpi-logs-clear-feedback cpi-logs-clear-feedback--error';
					}
				}
			} )
			.catch( function () {
				triggerBtn.disabled = false;
				if ( feedback ) {
					feedback.textContent = <?php echo wp_json_encode( __( 'Network error. Please try again.', 'csv-post-importer' ) ); ?>;
					feedback.className = 'cpi-logs-clear-feedback cpi-logs-clear-feedback--error';
				}
			} );
	}
} )();
</script>
