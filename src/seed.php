<?php

require_once __DIR__ . '/config/database.php';

echo "Seeding Gen 1–4 Pokémon...\n";

$limit = 100;
$total = 493;
$delayMicroseconds = 400000; // 0.4s
$maxRetries = 2;

for ($offset = 0; $offset < $total; $offset += $limit) {

    $listUrl = "https://pokeapi.co/api/v2/pokemon?limit=$limit&offset=$offset";

    echo "Fetching list batch offset $offset...\n";

    $listResponse = file_get_contents($listUrl);

    if (!$listResponse) {
        echo "Failed to fetch list batch.\n";
        break;
    }

    $listData = json_decode($listResponse, true);

    foreach ($listData['results'] as $pokemon) {

        $url = $pokemon['url'];

        preg_match('/\/pokemon\/(\d+)\//', $url, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id > 493 || $id === 0) {
            continue;
        }

        // Skip if exists
        $check = $pdo->prepare("SELECT 1 FROM pokemon WHERE id = :id");
        $check->execute(['id' => $id]);

        if ($check->fetch()) {
            echo "Skipping {$pokemon['name']} (#$id)\n";
            continue;
        }

        $attempt = 0;
        $data = null;

        while ($attempt < $maxRetries) {
            $response = @file_get_contents($url);

            if ($response) {
                $data = json_decode($response, true);
                break;
            }

            $attempt++;
            echo "Retry $attempt for {$pokemon['name']}...\n";
            usleep(800000);
        }

        if (!$data) {
            echo "Failed permanently: {$pokemon['name']}\n";
            continue;
        }

        $generation = match (true) {
            $id <= 151 => 1,
            $id <= 251 => 2,
            $id <= 386 => 3,
            default => 4
        };

        $sprite = $data['sprites']['other']['official-artwork']['front_default']
            ?? $data['sprites']['front_default']
            ?? null;

        $insert = $pdo->prepare("
            INSERT INTO pokemon (id, name, sprite_url, generation)
            VALUES (:id, :name, :sprite_url, :generation)
        ");

        $insert->execute([
            'id' => $id,
            'name' => $data['name'],
            'sprite_url' => $sprite,
            'generation' => $generation
        ]);

        echo "Inserted {$data['name']} (Gen $generation)\n";

        usleep($delayMicroseconds);
    }
}

echo "Seeding complete.\n";
