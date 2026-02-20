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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Top 20 Pokémon ranked by ELO rating">
    <title>Leaderboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page-leaderboard">

<h1 class="leaderboard-title">Top 20 Pokémon</h1>

<div class="leaderboard">
<?php foreach ($leaders as $index => $p): ?>
    <div class="leader-row">
        <div class="leader-header">
            <div class="rank">#<?= $index + 1 ?></div>
            <span class="leader-name"><?= htmlspecialchars(ucfirst($p['name'])) ?></span>
        </div>
        <div class="leader-content">
            <img src="<?= htmlspecialchars($p['sprite_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
            <div class="leader-info">
                <span class="leader-votes">Elo Rating: <?= htmlspecialchars($p['elo_rating']) ?></span>
                <span class="leader-votes"><?= htmlspecialchars($p['vote_count']) ?> votes</span>
            </div>
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

