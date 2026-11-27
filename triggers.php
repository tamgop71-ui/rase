<?php
declare(strict_types=1);

/**
 * Движок продвинутых триггеров RASE RUSSIA BOT (!триггер).
 * Поддерживает:
 * - Обычный автоответчик (обратная совместимость со старыми триггерами)
 * - Триггеры на наказания: кик, мут (системный ВК + БД), бан, пред (warn), удалить, снятьроль
 * - Мульти-чатовое действие: текущий чат, список ID бесед (1,2,3), объединение (unity), все чаты (all)
 * - Уведомление во второй чат (лог/админ-чат) с переменными и кнопкой быстрого отката наказания
 * - Режимы совпадения: точное совпадение и вхождение подстроки (~фраза)
 * - Готовый пресет ПСЖ (!триггер псж ...) и тестовый предпросмотр (!триггер тест ...)
 */

define('TRIGGER_JSON_PREFIX', 'TRIGJSON:');

function normalizeTriggerPeerId(string $raw): int {
    $raw = trim($raw);
    if ($raw === '' || !ctype_digit($raw)) return 0;
    $n = (int)$raw;
    if ($n <= 0) return 0;
    return ($n < 2000000000) ? (2000000000 + $n) : $n;
}

function decodeTriggerConfig(string $key, string $rawVal): array {
    $mode = 'exact';
    $cleanKey = $key;
    if (str_starts_with($cleanKey, '~')) {
        $mode = 'contains';
        $cleanKey = ltrim(mb_substr($cleanKey, 1));
    }

    if (str_starts_with($rawVal, TRIGGER_JSON_PREFIX)) {
        $data = json_decode(substr($rawVal, strlen(TRIGGER_JSON_PREFIX)), true);
        if (is_array($data)) {
            return array_merge([
                'key'          => $key,
                'match'        => $cleanKey,
                'mode'         => $data['mode'] ?? $mode,
                'action'       => 'text',
                'duration'     => 0,
                'reason'       => '',
                'reply'        => '',
                'notify_peer'  => 0,
                'notify_text'  => '',
                'target_peers' => 'current', // 'current' | 'unity' | 'all' | int[]
                'delete_msg'   => false,
                'reset_role'   => true,
                'ignore_staff' => false,
            ], $data, ['key' => $key, 'match' => $cleanKey]);
        }
    }

    // Старый простой текстовый триггер
    return [
        'key'          => $key,
        'match'        => $cleanKey,
        'mode'         => $mode,
        'action'       => 'text',
        'duration'     => 0,
        'reason'       => '',
        'reply'        => $rawVal,
        'notify_peer'  => 0,
        'notify_text'  => '',
        'target_peers' => 'current',
        'delete_msg'   => false,
        'reset_role'   => false,
        'ignore_staff' => false,
    ];
}

function encodeTriggerConfig(array $cfg): string {
    $save = [
        'mode'         => $cfg['mode'] ?? 'exact',
        'action'       => $cfg['action'] ?? 'text',
        'duration'     => (int)($cfg['duration'] ?? 0),
        'reason'       => (string)($cfg['reason'] ?? ''),
        'reply'        => (string)($cfg['reply'] ?? ''),
        'notify_peer'  => (int)($cfg['notify_peer'] ?? 0),
        'notify_text'  => (string)($cfg['notify_text'] ?? ''),
        'target_peers' => $cfg['target_peers'] ?? 'current',
        'delete_msg'   => !empty($cfg['delete_msg']),
        'reset_role'   => !empty($cfg['reset_role']),
        'ignore_staff' => !empty($cfg['ignore_staff']),
    ];
    return TRIGGER_JSON_PREFIX . json_encode($save, JSON_UNESCAPED_UNICODE);
}

function triggerActionLabel(array $cfg): string {
    $act = (string)($cfg['action'] ?? 'text');
    $dur = (int)($cfg['duration'] ?? 0);
    return match ($act) {
        'kick'       => '👢 Кик',
        'mute'       => '🔇 Мут (' . ($dur > 0 ? $dur : 30) . ' мин.)',
        'ban'        => '🔨 Бан (' . ($dur > 0 ? $dur : 7) . ' дн.)',
        'warn'       => '⚠️ Пред (warn)',
        'del'        => '🗑 Удаление сообщения',
        'removerole' => '🛡 Снятие роли и ника',
        default      => '💬 Текстовый ответ',
    };
}

function triggerTargetPeersLabel($tp): string {
    if ($tp === 'current' || empty($tp)) return 'текущая беседа';
    if ($tp === 'unity') return 'объединение бесед';
    if ($tp === 'all') return 'все беседы бота';
    if (is_array($tp)) {
        $short = array_map(fn($p) => '#' . ((int)$p > 2000000000 ? ((int)$p - 2000000000) : (int)$p), $tp);
        return implode(', ', $short);
    }
    return 'текущая беседа';
}

