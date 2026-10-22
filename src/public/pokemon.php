<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Stats.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/layout.php';

$stats = new Stats($pdo);

$id = (int) ($_GET['id'] ?? 0);
$p = $id > 0 ? $stats->pokemon($id) : null;

if (!$p) {
    http_response_code(404);
}

$battles = $p ? $stats->battles($id) : [];

// Record and highlights
$wins = 0;
$losses = 0;
$biggestWin = null;
$shockLoss = null;
$beaten = [];
$lostTo = [];
$peak = Elo::START;

foreach ($battles as $b) {
    if ($b['after'] !== null) {
        $peak = max($peak, $b['after']);
    }
    if ($b['won']) {
        $wins++;
        $beaten[$b['opp_id']] = ($beaten[$b['opp_id']] ?? 0) + 1;
        if ($b['opp_elo'] !== null && (!$biggestWin || $b['opp_elo'] > $biggestWin['opp_elo'])) {
            $biggestWin = $b;
        }
    } else {
        $losses++;
        $lostTo[$b['opp_id']] = ($lostTo[$b['opp_id']] ?? 0) + 1;
        $wasUpset = $b['opp_elo'] !== null && $b['before'] !== null && $b['opp_elo'] < $b['before'];
        if ($wasUpset && (!$shockLoss || $b['opp_elo'] < $shockLoss['opp_elo'])) {
            $shockLoss = $b;
        }
    }
}

$total = $wins + $losses;
$winRate = $total ? $wins / $total : null;

// A rival only counts if they've met more than once
$findRival = function (array $counts) use ($battles): ?array {
    arsort($counts);
    $oppId = array_key_first($counts);
    if ($oppId === null || $counts[$oppId] < 2) {
        return null;
    }
    foreach ($battles as $b) {
        if ($b['opp_id'] === $oppId) {
            return ['id' => $oppId, 'name' => $b['opp_name'], 'sprite' => $b['opp_sprite'], 'count' => $counts[$oppId]];
        }
    }
    return null;
};
$nemesis = $findRival($lostTo);
$victim = $findRival($beaten);

// Elo line: starting rating, then the rating after each battle
$history = [];
foreach ($battles as $b) {
    if ($b['after'] === null) {
        continue;
    }
    if (!$history) {
        $history[] = ['n' => 0, 'elo' => $b['before'] ?? Elo::START];
    }
    $history[] = [
        'n'     => count($history),
        'elo'   => $b['after'],
        'won'   => $b['won'],
        'opp'   => pokeName($b['opp_name']),
        'delta' => $b['before'] !== null ? $b['after'] - $b['before'] : null,
        'date'  => $b['date'],
    ];
}

$recent = array_reverse(array_slice($battles, -8));
$log = array_reverse(array_slice($battles, -200));
$change = $p ? (int) $p['elo_rating'] - Elo::START : 0;

$json = fn ($data) => json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$signed = fn (int $n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '±')) . number_format(abs($n));
$shortDate = fn (string $d) => date('M j', strtotime($d));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($p): ?>
        <?= renderSeoHead([
            'title' => pokeName($p['name']) . ' - PokeVote Stats',
            'description' => pokeName($p['name']) . ' is ranked #' . $p['rank'] . ' on PokeVote with an Elo rating of ' . $p['elo_rating'] . '. See its rating history, record, and biggest wins. Fan project, not affiliated with Nintendo or The Pokemon Company.',
            'path' => '/pokemon.php?id=' . $id,
        ]) ?>
    <?php else: ?>
        <?= renderSeoHead(['title' => 'Pokémon not found - PokeVote', 'path' => '/pokemon.php', 'robots' => 'noindex']) ?>
    <?php endif; ?>
    <?= renderHeadAssets() ?>
</head>
<body class="page-pokemon">

<?= renderHeader('') ?>

<main class="detail">
<?php if (!$p): ?>
    <div class="intro">
        <p class="eyebrow">Error 404</p>
        <h1 class="intro__title">No Pokémon <span class="grad">found</span></h1>
        <p class="intro__sub">That one isn't in the arena. Try the rankings instead.</p>
    </div>
    <div class="arena__actions">
        <a class="btn btn--primary" href="leaderboard.php">See the rankings</a>
    </div>
