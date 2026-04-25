<?php
/**
 * Admin View: Import Result (Step 3)
 *
 * Displays import summary cards and a filterable result table.
 * Called by CPI_Admin after run_import_loop() completes,
 * or directly when ?import_id=xxx is present in the URL.
 *
 * @package    CSV_Post_Importer
 * @subpackage CSV_Post_Importer/admin/views
 * @since      1.0.0
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Resolve import_id ────────────────────────────────────────── */
$import_id = '';

if ( ! empty( $_GET['import_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$import_id = sanitize_key( wp_unslash( $_GET['import_id'] ) ); // phpcs:ignore
}

if ( empty( $import_id ) ) {
	$import_id = get_transient( 'cpi_last_import_id_' . get_current_user_id() );
}

/* ── Pagination & filter params ───────────────────────────────── */
$per_page      = 30;
$current_page  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore
$filter_status = isset( $_GET['filter_status'] ) ? sanitize_key( $_GET['filter_status'] ) : 'errors'; // phpcs:ignore
$offset        = ( $current_page - 1 ) * $per_page;

/* ── Fetch data ───────────────────────────────────────────────── */
$summary = $import_id ? CPI_Logger::get_summary( $import_id ) : array();
$total   = array_sum( $summary );

$logs_args = array(
	'import_id' => $import_id,
	'limit'     => $per_page,
	'offset'    => $offset,
);

if ( 'errors' === $filter_status ) {
	$error_statuses = array( 'error', 'image_error', 'category_error' );
	$all_rows       = array();
	foreach ( $error_statuses as $s ) {
		$rows = CPI_Logger::get_logs( $import_id, $s, $per_page, $offset );
		if ( is_array( $rows ) ) {
			$all_rows = array_merge( $all_rows, $rows );
		}
	}
	$logs = $all_rows;
} elseif ( 'all' === $filter_status ) {
	$logs = CPI_Logger::get_logs( $import_id, null, $per_page, $offset );
} else {
	$logs = CPI_Logger::get_logs( $import_id, $filter_status, $per_page, $offset );
}

$logs = is_array( $logs ) ? $logs : array();

/* ── Helpers ──────────────────────────────────────────────────── */
$admin_url   = admin_url( 'admin.php?page=csv-post-importer' );
$logs_url    = admin_url( 'admin.php?page=csv-post-importer-logs' );

/**
 * Return CSS class for a status badge.
 *
 * @param string $status
 * @return string
 */
function cpi_result_badge_class( $status ) {
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
 * Return human-readable label for a status.
 *
 * @param string $status
 * @return string
 */
function cpi_result_status_label( $status ) {
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

<div class="wrap cpi-wrap">

	<!-- ── Page header ─────────────────────────────────────────── -->
	<h1 class="cpi-page-title">
		<span class="dashicons dashicons-media-spreadsheet"></span>
		<?php esc_html_e( 'CSV Post Importer', 'csv-post-importer' ); ?>
	</h1>

	<!-- ── Step indicator ──────────────────────────────────────── -->
	<div class="cpi-steps">
		<div class="cpi-step cpi-step--done">
			<span class="cpi-step__num">✓</span>
			<span class="cpi-step__label"><?php esc_html_e( 'Upload CSV', 'csv-post-importer' ); ?></span>
		</div>
		<div class="cpi-step cpi-step--done">
			<span class="cpi-step__num">✓</span>
			<span class="cpi-step__label"><?php esc_html_e( 'Map Columns', 'csv-post-importer' ); ?></span>
		</div>
		<div class="cpi-step cpi-step--active">
			<span class="cpi-step__num">3</span>
			<span class="cpi-step__label"><?php esc_html_e( 'Result', 'csv-post-importer' ); ?></span>
		</div>
	</div>

	<?php if ( empty( $import_id ) ) : ?>

		<!-- ── No import ID ────────────────────────────────────── -->
		<div class="cpi-notice cpi-notice--warning">
			<span class="dashicons dashicons-warning"></span>
			<?php esc_html_e( 'No import result found. Please run an import first.', 'csv-post-importer' ); ?>
		</div>
		<p>
			<a href="<?php echo esc_url( $admin_url ); ?>" class="button button-primary">
				<?php esc_html_e( '← Start Import', 'csv-post-importer' ); ?>
			</a>
		</p>

	<?php else : ?>

		<!-- ── Import ID pill ──────────────────────────────────── -->
		<p class="cpi-import-id-label">
			<?php esc_html_e( 'Import ID:', 'csv-post-importer' ); ?>
			<code><?php echo esc_html( $import_id ); ?></code>
		</p>

		<!-- ── Summary cards ───────────────────────────────────── -->
		<div class="cpi-stat-cards">

			<div class="cpi-stat-card cpi-stat-card--total">
				<span class="cpi-stat-card__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
				<span class="cpi-stat-card__label"><?php esc_html_e( 'Total Rows', 'csv-post-importer' ); ?></span>
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

		</div><!-- .cpi-stat-cards -->

		<!-- ── Filter bar ──────────────────────────────────────── -->
		<div class="cpi-result-filter">
			<span class="cpi-result-filter__label"><?php esc_html_e( 'Show:', 'csv-post-importer' ); ?></span>

			<?php
			$filter_options = array(
				'errors'                        => __( 'Errors Only', 'csv-post-importer' ),
				'all'                           => __( 'All Rows', 'csv-post-importer' ),
				'success'      => __( 'Created', 'csv-post-importer' ),
				'updated'      => __( 'Updated', 'csv-post-importer' ),
				'skipped'      => __( 'Skipped', 'csv-post-importer' ),
				'error'        => __( 'Error', 'csv-post-importer' ),
				'image_error'  => __( 'Image Error', 'csv-post-importer' ),
				'category_error' => __( 'Category Error', 'csv-post-importer' ),
			);
			foreach ( $filter_options as $val => $label ) :
				$active_class = ( $filter_status === $val ) ? ' cpi-filter-btn--active' : '';
				$url          = add_query_arg(
					array(
						'import_id'     => rawurlencode( $import_id ),
						'filter_status' => rawurlencode( $val ),
						'paged'         => 1,
					),
					admin_url( 'admin.php?page=csv-post-importer&step=result' )
				);
				?>
				<a href="<?php echo esc_url( $url ); ?>"
				   class="button cpi-filter-btn<?php echo esc_attr( $active_class ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<!-- ── Result table ────────────────────────────────────── -->
		<?php if ( empty( $logs ) ) : ?>

			<div class="cpi-empty-state">
				<span class="dashicons dashicons-yes-alt"></span>
				<p><?php esc_html_e( 'No rows match the selected filter.', 'csv-post-importer' ); ?></p>
			</div>

		<?php else : ?>

			<table class="widefat striped cpi-result-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Row #', 'csv-post-importer' ); ?></th>
						<th><?php esc_html_e( 'Filename', 'csv-post-importer' ); ?></th>
						<th><?php esc_html_e( 'Post Title', 'csv-post-importer' ); ?></th>
						<th><?php esc_html_e( 'Status', 'csv-post-importer' ); ?></th>
						<th><?php esc_html_e( 'Message', 'csv-post-importer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['row_number'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $log['filename'] ?? '—' ); ?></td>
							<td>
								<?php
								// Attempt to decode JSON message for post title, fallback to filename.
								$meta = ! empty( $log['message'] ) ? json_decode( $log['message'], true ) : null;
								echo esc_html( ( is_array( $meta ) && ! empty( $meta['title'] ) ) ? $meta['title'] : ( $log['filename'] ?? '—' ) );
								?>
							</td>
							<td>
								<span class="cpi-badge <?php echo esc_attr( cpi_result_badge_class( $log['status'] ) ); ?>">
									<?php echo esc_html( cpi_result_status_label( $log['status'] ) ); ?>
								</span>
							</td>
							<td class="cpi-result-message">
								<?php
								$msg = $log['message'] ?? '';
								// If message is JSON, extract the 'message' key; else display raw.
								$decoded = json_decode( $msg, true );
								echo esc_html( ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) ? $decoded['message'] : $msg );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

		<?php endif; ?>

		<!-- ── Action buttons ──────────────────────────────────── -->
		<div class="cpi-result-actions">
			<a href="<?php echo esc_url( $admin_url ); ?>" class="button button-primary">
				<?php esc_html_e( '← Import Again', 'csv-post-importer' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'import_id', rawurlencode( $import_id ), $logs_url ) ); ?>"
			   class="button">
				<?php esc_html_e( 'View Full Logs', 'csv-post-importer' ); ?>
			</a>
		</div>

	<?php endif; ?>

</div><!-- .cpi-wrap -->
