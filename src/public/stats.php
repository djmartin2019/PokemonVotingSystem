<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Stats.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/layout.php';

$stats = new Stats($pdo);

$overview = $stats->overview();
$race = $stats->rankingRace();
$upsets = $stats->biggestUpsets();
$field = $stats->field();

// Per-generation summary for the table view
$byGen = [];
foreach ($field as [$id, $name, $gen, $elo]) {
    $byGen[$gen][] = ['id' => $id, 'name' => $name, 'elo' => $elo];
}
ksort($byGen);
$genSummary = [];
foreach ($byGen as $gen => $members) {
    $elos = array_column($members, 'elo');
    sort($elos);
    $count = count($elos);
    $median = $count % 2 ? $elos[intdiv($count, 2)] : ($elos[$count / 2 - 1] + $elos[$count / 2]) / 2;
    usort($members, fn ($a, $b) => $b['elo'] <=> $a['elo']);
    $genSummary[] = [
        'gen'    => $gen,
        'count'  => $count,
        'median' => (int) round($median),
        'mean'   => (int) round(array_sum($elos) / $count),
        'best'   => $members[0],
    ];
}

$json = fn ($data) => json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$hasRace = count($race['points']) >= 2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= renderSeoHead([
        'title' => 'PokeVote Stats - Rankings Race, Upsets and Generations',
        'description' => 'Watch the Pokemon rankings race unfold, browse the biggest upsets, and compare every generation by Elo rating. Data comes from PokeAPI. Fan project, not affiliated with Nintendo or The Pokemon Company.',
        'path' => '/stats.php',
    ]) ?>
    <?= renderHeadAssets() ?>
</head>
<body class="page-stats">

<?= renderHeader('stats') ?>

