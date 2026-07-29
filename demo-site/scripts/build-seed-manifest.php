<?php
declare(strict_types=1);

/**
 * build-seed-manifest.php — extract title + paragraph + slug + image dir for
 * every post in plan/posts.md, and write plan/seed-posts.json. This is the
 * input for seed-posts.php (which runs on the demo server under wp eval-file).
 *
 *   php demo-site/scripts/build-seed-manifest.php
 */

$demoRoot = dirname(__DIR__);
$postsMd = $demoRoot . '/plan/posts.md';
$out = $demoRoot . '/plan/seed-posts.json';

$md = file_get_contents($postsMd);
$posts = [];
$cur = null;
$inPara = false;

$flush = static function () use (&$posts, &$cur): void {
    if ($cur !== null) {
        $cur['paragraph'] = trim(preg_replace('/\s+/', ' ', $cur['paragraph']));
        $posts[] = $cur;
    }
};

foreach (preg_split('/\r?\n/', $md) as $line) {
    if (preg_match('/^###\s+(\d+)\.\s+(.+?)\s*$/', $line, $m)) {
        $flush();
        $title = trim(preg_split('/\s+⭐/u', $m[2])[0]);
        $slug = slugify($title);
        $num = (int) $m[1];
        $cur = [
            'num'       => $num,
            'title'     => $title,
            'slug'      => $slug,
            'dir'       => sprintf('%02d-%s', $num, $slug),
            'paragraph' => '',
        ];
        $inPara = false;
        continue;
    }
    if ($cur === null) {
        continue;
    }
    if (preg_match('/^-\s+\*\*Text paragraph:\*\*\s*(.*)$/', $line, $m)) {
        $inPara = true;
        $cur['paragraph'] = $m[1];
        continue;
    }
    if (preg_match('/^-\s+\*\*/', $line)) {   // any other field bullet ends the paragraph
        $inPara = false;
        continue;
    }
    if ($inPara && trim($line) !== '' && preg_match('/^\s+\S/', $line)) {
        $cur['paragraph'] .= ' ' . trim($line);
    }
}
$flush();

// Sanity: confirm each image dir exists and count files.
foreach ($posts as &$p) {
    $dir = $demoRoot . '/images/' . $p['dir'];
    $files = glob($dir . '/*.jpg') ?: [];
    $p['image_count'] = count($files);
    if ($files === []) {
        fwrite(STDERR, "WARNING: no images for {$p['dir']}\n");
    }
}
unset($p);

file_put_contents($out, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
printf("Wrote %s (%d posts)\n", ltrim(str_replace(dirname($demoRoot), '', $out), '/'), count($posts));
foreach ($posts as $p) {
    printf("  #%2d %-40s %2d imgs\n", $p['num'], $p['slug'], $p['image_count']);
}

function slugify(string $s): string
{
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'post';
}
