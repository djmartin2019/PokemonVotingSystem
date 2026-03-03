<?php

function getBaseUrl(): string
{
    $envUrl = getenv('APP_URL');
    if (is_string($envUrl) && $envUrl !== '') {
        return rtrim($envUrl, '/');
    }

    return 'https://pokevote.djm-apps.com';
}

function renderSeoHead(array $options = []): string
{
    $baseUrl = getBaseUrl();

    $title = $options['title'] ?? 'PokeVote - Pokemon Fan Voting Project';
    $description = $options['description'] ?? 'Vote for your favorite Pokemon and explore live rankings. Data provided by PokeAPI. This is an independent fan project and is not affiliated with Nintendo or The Pokemon Company.';
    $path = $options['path'] ?? '/';
    $robots = $options['robots'] ?? 'index,follow,max-image-preview:large';
    $canonical = $baseUrl . $path;
    $siteName = 'PokeVote';
    $imagePath = $options['image_path'] ?? '/assets/images/pokevote.png';
    $faviconPath = $options['favicon_path'] ?? '/assets/images/favicon.png';
    $imageUrl = $baseUrl . $imagePath;
    $faviconUrl = $baseUrl . $faviconPath;

    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $descEsc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $canonicalEsc = htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8');
    $robotsEsc = htmlspecialchars($robots, ENT_QUOTES, 'UTF-8');
    $siteNameEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $imageEsc = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
    $faviconEsc = htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8');
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteName,
        'url' => $baseUrl . '/',
        'description' => $description,
        'isFamilyFriendly' => true,
        'creator' => [
            '@type' => 'Person',
            'name' => 'David Martin',
        ],
        'license' => 'https://pokeapi.co/docs/v2#fairuse',
    ];

    $jsonLdEsc = htmlspecialchars(
        json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ENT_NOQUOTES,
        'UTF-8'
    );

    return <<<HTML
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title>{$titleEsc}</title>
<meta name="description" content="{$descEsc}">
<meta name="robots" content="{$robotsEsc}">
<link rel="canonical" href="{$canonicalEsc}">
<link rel="icon" type="image/png" href="{$faviconEsc}">
<link rel="shortcut icon" type="image/png" href="{$faviconEsc}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{$siteNameEsc}">
<meta property="og:title" content="{$titleEsc}">
<meta property="og:description" content="{$descEsc}">
<meta property="og:url" content="{$canonicalEsc}">
<meta property="og:image" content="{$imageEsc}">
<meta property="og:image:alt" content="PokeVote tournament-style Pokemon matchup banner">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$titleEsc}">
<meta name="twitter:description" content="{$descEsc}">
<meta name="twitter:image" content="{$imageEsc}">
<script type="application/ld+json">{$jsonLdEsc}</script>
HTML;
}
