<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

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

function getChatRoles(PDO $pdo, int $peerId): array {
    $roles = getDefaultRoles();
    $stmt = $pdo->prepare("SELECT priority, role_name FROM bot_chat_roles WHERE peer_id = ?");
    $stmt->execute([$peerId]);
    while ($row = $stmt->fetch()) {
        $roles[(int)$row['priority']] = $row['role_name'];
    }
    ksort($roles);
    return $roles;
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

function ensureUserAndMember(PDO $pdo, int $peerId, int $vkId, string $text = '', bool $hasSticker = false): array {
    $stmt = $pdo->prepare("SELECT * FROM bot_users WHERE vk_id = ?");
    $stmt->execute([$vkId]);
    $globalUser = $stmt->fetch();
    $charLen = mb_strlen($text);
    $stickerInc = $hasSticker ? 1 : 0;

    if (!$globalUser) {
        $firstName = 'Игрок';
        $lastName  = (string)$vkId;
        $vkInfo = vkApi('users.get', ['user_ids' => $vkId]);
        if (!empty($vkInfo['response'][0])) {
            $firstName = $vkInfo['response'][0]['first_name'] ?? 'Игрок';
            $lastName  = $vkInfo['response'][0]['last_name'] ?? '';
        }
        $isSuper = in_array($vkId, array_filter(array_map('intval', explode(',', OWNER_VK_ID))), true) ? 1 : 0;

        $pdo->prepare("INSERT IGNORE INTO bot_users (vk_id, first_name, last_name, balance, is_superadmin, total_messages, total_chars, total_stickers) VALUES (?, ?, ?, 1500, ?, 1, ?, ?)")
            ->execute([$vkId, $firstName, $lastName, $isSuper, $charLen, $stickerInc]);
        $stmt->execute([$vkId]);
        $globalUser = $stmt->fetch();
    } else if ($text !== '' || $hasSticker) {
        $pdo->prepare("UPDATE bot_users SET total_messages = total_messages + 1, total_chars = total_chars + ?, total_stickers = total_stickers + ?, updated_at = NOW() WHERE vk_id = ?")
            ->execute([$charLen, $stickerInc, $vkId]);
    }

    $stmtChat = $pdo->prepare("SELECT * FROM bot_chats WHERE peer_id = ?");
    $stmtChat->execute([$peerId]);
    $chat = $stmtChat->fetch();

    if (!$chat) {
        $pdo->prepare("INSERT IGNORE INTO bot_chats (peer_id, title, owner_vk_id, total_messages) VALUES (?, ?, ?, 1)")
            ->execute([$peerId, "Беседа #{$peerId}", 0]);
        $stmtChat->execute([$peerId]);
        $chat = $stmtChat->fetch();
    } else if ($text !== '' || $hasSticker) {
        $pdo->prepare("UPDATE bot_chats SET total_messages = total_messages + 1 WHERE peer_id = ?")
            ->execute([$peerId]);
    }

    $stmtMem = $pdo->prepare("SELECT * FROM bot_chat_members WHERE peer_id = ? AND vk_id = ?");
    $stmtMem->execute([$peerId, $vkId]);
    $member = $stmtMem->fetch();

    if (!$member) {
        $initPriority = ((int)$globalUser['is_superadmin'] === 1) ? 100 : 0;
        $pdo->prepare("INSERT IGNORE INTO bot_chat_members (peer_id, vk_id, role_priority, messages_count, chars_count, stickers_count) VALUES (?, ?, ?, 1, ?, ?)")
            ->execute([$peerId, $vkId, $initPriority, $charLen, $stickerInc]);
        $stmtMem->execute([$peerId, $vkId]);
        $member = $stmtMem->fetch();
    } else if ($text !== '' || $hasSticker) {
        $pdo->prepare("UPDATE bot_chat_members SET messages_count = messages_count + 1, chars_count = chars_count + ?, stickers_count = stickers_count + ?, last_active_at = NOW() WHERE peer_id = ? AND vk_id = ?")
            ->execute([$charLen, $stickerInc, $peerId, $vkId]);
    }
    if (hasSuperAccess($pdo, $vkId)) {
        $member['role_priority'] = 100;
        $globalUser['is_superadmin'] = 1;
    }
    return ['global' => $globalUser, 'chat' => $chat, 'member' => $member];
}

function formatUserMention(PDO $pdo, int $peerId, int $vkId): string {
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, m.nickname, m.emoji FROM bot_users u LEFT JOIN bot_chat_members m ON m.vk_id = u.vk_id AND m.peer_id = ? WHERE u.vk_id = ?");
    $stmt->execute([$peerId, $vkId]);
    $row = $stmt->fetch();
    if (!$row) {
        ensureUserAndMember($pdo, $peerId, $vkId);
        $stmt->execute([$peerId, $vkId]);
        $row = $stmt->fetch();
    }
    $displayName = !empty($row['nickname']) ? $row['nickname'] : trim(($row['first_name'] ?? 'Игрок') . ' ' . ($row['last_name'] ?? ''));
    if ($displayName === '') $displayName = "ID {$vkId}";
    $emoji = !empty($row['emoji']) ? $row['emoji'] . ' ' : '';
    return "[id{$vkId}|{$emoji}{$displayName}]";
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
            if ($suffix === 'м' || $suffix === 'm') $mult = 1000000;
            else {
                $kCount = mb_strlen($suffix);
                $mult = (int)pow(1000, $kCount);
            }
        }
        return (int)round($num * $mult);
    }
    return null;
}

