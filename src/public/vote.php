<?php

require_once __DIR__ . '/../config/database.php';

$winner = (int) $_POST['winner_id'];
$loser  = (int) $_POST['loser_id'];

if (!$winner || !$loser) {
    die("Invalid vote.");
}

$pdo->beginTransaction();

// Insert vote record
$stmt = $pdo->prepare("
    INSERT INTO votes (winner_id, loser_id)
    VALUES (:winner, :loser)
    ");

$stmt->execute([
    'winner'    => $winner,
    'loser'     => $loser
]);

// Update vote count
$pdo->prepare("
    UPDATE pokemon
    SET vote_count = vote_count + 1
    WHERE id = :id
    ")->execute(['id' => $winner]);

$pdo->commit();

// Redirect back
header("Location: index.php");
exit;
