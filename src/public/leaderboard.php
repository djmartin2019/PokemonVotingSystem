<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/layout.php';

$stmt = $pdo->query("
    SELECT id, name, sprite_url, generation, vote_count, elo_rating
    FROM pokemon
    ORDER BY elo_rating DESC, vote_count DESC
    LIMIT 20
");

$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalVotes = (int) $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$totalPokemon = (int) $pdo->query("SELECT COUNT(*) FROM pokemon")->fetchColumn();

$podium = array_slice($leaders, 0, 3);
$rest = array_slice($leaders, 3);

// Scale the Elo bars between the lowest and highest rating shown
$elos = array_map(fn ($p) => (int) $p['elo_rating'], $leaders);
$maxElo = $elos ? max($elos) : 0;
$minElo = $elos ? min($elos) : 0;
$barWidth = function (int $elo) use ($maxElo, $minElo): int {
    if ($maxElo === $minElo) {
        return 100;
    }
    return (int) round(18 + 82 * ($elo - $minElo) / ($maxElo - $minElo));
};

$medals = ['gold', 'silver', 'bronze'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= renderSeoHead([
        'title' => 'PokeVote Leaderboard - Top Ranked Pokemon',
        'description' => 'See the top ranked Pokemon based on community voting and Elo ratings. Data comes from PokeAPI. Fan project, not affiliated with Nintendo or The Pokemon Company.',
        'path' => '/leaderboard.php',
    ]) ?>
    <?= renderHeadAssets() ?>
</head>
<body class="page-ranks">

<?= renderHeader('ranks') ?>

<main class="ranks">
    <div class="intro">
        <p class="eyebrow"><span class="pulse" aria-hidden="true"></span>Global rankings · updated live</p>
        <h1 class="intro__title">The <span class="grad">Top 20</span></h1>
        <p class="intro__sub">Ranked by Elo. Beat a stronger opponent, climb faster.</p>
    </div>

    <dl class="statbar">
        <div class="statbar__item">
            <dt>Battles</dt>
            <dd><?= number_format($totalVotes) ?></dd>
        </div>
        <div class="statbar__item">
            <dt>Contenders</dt>
            <dd><?= number_format($totalPokemon) ?></dd>
        </div>
        <div class="statbar__item">
            <dt>Peak Elo</dt>
            <dd><?= number_format($maxElo) ?></dd>
        </div>
    </dl>

    <?php if ($podium): ?>
    <section class="podium" aria-label="Top three">
        <?php foreach ($podium as $i => $p): ?>
            <article class="podium__card podium__card--<?= $medals[$i] ?>" style="--i: <?= $i ?>">
                <span class="card__glow" aria-hidden="true"></span>
                <span class="card__ghost" aria-hidden="true"><?= $i + 1 ?></span>
                <div class="podium__top">
                    <span class="podium__rank">0<?= $i + 1 ?></span>
                    <span class="chip chip--mono">#<?= dexNo((int) $p['id']) ?></span>
                </div>
                <div class="podium__art">
                    <span class="card__halo" aria-hidden="true"></span>
                    <img src="<?= e($p['sprite_url']) ?>" alt="<?= e(pokeName($p['name'])) ?>" width="475" height="475" decoding="async">
                </div>
                <h2 class="podium__name"><a href="pokemon.php?id=<?= (int) $p['id'] ?>"><?= e(pokeName($p['name'])) ?></a></h2>
                <div class="card__stats">
                    <span class="stat"><span class="stat__label">Elo</span><span class="stat__value"><?= number_format((int) $p['elo_rating']) ?></span></span>
                    <span class="stat"><span class="stat__label">Votes</span><span class="stat__value"><?= number_format((int) $p['vote_count']) ?></span></span>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if ($rest): ?>
    <ol class="ranklist" start="4">
        <li class="ranklist__head" aria-hidden="true">
            <span>Rank</span><span></span><span>Pokémon</span><span class="ranklist__bar-col">Rating</span><span>Elo</span><span>Votes</span>
        </li>
        <?php foreach ($rest as $i => $p): ?>
            <li class="rankrow" style="--i: <?= $i ?>">
                <span class="rankrow__rank"><?= sprintf('%02d', $i + 4) ?></span>
                <img class="rankrow__img" src="<?= e($p['sprite_url']) ?>" alt="" width="56" height="56" loading="lazy" decoding="async">
                <span class="rankrow__who">
                    <span class="rankrow__name"><a href="pokemon.php?id=<?= (int) $p['id'] ?>"><?= e(pokeName($p['name'])) ?></a></span>
                    <span class="rankrow__dex">#<?= dexNo((int) $p['id']) ?> · <?= e(genLabel((int) $p['generation'])) ?></span>
                </span>
                <span class="rankrow__bar" aria-hidden="true"><span style="width: <?= $barWidth((int) $p['elo_rating']) ?>%"></span></span>
                <span class="rankrow__elo"><?= number_format((int) $p['elo_rating']) ?></span>
                <span class="rankrow__votes"><?= number_format((int) $p['vote_count']) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <div class="arena__actions">
        <a class="btn btn--primary" href="index.php">
            Back to the arena
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    </div>
</main>

<?= renderFooter() ?>

</body>
</html>
