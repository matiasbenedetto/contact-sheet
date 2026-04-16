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

	// WordPress image blocks first
	if ( preg_match_all(
		'/<figure[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>.*?<img[^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*>.*?<\/figure>/is',
		$content,
		$matches
	) ) {
		for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
			$images[] = array( 'src' => $matches[1][ $i ], 'alt' => $matches[2][ $i ] );
		}
	}

	// Any remaining standalone <img> tags (deduplicated)
	if ( preg_match_all( '/<img[^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*>/i', $content, $matches ) ) {
		for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
			$entry = array( 'src' => $matches[1][ $i ], 'alt' => $matches[2][ $i ] );
			if ( ! in_array( $entry, $images, true ) ) {
				$images[] = $entry;
			}
		}
	}

	return $images;
}
