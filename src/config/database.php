<?php

$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {

    $parsed = parse_url($databaseUrl);

    $host = $parsed['host'] ?? null;
    $port = $parsed['port'] ?? 5432; // default if missing
    $user = $parsed['user'] ?? null;
    $pass = $parsed['pass'] ?? null;
    $db   = isset($parsed['path']) ? ltrim($parsed['path'], '/') : null;

    if (!$host || !$db) {
        die("Invalid DATABASE_URL configuration.");
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$db";

    $pdo = new PDO($dsn, $user, $pass);

} else {

    // Local Docker fallback
    $dsn = "pgsql:host=db;port=5432;dbname=pokedex";
    $pdo = new PDO($dsn, "postgres", "postgres");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

