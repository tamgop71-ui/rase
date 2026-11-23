<?php
declare(strict_types=1);

/**
 * Фоновый демон RASE RUSSIA BOT.
 * Каждые 15 секунд:
 * - обновляет пульс /tmp/worker_hb.txt для watchdog;
 * - отправляет сработавшие напоминания (!напомни);
 * - подводит итоги завершившихся розыгрышей (!розыгрыш);
 * Раз в минуту:
 * - снимает истёкшие муты/баны, отпуска и временные роли (!temprole);
 * - отправляет таймеры (!timeuved / !gtimeuvedg) и ежедневные авто-отчёты (!автоотчёт);
 * Раз в 10 минут:
 * - автоматически синхронизирует участников и роли всех бесед (autosync_enabled = 1);
 * - очищает старые временные файлы в /tmp.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

file_put_contents('php://stderr', "[WORKER] Background daemon started." . PHP_EOL);

$tick = 0;
while (true) {
    @file_put_contents('/tmp/worker_hb.txt', (string)time());

    try {
        $pdo = getDB();

        // 1. Отправка сработавших напоминаний (!напомни)
        $reminders = $pdo->query("SELECT * FROM bot_reminders WHERE is_sent = 0 AND remind_at <= NOW() ORDER BY remind_at ASC LIMIT 20")->fetchAll();
        foreach ($reminders as $r) {
            $rid = (int)$r['id'];
            $pId = (int)$r['peer_id'];
            $uid = (int)$r['user_vk_id'];
            $pdo->prepare("UPDATE bot_reminders SET is_sent = 1 WHERE id = ?")->execute([$rid]);
            $mention = ((int)$r['is_dm'] === 1) ? "" : (formatUserMention($pdo, $pId, $uid) . ", ");
            sendMessage($pId, "⏰ {$mention}напоминание (#{$rid}):\n📝 {$r['text']}");
        }

        // 2. Автоматическое подведение итогов розыгрышей (!розыгрыш)
        $endedGws = $pdo->query("SELECT * FROM bot_giveaways WHERE is_finished = 0 AND ends_at <= NOW() LIMIT 10")->fetchAll();
        foreach ($endedGws as $gw) {
            $gwId = (int)$gw['id'];
            $pId  = (int)$gw['peer_id'];
            $prize = (int)$gw['prize'];
            $wCnt = max(1, (int)$gw['winners_count']);
            $pdo->prepare("UPDATE bot_giveaways SET is_finished = 1 WHERE id = ?")->execute([$gwId]);

            $stEnt = $pdo->prepare("SELECT vk_id FROM bot_giveaway_entries WHERE giveaway_id = ?");
            $stEnt->execute([$gwId]);
            $entries = array_map('intval', $stEnt->fetchAll(PDO::FETCH_COLUMN));

            if (empty($entries)) {
                sendMessage($pId, "🎉 Розыгрыш #{$gwId} на " . formatMoney($prize) . " завершён!\n😔 Никто не принял участие.");
                continue;
            }

            shuffle($entries);
            $winners = array_slice($entries, 0, min($wCnt, count($entries)));
            $eachPrize = (int)floor($prize / count($winners));
            preloadUsers($pdo, $pId, $winners);

            $winLines = [];
            foreach ($winners as $wid) {
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$eachPrize, $wid]);
                $winLines[] = "🏆 " . formatUserMention($pdo, $pId, $wid) . " — +" . formatMoney($eachPrize);
            }
            sendMessage($pId, "🎉 Розыгрыш #{$gwId} завершён! (Участников: " . count($entries) . ")\n━━━━━━━━━━━━━━━━━━\n" . implode("\n", $winLines) . "\n\n💰 Призы автоматически зачислены на баланс!");
        }

        // 3. Раз в минуту: истёкшие муты/баны, отпуска, временные роли, таймеры и ежедневные отчёты
        if ($tick % 4 === 0) {
            $pdo->query("UPDATE bot_punishments SET is_active = 0 WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at <= NOW()");
            $pdo->query("UPDATE bot_chat_members SET vacation_until = NULL, vacation_reason = NULL WHERE vacation_until IS NOT NULL AND vacation_until <= NOW()");

            // Автоснятие истёкших временных ролей (!temprole)
            $expRoles = $pdo->query("SELECT peer_id, vk_id, role_priority FROM bot_chat_members WHERE temp_role_until IS NOT NULL AND temp_role_until <= NOW() LIMIT 20")->fetchAll();
            foreach ($expRoles as $er) {
                $ep = (int)$er['peer_id'];
                $eu = (int)$er['vk_id'];
                $oldR = getRoleName($pdo, $ep, (int)$er['role_priority']);
                $pdo->prepare("UPDATE bot_chat_members SET role_priority = 0, temp_role_until = NULL WHERE peer_id = ? AND vk_id = ?")->execute([$ep, $eu]);
                sendMessage($ep, "⏳ Срок временной роли «{$oldR}» истёк.\n👤 Пользователь: " . formatUserMention($pdo, $ep, $eu) . " переведён в Участники.");
            }

            $now = time();
            $curHM = date('H:i');
            $today = date('Y-m-d');

            // Ежедневные авто-отчёты (!автоотчёт)
            $stRepChats = $pdo->prepare("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000 AND daily_report_time = ? AND (last_daily_report_date IS NULL OR last_daily_report_date != ?)");
            $stRepChats->execute([$curHM, $today]);
            foreach ($stRepChats->fetchAll(PDO::FETCH_COLUMN) as $rp) {
                $rpId = (int)$rp;
                $pdo->prepare("UPDATE bot_chats SET last_daily_report_date = ? WHERE peer_id = ?")->execute([$today, $rpId]);
                sendMessage($rpId, buildDailyReportText($pdo, $rpId), null, ['disable_mentions' => 1]);
            }

            // Плановые таймеры (!timeuved / !gtimeuvedg)
            $timers = $pdo->query("SELECT * FROM bot_timers WHERE is_active = 1")->fetchAll();
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
                        $allChats = $pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000")->fetchAll(PDO::FETCH_COLUMN);
                        $calls = [];
                        foreach ($allChats as $cp) {
                            $calls[] = [
                                'method' => 'messages.send',
                                'params' => [
                                    'peer_id'   => (int)$cp,
                                    'message'   => "📢 Автоуведомление:\n" . $t['message_text'],
                                    'random_id' => random_int(1, PHP_INT_MAX),
                                ]
                            ];
                        }
                        vkApiMulti($calls, 10);
                    }
                }
            }
        }

        // 4. Раз в 10 минут: автоматическая синхронизация всех бесед (чтобы не прописывать !sync вручную) + чистка /tmp
        if ($tick % 40 === 1) {
            $syncChats = $pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000 AND autosync_enabled = 1 ORDER BY total_messages DESC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($syncChats as $sp) {
                @file_put_contents('/tmp/worker_hb.txt', (string)time());
                syncChatMembers($pdo, (int)$sp);
                usleep(250000); // 250 мс пауза между чатами, чтобы не словить лимит VK API
            }
            cleanupTmpCache();
        }
    } catch (Throwable $e) {
        file_put_contents('php://stderr', "[WORKER ERROR] " . $e->getMessage() . PHP_EOL);
        try { getDB(true); } catch (Throwable $ignore) {}
    }

    $tick++;
    sleep(15);
}
