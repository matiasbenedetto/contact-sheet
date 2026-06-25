<?php
/**
 * Photo Strip block registration and image-extraction helpers.
 *
 * @package Contact_Sheet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Photo Strip block from its build directory.
 *
 * Uses register_block_type_from_metadata() (the recommended block.json-based
 * registration API) rather than register_block_type(). Both read the block
 * metadata from build/block.json — including the `render` callback — but the
 * latter is flagged as plugin-territory by Theme Check; the metadata helper is
 * the correct, future-proof call for block themes.
 */
function contact_sheet_photo_strip_register_block() {
	register_block_type_from_metadata( get_theme_file_path( 'blocks/photo-strip/build' ) );
}
add_action( 'init', 'contact_sheet_photo_strip_register_block' );

/**
 * Extract all images from post content.
 * Used by build/render.php at render time.
 *
 * @param string $content Raw post content.
 * @return array<array{src:string,alt:string}>
 */
function contact_sheet_photo_strip_extract_images( $content ) {
	$images = array();
	$seen   = array();

	if ( preg_match_all( '/<img[^>]+>/i', $content, $tag_matches ) ) {
		foreach ( $tag_matches[0] as $tag ) {
			if ( ! preg_match( '/\bsrc="([^"]+)"/i', $tag, $src_match ) ) {
				continue;
			}
			$src = $src_match[1];
			$alt = preg_match( '/\balt="([^"]*)"/i', $tag, $alt_match ) ? $alt_match[1] : '';

			// Prefer the smallest generated size that preserves the original aspect ratio.
			if ( preg_match( '/\bwp-image-(\d+)\b/', $tag, $id_match ) ) {
				$size = contact_sheet_photo_strip_smallest_uncropped_size( intval( $id_match[1] ) );
				if ( $size ) {
					$url = wp_get_attachment_image_url( intval( $id_match[1] ), $size );
					if ( $url ) {
						$src = $url;
					}
				}
			}

			if ( isset( $seen[ $src ] ) ) {
				continue;
			}
			$seen[ $src ] = true;
			$images[]     = array( 'src' => $src, 'alt' => $alt );
		}
	}

	return $images;
}

/**
 * Find the smallest generated image size whose aspect ratio matches the original.
 * Skips cropped sizes like the default square "thumbnail".
 *
 * @param int $attachment_id
 * @return string|null Size slug, or null if none qualifies.
 */
function contact_sheet_photo_strip_smallest_uncropped_size( $attachment_id ) {
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $meta['sizes'] ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return null;
	}

	$orig_ratio = $meta['width'] / $meta['height'];
	$best_size  = null;
	$best_area  = PHP_INT_MAX;

	foreach ( $meta['sizes'] as $size_name => $info ) {
		if ( empty( $info['width'] ) || empty( $info['height'] ) ) {
			continue;
		}
		$ratio = $info['width'] / $info['height'];
		if ( abs( $ratio - $orig_ratio ) > 0.02 ) {
			continue;
		}
		$area = $info['width'] * $info['height'];
		if ( $area < $best_area ) {
			$best_area = $area;
			$best_size = $size_name;
		}
	}

	return $best_size;
}
