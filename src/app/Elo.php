<?php

/**
 * Elo math shared by vote.php and the history backfill, so both always agree.
 */
final class Elo
{
    public const START = 1000;
    public const K = 32;

    /** Chance that a player rated $a beats a player rated $b. */
    public static function expected(int $a, int $b): float
    {
        return 1 / (1 + pow(10, ($b - $a) / 400));
    }

    /** @return array{int, int} New [winner, loser] ratings. */
    public static function play(int $winner, int $loser): array
    {
        $ew = self::expected($winner, $loser);
        $el = 1 - $ew;

        return [
            (int) round($winner + self::K * (1 - $ew)),
            (int) round($loser + self::K * (0 - $el)),
        ];
    }
}
