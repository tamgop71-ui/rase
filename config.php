<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

// Конфигурация VK и внешней БД через переменные окружения
define('VK_GROUP_TOKEN', trim((string)(getenv('VK_GROUP_TOKEN') ?: '')));
define('VK_CONFIRMATION_TOKEN', trim((string)(getenv('VK_CONFIRMATION_TOKEN') ?: '')));
define('VK_SECRET_KEY', trim((string)(getenv('VK_SECRET_KEY') ?: '')));
define('VK_API_VERSION', '5.199');

// Корневой создатель бота (через запятую можно указать несколько VK ID в переменной OWNER_VK_ID)
define('OWNER_VK_ID', trim((string)(getenv('OWNER_VK_ID') ?: '0')));

// Параметры БД (принудительный TCP при указании localhost)
$rawHost = trim((string)(getenv('DB_HOST') ?: '127.0.0.1'));
if (strtolower($rawHost) === 'localhost' || $rawHost === '') {
    $rawHost = '127.0.0.1';
}
define('DB_HOST', $rawHost);
define('DB_PORT', trim((string)(getenv('DB_PORT') ?: '3306')));
define('DB_NAME', trim((string)(getenv('DB_NAME') ?: 'raze_bot')));
define('DB_USER', trim((string)(getenv('DB_USER') ?: 'root')));
define('DB_PASS', trim((string)(getenv('DB_PASS') ?: '')));

/**
 * Подключение к внешней БД через PDO
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4", DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            file_put_contents('php://stderr', "[DB ERROR] " . $e->getMessage() . PHP_EOL);
            exit('ok');
        }
    }
    return $pdo;
}

/**
 * Универсальный вызов VK API
 */
function vkApi(string $method, array $params = []): array {
    $params['access_token'] = VK_GROUP_TOKEN;
    $params['v'] = VK_API_VERSION;

    $ch = curl_init('https://api.vk.com/method/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode((string)$res, true) ?? [];
    if (isset($decoded['error'])) {
        file_put_contents('php://stderr', "[VK API ERROR] {$method}: " . json_encode($decoded['error'], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }
    return $decoded;
}

/**
 * Отправка сообщения с поддержкой инлайн-клавиатур
 */
function sendMessage(int $peerId, string $message, ?array $keyboard = null, array $extra = []): array {
    $params = array_merge([
        'peer_id'          => $peerId,
        'message'          => $message,
        'random_id'        => random_int(1, PHP_INT_MAX),
        'disable_mentions' => 0
    ], $extra);

    if ($keyboard !== null) {
        $params['keyboard'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }
    return vkApi('messages.send', $params);
}

/**
 * Удаление сообщения в беседе
 */
function deleteMessage(int $peerId, array $cmids): void {
    if (empty($cmids)) return;
    vkApi('messages.delete', [
        'peer_id'      => $peerId,
        'cmids'        => implode(',', $cmids),
        'delete_for_all' => 1
    ]);
}

/**
 * Построение inline-клавиатуры
 */
function makeInlineKeyboard(array $rows): array {
    $buttons = [];
    foreach ($rows as $row) {
        $btnRow = [];
        foreach ($row as $btn) {
            $btnRow[] = [
                'action' => [
                    'type'    => 'text',
                    'label'   => mb_substr($btn['label'], 0, 40),
                    'payload' => json_encode($btn['payload'] ?? ['cmd' => $btn['cmd'] ?? ''], JSON_UNESCAPED_UNICODE)
                ],
                'color' => $btn['color'] ?? 'secondary'
            ];
        }
        $buttons[] = $btnRow;
    }
    return ['inline' => true, 'buttons' => $buttons];
}

/**
 * Проверка супер-доступа (/addws или создатель из конфига)
 */
function hasSuperAccess(PDO $pdo, int $vkId): bool {
    $owners = array_filter(array_map('intval', explode(',', OWNER_VK_ID)));
    if (in_array($vkId, $owners, true)) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT is_superadmin FROM bot_users WHERE vk_id = ?");
    $stmt->execute([$vkId]);
    return (int)$stmt->fetchColumn() === 1;
}
