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
    try {
        $msg    = $data['object']['message'] ?? [];
        $peerId = (int)($msg['peer_id'] ?? 0);
        $fromId = (int)($msg['from_id'] ?? 0);
        $text   = trim((string)($msg['text'] ?? ''));
        $cmid   = (int)($msg['conversation_message_id'] ?? 0);

        if ($fromId <= 0 || $peerId <= 0) {
            exit('ok');
        }

        // 2.0. Сверхбыстрый Fast-Path (0 SQL-запросов!) для !пинг / !статус / !test
        if (empty($msg['action']) && $text !== '' && preg_match('/^[!\/\.\+\-](?:пинг|ping|статус|status|тест|test)$/iu', $text)) {
            queueStatIncrement($peerId, $fromId, mb_strlen($text), 0);
            $evDelay = max(0, time() - (int)($msg['date'] ?? time()));
            $procMs = max(1, (int)round((microtime(true) - $GLOBALS['_req_start']) * 1000));
            $tApi = microtime(true);
            $res = sendMessage($peerId, "⚙️ Понг! Задержка события: {$evDelay} с | Обработка: {$procMs} мс | API: ...");
            $apiMs = number_format((microtime(true) - $tApi) * 1000, 2, '.', '');
            $sentCmid = (int)($res['response']['cmid'] ?? $res['response']['conversation_message_id'] ?? 0);
            if ($sentCmid > 0) {
                editMessage($peerId, $sentCmid, "⚙️ Понг! Задержка события: {$evDelay} с | Обработка: {$procMs} мс | API: {$apiMs} мс");
            }
            exit('ok');
        }

        // 2.0b. Мгновенная проверка нажатия кнопки капчи «✅ Я не бот» (0 SQL-запросов!)
        if (!empty($msg['payload'])) {
            $payloadData = json_decode((string)$msg['payload'], true);
            if (!empty($payloadData['captcha_uid']) && (int)$payloadData['captcha_uid'] === $fromId) {
                if (removeCaptchaPending($peerId, $fromId)) {
                    sendMessage($peerId, "✅ Проверка пройдена! Добро пожаловать в беседу, [id{$fromId}|участник].");
                }
                exit('ok');
            }
            if (!empty($payloadData['cmd']) && is_string($payloadData['cmd'])) {
                $text = $payloadData['cmd'];
            }
        }

        $pdo = getDB();

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
                refreshChatTitle($pdo, $peerId);

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
                        invalidateMemberCache($peerId, $mid);
                        $ownerAssigned = true;
                    } elseif (!empty($item['is_admin'])) {
                        $pdo->prepare("INSERT INTO bot_chat_members (peer_id, vk_id, role_priority) VALUES (?, ?, 60) ON DUPLICATE KEY UPDATE role_priority = GREATEST(role_priority, 60)")->execute([$peerId, $mid]);
                        invalidateMemberCache($peerId, $mid);
                        $adminsCount++;
                    }
                }
                invalidateChatCache($peerId);

                $kb = makeInlineKeyboard([[
                    ['label' => '📋 Помощь (!помощь)', 'payload' => ['cmd' => '!помощь'], 'color' => 'primary'],
                    ['label' => '⚙️ Настройки (!settings)', 'payload' => ['cmd' => '!settings'], 'color' => 'secondary']
                ]]);
                $roleNote = $ownerAssigned
                    ? "✅ Создателю беседы выдана роль Владельца (100), админам со звездой ({$adminsCount}) — Администратор (60)."
                    : "⚠️ Выдайте мне права администратора беседы и введите !sync (или !setup).";
                sendMessage($peerId, "👋 Привет! Я чат-менеджер RASE RUSSIA.\n\n{$roleNote}\nПрефиксы команд: ! / . + -", $kb);
                exit('ok');
            }

            // Вошёл новый участник -> анти-рейд, проверка бана, капча и приветствие
            if (($actType === 'chat_invite_user' || $actType === 'chat_invite_user_by_link') && $actMemberId > 0) {
                $ctxJoin = ensureUserAndMember($pdo, $peerId, $actMemberId);

                // Анти-рейд (!антирейд)
                if ((int)($ctxJoin['chat']['antiraid_enabled'] ?? 0) === 1 && $peerId > 2000000000 && !hasSuperAccess($pdo, $actMemberId)) {
                    vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $actMemberId]);
                    sendMessage($peerId, "🚨 [АНТИ-РЕЙД] Вход в беседу временно закрыт. Пользователь " . formatUserMention($pdo, $peerId, $actMemberId) . " исключён.", null, ['disable_mentions' => 1]);
                    exit('ok');
                }

                // Проверка активного бана / гбана
                $stmtBan = $pdo->prepare("SELECT reason FROM bot_punishments WHERE (peer_id = ? OR peer_id = 0) AND target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
                $stmtBan->execute([$peerId, $actMemberId]);
                $banReason = $stmtBan->fetchColumn();

                if ($banReason !== false && $peerId > 2000000000) {
                    sendMessage($peerId, "🚫 " . formatUserMention($pdo, $peerId, $actMemberId) . " находится в списке заблокированных (Причина: {$banReason}) и исключается из беседы.");
                    vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $actMemberId]);
                    exit('ok');
                }

                // Авто-капча при входе (!капча)
                if ((int)($ctxJoin['chat']['captcha_enabled'] ?? 0) === 1 && $peerId > 2000000000 && !hasSuperAccess($pdo, $actMemberId)) {
                    addCaptchaPending($peerId, $actMemberId, 60);
                    $kbCap = makeInlineKeyboard([[
                        ['label' => '✅ Я не бот', 'payload' => ['captcha_uid' => $actMemberId], 'color' => 'positive']
                    ]]);
                    sendMessage($peerId, "🧩 " . formatUserMention($pdo, $peerId, $actMemberId) . ", нажмите кнопку «✅ Я не бот» в течение 60 секунд, иначе вы будете исключены.", $kbCap);
                    exit('ok');
                }

                $welcome = trim((string)($ctxJoin['chat']['welcome_text'] ?? ''));
                if ($welcome !== '') {
                    sendMessage($peerId, "👋 " . formatUserMention($pdo, $peerId, $actMemberId) . ", " . $welcome);
                }
            }
            exit('ok');
        }

        // Сохраняем cmid в локальный /tmp буфер для !clear (0 SQL-запросов!)
        if ($peerId > 2000000000 && $cmid > 0) {
            recordRecentMessage($peerId, $cmid, $fromId);
        }

        // Проверяем, является ли сообщение командой
        $isCommand = ($text !== '' && preg_match('/^[!\/\.\+\-]([\wа-яё]+)(?:\s+(.*))?$/isu', $text, $cmdMatches));

        // 2.2. Если это НЕ команда — идём по сверхбыстрому пути из /tmp-кэша (0 SQL-запросов!)
        if (!$isCommand) {
            $fast = getFastModContext($pdo, $peerId, $fromId);
            $chat = $fast['chat'];
            $member = $fast['member'];
            $isSuper = ((int)($member['is_superadmin'] ?? 0) === 1);
            $myPriority = $isSuper ? 100 : (int)($member['role_priority'] ?? 0);

            if ($fast['cached']) {
                queueStatIncrement($peerId, $fromId, mb_strlen($text), $hasSticker ? 1 : 0);
            }
        } else {
            // Для команд загружаем актуальный полный профиль (1 SELECT, статистика уходит в очередь)
            $ctx = ensureUserAndMember($pdo, $peerId, $fromId, $text, $hasSticker);
            $chat = $ctx['chat'];
            $member = $ctx['member'];
            $isSuper = ((int)$ctx['global']['is_superadmin'] === 1);
            $myPriority = $isSuper ? 100 : (int)$member['role_priority'];
        }

        // 2.3. Автоснятие AFK при написании сообщения
        if (!empty($member['afk_reason']) && !preg_match('/^[!\/\.\+\-](?:афк|afk)/iu', $text)) {
            $pdo->prepare("UPDATE bot_users SET afk_reason = NULL, afk_since = NULL WHERE vk_id = ?")->execute([$fromId]);
            setAfkCache($fromId, null);
            invalidateMemberCache($peerId, $fromId);
            sendMessage($peerId, "👋 С возвращением, " . formatUserMention($pdo, $peerId, $fromId) . "! Режим AFK снят.", null, ['disable_mentions' => 1]);
        }

        // 2.4. Прослушка (!listen)
        if (!empty($chat['listen_target_vk_id']) && !empty($chat['listen_until'])) {
            if (strtotime($chat['listen_until']) > time()) {
                $listenerId = (int)$chat['listen_target_vk_id'];
                if ($listenerId > 0 && $listenerId !== $fromId && $text !== '') {
                    sendMessage($listenerId, "🎧 [Чат {$peerId}] " . formatUserMention($pdo, $peerId, $fromId) . ": {$text}");
                }
            }
        }

        // 2.5. Модерация в реальном времени (Sysban, Капча, Тишина, Мут, Слоумод, Антистикер, Антифлуд, Фильтр, Автомод + Whitelist)
        if ($peerId > 2000000000 && !$isSuper) {
            // Мгновенная проверка системного бана (/sysban) из /tmp кэша
            $sysBanReason = getSysBanFromCache($fromId);
            if ($sysBanReason !== null) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $fromId]);
                exit('ok');
            }
            // Если пользователь не прошёл капчу — удаляем его сообщения
            if (isCaptchaPending($peerId, $fromId)) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                exit('ok');
            }

            $silUntil = $chat['silence_until'] ?? null;
            if ($silUntil && strtotime($silUntil) > time() && $myPriority <= 0) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                exit('ok');
            }

            if (!empty($member['is_muted'])) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                exit('ok');
            }

            // Анти-стикер (!антистикер)
            if ($hasSticker && (int)($chat['antisticker_enabled'] ?? 0) === 1 && $myPriority <= 0) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                exit('ok');
            }

            // Медленный режим (!слоумод)
            $slowSec = (int)($chat['slowmode_sec'] ?? 0);
            if ($slowSec > 0 && $myPriority <= 0 && checkSlowmode($peerId, $fromId, $slowSec)) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                exit('ok');
            }

            // Антифлуд
            if ((int)($chat['antiflood_enabled'] ?? 0) === 1 && $myPriority <= 0) {
                if (checkAntiFlood($peerId, $fromId)) {
                    $expires = date('Y-m-d H:i:s', time() + 300);
                    $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, 0, 'mute', 'Антифлуд (автомут)', 1, ?)")
                        ->execute([$peerId, $fromId, $expires]);
                    invalidateMemberCache($peerId, $fromId);
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

            // Автомод ссылок с Белым списком (!whitelist)
            if ((int)($chat['automod_enabled'] ?? 0) === 1 && $myPriority <= 0 && $text !== '') {
                $blocked = findBlockedLink($pdo, $peerId, $text);
                if ($blocked !== null) {
                    if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                    sendMessage($peerId, "🤖 " . formatUserMention($pdo, $peerId, $fromId) . ", сторонние ссылки и реклама запрещены! Разрешены только домены из !whitelist.");
                    exit('ok');
                }
            }
        }

        // 2.6. Выполнение команды
        if ($isCommand) {
            executeCommand($pdo, mb_strtolower($cmdMatches[1]), trim($cmdMatches[2] ?? ''), $peerId, $fromId, $ctx, $msg);
            exit('ok');
        }

        // 2.7. Уведомление об AFK (из /tmp, 0 SQL-запросов)
        if ($peerId > 2000000000) {
            $mentionedIds = [];
            if (!empty($msg['reply_message']['from_id']) && (int)$msg['reply_message']['from_id'] > 0) {
                $mentionedIds[] = (int)$msg['reply_message']['from_id'];
            }
            if ($text !== '' && preg_match_all('/\[id(\d+)\|[^\]]+\]/u', $text, $mm)) {
                foreach ($mm[1] as $mid) $mentionedIds[] = (int)$mid;
            }
            $mentionedIds = array_values(array_unique(array_filter($mentionedIds, fn($id) => $id > 0 && $id !== $fromId)));
            $notified = 0;
            foreach ($mentionedIds as $mid) {
                $afk = getAfkFromCache($mid);
                if ($afk !== null) {
                    sendMessage($peerId, "💤 " . formatUserMention($pdo, $peerId, $mid) . " сейчас AFK (с {$afk['t']} МСК): {$afk['r']}", null, ['disable_mentions' => 1]);
                    $notified++;
                    if ($notified >= 2) break;
                }
            }
        }

        // 2.8. Кастомные триггеры / автоответчик (!триггер)
        if ($text !== '') {
            $triggers = getChatTriggers($pdo, $peerId);
            if (!empty($triggers)) {
                $low = mb_strtolower($text);
                if (isset($triggers[$low])) {
                    sendMessage($peerId, $triggers[$low]);
                }
            }
        }
    } catch (Throwable $e) {
        file_put_contents('php://stderr', "[BOT ERROR] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL);
    }

    exit('ok');
}

exit('ok');
