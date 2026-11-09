<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pdo = getDB();

// Очистка логов старше 30 дней
$stmt = $pdo->prepare("DELETE FROM bot_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();

echo "Cron completed. Deleted old logs: " . $stmt->rowCount() . PHP_EOL;
