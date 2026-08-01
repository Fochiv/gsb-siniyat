<?php
/**
 * PHP built-in server router
 * Routes requests to proper PHP files or serves static assets
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
$staticDirs = ['/assets/', '/lang/'];
foreach ($staticDirs as $dir) {
    if (str_starts_with($uri, $dir)) {
        $file = __DIR__ . $uri;
        if (is_file($file)) {
            return false; // Let built-in server handle it
        }
    }
}

// Special files
if (in_array($uri, ['/manifest.json', '/sw.js', '/offline.html', '/favicon.ico'])) {
    $file = __DIR__ . $uri;
    if (is_file($file)) return false;
}

// Logo
if ($uri === '/logo.png') {
    $file = __DIR__ . '/logo.png';
    if (is_file($file)) return false;
}

// Remove trailing slash (except root)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

// Route to PHP file
$phpFile = __DIR__ . $uri;
if (is_dir($phpFile)) {
    $phpFile .= '/index.php';
} elseif (!str_ends_with($phpFile, '.php')) {
    $phpFile .= '.php';
}

if (is_file($phpFile)) {
    include $phpFile;
    return true;
}

// 404
http_response_code(404);
echo '<h1>404 — Page non trouvée</h1>';
echo '<p><a href="/">Retour à l\'accueil</a></p>';
return true;
