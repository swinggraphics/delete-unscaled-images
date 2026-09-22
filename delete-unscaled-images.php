<?php
/**
 * Plugin Name: Delete Unscaled Images
 * Version: 2.0.0
 * Description: Safely scans for and deletes original images retained after WordPress creates -scaled versions.
 * Author: Greg Perham
 * Author URI: https://github.com/swinggraphics/delete-unscaled-images
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * Text Domain: sgdui
 */

defined( 'ABSPATH' ) || exit;

define( 'SGDUI_VERSION', '2.0.0' );
define( 'SGDUI_BATCH_SIZE', 100 );

/**
 * Load translations.
 */
function sgdui_load_textdomain() {
	load_plugin_textdomain( 'sgdui', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'sgdui_load_textdomain' );

/**
 * Resolve and validate the original/scaled file pair for an attachment.
 *
 * @param int        $attachment_id Attachment ID.
 * @param array|null $metadata      Optional attachment metadata.
 * @return array|false
 */
function sgdui_get_file_pair( $attachment_id, $metadata = null ) {
	if ( null === $metadata ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
	}

	if ( ! is_array( $metadata ) || empty( $metadata['original_image'] ) ) {
		return false;
	}

	$scaled_file = get_attached_file( $attachment_id, true );

	if ( ! is_string( $scaled_file ) || '' === $scaled_file || ! is_file( $scaled_file ) ) {
		return false;
	}

	// Only act on WordPress big-image replacements, never arbitrary metadata.
	if ( ! preg_match( '/-scaled\.[a-z0-9]+$/i', basename( $scaled_file ) ) ) {
		return false;
	}

	$original_name = sanitize_file_name( wp_basename( $metadata['original_image'] ) );
	if ( '' === $original_name ) {
		return false;
	}

	$original_file = wp_normalize_path( path_join( dirname( $scaled_file ), $original_name ) );
	$scaled_file    = wp_normalize_path( $scaled_file );
	$uploads        = wp_get_upload_dir();
	$uploads_base   = trailingslashit( wp_normalize_path( $uploads['basedir'] ) );

	if (
		$original_file === $scaled_file ||
		0 !== strpos( $original_file, $uploads_base ) ||
		! is_file( $original_file )
	) {
		return false;
	}

	return array(
		'original' => $original_file,
		'scaled'   => $scaled_file,
		'size'     => (int) filesize( $original_file ),
		'metadata' => $metadata,
	);
}

/**
 * Delete the retained original for new uploads after WordPress creates a
 * valid scaled replacement. Disable with:
 * add_filter( 'sgdui_delete_new_upload_originals', '__return_false' );
 *
 * @param array  $metadata      Attachment metadata.
 * @param int    $attachment_id Attachment ID.
 * @param string $context       Metadata generation context.
 * @return array
 */
function sgdui_delete_unscaled_upload( $metadata, $attachment_id, $context = '' ) {
	if (
		'create' !== $context ||
		! apply_filters( 'sgdui_delete_new_upload_originals', true, $attachment_id )
	) {
		return $metadata;
	}

	$pair = sgdui_get_file_pair( $attachment_id, $metadata );
	if ( false === $pair ) {
		return $metadata;
	}

	wp_delete_file( $pair['original'] );

	if ( ! file_exists( $pair['original'] ) ) {
		unset( $metadata['original_image'] );
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'sgdui_delete_unscaled_upload', 10, 3 );

/**
 * Register the bulk-cleanup screen.
 */
function sgdui_bulk_unscaled_menu_item() {
	add_submenu_page(
		'upload.php',
		__( 'Bulk Delete Unscaled Images', 'sgdui' ),
		__( 'Delete Unscaled', 'sgdui' ),
		'manage_options',
		'sg-unscaled-images',
		'sgdui_admin_page'
	);
}
add_action( 'admin_menu', 'sgdui_bulk_unscaled_menu_item' );

/**
 * Render the administration screen.
 */
function sgdui_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to access this page.', 'sgdui' ) );
	}

	$ajax_url = admin_url( 'admin-ajax.php' );
	$nonce    = wp_create_nonce( 'sgdui_process_images' );
	?>
	<div class="wrap" id="sgdui-app">
		<h1><?php esc_html_e( 'Bulk Delete Unscaled Images', 'sgdui' ); ?></h1>

		<p><?php esc_html_e( 'This tool keeps the active -scaled image and its thumbnails. It only targets the larger original recorded in WordPress attachment metadata.', 'sgdui' ); ?></p>
		<p><strong><?php esc_html_e( 'Create a file backup or hosting snapshot before deletion.', 'sgdui' ); ?></strong></p>

		<p>
			<button type="button" class="button button-secondary" id="sgdui-scan">
				<?php esc_html_e( 'Scan and calculate savings', 'sgdui' ); ?>
			</button>
			<button type="button" class="button button-primary" id="sgdui-delete" disabled>
				<?php esc_html_e( 'Delete confirmed originals', 'sgdui' ); ?>
			</button>
		</p>

		<div id="sgdui-progress" hidden>
			<progress value="0" max="100" style="width:min(700px, 100%);"></progress>
			<p id="sgdui-status" aria-live="polite"></p>
		</div>

		<div id="sgdui-result" class="notice inline" hidden><p></p></div>
	</div>

	<script>
	(function () {
		'use strict';

		const ajaxUrl  = <?php echo wp_json_encode( $ajax_url ); ?>;
		const nonce    = <?php echo wp_json_encode( $nonce ); ?>;
		const scanBtn  = document.getElementById('sgdui-scan');
		const deleteBtn = document.getElementById('sgdui-delete');
		const progress = document.getElementById('sgdui-progress');
		const bar      = progress.querySelector('progress');
		const status   = document.getElementById('sgdui-status');
		const result   = document.getElementById('sgdui-result');
		const resultText = result.querySelector('p');

		let running = false;
		let scanTotals = null;

		function bytes(bytes) {
			if (!bytes) return '0 B';
			const units = ['B', 'KB', 'MB', 'GB', 'TB'];
			const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
			return (bytes / Math.pow(1024, index)).toFixed(index ? 2 : 0) + ' ' + units[index];
		}

		async function request(mode, cursor) {
			const body = new URLSearchParams({
				action: 'sgdui_process_images',
				nonce: nonce,
				mode: mode,
				cursor: String(cursor)
			});

			const response = await fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			});

			const payload = await response.json();
			if (!response.ok || !payload.success) {
				throw new Error(payload?.data?.message || 'The batch request failed.');
			}
			return payload.data;
		}

		async function run(mode) {
			if (running) return;

			if (mode === 'delete' && !window.confirm(
				'Delete ' + scanTotals.files + ' originals and recover approximately ' + bytes(scanTotals.bytes) + '? This cannot be undone.'
			)) return;

			running = true;
			scanBtn.disabled = true;
			deleteBtn.disabled = true;
			progress.hidden = false;
			result.hidden = true;
			bar.removeAttribute('value');

			let cursor = 0;
			let totals = {processed: 0, files: 0, bytes: 0, deleted: 0, failed: 0};

			try {
				do {
					const batch = await request(mode, cursor);
					cursor = batch.next_cursor;
					totals.processed += batch.processed;
					totals.files += batch.files;
					totals.bytes += batch.bytes;
					totals.deleted += batch.deleted;
					totals.failed += batch.failed;

					status.textContent = 'Processed ' + totals.processed + ' attachments; found ' + totals.files + ' originals (' + bytes(totals.bytes) + ').';
					if (batch.done) break;
				} while (true);

				bar.value = 100;
				result.className = 'notice notice-success inline';
				result.hidden = false;

				if (mode === 'scan') {
					scanTotals = totals;
					resultText.textContent = 'Scan complete: ' + totals.files + ' originals can recover approximately ' + bytes(totals.bytes) + '.';
					deleteBtn.disabled = totals.files === 0;
				} else {
					resultText.textContent = 'Cleanup complete: deleted ' + totals.deleted + ' originals and recovered ' + bytes(totals.bytes) + '. Failures: ' + totals.failed + '.';
					scanTotals = null;
				}
			} catch (error) {
				result.className = 'notice notice-error inline';
				result.hidden = false;
				resultText.textContent = error.message;
			} finally {
				running = false;
				scanBtn.disabled = false;
				if (mode === 'scan' && scanTotals && scanTotals.files > 0) {
					deleteBtn.disabled = false;
				}
			}
		}

		scanBtn.addEventListener('click', function () { run('scan'); });
		deleteBtn.addEventListener('click', function () { run('delete'); });
	}());
	</script>
	<?php
}

