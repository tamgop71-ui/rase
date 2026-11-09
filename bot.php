<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Защита от повторной отправки одного и того же события от ВК
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

// Проверка секретного ключа Callback API
if (!empty(VK_SECRET_KEY) && ($data['secret'] ?? '') !== VK_SECRET_KEY) {
    exit('Invalid Secret Key');
}

// Подтверждение сервера
if ($data['type'] === 'confirmation') {
    exit(VK_CONFIRMATION_TOKEN);
}

// Обработка нового сообщения
if ($data['type'] === 'message_new') {
    $msg    = $data['object']['message'] ?? [];
    $peerId = (int)($msg['peer_id'] ?? 0);
    $fromId = (int)($msg['from_id'] ?? 0);
    $text   = trim((string)($msg['text'] ?? ''));

    // Игнорируем сообщения от сообществ
    if ($fromId <= 0 || $text === '') {
        exit('ok');
    }

    $pdo = getDB();

    // Получаем или создаем профиль пользователя в БД
    $user = getOrCreateUser($pdo, $fromId);

    // Если пользователь забанен в боте — игнорируем команды
    if ((int)$user['is_banned'] === 1) {
        exit('ok');
    }

    // Разбор команды (префиксы: /, !)
    if (preg_match('/^[\/!]([\wа-яё]+)(?:\s+(.*))?$/iu', $text, $matches)) {
        $command = mb_strtolower($matches[1]);
        $args    = trim($matches[2] ?? '');

        handleCommand($pdo, $command, $args, $peerId, $fromId, $user, $msg);
    }

    exit('ok');
}

exit('ok');

/**
 * Регистрация / обновление счетчика сообщений пользователя
 */
function getOrCreateUser(PDO $pdo, int $vkId): array {
    $stmt = $pdo->prepare("SELECT * FROM bot_users WHERE vk_id = ?");
    $stmt->execute([$vkId]);
    $user = $stmt->fetch();

    if (!$user) {
        $pdo->prepare("INSERT INTO bot_users (vk_id, nickname, role_level, messages_count) VALUES (?, 'Не указан', 0, 1)")
            ->execute([$vkId]);
        return [
            'vk_id'          => $vkId,
            'nickname'       => 'Не указан',
            'role_level'     => 0,
            'warns'          => 0,
            'messages_count' => 1,
            'is_banned'      => 0
        ];
    }

    $pdo->prepare("UPDATE bot_users SET messages_count = messages_count + 1, updated_at = NOW() WHERE vk_id = ?")
        ->execute([$vkId]);
    $user['messages_count']++;
    return $user;
}

/**
 * Извлечение целевого VK ID из ответа (reply) или упоминания [id123|Name]
 */
function extractTargetVkId(array $msg, string &$args): ?int {
    if (isset($msg['reply_message']['from_id']) && $msg['reply_message']['from_id'] > 0) {
        return (int)$msg['reply_message']['from_id'];
    }
    if (preg_match('/^\[id(\d+)\|[^\]]+\]\s*(.*)$/iu', $args, $m)) {
        $args = trim($m[2]);
        return (int)$m[1];
    }
    if (preg_match('/^(\d+)\s*(.*)$/u', $args, $m)) {
        $args = trim($m[2]);
        return (int)$m[1];
    }
    return null;
}

/**
 * Логирование действий администрации
 */
