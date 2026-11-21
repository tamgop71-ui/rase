<?php
declare(strict_types=1);

$GLOBALS['_req_start'] = microtime(true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/commands.php';

// Защита от повторных запросов Callback API ВК
if (isset($_SERVER['HTTP_X_RETRY_COUNTER'])) {
    exit('ok');
}

$rawInput = file_get_contents('php://input');
if (!$rawInput) {
    exit('Access Denied');
}

$data = json_decode($rawInput, true);
if (!isset($data['type'])) {
    exit('Invalid JSON');
}

if (VK_SECRET_KEY !== '' && ($data['secret'] ?? '') !== VK_SECRET_KEY) {
    exit('Invalid Secret Key');
}

// 1. Подтверждение сервера
if ($data['type'] === 'confirmation') {
    exit(VK_CONFIRMATION_TOKEN);
}

// 2. Обработка входящего сообщения / события
if ($data['type'] === 'message_new') {
    $msg    = $data['object']['message'] ?? [];
    $peerId = (int)($msg['peer_id'] ?? 0);
    $fromId = (int)($msg['from_id'] ?? 0);
    $text   = trim((string)($msg['text'] ?? ''));
    $cmid   = (int)($msg['conversation_message_id'] ?? 0);

    if ($fromId <= 0 || $peerId <= 0) {
        exit('ok');
    }

    $pdo = getDB();

    // Проверка на стикеры
    $hasSticker = false;
    foreach (($msg['attachments'] ?? []) as $att) {
        if (($att['type'] ?? '') === 'sticker') {
            $hasSticker = true;
            break;
        }
    }

    // 2.1. Обработка системных событий беседы
    if (!empty($msg['action']['type'])) {
        $actType = (string)$msg['action']['type'];
        $actMemberId = (int)($msg['action']['member_id'] ?? 0);

        // Пригласили самого бота (member_id < 0)
        if ($actType === 'chat_invite_user' && $actMemberId < 0) {
            ensureUserAndMember($pdo, $peerId, $fromId);
            $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId]);
            $items = $res['response']['items'] ?? [];
            $profiles = $res['response']['profiles'] ?? [];
            $profMap = [];
            foreach ($profiles as $p) $profMap[(int)$p['id']] = $p;

            $ownerAssigned = false;
            $adminsCount = 0;
            foreach ($items as $item) {
                $mid = (int)($item['member_id'] ?? 0);
                if ($mid <= 0) continue;
                $fn = $profMap[$mid]['first_name'] ?? 'Игрок';
                $ln = $profMap[$mid]['last_name'] ?? '';
                $pdo->prepare("INSERT IGNORE INTO bot_users (vk_id, first_name, last_name) VALUES (?, ?, ?)")->execute([$mid, $fn, $ln]);

                if (!empty($item['is_owner'])) {
                    $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ?")->execute([$mid, $peerId]);
                    $pdo->prepare("INSERT INTO bot_chat_members (peer_id, vk_id, role_priority) VALUES (?, ?, 100) ON DUPLICATE KEY UPDATE role_priority = 100")->execute([$peerId, $mid]);
                    $ownerAssigned = true;
                } elseif (!empty($item['is_admin'])) {
                    $pdo->prepare("INSERT INTO bot_chat_members (peer_id, vk_id, role_priority) VALUES (?, ?, 60) ON DUPLICATE KEY UPDATE role_priority = GREATEST(role_priority, 60)")->execute([$peerId, $mid]);
                    $adminsCount++;
                }
            }

            $kb = makeInlineKeyboard([[
                ['label' => '📋 Помощь (!помощь)', 'payload' => ['cmd' => '!помощь'], 'color' => 'primary'],
                ['label' => '⚙️ Настройки (!settings)', 'payload' => ['cmd' => '!settings'], 'color' => 'secondary']
            ]]);
            $roleNote = $ownerAssigned
                ? "✅ Создателю беседы автоматически выдана роль Владельца (100), администраторам со звездой ({$adminsCount}) — Администратор (60)."
                : "⚠️ Выдайте мне права администратора беседы и напишите !sync (или !setup), чтобы я автоматически раздал роли.";
            sendMessage($peerId, "👋 Привет! Я чат-менеджер RAZE RUSSIA (v5.0 IDEAL).\n\n{$roleNote}\nПрефиксы команд: ! / . + -", $kb);
            exit('ok');
        }

        // Вошел новый участник -> проверка бана и приветствие
        if (($actType === 'chat_invite_user' || $actType === 'chat_invite_user_by_link') && $actMemberId > 0) {
            $ctxJoin = ensureUserAndMember($pdo, $peerId, $actMemberId);
            $stmtBan = $pdo->prepare("SELECT reason FROM bot_punishments WHERE (peer_id = ? OR peer_id = 0) AND target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            $stmtBan->execute([$peerId, $actMemberId]);
            $banReason = $stmtBan->fetchColumn();

            if ($banReason !== false && $peerId > 2000000000) {
                $uB = formatUserMention($pdo, $peerId, $actMemberId);
                sendMessage($peerId, "🚫 {$uB} находится в списке заблокированных (Причина: {$banReason}) и исключается из беседы.");
                vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $actMemberId]);
                exit('ok');
            }

            $welcome = trim((string)($ctxJoin['chat']['welcome_text'] ?? ''));
            if ($welcome !== '') {
                sendMessage($peerId, "👋 " . formatUserMention($pdo, $peerId, $actMemberId) . ", " . $welcome);
            }
        }
        exit('ok');
    }

    // Регистрация и получение контекста за 1 запрос
    $ctx = ensureUserAndMember($pdo, $peerId, $fromId, $text, $hasSticker);
    $isSuper = ((int)$ctx['global']['is_superadmin'] === 1);
    $myPriority = $isSuper ? 100 : (int)$ctx['member']['role_priority'];

    // Сохраняем cmid недавних сообщений для команды !clear (только для бесед)
    if ($peerId > 2000000000 && $cmid > 0) {
        $pdo->prepare("INSERT IGNORE INTO bot_recent_messages (peer_id, cmid, from_vk_id) VALUES (?, ?, ?)")->execute([$peerId, $cmid, $fromId]);
    }

    // 2.2. Прослушка (!listen)
    if (!empty($ctx['chat']['listen_target_vk_id']) && !empty($ctx['chat']['listen_until'])) {
        if (strtotime($ctx['chat']['listen_until']) > time()) {
            $listenerId = (int)$ctx['chat']['listen_target_vk_id'];
            if ($listenerId > 0 && $listenerId !== $fromId && $text !== '') {
                sendMessage($listenerId, "🎧 [Чат {$peerId}] " . formatUserMention($pdo, $peerId, $fromId) . ": {$text}");
            }
        }
    }

    // 2.3. Автомодерация, Мут, Режим тишины и Антифлуд (только в беседах для не-админов)
    if ($peerId > 2000000000 && !$isSuper) {
        // Режим тишины
        $silUntil = $ctx['chat']['silence_until'] ?? null;
        if ($silUntil && strtotime($silUntil) > time() && $myPriority <= 0) {
            if ($cmid > 0) deleteMessage($peerId, [$cmid]);
            exit('ok');
        }

        // Активный мут
        $stmtMute = $pdo->prepare("SELECT id FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
        $stmtMute->execute([$peerId, $fromId]);
        if ($stmtMute->fetchColumn()) {
            if ($cmid > 0) deleteMessage($peerId, [$cmid]);
            exit('ok');
        }

        // Антифлуд
        if ((int)($ctx['chat']['antiflood_enabled'] ?? 0) === 1 && $myPriority <= 0) {
            if (checkAntiFlood($peerId, $fromId)) {
                $expires = date('Y-m-d H:i:s', time() + 300);
                $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, 0, 'mute', 'Антифлуд (автомут)', 1, ?)")
                    ->execute([$peerId, $fromId, $expires]);
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                sendMessage($peerId, "🌊 " . formatUserMention($pdo, $peerId, $fromId) . " получил(а) автомут на 5 мин. за флуд!");
                exit('ok');
            }
        }

        // Автомодерация ссылок и рекламы
        if ((int)($ctx['chat']['automod_enabled'] ?? 0) === 1 && $myPriority <= 0 && $text !== '') {
            if (preg_match('/(https?:\/\/|vk\.cc\/|t\.me\/|discord\.gg\/|vk\.(?:com|ru)\/(?:club|public|join))/iu', $text)) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                sendMessage($peerId, "🤖 " . formatUserMention($pdo, $peerId, $fromId) . ", ссылки и реклама запрещены в этой беседе!");
                exit('ok');
            }
        }
    }

    // 2.4. Обработка нажатий на inline-кнопки (payload)
    if (!empty($msg['payload'])) {
        $payload = json_decode((string)$msg['payload'], true);
        if (!empty($payload['cmd']) && is_string($payload['cmd'])) {
            $text = $payload['cmd'];
        }
    }

    // 2.5. Парсинг команд с префиксами: ! / . + -
    if ($text !== '' && preg_match('/^[!\/\.\+\-]([\wа-яё]+)(?:\s+(.*))?$/isu', $text, $matches)) {
        executeCommand($pdo, mb_strtolower($matches[1]), trim($matches[2] ?? ''), $peerId, $fromId, $ctx, $msg);
    }

    // 2.6. Легкая фоновая очистка и таймеры (раз в ~20 сообщений, чтобы не тормозить ответы)
    if (random_int(1, 20) === 1) {
        processTimers($pdo);
    }

    exit('ok');
}

exit('ok');

function processTimers(PDO $pdo): void {
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