function resolveTriggerTargetPeers(PDO $pdo, int $currentPeerId, $tp): array {
    if ($tp === 'unity') {
        return getUnityPeers($pdo, $currentPeerId);
    }
    if ($tp === 'all') {
        $rows = $pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000")->fetchAll(PDO::FETCH_COLUMN);
        $list = array_values(array_unique(array_map('intval', $rows)));
        if (!in_array($currentPeerId, $list, true) && $currentPeerId > 2000000000) {
            $list[] = $currentPeerId;
        }
        return $list;
    }
    if (is_array($tp) && !empty($tp)) {
        return array_values(array_unique(array_filter(array_map('intval', $tp), fn($p) => $p > 2000000000)));
    }
    return ($currentPeerId > 2000000000) ? [$currentPeerId] : [];
}

function renderTriggerVars(string $tpl, array $vars): string {
    if ($tpl === '') return '';
    return strtr($tpl, $vars);
}

/**
 * Поиск сработавшего триггера в тексте сообщения (сначала точное совпадение, затем ~вхождение)
 */
function findMatchedTrigger(array $rawTriggers, string $text): ?array {
    $low = mb_strtolower(trim($text));
    if ($low === '') return null;

    // 1. Точное совпадение
    if (isset($rawTriggers[$low])) {
        return decodeTriggerConfig($low, (string)$rawTriggers[$low]);
    }

    // 2. Поиск по вхождению (ключи с ~ или mode=contains)
    foreach ($rawTriggers as $k => $v) {
        $cfg = decodeTriggerConfig((string)$k, (string)$v);
        if ($cfg['mode'] === 'contains' && $cfg['match'] !== '' && str_contains($low, $cfg['match'])) {
            return $cfg;
        }
    }
    return null;
}

/**
 * Выполнение сработавшего триггера в реальном времени из bot.php
 */
