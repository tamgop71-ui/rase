<?php
declare(strict_types=1);

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

// Проверка секретного ключа, если он задан
if (VK_SECRET_KEY !== '' && ($data['secret'] ?? '') !== VK_SECRET_KEY) {
    exit('Invalid Secret Key');
}

// 1. Подтверждение адреса сервера Callback API
if ($data['type'] === 'confirmation') {
    exit(VK_CONFIRMATION_TOKEN);
}

// 2. Обработка входящего сообщения / события беседы
if ($data['type'] === 'message_new') {
    $msg    = $data['object']['message'] ?? [];
    $peerId = (int)($msg['peer_id'] ?? 0);
    $fromId = (int)($msg['from_id'] ?? 0);
    $text   = trim((string)($msg['text'] ?? ''));
    $cmid   = (int)($msg['conversation_message_id'] ?? 0);

    // Игнорируем сообщения от других сообществ
    if ($fromId <= 0 || $peerId <= 0) {
        exit('ok');
    }

    $pdo = getDB();

    // Проверка на вложения (стикеры)
    $hasSticker = false;
    if (!empty($msg['attachments'])) {
        foreach ($msg['attachments'] as $att) {
            if (($att['type'] ?? '') === 'sticker') {
                $hasSticker = true;
                break;
            }
        }
    }

    // Регистрируем / обновляем профиль игрока и участника беседы
    $ctx = ensureUserAndMember($pdo, $peerId, $fromId, $text, $hasSticker);
    $isSuper = ((int)$ctx['global']['is_superadmin'] === 1) || hasSuperAccess($pdo, $fromId);
    $myPriority = $isSuper ? 100 : (int)$ctx['member']['role_priority'];

    // 2.1. Обработка системных событий беседы (Вход нового участника — приветствие и проверка бана)
    if (!empty($msg['action']['type'])) {
        $actType = $msg['action']['type'];
        $actMemberId = (int)($msg['action']['member_id'] ?? 0);

        if (($actType === 'chat_invite_user' || $actType === 'chat_invite_user_by_link') && $actMemberId > 0) {
            ensureUserAndMember($pdo, $peerId, $actMemberId);

            // Проверяем, нет ли у вошедшего активного бана в этой беседе или глобального бана
            $stmtBan = $pdo->prepare("SELECT * FROM bot_punishments WHERE (peer_id = ? OR peer_id = 0) AND target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            $stmtBan->execute([$peerId, $actMemberId]);
            $activeBan = $stmtBan->fetch();

            if ($activeBan && $peerId > 2000000000) {
                $uBanned = formatUserMention($pdo, $peerId, $actMemberId);
                sendMessage($peerId, "🚫 Пользователь {$uBanned} находится в списке заблокированных (Причина: {$activeBan['reason']}) и исключается из беседы.");
                vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $actMemberId]);
                exit('ok');
            }

            // Приветствие нового участника (!welcome)
            $welcome = trim((string)($ctx['chat']['welcome_text'] ?? ''));
            if ($welcome !== '') {
                $uNew = formatUserMention($pdo, $peerId, $actMemberId);
                sendMessage($peerId, "👋 {$uNew}, " . $welcome);
            }
        }
        exit('ok');
    }

    // 2.2. Прослушка беседы (!listen): пересылка сообщения в ЛС супер-админу
    if (!empty($ctx['chat']['listen_target_vk_id']) && !empty($ctx['chat']['listen_until'])) {
        if (strtotime($ctx['chat']['listen_until']) > time()) {
            $listenerId = (int)$ctx['chat']['listen_target_vk_id'];
            if ($listenerId > 0 && $listenerId !== $fromId) {
                $uSender = formatUserMention($pdo, $peerId, $fromId);
                sendMessage($listenerId, "🎧 [Прослушка чата {$peerId}]\n👤 {$uSender}: {$text}");
            }
        }
    }

    // 2.3. Проверка активного мута (!мут) и Режима тишины (!тишина)
    if ($peerId > 2000000000 && !$isSuper) {
        // Проверка режима тишины (писать могут только пользователи с ролью > 0)
        $silenceUntil = $ctx['chat']['silence_until'] ?? null;
        if ($silenceUntil && strtotime($silenceUntil) > time() && $myPriority <= 0) {
            if ($cmid > 0) deleteMessage($peerId, [$cmid]);
            exit('ok');
        }

        // Проверка индивидуального мута
        $stmtMute = $pdo->prepare("SELECT id FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
        $stmtMute->execute([$peerId, $fromId]);
        if ($stmtMute->fetchColumn()) {
            if ($cmid > 0) deleteMessage($peerId, [$cmid]);
            exit('ok');
        }

        // 2.4. Автомодерация рекламы и ссылок (!automod) для участников без роли
        if ((int)($ctx['chat']['automod_enabled'] ?? 0) === 1 && $myPriority <= 0) {
            if (preg_match('/(https?:\/\/|vk\.cc\/|t\.me\/|discord\.gg\/|vk\.com\/(?:club|public|join))/iu', $text)) {
                if ($cmid > 0) deleteMessage($peerId, [$cmid]);
                $uWarn = formatUserMention($pdo, $peerId, $fromId);
                sendMessage($peerId, "🤖 {$uWarn}, ссылки и реклама запрещены автомодерацией беседы!");
                exit('ok');
            }
        }
    }

    // 2.5. Проверка нажатия на инлайн-кнопку (payload)
    if (!empty($msg['payload'])) {
        $payload = json_decode((string)$msg['payload'], true);
        if (!empty($payload['cmd']) && is_string($payload['cmd'])) {
            $text = $payload['cmd'];
        }
    }

    // 2.6. Разбор команды с префиксами: ! / . (на русском и английском)
    if (preg_match('/^[!\/\.]([\wа-яё]+)(?:\s+(.*))?$/isu', $text, $matches)) {
        $rawCommand = mb_strtolower($matches[1]);
        $cmdArgs    = trim($matches[2] ?? '');

        executeCommand($pdo, $rawCommand, $cmdArgs, $peerId, $fromId, $ctx, $msg);
    }

    // 2.7. Фоновая проверка автоуведомлений (!timeuved и !gtimeuvedg) с вероятностью 20%, чтобы не нагружать каждый запрос
    if (random_int(1, 5) === 1) {
        processTimers($pdo);
    }

    exit('ok');
}

