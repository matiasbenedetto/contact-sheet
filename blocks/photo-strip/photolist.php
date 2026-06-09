<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Photo Strip block from its build directory.
 * Assets and render template are declared in build/block.json.
 */
function photo_strip_register_block() {
	register_block_type( get_theme_file_path( 'blocks/photo-strip/build' ) );
}
add_action( 'init', 'photo_strip_register_block' );

/**
 * Extract all images from post content.
 * Used by build/render.php at render time.
 *
 * @param string $content Raw post content.
 * @return array<array{src:string,alt:string}>
 */
function photo_strip_extract_images( $content ) {
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
				$size = photo_strip_smallest_uncropped_size( intval( $id_match[1] ) );
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
function photo_strip_smallest_uncropped_size( $attachment_id ) {
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
