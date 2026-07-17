<?php
/**
 * seed-posts.php — create the demo blog posts on the server. Run with WP-CLI so
 * WordPress is fully loaded:
 *
 *   wp eval-file seed-posts.php <seed-posts.json> <images-base-dir> [--force] \
 *     --path=/var/www/html/contactsheetdemo.mebenedetto.com
 *
 * For each post in the JSON it imports that post's images (in filename order)
 * into the media library, then creates a published post whose content is the
 * paragraph followed by a gallery of those images. The first image becomes the
 * featured image. Idempotent: a post whose slug already exists is skipped
 * unless --force is passed (which updates it in place).
 *
 * $args holds the positional args passed after the script path.
 */

$args = $args ?? [];
$jsonPath = $args[0] ?? null;
$imgBase  = isset($args[1]) ? rtrim($args[1], '/') : null;
$force    = in_array('--force', $args, true);

if ($jsonPath === null || $imgBase === null) {
    WP_CLI::error('Usage: wp eval-file seed-posts.php <json> <images-dir> [--force]');
}

$posts = json_decode(file_get_contents($jsonPath), true);
if (!is_array($posts)) {
    WP_CLI::error("Could not read posts JSON at {$jsonPath}");
}

// Space posts one day apart so #1 (the showcase) is the newest on the blog.
$base = current_time('timestamp');

$made = 0;
foreach ($posts as $p) {
    $slug  = $p['slug'];
    $title = $p['title'];

    $existing = get_page_by_path($slug, OBJECT, 'post');
    if ($existing && !$force) {
        WP_CLI::log("skip  {$slug} (exists, #{$existing->ID})");
        continue;
    }

    // Import this post's images, in filename order.
    $dir = $imgBase . '/' . $p['dir'];
    $files = glob($dir . '/*.jpg') ?: [];
    sort($files);
    if ($files === []) {
        WP_CLI::warning("no images found for {$slug} in {$dir}");
    }

    $ids = [];
    foreach ($files as $f) {
        $out = WP_CLI::runcommand(
            'media import ' . escapeshellarg($f) . ' --porcelain --title=' . escapeshellarg($title),
            ['return' => true, 'launch' => false, 'exit_error' => false]
        );
        $id = (int) trim((string) $out);
        if ($id > 0) {
            $ids[] = $id;
        } else {
            WP_CLI::warning("import failed: {$f}");
        }
    }

    // Build block content: paragraph, then a gallery of the imported images.
    $content  = "<!-- wp:paragraph -->\n<p>" . esc_html($p['paragraph']) . "</p>\n<!-- /wp:paragraph -->\n\n";
    $content .= "<!-- wp:gallery {\"columns\":3,\"linkTo\":\"none\"} -->\n"
        . "<figure class=\"wp-block-gallery has-nested-images columns-3 is-cropped\">\n";
    foreach ($ids as $id) {
        $url = esc_url(wp_get_attachment_url($id));
        $content .= "<!-- wp:image {\"id\":{$id},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n"
            . "<figure class=\"wp-block-image size-large\"><img src=\"{$url}\" alt=\"\" class=\"wp-image-{$id}\"/></figure>\n"
            . "<!-- /wp:image -->\n";
    }
    $content .= "</figure>\n<!-- /wp:gallery -->\n";

    $when = date('Y-m-d H:i:s', $base - ((int) $p['num']) * DAY_IN_SECONDS);
    $postarr = [
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_content' => $content,
        'post_date'    => $when,
    ];
    if ($existing) {
        $postarr['ID'] = $existing->ID;
        $pid = wp_update_post($postarr, true);
    } else {
        $pid = wp_insert_post($postarr, true);
    }
    if (is_wp_error($pid)) {
        WP_CLI::warning("post failed for {$slug}: " . $pid->get_error_message());
        continue;
    }
    if ($ids) {
        set_post_thumbnail($pid, $ids[0]);
    }
    $made++;
    WP_CLI::success("post {$slug} (#{$pid}) — " . count($ids) . ' images');
}

WP_CLI::log("Done: {$made} post(s) created/updated.");
