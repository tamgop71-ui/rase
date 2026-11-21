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

    // 2.1. Системные события беседы (приглашение бота или нового участника)
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
                ? "✅ Создателю беседы выдана роль Владельца (100), админам со звездой ({$adminsCount}) — Администратор (60)."
                : "⚠️ Выдайте мне права администратора беседы и введите !sync (или !setup).";
            sendMessage($peerId, "👋 Привет! Я чат-менеджер RAZE RUSSIA (v6.0 TURBO).\n\n{$roleNote}\nПрефиксы команд: ! / . + -", $kb);
            exit('ok');
        }

        // Вошёл новый участник -> проверка бана и приветствие
        if (($actType === 'chat_invite_user' || $actType === 'chat_invite_user_by_link') && $actMemberId > 0) {
            $ctxJoin = ensureUserAndMember($pdo, $peerId, $actMemberId);
            $stmtBan = $pdo->prepare("SELECT reason FROM bot_punishments WHERE (peer_id = ? OR peer_id = 0) AND target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            $stmtBan->execute([$peerId, $actMemberId]);
            $banReason = $stmtBan->fetchColumn();

            if ($banReason !== false && $peerId > 2000000000) {
                sendMessage($peerId, "🚫 " . formatUserMention($pdo, $peerId, $actMemberId) . " находится в списке заблокированных (Причина: {$banReason}) и исключается из беседы.");
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

    // 2.2. Турбо-контекст: 1 SELECT (user+chat+member+is_muted) + 1 multi-table UPDATE
    $ctx = ensureUserAndMember($pdo, $peerId, $fromId, $text, $hasSticker);
    $isSuper = ((int)$ctx['global']['is_superadmin'] === 1);
    $myPriority = $isSuper ? 100 : (int)$ctx['member']['role_priority'];

    // Сохраняем cmid для команды !clear (только в беседах)
    if ($peerId > 2000000000 && $cmid > 0) {
        $pdo->prepare("INSERT IGNORE INTO bot_recent_messages (peer_id, cmid, from_vk_id) VALUES (?, ?, ?)")->execute([$peerId, $cmid, $fromId]);
    }

    // 2.3. Автоснятие AFK, если пользователь сам написал сообщение (кроме самой команды !афк)
    if (!empty($ctx['global']['afk_reason']) && !preg_match('/^[!\/\.\+\-](?:афк|afk)/iu', $text)) {
        $pdo->prepare("UPDATE bot_users SET afk_reason = NULL, afk_since = NULL WHERE vk_id = ?")->execute([$fromId]);
        sendMessage($peerId, "👋 С возвращением, " . formatUserMention($pdo, $peerId, $fromId) . "! Режим AFK снят.", null, ['disable_mentions' => 1]);
    }

    // 2.4. Прослушка (!listen)
    if (!empty($ctx['chat']['listen_target_vk_id']) && !empty($ctx['chat']['listen_until'])) {
        if (strtotime($ctx['chat']['listen_until']) > time()) {
            $listenerId = (int)$ctx['chat']['listen_target_vk_id'];
            if ($listenerId > 0 && $listenerId !== $fromId && $text !== '') {
                sendMessage($listenerId, "🎧 [Чат {$peerId}] " . formatUserMention($pdo, $peerId, $fromId) . ": {$text}");
            }
        }
    }

    // 2.5. Модерация в реальном времени (Мут, Тишина, Антифлуд, Фильтр слов, Автомод с Whitelist)
    if ($peerId > 2000000000 && !$isSuper) {
        // Режим тишины
        $silUntil = $ctx['chat']['silence_until'] ?? null;
        if ($silUntil && strtotime($silUntil) > time() && $myPriority <= 0) {
            if ($cmid > 0) deleteMessage($peerId, [$cmid]);
            exit('ok');
        }

        // Активный мут (уже получен в ensureUserAndMember без лишнего запроса!)
        if (!empty($ctx['member']['is_muted'])) {
            if ($cmid > 0) deleteMessage($peerId, [$cmid]);
            exit('ok');
        }

        // Антифлуд (в /tmp за <0.1 мс)
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

        // Фильтр запрещённых слов (!фильтр)
        if ($myPriority <= 0 && $text !== '') {
            $badWords = getChatFilters($pdo, $peerId);
            if (!empty($badWords)) {
                $lowText = mb_strtolower($text);
                foreach ($badWords as $bw) {
                    if ($bw !== '' && str_contains($lowText, $bw)) {
                        if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                        sendMessage($peerId, "🛡 " . formatUserMention($pdo, $peerId, $fromId) . ", ваше сообщение удалено фильтром запрещённых слов.");
                        exit('ok');
                    }
                }
            }
        }

        // Умный Автомод ссылок с поддержкой Белого списка (!whitelist)
        if ((int)($ctx['chat']['automod_enabled'] ?? 0) === 1 && $myPriority <= 0 && $text !== '') {
            $blocked = findBlockedLink($pdo, $peerId, $text);
            if ($blocked !== null) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                sendMessage($peerId, "🤖 " . formatUserMention($pdo, $peerId, $fromId) . ", сторонние ссылки и реклама запрещены! Разрешены только домены из !whitelist.");
                exit('ok');
            }
        }
    }

    // 2.6. Обработка нажатия на inline-кнопку (payload)
    if (!empty($msg['payload'])) {
        $payload = json_decode((string)$msg['payload'], true);
        if (!empty($payload['cmd']) && is_string($payload['cmd'])) {
            $text = $payload['cmd'];
        }
    }

    // 2.7. Разбор команд с префиксами: ! / . + -
    if ($text !== '' && preg_match('/^[!\/\.\+\-]([\wа-яё]+)(?:\s+(.*))?$/isu', $text, $matches)) {
        executeCommand($pdo, mb_strtolower($matches[1]), trim($matches[2] ?? ''), $peerId, $fromId, $ctx, $msg);
        exit('ok');
    }

    // 2.8. Уведомление об AFK, если упомянули или ответили AFK-пользователю
    if ($peerId > 2000000000) {
        $mentionedIds = [];
        if (!empty($msg['reply_message']['from_id']) && (int)$msg['reply_message']['from_id'] > 0) {
            $mentionedIds[] = (int)$msg['reply_message']['from_id'];
        }
        if ($text !== '' && preg_match_all('/\[id(\d+)\|[^\]]+\]/u', $text, $mm)) {
            foreach ($mm[1] as $mid) $mentionedIds[] = (int)$mid;
        }
        $mentionedIds = array_values(array_unique(array_filter($mentionedIds, fn($id) => $id > 0 && $id !== $fromId)));
        if (!empty($mentionedIds)) {
            $ph = implode(',', array_fill(0, count($mentionedIds), '?'));
            $stmtAfk = $pdo->prepare("SELECT vk_id, afk_reason, afk_since FROM bot_users WHERE vk_id IN ({$ph}) AND afk_reason IS NOT NULL LIMIT 2");
            $stmtAfk->execute($mentionedIds);
            while ($a = $stmtAfk->fetch()) {
                $since = $a['afk_since'] ? date('H:i', strtotime($a['afk_since'])) : '';
                sendMessage($peerId, "💤 " . formatUserMention($pdo, $peerId, (int)$a['vk_id']) . " сейчас AFK (с {$since} МСК): {$a['afk_reason']}", null, ['disable_mentions' => 1]);
            }
        }
    }

    // 2.9. Кастомные триггеры / автоответчик (!триггер) из быстрого /tmp кэша
    if ($text !== '') {
        $triggers = getChatTriggers($pdo, $peerId);
        if (!empty($triggers)) {
            $low = mb_strtolower($text);
            if (isset($triggers[$low])) {
                sendMessage($peerId, $triggers[$low]);
            }
        }
    }

    exit('ok');
}

exit('ok');
