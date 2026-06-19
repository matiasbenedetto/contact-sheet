<?php
/**
 * Contact Sheet theme functions
 */

// Load Photo Strip block
require_once get_template_directory() . '/blocks/photo-strip/photolist.php';

/**
 * Show 5 posts per page on archive and search results, matching the home page.
 *
 * The archive and search Query Loop blocks inherit the main query (so they keep
 * filtering by term / search keyword), which means their post count follows the
 * "Blog pages show at most" setting rather than a block attribute. Cap it at 5
 * here so these views match the home page's 5-per-page layout.
 */
function contact_sheet_posts_per_page( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_search() || $query->is_archive() ) {
		$query->set( 'posts_per_page', 5 );
	}
}
add_action( 'pre_get_posts', 'contact_sheet_posts_per_page' );
