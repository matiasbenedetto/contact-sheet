<?php
declare(strict_types=1);

/**
 * generate-images.php — render the demo-site photo galleries.
 *
 * Parses demo-site/plan/posts.md and, for every image listed under each post's
 * "Image gallery", generates a real photo with the Google Imagen image client
 * borrowed from the sibling builder project
 * (/home/matias/dev/a8c/builder). Images land in demo-site/images/<post>/ and a
 * manifest.json records every prompt / file / aspect ratio for the later
 * content-seeding step.
 *
 * Each gallery line in posts.md has the shape:
 *   subject, colors, textures & POV | location | camera & film | aspect ratio
 * We turn the first three fields (plus the post's shared "Image grade") into
 * the text prompt, and map the aspect-ratio field to an Imagen ratio.
 *
 * Usage:
 *   php demo-site/scripts/generate-images.php [options]
 *
 * Options:
 *   --dry-run          Parse + compose prompts and print them; make no API calls.
 *   --only=<n|slug>    Only this post (by number, e.g. 1, or slug substring).
 *   --limit=<n>        At most N images per post (handy for a cheap sample run).
 *   --force            Regenerate images even if the output file already exists.
 *   --size=1K|2K       Imagen sample size (default 1K).
 *   --posts=<path>     Override the posts.md path.
 *   --out=<dir>        Override the output image directory.
 *   --builder=<dir>    Override the builder repo path (or set BUILDER_DIR env).
 *
 * Requires GOOGLE_VERTEX_API_TOKEN in the builder's .env (already the token the
 * builder uses for its own image generation).
 */

// ---------------------------------------------------------------------------
// Paths & options
// ---------------------------------------------------------------------------

$demoRoot = dirname(__DIR__);                 // .../contact-sheet/demo-site
$repoRoot = dirname($demoRoot);               // .../contact-sheet

