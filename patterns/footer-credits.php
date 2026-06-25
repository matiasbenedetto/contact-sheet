<?php
/**
 * Title: Footer Credits
 * Slug: contact-sheet/footer-credits
 * Inserter: no
 *
 * @package Contact_Sheet
 */

?>
<!-- wp:paragraph {"align":"left","className":"cs-footer-credits","style":{"typography":{"fontSize":"10px"}}} -->
<p class="has-text-align-left cs-footer-credits" style="font-size:10px">
<?php
	printf(
		/* translators: %s: Contact Sheet theme link. */
		esc_html__( 'Rendering %s', 'contact-sheet' ),
		'<a href="https://github.com/matiasbenedetto/contact-sheet" rel="nofollow">' . esc_html__( 'Contact Sheet', 'contact-sheet' ) . '</a>'
	);
	?>
	<br />
<?php
	printf(
		/* translators: %s: WordPress link. */
		esc_html__( 'Running on %s', 'contact-sheet' ),
		'<a href="https://wordpress.org" rel="nofollow">WordPress</a>'
	);
	?>
	</p>
<!-- /wp:paragraph -->
