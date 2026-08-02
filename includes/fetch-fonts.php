<?php

/**
 * Composer hook: keeps the locally hosted Noto Sans SC webfonts up to date.
 *
 * Runs automatically after `composer install` and `composer update`
 * (see the "scripts" section in composer.json). It:
 *
 *   1. creates views/assets/fonts/ (and views/assets/css/ if missing);
 *   2. fetches the variable Noto Sans SC (wght 100-900) font list from
 *      Google Fonts' css2 API, which serves the latest version split into
 *      unicode-range subsets;
 *   3. downloads every subset as woff2 into views/assets/fonts/;
 *   4. rewrites the @font-face rules to point at the local files and writes
 *      them to views/assets/css/noto-face.css;
 *   5. removes stale woff2 files left over from older font versions.
 *
 * Mirror fallback: Google also serves these assets on their mainland China
 * domains (fonts.googleapis.cn / fonts.gstatic.cn). The script tries the
 * primary .com mirror first, then falls back to .cn — both for the css2
 * font list and for individual subset downloads.
 *
 * The script never fails a Composer run: if the network is unreachable or a
 * download fails, it prints a warning and exits 0, leaving existing fonts
 * untouched so the application still works.
 */

// --- configuration ---
$projectRoot = dirname(__DIR__);
$fontsDir    = $projectRoot . '/views/assets/fonts';
$cssDir      = $projectRoot . '/views/assets/css';
$cssFile     = $cssDir . '/noto-face.css';

$fontQuery = 'css2?family=Noto+Sans+SC:wght@100..900&display=swap';

// Mirrors are tried in order: the primary .com pair first, then the mainland
// China pair. Each entry maps its css2 endpoint to the gstatic host that
// serves the subset files referenced by that endpoint.
$mirrors = [
    [
        'cssUrl'   => 'https://fonts.googleapis.com/' . $fontQuery,
        'fileHost' => 'fonts.gstatic.com',
    ],
    [
        'cssUrl'   => 'https://fonts.googleapis.cn/' . $fontQuery,
        'fileHost' => 'fonts.gstatic.cn',
    ],
];

$userAgent  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
$curlFound  = function_exists('curl_init');
$fopenFound = (bool) ini_get('allow_url_fopen');

