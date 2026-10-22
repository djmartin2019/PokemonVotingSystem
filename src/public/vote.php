<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Elo.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php');
    exit;
}

$winner = (int) ($_POST['winner_id'] ?? 0);
$loser  = (int) ($_POST['loser_id'] ?? 0);

if (!$winner || !$loser || $winner === $loser) {
    die("Invalid vote.");
}

$pdo->beginTransaction();

try {

    // Lock both rows so two simultaneous votes can't overwrite each other
    $stmt = $pdo->prepare("
        SELECT id, elo_rating
        FROM pokemon
        WHERE id IN (:winner, :loser)
        FOR UPDATE
    ");

    $stmt->execute([
        'winner' => $winner,
        'loser'  => $loser
    ]);

    $ratings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (count($ratings) !== 2) {
        throw new Exception("Pokémon not found.");
    }

    $winnerBefore = (int) $ratings[$winner];
    $loserBefore  = (int) $ratings[$loser];
    [$winnerAfter, $loserAfter] = Elo::play($winnerBefore, $loserBefore);

    $pdo->prepare("
        UPDATE pokemon
        SET elo_rating = :rating, vote_count = vote_count + 1
        WHERE id = :id
    ")->execute(['rating' => $winnerAfter, 'id' => $winner]);

    $pdo->prepare("
        UPDATE pokemon
        SET elo_rating = :rating
        WHERE id = :id
    ")->execute(['rating' => $loserAfter, 'id' => $loser]);

    // Record the vote along with the ratings it moved, for the stats charts
    $pdo->prepare("
        INSERT INTO votes (winner_id, loser_id, winner_elo_before, loser_elo_before, winner_elo_after, loser_elo_after)
        VALUES (:winner, :loser, :wb, :lb, :wa, :la)
    ")->execute([
        'winner' => $winner,
        'loser'  => $loser,
        'wb'     => $winnerBefore,
        'lb'     => $loserBefore,
        'wa'     => $winnerAfter,
        'la'     => $loserAfter,
    ]);

    $pdo->commit();

} catch (Exception $e) {

    $pdo->rollBack();
    die("Vote failed.");
}

header("Location: index.php");
exit;
