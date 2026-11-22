<?php
declare(strict_types=1);

/**
 * Фоновый демон RASE RUSSIA BOT.
 * Каждые 5 секунд:
 * 1) Сбрасывает накопленную очередь статистики сообщений в MySQL одной пачкой.
 * 2) Исключает пользователей, не прошедших авто-капчу (!капча) за 60 секунд.
 * Каждые 15 секунд: отправляет сработавшие напоминания (!напомни).
 * Каждые 60 секунд: отправляет таймеры (!timeuved / !gtimeuvedg) и снимает истёкшие муты/баны.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

file_put_contents('php://stderr', "[WORKER] Background daemon started." . PHP_EOL);

function flushStatQueue(PDO $pdo): void {
    $qFile = '/tmp/bot_stat_queue.jsonl';
    if (!is_file($qFile) || filesize($qFile) === 0) return;

    $tmpFile = '/tmp/bot_stat_flush_' . getmypid() . '.jsonl';
    if (!@rename($qFile, $tmpFile)) return;

    $lines = @file($tmpFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    @unlink($tmpFile);
    if (empty($lines)) return;

    $userAgg = [];
    $chatAgg = [];
    $memAgg  = [];

    foreach ($lines as $line) {
        $ev = json_decode($line, true);
        if (!is_array($ev)) continue;
        $p = (int)($ev['p'] ?? 0);
        $u = (int)($ev['u'] ?? 0);
        $c = (int)($ev['c'] ?? 0);
        $s = (int)($ev['s'] ?? 0);
        $d = (string)($ev['d'] ?? date('Y-m-d'));
        if ($p <= 0 || $u <= 0) continue;

        if (!isset($userAgg[$u])) $userAgg[$u] = ['m' => 0, 'c' => 0, 's' => 0, 'd' => $d];
        $userAgg[$u]['m']++; $userAgg[$u]['c'] += $c; $userAgg[$u]['s'] += $s; $userAgg[$u]['d'] = $d;

        if (!isset($chatAgg[$p])) $chatAgg[$p] = 0;
        $chatAgg[$p]++;

        $mk = "{$p}:{$u}";
        if (!isset($memAgg[$mk])) $memAgg[$mk] = ['p' => $p, 'u' => $u, 'm' => 0, 'c' => 0, 's' => 0];
        $memAgg[$mk]['m']++; $memAgg[$mk]['c'] += $c; $memAgg[$mk]['s'] += $s;
    }

    $pdo->beginTransaction();
    try {
        $stU = $pdo->prepare("UPDATE bot_users SET total_messages = total_messages + ?, total_chars = total_chars + ?, total_stickers = total_stickers + ?, daily_msgs = IF(daily_date = ?, daily_msgs + ?, ?), daily_games = IF(daily_date = ?, daily_games, 0), quest_claimed = IF(daily_date = ?, quest_claimed, 0), daily_date = ? WHERE vk_id = ?");
        foreach ($userAgg as $uid => $a) {
            $stU->execute([$a['m'], $a['c'], $a['s'], $a['d'], $a['m'], $a['m'], $a['d'], $a['d'], $a['d'], $uid]);
        }

        $stC = $pdo->prepare("UPDATE bot_chats SET total_messages = total_messages + ? WHERE peer_id = ?");
        foreach ($chatAgg as $pid => $cnt) {
            $stC->execute([$cnt, $pid]);
        }

        $stM = $pdo->prepare("UPDATE bot_chat_members SET messages_count = messages_count + ?, chars_count = chars_count + ?, stickers_count = stickers_count + ?, last_active_at = NOW() WHERE peer_id = ? AND vk_id = ?");
        foreach ($memAgg as $a) {
            $stM->execute([$a['m'], $a['c'], $a['s'], $a['p'], $a['u']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function processExpiredCaptchas(): void {
    $file = '/tmp/captcha_queue.json';
    if (!is_file($file)) return;
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data) || empty($data)) return;

    $now = time();
    $changed = false;
    foreach ($data as $key => $item) {
        if ($now >= (int)$item['exp']) {
            $pId = (int)$item['p'];
            $uid = (int)$item['u'];
            unset($data[$key]);
            $changed = true;
            if ($pId > 2000000000) {
                vkApi('messages.removeChatUser', ['chat_id' => $pId - 2000000000, 'member_id' => $uid]);
                sendMessage($pId, "🧩 [id{$uid}|Участник] не прошёл проверку капчи за 60 секунд и был исключён.", null, ['disable_mentions' => 1]);
            }
        }
    }
    if ($changed) {
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}

$tick = 0;
while (true) {
    try {
        $pdo = getDB();

        // 1. Каждые 5 сек: пакетный сброс статистики + проверка истёкших капч
        flushStatQueue($pdo);
        processExpiredCaptchas();

        // 2. Каждые 15 сек: отправка сработавших напоминаний (!напомни)
        if ($tick % 3 === 0) {
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
        }

        // 3. Раз в минуту: таймеры (!timeuved / !gtimeuvedg) и деактивация истёкших наказаний
        if ($tick % 12 === 0) {
            $pdo->query("UPDATE bot_punishments SET is_active = 0 WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at <= NOW()");

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
        try { getDB(true); } catch (Throwable $ignore) {}
    }

    $tick++;
    sleep(5);
}
