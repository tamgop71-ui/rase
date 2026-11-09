<?php
declare(strict_types=1);

require_once __DIR__ . '/bot.php';

$pdo = getDB();
processTimers($pdo);
echo "Cron executed successfully at " . date('Y-m-d H:i:s') . PHP_EOL;
