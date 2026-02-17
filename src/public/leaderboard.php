<?php

require_once __DIR__ . '/../config/database.php';

$stmt = $pdo->query("
    SELECT name, sprite_url, vote_count, elo_rating
    FROM pokemon
    ORDER BY elo_rating DESC
    LIMIT 20
    ");

$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Leaderboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<h1 class="leaderboard-title">Top 20 Pokémon</h1>

<div class="leaderboard">
<?php foreach ($leaders as $index => $p): ?>
    <div class="leader-row">
        <div class="rank">#<?= $index + 1 ?></div>
        <img src="<?= $p['sprite_url'] ?>" alt="<?= $p['name'] ?>">
        <div class="leader-info">
            <span class="leader-name"><?= ucfirst($p['name']) ?></span>
            <span class="leader-votes">Elo Rating: <?= $p['elo_rating'] ?></span>
            <span class="leader-votes"><?= $p['vote_count'] ?> votes</span>
        </div>
    </div>
<?php endforeach; ?>
</div>

<p class="back-link"><a href="index.php">← Back To Voting</a></p>

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

