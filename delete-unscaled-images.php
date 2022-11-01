<?php
/**
 * Plugin Name: Delete Unscaled Images
 * Version: 1.2.2
 * Description: Deletes original image files if they have been resized.
 * Author: Greg Perham
 * Author URI: https://github.com/swinggraphics?tab=repositories
 * Requires at least: 5.3
 * Tested up to: 6.1
 * Text Domain: sgdui
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/* Plugin text domain */

function sgdui_load_textdomain() {
	load_plugin_textdomain( 'sgdui', false, plugin_basename(dirname(__FILE__ )) . '/languages' );
}
add_action( 'plugins_loaded', 'sgdui_load_textdomain' );


/* Delete unscaled images after upload */

function sgdui_delete_unscaled_upload( $metadata, $attachment_id ) {
	if ( ! empty( $metadata['original_image'] ) ) {
		$upload_dir = wp_upload_dir();
		$original_image = path_join( dirname( $metadata['file'] ), $metadata['original_image'] );
		$original_file = path_join( $upload_dir['basedir'], $original_image );
		if ( unlink( $original_file ) ) {
			unset( $metadata['original_image'] );
		}
	}
	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'sgdui_delete_unscaled_upload', 10, 2 );


/* Bulk process existing images */

function sgdui_bulk_unscaled_menu_item() {
	add_submenu_page(
		'upload.php',
		__( 'Bulk Delete Unscaled Images', 'sgdui' ),
		__( 'Delete Unscaled', 'sgdui' ),
		'install_plugins',
		'sg-unscaled-images',
		'sgdui_admin_page',
	);
}
add_action( 'admin_menu', 'sgdui_bulk_unscaled_menu_item' );

function sgdui_admin_page() {
	?>
	<div class="wrap">
		<h1><?php _e( 'Bulk Delete Unscaled Images', 'sgdui' ); ?></h1>
		<p><strong><a href="upload.php?page=sg-unscaled-images&action=do-bulk-delete"><?php _e( 'Delete all original, unscaled image files.', 'sgdui' ); ?></a></strong></p>
		<?php if ( isset( $_GET['action'] ) && 'do-bulk-delete' == $_GET['action'] ) :
			sgdui_bulk_delete_unscaled_images();
		?>
			<p><strong><?php _e( 'Done!', 'sgdui' ); ?></strong></p>
		<?php endif; ?>
	</div>
	<?php
}

function sgdui_bulk_delete_unscaled_images() {
	$args = array(
		'post_type' => 'attachment',
		'numberposts' => -1,
		'post_status' => null,
		'post_parent' => null,
	);
	$attachments = get_posts( $args );
	if ( $attachments ) {
		echo '<ul style="column-width: 30ch;">';
		foreach ( $attachments as $attachment ) {
			echo "<li>ID $attachment->ID: ";
			$metadata = wp_get_attachment_metadata( $attachment->ID );
			if ( empty( $metadata['original_image'] ) ) {
				_e( 'No unscaled original', 'sgdui' );
			} else {
				$original_image = $metadata['original_image'];
				if ( unlink( wp_get_original_image_path( $attachment->ID ) ) ) {
					unset( $metadata['original_image'] );
					wp_update_attachment_metadata( $attachment->ID, $metadata );
					printf(
						/* translators: %s is replaced by file name */
						__( 'Deleted %s', 'sgdui' ),
						$original_image
					);
				} else {
					printf(
						/* translators: %s is replaced by file name */
						__( 'Error deleting %s', 'sgdui' ),
						$original_image
					);
				}
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
