<?php
declare(strict_types=1);

// Настройки времени и кодировки
date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

// Конфигурация VK и внешней БД через переменные окружения (Environment Variables)
define('VK_GROUP_TOKEN', getenv('VK_GROUP_TOKEN') ?: 'ВАШ_ТОКЕН_ГРУППЫ');
define('VK_CONFIRMATION_TOKEN', getenv('VK_CONFIRMATION_TOKEN') ?: 'СТРОКА_ПОДТВЕРЖДЕНИЯ');
define('VK_SECRET_KEY', getenv('VK_SECRET_KEY') ?: 'СЕКРЕТНЫЙ_КЛЮЧ');
define('VK_API_VERSION', '5.199');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'raze_bot');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

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
            PDO::ATTR_TIMEOUT            => 5, // Таймаут на случай задержек внешнего хоста БД
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            exit('ok'); // Возвращаем ok для ВК, чтобы не спамил повторами при падении БД
        }
    }
    return $pdo;
}

/**
 * Универсальный вызов методов VK API
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
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode((string)$response, true) ?? [];
}

/**
 * Отправка сообщения в чат/ЛС
 */
function sendMessage(int $peerId, string $message, ?array $keyboard = null): void {
    $params = [
        'peer_id'          => $peerId,
        'message'          => $message,
        'random_id'        => random_int(1, PHP_INT_MAX),
        'disable_mentions' => 1
    ];
    if ($keyboard !== null) {
        $params['keyboard'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }
    vkApi('messages.send', $params);
}
