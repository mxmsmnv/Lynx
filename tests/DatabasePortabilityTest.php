<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/src/LynxSchema.php');
$profiles = file_get_contents($root . '/src/LynxProfileData.php');

$assert = static function(bool $condition, string $message): void {
    if(!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert(stripos($schema, 'information_schema') === false, 'Upgrade schema must not query information_schema.');
$assert(strpos($schema, 'columnExists(') !== false, 'Upgrade schema must use columnExists().');
$assert(strpos($schema, 'indexExists(') !== false, 'Upgrade schema must use indexExists().');
$assert(strpos($schema, "name() === 'sqlite'") !== false, 'SQLite foreign-key upgrade guard is missing.');
$assert(stripos($profiles, 'DELETE c FROM') === false, 'Profile deletion must not use DELETE JOIN syntax.');
$assert(strpos($profiles, 'WHERE link_id IN (SELECT id FROM') !== false, 'Portable click deletion subquery is missing.');

echo "Database portability checks passed.\n";