function logAction(PDO $pdo, int $actorId, ?int $targetId, string $action, string $details = ''): void {
    $stmt = $pdo->prepare("INSERT INTO bot_logs (actor_vk_id, target_vk_id, action, details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$actorId, $targetId, $action, $details]);
}

/**
 * Роутер команд
 */
function handleCommand(PDO $pdo, string $cmd, string $args, int $peerId, int $fromId, array $user, array $msg): void {
    $role = (int)$user['role_level'];
    $roleNames = [
        0 => 'Игрок',
        1 => 'Модератор',
        2 => 'Администратор',
        3 => 'Руководство'
    ];

    switch ($cmd) {
        case 'help':
        case 'помощь':
        case 'команды':
            $help = "📋 Доступные команды бота RAZE RUSSIA:\n\n"
                  . "👤 Общие команды:\n"
                  . "• /profile (или /stats) — ваш профиль или профиль по ответу\n"
                  . "• /staff — список администрации в боте\n"
                  . "• /id — узнать ID чата и ваш VK ID\n\n";
            if ($role >= 1) {
                $help .= "🛡 Модерация (Уровень 1+):\n"
                       . "• /setnick [юзер] [Ник] — установить игровой ник\n"
                       . "• /warn [юзер] — выдать предупреждение (3/3 = кик)\n"
                       . "• /unwarn [юзер] — снять предупреждение\n"
                       . "• /kick [юзер] — исключить из беседы\n\n";
            }
            if ($role >= 2) {
                $help .= "⚡ Администрирование (Уровень 2+):\n"
                       . "• /ban [юзер] — заблокировать доступ к боту\n"
                       . "• /unban [юзер] — разблокировать в боте\n\n";
            }
            if ($role >= 3) {
                $help .= "👑 Руководство (Уровень 3):\n"
                       . "• /setrole [юзер] [0-3] — изменить уровень доступа\n";
            }
            sendMessage($peerId, $help);
            break;

        case 'id':
            sendMessage($peerId, "🆔 Ваш VK ID: {$fromId}\n💬 Peer ID чата: {$peerId}");
            break;

        case 'profile':
        case 'stats':
        case 'профиль':
            $targetId = extractTargetVkId($msg, $args) ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_users WHERE vk_id = ?");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();

            if (!$target) {
                sendMessage($peerId, "❌ Пользователь еще не зарегистрирован в базе бота.");
                return;
            }

            $rName = $roleNames[(int)$target['role_level']] ?? 'Неизвестно';
            $text = "📊 Профиль [id{$target['vk_id']}|пользователя]:\n"
                  . "🎮 Никнейм: {$target['nickname']}\n"
                  . "🛡 Должность: {$rName} (Ур. {$target['role_level']})\n"
                  . "⚠️ Предупреждения: {$target['warns']}/3\n"
                  . "✉️ Сообщений: {$target['messages_count']}";
            sendMessage($peerId, $text);
            break;

        case 'staff':
        case 'стафф':
            $stmt = $pdo->query("SELECT vk_id, nickname, role_level FROM bot_users WHERE role_level > 0 ORDER BY role_level DESC");
            $staff = $stmt->fetchAll();
            if (!$staff) {
                sendMessage($peerId, "Список администрации пуст.");
                return;
            }
            $out = "🛡 Состав администрации RAZE RUSSIA:\n\n";
            foreach ($staff as $s) {
                $rName = $roleNames[(int)$s['role_level']] ?? 'Staff';
                $out .= "• [id{$s['vk_id']}|{$s['nickname']}] — {$rName}\n";
            }
            sendMessage($peerId, $out);
            break;

        case 'setnick':
        case 'ник':
            if ($role < 1) {
                sendMessage($peerId, "⛔ У вас нет прав для этой команды.");
                return;
            }
            $targetId = extractTargetVkId($msg, $args);
            $newNick  = trim($args);
            if (!$targetId || $newNick === '') {
                sendMessage($peerId, "⚠️ Использование: /setnick @упоминание Имя_Фамилия (или ответом на сообщение).");
                return;
            }
            getOrCreateUser($pdo, $targetId);
            $pdo->prepare("UPDATE bot_users SET nickname = ? WHERE vk_id = ?")->execute([$newNick, $targetId]);
            logAction($pdo, $fromId, $targetId, 'setnick', $newNick);
            sendMessage($peerId, "✅ Пользователю [id{$targetId}|ID {$targetId}] установлен никнейм: {$newNick}");
            break;

        case 'warn':
        case 'варн':
            if ($role < 1) {
                sendMessage($peerId, "⛔ Недостаточно прав.");
                return;
            }
            $targetId = extractTargetVkId($msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя (ответом или упоминанием).");
                return;
            }
            $target = getOrCreateUser($pdo, $targetId);
            if ((int)$target['role_level'] >= $role) {
                sendMessage($peerId, "⛔ Нельзя выдать варн равному или старшему по должности.");
                return;
            }
            $newWarns = (int)$target['warns'] + 1;
            if ($newWarns >= 3) {
                $pdo->prepare("UPDATE bot_users SET warns = 0 WHERE vk_id = ?")->execute([$targetId]);
                sendMessage($peerId, "🚫 Пользователь [id{$targetId}|{$target['nickname']}] получил 3/3 предупреждений и исключается из беседы.");
                if ($peerId > 2000000000) {
                    vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
                }
            } else {
                $pdo->prepare("UPDATE bot_users SET warns = ? WHERE vk_id = ?")->execute([$newWarns, $targetId]);
                sendMessage($peerId, "⚠️ Пользователю [id{$targetId}|{$target['nickname']}] выдано предупреждение ({$newWarns}/3).");
            }
            logAction($pdo, $fromId, $targetId, 'warn', "Warns: {$newWarns}/3");
            break;

        case 'unwarn':
        case 'снятварн':
            if ($role < 1) {
                sendMessage($peerId, "⛔ Недостаточно прав.");
                return;
            }
            $targetId = extractTargetVkId($msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя.");
                return;
            }
            $target = getOrCreateUser($pdo, $targetId);
            $newWarns = max(0, (int)$target['warns'] - 1);
            $pdo->prepare("UPDATE bot_users SET warns = ? WHERE vk_id = ?")->execute([$newWarns, $targetId]);
            logAction($pdo, $fromId, $targetId, 'unwarn', "Warns: {$newWarns}/3");
            sendMessage($peerId, "✅ С пользователя [id{$targetId}|{$target['nickname']}] снято предупреждение ({$newWarns}/3).");
            break;

        case 'kick':
        case 'кик':
            if ($role < 1) {
                sendMessage($peerId, "⛔ Недостаточно прав.");
                return;
            }
            if ($peerId <= 2000000000) {
                sendMessage($peerId, "⚠️ Команда работает только в беседах.");
                return;
            }
            $targetId = extractTargetVkId($msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя для кика.");
                return;
            }
            $target = getOrCreateUser($pdo, $targetId);
            if ((int)$target['role_level'] >= $role) {
                sendMessage($peerId, "⛔ Вы не можете кикнуть этого пользователя.");
                return;
            }
            vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
            logAction($pdo, $fromId, $targetId, 'kick', $args);
            sendMessage($peerId, "👢 Пользователь [id{$targetId}|{$target['nickname']}] исключен из беседы.");
            break;

        case 'ban':
        case 'бан':
            if ($role < 2) {
                sendMessage($peerId, "⛔ Требуется уровень доступа 2+ (Администратор).");
                return;
            }
            $targetId = extractTargetVkId($msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя.");
                return;
            }
            $target = getOrCreateUser($pdo, $targetId);
            if ((int)$target['role_level'] >= $role) {
                sendMessage($peerId, "⛔ Нельзя заблокировать старшего или равного администратора.");
                return;
            }
            $pdo->prepare("UPDATE bot_users SET is_banned = 1 WHERE vk_id = ?")->execute([$targetId]);
            logAction($pdo, $fromId, $targetId, 'ban', $args);
            sendMessage($peerId, "🔨 Пользователю [id{$targetId}|{$target['nickname']}] заблокирован доступ к боту.");
            break;

        case 'unban':
        case 'разбан':
            if ($role < 2) {
                sendMessage($peerId, "⛔ Требуется уровень доступа 2+.");
                return;
            }
            $targetId = extractTargetVkId($msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя.");
                return;
            }
            $pdo->prepare("UPDATE bot_users SET is_banned = 0 WHERE vk_id = ?")->execute([$targetId]);
            logAction($pdo, $fromId, $targetId, 'unban');
            sendMessage($peerId, "✅ Пользователь [id{$targetId}|ID {$targetId}] разблокирован в боте.");
            break;

        case 'setrole':
        case 'роль':
            if ($role < 3) {
                sendMessage($peerId, "⛔ Доступно только Руководству (Уровень 3).");
                return;
            }
            $targetId = extractTargetVkId($msg, $args);
            $newLevel = (int)trim($args);
            if (!$targetId || $newLevel < 0 || $newLevel > 3) {
                sendMessage($peerId, "⚠️ Использование: /setrole @упоминание [0-3]\n0 - Игрок, 1 - Модератор, 2 - Админ, 3 - Руководство");
                return;
            }
            getOrCreateUser($pdo, $targetId);
            $pdo->prepare("UPDATE bot_users SET role_level = ? WHERE vk_id = ?")->execute([$newLevel, $targetId]);
            logAction($pdo, $fromId, $targetId, 'setrole', (string)$newLevel);
            $rName = $roleNames[$newLevel];
            sendMessage($peerId, "👑 Пользователю [id{$targetId}|ID {$targetId}] выдана роль: {$rName} (Уровень {$newLevel}).");
            break;
    }
}
