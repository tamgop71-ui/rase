<?php
declare(strict_types=1);

$GLOBALS['_req_start'] = microtime(true);

// Мгновенный ответ на healthcheck и повторные запросы ВК (<0.3 мс, до загрузки модулей и БД)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    exit('ok');
}
if (isset($_SERVER['HTTP_X_RETRY_COUNTER'])) {
    exit('ok');
}

require_once __DIR__ . '/config.php';

$rawInput = file_get_contents('php://input');
if (!$rawInput) {
    exit('ok');
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

// 1.5. Фиксация редактирования сообщений для !историясоо (0 SQL-запросов, < 1 мс)
if ($data['type'] === 'message_edit') {
    $eMsg    = $data['object'] ?? [];
    $ePeerId = (int)($eMsg['peer_id'] ?? 0);
    $eFromId = (int)($eMsg['from_id'] ?? 0);
    $eCmid   = (int)($eMsg['conversation_message_id'] ?? 0);
    $eText   = (string)($eMsg['text'] ?? '');
    if ($ePeerId > 0 && $eCmid > 0) {
        require_once __DIR__ . '/helpers.php';
        recordMessageEdit($ePeerId, $eCmid, $eFromId, $eText);
    }
    exit('ok');
}

// 2. Обработка входящего сообщения / события с глобальной защитой от HTTP 500
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

        // 2.0. Мгновенный fast-path для !пинг / !статус (1–2 мс, без загрузки helpers.php и без подключения к MySQL)
        $fastText = $text;
        if (!empty($msg['payload'])) {
            $pTmp = json_decode((string)$msg['payload'], true);
            if (!empty($pTmp['cmd']) && is_string($pTmp['cmd'])) {
                $fastText = trim($pTmp['cmd']);
            }
        }
        if ($fastText !== '' && preg_match('/^[!\/\.\+\-](?:пинг|статус|ping|status)$/iu', $fastText)) {
            $cachedPerms = cacheGet("cmdp_{$peerId}", 60);
            $isDisabled = is_array($cachedPerms) && ((int)($cachedPerms['ping'] ?? 0) >= 101);
            if (!$isDisabled) {
                sendFastPingResponse($peerId, $msg);
                require_once __DIR__ . '/telemetry.php';
                recordTelemetryEvent($peerId, $text, $msg['attachments'] ?? [], true);
                exit('ok');
            }
        }

        require_once __DIR__ . '/helpers.php';
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

                        // Автоматическое обновление названия беседы при его изменении в ВК
            if ($actType === 'chat_title_update' && !empty($msg['action']['text'])) {
                $newTitle = mb_substr(trim((string)$msg['action']['text']), 0, 120);
                if ($newTitle !== '') {
                    $pdo->prepare("UPDATE bot_chats SET title = ? WHERE peer_id = ?")->execute([$newTitle, $peerId]);
                }
                exit('ok');
            }

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
                        $ownerAssigned = true;
                    } elseif (!empty($item['is_admin'])) {
                        $pdo->prepare("INSERT INTO bot_chat_members (peer_id, vk_id, role_priority) VALUES (?, ?, 60) ON DUPLICATE KEY UPDATE role_priority = GREATEST(role_priority, 60)")->execute([$peerId, $mid]);
                        $adminsCount++;
                    }
                }

                $kbRows = [[
                    ['label' => '📋 Помощь (!помощь)', 'payload' => ['cmd' => '!помощь'], 'color' => 'primary'],
                    ['label' => '⚙️ Настройки (!settings)', 'payload' => ['cmd' => '!settings'], 'color' => 'secondary']
                ]];
                if (!$ownerAssigned) {
                    $kbRows[] = [
                        ['label' => '👑 Активировать владельца (!setup)', 'payload' => ['cmd' => '!setup'], 'color' => 'positive']
                    ];
                }
                $kb = makeInlineKeyboard($kbRows);
                $roleNote = $ownerAssigned
                    ? "✅ Создателю беседы выдана роль Владельца (100), админам со звездой ({$adminsCount}) — Администратор (60)."
                    : "⚠️ Выдайте мне права администратора беседы и введите !sync (или !setup).";
                sendMessage($peerId, "👋 Привет! Я чат-менеджер RASE RUSSIA.\n\n{$roleNote}\nПрефиксы команд: ! / . + -", $kb);
                exit('ok');
            }

            // Вошёл новый участник -> проверка анти-рейда, системного бана, локального бана и приветствие
            if (($actType === 'chat_invite_user' || $actType === 'chat_invite_user_by_link') && $actMemberId > 0) {
                $ctxJoin = ensureUserAndMember($pdo, $peerId, $actMemberId);
                $isInvitedByOther = ($actType === 'chat_invite_user' && $fromId > 0 && $fromId !== $actMemberId);
                $inviterIsSuper = $isInvitedByOther && hasSuperAccess($pdo, $fromId);
                $inviterPriority = 0;

                if ($isInvitedByOther) {
                    $stInv = $pdo->prepare("SELECT role_priority FROM bot_chat_members WHERE peer_id = ? AND vk_id = ?");
                    $stInv->execute([$peerId, $fromId]);
                    $inviterPriority = (int)$stInv->fetchColumn();
                    $pdo->prepare("UPDATE bot_chat_members SET invited_by = ? WHERE peer_id = ? AND vk_id = ? AND invited_by IS NULL")
                        ->execute([$fromId, $peerId, $actMemberId]);
                }

                // 1) Анти-рейд режим (!антирейд) — пропускает только если пригласил админ 40+ или супер-доступ
                if ((int)($ctxJoin['chat']['antiraid_enabled'] ?? 0) === 1 && $peerId > 2000000000 && !hasSuperAccess($pdo, $actMemberId) && !$inviterIsSuper && $inviterPriority < 40) {
                    vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $actMemberId]);
                    sendMessage($peerId, "🚨 [АНТИ-РЕЙД] Вход в беседу временно закрыт.\n👤 Пользователь " . formatUserMention($pdo, $peerId, $actMemberId) . " исключён.", null, ['disable_mentions' => 1]);
                    exit('ok');
                }

                // 2) Проверка СИСТЕМНОГО БАНА (/sysban, peer_id = 0) — снять может ТОЛЬКО супер-доступ!
                $stmtSysBan = $pdo->prepare("SELECT * FROM bot_punishments WHERE peer_id = 0 AND target_vk_id = ? AND type = 'gban' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 1");
                $stmtSysBan->execute([$actMemberId]);
                $sysBan = $stmtSysBan->fetch();

                if ($sysBan && $peerId > 2000000000) {
                    $uTarget = formatUserMention($pdo, $peerId, $actMemberId);
                    if ($inviterIsSuper) {
                        $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = 0 AND target_vk_id = ? AND type = 'gban'")->execute([$actMemberId]);
                        $uInv = formatUserMention($pdo, $peerId, $fromId);
                        sendMessage($peerId, "🔓 Системная блокировка автоматически снята!\n👤 Пользователь: {$uTarget}\n👑 Пригласил: {$uInv} (Супер-доступ)");
                        sendToLogChat($pdo, "Беседа {$peerId}: {$uInv} (супер-доступ) пригласил {$uTarget} и автоматически снял /sysban.");
                    } else {
                        vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $actMemberId]);
                        $uBanner = !empty($sysBan['actor_vk_id']) ? formatUserMention($pdo, $peerId, (int)$sysBan['actor_vk_id']) : 'Система';
                        $term = $sysBan['expires_at'] ? ('до ' . date('d.m.Y H:i', strtotime($sysBan['expires_at'])) . ' МСК') : 'Навсегда';
                        sendMessage($peerId, "⛔ Вход запрещён (Системная блокировка)!\n👤 Пользователь: {$uTarget}\n👮 Заблокировал: {$uBanner}\n⏳ Срок: {$term}\n📝 Причина: {$sysBan['reason']}\n🔒 Снять системный бан может только пользователь с супер-доступом.", null, ['disable_mentions' => 1]);
                        exit('ok');
                    }
                }

                // 3) Проверка ОБЫЧНОГО БАНА в беседе — если пригласил с ролью >= 40 (или супер-доступ), бан снимается!
                $stmtBan = $pdo->prepare("SELECT * FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 1");
                $stmtBan->execute([$peerId, $actMemberId]);
                $chatBan = $stmtBan->fetch();

                if ($chatBan && $peerId > 2000000000) {
                    $uTarget = formatUserMention($pdo, $peerId, $actMemberId);
                    if ($isInvitedByOther && ($inviterPriority >= 40 || $inviterIsSuper)) {
                        $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type IN ('ban','gban')")->execute([$peerId, $actMemberId]);
                        $uInv = formatUserMention($pdo, $peerId, $fromId);
                        $invRole = $inviterIsSuper ? 'Супер-доступ' : (getRoleName($pdo, $peerId, $inviterPriority) . " ({$inviterPriority})");
                        sendMessage($peerId, "🔓 Блокировка в беседе автоматически снята!\n👤 Пользователь: {$uTarget}\n👮 Пригласил: {$uInv} ({$invRole})");
                        sendToLogChat($pdo, "Беседа {$peerId}: {$uInv} ({$invRole}) пригласил {$uTarget} — локальный бан снят автоматически.");
                    } else {
                        vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $actMemberId]);
                        $uBanner = !empty($chatBan['actor_vk_id']) ? formatUserMention($pdo, $peerId, (int)$chatBan['actor_vk_id']) : 'Система';
                        $term = $chatBan['expires_at'] ? ('до ' . date('d.m.Y H:i', strtotime($chatBan['expires_at'])) . ' МСК') : 'Навсегда';
                        sendMessage($peerId, "🚫 Пользователь заблокирован в беседе и исключён!\n👤 Участник: {$uTarget}\n👮 Заблокировал: {$uBanner}\n⏳ Срок: {$term}\n📝 Причина: {$chatBan['reason']}\nℹ️ Чтобы разблокировать приглашением, нужна роль от 40 и выше.", null, ['disable_mentions' => 1]);
                        exit('ok');
                    }
                }

                $welcome = trim((string)($ctxJoin['chat']['welcome_text'] ?? ''));
                if ($welcome !== '') {
                    sendMessage($peerId, "👋 " . formatUserMention($pdo, $peerId, $actMemberId) . ", " . $welcome);
                }
            }
            exit('ok');
        }

        // 2.1.5. Флаг команды для отложенной телеметрии (пишется ПОСЛЕ ответа пользователю)
        $isCmdEvent = ($text !== '' && (bool)preg_match('/^[!\/\.\+\-][\wа-яё]+/iu', $text)) || !empty($msg['payload']);

        // 2.2. Быстрый контекст (1 SELECT + 1 multi-table UPDATE)
        $ctx = ensureUserAndMember($pdo, $peerId, $fromId, $text, $hasSticker, true);
        $isSuper = ((int)$ctx['global']['is_superadmin'] === 1);
        $myPriority = $isSuper ? 100 : (int)$ctx['member']['role_priority'];

        // Глобальный системный бан (/sysban): если забаненный пишет в любой беседе — сразу кик и удаление сообщения
        if ($peerId > 2000000000 && !$isSuper && !empty($ctx['member']['sysban_reason'])) {
            if ($cmid > 0) deleteMessage($peerId, [$cmid]);
            vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $fromId]);
            exit('ok');
        }

        // Сохраняем cmid в локальный /tmp буфер для !clear (0 SQL-запросов!)
        if ($peerId > 2000000000 && $cmid > 0) {
            recordRecentMessage($peerId, $cmid, $fromId, $text);
        }

        // 2.3. Автоснятие AFK при написании сообщения
        if (!empty($ctx['global']['afk_reason']) && !preg_match('/^[!\/\.\+\-](?:афк|afk)/iu', $text)) {
            $pdo->prepare("UPDATE bot_users SET afk_reason = NULL, afk_since = NULL WHERE vk_id = ?")->execute([$fromId]);
            setAfkCache($fromId, null);
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

        // 2.5. Модерация в реальном времени (Тишина, Мут, Слоумод, Антифлуд, Фильтр, Автомод + Whitelist)
        if ($peerId > 2000000000 && !$isSuper) {
            $silUntil = $ctx['chat']['silence_until'] ?? null;
            if ($silUntil && strtotime($silUntil) > time() && $myPriority <= 0) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                exit('ok');
            }

            if (!empty($ctx['member']['is_muted'])) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                exit('ok');
            }

            // Медленный режим (!слоумод) — 0 SQL-запросов
            $slowSec = (int)($ctx['chat']['slowmode_sec'] ?? 0);
            if ($slowSec > 0 && $myPriority <= 0 && checkSlowmode($peerId, $fromId, $slowSec)) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                exit('ok');
            }

            // Антифлуд — 0 SQL-запросов
            if ((int)($ctx['chat']['antiflood_enabled'] ?? 0) === 1 && $myPriority <= 0) {
                if (checkAntiFlood($peerId, $fromId)) {
                    $expires = date('Y-m-d H:i:s', time() + 300);
                    $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, 0, 'mute', 'Антифлуд (автомут)', 1, ?)")
                        ->execute([$peerId, $fromId, $expires]);
                    deleteAndSend($peerId, $cmid > 0 ? [$cmid] : [], "🌊 " . formatUserMention($pdo, $peerId, $fromId) . " получил(а) автомут на 5 мин. за флуд!");
                    exit('ok');
                }
            }

            // Фильтр запрещённых слов (!фильтр) из /tmp кэша
            if ($myPriority <= 0 && $text !== '') {
                $badWords = getChatFilters($pdo, $peerId);
                if (!empty($badWords)) {
                    $lowText = mb_strtolower($text);
                    foreach ($badWords as $bw) {
                        if ($bw !== '' && str_contains($lowText, $bw)) {
                            deleteAndSend($peerId, $cmid > 0 ? [$cmid] : [], "🛡 " . formatUserMention($pdo, $peerId, $fromId) . ", ваше сообщение удалено фильтром запрещённых слов.");
                            exit('ok');
                        }
                    }
                }
            }

            // Анти-капс (!антикапс) — 0 SQL-запросов
            if ((int)($ctx['chat']['anticaps_enabled'] ?? 0) === 1 && $myPriority <= 0 && $text !== '' && isExcessiveCaps($text)) {
                deleteAndSend($peerId, $cmid > 0 ? [$cmid] : [], "🔡 " . formatUserMention($pdo, $peerId, $fromId) . ", пожалуйста, не пишите КАПСОМ!");
                exit('ok');
            }

            // Анти-стикер (!антистикер) — 0 SQL-запросов
            if ($hasSticker && (int)($ctx['chat']['antisticker_enabled'] ?? 0) === 1 && $myPriority <= 0) {
                if (checkAntiSticker($peerId, $fromId)) {
                    deleteAndSend($peerId, $cmid > 0 ? [$cmid] : [], "🎭 " . formatUserMention($pdo, $peerId, $fromId) . ", не спамьте стикерами!");
                    exit('ok');
                }
            }

            // Умный Автомод ссылок и вложений с Белым списком (!whitelist)
            if ((int)($ctx['chat']['automod_enabled'] ?? 0) === 1 && $myPriority <= 0 && ($text !== '' || !empty($msg['attachments']))) {
                $blocked = findBlockedLink($pdo, $peerId, $text, $msg['attachments'] ?? []);
                if ($blocked !== null) {
                    deleteAndSend($peerId, $cmid > 0 ? [$cmid] : [], "🤖 " . formatUserMention($pdo, $peerId, $fromId) . ", сторонние ссылки и реклама запрещены! Разрешены только домены из !whitelist.");
                    exit('ok');
                }
            }
        }

        // 2.6. Обработка нажатий на inline-кнопки (payload)
        if (!empty($msg['payload'])) {
            $payload = json_decode((string)$msg['payload'], true);
            if (!empty($payload['cmd']) && is_string($payload['cmd'])) {
                $text = $payload['cmd'];
            }
        }

        // 2.7. Разбор команд с префиксами: ! / . + - (с ленивой загрузкой commands.php и отложенной записью счётчиков)
        if ($text !== '' && preg_match('/^([!\/\.\+\-])([\wа-яё]+)(?:\s+(.*))?$/isu', $text, $matches)) {
            if (!$isSuper && (int)($ctx['global']['is_bot_banned'] ?? 0) === 1) {
                exit('ok');
            }
            if (!$isSuper && (int)getSystemSetting($pdo, 'maintenance_mode', '0') === 1) {
                sendMessage($peerId, "🛠 Бот временно находится на техническом обслуживании. Команды скоро будут доступны.");
                exit('ok');
            }
            require_once __DIR__ . '/commands.php';
            executeCommand($pdo, mb_strtolower($matches[2]), trim($matches[3] ?? ''), $peerId, $fromId, $ctx, $msg, $matches[1]);
            flushPendingStats($pdo, $ctx);
            require_once __DIR__ . '/telemetry.php';
            recordTelemetryEvent($peerId, $text, $msg['attachments'] ?? [], true);
            exit('ok');
        }

        // 2.8. Уведомление об AFK (читается из /tmp без SQL-запросов!)
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

        // 2.9. Кастомные триггеры / автоответчик (!триггер) из /tmp кэша
        if ($text !== '') {
            $triggers = getChatTriggers($pdo, $peerId);
            if (!empty($triggers)) {
                $low = mb_strtolower($text);
                if (isset($triggers[$low])) {
                    sendMessage($peerId, $triggers[$low]);
                }
            }
        }

        flushPendingStats($pdo, $ctx);
        require_once __DIR__ . '/telemetry.php';
        recordTelemetryEvent($peerId, $text, $msg['attachments'] ?? [], $isCmdEvent);
    } catch (Throwable $e) {
        if (isDbGoneAway($e)) {
            file_put_contents('php://stderr', "[DB RECONNECT] Lost connection, retrying request..." . PHP_EOL);
            try {
                $pdo = getDB(true);
                $msg    = $data['object']['message'] ?? [];
                $peerId = (int)($msg['peer_id'] ?? 0);
                $fromId = (int)($msg['from_id'] ?? 0);
                $text   = trim((string)($msg['text'] ?? ''));
                if ($fromId > 0 && $peerId > 0) {
                    if (!empty($msg['payload'])) {
                        $payload = json_decode((string)$msg['payload'], true);
                        if (!empty($payload['cmd']) && is_string($payload['cmd'])) $text = $payload['cmd'];
                    }
                    $ctx = ensureUserAndMember($pdo, $peerId, $fromId, $text, false);
                    if ($text !== '' && preg_match('/^([!\/\.\+\-])([\wа-яё]+)(?:\s+(.*))?$/isu', $text, $matches)) {
                        require_once __DIR__ . '/commands.php';
                        executeCommand($pdo, mb_strtolower($matches[2]), trim($matches[3] ?? ''), $peerId, $fromId, $ctx, $msg, $matches[1]);
                    }
                }
            } catch (Throwable $e2) {
                file_put_contents('php://stderr', "[BOT ERROR RETRY] " . $e2->getMessage() . PHP_EOL);
            }
        } else {
            file_put_contents('php://stderr', "[BOT ERROR] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL);
        }
    }

    exit('ok');
}

exit('ok');
