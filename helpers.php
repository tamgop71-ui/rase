<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$GLOBALS['_roles_cache']   = [];
$GLOBALS['_mention_cache'] = [];

function getDefaultRoles(): array {
    return [
        0   => 'Участник',
        20  => 'Помощник',
        40  => 'Модератор',
        60  => 'Администратор',
        80  => 'Главный администратор',
        100 => 'Владелец'
    ];
}

function getChatRoles(PDO $pdo, int $peerId, bool $fresh = false): array {
    if (!$fresh && isset($GLOBALS['_roles_cache'][$peerId])) {
        return $GLOBALS['_roles_cache'][$peerId];
    }
    if (!$fresh) {
        $cached = cacheGet("roles_{$peerId}", 60);
        if (is_array($cached)) {
            $roles = [];
            foreach ($cached as $k => $v) $roles[(int)$k] = $v;
            return $GLOBALS['_roles_cache'][$peerId] = $roles;
        }
    }
    $roles = getDefaultRoles();
    $stmt = $pdo->prepare("SELECT priority, role_name FROM bot_chat_roles WHERE peer_id = ?");
    $stmt->execute([$peerId]);
    while ($row = $stmt->fetch()) {
        $roles[(int)$row['priority']] = $row['role_name'];
    }
    ksort($roles);
    cacheSet("roles_{$peerId}", $roles);
    return $GLOBALS['_roles_cache'][$peerId] = $roles;
}

function getRoleName(PDO $pdo, int $peerId, int $priority): string {
    $roles = getChatRoles($pdo, $peerId);
    if (isset($roles[$priority])) return $roles[$priority];
    $best = 'Участник';
    foreach ($roles as $p => $name) {
        if ($priority >= $p) $best = $name;
    }
    return $best;
}

function preloadUsers(PDO $pdo, int $peerId, array $vkIds): void {
    $vkIds = array_values(array_unique(array_filter(array_map('intval', $vkIds), fn($id) => $id > 0)));
    if (empty($vkIds)) return;

    $missing = [];
    foreach ($vkIds as $id) {
        if (!isset($GLOBALS['_mention_cache']["{$peerId}:{$id}"])) {
            $missing[] = $id;
        }
    }
    if (empty($missing)) return;

    $placeholders = implode(',', array_fill(0, count($missing), '?'));
    $sql = "SELECT u.vk_id, u.first_name, u.last_name, m.nickname, m.emoji
            FROM bot_users u
            LEFT JOIN bot_chat_members m ON m.vk_id = u.vk_id AND m.peer_id = ?
            WHERE u.vk_id IN ({$placeholders})";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$peerId], $missing));

    while ($row = $stmt->fetch()) {
        $uid = (int)$row['vk_id'];
        $name = !empty($row['nickname'])
            ? $row['nickname']
            : trim(($row['first_name'] ?? 'Игрок') . ' ' . ($row['last_name'] ?? ''));
        if ($name === '') $name = "ID {$uid}";
        $emoji = !empty($row['emoji']) ? $row['emoji'] . ' ' : '';
        $GLOBALS['_mention_cache']["{$peerId}:{$uid}"] = "[id{$uid}|{$emoji}{$name}]";
    }
}

/**
 * Обновление реального названия беседы из VK API
 */
function refreshChatTitle(PDO $pdo, int $peerId): string {
    if ($peerId <= 2000000000) return "Личные сообщения";
    $res = vkApi('messages.getConversationsById', ['peer_ids' => $peerId]);
    $title = trim((string)($res['response']['items'][0]['chat_settings']['title'] ?? ''));
    if ($title !== '') {
        $title = mb_substr($title, 0, 120);
        $pdo->prepare("UPDATE bot_chats SET title = ? WHERE peer_id = ?")->execute([$title, $peerId]);
        return $title;
    }
    return "Беседа #{$peerId}";
}

/**
 * Быстрая загрузка контекста за 1 SELECT + 1 объединённый multi-table UPDATE
 */
