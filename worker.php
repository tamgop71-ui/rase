<?php
declare(strict_types=1);

/**
 * Фоновый демон v6.0 TURBO.
 * Работает параллельно с веб-сервером, каждые 15 секунд:
 * 1) Отправляет напоминания (!напомни) точно в срок, даже если в чате тишина.
 * 2) Отправляет плановые автоуведомления (!timeuved / !gtimeuvedg).
 * 3) Деактивирует истёкшие муты/баны и чистит старый кэш сообщений.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

file_put_contents('php://stderr', "[WORKER] Background reminder & timer daemon started." . PHP_EOL);

$tick = 0;
while (true) {
    try {
        $pdo = getDB();

        // 1. Отправка сработавших напоминаний (!напомни)
        $stmtRem = $pdo->query("SELECT * FROM bot_reminders WHERE is_sent = 0 AND remind_at <= NOW() ORDER BY remind_at ASC LIMIT 20");
        $reminders = $stmtRem->fetchAll();
        foreach ($reminders as $r) {
            $rid = (int)$r['id'];
            $pId = (int)$r['peer_id'];
            $uid = (int)$r['user_vk_id'];
            $pdo->prepare("UPDATE bot_reminders SET is_sent = 1 WHERE id = ?")->execute([$rid]);
            $mention = ((int)$r['is_dm'] === 1) ? "" : (formatUserMention($pdo, $pId, $uid) . ", ");
            sendMessage($pId, "⏰ {$mention}напоминание (#{$rid}):\n📝 {$r['text']}");
        }

        // 2. Раз в минуту: таймеры (!timeuved / !gtimeuvedg) + очистка истёкших мутов/банов
        if ($tick % 4 === 0) {
            $pdo->query("UPDATE bot_punishments SET is_active = 0 WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at <= NOW()");
            $pdo->query("DELETE FROM bot_recent_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");

            $timers = $pdo->query("SELECT * FROM bot_timers WHERE is_active = 1")->fetchAll();
            $now = time();
            $curHM = date('H:i');

            foreach ($timers as $t) {
                $tId = (int)$t['id'];
                $tPeer = (int)$t['peer_id'];
                $lastSent = !empty($t['last_sent_at']) ? strtotime($t['last_sent_at']) : 0;
                $shouldSend = false;

                if (!empty($t['daily_time'])) {
                    if ($t['daily_time'] === $curHM && ($now - $lastSent) >= 3600) $shouldSend = true;
                } else {
                    if (($now - $lastSent) >= max(1, (int)$t['interval_hours']) * 3600) $shouldSend = true;
                }

                if ($shouldSend) {
                    $pdo->prepare("UPDATE bot_timers SET last_sent_at = NOW() WHERE id = ?")->execute([$tId]);
                    if ($tPeer > 0) {
                        sendMessage($tPeer, "⏰ Напоминание:\n" . $t['message_text']);
                    } else {
                        foreach ($pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000")->fetchAll(PDO::FETCH_COLUMN) as $cp) {
                            sendMessage((int)$cp, "📢 Автоуведомление:\n" . $t['message_text']);
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        file_put_contents('php://stderr', "[WORKER ERROR] " . $e->getMessage() . PHP_EOL);
    }

    $tick++;
    sleep(15);
}
