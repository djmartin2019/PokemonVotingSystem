<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/seo.php';

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
<html lang="en">
<head>
    <?= renderSeoHead([
        'title' => 'PokeVote - Vote for Your Favorite Pokemon',
        'description' => 'Choose between two Pokemon, cast your vote, and help shape the live rankings. Data comes from PokeAPI. Fan project, not affiliated with Nintendo or The Pokemon Company.',
        'path' => '/',
    ]) ?>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page-vote">

<h1>Which Pokémon Do You Prefer?</h1>

<?php
$first = $pokemon[0];
$second = $pokemon[1];
?>

<div class="container">

    <form method="POST" action="vote.php" class="vote-form">
        <input type="hidden" name="winner_id" value="<?= $first['id'] ?>">
        <input type="hidden" name="loser_id" value="<?= $second['id'] ?>">
        <button class="card" type="submit" aria-label="Vote for <?= htmlspecialchars($first['name']) ?>">
            <img src="<?= htmlspecialchars($first['sprite_url']) ?>" alt="<?= htmlspecialchars($first['name']) ?>" loading="lazy">
            <h3><?= htmlspecialchars(ucfirst($first['name'])) ?></h3>
        </button>
    </form>

    <div class="vs-divider" aria-hidden="true">
        <span class="vs-text">VS</span>
    </div>

    <form method="POST" action="vote.php" class="vote-form">
        <input type="hidden" name="winner_id" value="<?= $second['id'] ?>">
        <input type="hidden" name="loser_id" value="<?= $first['id'] ?>">
        <button class="card" type="submit" aria-label="Vote for <?= htmlspecialchars($second['name']) ?>">
            <img src="<?= htmlspecialchars($second['sprite_url']) ?>" alt="<?= htmlspecialchars($second['name']) ?>" loading="lazy">
            <h3><?= htmlspecialchars(ucfirst($second['name'])) ?></h3>
        </button>
    </form>

</div>

<p class="leaderboard-link"><a href="leaderboard.php">View Leaderboard →</a></p>

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
