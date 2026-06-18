<?php
/**
 * Title: Footer Credits
 * Slug: contact-sheet/footer-credits
 * Inserter: no
 */
?>
<!-- wp:paragraph {"align":"left","className":"cs-footer-credits","style":{"typography":{"fontSize":"10px"}}} -->
<p class="has-text-align-left cs-footer-credits" style="font-size:10px"><?php
	printf(
		/* translators: 1: Contact Sheet theme link, 2: Benedetto link. */
		esc_html__( 'Rendering %1$s a %2$s theme', 'contact-sheet' ),
		'<a href="https://github.com/matiasbenedetto/contact-sheet" rel="nofollow">' . esc_html__( 'Contact Sheet', 'contact-sheet' ) . '</a>',
		'<a href="https://mebenedetto.com">' . esc_html__( "Benedetto's", 'contact-sheet' ) . '</a>'
	);
?><br /><?php
	printf(
		/* translators: %s: WordPress link. */
		esc_html__( 'Running on %s', 'contact-sheet' ),
		'<a href="https://wordpress.org" rel="nofollow">WordPress</a>'
	);
?></p>
<!-- /wp:paragraph -->
