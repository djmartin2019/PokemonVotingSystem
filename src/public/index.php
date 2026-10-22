<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/layout.php';

// Get two random Pokémon
$stmt = $pdo->query("
    SELECT id, name, sprite_url, generation, elo_rating, vote_count
    FROM pokemon
    ORDER BY RANDOM()
    LIMIT 2
");

$pokemon = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($pokemon) < 2) {
    die("Not enough Pokémon in DB.");
}

$totalVotes = (int) $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();

$sides = [
    'left'  => [$pokemon[0], $pokemon[1]],
    'right' => [$pokemon[1], $pokemon[0]],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= renderSeoHead([
        'title' => 'PokeVote - Vote for Your Favorite Pokemon',
        'description' => 'Choose between two Pokemon, cast your vote, and help shape the live rankings. Data comes from PokeAPI. Fan project, not affiliated with Nintendo or The Pokemon Company.',
        'path' => '/',
    ]) ?>
    <?= renderHeadAssets() ?>
</head>
<body class="page-arena">

<?= renderHeader('arena') ?>

<main class="arena">
    <div class="intro">
        <p class="eyebrow"><span class="pulse" aria-hidden="true"></span>Live arena · <?= number_format($totalVotes) ?> battles logged</p>
        <h1 class="intro__title">Who takes the <span class="grad">win</span>?</h1>
        <p class="intro__sub">Pick your favorite. Every vote shifts the Elo rankings in real time.</p>
    </div>

    <div class="matchup">
        <?php foreach ($sides as $side => [$p, $opponent]): ?>
            <?php if ($side === 'right'): ?>
                <div class="vs" aria-hidden="true">
                    <span class="vs__line"></span>
                    <span class="vs__core"><span class="vs__ring"></span><span class="vs__text">VS</span></span>
                    <span class="vs__line"></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="vote.php" class="contender contender--<?= $side ?>">
                <input type="hidden" name="winner_id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="loser_id" value="<?= (int) $opponent['id'] ?>">
                <button class="card" type="submit" aria-label="Vote for <?= e(pokeName($p['name'])) ?>">
                    <span class="card__glow" aria-hidden="true"></span>
                    <span class="card__ghost" aria-hidden="true"><?= dexNo((int) $p['id']) ?></span>

                    <span class="card__hud">
                        <span class="chip chip--mono">#<?= dexNo((int) $p['id']) ?></span>
                        <span class="chip"><?= e(genLabel((int) $p['generation'])) ?></span>
                    </span>

                    <span class="card__art">
                        <span class="card__halo" aria-hidden="true"></span>
                        <img src="<?= Sprite::url((int) $p['id'], 'large') ?>" alt="" width="480" height="480" fetchpriority="high" decoding="async">
                        <span class="card__floor" aria-hidden="true"></span>
                    </span>

                    <span class="card__meta">
                        <span class="card__name"><?= e(pokeName($p['name'])) ?></span>
                        <span class="card__stats">
                            <span class="stat"><span class="stat__label">Elo</span><span class="stat__value"><?= number_format((int) $p['elo_rating']) ?></span></span>
                            <span class="stat"><span class="stat__label">Votes</span><span class="stat__value"><?= number_format((int) $p['vote_count']) ?></span></span>
                        </span>
                    </span>

                    <span class="card__cta">
                        <span>Choose</span>
                        <kbd><?= $side === 'left' ? '←' : '→' ?></kbd>
                    </span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="arena__actions">
        <a class="btn btn--ghost" href="index.php" data-reroll>
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M16 10a6 6 0 1 1-1.76-4.24M16 4v3.5h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            New matchup
        </a>
        <span class="hint"><kbd>←</kbd><kbd>→</kbd> vote <span class="hint__sep"></span><kbd>R</kbd> reroll</span>
    </div>
</main>

<?= renderFooter() ?>

</body>
</html>
