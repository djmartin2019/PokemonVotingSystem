<?php

require_once __DIR__ . '/Elo.php';

/**
 * Queries behind the stats and Pokémon detail pages.
 */
final class Stats
{
    private const RACE_CACHE_SECONDS = 60;

    public function __construct(private PDO $pdo)
    {
    }

    /** Headline numbers for the stats page. */
    public function overview(): array
    {
        $row = $this->pdo->query("
            SELECT
                COUNT(*) AS battles,
                COUNT(*) FILTER (WHERE created_at > NOW() - INTERVAL '24 hours') AS last_day,
                COUNT(*) FILTER (WHERE winner_elo_before < loser_elo_before) AS underdog_wins,
                COUNT(*) FILTER (WHERE winner_elo_before <> loser_elo_before) AS uneven,
                MAX(loser_elo_before - winner_elo_before) AS biggest_gap
            FROM votes
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'battles'     => (int) $row['battles'],
            'last_day'    => (int) $row['last_day'],
            'underdog'    => $row['uneven'] > 0 ? $row['underdog_wins'] / $row['uneven'] : null,
            'biggest_gap' => max(0, (int) $row['biggest_gap']),
        ];
    }

    /**
     * Rank over time for the current top Pokémon, rebuilt from the vote history.
     * Sampled at evenly spaced points so the chart stays light however many votes exist.
     */
    public function rankingRace(int $seriesCount = 8, int $samples = 40): array
    {
        $cacheFile = sys_get_temp_dir() . '/pokevote-race-' . $seriesCount . '-' . $samples . '.json';
        if (is_file($cacheFile) && time() - filemtime($cacheFile) < self::RACE_CACHE_SECONDS) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $top = $this->pdo->query("
            SELECT id, name, sprite_url, elo_rating
            FROM pokemon
            ORDER BY elo_rating DESC, vote_count DESC, id
            LIMIT " . (int) $seriesCount
        )->fetchAll(PDO::FETCH_ASSOC);

        $ratings = [];
        foreach ($this->pdo->query("SELECT id FROM pokemon") as $row) {
            $ratings[(int) $row['id']] = Elo::START;
        }

        $votes = $this->pdo->query("
            SELECT created_at, winner_id, loser_id, winner_elo_after, loser_elo_after
            FROM votes
            WHERE winner_elo_after IS NOT NULL
            ORDER BY id
        ")->fetchAll(PDO::FETCH_NUM);

        $total = count($votes);
        $result = ['total' => $total, 'points' => [], 'series' => []];

        if ($total < 2 || !$top) {
            return $result;
        }

        $samples = min($samples, $total);
        $sampleAt = [];
        for ($s = 1; $s <= $samples; $s++) {
            $sampleAt[(int) round($s * $total / $samples)] = true;
        }

        $series = [];
        foreach ($top as $p) {
            $series[(int) $p['id']] = [
                'id'     => (int) $p['id'],
                'name'   => $p['name'],
                'sprite' => $p['sprite_url'],
                'ranks'  => [],
                'elos'   => [],
            ];
        }

        foreach ($votes as $i => [$createdAt, $winnerId, $loserId, $winnerAfter, $loserAfter]) {
            $ratings[(int) $winnerId] = (int) $winnerAfter;
            $ratings[(int) $loserId] = (int) $loserAfter;

            $applied = $i + 1;
            if (!isset($sampleAt[$applied])) {
                continue;
            }

            $result['points'][] = ['battle' => $applied, 'date' => substr((string) $createdAt, 0, 16)];

            foreach ($series as $id => &$s) {
                $mine = $ratings[$id];
                $rank = 1;
                foreach ($ratings as $otherId => $other) {
                    if ($other > $mine || ($other === $mine && $otherId < $id)) {
                        $rank++;
                    }
                }
                $s['ranks'][] = $rank;
                $s['elos'][] = $mine;
            }
            unset($s);
        }

        $result['series'] = array_values($series);

        @file_put_contents($cacheFile, json_encode($result), LOCK_EX);

        return $result;
    }

    /** Wins where the lower-rated Pokémon beat the higher-rated one, biggest gap first. */
    public function biggestUpsets(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                v.created_at,
                v.winner_elo_before, v.loser_elo_before,
                w.id AS winner_id, w.name AS winner_name, w.sprite_url AS winner_sprite,
                l.id AS loser_id, l.name AS loser_name, l.sprite_url AS loser_sprite
            FROM votes v
            JOIN pokemon w ON w.id = v.winner_id
            JOIN pokemon l ON l.id = v.loser_id
            WHERE v.loser_elo_before > v.winner_elo_before
            ORDER BY (v.loser_elo_before - v.winner_elo_before) DESC, v.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(function (array $u): array {
            $u['gap'] = (int) $u['loser_elo_before'] - (int) $u['winner_elo_before'];
            $u['odds'] = Elo::expected((int) $u['winner_elo_before'], (int) $u['loser_elo_before']);
            return $u;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Every Pokémon's generation and rating, for the generation chart. */
    public function field(): array
    {
        $rows = $this->pdo->query("
            SELECT id, name, generation, elo_rating, sprite_url
            FROM pokemon
            ORDER BY id
        ")->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($r) => [
            (int) $r['id'],
            $r['name'],
            (int) $r['generation'],
            (int) $r['elo_rating'],
            $r['sprite_url'],
        ], $rows);
    }

    public function pokemon(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pokemon WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) + 1 FROM pokemon
            WHERE elo_rating > :elo OR (elo_rating = :elo2 AND vote_count > :votes)
        ");
        $stmt->execute(['elo' => $p['elo_rating'], 'elo2' => $p['elo_rating'], 'votes' => $p['vote_count']]);
        $p['rank'] = (int) $stmt->fetchColumn();
        $p['field_size'] = (int) $this->pdo->query("SELECT COUNT(*) FROM pokemon")->fetchColumn();

        $neighbours = $this->pdo->prepare("
            SELECT
                (SELECT MAX(id) FROM pokemon WHERE id < :a) AS prev,
                (SELECT MIN(id) FROM pokemon WHERE id > :b) AS next
        ");
        $neighbours->execute(['a' => $id, 'b' => $id]);
        $n = $neighbours->fetch(PDO::FETCH_ASSOC);
        $p['prev'] = $n['prev'] !== null ? (int) $n['prev'] : null;
        $p['next'] = $n['next'] !== null ? (int) $n['next'] : null;

        return $p;
    }

    /** Every battle a Pokémon has fought, oldest first, from its point of view. */
    public function battles(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                v.id, v.created_at, v.winner_id,
                v.winner_elo_before, v.winner_elo_after, v.loser_elo_before, v.loser_elo_after,
                o.id AS opp_id, o.name AS opp_name, o.sprite_url AS opp_sprite
            FROM votes v
            JOIN pokemon o ON o.id = CASE WHEN v.winner_id = :a THEN v.loser_id ELSE v.winner_id END
            WHERE v.winner_id = :b OR v.loser_id = :c
            ORDER BY v.id
        ");
        $stmt->execute(['a' => $id, 'b' => $id, 'c' => $id]);

        $battles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $won = (int) $v['winner_id'] === $id;
            $before = $won ? $v['winner_elo_before'] : $v['loser_elo_before'];
            $after = $won ? $v['winner_elo_after'] : $v['loser_elo_after'];

            $battles[] = [
                'won'        => $won,
                'date'       => substr((string) $v['created_at'], 0, 16),
                'before'     => $before !== null ? (int) $before : null,
                'after'      => $after !== null ? (int) $after : null,
                'opp_id'     => (int) $v['opp_id'],
                'opp_name'   => $v['opp_name'],
                'opp_sprite' => $v['opp_sprite'],
                'opp_elo'    => ($won ? $v['loser_elo_before'] : $v['winner_elo_before']) !== null
                    ? (int) ($won ? $v['loser_elo_before'] : $v['winner_elo_before'])
                    : null,
            ];
        }

        return $battles;
    }
}
