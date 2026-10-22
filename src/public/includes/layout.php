<?php

require_once __DIR__ . '/../../app/Sprite.php';

/**
 * Shared page chrome (fonts, background, header, footer) and small display helpers.
 */

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pokeName(string $name): string
{
    return ucwords(str_replace('-', ' ', $name));
}

function dexNo(int $id): string
{
    return sprintf('%03d', $id);
}

function genLabel(?int $generation): string
{
    $roman = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
    return 'Gen ' . ($roman[$generation] ?? (string) $generation);
}

function assetUrl(string $path): string
{
    $file = __DIR__ . '/../' . $path;
    $version = is_file($file) ? filemtime($file) : time();
    return e($path . '?v=' . $version);
}

function renderHeadAssets(): string
{
    $css = assetUrl('assets/css/style.css');

    return <<<HTML
<meta name="theme-color" content="#05060d">
<link rel="preload" href="assets/fonts/space-grotesk-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/fonts/jetbrains-mono-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{$css}">
<script type="speculationrules">
{"prefetch": [{"where": {"and": [{"href_matches": "/*"}, {"not": {"href_matches": "/vote.php"}}]}, "eagerness": "moderate"}]}
</script>
HTML;
}

function renderHeader(string $active): string
{
    $arena = $active === 'arena' ? ' is-active" aria-current="page' : '';
    $ranks = $active === 'ranks' ? ' is-active" aria-current="page' : '';
    $stats = $active === 'stats' ? ' is-active" aria-current="page' : '';

    return <<<HTML
<header class="site-header">
    <a class="brand" href="index.php" aria-label="PokeVote home">
        <svg class="brand__mark" viewBox="0 0 32 32" aria-hidden="true">
            <defs>
                <linearGradient id="brandGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#5ef0ff"/>
                    <stop offset="1" stop-color="#a47bff"/>
                </linearGradient>
            </defs>
            <circle cx="16" cy="16" r="13" fill="none" stroke="url(#brandGrad)" stroke-width="2"/>
            <path d="M3 16h8.5M20.5 16H29" stroke="url(#brandGrad)" stroke-width="2" stroke-linecap="round"/>
            <circle cx="16" cy="16" r="4.2" fill="none" stroke="url(#brandGrad)" stroke-width="2"/>
            <circle cx="16" cy="16" r="1.4" fill="#5ef0ff"/>
        </svg>
        <span class="brand__word">Poke<span>Vote</span></span>
    </a>
    <nav class="nav" aria-label="Main">
        <a class="nav__link{$arena}" href="index.php">Arena</a>
        <a class="nav__link{$ranks}" href="leaderboard.php">Rankings</a>
        <a class="nav__link{$stats}" href="stats.php">Stats</a>
    </nav>
</header>
HTML;
}

function renderFooter(): string
{
    $year = date('Y');
    $js = assetUrl('assets/js/app.js');

    return <<<HTML
<footer class="site-footer">
    <div class="site-footer__inner">
        <span class="site-footer__tag">PokeVote · {$year}</span>
        <p>
            Pokémon data provided by <a href="https://pokeapi.co/" target="_blank" rel="noopener">PokéAPI</a>.
            Fan-made project, not affiliated with or endorsed by Nintendo, Game Freak, or The Pokémon Company.
        </p>
    </div>
</footer>
<script src="{$js}" defer></script>
HTML;
}
