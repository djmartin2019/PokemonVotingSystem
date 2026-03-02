<?php

require_once __DIR__ . '/../src/config/database.php';

const POKEAPI_LIST_URL = 'https://pokeapi.co/api/v2/pokemon?limit=2000';
const REQUEST_TIMEOUT_SECONDS = 20;
const MAX_RETRIES = 3;
const RETRY_DELAY_SECONDS = 1;

function fetchJson(string $url): ?array
{
    $attempt = 0;

    while ($attempt < MAX_RETRIES) {
        $attempt++;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => REQUEST_TIMEOUT_SECONDS,
                'header' => "Accept: application/json\r\nUser-Agent: pokevote-seeder/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response !== false) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if ($attempt < MAX_RETRIES) {
            sleep(RETRY_DELAY_SECONDS);
        }
    }

    return null;
}

function pokemonIdFromUrl(string $url): ?int
{
    if (preg_match('#/pokemon/(\d+)/?$#', $url, $matches) !== 1) {
        return null;
    }

    return (int)$matches[1];
}

function generationFromId(int $id): int
{
    if ($id <= 151) {
        return 1;
    }
    if ($id <= 251) {
        return 2;
    }
    if ($id <= 386) {
        return 3;
    }
    if ($id <= 493) {
        return 4;
    }
    if ($id <= 649) {
        return 5;
    }
    if ($id <= 721) {
        return 6;
    }
    if ($id <= 809) {
        return 7;
    }
    if ($id <= 905) {
        return 8;
    }

    return 9;
}

echo "Loading existing Pokemon IDs from DB...\n";
$existingIds = [];
foreach ($pdo->query('SELECT id FROM pokemon') as $row) {
    $existingIds[(int)$row['id']] = true;
}
echo 'Existing count: ' . count($existingIds) . "\n";

echo "Fetching Pokemon list from PokeAPI...\n";
$listPayload = fetchJson(POKEAPI_LIST_URL);
if (!$listPayload || !isset($listPayload['results']) || !is_array($listPayload['results'])) {
    die("Failed to fetch Pokemon list from PokeAPI.\n");
}

$allPokemon = [];
foreach ($listPayload['results'] as $entry) {
    if (!isset($entry['url'], $entry['name'])) {
        continue;
    }

    $id = pokemonIdFromUrl($entry['url']);
    if ($id === null) {
        continue;
    }

    $allPokemon[] = [
        'id' => $id,
        'name' => $entry['name'],
    ];
}

usort(
    $allPokemon,
    static fn(array $a, array $b): int => $a['id'] <=> $b['id']
);

$insert = $pdo->prepare(
    'INSERT INTO pokemon (id, name, sprite_url, generation)
     VALUES (:id, :name, :sprite_url, :generation)
     ON CONFLICT (id) DO NOTHING'
);

$inserted = 0;
$skippedExisting = 0;
$skippedFailedFetch = 0;

echo 'Discovered ' . count($allPokemon) . " Pokemon entries from API.\n";

foreach ($allPokemon as $entry) {
    $id = $entry['id'];
    $name = $entry['name'];

    if (isset($existingIds[$id])) {
        $skippedExisting++;
        continue;
    }

    $details = fetchJson("https://pokeapi.co/api/v2/pokemon/{$id}");
    if (!$details) {
        $skippedFailedFetch++;
        echo "Failed to fetch details for #{$id} ({$name})\n";
        continue;
    }

    $spriteUrl = $details['sprites']['other']['official-artwork']['front_default']
        ?? $details['sprites']['front_default']
        ?? null;

    $insert->execute([
        'id' => $id,
        'name' => $name,
        'sprite_url' => $spriteUrl,
        'generation' => generationFromId($id),
    ]);

    if ($insert->rowCount() > 0) {
        $inserted++;
        echo "Inserted #{$id} {$name}\n";
        $existingIds[$id] = true;
    } else {
        // This keeps the script idempotent even if another run inserted the row first.
        $skippedExisting++;
    }
}

echo "\nDone.\n";
echo "Inserted: {$inserted}\n";
echo "Already existed / conflict skipped: {$skippedExisting}\n";
echo "Failed detail fetches: {$skippedFailedFetch}\n";
