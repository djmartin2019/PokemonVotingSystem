<?php

/**
 * Builds every resized sprite ahead of time so no visitor waits for one.
 * Skips sprites that already exist. Safe to run repeatedly.
 *
 * Usage: php scripts/warm_sprites.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Sprite.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$rows = $pdo->query("SELECT id, sprite_url FROM pokemon WHERE sprite_url IS NOT NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$built = 0;
$skipped = 0;
$failed = [];

foreach ($rows as $row) {
    $id = (int) $row['id'];
    foreach (array_keys(Sprite::SIZES) as $size) {
        if (is_file(Sprite::path($id, $size))) {
            $skipped++;
            continue;
        }
        if (Sprite::build($row['sprite_url'], $id, $size) === null || !is_file(Sprite::path($id, $size))) {
            $failed[] = "$id/$size";
            continue;
        }
        $built++;
    }
    if ($built && $built % 100 === 0) {
        echo "  built {$built}...\n";
    }
}

echo "Sprites: {$built} built, {$skipped} already cached, " . count($failed) . " failed.\n";
if ($failed) {
    echo "Failed (pages will fall back to the original image): " . implode(', ', array_slice($failed, 0, 20)) . (count($failed) > 20 ? ' ...' : '') . "\n";
}