<main class="stats">
    <div class="intro">
        <p class="eyebrow"><span class="pulse" aria-hidden="true"></span>Arena analytics · live</p>
        <h1 class="intro__title">By the <span class="grad">numbers</span></h1>
        <p class="intro__sub">Every vote ever cast, replayed. See who climbed, who stumbled, and which generation rules.</p>
    </div>

    <dl class="tiles">
        <div class="tile">
            <dt>Battles</dt>
            <dd><?= number_format($overview['battles']) ?></dd>
        </div>
        <div class="tile">
            <dt>Last 24 hours</dt>
            <dd><?= number_format($overview['last_day']) ?></dd>
        </div>
        <div class="tile">
            <dt>Underdog win rate</dt>
            <dd><?= $overview['underdog'] !== null ? round($overview['underdog'] * 100) . '%' : '—' ?></dd>
        </div>
        <div class="tile">
            <dt>Biggest upset</dt>
            <dd><?= $overview['biggest_gap'] ? number_format($overview['biggest_gap']) . '<small> pts</small>' : '—' ?></dd>
        </div>
    </dl>

    <section class="panel" aria-labelledby="race-title">
        <header class="panel__head">
            <div>
                <h2 class="panel__title" id="race-title">Rankings race</h2>
                <p class="panel__sub">Rank of today's top 8 across all <?= number_format($race['total']) ?> battles. Hover to scrub through time.</p>
            </div>
            <div class="legend" id="race-legend" role="list"></div>
        </header>

        <?php if ($hasRace): ?>
            <div class="chart chart--race" id="race-chart" role="img" aria-label="Line chart of how the current top 8 Pokémon's ranks changed over time. A table version follows."></div>
            <details class="tableview">
                <summary>View as table</summary>
                <div class="tableview__scroll">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Pokémon</th>
                                <?php foreach ([0, 0.25, 0.5, 0.75, 1] as $f): $pt = $race['points'][(int) round($f * (count($race['points']) - 1))]; ?>
                                    <th scope="col" class="num">Rank at battle <?= number_format($pt['battle']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($race['series'] as $s): ?>
                                <tr>
                                    <th scope="row"><a href="pokemon.php?id=<?= $s['id'] ?>"><?= e(pokeName($s['name'])) ?></a></th>
                                    <?php foreach ([0, 0.25, 0.5, 0.75, 1] as $f): ?>
                                        <td class="num">#<?= $s['ranks'][(int) round($f * (count($s['ranks']) - 1))] ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php else: ?>
            <p class="empty">Not enough battles yet. Cast a few votes in the <a href="index.php">arena</a> and the race will appear here.</p>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="gen-title">
        <header class="panel__head">
            <div>
                <h2 class="panel__title" id="gen-title">Generation showdown</h2>
                <p class="panel__sub">Every Pokémon as a dot, placed by Elo. The bar marks each generation's median.</p>
            </div>
        </header>

        <div class="chart chart--swarm" id="swarm-chart" role="img" aria-label="Dot chart of every Pokémon's Elo rating grouped by generation. A table version follows."></div>

        <details class="tableview">
            <summary>View as table</summary>
            <div class="tableview__scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Generation</th>
                            <th scope="col" class="num">Pokémon</th>
                            <th scope="col" class="num">Median Elo</th>
                            <th scope="col" class="num">Average Elo</th>
                            <th scope="col">Highest rated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($genSummary as $g): ?>
                            <tr>
                                <th scope="row"><?= e(genLabel($g['gen'])) ?></th>
                                <td class="num"><?= $g['count'] ?></td>
                                <td class="num"><?= number_format($g['median']) ?></td>
                                <td class="num"><?= number_format($g['mean']) ?></td>
                                <td><a href="pokemon.php?id=<?= $g['best']['id'] ?>"><?= e(pokeName($g['best']['name'])) ?></a> · <?= number_format($g['best']['elo']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </section>

    <section class="panel" aria-labelledby="upsets-title">
        <header class="panel__head">
            <div>
                <h2 class="panel__title" id="upsets-title">Biggest upsets</h2>
                <p class="panel__sub">Wins where the lower-rated Pokémon took down a favorite, ranked by the Elo gap going in.</p>
            </div>
        </header>

        <?php if ($upsets): ?>
            <ol class="upsets">
                <?php foreach ($upsets as $i => $u): ?>
                    <li class="upset" style="--i: <?= $i ?>">
                        <span class="upset__rank"><?= sprintf('%02d', $i + 1) ?></span>
                        <span class="upset__duel">
                            <a class="upset__mon" href="pokemon.php?id=<?= (int) $u['winner_id'] ?>">
                                <img src="<?= e($u['winner_sprite']) ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
                                <span>
                                    <span class="upset__name"><?= e(pokeName($u['winner_name'])) ?></span>
                                    <span class="upset__elo"><?= number_format((int) $u['winner_elo_before']) ?></span>
                                </span>
                            </a>
                            <span class="upset__beat">beat</span>
                            <a class="upset__mon upset__mon--loser" href="pokemon.php?id=<?= (int) $u['loser_id'] ?>">
                                <img src="<?= e($u['loser_sprite']) ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
                                <span>
                                    <span class="upset__name"><?= e(pokeName($u['loser_name'])) ?></span>
                                    <span class="upset__elo"><?= number_format((int) $u['loser_elo_before']) ?></span>
                                </span>
                            </a>
                        </span>
                        <span class="upset__numbers">
                            <span class="upset__gap"><?= number_format($u['gap']) ?><small> pt gap</small></span>
                            <span class="upset__odds"><?= max(1, (int) round($u['odds'] * 100)) ?>% expected chance</span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="empty">No upsets yet. Every favorite has held on so far.</p>
        <?php endif; ?>
    </section>
</main>

<script type="application/json" id="race-data"><?= $json($race) ?></script>
<script type="application/json" id="field-data"><?= $json($field) ?></script>
<script src="<?= assetUrl('assets/js/charts.js') ?>" defer></script>

<?= renderFooter() ?>

</body>
</html>
