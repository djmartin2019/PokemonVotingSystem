<?php

require_once __DIR__ . '/config/database.php';

$jsonPath = __DIR__ . '/data/pokemon_gen1_4.json';

if (!file_exists($jsonPath)) {
    die("JSON file not found.\n");
}

$data = json_decode(file_get_contents($jsonPath), true);

if (!$data) {
    die("Invalid JSON.\n");
}

echo "Seeding from JSON...\n";

foreach ($data as $pokemon) {
    // Skip if exists
    $check = $pdo->prepare("SELECT 1 FROM pokemon WHERE id = :id");
    $check->execute(['id' => $pokemon['id']]);

    if ($check->fetch()) {
        echo "Skipping {$pokemon['name']} (exists)\n";
        continue;
    }

    $insert = $pdo->prepare("
        INSERT INTO pokemon (id, name, sprite_url, generation)
        VALUES (:id, :name, :sprite_url, :generation)
    ");

    $insert->execute([
        'id' => $pokemon['id'],
        'name' => $pokemon['name'],
        'sprite_url' => $pokemon['sprite_url'],
        'generation' => $pokemon['generation']
    ]);

    echo "Inserted {$pokemon['name']}\n";
}

echo "Done.\n";
