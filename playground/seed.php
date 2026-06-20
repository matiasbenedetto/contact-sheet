<?php
/**
 * Contact Sheet — demo content seeder (run via `playground.sh seed`).
 *
 * Creates a photo-blog with posts whose content is a sequence of image blocks,
 * so the bundled Photo Strip block (which extracts <img> tags from post content)
 * renders real contact-sheet strips on the home page and full-width photos on
 * single posts — mirroring the look of mebenedetto.com.
 *
 * Images are imported from /playground/assets/photos/ (a mount of the repo's
 * playground/assets/ dir). Re-runnable: skips if already seeded unless
 * CS_SEED_FORCE=1 (env), in which case it tears down prior demo content first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$force = getenv( 'CS_SEED_FORCE' ) === '1' || file_exists( '/host/seed-force' );

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$photos_dir = '/playground/assets/photos';

if ( ! is_dir( $photos_dir ) ) {
	echo "ERROR: photos dir not mounted at $photos_dir (did you run gen-photos.sh?)\n";
	return;
}

/**
 * Demo posts. 'photos' => [[file, alt], ...]; files live in $photos_dir.
 * Dates are set explicitly so pagination (5/page) shows across the home page.
 */
$demo = array(
	array(
		'title'   => 'Hello World',
		'slug'    => 'hello-world',
		'date'    => '2025-11-25 09:00:00',
		'excerpt' => 'First roll off the contact sheet.',
		'content' => '<!-- wp:paragraph --><p>The first frame on a fresh roll. Welcome to the contact sheet — every post here is a strip of photos, the way a photographer proofs a roll of film.</p><!-- /wp:paragraph -->',
		'photos'  => array(
			array( 'hello-world-1.jpg', 'Sunlit wall, San Telmo' ),
			array( 'hello-world-2.jpg', 'Coffee on a marble bar' ),
			array( 'hello-world-3.jpg', 'Shadow on cobblestones' ),
		),
		'comment' => true,
	),
	array(
		'title'   => 'San Telmo',
		'slug'    => 'san-telmo',
		'date'    => '2025-11-20 16:30:00',
		'excerpt' => 'A Saturday walk through Buenos Aires’ oldest barrio.',
		'content' => '<!-- wp:paragraph --><p>San Telmo on a weekend: antique stalls, milonga spilling out of doorways, and the warm ochre light that hits the facades in the late afternoon.</p><!-- /wp:paragraph -->',
		'photos'  => array(
			array( 'san-telmo-1.jpg', 'Defensa street market' ),
			array( 'san-telmo-2.jpg', 'Bandoneón player' ),
			array( 'san-telmo-3.jpg', 'Old doorway, Calle Balcarce' ),
			array( 'san-telmo-4.jpg', 'Yellow tram on cobbles' ),
			array( 'san-telmo-5.jpg', 'Courtyard cat' ),
			array( 'san-telmo-6.jpg', 'Dusk over Plaza Dorrego' ),
		),
	),
	array(
		'title'   => 'La Boca at Dusk',
		'slug'    => 'la-boca-at-dusk',
		'date'    => '2025-11-12 19:10:00',
		'excerpt' => 'Caminito empties out and the colors get loud.',
		'content' => '<!-- wp:paragraph --><p>Once the day-trippers leave, Caminito turns into a stage of sheet-metal walls painted in leftover boat paint — blue, red, yellow — lit by a few streetlamps.</p><!-- /wp:paragraph -->',
		'photos'  => array(
			array( 'la-boca-1.jpg', 'Caminito, blue wall' ),
			array( 'la-boca-2.jpg', 'Red and yellow facades' ),
			array( 'la-boca-3.jpg', 'Mural and streetlamp' ),
			array( 'la-boca-4.jpg', 'Riachuelo bridge at night' ),
			array( 'la-boca-5.jpg', 'Couple dancing tango' ),
		),
	),
	array(
		'title'   => 'Along the Riachuelo',
		'slug'    => 'along-the-riachuelo',
		'date'    => '2025-11-05 08:20:00',
		'excerpt' => 'Still water and rusting hulls at the old port.',
		'content' => '<!-- wp:paragraph --><p>The Riachuelo is Buenos Aires’ forgotten edge — a slow brown river lined with sunken boats and colorfully crumbling houses in La Boca and Avellaneda.</p><!-- /wp:paragraph -->',
		'photos'  => array(
			array( 'riachuelo-1.jpg', 'Boats at low tide' ),
			array( 'riachuelo-2.jpg', 'Conventillos on the bank' ),
			array( 'riachuelo-3.jpg', 'Transboder bridge' ),
			array( 'riachuelo-4.jpg', 'Fisherman at dawn' ),
		),
	),
	array(
		'title'   => 'Studio Days',
		'slug'    => 'studio-days',
		'date'    => '2025-10-28 11:00:00',
		'excerpt' => 'Quiet frames made between assignments.',
		'content' => '<!-- wp:paragraph --><p>Days with nothing to shoot for: still lives, a single window, the cat on the studio chair. The kind of roll that teaches you to look again.</p><!-- /wp:paragraph -->',
		'photos'  => array(
			array( 'studio-1.jpg', 'Window light on a table' ),
			array( 'studio-2.jpg', 'Leica on a shelf' ),
			array( 'studio-3.jpg', 'Studio chair and cat' ),
			array( 'studio-4.jpg', 'Contact print on the wall' ),
		),
	),
	array(
		'title'   => 'First Light, Costanera',
		'slug'    => 'first-light-costanera',
		'date'    => '2025-10-20 06:45:00',
		'excerpt' => 'The riverfront before the city wakes up.',
		'content' => '<!-- wp:paragraph --><p>The Costanera Sur ecological reserve at first light — reeds, joggers, and the silhouette of Puerto Madero waking up across the water.</p><!-- /wp:paragraph -->',
		'photos'  => array(
			array( 'costanera-1.jpg', 'Reeds at sunrise' ),
			array( 'costanera-2.jpg', 'Jogger on the path' ),
			array( 'costanera-3.jpg', 'Puerto Madero silhouette' ),
			array( 'costanera-4.jpg', 'Heron in the shallows' ),
			array( 'costanera-5.jpg', 'Golden light on the dock' ),
		),
	),
);