/**
 * Process one scan/deletion batch.
 */
function sgdui_ajax_process_images() {
	check_ajax_referer( 'sgdui_process_images', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sgdui' ) ), 403 );
	}

	$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'scan';
	if ( ! in_array( $mode, array( 'scan', 'delete' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid operation.', 'sgdui' ) ), 400 );
	}

	$cursor = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;

	global $wpdb;

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type LIKE %s
			AND ID > %d
			ORDER BY ID ASC
			LIMIT %d",
			$wpdb->esc_like( 'image/' ) . '%',
			$cursor,
			SGDUI_BATCH_SIZE
		)
	);

	$result = array(
		'processed'   => count( $ids ),
		'files'       => 0,
		'bytes'       => 0,
		'deleted'     => 0,
		'failed'      => 0,
		'next_cursor' => $cursor,
		'done'        => count( $ids ) < SGDUI_BATCH_SIZE,
	);

	foreach ( $ids as $attachment_id ) {
		$attachment_id        = (int) $attachment_id;
		$result['next_cursor'] = $attachment_id;
		$metadata              = wp_get_attachment_metadata( $attachment_id );
		$pair                  = sgdui_get_file_pair( $attachment_id, $metadata );

		if ( false === $pair ) {
			continue;
		}

		$result['files']++;
		$result['bytes'] += $pair['size'];

		if ( 'delete' !== $mode ) {
			continue;
		}

		wp_delete_file( $pair['original'] );

		if ( file_exists( $pair['original'] ) ) {
			$result['failed']++;
			continue;
		}

		unset( $metadata['original_image'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		$result['deleted']++;
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_sgdui_process_images', 'sgdui_ajax_process_images' );