<?php else: ?>

    <nav class="crumbs" aria-label="Pokémon navigation">
        <a class="crumbs__back" href="leaderboard.php">
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M16 10H5M9 5l-5 5 5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Rankings
        </a>
        <span class="crumbs__step">
            <?php if ($p['prev']): ?>
                <a href="pokemon.php?id=<?= $p['prev'] ?>" aria-label="Previous Pokémon">#<?= dexNo($p['prev']) ?></a>
            <?php endif; ?>
            <?php if ($p['next']): ?>
                <a href="pokemon.php?id=<?= $p['next'] ?>" aria-label="Next Pokémon">#<?= dexNo($p['next']) ?></a>
            <?php endif; ?>
        </span>
    </nav>

    <section class="profile">
        <div class="profile__art">
            <span class="card__ghost" aria-hidden="true"><?= dexNo($id) ?></span>
            <span class="card__halo" aria-hidden="true"></span>
            <img src="<?= Sprite::url($id, 'large') ?>" alt="<?= e(pokeName($p['name'])) ?>" width="480" height="480" fetchpriority="high" decoding="async">
        </div>

        <div class="profile__info">
            <div class="profile__chips">
                <span class="chip chip--mono">#<?= dexNo($id) ?></span>
                <span class="chip"><?= e(genLabel((int) $p['generation'])) ?></span>
                <span class="chip">Rank #<?= number_format($p['rank']) ?> of <?= number_format($p['field_size']) ?></span>
            </div>
            <h1 class="profile__name"><?= e(pokeName($p['name'])) ?></h1>

            <div class="profile__hero">
                <span class="profile__label">Elo rating</span>
                <span class="profile__elo"><?= number_format((int) $p['elo_rating']) ?></span>
                <span class="profile__delta <?= $change > 0 ? 'is-up' : ($change < 0 ? 'is-down' : '') ?>">
                    <?= $change > 0 ? '▲' : ($change < 0 ? '▼' : '') ?> <?= $signed($change) ?> since debut
                </span>
            </div>

            <dl class="tiles tiles--compact">
                <div class="tile">
                    <dt>Record</dt>
                    <dd><?= $wins ?>–<?= $losses ?></dd>
                </div>
                <div class="tile">
                    <dt>Win rate</dt>
                    <dd><?= $winRate !== null ? round($winRate * 100) . '%' : '—' ?></dd>
                </div>
                <div class="tile">
                    <dt>Peak Elo</dt>
                    <dd><?= number_format($peak) ?></dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="panel" aria-labelledby="history-title">
        <header class="panel__head">
            <div>
                <h2 class="panel__title" id="history-title">Elo over time</h2>
                <p class="panel__sub">Rating after each of <?= e(pokeName($p['name'])) ?>'s <?= number_format($total) ?> battles. Everyone starts at <?= Elo::START ?>.</p>
            </div>
        </header>
        <?php if (count($history) >= 2): ?>
            <div class="chart chart--line" id="elo-chart" role="img" aria-label="Line chart of Elo rating after each battle. The battle log below lists the same data."></div>
        <?php else: ?>
            <p class="empty">No battles yet. <?= e(pokeName($p['name'])) ?> is waiting for its first matchup in the <a href="index.php">arena</a>.</p>
        <?php endif; ?>
    </section>

    <?php if ($biggestWin || $shockLoss || $nemesis || $victim): ?>
    <section class="highlights" aria-label="Highlights">
        <?php
        $cards = [];
        if ($biggestWin) {
            $cards[] = ['label' => 'Biggest win', 'id' => $biggestWin['opp_id'], 'name' => $biggestWin['opp_name'], 'sprite' => $biggestWin['opp_sprite'],
                'detail' => 'Beat a ' . number_format($biggestWin['opp_elo']) . ' Elo opponent · ' . $shortDate($biggestWin['date'])];
        }
        if ($shockLoss) {
            $cards[] = ['label' => 'Shock loss', 'id' => $shockLoss['opp_id'], 'name' => $shockLoss['opp_name'], 'sprite' => $shockLoss['opp_sprite'],
                'detail' => 'Lost to a ' . number_format($shockLoss['opp_elo']) . ' Elo opponent · ' . $shortDate($shockLoss['date'])];
        }
        if ($nemesis) {
            $cards[] = ['label' => 'Nemesis', 'id' => $nemesis['id'], 'name' => $nemesis['name'], 'sprite' => $nemesis['sprite'],
                'detail' => 'Lost to them ' . $nemesis['count'] . ' times'];
        }
        if ($victim) {
            $cards[] = ['label' => 'Favorite victim', 'id' => $victim['id'], 'name' => $victim['name'], 'sprite' => $victim['sprite'],
                'detail' => 'Beaten ' . $victim['count'] . ' times'];
        }
        ?>
        <?php foreach ($cards as $i => $c): ?>
            <a class="highlight" href="pokemon.php?id=<?= (int) $c['id'] ?>" style="--i: <?= $i ?>">
                <img src="<?= Sprite::url((int) $c['id']) ?>" alt="" width="72" height="72" loading="lazy" decoding="async">
                <span class="highlight__text">
                    <span class="highlight__label"><?= e($c['label']) ?></span>
                    <span class="highlight__name"><?= e(pokeName($c['name'])) ?></span>
                    <span class="highlight__detail"><?= e($c['detail']) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if ($recent): ?>
    <section class="panel" aria-labelledby="recent-title">
        <header class="panel__head">
            <div>
                <h2 class="panel__title" id="recent-title">Recent battles</h2>
                <p class="panel__sub">The last <?= count($recent) ?> matchups, newest first.</p>
            </div>
        </header>

        <ol class="battles">
            <?php foreach ($recent as $b): ?>
                <li class="battle">
                    <span class="result <?= $b['won'] ? 'result--win' : 'result--loss' ?>"><?= $b['won'] ? 'Won' : 'Lost' ?></span>
                    <img src="<?= Sprite::url($b['opp_id']) ?>" alt="" width="40" height="40" loading="lazy" decoding="async">
                    <span class="battle__who">
                        <span class="battle__vs"><?= $b['won'] ? 'vs' : 'to' ?></span>
                        <a href="pokemon.php?id=<?= $b['opp_id'] ?>"><?= e(pokeName($b['opp_name'])) ?></a>
                        <?php if ($b['opp_elo'] !== null): ?><span class="battle__opp-elo"><?= number_format($b['opp_elo']) ?></span><?php endif; ?>
                    </span>
                    <span class="battle__date"><?= e($shortDate($b['date'])) ?></span>
                    <span class="battle__delta"><?= $b['before'] !== null && $b['after'] !== null ? $signed($b['after'] - $b['before']) : '' ?></span>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php if (count($battles) > count($recent)): ?>
        <details class="tableview">
            <summary>Full battle log<?= count($battles) > 200 ? ' (last 200)' : '' ?></summary>
            <div class="tableview__scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Result</th>
                            <th scope="col">Opponent</th>
                            <th scope="col" class="num">Elo after</th>
                            <th scope="col" class="num">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log as $b): ?>
                            <tr>
                                <td><?= e($shortDate($b['date'])) ?></td>
                                <td><?= $b['won'] ? 'Won' : 'Lost' ?></td>
                                <td><a href="pokemon.php?id=<?= $b['opp_id'] ?>"><?= e(pokeName($b['opp_name'])) ?></a></td>
                                <td class="num"><?= $b['after'] !== null ? number_format($b['after']) : '—' ?></td>
                                <td class="num"><?= $b['before'] !== null && $b['after'] !== null ? $signed($b['after'] - $b['before']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php endif; ?>
    </section>
    <?php endif; ?>

<?php endif; ?>
</main>

<?php if (count($history) >= 2): ?>
<script type="application/json" id="elo-data"><?= $json(['start' => Elo::START, 'points' => $history]) ?></script>
<script src="<?= assetUrl('assets/js/charts.js') ?>" defer></script>
<?php endif; ?>

<?= renderFooter() ?>

</body>
</html>
