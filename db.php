<?php
declare(strict_types=1);

/* Shrnk — data layer. SQLite means zero database setup: the file is
   created automatically on first run, no MySQL/phpMyAdmin needed. */

function db(): PDO
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $pdo = new PDO('sqlite:' . $dir . '/links.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE IF NOT EXISTS links (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        code        TEXT UNIQUE NOT NULL,
        url         TEXT NOT NULL,
        clicks      INTEGER NOT NULL DEFAULT 0,
        created_at  TEXT NOT NULL,
        last_visit  TEXT
    )');
    return $pdo;
}

/** Generate a short, unique, unambiguous code (no 0/O/1/l confusion). */
function make_code(PDO $pdo, int $len = 5): string
{
    $alphabet = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $max = strlen($alphabet) - 1;
    do {
        $code = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM links WHERE code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() !== false);
    return $code;
}

/** Validate + normalize a user-supplied URL, or null if it's not a safe http(s) URL. */
function normalize_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (!preg_match('~^[a-z][a-z0-9+.\-]*://~i', $url)) {
        $url = 'https://' . $url;            // assume https if no scheme given
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;                          // block javascript:, data:, etc.
    }
    return $url;
}
