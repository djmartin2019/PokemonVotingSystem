<?php

/**
 * Builds a resized WebP sprite on its first request. Apache only routes here
 * when the file doesn't exist yet. Falls back to the original image on failure.
 */

require_once __DIR__ . '/../app/Sprite.php';

$size = (string) ($_GET['size'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

if (!isset(Sprite::SIZES[$size]) || $id <= 0) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$stmt = $pdo->prepare("SELECT sprite_url FROM pokemon WHERE id = :id");
$stmt->execute(['id' => $id]);
$source = $stmt->fetchColumn();

if (!is_string($source) || !str_starts_with($source, 'https://')) {
    http_response_code(404);
    exit;
}

$webp = Sprite::build($source, $id, $size);

if ($webp === null) {
    header('Cache-Control: public, max-age=300');
    header('Location: ' . $source, true, 302);
    exit;
}

header('Content-Type: image/webp');
header('Content-Length: ' . strlen($webp));
header('Cache-Control: public, max-age=31536000, immutable');
echo $webp;
