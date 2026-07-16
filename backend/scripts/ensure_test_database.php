<?php

/**
 * Phase 3.3 — create the disposable cmart_test database if it does not exist.
 *
 * Uses credentials from .env.testing or .env. Never creates or modifies cmart_db.
 */
require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (is_readable($basePath . '/.env.testing')) {
    Dotenv\Dotenv::createImmutable($basePath, '.env.testing')->safeLoad();
} else {
    Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$approved = getenv('TESTING_APPROVED_DATABASE') ?: 'cmart_test';
$development = getenv('TESTING_DEVELOPMENT_DATABASE') ?: 'cmart_db';

if (strtolower($approved) === strtolower($development)) {
    fwrite(STDERR, "Refusing to create database: approved name matches development database.\n");
    exit(1);
}

$blocked = ['cmart_db', 'cmart', 'production', 'prod', 'staging', 'main', 'development', 'dev'];
if (in_array(strtolower($approved), $blocked, true)) {
    fwrite(STDERR, "Refusing to create blocked database name: {$approved}\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%s', $host, $port);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec(
        sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '``', $approved),
        ),
    );
    echo "database_created_or_exists={$approved}\n";
} catch (PDOException $exception) {
    fwrite(STDERR, 'Failed to create test database. Create it manually with CREATE DATABASE IF NOT EXISTS cmart_test ...' . PHP_EOL);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
