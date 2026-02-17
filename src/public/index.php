<?php

require_once __DIR__ . '/../config/database.php';

// Get two random Pokémon
$stmt = $pdo->query("
    SELECT id, name, sprite_url
    FROM pokemon
    ORDER BY RANDOM()
    LIMIT 2
");

$pokemon = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($pokemon) < 2) {
    die("Not enough Pokémon in DB.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pokémon Vote</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<h1>Which Pokémon Do You Prefer?</h1>

<?php
$first = $pokemon[0];
$second = $pokemon[1];
?>

<div class="container">

    <form method="POST" action="vote.php">
        <input type="hidden" name="winner_id" value="<?= $first['id'] ?>">
        <input type="hidden" name="loser_id" value="<?= $second['id'] ?>">
        <button class="card" type="submit">
            <img src="<?= $first['sprite_url'] ?>" alt="<?= $first['name'] ?>">
            <h3><?= ucfirst($first['name']) ?></h3>
        </button>
    </form>

    <form method="POST" action="vote.php">
        <input type="hidden" name="winner_id" value="<?= $second['id'] ?>">
        <input type="hidden" name="loser_id" value="<?= $first['id'] ?>">
        <button class="card" type="submit">
            <img src="<?= $second['sprite_url'] ?>" alt="<?= $second['name'] ?>">
            <h3><?= ucfirst($second['name']) ?></h3>
        </button>
    </form>

</div>

<p><a href="leaderboard.php">View Leaderboard</a></p>

<footer class="site-footer">
    <p>
        Pokémon data provided by
        <a href="https://pokeapi.co/" target="_blank" rel="noopener">
            PokéAPI
        </a>
        • Not affiliated with Nintendo or The Pokémon Company
    </p>
</footer>

</body>
</html>