function executeChatTrigger(PDO $pdo, int $peerId, int $fromId, int $cmid, string $text, array $ctx, array $cfg): bool {
    $action = (string)($cfg['action'] ?? 'text');
    $isSuper = ((int)($ctx['global']['is_superadmin'] ?? 0) === 1) || hasSuperAccess($pdo, $fromId);
    $myPriority = $isSuper ? 100 : (int)($ctx['member']['role_priority'] ?? 0);

    // Защита от наказаний: если включён ignore_staff — не наказываем админов (20+) и супер-доступ
    if ($action !== 'text' && !empty($cfg['ignore_staff']) && ($isSuper || $myPriority >= 20)) {
        return false;
    }

    // Главных Создателей (831772755, 749573883) никогда не наказываем авто-триггерами (кроме текстового ответа)
    if ($action !== 'text' && isMainOwner($fromId)) {
        return false;
    }

    // Собираем переменные ДО сброса ника и роли (чтобы во второй чат ушли реальные ник и роль!)
    $uNom  = formatUserMention($pdo, $peerId, $fromId, 'nom');
    $uGen  = formatUserMention($pdo, $peerId, $fromId, 'gen');
    $uAcc  = formatUserMention($pdo, $peerId, $fromId, 'acc');
    $gName = trim((string)($ctx['global']['first_name'] ?? '') . ' ' . (string)($ctx['global']['last_name'] ?? ''));
    $nick  = trim((string)($ctx['member']['nickname'] ?? '')) ?: ($gName ?: "id{$fromId}");
    $rName = getRoleName($pdo, $peerId, (int)($ctx['member']['role_priority'] ?? 0)) . " ({$ctx['member']['role_priority']})";
    $chatTitle = (string)($ctx['chat']['title'] ?? "Беседа #{$peerId}");

    $vars = [
        '{user}'         => $uNom,
        '{user_gen}'     => $uGen,
        '{user_acc}'     => $uAcc,
        '{name}'         => $gName ?: "id{$fromId}",
        '{nick}'         => $nick,
        '{id}'           => (string)$fromId,
        '{role}'         => $rName,
        '{chat}'         => $chatTitle,
        '{peer_id}'      => (string)$peerId,
        '{time}'         => date('d.m.Y H:i'),
        '{text}'         => mb_substr($text, 0, 200),
        '{kicked_count}' => '0',
    ];

    // Простой текстовый ответ
    if ($action === 'text') {
        $replyText = renderTriggerVars((string)$cfg['reply'], $vars);
        if (!empty($cfg['delete_msg']) && $cmid > 0 && $peerId > 2000000000) {
            deleteAndSend($peerId, [$cmid], $replyText !== '' ? $replyText : '—');
        } elseif ($replyText !== '') {
            sendMessage($peerId, $replyText);
        }
        $notifyPeer = (int)($cfg['notify_peer'] ?? 0);
        if ($notifyPeer > 0 && !empty($cfg['notify_text'])) {
            sendMessage($notifyPeer, renderTriggerVars((string)$cfg['notify_text'], $vars), null, ['disable_mentions' => 1]);
        }
        return true;
    }

    // Удаление исходного сообщения игрока (если включено или действие = del)
    if (($action === 'del' || !empty($cfg['delete_msg'])) && $cmid > 0 && $peerId > 2000000000) {
        deleteMessage($peerId, [$cmid]);
    }

    $targetPeers = resolveTriggerTargetPeers($pdo, $peerId, $cfg['target_peers'] ?? 'current');
    $reason = renderTriggerVars((string)($cfg['reason'] ?: "Триггер «{$cfg['match']}»"), $vars);
    $reasonDb = mb_substr($reason, 0, 250);
    $rollbackKb = null;
    $actedCount = 0;

    if ($action === 'kick') {
        $calls = [];
        foreach ($targetPeers as $tp) {
            $calls[$tp] = [
                'method' => 'messages.removeChatUser',
                'params' => ['chat_id' => $tp - 2000000000, 'member_id' => $fromId]
            ];
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active) VALUES (?, ?, 0, 'kick', ?, 0)")
                ->execute([$tp, $fromId, $reasonDb]);
            if (!empty($cfg['reset_role'])) {
                resetMemberOnLeave($pdo, $tp, $fromId);
            }
        }
        $failedKickChats = [];
        if (!empty($calls)) {
            $resMulti = vkApiMulti($calls, 10);
            foreach ($resMulti as $tpKey => $res) {
                if (isset($res['response']) && (int)$res['response'] === 1) {
                    $actedCount++;
                } else {
                    $eCode = (int)($res['error']['error_code'] ?? 0);
                    $shortId = ((int)$tpKey > 2000000000) ? ((int)$tpKey - 2000000000) : (int)$tpKey;
                    $why = in_array($eCode, [15, 925], true) ? 'админ ВК / звезда' : "код {$eCode}";
                    $failedKickChats[] = "#{$shortId} ({$why})";
                }
            }
        }
        $vars['{kicked_count}'] = (string)$actedCount;
    } elseif ($action === 'mute') {
        $mins = max(1, min(43200, (int)($cfg['duration'] ?: 30)));
        $expires = date('Y-m-d H:i:s', time() + $mins * 60);
        foreach ($targetPeers as $tp) {
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute'")->execute([$tp, $fromId]);
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, 0, 'mute', ?, 1, ?)")
                ->execute([$tp, $fromId, $reasonDb, $expires]);
            vkSystemMute($tp, $fromId, $mins * 60);
            $actedCount++;
        }
        $vars['{kicked_count}'] = (string)$actedCount;
        $rollbackKb = makeInlineKeyboard([[
            ['label' => '🔊 Снять мут', 'payload' => ['cmd' => count($targetPeers) > 1 ? "/sysunmute {$fromId}" : "!unmute {$fromId}"], 'color' => 'positive']
        ]]);
    } elseif ($action === 'ban') {
        $days = max(1, min(3650, (int)($cfg['duration'] ?: 7)));
        $expires = date('Y-m-d H:i:s', time() + $days * 86400);
        $calls = [];
        foreach ($targetPeers as $tp) {
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type IN ('ban','gban')")->execute([$tp, $fromId]);
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, 0, 'ban', ?, 1, ?)")
                ->execute([$tp, $fromId, $reasonDb, $expires]);
            if (!empty($cfg['reset_role'])) {
                resetMemberOnLeave($pdo, $tp, $fromId);
            }
            $calls[$tp] = [
                'method' => 'messages.removeChatUser',
                'params' => ['chat_id' => $tp - 2000000000, 'member_id' => $fromId]
            ];
        }
        if (!empty($calls)) {
            $resMulti = vkApiMulti($calls, 10);
            foreach ($resMulti as $res) {
                if (isset($res['response']) && (int)$res['response'] === 1) {
                    $actedCount++;
                }
            }
        }
        $vars['{kicked_count}'] = (string)$actedCount;
        $rollbackKb = makeInlineKeyboard([[
            ['label' => '🔓 Снять бан', 'payload' => ['cmd' => "!unban {$fromId}"], 'color' => 'positive']
        ]]);
    } elseif ($action === 'warn') {
        $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active) VALUES (?, ?, 0, 'warn', ?, 1)")
            ->execute([$peerId, $fromId, $reasonDb]);
        $stW = $pdo->prepare("SELECT COUNT(*) FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1");
        $stW->execute([$peerId, $fromId]);
        $wCount = (int)$stW->fetchColumn();
        if ($wCount >= 3 && $peerId > 2000000000) {
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn'")->execute([$peerId, $fromId]);
            vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $fromId]);
            resetMemberOnLeave($pdo, $peerId, $fromId);
            $actedCount = 1;
        }
        $rollbackKb = makeInlineKeyboard([[
            ['label' => '✅ Снять пред', 'payload' => ['cmd' => "!unwarn {$fromId}"], 'color' => 'positive']
        ]]);
    } elseif ($action === 'removerole') {
        foreach ($targetPeers as $tp) {
            resetMemberOnLeave($pdo, $tp, $fromId);
            $actedCount++;
        }
        $vars['{kicked_count}'] = (string)$actedCount;
    }

    // 1. Ответ в текущий (первый) чат
    $replyText = renderTriggerVars((string)($cfg['reply'] ?? ''), $vars);
    if ($replyText !== '') {
        sendMessage($peerId, $replyText, $rollbackKb, ['disable_mentions' => 1]);
    }

    // 2. Уведомление во второй чат (если задан)
    $notifyPeer = (int)($cfg['notify_peer'] ?? 0);
    if ($notifyPeer > 0) {
        $notifyBody = (string)($cfg['notify_text'] ?? '');
        if ($notifyBody === '') {
            $notifyBody = "⚡ [ТРИГГЕР] {$uNom} (Ник: {$nick} | Роль: {$rName} | ID: {$fromId})\n"
                        . "💬 Беседа: «{$chatTitle}»\n"
                        . "🎯 Действие: " . triggerActionLabel($cfg) . "\n"
                        . "📝 Причина: {$reason}";
        } else {
            $notifyBody = renderTriggerVars($notifyBody, $vars);
        }
        if (!empty($failedKickChats)) {
            $notifyBody .= "\n⚠️ Не удалось кикнуть из: " . implode(', ', $failedKickChats);
        }
        // Кнопку отката в чужой чат вешаем только если это глобальное снятие (sysunmute) или тот же чат
        $kb2 = ($notifyPeer === $peerId) ? $rollbackKb : null;
        sendMessage($notifyPeer, $notifyBody, $kb2, ['disable_mentions' => 1]);
    }

    sendToLogChat($pdo, "Триггер «{$cfg['match']}» в чате {$peerId}: {$uNom} -> " . triggerActionLabel($cfg) . " ({$reason})");
    return true;
}

