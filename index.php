<?php
$reactApp = __DIR__ . '/react-app/index.html';

if (!is_file($reactApp)) {
    http_response_code(500);
    echo 'React website not found.';
    exit;
}

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$baseHref = ($basePath === '' || $basePath === '.') ? '/' : $basePath . '/';
$html = file_get_contents($reactApp);
if ($html === false) {
    http_response_code(500);
    echo 'React website could not be loaded.';
    exit;
}
$baseTag = '<base href="' . htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') . '">';
if (stripos($html, '<base ') === false) {
    $html = preg_replace('/<head(\s*[^>]*)>/i', '<head$1>' . "\n    " . $baseTag, $html, 1) ?? $html;
}

header('Content-Type: text/html; charset=UTF-8');
$lastModified = filemtime($reactApp) ?: time();
$etag = '"' . md5($html) . '"';
header('Cache-Control: public, max-age=60');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: ' . $etag);

$ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$ifModifiedSince = strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) ?: 0;
if ($ifNoneMatch === $etag || $ifModifiedSince >= $lastModified) {
    http_response_code(304);
    exit;
}

echo $html;
