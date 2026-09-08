<?php

declare(strict_types=1);

/**
 * Local Development Server Router
 * Usage: php -S localhost:8000 server.php
 * Simulates Apache rewrites and subdirectory handling for local testing.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// Strip /LC prefix if testing simulated subdirectory locally
$basePath = '/LC';
if (str_starts_with($uri, $basePath . '/')) {
    $relativePath = substr($uri, strlen($basePath));
} elseif ($uri === $basePath) {
    $relativePath = '/';
} else {
    $relativePath = $uri;
}

// Emulate Apache security rules: Deny access to sensitive files and directories
if (
    preg_match('#^/(\.|\.env|app|config|database|storage|vendor|tests|bin)(/|$)#i', $relativePath) ||
    preg_match('#\.(env|json|lock|sql|md|log|yml|yaml|ini|sh|bat)$#i', $relativePath)
) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo '403 Forbidden: Access Denied';
    exit;
}

// Serve public static assets if they exist
$publicFile = __DIR__ . '/public' . $relativePath;
if ($relativePath !== '/' && file_exists($publicFile) && !is_dir($publicFile)) {
    $ext = pathinfo($publicFile, PATHINFO_EXTENSION);
    $mime = match (strtolower($ext)) {
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'txt' => 'text/plain; charset=UTF-8',
        default => 'application/octet-stream',
    };
    header("Content-Type: {$mime}");
    readfile($publicFile);
    exit;
}

// Forward to public front controller
require_once __DIR__ . '/public/index.php';