function fetchFontCss(string $url, string $userAgent): ?string
{
    global $curlFound, $fopenFound;

    if ($curlFound) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => $userAgent,
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body !== false && $status === 200) {
            return $body;
        }
        fwrite(STDERR, '  [warn] curl request failed' . ($error ? ': ' . $error : ' (HTTP ' . $status . ')') . PHP_EOL);
        return null;
    }

    if ($fopenFound) {
        $context = stream_context_create([
            'http' => [
                'timeout'   => 60,
                'user_agent' => $userAgent,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body !== false) {
            return $body;
        }
        fwrite(STDERR, '  [warn] file_get_contents() failed for ' . $url . PHP_EOL);
        return null;
    }

    fwrite(STDERR, '  [warn] neither curl nor allow_url_fopen is available; cannot download fonts.' . PHP_EOL);
    return null;
}

function downloadFontFile(string $url, string $destination, string $userAgent): bool
{
    $body = fetchFontCss($url, $userAgent);
    if ($body === null) {
        return false;
    }

    $temporary = $destination . '.tmp';
    if (file_put_contents($temporary, $body) === false) {
        return false;
    }

    return rename($temporary, $destination);
}

function fontFileName(string $url): string
{
    return basename(parse_url($url, PHP_URL_PATH));
}

function swapFileHost(string $url, string $host): string
{
    return preg_replace('#^https?://[^/]+/#', 'https://' . $host . '/', $url);
}

// --- main ---
echo 'Fetching latest Noto Sans SC font list from Google Fonts…' . PHP_EOL;

if (!$curlFound && !$fopenFound) {
    fwrite(STDERR, '  [warn] neither curl nor allow_url_fopen is available; keeping existing fonts.' . PHP_EOL);
    exit(0);
}

$css        = null;
$usedMirror = null;

foreach ($mirrors as $mirror) {
    echo '  Trying ' . $mirror['cssUrl'] . '…' . PHP_EOL;
    $css = fetchFontCss($mirror['cssUrl'], $userAgent);
    if ($css !== null) {
        $usedMirror = $mirror;
        break;
    }
}

if ($css === null) {
    fwrite(STDERR, '  [warn] could not reach any Google Fonts mirror; keeping existing fonts. Run `composer install` again later to update them.' . PHP_EOL);
    exit(0);
}

preg_match_all('#url\((https://fonts\.gstatic\.(?:com|cn)/[^)]+\.woff2)\)#', $css, $matches);
$fontUrls = array_values(array_unique($matches[1]));

if (count($fontUrls) === 0) {
    fwrite(STDERR, '  [warn] font list contained no woff2 files; keeping existing fonts.' . PHP_EOL);
    exit(0);
}

if (!is_dir($fontsDir) && !mkdir($fontsDir, 0755, true)) {
    fwrite(STDERR, '  [error] could not create font directory: ' . $fontsDir . PHP_EOL);
    exit(0);
}

$downloaded = [];
$failed     = 0;

foreach ($fontUrls as $index => $url) {
    $name = fontFileName($url);
    $dest = $fontsDir . DIRECTORY_SEPARATOR . $name;

    if (file_exists($dest) && filesize($dest) > 0) {
        $downloaded[] = $name;
        continue;
    }

    echo sprintf('  [%d/%d] %s', $index + 1, count($fontUrls), $name) . PHP_EOL;
    if (downloadFontFile($url, $dest, $userAgent)) {
        $downloaded[] = $name;
        continue;
    }

    // Retry the subset from the mirror's own file host (e.g. a .com URL
    // served by the .cn list, or a direct .com failure on a China network).
    $fallbackUrl = swapFileHost($url, $usedMirror['fileHost']);
    if ($fallbackUrl !== $url && downloadFontFile($fallbackUrl, $dest, $userAgent)) {
        echo '  [fallback] re-downloaded from ' . $fallbackUrl . PHP_EOL;
        $downloaded[] = $name;
        continue;
    }

    $failed++;
    fwrite(STDERR, '  [warn] failed to download ' . $name . PHP_EOL);
}

$localCss = preg_replace_callback(
    '#url\((https://fonts\.gstatic\.(?:com|cn)/[^)]+\.woff2)\)#',
    function (array $match): string {
        return "url('../fonts/" . fontFileName($match[1]) . "')";
    },
    $css
);

if (!is_dir($cssDir) && !mkdir($cssDir, 0755, true)) {
    fwrite(STDERR, '  [error] could not create CSS directory: ' . $cssDir . PHP_EOL);
    exit(0);
}

$localCss = "/* Generated by scripts/fetch-fonts.php — do not edit. */\n" . $localCss;
if (file_put_contents($cssFile, $localCss) === false) {
    fwrite(STDERR, '  [error] could not write ' . $cssFile . PHP_EOL);
    exit(0);
}

if ($failed === 0) {
    foreach (glob($fontsDir . '/*.woff2') as $stale) {
        if (!in_array(basename($stale), $downloaded, true)) {
            unlink($stale);
            echo '  [clean] removed stale ' . basename($stale) . PHP_EOL;
        }
    }
}

echo PHP_EOL;
echo 'Font list served by ' . $usedMirror['cssUrl'] . PHP_EOL;
echo sprintf('Done: %d font subsets ready in %s', count($downloaded), $fontsDir) . PHP_EOL;
echo 'Note: @font-face rules written to ' . $cssFile . PHP_EOL;

if ($failed > 0) {
    fwrite(STDERR, '  [warn] ' . $failed . ' subset(s) failed to download; the CSS references only the files that were downloaded.' . PHP_EOL);
}

exit(0);