/**
 * Import a local file (already inside the Playground VFS) as an attachment.
 * Copies it into the uploads dir, registers it, and generates resized sub-sizes
 * so the Photo Strip block can pick the smallest uncropped size per image.
 */
function cs_import_attachment( $local_path, $alt, $parent = 0 ) {
	if ( ! file_exists( $local_path ) ) {
		echo "  WARN: missing $local_path\n";
		return 0;
	}
	$filename = basename( $local_path );
	$ud       = wp_upload_dir();
	$dest     = trailingslashit( $ud['path'] ) . wp_unique_filename( $ud['path'], $filename );
	copy( $local_path, $dest );

	$filetype = wp_check_filetype( $filename, null );
	$mime     = $filetype['type'] ?: 'image/jpeg';

	$attach = array(
		'post_mime_type' => $mime,
		'post_title'     => sanitize_title( preg_replace( '/\.[^.]+$/', '', $filename ) ),
		'post_content'   => '',
		'post_excerpt'   => $alt,
		'post_status'    => 'inherit',
		'post_parent'    => $parent,
	);
	$attach_id = wp_insert_attachment( $attach, $dest, $parent );
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		echo "  WARN: wp_insert_attachment failed for $filename\n";
		return 0;
	}
	$meta = wp_generate_attachment_metadata( $attach_id, $dest );
	wp_update_attachment_metadata( $attach_id, $meta );
	update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $attach_id, '_cs_demo', 1 );
	return $attach_id;
}