exit('ok');

/**
 * Фоновая отправка автоуведомлений (!timeuved и !gtimeuvedg)
 */
function processTimers(PDO $pdo): void {
    // 1. Деактивация истёкших мутов и банов
    $pdo->query("UPDATE bot_punishments SET is_active = 0 WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at <= NOW()");

    // 2. Проверка таймеров уведомлений
    $stmt = $pdo->query("SELECT * FROM bot_timers WHERE is_active = 1");
    $timers = $stmt->fetchAll();
    $now = time();
    $curHM = date('H:i');

    foreach ($timers as $t) {
        $tId = (int)$t['id'];
        $tPeer = (int)$t['peer_id'];
        $lastSent = !empty($t['last_sent_at']) ? strtotime($t['last_sent_at']) : 0;
        $shouldSend = false;

        if (!empty($t['daily_time'])) {
            if ($t['daily_time'] === $curHM && ($now - $lastSent) >= 3600) {
                $shouldSend = true;
            }
        } else {
            $intervalSec = max(1, (int)$t['interval_hours']) * 3600;
            if (($now - $lastSent) >= $intervalSec) {
                $shouldSend = true;
            }
        }

        if ($shouldSend) {
            $pdo->prepare("UPDATE bot_timers SET last_sent_at = NOW() WHERE id = ?")->execute([$tId]);
            if ($tPeer > 0) {
                sendMessage($tPeer, "⏰ Напоминание:\n" . $t['message_text']);
            } else {
                $allChats = $pdo->query("SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($allChats as $cp) {
                    sendMessage((int)$cp, "📢 Автоуведомление:\n" . $t['message_text']);
                }
            }
        }
    }
}
