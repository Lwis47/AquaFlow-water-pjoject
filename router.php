<?php

declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__ . $path);
$relative = $file === false ? $path : str_replace('\\', '/', substr($file, strlen(__DIR__)));

if (($file !== false && !str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR))
    || preg_match('#(?:^|/)\.[^/]+|^/(?:backend/(?:storage|database|src)|tmp)(?:/|$)|\.(?:sqlite(?:3)?(?:-wal|-shm)?|sql|ini|log)$#i', $relative)) {
    http_response_code(404);
    echo 'Not found.';
    return true;
}

return false;