function cs_image_block( $attach_id ) {
	// Root-relative URL so the stored markup works regardless of the Playground
	// instance port (the seeder runs in its own short-lived PHP instance whose
	// siteurl is an ephemeral port; baking that into content would break images
	// on single posts, which render post_content verbatim).
	$url = wp_get_attachment_url( $attach_id );
	$url = preg_replace( '#^https?://[^/]+#', '', $url );
	$alt = get_post_meta( $attach_id, '_wp_attachment_image_alt', true );
	$img = sprintf(
		'<figure class="wp-block-image size-large"><img src="%s" alt="%s" class="wp-image-%d"/></figure>',
		esc_url( $url ),
		esc_attr( $alt ),
		$attach_id
	);
	return '<!-- wp:image {"id":' . $attach_id . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n" .
		$img . "\n" . '<!-- /wp:image -->';
}

// --- teardown prior demo content (only on --force) ---------------------------
if ( $force ) {
	echo "▶ force: removing prior demo content\n";
	foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_cs_demo' ) ) as $p ) {
		wp_delete_post( $p->ID, true );
	}
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_cs_demo' ) ) as $a ) {
		wp_delete_attachment( $a->ID, true );
	}
	delete_option( 'cs_seeded' );
}

if ( get_option( 'cs_seeded' ) && ! $force ) {
	echo "already seeded (run with CS_SEED_FORCE=1 to re-seed)\n";
	return;
}

// --- remove WP's default sample content so our posts own their slugs ---------
foreach ( array( 'hello-world', 'sample-page' ) as $slug ) {
	$default = get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) );
	if ( $default ) {
		wp_delete_post( $default->ID, true );
		echo "▶ removed default '$slug' (ID {$default->ID})\n";
	}
}

// --- site config -------------------------------------------------------------
update_option( 'blogname', 'mebenedetto' );
update_option( 'blogdescription', 'photo blog' );
update_option( 'timezone_string', 'America/Argentina/Buenos_Aires' );
update_option( 'permalink_structure', '/%year%/%monthnum%/%postname%/' );
update_option( 'posts_per_page', 5 );
// Date format like the prod blog (e.g. "November 25, 2025").
update_option( 'date_format', 'F j, Y' );

// --- create posts + photos ---------------------------------------------------
foreach ( $demo as $d ) {
	$existing = get_page_by_path( $d['slug'], OBJECT, 'post' );
	$content  = $d['content'];
	$photo_ids = array();

	foreach ( $d['photos'] as $photo ) {
		list( $file, $alt ) = $photo;
		$aid = cs_import_attachment( "$photos_dir/$file", $alt );
		if ( $aid ) {
			$photo_ids[] = $aid;
			$content    .= "\n\n" . cs_image_block( $aid );
		}
	}

	$post_id = wp_insert_post( array(
		'post_title'    => $d['title'],
		'post_name'     => $d['slug'],
		'post_content'  => $content,
		'post_excerpt'  => $d['excerpt'],
		'post_status'   => 'publish',
		'post_type'     => 'post',
		'post_date'     => $d['date'],
		'post_date_gmt' => get_gmt_from_date( $d['date'] ),
	), true );

	if ( is_wp_error( $post_id ) ) {
		echo "  WARN: failed to create '{$d['title']}': " . $post_id->get_error_message() . "\n";
		continue;
	}
	update_post_meta( $post_id, '_cs_demo', 1 );

	// Re-parent the imported attachments to the post so the media library groups them.
	foreach ( $photo_ids as $aid ) {
		wp_update_post( array( 'ID' => $aid, 'post_parent' => $post_id ) );
	}

	if ( ! empty( $d['comment'] ) ) {
		wp_insert_comment( array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => 'A reader',
			'comment_author_email' => 'reader@example.com',
			'comment_author_url'   => '',
			'comment_content'      => 'Beautiful light in these. The third frame especially.',
			'comment_approved'     => 1,
			'comment_date'          => $d['date'],
			'comment_meta'          => array( '_cs_demo' => 1 ),
		) );
	}

	$url = get_permalink( $post_id );
	echo "  created: {$d['title']} (" . count( $photo_ids ) . " photos) -> $url\n";
}

update_option( 'cs_seeded', 1 );
echo "done.\n";