/**
 * Обработчик команды !триггер (управление, пресет ПСЖ, конструктор, инфо, тест, удаление)
 */
function handleTriggerCommand(PDO $pdo, int $peerId, int $fromId, string $args, bool $isSuper, array $ctx): void {
    $raw = trim($args);

    // 1. Список триггеров беседы
    if ($raw === '' || mb_strtolower($raw) === 'список' || mb_strtolower($raw) === 'list') {
        $trgs = getChatTriggers($pdo, $peerId);
        if (empty($trgs)) {
            sendMessage(
                $peerId,
                "⚡ Триггеры беседы пусты.\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "📌 Быстрый пресет ПСЖ:\n"
                . "• !триггер псж [ID_чат_отчёта] (ID_чатов_кика)\n"
                . "  Пример: !триггер псж 15 1,2,3\n\n"
                . "📌 Обычный автоответчик:\n"
                . "• !триггер +форум = Наш форум: forum-raserussia.online\n"
                . "• !триггер ~реклама | действие:мут 60 | удалить:да | чат2:15\n\n"
                . "📖 Полная справка: !триггер помощь"
            );
            return;
        }
        $lines = ["⚡ Активные триггеры беседы (" . count($trgs) . "):"];
        $i = 1;
        foreach ($trgs as $k => $v) {
            $cfg = decodeTriggerConfig((string)$k, (string)$v);
            $modeTag = $cfg['mode'] === 'contains' ? '[вхождение]' : '[точно]';
            $actTag  = triggerActionLabel($cfg);
            $extra   = '';
            if (!empty($cfg['notify_peer'])) {
                $npShort = (int)$cfg['notify_peer'] > 2000000000 ? ((int)$cfg['notify_peer'] - 2000000000) : (int)$cfg['notify_peer'];
                $extra .= " → чат #{$npShort}";
            }
            if ($cfg['action'] !== 'text' && $cfg['target_peers'] !== 'current') {
                $extra .= " [" . triggerTargetPeersLabel($cfg['target_peers']) . "]";
            }
            $lines[] = "{$i}. «{$cfg['match']}» {$modeTag} — {$actTag}{$extra}";
            $i++;
        }
        $lines[] = "\n🔎 Подробнее: !триггер инфо [фраза]\n🗑 Удалить: !триггер -[фраза]\n📖 Конструктор: !триггер помощь";
        sendLongMessage($peerId, implode("\n", $lines));
        return;
    }

    // 2. Подробная справка по конструктору и переменным
    if (in_array(mb_strtolower($raw), ['помощь', 'help', '?'], true)) {
        sendMessage(
            $peerId,
            "⚡ КОНСТРУКТОР ТРИГГЕРОВ RASE RUSSIA\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "1️⃣ Пресет ПСЖ (одной командой):\n"
            . "• !триггер псж [чат_отчёта] (чаты_кика)\n"
            . "  Пример: !триггер псж 15 — кик из этого чата + отчёт в #15\n"
            . "  Пример: !триггер псж 15 1,2,3 — кик из #1,#2,#3 + отчёт в #15\n"
            . "  Пример: !триггер псж 15 unity — кик из объединения + отчёт в #15\n\n"
            . "2️⃣ Конструктор наказаний (через |):\n"
            . "• !триггер +я псж | действие:кик | чаты:1,2,3 | чат2:15 | текст2:🚪 {user} ушёл ПСЖ (Ник: {nick}, Роль: {role})\n"
            . "• !триггер ~реклама | действие:мут 60 | удалить:да | причина:Реклама | чат2:15\n"
            . "• !триггер ~слив | действие:бан 7 | чаты:unity | причина:Слив\n"
            . "• !триггер ~плохо | действие:пред | удалить:да | ответ:⚠️ {user}, без этого!\n"
            . "  (+фраза — точное совпадение, ~фраза — если фраза есть внутри сообщения)\n\n"
            . "3️⃣ Ключи конструктора:\n"
            . "• действие: кик / мут [мин] / бан [дни] / пред / удалить / снятьроль / текст\n"
            . "• чаты: текущий / 1,2,3 / unity / all (all — супер-доступ)\n"
            . "• чат2: [ID] | текст2: [текст] | ответ: [текст] | причина: [текст]\n"
            . "• удалить: да/нет | длявсех: да/нет (срабатывать ли на админов)\n\n"
            . "4️⃣ Переменные:\n"
            . "{user}, {name}, {nick}, {id}, {role}, {chat}, {time}, {text}, {kicked_count}\n\n"
            . "5️⃣ Управление: !триггер инфо [фраза] | !триггер тест [фраза] | !триггер -[фраза]"
        );
        return;
    }

    // 3. Удаление триггера: !триггер -фраза или !триггер del фраза
    if (preg_match('/^(?:del|удалить|-)\s*(.+)$/iu', $raw, $m)) {
        $keyRaw = mb_strtolower(trim($m[1]));
        $altKey = str_starts_with($keyRaw, '~') ? ltrim(mb_substr($keyRaw, 1)) : ('~' . $keyRaw);
        $st = $pdo->prepare("DELETE FROM bot_triggers WHERE peer_id = ? AND trigger_key IN (?, ?)");
        $st->execute([$peerId, $keyRaw, $altKey]);
        cacheDel("trg_{$peerId}");
        sendMessage($peerId, $st->rowCount() > 0
            ? "🗑 Триггер «" . ltrim($keyRaw, '~') . "» удалён."
            : "❌ Триггер «" . ltrim($keyRaw, '~') . "» не найден в этой беседе.");
        return;
    }

    // 4. Подробная карточка триггера: !триггер инфо [фраза]
    if (preg_match('/^(?:инфо|info)\s+(.+)$/iu', $raw, $m)) {
        $qKey = mb_strtolower(trim($m[1]));
        $trgs = getChatTriggers($pdo, $peerId);
        $foundKey = isset($trgs[$qKey]) ? $qKey : (isset($trgs['~' . $qKey]) ? ('~' . $qKey) : null);
        if ($foundKey === null) {
            sendMessage($peerId, "❌ Триггер «{$qKey}» не найден.");
            return;
        }
        $cfg = decodeTriggerConfig($foundKey, (string)$trgs[$foundKey]);
        $npStr = !empty($cfg['notify_peer']) ? ("{$cfg['notify_peer']} (#" . ((int)$cfg['notify_peer'] - 2000000000) . ")") : 'Нет';
        $info = "⚡ Карточка триггера «{$cfg['match']}»\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "🔍 Режим: " . ($cfg['mode'] === 'contains' ? 'вхождение в текст (~)' : 'точное совпадение') . "\n"
              . "🎯 Действие: " . triggerActionLabel($cfg) . "\n"
              . "💬 Беседы действия: " . triggerTargetPeersLabel($cfg['target_peers']) . "\n"
              . "📩 Второй чат (отчёт): {$npStr}\n"
              . "🗑 Удалять сообщение: " . (!empty($cfg['delete_msg']) ? 'Да' : 'Нет') . "\n"
              . "👮 Действует на админов: " . (empty($cfg['ignore_staff']) ? 'Да' : 'Нет (только участники)') . "\n"
              . ($cfg['reason'] !== '' ? "📝 Причина: {$cfg['reason']}\n" : "")
              . ($cfg['reply'] !== '' ? "💬 Ответ в чат: {$cfg['reply']}\n" : "")
              . ($cfg['notify_text'] !== '' ? "📢 Текст во 2-й чат: {$cfg['notify_text']}\n" : "");

        $kb = makeInlineKeyboard([[
            ['label' => '🗑 Удалить триггер', 'payload' => ['cmd' => "!триггер -{$foundKey}"], 'color' => 'negative']
        ]]);
        sendMessage($peerId, trim($info), $kb);
        return;
    }

    // 5. Тестовый предпросмотр без наказания: !триггер тест [фраза]
    if (preg_match('/^(?:тест|test)\s+(.+)$/iu', $raw, $m)) {
        $sample = trim($m[1]);
        $trgs = getChatTriggers($pdo, $peerId);
        $cfg = findMatchedTrigger($trgs, $sample);
        if (!$cfg) {
            sendMessage($peerId, "ℹ️ На фразу «{$sample}» не сработал ни один триггер этой беседы.");
            return;
        }
        $uNom = formatUserMention($pdo, $peerId, $fromId);
        $gName = trim((string)($ctx['global']['first_name'] ?? '') . ' ' . (string)($ctx['global']['last_name'] ?? ''));
        $nick = trim((string)($ctx['member']['nickname'] ?? '')) ?: ($gName ?: "id{$fromId}");
        $rName = getRoleName($pdo, $peerId, (int)($ctx['member']['role_priority'] ?? 0)) . " ({$ctx['member']['role_priority']})";
        $chatTitle = (string)($ctx['chat']['title'] ?? "Беседа #{$peerId}");
        $vars = [
            '{user}' => $uNom, '{user_gen}' => formatUserMention($pdo, $peerId, $fromId, 'gen'),
            '{user_acc}' => formatUserMention($pdo, $peerId, $fromId, 'acc'),
            '{name}' => $gName, '{nick}' => $nick, '{id}' => (string)$fromId,
            '{role}' => $rName, '{chat}' => $chatTitle, '{peer_id}' => (string)$peerId,
            '{time}' => date('d.m.Y H:i'), '{text}' => $sample, '{kicked_count}' => '1',
        ];
        $preview = "🧪 [ТЕСТ ТРИГГЕРА] Сработал: «{$cfg['match']}»\n"
                 . "🎯 Действие: " . triggerActionLabel($cfg) . " (" . triggerTargetPeersLabel($cfg['target_peers']) . ")\n"
                 . ($cfg['reply'] !== '' ? "💬 В этот чат:\n" . renderTriggerVars($cfg['reply'], $vars) . "\n" : "")
                 . (!empty($cfg['notify_peer']) ? "📩 В чат #{$cfg['notify_peer']}:\n" . renderTriggerVars($cfg['notify_text'] ?: 'Стандартный отчёт', $vars) : "");
        sendMessage($peerId, trim($preview), null, ['disable_mentions' => 1]);
        return;
    }

    // 6. Готовый пресет ПСЖ: !триггер псж [ID_чат_отчёта] (ID_чатов_кика: 1,2,3 / unity / all)
    if (preg_match('/^(?:псж|psj)(?:\s+(.*))?$/iu', $raw, $m)) {
        $rest = trim((string)($m[1] ?? ''));
        if ($rest === '') {
            sendMessage(
                $peerId,
                "🚪 Быстрая настройка триггера ПСЖ:\n"
                . "• !триггер псж [ID_чат_отчёта] — при «я псж» кикает из этого чата и пишет отчёт в указанный чат\n"
                . "• !триггер псж [ID_чат_отчёта] [ID1,ID2,...] — кикает сразу из указанных бесед\n"
                . "• !триггер псж [ID_чат_отчёта] unity — кикает из всего объединения бесед\n\n"
                . "Пример: !триггер псж 15 1,2,3"
            );
            return;
        }
        $parts = preg_split('/\s+/u', $rest);
        $notifyPeer = normalizeTriggerPeerId((string)($parts[0] ?? ''));
        if ($notifyPeer <= 2000000000) {
            sendMessage($peerId, "⚠️ Укажите корректный ID второго чата для отчёта (например: 15 или 2000000015).");
            return;
        }

        $targetPeers = 'current';
        if (!empty($parts[1])) {
            $tpRaw = mb_strtolower(trim((string)$parts[1]));
            if ($tpRaw === 'unity' || $tpRaw === 'объединение') {
                $targetPeers = 'unity';
            } elseif ($tpRaw === 'all' || $tpRaw === 'все') {
                if (!$isSuper) {
                    sendMessage($peerId, "⛔ Кик из всех бесед бота (all) доступен только супер-доступу. Используйте unity или список ID.");
                    return;
                }
                $targetPeers = 'all';
            } else {
                $ids = [];
                foreach (explode(',', $tpRaw) as $pTok) {
                    $np = normalizeTriggerPeerId($pTok);
                    if ($np > 2000000000) $ids[] = $np;
                }
                if (!empty($ids)) $targetPeers = array_values(array_unique($ids));
            }
        }

        $cfg = [
            'mode'         => 'exact',
            'action'       => 'kick',
            'duration'     => 0,
            'reason'       => 'Ушёл ПСЖ',
            'reply'        => '👋 {user} покинул(а) проект по собственному желанию (ПСЖ).',
            'notify_peer'  => $notifyPeer,
            'notify_text'  => "🚪 [ПСЖ] Игрок {user} ушёл по собственному желанию!\n━━━━━━━━━━━━━━━━━━\n🏷 Ник: {nick}\n🛡 Бывшая роль: {role}\n🆔 VK ID: {id}\n💬 Из беседы: «{chat}»\n👢 Исключён из бесед: {kicked_count}\n🕒 Время: {time} МСК",
            'target_peers' => $targetPeers,
            'delete_msg'   => false,
            'reset_role'   => true,
            'ignore_staff' => false,
        ];

        $pdo->prepare("REPLACE INTO bot_triggers (peer_id, trigger_key, response_text) VALUES (?, ?, ?)")
            ->execute([$peerId, 'я псж', encodeTriggerConfig($cfg)]);
        cacheDel("trg_{$peerId}");

        $shortN = $notifyPeer - 2000000000;
        $kb = makeInlineKeyboard([[
            ['label' => '🗑 Удалить триггер', 'payload' => ['cmd' => '!триггер -я псж'], 'color' => 'negative']
        ]]);
        sendMessage(
            $peerId,
            "✅ Триггер «я псж» успешно настроен!\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "🎯 Действие: 👢 Кик + сброс ника и роли\n"
            . "💬 Откуда кикнет: " . triggerTargetPeersLabel($targetPeers) . "\n"
            . "📩 Отчёт во 2-й чат: {$notifyPeer} (#{$shortN})\n"
            . "💡 Проверить без кика: !триггер тест я псж",
            $kb
        );
        return;
    }

    // 7. Конструктор расширенного триггера через разделитель «|»
    if (str_contains($raw, '|')) {
        $segments = array_map('trim', explode('|', $raw));
        $head = array_shift($segments);
        $head = trim((string)preg_replace('/^(?:\+|add\s+)/iu', '', (string)$head));

        $mode = 'exact';
        if (str_starts_with($head, '~')) {
            $mode = 'contains';
            $head = trim(mb_substr($head, 1));
        }
        $matchKey = mb_strtolower(mb_substr($head, 0, 62));
        if ($matchKey === '') {
            sendMessage($peerId, "⚠️ Укажите фразу триггера перед знаком «|». Пример: !триггер +я псж | действие:кик | чат2:15");
            return;
        }

        $cfg = [
            'mode'         => $mode,
            'action'       => 'text',
            'duration'     => 0,
            'reason'       => '',
            'reply'        => '',
            'notify_peer'  => 0,
            'notify_text'  => '',
            'target_peers' => 'current',
            'delete_msg'   => false,
            'reset_role'   => true,
            'ignore_staff' => null, // определим автоматически по типу действия, если явно не указали
        ];

        foreach ($segments as $seg) {
            if ($seg === '' || !str_contains($seg, ':')) continue;
            [$kRaw, $vRaw] = explode(':', $seg, 2);
            $k = mb_strtolower(trim($kRaw));
            $v = trim($vRaw);

            if (in_array($k, ['действие', 'action', 'act'], true)) {
                $vl = mb_strtolower($v);
                if (preg_match('/^(кик|kick)$/u', $vl)) {
                    $cfg['action'] = 'kick';
                } elseif (preg_match('/^(мут|mute)(?:\s+(\d+))?$/u', $vl, $am)) {
                    $cfg['action'] = 'mute';
                    $cfg['duration'] = !empty($am[2]) ? max(1, min(43200, (int)$am[2])) : 30;
                } elseif (preg_match('/^(бан|ban)(?:\s+(\d+))?$/u', $vl, $am)) {
                    $cfg['action'] = 'ban';
                    $cfg['duration'] = !empty($am[2]) ? max(1, min(3650, (int)$am[2])) : 7;
                } elseif (preg_match('/^(пред|варн|warn)$/u', $vl)) {
                    $cfg['action'] = 'warn';
                } elseif (preg_match('/^(удалить|del|delete)$/u', $vl)) {
                    $cfg['action'] = 'del';
                    $cfg['delete_msg'] = true;
                } elseif (preg_match('/^(снятьроль|removerole|resetrole)$/u', $vl)) {
                    $cfg['action'] = 'removerole';
                } elseif (preg_match('/^(текст|text)$/u', $vl)) {
                    $cfg['action'] = 'text';
                }
            } elseif (in_array($k, ['чаты', 'chats', 'беседы', 'peers'], true)) {
                $vl = mb_strtolower($v);
                if ($vl === 'текущий' || $vl === 'current') {
                    $cfg['target_peers'] = 'current';
                } elseif ($vl === 'объединение' || $vl === 'unity') {
                    $cfg['target_peers'] = 'unity';
                } elseif ($vl === 'все' || $vl === 'all') {
                    if (!$isSuper) {
                        sendMessage($peerId, "⛔ Параметр «чаты:все» доступен только супер-доступу. Используйте «чаты:unity» или список ID.");
                        return;
                    }
                    $cfg['target_peers'] = 'all';
                } else {
                    $ids = [];
                    foreach (preg_split('/[,\s]+/', $vl) as $tok) {
                        $np = normalizeTriggerPeerId($tok);
                        if ($np > 2000000000) $ids[] = $np;
                    }
                    if (!empty($ids)) $cfg['target_peers'] = array_values(array_unique($ids));
                }
            } elseif (in_array($k, ['чат2', 'лог', 'notify', 'чат_отчета', 'второйчат'], true)) {
                $np = normalizeTriggerPeerId($v);
                if ($np > 2000000000) $cfg['notify_peer'] = $np;
            } elseif (in_array($k, ['текст2', 'увед', 'msg2', 'notify_text'], true)) {
                $cfg['notify_text'] = $v;
            } elseif (in_array($k, ['ответ', 'reply', 'текст'], true)) {
                $cfg['reply'] = $v;
            } elseif (in_array($k, ['причина', 'reason'], true)) {
                $cfg['reason'] = $v;
            } elseif (in_array($k, ['удалить', 'del', 'delete_msg'], true)) {
                $cfg['delete_msg'] = in_array(mb_strtolower($v), ['да', 'yes', '1', 'true', '+'], true);
            } elseif (in_array($k, ['длявсех', 'allusers', 'наадминов'], true)) {
                $cfg['ignore_staff'] = !in_array(mb_strtolower($v), ['да', 'yes', '1', 'true', '+'], true);
            } elseif (in_array($k, ['режим', 'mode'], true)) {
                $cfg['mode'] = in_array(mb_strtolower($v), ['вхождение', 'contains', 'часть', '~'], true) ? 'contains' : 'exact';
            }
        }

        // По умолчанию: кик/снятьроль работают и на админов (для сценария ПСЖ), а мут/бан/пред/удалить — игнорируют админов 20+
        if ($cfg['ignore_staff'] === null) {
            $cfg['ignore_staff'] = in_array($cfg['action'], ['mute', 'ban', 'warn', 'del'], true);
        }

        if ($cfg['action'] === 'text' && $cfg['reply'] === '' && $cfg['notify_text'] === '') {
            sendMessage($peerId, "⚠️ Укажите действие (например «действие:кик») или «ответ:текст». Подробнее: !триггер помощь");
            return;
        }

        $dbKey = ($cfg['mode'] === 'contains' ? '~' : '') . $matchKey;
        $pdo->prepare("REPLACE INTO bot_triggers (peer_id, trigger_key, response_text) VALUES (?, ?, ?)")
            ->execute([$peerId, $dbKey, encodeTriggerConfig($cfg)]);
        cacheDel("trg_{$peerId}");

        $kb = makeInlineKeyboard([[
            ['label' => '🗑 Удалить триггер', 'payload' => ['cmd' => "!триггер -{$dbKey}"], 'color' => 'negative']
        ]]);
        $npStr = !empty($cfg['notify_peer']) ? ("#{$cfg['notify_peer']}") : 'Нет';
        sendMessage(
            $peerId,
            "⚡ Расширенный триггер «{$matchKey}» сохранён!\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "🔍 Режим: " . ($cfg['mode'] === 'contains' ? 'вхождение (~)' : 'точное совпадение') . "\n"
            . "🎯 Действие: " . triggerActionLabel($cfg) . "\n"
            . "💬 Беседы действия: " . triggerTargetPeersLabel($cfg['target_peers']) . "\n"
            . "📩 Уведомление во 2-й чат: {$npStr}\n"
            . "👮 Действует на админов: " . (empty($cfg['ignore_staff']) ? 'Да' : 'Нет'),
            $kb
        );
        return;
    }

    // 8. Обычный текстовый триггер: !триггер +слово = ответ (или ~слово = ответ)
    if (preg_match('/^(?:\+|add\s+)?([^=]+)=(.+)$/isu', $raw, $m)) {
        $keyPart = trim($m[1]);
        $isContains = str_starts_with($keyPart, '~');
        $cleanKey = mb_strtolower(mb_substr(ltrim($keyPart, '~ '), 0, 62));
        $resp = trim($m[2]);
        if ($cleanKey === '' || $resp === '') {
            sendMessage($peerId, "⚠️ Формат: !триггер +слово = текст ответа");
            return;
        }
        $dbKey = ($isContains ? '~' : '') . $cleanKey;
        $pdo->prepare("REPLACE INTO bot_triggers (peer_id, trigger_key, response_text) VALUES (?, ?, ?)")
            ->execute([$peerId, $dbKey, $resp]);
        cacheDel("trg_{$peerId}");
        sendMessage($peerId, "⚡ Триггер «{$cleanKey}» (" . ($isContains ? 'вхождение' : 'точно') . ") сохранён!");
        return;
    }

    sendMessage($peerId, "⚠️ Неверный формат. Введите «!триггер помощь» для примеров настройки ПСЖ и наказаний.");
}
