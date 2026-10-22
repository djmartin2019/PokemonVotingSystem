<?php

/**
 * Adds Elo history columns to `votes` and backfills them by replaying every
 * vote in order. Safe to run more than once.
 *
 * Usage: php scripts/migrate.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Elo.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

echo "Adding Elo history columns...\n";

$pdo->exec("
    ALTER TABLE votes
        ADD COLUMN IF NOT EXISTS winner_elo_before INT,
        ADD COLUMN IF NOT EXISTS loser_elo_before INT,
        ADD COLUMN IF NOT EXISTS winner_elo_after INT,
        ADD COLUMN IF NOT EXISTS loser_elo_after INT
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_votes_winner ON votes(winner_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_votes_loser ON votes(loser_id)");

$missing = (int) $pdo->query("SELECT COUNT(*) FROM votes WHERE winner_elo_after IS NULL")->fetchColumn();
if ($missing === 0) {
    echo "History already complete. Nothing to backfill.\n";
    exit(0);
}

echo "Replaying all votes to backfill {$missing} rows...\n";

$ratings = [];
foreach ($pdo->query("SELECT id FROM pokemon") as $row) {
    $ratings[(int) $row['id']] = Elo::START;
}

$votes = $pdo->query("SELECT id, winner_id, loser_id FROM votes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$update = $pdo->prepare("
    UPDATE votes
    SET winner_elo_before = :wb, loser_elo_before = :lb,
        winner_elo_after = :wa, loser_elo_after = :la
    WHERE id = :id
");

$pdo->beginTransaction();
foreach ($votes as $i => $vote) {
    $w = (int) $vote['winner_id'];
    $l = (int) $vote['loser_id'];
    $wb = $ratings[$w] ?? Elo::START;
    $lb = $ratings[$l] ?? Elo::START;
    [$wa, $la] = Elo::play($wb, $lb);

    $update->execute(['wb' => $wb, 'lb' => $lb, 'wa' => $wa, 'la' => $la, 'id' => $vote['id']]);

    $ratings[$w] = $wa;
    $ratings[$l] = $la;

    if (($i + 1) % 5000 === 0) {
        echo '  ' . ($i + 1) . " / " . count($votes) . "\n";
    }
}
$pdo->commit();

// The replay should land on the ratings stored in `pokemon`. Report any drift.
$drift = 0;
foreach ($pdo->query("SELECT id, elo_rating FROM pokemon") as $row) {
    if (($ratings[(int) $row['id']] ?? Elo::START) !== (int) $row['elo_rating']) {
        $drift++;
    }
}

echo "Backfilled " . count($votes) . " votes.\n";
echo $drift === 0
    ? "Replay matches current ratings exactly.\n"
    : "Warning: {$drift} Pokémon have a current rating that differs from the replay. Charts use the replayed history.\n";
