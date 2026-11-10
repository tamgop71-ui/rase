<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/commands.php';

if (isset($_SERVER['HTTP_X_RETRY_COUNTER'])) exit('ok');
$rawInput = file_get_contents('php://input');
if (!$rawInput) exit('Access Denied');
$data = json_decode($rawInput, true);
if (!isset($data['type'])) exit('Invalid JSON');
if (VK_SECRET_KEY !== '' && ($data['secret'] ?? '') !== VK_SECRET_KEY) exit('Invalid Secret Key');
if ($data['type'] === 'confirmation') exit(VK_CONFIRMATION_TOKEN);

if ($data['type'] === 'message_new') {
    $msg = $data['object']['message'] ?? [];
    $peerId = (int)($msg['peer_id'] ?? 0);
    $fromId = (int)($msg['from_id'] ?? 0);
    $text = trim((string)($msg['text'] ?? ''));

    if ($fromId <= 0 || $peerId <= 0) exit('ok');
    $pdo = getDB();
    
    // Регистрация юзера/чата
    $ctx = ensureUserAndMember($pdo, $peerId, $fromId, $text);

    // Авто-роль 100 для владельца беседы и 60 для админов со звездой при входе бота
    if (!empty($msg['action']['type']) && $msg['action']['type'] === 'chat_invite_user' && $msg['action']['member_id'] < 0) {
        $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId]);
        foreach (($res['response']['items'] ?? []) as $item) {
            $mid = (int)$item['member_id'];
            if ($mid > 0) {
                ensureUserAndMember($pdo, $peerId, $mid);
                if (!empty($item['is_owner'])) {
                    $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ?")->execute([$mid, $peerId]);
                    $pdo->prepare("UPDATE bot_chat_members SET role_priority = 100 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $mid]);
                } elseif (!empty($item['is_admin'])) {
                    $pdo->prepare("UPDATE bot_chat_members SET role_priority = 60 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $mid]);
                }
            }
        }
        sendMessage($peerId, "👋 Привет! Я успешно инициализирован. Владельцу беседы выдана роль 100, админам ВК — 60.\nВведите !помощь для списка команд.");
        exit('ok');
    }

    if (!empty($msg['payload'])) {
        $payload = json_decode((string)$msg['payload'], true);
        if (!empty($payload['cmd'])) $text = (string)$payload['cmd'];
    }

    // Поддержка префиксов: ! / + -
    if (preg_match('/^[!\/\+\-]([\wа-яё]+)(?:\s+(.*))?$/isu', $text, $matches)) {
        executeCommand($pdo, mb_strtolower($matches[1]), trim($matches[2] ?? ''), $peerId, $fromId, $ctx, $msg);
    }
    exit('ok');
}
exit('ok');
