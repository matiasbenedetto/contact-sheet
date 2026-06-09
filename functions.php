<?php
/**
 * Contact Sheet theme functions
 */

// Load Photo Strip block
require_once get_template_directory() . '/blocks/photo-strip/photolist.php';

// Register footer credits pattern (translatable)
function contact_sheet_register_patterns() {
	$credits  = sprintf(
		/* translators: 1: Contact Sheet theme link, 2: Benedetto link. */
		esc_html__( 'Rendering %1$s a %2$s theme', 'contact-sheet' ),
		'<a href="https://github.com/matiasbenedetto/contact-sheet" rel="nofollow">' . esc_html__( 'Contact Sheet', 'contact-sheet' ) . '</a>',
		'<a href="https://mebenedetto.com">' . esc_html__( "Benedetto's", 'contact-sheet' ) . '</a>'
	);
	$credits .= '<br />';
	$credits .= sprintf(
		/* translators: %s: WordPress link. */
		esc_html__( 'Running on %s', 'contact-sheet' ),
		'<a href="https://wordpress.org" rel="nofollow">WordPress</a>'
	);

	$content = '<!-- wp:paragraph {"align":"left","style":{"typography":{"fontSize":"10px"},"color":{"text":"#999999"},"elements":{"link":{"color":{"text":"#999999"}}}}} -->'
		. '<p class="has-text-align-left has-text-color has-link-color" style="color:#999999;font-size:10px">' . $credits . '</p>'
		. '<!-- /wp:paragraph -->';

	register_block_pattern(
		'contact-sheet/footer-credits',
		array(
			'title'    => __( 'Footer Credits', 'contact-sheet' ),
			'inserter' => false,
			'content'  => $content,
		)
	);
}
add_action( 'init', 'contact_sheet_register_patterns' );
