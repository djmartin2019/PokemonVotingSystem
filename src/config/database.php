<?php

$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    $parsed = parse_url($databaseUrl);

    $host = $parsed['host'];
    $port = $parsed['port'];
    $user = $parsed['user'];
    $pass = $parsed['pass'];
    $db   = ltrim($parsed['path'], '/');

    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass);
} else {
    // Local Docker fallback
    $dsn = "pgsql:host=db;port=5432;dbname=pokedex";
    $pdo = new PDO($dsn, "postgres", "postgres");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

