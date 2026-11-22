<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

// Конфигурация VK и внешней БД через переменные окружения
define('VK_GROUP_TOKEN', trim((string)(getenv('VK_GROUP_TOKEN') ?: '')));
define('VK_CONFIRMATION_TOKEN', trim((string)(getenv('VK_CONFIRMATION_TOKEN') ?: '')));
define('VK_SECRET_KEY', trim((string)(getenv('VK_SECRET_KEY') ?: '')));
define('VK_API_VERSION', '5.199');
define('OWNER_VK_ID', trim((string)(getenv('OWNER_VK_ID') ?: '0')));

// Принудительный TCP, если указан localhost
$rawHost = trim((string)(getenv('DB_HOST') ?: '127.0.0.1'));
if (strtolower($rawHost) === 'localhost' || $rawHost === '') {
    $rawHost = '127.0.0.1';
}
define('DB_HOST', $rawHost);
define('DB_PORT', trim((string)(getenv('DB_PORT') ?: '3306')));
define('DB_NAME', trim((string)(getenv('DB_NAME') ?: 'rase_bot')));
define('DB_USER', trim((string)(getenv('DB_USER') ?: 'root')));
define('DB_PASS', trim((string)(getenv('DB_PASS') ?: '')));

/**
 * Постоянное PDO-подключение к удалённой БД с авто-переподключением
 */
function getDB(bool $forceReconnect = false): PDO {
    static $pdo = null;
    static $lastPing = 0;

    if ($pdo !== null && !$forceReconnect) {
        $now = time();
        if (($now - $lastPing) >= 45) {
            try {
                $pdo->query('SELECT 1');
                $lastPing = $now;
            } catch (Throwable $e) {
                $pdo = null;
            }
        }
        if ($pdo !== null) {
            return $pdo;
        }
    }

    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4", DB_HOST, DB_PORT, DB_NAME);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 4,
        PDO::ATTR_PERSISTENT         => true,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $lastPing = time();
        return $pdo;
    } catch (PDOException $e) {
        file_put_contents('php://stderr', "[DB ERROR] " . $e->getMessage() . PHP_EOL);
        if (PHP_SAPI === 'cli') {
            throw $e;
        }
        exit('ok');
    }
}

/**
 * Быстрый локальный кэш в /tmp (экономит сетевые запросы к удалённой БД)
 */
function cacheGet(string $key, int $ttl = 60) {
    $file = '/tmp/botc_' . md5($key) . '.json';
    if (!is_file($file)) return null;
    if ($ttl > 0 && (time() - (int)@filemtime($file)) > $ttl) {
        @unlink($file);
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    return json_decode($raw, true);
}

function cacheSet(string $key, $value): void {
    $file = '/tmp/botc_' . md5($key) . '.json';
    @file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function cacheDel(string $key): void {
    $file = '/tmp/botc_' . md5($key) . '.json';
    if (is_file($file)) @unlink($file);
}

/**
 * Быстрый вызов VK API (HTTP/2 + Keep-Alive + IPv4)
 */
function vkApi(string $method, array $params = []): array {
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_TCP_KEEPALIVE  => 1,
            CURLOPT_FORBID_REUSE   => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            CURLOPT_ENCODING       => '',
        ]);
    }

    $params['access_token'] = VK_GROUP_TOKEN;
    $params['v'] = VK_API_VERSION;

    curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/' . $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

    $res = curl_exec($ch);
    if ($res === false) {
        file_put_contents('php://stderr', "[CURL ERROR] {$method}: " . curl_error($ch) . PHP_EOL);
        curl_close($ch);
        $ch = null;
        return [];
    }

    $decoded = json_decode((string)$res, true) ?? [];
    if (isset($decoded['error'])) {
        $code = $decoded['error']['error_code'] ?? 0;
        $msg  = $decoded['error']['error_msg'] ?? '';
        file_put_contents('php://stderr', "[VK API ERROR {$code}] {$method}: {$msg}" . PHP_EOL);
    }
    return $decoded;
}


/**
 * Параллельный вызов VK API через curl_multi (до 10 запросов одновременно).
 * Ускоряет массовые операции (/sysban, /syskick, /broadcast, /zovvv) в 5-8 раз.
 */
function vkApiMulti(array $calls, int $concurrency = 10): array {
    if (empty($calls)) return [];
    $results = [];
    $chunks = array_chunk($calls, max(1, $concurrency), true);

    foreach ($chunks as $chunk) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($chunk as $idx => $call) {
            $method = (string)($call['method'] ?? '');
            $params = (array)($call['params'] ?? []);
            $params['access_token'] = VK_GROUP_TOKEN;
            $params['v'] = VK_API_VERSION;

            $ch = curl_init('https://api.vk.com/method/' . $method);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
                CURLOPT_ENCODING       => '',
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$idx] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.2);
            }
        } while ($running > 0 && $status === CURLM_OK);

        foreach ($handles as $idx => $ch) {
            $raw = curl_multi_getcontent($ch);
            $results[$idx] = json_decode((string)$raw, true) ?? [];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return $results;
}

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

function deleteMessage(int $peerId, array $cmids): void {
    $cmids = array_values(array_unique(array_filter(array_map('intval', $cmids))));
    if (empty($cmids)) return;
    vkApi('messages.delete', [
        'peer_id'        => $peerId,
        'cmids'          => implode(',', $cmids),
        'delete_for_all' => 1
    ]);
}

function makeInlineKeyboard(array $rows): array {
    $buttons = [];
    foreach ($rows as $row) {
        $btnRow = [];
        foreach ($row as $btn) {
            $btnRow[] = [
                'action' => [
                    'type'    => 'text',
                    'label'   => mb_substr((string)$btn['label'], 0, 40),
                    'payload' => json_encode($btn['payload'] ?? ['cmd' => $btn['cmd'] ?? ''], JSON_UNESCAPED_UNICODE)
                ],
                'color' => $btn['color'] ?? 'secondary'
            ];
        }
        if (!empty($btnRow)) {
            $buttons[] = $btnRow;
        }
    }
    return ['inline' => true, 'buttons' => $buttons];
}

function hasSuperAccess(PDO $pdo, int $vkId): bool {
    static $superCache = [];
    if (isset($superCache[$vkId])) return $superCache[$vkId];

    $owners = array_filter(array_map('intval', explode(',', OWNER_VK_ID)));
    if (in_array($vkId, $owners, true)) {
        return $superCache[$vkId] = true;
    }
    $stmt = $pdo->prepare("SELECT is_superadmin FROM bot_users WHERE vk_id = ?");
    $stmt->execute([$vkId]);
    return $superCache[$vkId] = ((int)$stmt->fetchColumn() === 1);
}