function formatMoney(int $amount): string {
    return number_format($amount, 0, '.', ' ') . ' ₽';
}

function extractTargetUser(PDO $pdo, int $peerId, array $msg, string &$args): ?int {
    if (!empty($msg['reply_message']['from_id']) && (int)$msg['reply_message']['from_id'] > 0) return (int)$msg['reply_message']['from_id'];
    if (!empty($msg['fwd_messages'][0]['from_id']) && (int)$msg['fwd_messages'][0]['from_id'] > 0) return (int)$msg['fwd_messages'][0]['from_id'];
    $args = trim($args);
    if ($args === '') return null;
    if (preg_match('/^\[id(\d+)\|[^\]]+\](?:\s+(.*))?$/isu', $args, $m)) {
        $args = trim($m[2] ?? '');
        return (int)$m[1];
    }
    if (preg_match('/^(?:https?:\/\/)?(?:m\.)?vk\.com\/([a-zA-Z0-9_\.]+)(?:\s+(.*))?$/isu', $args, $m)) {
        $screenName = $m[1];
        $args = trim($m[2] ?? '');
        if (preg_match('/^id(\d+)$/i', $screenName, $idm)) return (int)$idm[1];
        $res = vkApi('utils.resolveScreenName', ['screen_name' => $screenName]);
        if (!empty($res['response']['object_id']) && ($res['response']['type'] ?? '') === 'user') return (int)$res['response']['object_id'];
    }
    if (preg_match('/^(\d+)(?:\s+(.*))?$/isu', $args, $m)) {
        $args = trim($m[2] ?? '');
        return (int)$m[1];
    }
    if (preg_match('/^@([a-zA-Z0-9_\.]+)(?:\s+(.*))?$/isu', $args, $m)) {
        $screenName = $m[1];
        $args = trim($m[2] ?? '');
        $res = vkApi('utils.resolveScreenName', ['screen_name' => $screenName]);
        if (!empty($res['response']['object_id']) && ($res['response']['type'] ?? '') === 'user') return (int)$res['response']['object_id'];
    }
    return null;
}

function canPunish(PDO $pdo, int $peerId, int $actorId, int $actorPriority, int $targetId, bool $isSuper): ?string {
    if ($actorId === $targetId) return "Вы не можете применить наказание к самому себе.";
    if ($isSuper) return null;
    if (hasSuperAccess($pdo, $targetId)) return "Вы не можете применить наказание к пользователю с супер-доступом.";
    $stmt = $pdo->prepare("SELECT role_priority, gm_granted_by, gm_min_priority FROM bot_chat_members WHERE peer_id = ? AND vk_id = ?");
    $stmt->execute([$peerId, $targetId]);
    $targetMem = $stmt->fetch();
    $targetPriority = (int)($targetMem['role_priority'] ?? 0);
    if ($targetPriority >= $actorPriority) return "Вы не можете применить наказание к пользователю, роль которого выше или равна вашей.";
    $gmMin = (int)($targetMem['gm_min_priority'] ?? 0);
    if (!empty($targetMem['gm_granted_by']) && $actorPriority < $gmMin) {
        $reqRole = getRoleName($pdo, $peerId, $gmMin);
        return "У пользователя активен иммунитет (/gm). Необходима роль не ниже: {$reqRole} ({$gmMin}).";
    }
    return null;
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
    $stmt = $pdo->prepare("SELECT min_priority FROM bot_cmd_permissions WHERE peer_id = ? AND cmd_name = ?");
    $stmt->execute([$peerId, $canonicalCmd]);
    $custom = $stmt->fetchColumn();
    return ($custom !== false) ? (int)$custom : $defaultPriority;
}