$opts = [
    'dry-run' => false,
    'only'    => null,
    'limit'   => null,
    'force'   => false,
    'size'    => '1K',
    'posts'   => $demoRoot . '/plan/posts.md',
    'out'     => $demoRoot . '/images',
    'builder' => getenv('BUILDER_DIR') ?: '/home/matias/dev/a8c/builder',
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') { $opts['dry-run'] = true; continue; }
    if ($arg === '--force')   { $opts['force']   = true; continue; }
    if (preg_match('/^--(only|limit|size|posts|out|builder)=(.*)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
        continue;
    }
    fwrite(STDERR, "Unknown option: {$arg}\n");
    exit(1);
}
$opts['limit'] = $opts['limit'] !== null ? max(0, (int) $opts['limit']) : null;

// ---------------------------------------------------------------------------
// Load the builder's image client (unless we're only doing a dry run)
// ---------------------------------------------------------------------------

$imageClient = null;
if (!$opts['dry-run']) {
    $bootstrap = rtrim($opts['builder'], '/') . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        fwrite(STDERR, "Cannot find builder bootstrap at {$bootstrap}\n");
        fwrite(STDERR, "Point --builder=<dir> (or BUILDER_DIR) at the builder repo.\n");
        exit(1);
    }
    require_once $bootstrap;                   // defines make_image_client(), loads .env
    try {
        $imageClient = make_image_client();
    } catch (\Throwable $e) {
        fwrite(STDERR, "Could not build the image client: {$e->getMessage()}\n");
        fwrite(STDERR, "Ensure GOOGLE_VERTEX_API_TOKEN is set in {$opts['builder']}/.env\n");
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Parse posts.md → [ ['num','title','slug','grade','images'=>[['n','text']]] ]
// ---------------------------------------------------------------------------

if (!is_file($opts['posts'])) {
    fwrite(STDERR, "posts.md not found at {$opts['posts']}\n");
    exit(1);
}

$posts = parse_posts(file_get_contents($opts['posts']));
if ($posts === []) {
    fwrite(STDERR, "No posts with galleries found in {$opts['posts']}\n");
    exit(1);
}

// Optional filter.
if ($opts['only'] !== null) {
    $needle = strtolower((string) $opts['only']);
    $posts = array_values(array_filter($posts, static function (array $p) use ($needle): bool {
        return (string) $p['num'] === $needle || str_contains($p['slug'], $needle);
    }));
    if ($posts === []) {
        fwrite(STDERR, "No post matches --only={$opts['only']}\n");
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Generate
// ---------------------------------------------------------------------------

@mkdir($opts['out'], 0775, true);
$manifest = [];
$totals = ['planned' => 0, 'generated' => 0, 'skipped' => 0, 'failed' => 0];

foreach ($posts as $post) {
    $postDir = sprintf('%s/%02d-%s', $opts['out'], $post['num'], $post['slug']);
    @mkdir($postDir, 0775, true);

    $images = $post['images'];
    if ($opts['limit'] !== null) {
        $images = array_slice($images, 0, $opts['limit']);
    }

    printf("\n#%d %s  (%d image%s)\n", $post['num'], $post['title'], count($images), count($images) === 1 ? '' : 's');

    // Compose every image's prompt / target first, so we can batch the pending ones.
    $planned = [];
    foreach ($images as $img) {
        $aspect = aspect_to_ratio($img['text']);
        $prompt = compose_prompt($img['text'], $post['grade']);
        $file   = sprintf('%s/%02d-%s.jpg', $postDir, $img['n'], short_slug($img['text']));
        $rel    = ltrim(str_replace($repoRoot, '', $file), '/');
        $planned[] = compact('img', 'aspect', 'prompt', 'file', 'rel');
        $totals['planned']++;
    }

    // Which ones actually need generating?
    $pending = [];
    foreach ($planned as $p) {
        $exists = is_file($p['file']);
        $status = $exists && !$opts['force'] ? 'skipped' : 'pending';
        if ($status === 'skipped') {
            $totals['skipped']++;
            printf("  %2d. skip (exists) %s\n", $p['img']['n'], basename($p['file']));
        } else {
            $pending[] = $p;
        }
        $manifest[] = [
            'post'   => $post['num'],
            'slug'   => $post['slug'],
            'n'      => $p['img']['n'],
            'file'   => $p['rel'],
            'aspect' => $p['aspect'],
            'prompt' => $p['prompt'],
            'status' => $status,
        ];
    }

    if ($opts['dry-run']) {
        foreach ($pending as $p) {
            printf("  %2d. [%s] %s\n      → %s\n", $p['img']['n'], $p['aspect'], $p['prompt'], $p['rel']);
        }
        continue;
    }

    if ($pending === []) {
        continue;
    }

    // Generate the pending images for this post concurrently.
    $specs = array_map(static fn (array $p): array => [
        'prompt'            => $p['prompt'],
        'aspect_ratio'      => $p['aspect'],
        'sample_image_size' => $opts['size'],
    ], $pending);

    $results = $imageClient->generateBatch($specs);

    foreach ($pending as $i => $p) {
        $res = $results[$i] ?? ['ok' => false, 'error' => 'no result'];
        if (($res['ok'] ?? false) && isset($res['bytes'])) {
            file_put_contents($p['file'], $res['bytes']);
            $totals['generated']++;
            manifest_set_status($manifest, $post['num'], $p['img']['n'], 'generated');
            printf("  %2d. ok   %s (%.0f KB)\n", $p['img']['n'], basename($p['file']), strlen($res['bytes']) / 1024);
        } else {
            $totals['failed']++;
            $why = ($res['filtered'] ?? false) ? 'safety-filtered' : ($res['error'] ?? 'unknown error');
            manifest_set_status($manifest, $post['num'], $p['img']['n'], 'failed: ' . $why);
            printf("  %2d. FAIL %s — %s\n", $p['img']['n'], basename($p['file']), $why);
        }
    }
}

// ---------------------------------------------------------------------------
// Manifest + summary
// ---------------------------------------------------------------------------

if (!$opts['dry-run']) {
    $manifestPath = $opts['out'] . '/manifest.json';
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    echo "\nManifest: " . ltrim(str_replace($repoRoot, '', $manifestPath), '/') . "\n";
}

printf(
    "\nSummary: %d planned, %d generated, %d skipped, %d failed%s\n",
    $totals['planned'],
    $totals['generated'],
    $totals['skipped'],
    $totals['failed'],
    $opts['dry-run'] ? '  (dry run — no images written)' : ''
);

exit($totals['failed'] > 0 ? 1 : 0);

// ===========================================================================
// Helpers
// ===========================================================================

/**
 * Parse posts.md into a list of posts with their gallery images.
 *
 * @return list<array{num:int,title:string,slug:string,grade:string,images:list<array{n:int,text:string}>}>
 */
function parse_posts(string $md): array
{
    $posts = [];
    $cur = null;
    $section = null;         // 'grade' | 'gallery' | 'other' | null
    $curImage = null;        // index into $cur['images'] currently accumulating

    $flush = static function () use (&$posts, &$cur): void {
        if ($cur !== null && $cur['images'] !== []) {
            $cur['grade'] = trim(preg_replace('/\s+/', ' ', $cur['grade']));
            $posts[] = $cur;
        }
    };

    foreach (preg_split('/\r?\n/', $md) as $line) {
        // New post header: "### 1. Title …"
        if (preg_match('/^###\s+(\d+)\.\s+(.+?)\s*$/', $line, $m)) {
            $flush();
            $title = clean_title($m[2]);
            $cur = ['num' => (int) $m[1], 'title' => $title, 'slug' => slugify($title), 'grade' => '', 'images' => []];
            $section = null;
            $curImage = null;
            continue;
        }
        if ($cur === null) {
            continue;
        }
        // Field bullets at column 0.
        if (preg_match('/^-\s+\*\*Image grade:\*\*\s*(.*)$/', $line, $m)) {
            $section = 'grade';
            $cur['grade'] = $m[1];
            continue;
        }
        if (preg_match('/^-\s+\*\*Image gallery:\*\*/', $line)) {
            $section = 'gallery';
            continue;
        }
        if (preg_match('/^-\s+\*\*/', $line)) {   // any other field bullet
            $section = 'other';
            continue;
        }

        if ($section === 'grade') {
            // Continuation lines (indented, not a new bullet) extend the grade.
            if (trim($line) !== '' && preg_match('/^\s+\S/', $line)) {
                $cur['grade'] .= ' ' . trim($line);
            }
            continue;
        }

        if ($section === 'gallery') {
            // New numbered image: "  1. subject … | … | … | aspect"
            if (preg_match('/^\s+(\d+)\.\s+(.+)$/', $line, $m)) {
                $cur['images'][] = ['n' => (int) $m[1], 'text' => trim($m[2])];
                $curImage = count($cur['images']) - 1;
                continue;
            }
            // Continuation of the current image (indented wrap line).
            if ($curImage !== null && trim($line) !== '' && preg_match('/^\s+\S/', $line)) {
                $cur['images'][$curImage]['text'] .= ' ' . trim($line);
                continue;
            }
        }
    }
    $flush();

    // Normalise whitespace in image text.
    foreach ($posts as &$p) {
        foreach ($p['images'] as &$img) {
            $img['text'] = trim(preg_replace('/\s+/', ' ', $img['text']));
        }
    }
    return $posts;
}

/** Strip trailing showcase annotations like "⭐ heavy image showcase (12 images)". */
function clean_title(string $title): string
{
    $title = preg_split('/\s+⭐/u', $title)[0];
    return trim($title);
}

/**
 * Compose the Imagen text prompt from a gallery line + the post's shared grade.
 * Line = "subject … | location | camera & film | aspect ratio"; we keep the
 * first three fields and drop the aspect (passed separately as a ratio).
 */
function compose_prompt(string $text, string $grade): string
{
    $parts = array_map('trim', explode('|', $text));
    $subject = $parts[0] ?? $text;
    $location = $parts[1] ?? '';
    $camera = $parts[2] ?? '';

    $prompt = 'A fine-art photograph. ' . $subject;
    if ($location !== '') {
        $prompt .= '. Location: ' . $location;
    }
    if ($camera !== '') {
        $prompt .= '. Shot on ' . $camera;
    }
    if ($grade !== '') {
        $prompt .= '. Overall look: ' . rtrim($grade, '.') . '.';
    }
    return trim(preg_replace('/\s+/', ' ', $prompt));
}

/** Map the trailing "aspect ratio" field to an Imagen-supported ratio. */
function aspect_to_ratio(string $text): string
{
    $parts = explode('|', $text);
    $last = strtolower(trim(end($parts)));
    if (str_contains($last, 'portrait')) {
        return '3:4';
    }
    if (str_contains($last, 'square')) {
        return '1:1';
    }
    return '4:3'; // landscape (and default)
}

/** Short filename slug from the first words of the subject field. */
function short_slug(string $text): string
{
    $subject = explode('|', $text)[0];
    $words = array_slice(preg_split('/\s+/', trim($subject)), 0, 6);
    return slugify(implode(' ', $words));
}

function slugify(string $s): string
{
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'image';
}

/** Update the manifest entry's status in place. */
function manifest_set_status(array &$manifest, int $post, int $n, string $status): void
{
    foreach ($manifest as &$row) {
        if ($row['post'] === $post && $row['n'] === $n) {
            $row['status'] = $status;
            return;
        }
    }
}