function ensureUserAndMember(PDO $pdo, int $peerId, int $vkId, string $text = '', bool $hasSticker = false): array {
    $charLen = mb_strlen($text);
    $stickerInc = $hasSticker ? 1 : 0;
    $hasActivity = ($text !== '' || $hasSticker);
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT
            u.vk_id AS u_exists, u.first_name, u.last_name, u.balance, u.bank_balance,
            u.country_id, u.clan_id, u.spouse_vk_id, u.marriage_date, u.last_bonus, u.last_work, u.last_rob,
            u.rob_shield_until, u.daily_given_amount, u.daily_given_date, u.is_superadmin, u.is_bot_banned, u.is_vip, u.reputation,
            u.afk_reason, u.afk_since, u.daily_msgs, u.daily_games, u.daily_date, u.quest_claimed,
            u.total_messages, u.total_chars, u.total_stickers,
            c.peer_id AS c_exists, c.title, c.owner_vk_id, c.unity_id, c.rules_text, c.welcome_text,
            c.games_enabled, c.silence_until, c.silence_action, c.automod_enabled, c.antiflood_enabled,
            c.antidrain_enabled, c.antiraid_enabled, c.slowmode_sec, c.mentions_enabled,
            c.listen_target_vk_id, c.listen_until, c.total_messages AS chat_total_messages,
            m.vk_id AS m_exists, m.nickname, m.emoji, m.role_priority, m.gm_granted_by, m.gm_min_priority,
            m.mention_opt_in, m.messages_count, m.chars_count, m.stickers_count, m.joined_at, m.last_active_at,
            (SELECT 1 FROM bot_punishments p
             WHERE p.peer_id = q.req_peer AND p.target_vk_id = q.req_vk AND p.type = 'mute'
               AND p.is_active = 1 AND (p.expires_at IS NULL OR p.expires_at > NOW())
             LIMIT 1) AS is_muted,
            (SELECT p2.reason FROM bot_punishments p2
             WHERE p2.peer_id = 0 AND p2.target_vk_id = q.req_vk AND p2.type = 'gban'
               AND p2.is_active = 1 AND (p2.expires_at IS NULL OR p2.expires_at > NOW())
             LIMIT 1) AS sysban_reason
        FROM (SELECT ? AS req_vk, ? AS req_peer) q
        LEFT JOIN bot_users u ON u.vk_id = q.req_vk
        LEFT JOIN bot_chats c ON c.peer_id = q.req_peer
        LEFT JOIN bot_chat_members m ON m.peer_id = q.req_peer AND m.vk_id = q.req_vk
    ");
    $stmt->execute([$vkId, $peerId]);
    $row = $stmt->fetch() ?: [];

    $needRefetch = false;

    if (empty($row['u_exists'])) {
        $firstName = 'Игрок';
        $lastName  = (string)$vkId;
        $vkInfo = vkApi('users.get', ['user_ids' => $vkId]);
        if (!empty($vkInfo['response'][0])) {
            $firstName = $vkInfo['response'][0]['first_name'] ?? 'Игрок';
            $lastName  = $vkInfo['response'][0]['last_name'] ?? '';
        }
        $isSuper = in_array($vkId, array_filter(array_map('intval', explode(',', OWNER_VK_ID))), true) ? 1 : 0;
        $pdo->prepare("INSERT IGNORE INTO bot_users (vk_id, first_name, last_name, balance, is_superadmin, daily_msgs, daily_date, total_messages, total_chars, total_stickers) VALUES (?, ?, ?, 1500, ?, 1, ?, 1, ?, ?)")
            ->execute([$vkId, $firstName, $lastName, $isSuper, $today, $charLen, $stickerInc]);
        $needRefetch = true;
    }

    if (empty($row['c_exists'])) {
        $pdo->prepare("INSERT IGNORE INTO bot_chats (peer_id, title, owner_vk_id, total_messages) VALUES (?, ?, 0, 1)")
            ->execute([$peerId, "Беседа #{$peerId}"]);
        $needRefetch = true;
    }

    if (empty($row['m_exists'])) {
        $isSuperInit = (!empty($row['is_superadmin']) || in_array($vkId, array_filter(array_map('intval', explode(',', OWNER_VK_ID))), true)) ? 100 : 0;
        $pdo->prepare("INSERT IGNORE INTO bot_chat_members (peer_id, vk_id, role_priority, messages_count, chars_count, stickers_count) VALUES (?, ?, ?, 1, ?, ?)")
            ->execute([$peerId, $vkId, $isSuperInit, $charLen, $stickerInc]);
        $needRefetch = true;
    }

    if ($needRefetch) {
        $stmt->execute([$vkId, $peerId]);
        $row = $stmt->fetch() ?: [];
    } elseif ($hasActivity) {
        $sameDay = (($row['daily_date'] ?? '') === $today);
        if ($sameDay) {
            $pdo->prepare("
                UPDATE bot_users u
                JOIN bot_chats c ON c.peer_id = ?
                JOIN bot_chat_members m ON m.peer_id = c.peer_id AND m.vk_id = u.vk_id
                SET u.total_messages = u.total_messages + 1,
                    u.total_chars = u.total_chars + ?,
                    u.total_stickers = u.total_stickers + ?,
                    u.daily_msgs = u.daily_msgs + 1,
                    c.total_messages = c.total_messages + 1,
                    m.messages_count = m.messages_count + 1,
                    m.chars_count = m.chars_count + ?,
                    m.stickers_count = m.stickers_count + ?,
                    m.last_active_at = NOW()
                WHERE u.vk_id = ?
            ")->execute([$peerId, $charLen, $stickerInc, $charLen, $stickerInc, $vkId]);
        } else {
            $pdo->prepare("
                UPDATE bot_users u
                JOIN bot_chats c ON c.peer_id = ?
                JOIN bot_chat_members m ON m.peer_id = c.peer_id AND m.vk_id = u.vk_id
                SET u.total_messages = u.total_messages + 1,
                    u.total_chars = u.total_chars + ?,
                    u.total_stickers = u.total_stickers + ?,
                    u.daily_msgs = 1,
                    u.daily_games = 0,
                    u.daily_date = ?,
                    u.quest_claimed = 0,
                    c.total_messages = c.total_messages + 1,
                    m.messages_count = m.messages_count + 1,
                    m.chars_count = m.chars_count + ?,
                    m.stickers_count = m.stickers_count + ?,
                    m.last_active_at = NOW()
                WHERE u.vk_id = ?
            ")->execute([$peerId, $charLen, $stickerInc, $today, $charLen, $stickerInc, $vkId]);
            $row['daily_msgs'] = 0;
            $row['daily_games'] = 0;
            $row['daily_date'] = $today;
            $row['quest_claimed'] = 0;
        }
    }

    $isSuperFinal = ((int)($row['is_superadmin'] ?? 0) === 1) || hasSuperAccess($pdo, $vkId);

    $global = [
        'vk_id'              => $vkId,
        'first_name'         => $row['first_name'] ?? 'Игрок',
        'last_name'          => $row['last_name'] ?? '',
        'balance'            => (int)($row['balance'] ?? 1500),
        'bank_balance'       => (int)($row['bank_balance'] ?? 0),
        'country_id'         => $row['country_id'] ?? null,
        'clan_id'            => $row['clan_id'] ?? null,
        'spouse_vk_id'       => $row['spouse_vk_id'] ?? null,
        'marriage_date'      => $row['marriage_date'] ?? null,
        'last_bonus'         => $row['last_bonus'] ?? null,
        'last_work'          => $row['last_work'] ?? null,
        'last_rob'           => $row['last_rob'] ?? null,
        'rob_shield_until'   => $row['rob_shield_until'] ?? null,
        'daily_given_amount' => (int)($row['daily_given_amount'] ?? 0),
        'daily_given_date'   => $row['daily_given_date'] ?? null,
        'is_superadmin'      => $isSuperFinal ? 1 : 0,
        'is_bot_banned'      => (int)($row['is_bot_banned'] ?? 0),
        'is_vip'             => (int)($row['is_vip'] ?? 0),
        'reputation'         => (int)($row['reputation'] ?? 0),
        'afk_reason'         => $row['afk_reason'] ?? null,
        'afk_since'          => $row['afk_since'] ?? null,
        'daily_msgs'         => (int)($row['daily_msgs'] ?? 0) + ($hasActivity && !$needRefetch ? 1 : 0),
        'daily_games'        => (int)($row['daily_games'] ?? 0),
        'daily_date'         => $row['daily_date'] ?? $today,
        'quest_claimed'      => (int)($row['quest_claimed'] ?? 0),
        'total_messages'     => (int)($row['total_messages'] ?? 1) + ($hasActivity && !$needRefetch ? 1 : 0),
        'total_chars'        => (int)($row['total_chars'] ?? 0) + ($hasActivity && !$needRefetch ? $charLen : 0),
        'total_stickers'     => (int)($row['total_stickers'] ?? 0) + ($hasActivity && !$needRefetch ? $stickerInc : 0),
    ];

    $chat = [
        'peer_id'             => $peerId,
        'title'               => $row['title'] ?? "Беседа #{$peerId}",
        'owner_vk_id'         => (int)($row['owner_vk_id'] ?? 0),
        'unity_id'            => $row['unity_id'] ?? null,
        'rules_text'          => $row['rules_text'] ?? null,
        'welcome_text'        => $row['welcome_text'] ?? null,
        'games_enabled'       => (int)($row['games_enabled'] ?? 1),
        'silence_until'       => $row['silence_until'] ?? null,
        'silence_action'      => $row['silence_action'] ?? 'delete',
        'automod_enabled'     => (int)($row['automod_enabled'] ?? 0),
        'antiflood_enabled'   => (int)($row['antiflood_enabled'] ?? 0),
        'antidrain_enabled'   => (int)($row['antidrain_enabled'] ?? 0),
        'antiraid_enabled'    => (int)($row['antiraid_enabled'] ?? 0),
        'slowmode_sec'        => (int)($row['slowmode_sec'] ?? 0),
        'mentions_enabled'    => (int)($row['mentions_enabled'] ?? 1),
        'listen_target_vk_id' => $row['listen_target_vk_id'] ?? null,
        'listen_until'        => $row['listen_until'] ?? null,
        'total_messages'      => (int)($row['chat_total_messages'] ?? 1),
    ];

    $member = [
        'peer_id'         => $peerId,
        'vk_id'           => $vkId,
        'nickname'        => $row['nickname'] ?? null,
        'emoji'           => $row['emoji'] ?? null,
        'role_priority'   => (int)($row['role_priority'] ?? ($isSuperFinal ? 100 : 0)),
        'gm_granted_by'   => $row['gm_granted_by'] ?? null,
        'gm_min_priority' => (int)($row['gm_min_priority'] ?? 0),
        'mention_opt_in'  => (int)($row['mention_opt_in'] ?? 1),
        'messages_count'  => (int)($row['messages_count'] ?? 1) + ($hasActivity && !$needRefetch ? 1 : 0),
        'chars_count'     => (int)($row['chars_count'] ?? 0) + ($hasActivity && !$needRefetch ? $charLen : 0),
        'stickers_count'  => (int)($row['stickers_count'] ?? 0) + ($hasActivity && !$needRefetch ? $stickerInc : 0),
        'joined_at'       => $row['joined_at'] ?? date('Y-m-d H:i:s'),
        'last_active_at'  => $row['last_active_at'] ?? date('Y-m-d H:i:s'),
        'is_muted'        => !empty($row['is_muted']),
        'sysban_reason'   => ($row['sysban_reason'] !== null && $row['sysban_reason'] !== '') ? (string)$row['sysban_reason'] : null,
    ];

    $disp = !empty($member['nickname']) ? $member['nickname'] : trim($global['first_name'] . ' ' . $global['last_name']);
    if ($disp === '') $disp = "ID {$vkId}";
    $em = !empty($member['emoji']) ? $member['emoji'] . ' ' : '';
    $GLOBALS['_mention_cache']["{$peerId}:{$vkId}"] = "[id{$vkId}|{$em}{$disp}]";

    return ['global' => $global, 'chat' => $chat, 'member' => $member];
}

function formatUserMention(PDO $pdo, int $peerId, int $vkId): string {
    $key = "{$peerId}:{$vkId}";
    if (isset($GLOBALS['_mention_cache'][$key])) return $GLOBALS['_mention_cache'][$key];

    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, m.nickname, m.emoji
                           FROM bot_users u
                           LEFT JOIN bot_chat_members m ON m.vk_id = u.vk_id AND m.peer_id = ?
                           WHERE u.vk_id = ?");
    $stmt->execute([$peerId, $vkId]);
    $row = $stmt->fetch();

    if (!$row) {
        ensureUserAndMember($pdo, $peerId, $vkId);
        return $GLOBALS['_mention_cache'][$key] ?? "[id{$vkId}|ID {$vkId}]";
    }

    $displayName = !empty($row['nickname'])
        ? $row['nickname']
        : trim(($row['first_name'] ?? 'Игрок') . ' ' . ($row['last_name'] ?? ''));
    if ($displayName === '') $displayName = "ID {$vkId}";
    $emoji = !empty($row['emoji']) ? $row['emoji'] . ' ' : '';
    return $GLOBALS['_mention_cache'][$key] = "[id{$vkId}|{$emoji}{$displayName}]";
}

function parseAmount(string $raw, int $userBalance = 0): ?int {
    $raw = mb_strtolower(trim($raw));
    if ($raw === '') return null;
    if (in_array($raw, ['все', 'всё', 'вабанк', 'all', 'max'], true)) return max(0, $userBalance);
    $raw = str_replace([',', ' '], ['.', ''], $raw);
    if (preg_match('/^(\d+(?:\.\d+)?)(к+|k+|м|m)?$/iu', $raw, $m)) {
        $num = (float)$m[1];
        $suffix = $m[2] ?? '';
        $mult = 1;
        if ($suffix !== '') {
            $mult = ($suffix === 'м' || $suffix === 'm') ? 1000000 : (int)pow(1000, mb_strlen($suffix));
        }
        return (int)round($num * $mult);
    }
    return null;
}

function formatMoney(int $amount): string {
    return number_format($amount, 0, '.', ' ') . ' ₽';
}

function parseReminderTime(string $token): ?int {
    $token = mb_strtolower(trim($token));
    if ($token === '') return null;

    if (preg_match('/^(\d{1,2}):(\d{2})$/', $token, $m)) {
        $h = (int)$m[1]; $min = (int)$m[2];
        if ($h > 23 || $min > 59) return null;
        $ts = mktime($h, $min, 0);
        if ($ts <= time()) $ts += 86400;
        return $ts;
    }

    if (preg_match_all('/(\d+)\s*(д|дн|d|ч|час|h|м|мин|m|с|сек|s)/iu', $token, $matches, PREG_SET_ORDER)) {
        $totalSec = 0;
        $matchedLen = 0;
        foreach ($matches as $m) {
            $val = (int)$m[1];
            $u = mb_strtolower($m[2]);
            $matchedLen += mb_strlen($m[0]);
            if (str_starts_with($u, 'д') || $u === 'd') $totalSec += $val * 86400;
            elseif (str_starts_with($u, 'ч') || $u === 'h') $totalSec += $val * 3600;
            elseif (str_starts_with($u, 'м') || $u === 'm') $totalSec += $val * 60;
            elseif (str_starts_with($u, 'с') || $u === 's') $totalSec += $val;
        }
        if ($totalSec >= 10 && $totalSec <= 30 * 86400 && $matchedLen >= mb_strlen(str_replace(' ', '', $token))) {
            return time() + $totalSec;
        }
    }
    return null;
}

/**
 * Кэшируемый резолвер короткого имени ВК (экономит 150 мс VK API при повторных командах)
 */
function resolveVkScreenName(string $screenName): ?int {
    $screenName = mb_strtolower(trim($screenName));
    if (preg_match('/^id(\d+)$/i', $screenName, $idm)) {
        return (int)$idm[1];
    }
    $cached = cacheGet("sn_{$screenName}", 86400);
    if (is_int($cached) && $cached > 0) return $cached;
    $res = vkApi('utils.resolveScreenName', ['screen_name' => $screenName]);
    if (!empty($res['response']['object_id']) && ($res['response']['type'] ?? '') === 'user') {
        $uid = (int)$res['response']['object_id'];
        cacheSet("sn_{$screenName}", $uid);
        return $uid;
    }
    return null;
}

function extractTargetUser(PDO $pdo, int $peerId, array $msg, string &$args): ?int {
    if (!empty($msg['reply_message']['from_id']) && (int)$msg['reply_message']['from_id'] > 0) {
        return (int)$msg['reply_message']['from_id'];
    }
    if (!empty($msg['fwd_messages'][0]['from_id']) && (int)$msg['fwd_messages'][0]['from_id'] > 0) {
        return (int)$msg['fwd_messages'][0]['from_id'];
    }
    $args = trim($args);
    if ($args === '') return null;

    if (preg_match('/^\[id(\d+)\|[^\]]+\](?:\s+(.*))?$/isu', $args, $m)) {
        $args = trim($m[2] ?? '');
        return (int)$m[1];
    }
    if (preg_match('/^(?:https?:\/\/)?(?:m\.)?vk\.(?:com|ru)\/([a-zA-Z0-9_\.]+)(?:\s+(.*))?$/isu', $args, $m)) {
        $uid = resolveVkScreenName($m[1]);
        if ($uid) { $args = trim($m[2] ?? ''); return $uid; }
    }
    if (preg_match('/^@([a-zA-Z0-9_\.]+)(?:\s+(.*))?$/isu', $args, $m)) {
        $uid = resolveVkScreenName($m[1]);
        if ($uid) { $args = trim($m[2] ?? ''); return $uid; }
    }
    if (preg_match('/^(\d{3,12})(?:\s+(.*))?$/isu', $args, $m)) {
        $args = trim($m[2] ?? '');
        return (int)$m[1];
    }
    return null;
}

function canPunish(PDO $pdo, int $peerId, int $actorId, int $actorPriority, int $targetId, bool $isSuper): ?string {
    if ($actorId === $targetId) return "⛔ Вы не можете применить это действие к самому себе.";
    if ($isSuper) return null;
    if (hasSuperAccess($pdo, $targetId)) return "⛔ Вы не можете применить наказание к пользователю с супер-доступом.";

    $stmt = $pdo->prepare("SELECT role_priority, gm_granted_by, gm_min_priority FROM bot_chat_members WHERE peer_id = ? AND vk_id = ?");
    $stmt->execute([$peerId, $targetId]);
    $targetMem = $stmt->fetch();

    if ((int)($targetMem['role_priority'] ?? 0) >= $actorPriority) {
        return "⛔ Вы не можете применить наказание к пользователю, роль которого выше или равна вашей.";
    }
    $gmMin = (int)($targetMem['gm_min_priority'] ?? 0);
    if (!empty($targetMem['gm_granted_by']) && $actorPriority < $gmMin) {
        $reqRole = getRoleName($pdo, $peerId, $gmMin);
        return "🛡 У пользователя активен иммунитет (/gm). Необходима роль не ниже: {$reqRole} ({$gmMin}).";
    }
    return null;
}

function getSystemSetting(PDO $pdo, string $key, string $default = ''): string {
    $cached = cacheGet("sys_{$key}", 60);
    if ($cached !== null) return (string)$cached;
    $stmt = $pdo->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    $res = ($val !== false && $val !== null) ? (string)$val : $default;
    cacheSet("sys_{$key}", $res);
    return $res;
}

function setSystemSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("REPLACE INTO bot_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $value]);
    cacheSet("sys_{$key}", $value);
}

function sendToLogChat(PDO $pdo, string $logMessage): void {
    $logPeerId = (int)getSystemSetting($pdo, 'log_peer_id', '0');
    if ($logPeerId > 0) {
        sendMessage($logPeerId, "📋 [ЛОГ БОТА]\n" . $logMessage, null, ['disable_mentions' => 1]);
    }
}

function getUnityPeers(PDO $pdo, int $peerId): array {
    $stmt = $pdo->prepare("SELECT unity_id FROM bot_chats WHERE peer_id = ?");
    $stmt->execute([$peerId]);
    $unityId = (int)$stmt->fetchColumn();
    if ($unityId <= 0) return [$peerId];
    $stmtPeers = $pdo->prepare("SELECT peer_id FROM bot_chats WHERE unity_id = ?");
    $stmtPeers->execute([$unityId]);
    $peers = $stmtPeers->fetchAll(PDO::FETCH_COLUMN);
    return !empty($peers) ? array_map('intval', $peers) : [$peerId];
}

function getRequiredPriority(PDO $pdo, int $peerId, string $canonicalCmd, int $defaultPriority): int {
    $cached = cacheGet("cmdp_{$peerId}", 60);
    if (!is_array($cached)) {
        $stmt = $pdo->prepare("SELECT cmd_name, min_priority FROM bot_cmd_permissions WHERE peer_id = ?");
        $stmt->execute([$peerId]);
        $cached = [];
        while ($r = $stmt->fetch()) $cached[$r['cmd_name']] = (int)$r['min_priority'];
        cacheSet("cmdp_{$peerId}", $cached);
    }
    return $cached[$canonicalCmd] ?? $defaultPriority;
}

function getChatWhitelist(PDO $pdo, int $peerId): array {
    $cached = cacheGet("wl_{$peerId}", 60);
    if (is_array($cached)) return $cached;
    $stmt = $pdo->prepare("SELECT domain FROM bot_whitelist WHERE peer_id IN (0, ?) ORDER BY peer_id ASC, domain ASC");
    $stmt->execute([$peerId]);
    $list = array_values(array_unique(array_map('mb_strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    cacheSet("wl_{$peerId}", $list);
    return $list;
}

function findBlockedLink(PDO $pdo, int $peerId, string $text): ?string {
    $pattern = '/(?:https?:\/\/|www\.)[^\s<>"\']+|(?:vk\.cc|t\.me|telegram\.me|discord\.gg|discord\.com\/invite|vk\.(?:com|ru)\/(?:club|public|join)|[a-z0-9\-]+\.(?:ru|com|net|org|online|site|xyz|su|fun|gg|me|io|tv|cc))(?:\/[^\s<>"\']*)?/iu';
    if (!preg_match_all($pattern, $text, $matches)) {
        return null;
    }
    $whitelist = getChatWhitelist($pdo, $peerId);
    foreach ($matches[0] as $foundUrl) {
        $norm = mb_strtolower(preg_replace('/^(?:https?:\/\/)?(?:www\.)?/i', '', trim($foundUrl, ".,!?;:()[]")));
        $allowed = false;
        foreach ($whitelist as $w) {
            $wNorm = mb_strtolower(preg_replace('/^(?:https?:\/\/)?(?:www\.)?/i', '', trim($w)));
            if ($wNorm !== '' && (str_starts_with($norm, $wNorm) || str_contains($norm, $wNorm))) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return $foundUrl;
        }
    }
    return null;
}

function getChatFilters(PDO $pdo, int $peerId): array {
    $cached = cacheGet("flt_{$peerId}", 60);
    if (is_array($cached)) return $cached;
    $stmt = $pdo->prepare("SELECT word FROM bot_filters WHERE peer_id = ?");
    $stmt->execute([$peerId]);
    $list = array_map('mb_strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
    cacheSet("flt_{$peerId}", $list);
    return $list;
}

function getChatTriggers(PDO $pdo, int $peerId): array {
    $cached = cacheGet("trg_{$peerId}", 60);
    if (is_array($cached)) return $cached;
    $stmt = $pdo->prepare("SELECT trigger_key, response_text FROM bot_triggers WHERE peer_id = ?");
    $stmt->execute([$peerId]);
    $map = [];
    while ($r = $stmt->fetch()) {
        $map[mb_strtolower($r['trigger_key'])] = $r['response_text'];
    }
    cacheSet("trg_{$peerId}", $map);
    return $map;
}

/**
 * Сверхбыстрый локальный буфер последних сообщений для !clear (0 SQL-запросов!)
 */
function recordRecentMessage(int $peerId, int $cmid, int $fromId): void {
    $file = "/tmp/msgs_{$peerId}.json";
    $list = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw) $list = json_decode($raw, true) ?: [];
    }
    $list[] = ['c' => $cmid, 'u' => $fromId];
    if (count($list) > 120) {
        $list = array_slice($list, -120);
    }
    @file_put_contents($file, json_encode($list), LOCK_EX);
}

function popRecentMessages(int $peerId, ?int $targetId, int $limit): array {
    $file = "/tmp/msgs_{$peerId}.json";
    if (!is_file($file)) return [];
    $list = json_decode((string)@file_get_contents($file), true) ?: [];
    $matched = [];
    $remaining = [];
    for ($i = count($list) - 1; $i >= 0; $i--) {
        $item = $list[$i];
        if (count($matched) < $limit && ($targetId === null || (int)$item['u'] === $targetId)) {
            $matched[] = (int)$item['c'];
        } else {
            $remaining[] = $item;
        }
    }
    @file_put_contents($file, json_encode(array_reverse($remaining)), LOCK_EX);
    return $matched;
}

/**
 * Локальный индекс AFK-пользователей в /tmp (убирает SQL-запрос при каждом упоминании)
 */
function setAfkCache(int $vkId, ?array $info): void {
    $file = '/tmp/afk_index.json';
    $data = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
    if ($info === null) {
        unset($data[(string)$vkId]);
    } else {
        $data[(string)$vkId] = $info;
    }
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function getAfkFromCache(int $vkId): ?array {
    $file = '/tmp/afk_index.json';
    if (!is_file($file)) return null;
    $data = json_decode((string)@file_get_contents($file), true);
    return $data[(string)$vkId] ?? null;
}

/**
 * Анти-флуд (5 сообщений за 4 сек)
 */
function checkAntiFlood(int $peerId, int $vkId): bool {
    $file = "/tmp/af_{$peerId}_{$vkId}.txt";
    $now = microtime(true);
    $times = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw) {
            foreach (explode(',', $raw) as $t) {
                $tf = (float)$t;
                if (($now - $tf) <= 4.0) $times[] = $tf;
            }
        }
    }
    $times[] = $now;
    @file_put_contents($file, implode(',', $times));
    return count($times) >= 5;
}

/**
 * Медленный режим (!слоумод) через /tmp (0 SQL-запросов)
 */
function checkSlowmode(int $peerId, int $vkId, int $seconds): bool {
    if ($seconds <= 0) return false;
    $file = "/tmp/sm_{$peerId}_{$vkId}.txt";
    $now = time();
    if (is_file($file)) {
        $last = (int)@file_get_contents($file);
        if (($now - $last) < $seconds) {
            return true;
        }
    }
    @file_put_contents($file, (string)$now);
    return false;
}

/**
 * Анти-слив (!антислив): 4+ жёстких действий админа (<100) за 2 минуты -> снятие роли + кик
 */
function checkAntiDrain(PDO $pdo, int $peerId, int $actorId, int $actorPriority, bool $isSuper, array $chat): bool {
    if ($isSuper || $actorPriority >= 100 || (int)($chat['antidrain_enabled'] ?? 0) === 0) {
        return false;
    }
    $file = "/tmp/ad_{$peerId}_{$actorId}.txt";
    $now = time();
    $times = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw) {
            foreach (explode(',', $raw) as $t) {
                $ti = (int)$t;
                if (($now - $ti) <= 120) $times[] = $ti;
            }
        }
    }
    $times[] = $now;
    @file_put_contents($file, implode(',', $times));

    if (count($times) >= 4) {
        $pdo->prepare("UPDATE bot_chat_members SET role_priority = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $actorId]);
        if ($peerId > 2000000000) {
            vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $actorId]);
        }
        $uActor = formatUserMention($pdo, $peerId, $actorId);
        $alert = "🚨 [АНТИ-СЛИВ] Обнаружена подозрительная активность администратора {$uActor} (4+ киков/банов/снятий ролей за 2 минуты)!\n🛡 Роль немедленно снята, нарушитель исключён из беседы.";
        sendMessage($peerId, $alert);
        sendToLogChat($pdo, "Беседа {$peerId}:\n" . $alert);
        @unlink($file);
        return true;
    }
    return false;
}
