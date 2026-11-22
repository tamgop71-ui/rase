<?php
declare(strict_types=1);

/**
 * Обработчик команд Помощника (20+), Модератора (40+), Администратора (60+),
 * Главного админа (80+), Владельца (100) и Супер-доступа (/addws).
 */
function handleModAndAdminCommands(PDO $pdo, string $cmd, string $args, int $peerId, int $fromId, array $ctx, array $msg, int $myPriority, bool $isSuper): bool {
    switch ($cmd) {
        // ==========================================
        // 1. ПОМОЩНИК (ПРИОРИТЕТ >= 20)
        // ==========================================
        case 'stats':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ($targetId === $fromId) ? $ctx : ensureUserAndMember($pdo, $peerId, $targetId);
            $m = $tCtx['member'];
            $g = $tCtx['global'];
            $rName = getRoleName($pdo, $peerId, (int)$m['role_priority']);
            $stmtW = $pdo->prepare("SELECT COUNT(*) FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1");
            $stmtW->execute([$peerId, $targetId]);
            $warns = (int)$stmtW->fetchColumn();
            $uM = formatUserMention($pdo, $peerId, $targetId);
            $reg = date('d.m.Y H:i', strtotime($m['joined_at'] ?? 'now'));
            $act = date('d.m.Y H:i', strtotime($m['last_active_at'] ?? 'now'));
            sendMessage($peerId, "📊 Статистика {$uM}:\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "🛡 Роль: {$rName} ({$m['role_priority']})\n"
                . "🏷 Никнейм: " . ($m['nickname'] ?: 'Не установлен') . "\n"
                . "⚠️ Предупреждения: {$warns}/3\n"
                . "✉️ Сообщений: {$m['messages_count']} (Символов: {$m['chars_count']})\n"
                . "🎭 Стикеров: {$m['stickers_count']}\n"
                . "📅 Регистрация: {$reg} МСК\n"
                . "🕒 Активность: {$act} МСК", null, ['disable_mentions' => 1]);
            return true;

        case 'chatinfo':
            $stmtM = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(messages_count),0) AS msgs, SUM(CASE WHEN nickname IS NOT NULL AND nickname != '' THEN 1 ELSE 0 END) AS nicks, SUM(CASE WHEN role_priority > 0 THEN 1 ELSE 0 END) AS adms FROM bot_chat_members WHERE peer_id = ?");
            $stmtM->execute([$peerId]);
            $stat = $stmtM->fetch();
            $c = $ctx['chat'];
            $unityStr = !empty($c['unity_id']) ? "ID #{$c['unity_id']}" : "Не состоит";
            $ownerStr = !empty($c['owner_vk_id']) ? formatUserMention($pdo, $peerId, (int)$c['owner_vk_id']) : "Не назначен (!setup)";
            $silStr = (!empty($c['silence_until']) && strtotime($c['silence_until']) > time()) ? "Включена до " . date('d.m H:i', strtotime($c['silence_until'])) : "Выключена";
            sendMessage($peerId, "📊 Информация о беседе (ID: {$peerId}):\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "👑 Владелец: {$ownerStr}\n"
                . "🔗 Объединение: {$unityStr}\n"
                . "👥 Участников в БД: " . (int)$stat['cnt'] . " (С никами: " . (int)$stat['nicks'] . ")\n"
                . "🛡 Администрации: " . (int)$stat['adms'] . " чел.\n"
                . "✉️ Всего сообщений: " . number_format((int)$stat['msgs'], 0, '.', ' ') . "\n"
                . "🔇 Тишина: {$silStr}\n"
                . "🎮 Игры: " . ((int)$c['games_enabled'] ? "Вкл" : "Выкл")
                . " | 🤖 Автомод: " . ((int)$c['automod_enabled'] ? "Вкл" : "Выкл")
                . " | 🌊 Антифлуд: " . ((int)$c['antiflood_enabled'] ? "Вкл" : "Выкл"), null, ['disable_mentions' => 1]);
            return true;

        case 'mute':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !мут [ID/упоминание] [минуты] (причина)"); return true; }
            $minutes = 30; $reason = 'не указана';
            if (preg_match('/^(\d+)(?:\s+(.*))?$/isu', trim($args), $m)) {
                $minutes = max(1, min(43200, (int)$m[1]));
                if (!empty($m[2])) $reason = trim($m[2]);
            } elseif (trim($args) !== '') { $reason = trim($args); }

            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, 'mute', $minutes, $reason);
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }

            $expiresTs = time() + ($minutes * 60);
            $expires = date('Y-m-d H:i:s', $expiresTs);
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute'")->execute([$peerId, $targetId]);
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, ?, 'mute', ?, 1, ?)")
                ->execute([$peerId, $targetId, $fromId, mb_substr($reason, 0, 250), $expires]);
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            $aRole = $isSuper ? 'Супер-доступ' : getRoleName($pdo, $peerId, $myPriority);
            sendMessage($peerId, "🔇 Пользователь заглушён\n👤 Нарушитель: {$tM}\n👮 Выдал: {$aM} ({$aRole})\n⏳ Срок: {$minutes} мин. (до " . date('d.m.Y H:i', $expiresTs) . " МСК)\n📝 Причина: {$reason}");
            sendToLogChat($pdo, "Беседа {$peerId}: {$aM} выдал мут {$tM} ({$minutes} мин). Причина: {$reason}");
            return true;

        case 'unmute':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !унмут [ID/упоминание]"); return true; }
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute'")->execute([$peerId, $targetId]);
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🔊 Мут снят\n👤 Пользователь: {$tM}\n👮 Снял: {$aM}");
            return true;

        case 'kick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !кик [ID/упоминание] (причина)"); return true; }
            $reason = trim($args) !== '' ? trim($args) : 'не указана';

            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, 'kick', 0, $reason);
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }

            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active) VALUES (?, ?, ?, 'kick', ?, 0)")
                ->execute([$peerId, $targetId, $fromId, mb_substr($reason, 0, 250)]);
            if ($peerId > 2000000000) {
                vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
            }
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            $aRole = $isSuper ? 'Супер-доступ' : getRoleName($pdo, $peerId, $myPriority);
            sendMessage($peerId, "👢 Пользователь исключён из беседы\n👤 Участник: {$tM}\n👮 Исключил: {$aM} ({$aRole})\n📝 Причина: {$reason}");
            sendToLogChat($pdo, "Беседа {$peerId}: {$aM} кикнул {$tM}. Причина: {$reason}");
            checkAntiDrain($pdo, $peerId, $fromId, $myPriority, $isSuper, $ctx['chat']);
            return true;

        case 'warn':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !пред [ID/упоминание] (причина)"); return true; }
            $reason = trim($args) !== '' ? trim($args) : 'не указана';

            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, 'warn', 0, $reason);
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }

            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active) VALUES (?, ?, ?, 'warn', ?, 1)")
                ->execute([$peerId, $targetId, $fromId, mb_substr($reason, 0, 250)]);
            $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1");
            $stmtCnt->execute([$peerId, $targetId]);
            $wCount = (int)$stmtCnt->fetchColumn();
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            $aRole = $isSuper ? 'Супер-доступ' : getRoleName($pdo, $peerId, $myPriority);
            if ($wCount >= 3) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn'")->execute([$peerId, $targetId]);
                sendMessage($peerId, "⚠️ Выдано предупреждение (3/3)\n👤 Нарушитель: {$tM}\n👮 Выдал: {$aM} ({$aRole})\n📝 Причина: {$reason}\n🚫 Лимит исчерпан — пользователь исключается из беседы.");
                if ($peerId > 2000000000) {
                    vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
                }
            } else {
                sendMessage($peerId, "⚠️ Выдано предупреждение ({$wCount}/3)\n👤 Нарушитель: {$tM}\n👮 Выдал: {$aM} ({$aRole})\n📝 Причина: {$reason}");
            }
            sendToLogChat($pdo, "Беседа {$peerId}: {$aM} выдал варн ({$wCount}/3) {$tM}. Причина: {$reason}");
            return true;

        case 'unwarn':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !унварн [ID/упоминание]"); return true; }
            $stmtLast = $pdo->prepare("SELECT id FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmtLast->execute([$peerId, $targetId]);
            $lastId = $stmtLast->fetchColumn();
            $tM = formatUserMention($pdo, $peerId, $targetId);
            if (!$lastId) { sendMessage($peerId, "ℹ️ У {$tM} нет активных предупреждений."); return true; }
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE id = ?")->execute([$lastId]);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "✅ Предупреждение снято\n👤 Пользователь: {$tM}\n👮 Снял: {$aM}");
            return true;

        case 'getwarns':
        case 'getwarn':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1 ORDER BY id ASC");
            $stmt->execute([$peerId, $targetId]);
            $warns = $stmt->fetchAll();
            $tM = formatUserMention($pdo, $peerId, $targetId);
            if (empty($warns)) { sendMessage($peerId, "✅ У {$tM} нет активных предупреждений (0/3)."); return true; }
            preloadUsers($pdo, $peerId, array_column($warns, 'actor_vk_id'));
            $lines = ["⚠️ Активные предупреждения {$tM} (" . count($warns) . "/3):"];
            $i = 1;
            foreach ($warns as $w) {
                $actor = formatUserMention($pdo, $peerId, (int)$w['actor_vk_id']);
                $dt = date('d.m.Y H:i', strtotime($w['created_at']));
                $lines[] = "{$i}. Выдал: {$actor} | {$w['reason']} | {$dt} МСК";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'warnlist':
            $page = max(1, (int)trim($args));
            $limit = 10; $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT target_vk_id, COUNT(*) AS cnt FROM bot_punishments WHERE peer_id = ? AND type = 'warn' AND is_active = 1 GROUP BY target_vk_id ORDER BY cnt DESC LIMIT ? OFFSET ?");
            $stmt->execute([$peerId, $limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "✅ Пользователей с предупреждениями нет (Страница {$page})."); return true; }
            preloadUsers($pdo, $peerId, array_column($rows, 'target_vk_id'));
            $lines = ["⚠️ Список предупреждений (Страница {$page}):"];
            $i = $offset + 1;
            foreach ($rows as $r) {
                $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['target_vk_id']) . " — {$r['cnt']}/3";
                $i++;
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!warnlist ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!warnlist ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
            return true;

        case 'getban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE (peer_id = ? OR peer_id = 0) AND target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$peerId, $targetId]);
            $b = $stmt->fetch();
            $tM = formatUserMention($pdo, $peerId, $targetId);
            if (!$b) { sendMessage($peerId, "✅ {$tM} не находится в списке заблокированных."); return true; }
            $actor = formatUserMention($pdo, $peerId, (int)$b['actor_vk_id']);
            $dtStart = date('d.m.Y H:i', strtotime($b['created_at']));
            $dtEnd = $b['expires_at'] ? ('до ' . date('d.m.Y H:i', strtotime($b['expires_at'])) . ' МСК') : 'Навсегда';
            sendMessage($peerId, "🚫 Информация о блокировке {$tM}:\n• Заблокировал: {$actor}\n• Дата: {$dtStart} МСК\n• Срок: {$dtEnd}\n• Причина: {$b['reason']}", null, ['disable_mentions' => 1]);
            return true;

        case 'getmute':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 1");
            $stmt->execute([$peerId, $targetId]);
            $m = $stmt->fetch();
            $tM = formatUserMention($pdo, $peerId, $targetId);
            if (!$m) { sendMessage($peerId, "✅ У {$tM} нет активного мута."); return true; }
            $actor = formatUserMention($pdo, $peerId, (int)$m['actor_vk_id']);
            $dtStart = date('d.m.Y H:i', strtotime($m['created_at']));
            $dtEnd = $m['expires_at'] ? ('до ' . date('d.m.Y H:i', strtotime($m['expires_at'])) . ' МСК') : 'Бессрочно';
            sendMessage($peerId, "🔇 Информация о муте {$tM}:\n• Замутил: {$actor}\n• Дата: {$dtStart} МСК\n• Истекает: {$dtEnd}\n• Причина: {$m['reason']}", null, ['disable_mentions' => 1]);
            return true;

        case 'history':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if ($targetId && $targetId !== $fromId && $myPriority < 20 && !$isSuper) {
                sendMessage($peerId, "⛔ Просматривать чужую историю наказаний могут Помощники (20+) и выше.");
                return true;
            }
            $targetId = $targetId ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? ORDER BY id DESC LIMIT 15");
            $stmt->execute([$peerId, $targetId]);
            $rows = $stmt->fetchAll();
            $uM = formatUserMention($pdo, $peerId, $targetId);
            if (empty($rows)) { sendMessage($peerId, "📜 История наказаний {$uM} чиста."); return true; }
            preloadUsers($pdo, $peerId, array_column($rows, 'actor_vk_id'));
            $lines = ["📜 История наказаний {$uM}:"];
            foreach ($rows as $r) {
                $dt = date('d.m.Y H:i', strtotime($r['created_at']));
                $actor = formatUserMention($pdo, $peerId, (int)$r['actor_vk_id']);
                $lines[] = "• [{$dt}] " . strtoupper($r['type']) . " от {$actor} | {$r['reason']}";
            }
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'nick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $nick = $tCtx['member']['nickname'] ?? null;
            sendMessage($peerId, $nick ? "🏷 Никнейм " . formatUserMention($pdo, $peerId, $targetId) . ": {$nick}" : "ℹ️ У пользователя нет никнейма в этой беседе.");
            return true;

        case 'snick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $newNick = trim($args);
            if (!$targetId || $newNick === '') { sendMessage($peerId, "⚠️ Использование: !сник [ID/упоминание] [никнейм]"); return true; }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper, true);
            if ($err !== null && $err !== 'REQUIRE_SUPER_APPROVAL') { sendMessage($peerId, $err); return true; }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $cleanNick = mb_substr($newNick, 0, 48);
            $pdo->prepare("UPDATE bot_chat_members SET nickname = ? WHERE peer_id = ? AND vk_id = ?")->execute([$cleanNick, $peerId, $targetId]);
            unset($GLOBALS['_mention_cache']["{$peerId}:{$targetId}"]);
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🏷 Установлен никнейм\n👤 Пользователь: {$tM}\n✏️ Никнейм: {$cleanNick}\n👮 Выдал: {$aM}");
            return true;

        case 'rnick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !рник [ID/упоминание]"); return true; }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper, true);
            if ($err !== null && $err !== 'REQUIRE_SUPER_APPROVAL') { sendMessage($peerId, $err); return true; }
            $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $targetId]);
            unset($GLOBALS['_mention_cache']["{$peerId}:{$targetId}"]);
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🗑 Никнейм удалён\n👤 Пользователь: {$tM}\n👮 Удалил: {$aM}");
            return true;

        case 'nlist':
            $page = max(1, (int)trim($args));
            $limit = 15; $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT vk_id, nickname, emoji FROM bot_chat_members WHERE peer_id = ? AND nickname IS NOT NULL AND nickname != '' ORDER BY nickname ASC LIMIT ? OFFSET ?");
            $stmt->execute([$peerId, $limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "📋 Список никнеймов пуст (Страница {$page})."); return true; }
            $lines = ["🏷 Список никнеймов (Страница {$page}):"];
            $i = $offset + 1;
            foreach ($rows as $r) {
                $em = !empty($r['emoji']) ? $r['emoji'] . ' ' : '';
                $lines[] = "{$i}. [id{$r['vk_id']}|{$em}{$r['nickname']}]";
                $i++;
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!нлист ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!нлист ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
            return true;

        case 'nonames':
            $stmt = $pdo->prepare("SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND (nickname IS NULL OR nickname = '') LIMIT 40");
            $stmt->execute([$peerId]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($ids)) { sendMessage($peerId, "✅ У всех участников в базе есть никнеймы!"); return true; }
            preloadUsers($pdo, $peerId, $ids);
            $list = array_map(fn($u) => "• " . formatUserMention($pdo, $peerId, (int)$u), $ids);
            sendMessage($peerId, "👤 Участники без никнейма (" . count($list) . "):\n" . implode("\n", $list), null, ['disable_mentions' => 1]);
            return true;

        case 'checknicks':
            $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId]);
            $items = $res['response']['items'] ?? [];
            if (empty($items)) { sendMessage($peerId, "⚠️ Не удалось получить список участников (нужны права админа беседы)."); return true; }
            $activeIds = array_values(array_filter(array_map(fn($i) => (int)($i['member_id'] ?? 0), $items), fn($id) => $id > 0));
            if (empty($activeIds)) { sendMessage($peerId, "⚠️ Список участников пуст."); return true; }
            $ph = implode(',', array_fill(0, count($activeIds), '?'));
            $stmt = $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ? AND nickname IS NOT NULL AND vk_id NOT IN ({$ph})");
            $stmt->execute(array_merge([$peerId], $activeIds));
            sendMessage($peerId, "🧹 Очистка завершена! Удалено никнеймов вышедших участников: " . $stmt->rowCount());
            return true;

        case 'zov':
            $reason = trim($args) !== '' ? trim($args) : 'Общий сбор в беседе!';
            $stmt = $pdo->prepare("SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND mention_opt_in = 1 LIMIT 50");
            $stmt->execute([$peerId]);
            $uids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $mentions = array_map(fn($u) => "[id{$u}|🔔]", $uids);
            sendMessage($peerId, "📢 Вызов от " . formatUserMention($pdo, $peerId, $fromId) . "!\n💬 {$reason}\n\n" . implode(' ', $mentions));
            return true;

        case 'reg':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $dt = date('d.m.Y в H:i', strtotime($tCtx['member']['joined_at'] ?? 'now'));
            sendMessage($peerId, "📅 Дата регистрации " . formatUserMention($pdo, $peerId, $targetId) . " в беседе: {$dt} МСК.");
            return true;

        case 'note':
            $name = mb_strtolower(trim($args));
            if ($name === '') {
                $stmt = $pdo->prepare("SELECT note_key FROM bot_notes WHERE peer_id = ? ORDER BY note_key ASC");
                $stmt->execute([$peerId]);
                $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
                sendMessage($peerId, empty($keys) ? "📝 Заметок в беседе пока нет. Создать: !заметки [ключ] [текст]" : "📝 Доступные заметки (!заметка [ключ]):\n• " . implode("\n• ", $keys));
                return true;
            }
            $stmt = $pdo->prepare("SELECT note_text FROM bot_notes WHERE peer_id = ? AND note_key = ?");
            $stmt->execute([$peerId, $name]);
            $txt = $stmt->fetchColumn();
            sendMessage($peerId, $txt ? "📌 Заметка «{$name}»:\n{$txt}" : "❌ Заметка «{$name}» не найдена.");
            return true;

        // ==========================================
        // 2. МОДЕРАТОР (ПРИОРИТЕТ >= 40)
        // ==========================================
        case 'ban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !бан [ID/упоминание] (дни) (причина)"); return true; }
            $days = 365; $reason = 'не указана';
            if (preg_match('/^(\d+)(?:\s+(.*))?$/isu', trim($args), $m)) {
                $days = max(1, min(3650, (int)$m[1]));
                if (!empty($m[2])) $reason = trim($m[2]);
            } elseif (trim($args) !== '') { $reason = trim($args); }

            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, 'ban', $days, $reason);
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }

            $expiresTs = time() + ($days * 86400);
            $expires = date('Y-m-d H:i:s', $expiresTs);
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'ban'")->execute([$peerId, $targetId]);
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, ?, 'ban', ?, 1, ?)")
                ->execute([$peerId, $targetId, $fromId, mb_substr($reason, 0, 250), $expires]);
            if ($peerId > 2000000000) {
                vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
            }
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            $aRole = $isSuper ? 'Супер-доступ' : getRoleName($pdo, $peerId, $myPriority);
            sendMessage($peerId, "🔨 Пользователь заблокирован в беседе\n👤 Нарушитель: {$tM}\n👮 Выдал: {$aM} ({$aRole})\n⏳ Срок: {$days} дн. (до " . date('d.m.Y H:i', $expiresTs) . " МСК)\n📝 Причина: {$reason}");
            sendToLogChat($pdo, "Беседа {$peerId}: {$aM} забанил {$tM} ({$days} дн). Причина: {$reason}");
            checkAntiDrain($pdo, $peerId, $fromId, $myPriority, $isSuper, $ctx['chat']);
            return true;

        case 'unban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !унбан [ID/упоминание]"); return true; }
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type IN ('ban','gban')")->execute([$peerId, $targetId]);
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "✅ Блокировка в беседе снята\n👤 Пользователь: {$tM}\n👮 Снял: {$aM}");
            return true;

        case 'banlist':
            $page = max(1, (int)trim($args));
            $limit = 10; $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT target_vk_id, reason, expires_at FROM bot_punishments WHERE peer_id = ? AND type IN ('ban','gban') AND is_active = 1 ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->execute([$peerId, $limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "✅ Список заблокированных пуст (Страница {$page})."); return true; }
            preloadUsers($pdo, $peerId, array_column($rows, 'target_vk_id'));
            $lines = ["🚫 Заблокированные участники (Страница {$page}):"];
            $i = $offset + 1;
            foreach ($rows as $r) {
                $exp = $r['expires_at'] ? date('d.m.Y', strtotime($r['expires_at'])) : 'Навсегда';
                $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['target_vk_id']) . " | До: {$exp} | {$r['reason']}";
                $i++;
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!banlist ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!banlist ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
            return true;

        case 'clear':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $count = ctype_digit(trim($args)) ? max(1, min(50, (int)trim($args))) : 10;
            $cmids = popRecentMessages($peerId, $targetId, $count);
            if (empty($cmids)) {
                sendMessage($peerId, "⚠️ Нет недавних сообщений для удаления (либо у бота нет прав администратора).");
                return true;
            }
            deleteMessage($peerId, $cmids);
            sendMessage($peerId, "🧹 Очищено сообщений: " . count($cmids) . " шт.");
            return true;

        case 'role':
        case 'helper':
        case 'moder':
        case 'admin':
        case 'spec':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Укажите пользователя! Пример: !role @ivan 40"); return true; }
            $newPriority = 0;
            if ($cmd === 'helper') $newPriority = 20;
            elseif ($cmd === 'moder') $newPriority = 40;
            elseif ($cmd === 'admin') $newPriority = 60;
            elseif ($cmd === 'spec') $newPriority = 80;
            else {
                $roleArg = mb_strtolower(trim($args));
                if (ctype_digit($roleArg)) {
                    $newPriority = max(0, min(100, (int)$roleArg));
                } else {
                    $found = false;
                    foreach (getChatRoles($pdo, $peerId) as $p => $rName) {
                        if (mb_strtolower($rName) === $roleArg) { $newPriority = $p; $found = true; break; }
                    }
                    if (!$found) { sendMessage($peerId, "⚠️ Укажите приоритет (0, 20, 40, 60, 80) или точное название роли."); return true; }
                }
            }
            if (!$isSuper && $newPriority >= $myPriority) {
                sendMessage($peerId, "⛔ Вы не можете выдать роль с приоритетом равным или выше вашего ({$myPriority}).");
                return true;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null && $err !== 'REQUIRE_SUPER_APPROVAL') { sendMessage($peerId, $err); return true; }

            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_chat_members SET role_priority = ? WHERE peer_id = ? AND vk_id = ?")->execute([$newPriority, $peerId, $targetId]);
            $rName = getRoleName($pdo, $peerId, $newPriority);
            $uM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🛡 Назначена роль\n👤 Пользователь: {$uM}\n🎖 Роль: «{$rName}» ({$newPriority})\n👮 Выдал: {$aM}");
            sendToLogChat($pdo, "Беседа {$peerId}: {$aM} выдал роль «{$rName}» ({$newPriority}) -> {$uM}");
            return true;

        case 'removerole':
        case 'gremoverole':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Укажите пользователя: !removerole [ID/упоминание]"); return true; }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, $cmd, 0, 'Снятие ролей');
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }
            $peers = ($cmd === 'gremoverole') ? getUnityPeers($pdo, $peerId) : [$peerId];
            foreach ($peers as $pId) {
                $pdo->prepare("UPDATE bot_chat_members SET role_priority = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$pId, $targetId]);
            }
            $uM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            $scope = ($cmd === 'gremoverole') ? "во всём объединении (" . count($peers) . " бесед)" : "в беседе";
            sendMessage($peerId, "🛡 Роли сняты ({$scope})\n👤 Пользователь: {$uM}\n👮 Снял: {$aM}");
            checkAntiDrain($pdo, $peerId, $fromId, $myPriority, $isSuper, $ctx['chat']);
            return true;

        case 'silence':
            $rawT = mb_strtolower(trim($args));
            if (in_array($rawT, ['выкл', 'off', '0', 'стоп', 'stop'], true)) {
                $pdo->prepare("UPDATE bot_chats SET silence_until = NULL WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "🔊 Режим тишины отключён.");
                return true;
            }
            if ($rawT === '') {
                $cur = $ctx['chat']['silence_until'] ?? null;
                if ($cur && strtotime($cur) > time()) {
                    $pdo->prepare("UPDATE bot_chats SET silence_until = NULL WHERE peer_id = ?")->execute([$peerId]);
                    sendMessage($peerId, "🔊 Режим тишины отключён.");
                } else {
                    $pdo->prepare("UPDATE bot_chats SET silence_until = '2036-12-31 23:59:59' WHERE peer_id = ?")->execute([$peerId]);
                    sendMessage($peerId, "🔇 Режим тишины включён бессрочно. Писать смогут только участники с ролью.");
                }
                return true;
            }
            $sec = 1800;
            if (preg_match('/(\d+)\s*(м|мин|min|ч|час|h|д|дн|d)?/iu', $rawT, $m)) {
                $val = (int)$m[1]; $u = $m[2] ?? 'м';
                if (str_starts_with($u, 'ч') || $u === 'h') $sec = $val * 3600;
                elseif (str_starts_with($u, 'д') || $u === 'd') $sec = $val * 86400;
                else $sec = $val * 60;
            }
            $until = date('Y-m-d H:i:s', time() + $sec);
            $pdo->prepare("UPDATE bot_chats SET silence_until = ? WHERE peer_id = ?")->execute([$until, $peerId]);
            sendMessage($peerId, "🔇 Режим тишины включён до " . date('d.m.Y H:i', time() + $sec) . " МСК.");
            return true;

        case 'findnick':
            $q = trim($args);
            if ($q === '') { sendMessage($peerId, "🔍 Использование: !понику [часть ника]"); return true; }
            $stmt = $pdo->prepare("SELECT vk_id, nickname FROM bot_chat_members WHERE peer_id = ? AND nickname LIKE ? LIMIT 20");
            $stmt->execute([$peerId, '%' . $q . '%']);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "🔍 Ничего не найдено по запросу «{$q}»."); return true; }
            $lines = ["🔍 Найдено по нику «{$q}»:"];
            foreach ($rows as $r) $lines[] = "• [id{$r['vk_id']}|{$r['nickname']}] (ID: {$r['vk_id']})";
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'gzov':
            $reason = trim($args) !== '' ? trim($args) : 'Общий сбор объединения!';
            $peers = getUnityPeers($pdo, $peerId);
            $caller = formatUserMention($pdo, $peerId, $fromId);
            foreach ($peers as $pId) {
                $stmt = $pdo->prepare("SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND mention_opt_in = 1 LIMIT 50");
                $stmt->execute([$pId]);
                $mentions = array_map(fn($u) => "[id{$u}|🔔]", $stmt->fetchAll(PDO::FETCH_COLUMN));
                sendMessage($pId, "📢 Вызов объединения от {$caller}!\n💬 {$reason}\n\n" . implode(' ', $mentions));
            }
            return true;

        case 'gkick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !gkick [ID/упоминание]"); return true; }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, 'gkick', 0, trim($args) ?: 'не указана');
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }
            $peers = getUnityPeers($pdo, $peerId);
            $calls = [];
            foreach ($peers as $pId) {
                if ($pId > 2000000000) $calls[] = ['method' => 'messages.removeChatUser', 'params' => ['chat_id' => $pId - 2000000000, 'member_id' => $targetId]];
            }
            vkApiMulti($calls, 10);
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "👢 Глобальное исключение по объединению\n👤 Участник: {$tM}\n👮 Исключил: {$aM}\n💬 Затронуто бесед: " . count($peers));
            checkAntiDrain($pdo, $peerId, $fromId, $myPriority, $isSuper, $ctx['chat']);
            return true;

        case 'gsnick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $newNick = trim($args);
            if (!$targetId || $newNick === '') { sendMessage($peerId, "⚠️ Использование: !gsnick [ID/упоминание] [ник]"); return true; }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper, true);
            if ($err !== null && $err !== 'REQUIRE_SUPER_APPROVAL') { sendMessage($peerId, $err); return true; }
            $cleanNick = mb_substr($newNick, 0, 48);
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                ensureUserAndMember($pdo, $pId, $targetId);
                $pdo->prepare("UPDATE bot_chat_members SET nickname = ? WHERE peer_id = ? AND vk_id = ?")->execute([$cleanNick, $pId, $targetId]);
            }
            unset($GLOBALS['_mention_cache']["{$peerId}:{$targetId}"]);
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🏷 Никнейм установлен в объединении (" . count($peers) . " бесед)\n👤 Пользователь: {$tM}\n✏️ Никнейм: {$cleanNick}\n👮 Выдал: {$aM}");
            return true;

        case 'grnick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !грник [ID/упоминание]"); return true; }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper, true);
            if ($err !== null && $err !== 'REQUIRE_SUPER_APPROVAL') { sendMessage($peerId, $err); return true; }
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ? AND vk_id = ?")->execute([$pId, $targetId]);
            }
            unset($GLOBALS['_mention_cache']["{$peerId}:{$targetId}"]);
            $tM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🗑 Никнейм удалён в объединении (" . count($peers) . " бесед)\n👤 Пользователь: {$tM}\n👮 Удалил: {$aM}");
            return true;

        // ==========================================
        // 3. АДМИНИСТРАТОР (60+) И ГЛ. АДМИН (80+)
        // ==========================================
        case 'gban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !gban [ID/упоминание] (дни) (причина)"); return true; }
            $days = 365; $reason = 'не указана';
            if (preg_match('/^(\d+)(?:\s+(.*))?$/isu', trim($args), $m)) {
                $days = max(1, min(3650, (int)$m[1]));
                if (!empty($m[2])) $reason = trim($m[2]);
            } elseif (trim($args) !== '') { $reason = trim($args); }

            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, 'gban', $days, $reason);
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }

            $expiresTs = time() + ($days * 86400);
            $expires = date('Y-m-d H:i:s', $expiresTs);
            $peers = getUnityPeers($pdo, $peerId);
            $calls = [];
            foreach ($peers as $pId) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type IN ('ban','gban')")->execute([$pId, $targetId]);
                $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, ?, 'gban', ?, 1, ?)")
                    ->execute([$pId, $targetId, $fromId, mb_substr($reason, 0, 250), $expires]);
                if ($pId > 2000000000) $calls[] = ['method' => 'messages.removeChatUser', 'params' => ['chat_id' => $pId - 2000000000, 'member_id' => $targetId]];
            }
            vkApiMulti($calls, 10);
            $uM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            $aRole = $isSuper ? 'Супер-доступ' : getRoleName($pdo, $peerId, $myPriority);
            sendMessage($peerId, "🔨 Глобальная блокировка по объединению\n👤 Нарушитель: {$uM}\n👮 Выдал: {$aM} ({$aRole})\n⏳ Срок: {$days} дн. (до " . date('d.m.Y H:i', $expiresTs) . " МСК)\n💬 Бесед: " . count($peers) . "\n📝 Причина: {$reason}");
            sendToLogChat($pdo, "GBAN: {$aM} -> {$uM} ({$days} дн). Причина: {$reason}");
            checkAntiDrain($pdo, $peerId, $fromId, $myPriority, $isSuper, $ctx['chat']);
            return true;

        case 'gunban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !gunban [ID/упоминание]"); return true; }
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type IN ('ban','gban')")->execute([$pId, $targetId]);
            }
            $uM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "✅ Блокировка в объединении снята\n👤 Пользователь: {$uM}\n👮 Снял: {$aM}\n💬 Бесед: " . count($peers));
            return true;

        case 'grole':
        case 'ghelper':
        case 'gmoder':
        case 'gadmin':
        case 'gspec':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Укажите пользователя!"); return true; }
            $newPriority = 0;
            if ($cmd === 'ghelper') $newPriority = 20;
            elseif ($cmd === 'gmoder') $newPriority = 40;
            elseif ($cmd === 'gadmin') $newPriority = 60;
            elseif ($cmd === 'gspec') $newPriority = 80;
            else { $newPriority = max(0, min(100, (int)trim($args))); }
            if (!$isSuper && $newPriority >= $myPriority) {
                sendMessage($peerId, "⛔ Вы не можете выдать роль равную или выше вашей ({$myPriority}).");
                return true;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null && $err !== 'REQUIRE_SUPER_APPROVAL') { sendMessage($peerId, $err); return true; }

            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                ensureUserAndMember($pdo, $pId, $targetId);
                $pdo->prepare("UPDATE bot_chat_members SET role_priority = ? WHERE peer_id = ? AND vk_id = ?")->execute([$newPriority, $pId, $targetId]);
            }
            $rName = getRoleName($pdo, $peerId, $newPriority);
            $uM = formatUserMention($pdo, $peerId, $targetId);
            $aM = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🛡 Назначена роль в объединении (" . count($peers) . " бесед)\n👤 Пользователь: {$uM}\n🎖 Роль: «{$rName}» ({$newPriority})\n👮 Выдал: {$aM}");
            return true;

        case 'delmsg':
            $cmids = [];
            if (!empty($msg['reply_message']['conversation_message_id'])) $cmids[] = (int)$msg['reply_message']['conversation_message_id'];
            foreach (($msg['fwd_messages'] ?? []) as $fw) {
                if (!empty($fw['conversation_message_id'])) $cmids[] = (int)$fw['conversation_message_id'];
            }
            if (empty($cmids)) { sendMessage($peerId, "⚠️ Перешлите или ответьте на сообщение для удаления."); return true; }
            deleteMessage($peerId, $cmids);
            sendMessage($peerId, "🗑 Сообщение удалено.");
            return true;

        case 'gm':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !gm [ID/упоминание]"); return true; }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $uT = formatUserMention($pdo, $peerId, $targetId);
            $uA = formatUserMention($pdo, $peerId, $fromId);
            if (!empty($tCtx['member']['gm_granted_by'])) {
                if (!$isSuper && $myPriority < (int)$tCtx['member']['gm_min_priority']) {
                    sendMessage($peerId, "⛔ Ваша роль слишком низкая для снятия этого иммунитета.");
                    return true;
                }
                $pdo->prepare("UPDATE bot_chat_members SET gm_granted_by = NULL, gm_min_priority = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $targetId]);
                sendMessage($peerId, "🛡 Иммунитет снят\n👤 Пользователь: {$uT}\n👮 Снял: {$uA}");
            } else {
                $pdo->prepare("UPDATE bot_chat_members SET gm_granted_by = ?, gm_min_priority = ? WHERE peer_id = ? AND vk_id = ?")->execute([$fromId, $myPriority, $peerId, $targetId]);
                $roleReq = getRoleName($pdo, $peerId, $myPriority);
                sendMessage($peerId, "🛡 Выдан иммунитет от наказаний\n👤 Пользователь: {$uT}\n👮 Выдал: {$uA}\n🔒 Необходимая роль для взаимодействия: «{$roleReq}» ({$myPriority}) и выше.");
            }
            return true;

        case 'removegm':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !removegm [ID/упоминание]"); return true; }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            if (!$isSuper && $myPriority < (int)($tCtx['member']['gm_min_priority'] ?? 0)) {
                sendMessage($peerId, "⛔ Недостаточно прав для снятия этого иммунитета.");
                return true;
            }
            $pdo->prepare("UPDATE bot_chat_members SET gm_granted_by = NULL, gm_min_priority = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $targetId]);
            sendMessage($peerId, "🛡 Иммунитет снят\n👤 Пользователь: " . formatUserMention($pdo, $peerId, $targetId) . "\n👮 Снял: " . formatUserMention($pdo, $peerId, $fromId));
            return true;

        case 'gms':
            $stmt = $pdo->prepare("SELECT vk_id, gm_granted_by, gm_min_priority FROM bot_chat_members WHERE peer_id = ? AND gm_granted_by IS NOT NULL ORDER BY gm_min_priority DESC");
            $stmt->execute([$peerId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "🛡 Пользователей с иммунитетом нет."); return true; }
            preloadUsers($pdo, $peerId, array_merge(array_column($rows, 'vk_id'), array_column($rows, 'gm_granted_by')));
            $lines = ["🛡 Пользователи с иммунитетом:"];
            $i = 1;
            foreach ($rows as $r) {
                $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['vk_id']) . " ({$r['gm_min_priority']}), выдал: " . formatUserMention($pdo, $peerId, (int)$r['gm_granted_by']);
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'pin':
            $cmid = $msg['reply_message']['conversation_message_id'] ?? ($msg['fwd_messages'][0]['conversation_message_id'] ?? null);
            if (!$cmid) { sendMessage($peerId, "⚠️ Ответьте на сообщение, которое нужно закрепить."); return true; }
            vkApi('messages.pin', ['peer_id' => $peerId, 'conversation_message_id' => (int)$cmid]);
            sendMessage($peerId, "📌 Сообщение закреплено.");
            return true;

        case 'unpin':
            vkApi('messages.unpin', ['peer_id' => $peerId]);
            sendMessage($peerId, "📌 Сообщение откреплено.");
            return true;

        case 'newrole':
        case 'gnewrole':
            if (!preg_match('/^(\d+)\s+(.+)$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !newrole [приоритет 0-100] [название]");
                return true;
            }
            $prio = max(0, min(100, (int)$m[1]));
            $maxLen = ((int)$ctx['global']['is_vip'] === 1 || $isSuper) ? 50 : 25;
            $rName = mb_substr(trim($m[2]), 0, $maxLen);
            $peers = ($cmd === 'gnewrole') ? getUnityPeers($pdo, $peerId) : [$peerId];
            foreach ($peers as $pId) {
                $pdo->prepare("REPLACE INTO bot_chat_roles (peer_id, priority, role_name) VALUES (?, ?, ?)")->execute([$pId, $prio, $rName]);
                cacheDel("roles_{$pId}");
            }
            sendMessage($peerId, "✅ Роль «{$rName}» ({$prio}) сохранена!");
            return true;

        case 'delrole':
        case 'gdelrole':
            $prio = (int)trim($args);
            $peers = ($cmd === 'gdelrole') ? getUnityPeers($pdo, $peerId) : [$peerId];
            foreach ($peers as $pId) {
                $pdo->prepare("DELETE FROM bot_chat_roles WHERE peer_id = ? AND priority = ?")->execute([$pId, $prio]);
                cacheDel("roles_{$pId}");
            }
            sendMessage($peerId, "🗑 Кастомная роль ({$prio}) удалена.");
            return true;

        case 'welcome':
            $wText = trim($args);
            if ($wText === '') { sendMessage($peerId, "⚠️ Использование: !welcome [текст приветствия] (или !welcome off)"); return true; }
            $val = (mb_strtolower($wText) === 'off') ? null : $wText;
            $pdo->prepare("UPDATE bot_chats SET welcome_text = ? WHERE peer_id = ?")->execute([$val, $peerId]);
            sendMessage($peerId, $val ? "👋 Приветствие установлено!" : "🗑 Приветствие отключено.");
            return true;

        case 'setrules':
            $rText = trim($args);
            if ($rText === '') { sendMessage($peerId, "⚠️ Использование: !setrules [текст правил]"); return true; }
            $pdo->prepare("UPDATE bot_chats SET rules_text = ? WHERE peer_id = ?")->execute([$rText, $peerId]);
            sendMessage($peerId, "📜 Правила беседы обновлены!");
            return true;

        case 'delrules':
            $pdo->prepare("UPDATE bot_chats SET rules_text = NULL WHERE peer_id = ?")->execute([$peerId]);
            sendMessage($peerId, "🗑 Правила беседы сброшены.");
            return true;

        case 'setnote':
            if (!preg_match('/^([\wа-яё\-]+)\s+(.+)$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !заметки [ключ] [текст] (удалить: !заметки [ключ] del)");
                return true;
            }
            $nKey = mb_strtolower(mb_substr($m[1], 0, 32));
            $nTxt = trim($m[2]);
            if (mb_strtolower($nTxt) === 'del') {
                $pdo->prepare("DELETE FROM bot_notes WHERE peer_id = ? AND note_key = ?")->execute([$peerId, $nKey]);
                sendMessage($peerId, "🗑 Заметка «{$nKey}» удалена.");
            } else {
                $pdo->prepare("REPLACE INTO bot_notes (peer_id, note_key, note_text, author_vk_id) VALUES (?, ?, ?, ?)")->execute([$peerId, $nKey, $nTxt, $fromId]);
                sendMessage($peerId, "📌 Заметка «{$nKey}» сохранена! Вызов: !заметка {$nKey}");
            }
            return true;

        case 'inactive':
            if (!preg_match('/^(\d+)(?:\s+(kick|кик))?$/iu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Укажите период в днях: !неактив 3 (или !неактив 3 кик)");
                return true;
            }
            $days = max(1, (int)$m[1]);
            $doKick = !empty($m[2]);
            $stmt = $pdo->prepare("SELECT vk_id, last_active_at FROM bot_chat_members WHERE peer_id = ? AND role_priority = 0 AND last_active_at < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 40");
            $stmt->execute([$peerId, $days]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "✅ Неактивных участников более {$days} дн. нет."); return true; }
            preloadUsers($pdo, $peerId, array_column($rows, 'vk_id'));
            $lines = ["⏳ Неактивны более {$days} дн. (" . count($rows) . " чел.):"];
            foreach ($rows as $r) {
                $uid = (int)$r['vk_id'];
                $lines[] = "• " . formatUserMention($pdo, $peerId, $uid) . " (" . date('d.m.Y', strtotime($r['last_active_at'])) . ")";
                if ($doKick && $peerId > 2000000000) {
                    vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $uid]);
                }
            }
            if ($doKick) $lines[] = "\n👢 Указанные участники исключены.";
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'givemoney':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $amount = parseAmount($args, 5000000);
            if (!$targetId || $amount === null || $amount <= 0) {
                sendMessage($peerId, "⚠️ Использование: !givemoney [ID/упоминание] [сумма]");
                return true;
            }
            $today = date('Y-m-d');
            $givenToday = ($ctx['global']['daily_given_date'] === $today) ? (int)$ctx['global']['daily_given_amount'] : 0;
            if (!$isSuper && ($givenToday + $amount) > 5000000) {
                sendMessage($peerId, "⛔ Лимит выдачи — 5 000 000 ₽/сутки. Остаток: " . formatMoney(max(0, 5000000 - $givenToday)));
                return true;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$amount, $targetId]);
            $pdo->prepare("UPDATE bot_users SET daily_given_amount = ?, daily_given_date = ? WHERE vk_id = ?")->execute([$givenToday + $amount, $today, $fromId]);
            sendMessage($peerId, "💰 Пользователю " . formatUserMention($pdo, $peerId, $targetId) . " выдано " . formatMoney($amount) . "!");
            return true;

        case 'timeuved':
            if (in_array(mb_strtolower(trim($args)), ['off', '0'], true)) {
                $pdo->prepare("DELETE FROM bot_timers WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "⏰ Автоуведомления беседы отключены.");
                return true;
            }
            if (!preg_match('/^(\d+)\s+(.+)$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !timeuved [ЧАСЫ] [текст] (отключить: !timeuved 0)");
                return true;
            }
            $hours = max(1, min(168, (int)$m[1]));
            $tText = trim($m[2]);
            $pdo->prepare("DELETE FROM bot_timers WHERE peer_id = ?")->execute([$peerId]);
            $pdo->prepare("INSERT INTO bot_timers (peer_id, interval_hours, message_text, last_sent_at, is_active) VALUES (?, ?, ?, NOW(), 1)")->execute([$peerId, $hours, $tText]);
            sendMessage($peerId, "⏰ Автоуведомление каждые {$hours} ч. установлено!");
            return true;

        // ==========================================
        // 4. ВЛАДЕЛЕЦ (100) И СУПЕР-ДОСТУП (/addws)
        // ==========================================
        case 'setup':
            $curOwner = (int)($ctx['chat']['owner_vk_id'] ?? 0);
            if ($curOwner > 0 && $curOwner !== $fromId && !$isSuper) {
                sendMessage($peerId, "⚠️ Беседа уже настроена. Владелец: " . formatUserMention($pdo, $peerId, $curOwner));
                return true;
            }
            $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ?")->execute([$fromId, $peerId]);
            refreshChatTitle($pdo, $peerId);
            $pdo->prepare("UPDATE bot_chat_members SET role_priority = 100 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            $kb = makeInlineKeyboard([[
                ['label' => '🔄 Синхронизировать (!sync)', 'payload' => ['cmd' => '!sync'], 'color' => 'primary'],
                ['label' => '⚙️ Настройки (!settings)', 'payload' => ['cmd' => '!settings'], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, "✅ Беседа успешно настроена! Вы назначены Владельцем (приоритет 100).", $kb);
            return true;

        case 'sync':
            $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId]);
            $items = $res['response']['items'] ?? [];
            $profiles = $res['response']['profiles'] ?? [];
            if (empty($items)) {
                sendMessage($peerId, "⚠️ Не удалось получить участников (выдайте боту права администратора беседы).");
                return true;
            }
            $profMap = [];
            foreach ($profiles as $p) $profMap[(int)$p['id']] = $p;
            $synced = 0;
            foreach ($items as $item) {
                $mid = (int)($item['member_id'] ?? 0);
                if ($mid <= 0) continue;
                $fn = $profMap[$mid]['first_name'] ?? 'Игрок';
                $ln = $profMap[$mid]['last_name'] ?? '';
                $pdo->prepare("INSERT INTO bot_users (vk_id, first_name, last_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name)")
                    ->execute([$mid, $fn, $ln]);
                $prio = !empty($item['is_owner']) ? 100 : (!empty($item['is_admin']) ? 60 : 0);
                if (!empty($item['is_owner'])) {
                    $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ? AND owner_vk_id = 0")->execute([$mid, $peerId]);
                }
                $pdo->prepare("INSERT INTO bot_chat_members (peer_id, vk_id, role_priority) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role_priority = GREATEST(role_priority, VALUES(role_priority))")
                    ->execute([$peerId, $mid, $prio]);
                $synced++;
            }
            refreshChatTitle($pdo, $peerId);
            sendMessage($peerId, "🔄 Синхронизация завершена! Обновлено участников: {$synced} (Владельцу выдана роль 100, админам со звездой — 60).");
            return true;

        case 'settings':
        case 'gsettings':
            $isUnity = ($cmd === 'gsettings');
            $sub = mb_strtolower(trim($args));
            $peers = $isUnity ? getUnityPeers($pdo, $peerId) : [$peerId];

            if (in_array($sub, ['games', 'silence', 'mentions', 'automod', 'antiflood', 'antidrain', 'antiraid', 'anticaps'], true)) {
                foreach ($peers as $pId) {
                    if ($sub === 'games') $pdo->prepare("UPDATE bot_chats SET games_enabled = 1 - games_enabled WHERE peer_id = ?")->execute([$pId]);
                    elseif ($sub === 'automod') $pdo->prepare("UPDATE bot_chats SET automod_enabled = 1 - automod_enabled WHERE peer_id = ?")->execute([$pId]);
                    elseif ($sub === 'antiflood') $pdo->prepare("UPDATE bot_chats SET antiflood_enabled = 1 - antiflood_enabled WHERE peer_id = ?")->execute([$pId]);
                    elseif ($sub === 'antidrain') $pdo->prepare("UPDATE bot_chats SET antidrain_enabled = 1 - antidrain_enabled WHERE peer_id = ?")->execute([$pId]);
                    elseif ($sub === 'antiraid') $pdo->prepare("UPDATE bot_chats SET antiraid_enabled = 1 - antiraid_enabled WHERE peer_id = ?")->execute([$pId]);
                    elseif ($sub === 'anticaps') $pdo->prepare("UPDATE bot_chats SET anticaps_enabled = 1 - anticaps_enabled WHERE peer_id = ?")->execute([$pId]);
                    elseif ($sub === 'mentions') $pdo->prepare("UPDATE bot_chats SET mentions_enabled = 1 - mentions_enabled WHERE peer_id = ?")->execute([$pId]);
                    elseif ($sub === 'silence') {
                        $stmtS = $pdo->prepare("SELECT silence_until FROM bot_chats WHERE peer_id = ?");
                        $stmtS->execute([$pId]);
                        $sCur = $stmtS->fetchColumn();
                        $newS = ($sCur && strtotime($sCur) > time()) ? null : '2036-12-31 23:59:59';
                        $pdo->prepare("UPDATE bot_chats SET silence_until = ? WHERE peer_id = ?")->execute([$newS, $pId]);
                    }
                }
            }
            $stmtChat = $pdo->prepare("SELECT * FROM bot_chats WHERE peer_id = ?");
            $stmtChat->execute([$peerId]);
            $c = $stmtChat->fetch();
            $gS = (int)$c['games_enabled'] ? '✅' : '❌';
            $aS = (int)$c['automod_enabled'] ? '✅' : '❌';
            $fS = (int)$c['antiflood_enabled'] ? '✅' : '❌';
            $dS = (int)($c['antidrain_enabled'] ?? 0) ? '✅' : '❌';
            $rS = (int)($c['antiraid_enabled'] ?? 0) ? '✅' : '❌';
            $cS = (int)($c['anticaps_enabled'] ?? 0) ? '✅' : '❌';
            $mS = (int)$c['mentions_enabled'] ? '✅' : '❌';
            $sS = (!empty($c['silence_until']) && strtotime($c['silence_until']) > time()) ? '✅' : '❌';
            $pCmd = $isUnity ? '!gsettings' : '!settings';
            $kb = makeInlineKeyboard([
                [
                    ['label' => "🎮 Игры {$gS}", 'payload' => ['cmd' => "{$pCmd} games"], 'color' => 'primary'],
                    ['label' => "🔇 Тишина {$sS}", 'payload' => ['cmd' => "{$pCmd} silence"], 'color' => 'secondary']
                ],
                [
                    ['label' => "🤖 Автомод {$aS}", 'payload' => ['cmd' => "{$pCmd} automod"], 'color' => 'primary'],
                    ['label' => "🌊 Антифлуд {$fS}", 'payload' => ['cmd' => "{$pCmd} antiflood"], 'color' => 'secondary']
                ],
                [
                    ['label' => "🛡 Анти-слив {$dS}", 'payload' => ['cmd' => "{$pCmd} antidrain"], 'color' => 'negative'],
                    ['label' => "🚨 Анти-рейд {$rS}", 'payload' => ['cmd' => "{$pCmd} antiraid"], 'color' => 'negative']
                ],
                [
                    ['label' => "🔡 Анти-капс {$cS}", 'payload' => ['cmd' => "{$pCmd} anticaps"], 'color' => 'secondary'],
                    ['label' => "🔔 Упоминания {$mS}", 'payload' => ['cmd' => "{$pCmd} mentions"], 'color' => 'secondary']
                ]
            ]);
            $title = $isUnity ? "⚙️ Глобальные настройки объединения (" . count($peers) . " бесед):" : "⚙️ Настройки беседы:";
            sendMessage($peerId, $title, $kb);
            return true;

        case 'games':
            $pdo->prepare("UPDATE bot_chats SET games_enabled = 1 - games_enabled WHERE peer_id = ?")->execute([$peerId]);
            $stS = $pdo->prepare("SELECT games_enabled FROM bot_chats WHERE peer_id = ?"); $stS->execute([$peerId]); $st = (int)$stS->fetchColumn();
            sendMessage($peerId, $st ? "🎮 Игры и экономика включены!" : "🎮 Игры и экономика отключены.");
            return true;

        case 'automod':
            $pdo->prepare("UPDATE bot_chats SET automod_enabled = 1 - automod_enabled WHERE peer_id = ?")->execute([$peerId]);
            $stS = $pdo->prepare("SELECT automod_enabled FROM bot_chats WHERE peer_id = ?"); $stS->execute([$peerId]); $st = (int)$stS->fetchColumn();
            sendMessage($peerId, $st ? "🤖 Автомодерация рекламы включена!" : "🤖 Автомодерация отключена.");
            return true;

        case 'antiflood':
            $pdo->prepare("UPDATE bot_chats SET antiflood_enabled = 1 - antiflood_enabled WHERE peer_id = ?")->execute([$peerId]);
            $stS = $pdo->prepare("SELECT antiflood_enabled FROM bot_chats WHERE peer_id = ?"); $stS->execute([$peerId]); $st = (int)$stS->fetchColumn();
            sendMessage($peerId, $st ? "🌊 Антифлуд включён (автомут на 5 мин за спам)!" : "🌊 Антифлуд отключён.");
            return true;

        case 'migration':
            $srcText = trim((string)($msg['reply_message']['text'] ?? ($msg['fwd_messages'][0]['text'] ?? $args)));
            if ($srcText === '') {
                sendMessage($peerId, "⚠️ Перешлите сообщение со списком ников или банов от Квик / Iris / Chat Manager и напишите !migration (или !migration bans).");
                return true;
            }
            $isBanMode = (mb_stripos($args, 'ban') !== false || mb_stripos($srcText, 'заблокирован') !== false || mb_stripos($srcText, 'бан') !== false);
            $lines = preg_split('/\r?\n/', $srcText);
            $imported = 0;
            foreach ($lines as $line) {
                $uid = null; $nick = null;
                if (preg_match('/\[id(\d+)\|([^\]]+)\](?:\s*[-—:]\s*(.+))?/iu', $line, $m)) {
                    $uid = (int)$m[1];
                    $nick = !empty($m[3]) ? trim($m[3]) : trim($m[2]);
                } elseif (preg_match('/vk\.(?:com|ru)\/id(\d+)(?:\s*[-—:]\s*(.+))?/iu', $line, $m)) {
                    $uid = (int)$m[1];
                    $nick = !empty($m[2]) ? trim($m[2]) : null;
                }
                if ($uid && $uid > 0) {
                    ensureUserAndMember($pdo, $peerId, $uid);
                    if ($isBanMode) {
                        $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active) VALUES (?, ?, ?, 'ban', 'Миграция блокировки', 1)")
                            ->execute([$peerId, $uid, $fromId]);
                    } elseif ($nick) {
                        $pdo->prepare("UPDATE bot_chat_members SET nickname = ? WHERE peer_id = ? AND vk_id = ?")
                            ->execute([mb_substr($nick, 0, 48), $peerId, $uid]);
                    }
                    $imported++;
                }
            }
            sendMessage($peerId, $imported > 0
                ? "✅ Миграция завершена! Перенесено записей (" . ($isBanMode ? "блокировок" : "никнеймов") . "): {$imported}."
                : "❌ Не удалось распознать ни одной записи. Перешлите сообщение со ссылками/упоминаниями участников.");
            return true;

        case 'wipe':
            $sub = mb_strtolower(trim($args));
            if (in_array($sub, ['nicks', 'ники'], true)) {
                $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "🧹 Все никнеймы в беседе сброшены.");
            } elseif (in_array($sub, ['warns', 'варны', 'преды'], true)) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND type = 'warn'")->execute([$peerId]);
                sendMessage($peerId, "🧹 Все активные предупреждения сброшены.");
            } elseif (in_array($sub, ['bans', 'баны'], true)) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND type IN ('ban','gban')")->execute([$peerId]);
                sendMessage($peerId, "🧹 Все блокировки сброшены.");
            } elseif (in_array($sub, ['roles', 'роли'], true)) {
                $pdo->prepare("UPDATE bot_chat_members SET role_priority = 0 WHERE peer_id = ? AND role_priority < 100")->execute([$peerId]);
                sendMessage($peerId, "🧹 Все роли (кроме владельца) сброшены.");
            } else {
                $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ?")->execute([$peerId]);
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "🧹 Полный сброс: очищены никнеймы и активные наказания.");
            }
            return true;

        case 'owner':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !owner [ID/упоминание]"); return true; }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ?")->execute([$targetId, $peerId]);
            $pdo->prepare("UPDATE bot_chat_members SET role_priority = 100 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $targetId]);
            sendMessage($peerId, "👑 Права Владельца беседы (100) выданы " . formatUserMention($pdo, $peerId, $targetId) . "!");
            return true;

        case 'editcmd':
        case 'geditcmd':
            if (!preg_match('/^([\wа-яё]+)\s+(\d+)$/iu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !editcmd [команда] [приоритет 0-100]");
                return true;
            }
            $tCmd = resolveCanonicalCommand(mb_strtolower($m[1]));
            $nPrio = max(0, min(100, (int)$m[2]));
            $peers = ($cmd === 'geditcmd') ? getUnityPeers($pdo, $peerId) : [$peerId];
            foreach ($peers as $pId) {
                $pdo->prepare("REPLACE INTO bot_cmd_permissions (peer_id, cmd_name, min_priority) VALUES (?, ?, ?)")->execute([$pId, $tCmd, $nPrio]);
                cacheDel("cmdp_{$pId}");
            }
            sendMessage($peerId, "⚙️ Приоритет команды «{$tCmd}» изменён на {$nPrio}.");
            return true;

        case 'gedit':
            if (!preg_match('/^(rules|правила|welcome|приветствие)\s+(.+)$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !gedit [правила/приветствие] [текст]");
                return true;
            }
            $col = in_array(mb_strtolower($m[1]), ['rules', 'правила'], true) ? 'rules_text' : 'welcome_text';
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                $pdo->prepare("UPDATE bot_chats SET {$col} = ? WHERE peer_id = ?")->execute([trim($m[2]), $pId]);
            }
            sendMessage($peerId, "✅ Текст обновлён во всех беседах объединения (" . count($peers) . " шт.).");
            return true;

        case 'unity':
        case 'createunity':
            $uName = trim($args);
            if ($uName === '') {
                $curU = (int)($ctx['chat']['unity_id'] ?? 0);
                if ($curU > 0) {
                    $stU = $pdo->prepare("SELECT * FROM bot_unities WHERE id = ?"); $stU->execute([$curU]); $uInfo = $stU->fetch();
                    $peers = getUnityPeers($pdo, $peerId);
                    sendMessage($peerId, "🔗 Объединение: «{$uInfo['name']}» (ID: #{$curU})\n💬 Бесед в сети: " . count($peers));
                } else {
                    sendMessage($peerId, "🔗 Беседа не в объединении. Создать: !createunity [название] | Войти: !addunity [ID]");
                }
                return true;
            }
            $pdo->prepare("INSERT INTO bot_unities (name, owner_vk_id) VALUES (?, ?)")->execute([mb_substr($uName, 0, 60), $fromId]);
            $uId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE bot_chats SET unity_id = ? WHERE peer_id = ?")->execute([$uId, $peerId]);
            sendMessage($peerId, "🔗 Создано объединение «{$uName}» (ID: #{$uId})! В других беседах введите: !addunity {$uId}");
            return true;

        case 'unites':
            $stmt = $pdo->prepare("SELECT u.id, u.name, (SELECT COUNT(*) FROM bot_chats c WHERE c.unity_id = u.id) AS cnt FROM bot_unities u WHERE u.owner_vk_id = ?");
            $stmt->execute([$fromId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "🔗 У вас пока нет созданных объединений (!createunity)."); return true; }
            $lines = ["🔗 Ваши объединения:"];
            foreach ($rows as $r) $lines[] = "• #{$r['id']} «{$r['name']}» — {$r['cnt']} бесед";
            sendMessage($peerId, implode("\n", $lines));
            return true;

        case 'addunity':
            $uId = (int)trim($args);
            $stmtU = $pdo->prepare("SELECT * FROM bot_unities WHERE id = ?");
            $stmtU->execute([$uId]);
            $uInfo = $stmtU->fetch();
            if (!$uInfo) { sendMessage($peerId, "❌ Объединение #{$uId} не найдено."); return true; }
            if ((int)$uInfo['owner_vk_id'] !== $fromId && !$isSuper) { sendMessage($peerId, "⛔ Только создатель объединения может добавлять беседы."); return true; }
            $pdo->prepare("UPDATE bot_chats SET unity_id = ? WHERE peer_id = ?")->execute([$uId, $peerId]);
            sendMessage($peerId, "✅ Беседа добавлена в объединение «{$uInfo['name']}» (#{$uId})!");
            return true;

        case 'editunity':
            $newName = trim($args);
            $curU = (int)($ctx['chat']['unity_id'] ?? 0);
            if ($curU <= 0 || $newName === '') { sendMessage($peerId, "⚠️ Использование: !editunity [новое название]"); return true; }
            $pdo->prepare("UPDATE bot_unities SET name = ? WHERE id = ?")->execute([mb_substr($newName, 0, 60), $curU]);
            sendMessage($peerId, "✅ Название объединения #{$curU} изменено на «{$newName}».");
            return true;

        case 'removeunity':
            $pdo->prepare("UPDATE bot_chats SET unity_id = NULL WHERE peer_id = ?")->execute([$peerId]);
            sendMessage($peerId, "🔗 Беседа отвязана от объединения.");
            return true;

        case 'deleteunity':
            $uId = (int)trim($args) ?: (int)($ctx['chat']['unity_id'] ?? 0);
            if ($uId <= 0) { sendMessage($peerId, "⚠️ Укажите ID объединения: !deleteunity [ID]"); return true; }
            $stmtU = $pdo->prepare("SELECT owner_vk_id FROM bot_unities WHERE id = ?");
            $stmtU->execute([$uId]);
            $uOwn = (int)$stmtU->fetchColumn();
            if ($uOwn !== $fromId && !$isSuper) { sendMessage($peerId, "⛔ Вы не создатель этого объединения."); return true; }
            $pdo->prepare("UPDATE bot_chats SET unity_id = NULL WHERE unity_id = ?")->execute([$uId]);
            $pdo->prepare("DELETE FROM bot_unities WHERE id = ?")->execute([$uId]);
            sendMessage($peerId, "🗑 Объединение #{$uId} полностью удалено.");
            return true;

        // ==========================================
        // 5. РЕПОРТЫ И СУПЕР-ДОСТУП (/addws)
        // ==========================================
        case 'report':
            $repText = trim($args);
            if ($repText === '') { sendMessage($peerId, "⚠️ Использование: /report [текст жалобы/вопроса]"); return true; }
            $pdo->prepare("INSERT INTO bot_reports (peer_id, from_vk_id, text, status) VALUES (?, ?, ?, 'open')")->execute([$peerId, $fromId, $repText]);
            $repId = (int)$pdo->lastInsertId();
            sendMessage($peerId, "✅ Ваш репорт (#{$repId}) отправлен администрации. Ожидайте ответа.");
            if ((int)getSystemSetting($pdo, 'report_notify_enabled', '1') === 1) {
                $notifPeer = (int)getSystemSetting($pdo, 'report_notify_peer_id', '0');
                if ($notifPeer > 0) {
                    $kb = makeInlineKeyboard([[
                        ['label' => '🛠 Взять в работу', 'payload' => ['cmd' => "!getreport {$repId} take"], 'color' => 'primary'],
                        ['label' => '🔒 Закрыть', 'payload' => ['cmd' => "!getreport {$repId} close"], 'color' => 'negative']
                    ]]);
                    sendMessage($notifPeer, "🚨 Новый репорт #{$repId}!\n👤 От: " . formatUserMention($pdo, $peerId, $fromId) . " (Чат: {$peerId})\n💬 {$repText}", $kb);
                }
            }
            return true;

        case 'reports':
            $rows = $pdo->query("SELECT * FROM bot_reports WHERE status IN ('open','in_progress') ORDER BY id DESC LIMIT 15")->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "📭 Открытых тикетов нет."); return true; }
            preloadUsers($pdo, $peerId, array_column($rows, 'from_vk_id'));
            $lines = ["📬 Открытые тикеты:"];
            foreach ($rows as $r) {
                $st = ($r['status'] === 'in_progress') ? '🛠' : '🟢';
                $lines[] = "• #{$r['id']} {$st} " . formatUserMention($pdo, $peerId, (int)$r['from_vk_id']) . ": «" . mb_substr($r['text'], 0, 45) . "»";
            }
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'getdialog':
            $repId = (int)trim($args);
            $stmt = $pdo->prepare("SELECT * FROM bot_reports WHERE id = ?");
            $stmt->execute([$repId]);
            $r = $stmt->fetch();
            if (!$r) { sendMessage($peerId, "❌ Тикет #{$repId} не найден."); return true; }
            $txt = "💬 Тикет #{$repId} от " . formatUserMention($pdo, $peerId, (int)$r['from_vk_id']) . ":\n📝 {$r['text']}";
            if (!empty($r['response_text'])) $txt .= "\n\n📣 Ответ: {$r['response_text']}";
            sendMessage($peerId, $txt);
            return true;

        case 'getreport':
            if (trim($args) === '') {
                $stmt = $pdo->prepare("SELECT * FROM bot_reports WHERE from_vk_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$fromId]);
                $r = $stmt->fetch();
                if (!$r) { sendMessage($peerId, "ℹ️ У вас ещё нет отправленных репортов."); return true; }
                sendMessage($peerId, "🗂 Ваш последний тикет #{$r['id']}:\n📌 Статус: {$r['status']}\n📝 {$r['text']}");
                return true;
            }
            if (!$isSuper && $myPriority < 60) { sendMessage($peerId, "⛔ Просмотр чужих тикетов доступен с роли Администратора (60+)."); return true; }
            if (!preg_match('/^(\d+)(?:\s+(take|close)\s*(.*))?$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !getreport [номер тикета]");
                return true;
            }
            $repId = (int)$m[1]; $subAct = mb_strtolower($m[2] ?? ''); $replyTxt = trim($m[3] ?? '');
            $stmt = $pdo->prepare("SELECT * FROM bot_reports WHERE id = ?");
            $stmt->execute([$repId]);
            $r = $stmt->fetch();
            if (!$r) { sendMessage($peerId, "❌ Тикет #{$repId} не найден."); return true; }

            if ($subAct === 'take') {
                $pdo->prepare("UPDATE bot_reports SET status = 'in_progress', handled_by = ? WHERE id = ?")->execute([$fromId, $repId]);
                $r['status'] = 'in_progress'; $r['handled_by'] = $fromId;
                sendMessage((int)$r['peer_id'], "🛠 Ваш репорт #{$repId} взят в работу.");
            } elseif ($subAct === 'close') {
                $pdo->prepare("UPDATE bot_reports SET status = 'closed', handled_by = ?, response_text = ? WHERE id = ?")->execute([$fromId, $replyTxt, $repId]);
                $r['status'] = 'closed'; $r['handled_by'] = $fromId;
                sendMessage((int)$r['peer_id'], "🔒 Ваш репорт #{$repId} закрыт." . ($replyTxt !== '' ? "\n💬 Ответ: {$replyTxt}" : ""));
            }
            $kb = makeInlineKeyboard([[
                ['label' => '🛠 Взять в работу', 'payload' => ['cmd' => "!getreport {$repId} take"], 'color' => 'primary'],
                ['label' => '🔒 Закрыть', 'payload' => ['cmd' => "!getreport {$repId} close"], 'color' => 'negative']
            ]]);
            sendMessage($peerId, "🗂 Тикет #{$repId} [{$r['status']}]\n👤 Автор: " . formatUserMention($pdo, $peerId, (int)$r['from_vk_id']) . "\n💬 Чат: {$r['peer_id']}\n📝 {$r['text']}", $kb);
            return true;

        case 'reportedit':
            $sub = trim($args);
            if (mb_strtolower($sub) === 'toggle') {
                $new = (int)getSystemSetting($pdo, 'report_notify_enabled', '1') ? '0' : '1';
                setSystemSetting($pdo, 'report_notify_enabled', $new);
                sendMessage($peerId, $new === '1' ? "🔔 Уведомления о репортах включены." : "🔕 Уведомления о репортах отключены.");
                return true;
            }
            if (preg_match('/^notify\s+(\d+)$/i', $sub, $m)) {
                $tp = (int)$m[1]; if ($tp > 0 && $tp < 2000000000) $tp += 2000000000;
                setSystemSetting($pdo, 'report_notify_peer_id', (string)$tp);
                sendMessage($peerId, "✅ Чат уведомлений о репортах: {$tp}.");
                return true;
            }
            sendMessage($peerId, "⚠️ !reportedit toggle | !reportedit notify [peerId]");
            return true;

        case 'addws':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !addws [ID/ссылка]"); return true; }
            if (isMainOwner($targetId)) {
                sendMessage($peerId, "⛔ Нельзя изменить статус супер-доступа Главного Создателя бота.");
                return true;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $newS = (int)$tCtx['global']['is_superadmin'] ? 0 : 1;
            $pdo->prepare("UPDATE bot_users SET is_superadmin = ? WHERE vk_id = ?")->execute([$newS, $targetId]);
            clearSuperAccessCache($targetId);
            $uT = formatUserMention($pdo, $peerId, $targetId);
            $uA = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, $newS
                ? "⚡ Выдан супер-доступ (/addws)\n👤 Пользователь: {$uT}\n👑 Выдал: {$uA}"
                : "⚡ Супер-доступ (/addws) отозван\n👤 Пользователь: {$uT}\n👑 Снял: {$uA}");
            return true;

        case 'vip':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !vip [ID/ссылка]"); return true; }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $newV = (int)$tCtx['global']['is_vip'] ? 0 : 1;
            $pdo->prepare("UPDATE bot_users SET is_vip = ? WHERE vk_id = ?")->execute([$newV, $targetId]);
            sendMessage($peerId, $newV ? "💎 VIP-статус выдан " . formatUserMention($pdo, $peerId, $targetId) : "💎 VIP-статус снят.");
            return true;

        case 'setlog':
            $rawPeer = trim($args);
            if ($rawPeer === '') {
                $cur = (int)getSystemSetting($pdo, 'log_peer_id', '0');
                sendMessage($peerId, "📋 Лог-чат: " . ($cur > 0 ? $cur : "Отключён"));
                return true;
            }
            $logId = (int)$rawPeer;
            if ($logId > 0 && $logId < 2000000000) $logId += 2000000000;
            setSystemSetting($pdo, 'log_peer_id', (string)$logId);
            sendMessage($peerId, $logId > 0 ? "✅ Лог-чат установлен: {$logId}" : "🔕 Лог-чат отключён.");
            return true;

        case 'zovvv':
        case 'gzovg':
            $ignoreOpt = ($cmd === 'zovvv');
            $reason = trim($args) !== '' ? trim($args) : 'Глобальное объявление!';
            $sql = $ignoreOpt ? "SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000" : "SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000 AND mentions_enabled = 1";
            $allPeers = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            $caller = formatUserMention($pdo, $peerId, $fromId);
            $calls = [];
            foreach ($allPeers as $pId) {
                $mSql = $ignoreOpt ? "SELECT vk_id FROM bot_chat_members WHERE peer_id = ? LIMIT 50" : "SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND mention_opt_in = 1 LIMIT 50";
                $st = $pdo->prepare($mSql); $st->execute([(int)$pId]);
                $mentions = array_map(fn($u) => "[id{$u}|🔔]", $st->fetchAll(PDO::FETCH_COLUMN));
                $calls[] = [
                    'method' => 'messages.send',
                    'params' => [
                        'peer_id'   => (int)$pId,
                        'message'   => "🚨 Глобальный вызов от {$caller}!\n💬 {$reason}\n\n" . implode(' ', $mentions),
                        'random_id' => random_int(1, PHP_INT_MAX),
                    ]
                ];
            }
            vkApiMulti($calls, 10);
            sendMessage($peerId, "✅ Отправлено в " . count($allPeers) . " бесед.");
            return true;

        case 'gtimeuvedg':
            if (in_array(mb_strtolower(trim($args)), ['0', 'off'], true)) {
                $pdo->query("DELETE FROM bot_timers WHERE peer_id = 0");
                sendMessage($peerId, "⏰ Глобальные автоуведомления отключены.");
                return true;
            }
            if (preg_match('/^(\d{1,2}:\d{2})\s+(.+)$/isu', trim($args), $m)) {
                $pdo->query("DELETE FROM bot_timers WHERE peer_id = 0");
                $pdo->prepare("INSERT INTO bot_timers (peer_id, interval_hours, daily_time, message_text, is_active) VALUES (0, 24, ?, ?, 1)")->execute([$m[1], trim($m[2])]);
                sendMessage($peerId, "⏰ Глобальное ежедневное уведомление на {$m[1]} МСК установлено!");
                return true;
            }
            if (preg_match('/^(\d+)\s+(.+)$/isu', trim($args), $m)) {
                $h = max(1, min(168, (int)$m[1]));
                $pdo->query("DELETE FROM bot_timers WHERE peer_id = 0");
                $pdo->prepare("INSERT INTO bot_timers (peer_id, interval_hours, message_text, last_sent_at, is_active) VALUES (0, ?, ?, NOW(), 1)")->execute([$h, trim($m[2])]);
                sendMessage($peerId, "⏰ Глобальное автоуведомление каждые {$h} ч. установлено!");
                return true;
            }
            sendMessage($peerId, "⚠️ !gtimeuvedg [часы или ЧЧ:ММ] [текст]");
            return true;

        case 'listunities':
            $page = max(1, (int)trim($args));
            $limit = 10; $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM bot_chats c WHERE c.unity_id = u.id) AS chat_cnt FROM bot_unities u ORDER BY u.id ASC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "🔗 Объединений нет (Страница {$page})."); return true; }
            preloadUsers($pdo, $peerId, array_column($rows, 'owner_vk_id'));
            $lines = ["🔗 Все объединения (Страница {$page}):"];
            foreach ($rows as $r) $lines[] = "• #{$r['id']} «{$r['name']}» | Бесед: {$r['chat_cnt']} | " . formatUserMention($pdo, $peerId, (int)$r['owner_vk_id']);
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'listen':
            $arg = mb_strtolower(trim($args));
            if (in_array($arg, ['stop', 'стоп'], true)) {
                $pdo->query("UPDATE bot_chats SET listen_target_vk_id = NULL, listen_until = NULL");
                sendMessage($peerId, "🔇 Все прослушки остановлены.");
                return true;
            }
            if ($arg === '') {
                $rows = $pdo->query("SELECT peer_id, title, listen_until FROM bot_chats WHERE listen_target_vk_id IS NOT NULL AND listen_until > NOW()")->fetchAll();
                if (empty($rows)) { sendMessage($peerId, "🎧 Активных прослушек нет."); return true; }
                $lines = ["🎧 Активные прослушки:"];
                foreach ($rows as $r) $lines[] = "• {$r['peer_id']} ({$r['title']}) до {$r['listen_until']}";
                sendMessage($peerId, implode("\n", $lines));
                return true;
            }
            $tp = (int)$arg; if ($tp > 0 && $tp < 2000000000) $tp += 2000000000;
            $pdo->prepare("UPDATE bot_chats SET listen_target_vk_id = ?, listen_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE peer_id = ?")->execute([$fromId, $tp]);
            sendMessage($peerId, "🎧 Прослушка чата {$tp} включена на 24 часа.");
            return true;

        case 'chatlist':
            $q = trim($args);
            if ($q !== '' && ctype_digit($q) && (int)$q > 100) {
                $tp = (int)$q; if ($tp < 2000000000) $tp += 2000000000;
                $stC = $pdo->prepare("SELECT * FROM bot_chats WHERE peer_id = ?"); $stC->execute([$tp]); $c = $stC->fetch();
                if (!$c) { sendMessage($peerId, "❌ Беседа {$tp} не найдена."); return true; }
                $stMC = $pdo->prepare("SELECT COUNT(*) FROM bot_chat_members WHERE peer_id = ?"); $stMC->execute([$tp]); $mCnt = (int)$stMC->fetchColumn();
                sendMessage($peerId, "💬 Беседа {$tp}:\n• Название: {$c['title']}\n• Участников: {$mCnt}\n• Сообщений: {$c['total_messages']}");
                return true;
            }
            $page = max(1, (int)$q); $limit = 10; $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT c.peer_id, c.title, c.total_messages, (SELECT COUNT(*) FROM bot_chat_members m WHERE m.peer_id = c.peer_id) AS mems FROM bot_chats c ORDER BY c.total_messages DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "💬 Бесед нет (Страница {$page})."); return true; }
            $lines = ["💬 Беседы бота (Страница {$page}):"];
            foreach ($rows as $r) $lines[] = "• {$r['peer_id']} | {$r['title']} | Уч: {$r['mems']} | Сообщ: {$r['total_messages']}";
            sendMessage($peerId, implode("\n", $lines));
            return true;

        case 'userchats':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ !userchats [ID/ссылка]"); return true; }
            $stmt = $pdo->prepare("SELECT c.peer_id, c.title, m.role_priority FROM bot_chat_members m JOIN bot_chats c ON c.peer_id = m.peer_id WHERE m.vk_id = ? LIMIT 30");
            $stmt->execute([$targetId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "ℹ️ Пользователь не найден в беседах бота."); return true; }
            $lines = ["💬 Беседы " . formatUserMention($pdo, $peerId, $targetId) . ":"];
            foreach ($rows as $r) $lines[] = "• {$r['peer_id']} ({$r['title']}) | Роль: {$r['role_priority']}";
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'ahistory':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ !ahistory [ID/ссылка]"); return true; }
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE target_vk_id = ? ORDER BY id DESC LIMIT 20");
            $stmt->execute([$targetId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "📜 Глобальная история наказаний чиста."); return true; }
            $lines = ["🌍 Глобальная история " . formatUserMention($pdo, $peerId, $targetId) . ":"];
            foreach ($rows as $r) $lines[] = "• [" . date('d.m.Y H:i', strtotime($r['created_at'])) . "] Чат {$r['peer_id']} | " . strtoupper($r['type']) . " | {$r['reason']}";
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'checkban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ !checkban [ID/ссылка]"); return true; }
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1");
            $stmt->execute([$targetId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "✅ Активных блокировок ни в одном чате нет."); return true; }
            $lines = ["🚫 Активные блокировки " . formatUserMention($pdo, $peerId, $targetId) . ":"];
            foreach ($rows as $r) $lines[] = "• Чат {$r['peer_id']} | {$r['type']} | До: " . ($r['expires_at'] ?? 'Навсегда') . " | {$r['reason']}";
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        // ==========================================
        // НОВЫЕ АДМИН-СИСТЕМЫ: WHITELIST, ФИЛЬТР, ТРИГГЕРЫ, АНТИСЛИВ, KPI, ПРОМОКОДЫ
        // ==========================================
        case 'whitelist':
            $raw = trim($args);
            if ($raw === '') {
                $list = getChatWhitelist($pdo, $peerId);
                if (empty($list)) {
                    sendMessage($peerId, "🔗 Белый список ссылок пуст.\nДобавить: !whitelist +forum-raserussia.online\nУдалить: !whitelist -forum-raserussia.online");
                } else {
                    sendMessage($peerId, "🔗 Белый список ссылок беседы (пропускаются автомодом):\n• " . implode("\n• ", $list) . "\n\nУправление: !whitelist +домен | !whitelist -домен");
                }
                return true;
            }
            $isDel = false;
            if (preg_match('/^(?:add|\+)\s*(.+)$/iu', $raw, $m)) {
                $dom = trim($m[1]);
            } elseif (preg_match('/^(?:del|remove|-)\s*(.+)$/iu', $raw, $m)) {
                $isDel = true;
                $dom = trim($m[1]);
            } else {
                $dom = $raw;
            }
            $dom = mb_strtolower(preg_replace('/^(?:https?:\/\/)?(?:www\.)?/i', '', trim($dom, " /")));
            if ($dom === '' || mb_strlen($dom) > 120) {
                sendMessage($peerId, "⚠️ Укажите корректный домен или ссылку! Пример: !whitelist +forum-raserussia.online");
                return true;
            }
            $targetWlPeer = $peerId;
            if ($isSuper && preg_match('/^(?:global|глоб)\s+(.+)$/iu', $dom, $gm)) {
                $targetWlPeer = 0;
                $dom = trim($gm[1]);
            }
            if ($isDel) {
                $pdo->prepare("DELETE FROM bot_whitelist WHERE peer_id = ? AND domain = ?")->execute([$targetWlPeer, $dom]);
                cacheDel("wl_{$peerId}");
                sendMessage($peerId, "🗑 «{$dom}» удалён из белого списка ссылок беседы.");
            } else {
                $pdo->prepare("INSERT IGNORE INTO bot_whitelist (peer_id, domain, added_by) VALUES (?, ?, ?)")->execute([$targetWlPeer, $dom, $fromId]);
                cacheDel("wl_{$peerId}");
                sendMessage($peerId, "✅ «{$dom}» добавлен в белый список ссылок! Автомод будет пропускать эти ссылки.");
            }
            return true;

        case 'filter':
            $raw = trim($args);
            if ($raw === '') {
                $list = getChatFilters($pdo, $peerId);
                sendMessage($peerId, empty($list)
                    ? "🛡 Фильтр запрещённых слов пуст.\nДобавить: !фильтр +слово | Удалить: !фильтр -слово"
                    : "🛡 Запрещённые слова беседы (" . count($list) . "):\n• " . implode(", ", $list));
                return true;
            }
            $isDel = false;
            if (preg_match('/^(?:add|\+)\s*(.+)$/iu', $raw, $m)) { $word = mb_strtolower(trim($m[1])); }
            elseif (preg_match('/^(?:del|remove|-)\s*(.+)$/iu', $raw, $m)) { $isDel = true; $word = mb_strtolower(trim($m[1])); }
            else { $word = mb_strtolower($raw); }
            if ($word === '' || mb_strlen($word) > 64) { sendMessage($peerId, "⚠️ Укажите слово (до 64 символов)."); return true; }
            if ($isDel) {
                $pdo->prepare("DELETE FROM bot_filters WHERE peer_id = ? AND word = ?")->execute([$peerId, $word]);
                cacheDel("flt_{$peerId}");
                sendMessage($peerId, "🗑 Слово «{$word}» удалено из фильтра.");
            } else {
                $pdo->prepare("INSERT IGNORE INTO bot_filters (peer_id, word) VALUES (?, ?)")->execute([$peerId, $word]);
                cacheDel("flt_{$peerId}");
                sendMessage($peerId, "✅ Слово «{$word}» добавлено в автоудаление.");
            }
            return true;

        case 'trigger':
            $raw = trim($args);
            if ($raw === '') {
                $trgs = getChatTriggers($pdo, $peerId);
                if (empty($trgs)) {
                    sendMessage($peerId, "⚡ Триггеры (автоответчик) пусты.\nСоздать: !триггер +ip = IP сервера: 1.2.3.4\nУдалить: !триггер -ip");
                } else {
                    $lines = ["⚡ Активные триггеры беседы:"];
                    foreach ($trgs as $k => $v) $lines[] = "• «{$k}» → " . mb_substr($v, 0, 50);
                    sendMessage($peerId, implode("\n", $lines));
                }
                return true;
            }
            if (preg_match('/^(?:del|-)\s*(.+)$/iu', $raw, $m)) {
                $key = mb_strtolower(trim($m[1]));
                $pdo->prepare("DELETE FROM bot_triggers WHERE peer_id = ? AND trigger_key = ?")->execute([$peerId, $key]);
                cacheDel("trg_{$peerId}");
                sendMessage($peerId, "🗑 Триггер «{$key}» удалён.");
                return true;
            }
            if (preg_match('/^(?:\+|add\s+)?([^=]+)=(.+)$/isu', $raw, $m)) {
                $key = mb_strtolower(mb_substr(trim($m[1]), 0, 64));
                $resp = trim($m[2]);
                if ($key === '' || $resp === '') { sendMessage($peerId, "⚠️ Формат: !триггер +слово = текст ответа"); return true; }
                $pdo->prepare("REPLACE INTO bot_triggers (peer_id, trigger_key, response_text) VALUES (?, ?, ?)")->execute([$peerId, $key, $resp]);
                cacheDel("trg_{$peerId}");
                sendMessage($peerId, "⚡ Триггер «{$key}» сохранён! Теперь бот будет сам отвечать на это слово.");
                return true;
            }
            sendMessage($peerId, "⚠️ Формат:\n• !триггер +форум = Наш форум: forum-raserussia.online\n• !триггер -форум");
            return true;

        case 'antidrain':
            $pdo->prepare("UPDATE bot_chats SET antidrain_enabled = 1 - antidrain_enabled WHERE peer_id = ?")->execute([$peerId]);
            $stS = $pdo->prepare("SELECT antidrain_enabled FROM bot_chats WHERE peer_id = ?"); $stS->execute([$peerId]); $st = (int)$stS->fetchColumn();
            sendMessage($peerId, $st
                ? "🛡 Анти-слив включён! Если любой админ (<100) сделает 4+ кика/бана/снятия ролей за 2 минуты — бот снимет его роль и кикнет."
                : "🛡 Анти-слив отключён.");
            return true;

        case 'kpi':
            $days = ctype_digit(trim($args)) ? max(1, min(90, (int)trim($args))) : 7;
            $stmt = $pdo->prepare("
                SELECT m.vk_id, m.role_priority, m.messages_count,
                       (SELECT COUNT(*) FROM bot_punishments p WHERE p.peer_id = m.peer_id AND p.actor_vk_id = m.vk_id AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) AS actions_cnt
                FROM bot_chat_members m
                WHERE m.peer_id = ? AND m.role_priority > 0
                ORDER BY actions_cnt DESC, m.role_priority DESC
                LIMIT 20
            ");
            $stmt->execute([$days, $peerId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "📊 Администрации в беседе пока нет."); return true; }
            preloadUsers($pdo, $peerId, array_column($rows, 'vk_id'));
            $lines = ["📊 KPI / Активность администрации за {$days} дн.:"];
            $i = 1;
            foreach ($rows as $r) {
                $rName = getRoleName($pdo, $peerId, (int)$r['role_priority']);
                $uM = formatUserMention($pdo, $peerId, (int)$r['vk_id']);
                $lines[] = "{$i}. {$uM} ({$rName}) — 🛡 Наказаний: {$r['actions_cnt']} | ✉️ Сообщ: {$r['messages_count']}";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

        case 'createpromo':
            if (!preg_match('/^([A-Za-z0-9А-Яа-яё_\-]+)\s+(\S+)\s+(\d+)$/iu', trim($args), $m)) {
                sendMessage($peerId, "🎟 Использование: !создатьпромо [КОД] [СУММА] [КОЛ-ВО АКТИВАЦИЙ]\nПример: !создатьпромо RASE2026 25к 15");
                return true;
            }
            $code = mb_strtoupper(mb_substr($m[1], 0, 32));
            $reward = parseAmount($m[2], 1000000);
            $uses = max(1, min(500, (int)$m[3]));
            if ($reward === null || $reward <= 0 || (!$isSuper && $reward > 500000)) {
                sendMessage($peerId, "⚠️ Сумма награды должна быть от 1 ₽ до 500 000 ₽.");
                return true;
            }
            $pdo->prepare("REPLACE INTO bot_promocodes (code, reward, uses_left, created_by) VALUES (?, ?, ?, ?)")
                ->execute([$code, $reward, $uses, $fromId]);
            sendMessage($peerId, "🎟 Промокод «{$code}» создан!\n💰 Награда: " . formatMoney($reward) . "\n👥 Активаций: {$uses}\nАктивация: !промо {$code}");
            return true;


        case 'mutelist':
            $page = max(1, (int)trim($args));
            $limit = 10; $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT target_vk_id, reason, expires_at FROM bot_punishments WHERE peer_id = ? AND type = 'mute' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->execute([$peerId, $limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "✅ Заглушённых участников нет (Страница {$page})."); return true; }
            preloadUsers($pdo, $peerId, array_column($rows, 'target_vk_id'));
            $lines = ["🔇 Список активных мутов (Страница {$page}):"];
            $i = $offset + 1;
            foreach ($rows as $r) {
                $exp = $r['expires_at'] ? date('d.m H:i', strtotime($r['expires_at'])) : 'Бессрочно';
                $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['target_vk_id']) . " | До: {$exp} | {$r['reason']}";
                $i++;
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!муты ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!муты ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
            return true;

        case 'slowmode':
            $sec = trim($args);
            if ($sec === '' || !ctype_digit($sec)) {
                $cur = (int)($ctx['chat']['slowmode_sec'] ?? 0);
                sendMessage($peerId, "🐢 Текущий медленный режим: " . ($cur > 0 ? "{$cur} сек." : "выключен") . "\nУстановить: !слоумод [секунды 0-300]");
                return true;
            }
            $val = max(0, min(300, (int)$sec));
            $pdo->prepare("UPDATE bot_chats SET slowmode_sec = ? WHERE peer_id = ?")->execute([$val, $peerId]);
            sendMessage($peerId, $val > 0
                ? "🐢 Медленный режим включён! Участники без роли могут писать 1 сообщение раз в {$val} сек."
                : "🚀 Медленный режим отключён.");
            return true;

        case 'antiraid':
            $pdo->prepare("UPDATE bot_chats SET antiraid_enabled = 1 - antiraid_enabled WHERE peer_id = ?")->execute([$peerId]);
            $stR = $pdo->prepare("SELECT antiraid_enabled FROM bot_chats WHERE peer_id = ?");
            $stR->execute([$peerId]);
            $st = (int)$stR->fetchColumn();
            sendMessage($peerId, $st
                ? "🚨 Анти-рейд включён! Все новые входящие участники будут автоматически исключаться до отключения режима."
                : "✅ Анти-рейд отключён. Вход в беседу снова открыт.");
            return true;


        // ==========================================
        // КОМАНДЫ СУПЕР-ДОСТУПА (/sysban, /sysrole, /giverub, /givesoo и др.)
        // ==========================================
        case 'sysban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: /sysban [ID/ссылка] (дни) (причина)");
                return true;
            }
            $days = 0; $reason = 'Системная блокировка';
            if (preg_match('/^(\d+)(?:\s+(.*))?$/isu', trim($args), $m)) {
                $days = max(1, min(3650, (int)$m[1]));
                if (!empty($m[2])) $reason = trim($m[2]);
            } elseif (trim($args) !== '') { $reason = trim($args); }

            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, 'sysban', $days, $reason);
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }

            $expires = $days > 0 ? date('Y-m-d H:i:s', time() + ($days * 86400)) : null;
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = 0 AND target_vk_id = ? AND type = 'gban'")->execute([$targetId]);
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (0, ?, ?, 'gban', ?, 1, ?)")
                ->execute([$targetId, $fromId, mb_substr($reason, 0, 250), $expires]);

            $allChats = $pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000")->fetchAll(PDO::FETCH_COLUMN);
            $kickCalls = [];
            foreach ($allChats as $cp) {
                $kickCalls[] = ['method' => 'messages.removeChatUser', 'params' => ['chat_id' => (int)$cp - 2000000000, 'member_id' => $targetId]];
            }
            $kickResults = vkApiMulti($kickCalls, 10);
            $kickedOk = 0;
            foreach ($kickResults as $kr) { if (isset($kr['response']) && (int)$kr['response'] === 1) $kickedOk++; }

            $uT = formatUserMention($pdo, $peerId, $targetId);
            $uA = formatUserMention($pdo, $peerId, $fromId);
            $termStr = $days > 0 ? "на {$days} дн." : "Навсегда";
            sendMessage($peerId, "⛔ [SYSBAN] Системная блокировка\n👤 Нарушитель: {$uT}\n👑 Выдал: {$uA}\n⏳ Срок: {$termStr}\n👢 Исключён из бесед: {$kickedOk} из " . count($allChats) . "\n📝 Причина: {$reason}\n🔒 Вход в любые беседы бота закрыт.");
            sendToLogChat($pdo, "SYSBAN: {$uA} -> {$uT} ({$termStr}). Кикнут из {$kickedOk} бесед. Причина: {$reason}");
            return true;

        case 'sysunban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: /sysunban [ID/ссылка]"); return true; }
            $stmt = $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1");
            $stmt->execute([$targetId]);
            $uT = formatUserMention($pdo, $peerId, $targetId);
            $uA = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "✅ [SYSUNBAN] Системная блокировка снята\n👤 Пользователь: {$uT}\n👑 Снял: {$uA}\n📋 Снято блокировок: " . $stmt->rowCount());
            sendToLogChat($pdo, "SYSUNBAN: {$uA} разблокировал {$uT}");
            return true;

        case 'syskick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: /syskick [ID/ссылка] (причина)"); return true; }
            $reason = trim($args) !== '' ? trim($args) : 'Системное исключение';

            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err === 'REQUIRE_SUPER_APPROVAL') {
                requestSuperApproval($pdo, $peerId, $fromId, $targetId, 'syskick', 0, $reason);
                return true;
            }
            if ($err !== null) { sendMessage($peerId, $err); return true; }

            $allChats = $pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000")->fetchAll(PDO::FETCH_COLUMN);
            $kickCalls = [];
            foreach ($allChats as $cp) {
                $kickCalls[] = ['method' => 'messages.removeChatUser', 'params' => ['chat_id' => (int)$cp - 2000000000, 'member_id' => $targetId]];
            }
            $kickResults = vkApiMulti($kickCalls, 10);
            $kickedOk = 0;
            foreach ($kickResults as $kr) { if (isset($kr['response']) && (int)$kr['response'] === 1) $kickedOk++; }

            $uT = formatUserMention($pdo, $peerId, $targetId);
            $uA = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "👢 [SYSKICK] Системное исключение\n👤 Участник: {$uT}\n👑 Исключил: {$uA}\n💬 Исключён из бесед: {$kickedOk} из " . count($allChats) . "\n📝 Причина: {$reason}");
            return true;

        case 'sysrole':
            $raw = trim($args);
            if ($raw === '') {
                sendMessage($peerId, "⚠️ Использование:\n• /sysrole [приоритет или название] — выдать себе роль в этой беседе\n• /sysrole [приоритет] all — выдать себе роль во всех беседах бота\nПримеры: /sysrole 100 | /sysrole 80 | /sysrole Администратор");
                return true;
            }
            $applyAll = false;
            if (preg_match('/^(.+?)\s+(all|везде|все)$/iu', $raw, $m)) {
                $raw = trim($m[1]);
                $applyAll = true;
            }
            $newPriority = null;
            if (ctype_digit($raw)) {
                $newPriority = max(0, min(100, (int)$raw));
            } else {
                foreach (getChatRoles($pdo, $peerId) as $p => $rName) {
                    if (mb_strtolower($rName) === mb_strtolower($raw)) {
                        $newPriority = $p;
                        break;
                    }
                }
            }
            if ($newPriority === null) {
                sendMessage($peerId, "⚠️ Не удалось определить роль «{$raw}». Укажите число от 0 до 100 или точное название роли из !роли.");
                return true;
            }
            if ($applyAll) {
                $allChats = $pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($allChats as $cp) {
                    $pdo->prepare("INSERT INTO bot_chat_members (peer_id, vk_id, role_priority) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role_priority = VALUES(role_priority)")
                        ->execute([(int)$cp, $fromId, $newPriority]);
                }
                $rName = getRoleName($pdo, $peerId, $newPriority);
                sendMessage($peerId, "👑 [SYSROLE] Вы выдали себе роль «{$rName}» (приоритет {$newPriority}) во всех беседах бота (" . count($allChats) . " шт.).");
            } else {
                $pdo->prepare("INSERT INTO bot_chat_members (peer_id, vk_id, role_priority) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role_priority = VALUES(role_priority)")
                    ->execute([$peerId, $fromId, $newPriority]);
                if ($newPriority === 100) {
                    $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ? AND owner_vk_id = 0")->execute([$fromId, $peerId]);
                }
                $rName = getRoleName($pdo, $peerId, $newPriority);
                sendMessage($peerId, "👑 [SYSROLE] Вы выдали себе роль «{$rName}» (приоритет {$newPriority}) в этой беседе.");
            }
            return true;

        case 'sysnick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $newNick = trim($args);
            if (!$targetId || $newNick === '') {
                sendMessage($peerId, "⚠️ Использование: /sysnick [ID/ссылка] [никнейм]");
                return true;
            }
            $cleanNick = mb_substr($newNick, 0, 48);
            $stmt = $pdo->prepare("UPDATE bot_chat_members SET nickname = ? WHERE vk_id = ?");
            $stmt->execute([$cleanNick, $targetId]);
            $pdo->prepare("INSERT INTO bot_chat_members (peer_id, vk_id, nickname) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nickname = VALUES(nickname)")
                ->execute([$peerId, $targetId, $cleanNick]);
            unset($GLOBALS['_mention_cache']["{$peerId}:{$targetId}"]);
            sendMessage($peerId, "🏷 [SYSNICK] Пользователю установлен глобальный никнейм «{$cleanNick}» во всех беседах (обновлено записей: " . max(1, $stmt->rowCount()) . ").");
            return true;

        case 'giverub':
            $rawArgs = trim($args);
            $targetId = extractTargetUser($pdo, $peerId, $msg, $rawArgs);
            if (!$targetId) {
                // Если цель не указана — выдаём самому себе
                $targetId = $fromId;
                $amtStr = $rawArgs;
            } else {
                $amtStr = trim($rawArgs);
            }
            $isSet = false;
            $isNeg = false;
            if (preg_match('/^(set|уст)\s+(.+)$/iu', $amtStr, $m)) {
                $isSet = true;
                $amtStr = trim($m[2]);
            } elseif (str_starts_with($amtStr, '-')) {
                $isNeg = true;
                $amtStr = ltrim($amtStr, '-');
            }
            $amount = parseAmount($amtStr, 0);
            if ($amount === null || ($amount <= 0 && !$isSet)) {
                sendMessage($peerId, "⚠️ Использование: /giverub (ID/ссылка) [сумма]\nПримеры: /giverub 100к | /giverub @user 5кк | /giverub @user -10к | /giverub @user set 50к");
                return true;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            if ($isSet) {
                $pdo->prepare("UPDATE bot_users SET balance = ? WHERE vk_id = ?")->execute([$amount, $targetId]);
                $actText = "установлен баланс " . formatMoney($amount);
            } elseif ($isNeg) {
                $pdo->prepare("UPDATE bot_users SET balance = GREATEST(0, balance - ?) WHERE vk_id = ?")->execute([$amount, $targetId]);
                $actText = "списано " . formatMoney($amount);
            } else {
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$amount, $targetId]);
                $actText = "выдано " . formatMoney($amount);
            }
            $stB = $pdo->prepare("SELECT balance FROM bot_users WHERE vk_id = ?");
            $stB->execute([$targetId]);
            $newBal = (int)$stB->fetchColumn();
            $uT = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "💰 [GIVERUB] Пользователю {$uT} {$actText}.\n💳 Текущий баланс: " . formatMoney($newBal));
            return true;

        case 'givesoo':
            $rawArgs = trim($args);
            $targetId = extractTargetUser($pdo, $peerId, $msg, $rawArgs);
            if (!$targetId) {
                $targetId = $fromId;
                $amtStr = $rawArgs;
            } else {
                $amtStr = trim($rawArgs);
            }
            $isSet = false;
            $isNeg = false;
            if (preg_match('/^(set|уст)\s+(.+)$/iu', $amtStr, $m)) {
                $isSet = true;
                $amtStr = trim($m[2]);
            } elseif (str_starts_with($amtStr, '-')) {
                $isNeg = true;
                $amtStr = ltrim($amtStr, '-');
            }
            $amount = parseAmount($amtStr, 0);
            if ($amount === null || ($amount <= 0 && !$isSet)) {
                sendMessage($peerId, "⚠️ Использование: /givesoo (ID/ссылка) [кол-во]\nПримеры: /givesoo 1000 | /givesoo @user 5к | /givesoo @user set 2500");
                return true;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            if ($isSet) {
                $pdo->prepare("UPDATE bot_chat_members SET messages_count = ? WHERE peer_id = ? AND vk_id = ?")->execute([$amount, $peerId, $targetId]);
                $pdo->prepare("UPDATE bot_users SET total_messages = GREATEST(total_messages, ?) WHERE vk_id = ?")->execute([$amount, $targetId]);
                $actText = "установлено " . number_format($amount, 0, '.', ' ') . " сообщ.";
            } elseif ($isNeg) {
                $pdo->prepare("UPDATE bot_chat_members SET messages_count = GREATEST(0, messages_count - ?) WHERE peer_id = ? AND vk_id = ?")->execute([$amount, $peerId, $targetId]);
                $pdo->prepare("UPDATE bot_users SET total_messages = GREATEST(0, total_messages - ?) WHERE vk_id = ?")->execute([$amount, $targetId]);
                $actText = "списано " . number_format($amount, 0, '.', ' ') . " сообщ.";
            } else {
                $pdo->prepare("UPDATE bot_chat_members SET messages_count = messages_count + ? WHERE peer_id = ? AND vk_id = ?")->execute([$amount, $peerId, $targetId]);
                $pdo->prepare("UPDATE bot_users SET total_messages = total_messages + ? WHERE vk_id = ?")->execute([$amount, $targetId]);
                $actText = "выдано +" . number_format($amount, 0, '.', ' ') . " сообщ.";
            }
            $stM = $pdo->prepare("SELECT messages_count FROM bot_chat_members WHERE peer_id = ? AND vk_id = ?");
            $stM->execute([$peerId, $targetId]);
            $curMsgs = (int)$stM->fetchColumn();
            $uT = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "✉️ [GIVESOO] Пользователю {$uT} {$actText}.\n📊 Сообщений в беседе: " . number_format($curMsgs, 0, '.', ' '));
            return true;

        case 'broadcast':
            $bText = trim($args);
            if ($bText === '') {
                sendMessage($peerId, "⚠️ Использование: /broadcast [текст объявления для всех бесед]");
                return true;
            }
            $allChats = $pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000")->fetchAll(PDO::FETCH_COLUMN);
            $calls = [];
            foreach ($allChats as $cp) {
                $calls[] = [
                    'method' => 'messages.send',
                    'params' => [
                        'peer_id'          => (int)$cp,
                        'message'          => "📢 Объявление от руководства RASE RUSSIA:\n\n{$bText}",
                        'random_id'        => random_int(1, PHP_INT_MAX),
                        'disable_mentions' => 1
                    ]
                ];
            }
            $resList = vkApiMulti($calls, 10);
            $okCnt = 0;
            foreach ($resList as $r) {
                if (isset($r['response'])) $okCnt++;
            }
            sendMessage($peerId, "✅ Рассылка доставлена в {$okCnt} из " . count($allChats) . " бесед.");
            return true;

        case 'botban':
        case 'botunban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "⚠️ Использование: /{$cmd} [ID/ссылка]");
                return true;
            }
            if ($cmd === 'botban' && hasSuperAccess($pdo, $targetId)) {
                sendMessage($peerId, "⛔ Нельзя заблокировать супер-администратора.");
                return true;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $val = ($cmd === 'botban') ? 1 : 0;
            $pdo->prepare("UPDATE bot_users SET is_bot_banned = ? WHERE vk_id = ?")->execute([$val, $targetId]);
            $uT = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, $val
                ? "🚫 Пользователю {$uT} полностью закрыт доступ ко всем командам бота."
                : "✅ Пользователю {$uT} возвращён доступ к командам бота.");
            return true;

        case 'maintenance':
            $cur = (int)getSystemSetting($pdo, 'maintenance_mode', '0');
            $new = $cur ? '0' : '1';
            setSystemSetting($pdo, 'maintenance_mode', $new);
            sendMessage($peerId, $new === '1'
                ? "🛠 Режим технических работ ВКЛЮЧЁН! Команды доступны только супер-администраторам."
                : "✅ Режим технических работ ВЫКЛЮЧЕН! Бот снова доступен всем пользователям.");
            return true;

        case 'sysstats':
            $t0 = microtime(true);
            $uCnt = (int)$pdo->query("SELECT COUNT(*) FROM bot_users")->fetchColumn();
            $cCnt = (int)$pdo->query("SELECT COUNT(*) FROM bot_chats WHERE peer_id > 2000000000")->fetchColumn();
            $unCnt = (int)$pdo->query("SELECT COUNT(*) FROM bot_unities")->fetchColumn();
            $sbCnt = (int)$pdo->query("SELECT COUNT(*) FROM bot_punishments WHERE peer_id = 0 AND type = 'gban' AND is_active = 1")->fetchColumn();
            $mCnt = (int)$pdo->query("SELECT COUNT(*) FROM bot_punishments WHERE type = 'mute' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())")->fetchColumn();
            $remCnt = (int)$pdo->query("SELECT COUNT(*) FROM bot_reminders WHERE is_sent = 0")->fetchColumn();
            $repCnt = (int)$pdo->query("SELECT COUNT(*) FROM bot_reports WHERE status IN ('open','in_progress')")->fetchColumn();
            $dbMs = round((microtime(true) - $t0) * 1000, 1);
            $ramMb = round(memory_get_usage(true) / 1048576, 1);
            $maint = (int)getSystemSetting($pdo, 'maintenance_mode', '0') ? '🛠 Вкл' : '✅ Выкл';

            sendMessage($peerId, "🖥 Системная статистика RASE RUSSIA BOT:\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "👥 Пользователей в БД: " . number_format($uCnt, 0, '.', ' ') . "\n"
                . "💬 Активных бесед: {$cCnt} (Объединений: {$unCnt})\n"
                . "⛔ Активных /sysban: {$sbCnt} | 🔇 Мутов: {$mCnt}\n"
                . "⏰ Ожидающих напоминаний: {$remCnt}\n"
                . "📬 Открытых репортов: {$repCnt}\n"
                . "🛠 Техработы: {$maint}\n"
                . "⚡ Отклик БД: {$dbMs} мс | 💾 RAM: {$ramMb} МБ | PHP " . PHP_VERSION);
            return true;


        case 'superreq':
            if (!isMainOwner($fromId)) {
                sendMessage($peerId, "⛔ Подтверждать или отклонять запросы против супер-доступа может только Создатель бота.");
                return true;
            }
            if (!preg_match('/^(approve|deny)\s+(\d+)$/i', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !superreq [approve/deny] [ID]");
                return true;
            }
            $decision = mb_strtolower($m[1]);
            $reqId = (int)$m[2];
            $stReq = $pdo->prepare("SELECT * FROM bot_super_requests WHERE id = ?");
            $stReq->execute([$reqId]);
            $req = $stReq->fetch();
            if (!$req) { sendMessage($peerId, "❌ Запрос #{$reqId} не найден."); return true; }
            if ($req['status'] !== 'pending') {
                sendMessage($peerId, "ℹ️ Запрос #{$reqId} уже был обработан (статус: {$req['status']}).");
                return true;
            }
            if ($decision === 'approve') {
                $pdo->prepare("UPDATE bot_super_requests SET status = 'approved' WHERE id = ?")->execute([$reqId]);
                $resMsg = executeApprovedSuperAction($pdo, $req, $fromId);
                sendMessage($peerId, "✅ Запрос #{$reqId} ОДОБРЕН!\n📋 {$resMsg}");
            } else {
                $pdo->prepare("UPDATE bot_super_requests SET status = 'denied' WHERE id = ?")->execute([$reqId]);
                $uActor = formatUserMention($pdo, (int)$req['peer_id'], (int)$req['actor_vk_id']);
                $uTarget = formatUserMention($pdo, (int)$req['peer_id'], (int)$req['target_vk_id']);
                sendMessage((int)$req['peer_id'], "❌ Создатель бота отклонил запрос #{$reqId} от {$uActor} на действие против {$uTarget}.");
                sendMessage($peerId, "❌ Запрос #{$reqId} отклонён.");
            }
            return true;

        case 'anticaps':
            $pdo->prepare("UPDATE bot_chats SET anticaps_enabled = 1 - anticaps_enabled WHERE peer_id = ?")->execute([$peerId]);
            $stC = $pdo->prepare("SELECT anticaps_enabled FROM bot_chats WHERE peer_id = ?");
            $stC->execute([$peerId]);
            $st = (int)$stC->fetchColumn();
            sendMessage($peerId, $st
                ? "🔡 Анти-капс включён! Сообщения из сплошных заглавных букв от участников без роли будут удаляться."
                : "🔡 Анти-капс отключён.");
            return true;

        case 'whois':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ($targetId === $fromId) ? $ctx : ensureUserAndMember($pdo, $peerId, $targetId);
            $g = $tCtx['global'];
            $m = $tCtx['member'];
            $uM = formatUserMention($pdo, $peerId, $targetId);
            $rName = getRoleName($pdo, $peerId, (int)$m['role_priority']);
            $invStr = !empty($m['invited_by']) ? formatUserMention($pdo, $peerId, (int)$m['invited_by']) : 'Неизвестно / По ссылке';
            $gmStr = !empty($m['gm_granted_by']) ? ("Активен (от " . formatUserMention($pdo, $peerId, (int)$m['gm_granted_by']) . ", мин. роль: {$m['gm_min_priority']})") : 'Нет';
            $supStr = hasSuperAccess($pdo, $targetId) ? (isMainOwner($targetId) ? '👑 Создатель бота' : '⚡ Супер-доступ') : 'Нет';

            $stW = $pdo->prepare("SELECT COUNT(*) FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1");
            $stW->execute([$peerId, $targetId]);
            $warns = (int)$stW->fetchColumn();

            $stB = $pdo->prepare("SELECT COUNT(*) FROM bot_punishments WHERE target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
            $stB->execute([$targetId]);
            $bans = (int)$stB->fetchColumn();

            sendMessage($peerId, "🔎 Досье участника {$uM} (ID: {$targetId}):\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "🛡 Роль в чате: {$rName} ({$m['role_priority']})\n"
                . "⚡ Статус в боте: {$supStr}" . ((int)$g['is_vip'] ? ' | 💎 VIP' : '') . "\n"
                . "🏷 Никнейм: " . ($m['nickname'] ?: 'Не установлен') . "\n"
                . "🤝 Кто пригласил: {$invStr}\n"
                . "🔰 Иммунитет (/gm): {$gmStr}\n"
                . "⚠️ Активных варнов: {$warns}/3 | 🚫 Активных банов: {$bans}\n"
                . "🔇 Мут сейчас: " . (!empty($m['is_muted']) ? 'Да' : 'Нет') . "\n"
                . "✉️ Сообщений в чате: {$m['messages_count']} (Всего: {$g['total_messages']})", null, ['disable_mentions' => 1]);
            return true;

        case 'syslist':
            $rows = $pdo->query("SELECT vk_id FROM bot_users WHERE is_superadmin = 1 ORDER BY vk_id ASC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
            $owners = array_filter(array_map('intval', explode(',', OWNER_VK_ID)));
            $all = array_values(array_unique(array_merge([MAIN_OWNER_VK_ID], $owners, array_map('intval', $rows))));
            preloadUsers($pdo, $peerId, $all);
            $lines = ["⚡ Список пользователей с супер-доступом (" . count($all) . "):"];
            $i = 1;
            foreach ($all as $uid) {
                $tag = isMainOwner($uid) ? ' 👑 [Создатель]' : '';
                $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, $uid) . " (ID: {$uid}){$tag}";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
            return true;

    }

    return false;
}
