<?php

require_once __DIR__ . '/../config/database.php';

$winner = (int) $_POST['winner_id'];
$loser  = (int) $_POST['loser_id'];

if (!$winner || !$loser || $winner === $loser) {
    die("Invalid vote.");
}

$pdo->beginTransaction();

try {

    // Fetch current ratings
    $stmt = $pdo->prepare("
        SELECT id, elo_rating
        FROM pokemon
        WHERE id IN (:winner, :loser)
    ");

    $stmt->execute([
        'winner' => $winner,
        'loser'  => $loser
    ]);

    $ratings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (count($ratings) !== 2) {
        throw new Exception("Pokémon not found.");
    }

    $Ra = (int) $ratings[$winner];
    $Rb = (int) $ratings[$loser];

    // ELO calculation
    $K = 32;

    $Ea = 1 / (1 + pow(10, ($Rb - $Ra) / 400));
    $Eb = 1 - $Ea;

    $Ra_new = round($Ra + $K * (1 - $Ea));
    $Rb_new = round($Rb + $K * (0 - $Eb));

    // Update ratings
    $update = $pdo->prepare("
        UPDATE pokemon
        SET elo_rating = :rating
        WHERE id = :id
    ");

    $update->execute([
        'rating' => $Ra_new,
        'id'     => $winner
    ]);

    $update->execute([
        'rating' => $Rb_new,
        'id'     => $loser
    ]);

    // Optional: still track vote_count
    $pdo->prepare("
        UPDATE pokemon
        SET vote_count = vote_count + 1
        WHERE id = :id
    ")->execute(['id' => $winner]);

    // Insert vote record
    $insertVote = $pdo->prepare("
        INSERT INTO votes (winner_id, loser_id)
        VALUES (:winner, :loser)
    ");

    $insertVote->execute([
        'winner' => $winner,
        'loser'  => $loser
    ]);

    $pdo->commit();

} catch (Exception $e) {

    $pdo->rollBack();
    die("Vote failed.");
}

header("Location: index.php");
exit;

