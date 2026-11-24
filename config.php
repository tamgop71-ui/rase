<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

// Защита от зависания воркера: ни один HTTP-запрос не может висеть дольше 15 секунд
if (PHP_SAPI !== 'cli') {
    @set_time_limit(15);
}

// Конфигурация VK и внешней БД через переменные окружения
define('VK_GROUP_TOKEN', trim((string)(getenv('VK_GROUP_TOKEN') ?: '')));
define('VK_CONFIRMATION_TOKEN', trim((string)(getenv('VK_CONFIRMATION_TOKEN') ?: '')));
define('VK_SECRET_KEY', trim((string)(getenv('VK_SECRET_KEY') ?: '')));
define('VK_API_VERSION', '5.199');
define('MAIN_OWNER_VK_ID', (int)(getenv('MAIN_OWNER_VK_ID') ?: 831772755));
define('OWNER_VK_ID', trim((string)(getenv('OWNER_VK_ID') ?: '831772755')));

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
 * Стабильное подключение к удалённой БД без зависших persistent-сокетов.
 * - Не держит мёртвые соединения в пуле (главная причина "бот перестал отвечать").
 * - Автоматически переподключается при обрыве MySQL (2006/2013) с 2 попытками.
 */
function getDB(bool $forceReconnect = false): PDO {
    static $pdo = null;
    static $createdAt = 0;

    if ($forceReconnect) {
        $pdo = null;
    }

    // В рамках одного запроса или короткого тика переиспользуем открытый PDO;
    // в CLI-демоне (worker.php) перепроверяем соединение, если прошло > 20 сек.
    if ($pdo !== null) {
        if (PHP_SAPI === 'cli' && (time() - $createdAt) >= 20) {
            try {
                $pdo->query('SELECT 1');
                $createdAt = time();
                return $pdo;
            } catch (Throwable $e) {
                $pdo = null;
            }
        } else {
            return $pdo;
        }
    }

    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4", DB_HOST, DB_PORT, DB_NAME);
    // ATTR_PERSISTENT => false + mysqlnd.net_read_timeout=5 полностью исключают зависание на мёртвом TCP-сокете
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 3,
        PDO::ATTR_PERSISTENT         => false,
    ];

    $lastErr = null;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $createdAt = time();
            return $pdo;
        } catch (PDOException $e) {
            $lastErr = $e;
            usleep(120000);
        }
    }

    file_put_contents('php://stderr', "[DB ERROR] " . ($lastErr ? $lastErr->getMessage() : 'Connection failed') . PHP_EOL);
    if (PHP_SAPI === 'cli') {
        throw $lastErr ?? new RuntimeException('DB connection failed');
    }
    exit('ok');
}

/**
 * Проверка, является ли исключение обрывом соединения с MySQL
 */
function isDbGoneAway(Throwable $e): bool {
    $msg = strtolower($e->getMessage());
    return str_contains($msg, 'gone away')
        || str_contains($msg, 'lost connection')
        || str_contains($msg, 'error while sending')
        || str_contains($msg, '2006')
        || str_contains($msg, '2013');
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
    $tmp  = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode($value, JSON_UNESCAPED_UNICODE)) !== false) {
        @rename($tmp, $file);
    }
}

function cacheDel(string $key): void {
    $file = '/tmp/botc_' . md5($key) . '.json';
    if (is_file($file)) @unlink($file);
}

/**
 * Очистка протухших временных файлов в /tmp (вызывается из worker.php)
 */

/**
 * Полная очистка локального кэша в /tmp (команда /clearcache)
 */
function cacheClearAll(): int {
    $removed = 0;
    $patterns = ['/tmp/botc_*.json', '/tmp/af_*.txt', '/tmp/sm_*.txt', '/tmp/ad_*.txt', '/tmp/st_*.txt', '/tmp/st_*.txt', '/tmp/ai_cd_*.txt', '/tmp/q_cd_*.txt', '/tmp/quote_*.jpg', '/tmp/aigen_*.jpg', '/tmp/aigen_cd_*.txt', '/tmp/dem_*.jpg', '/tmp/rep_*.txt', '/tmp/db_hb_*.txt'];
    foreach ($patterns as $pat) {
        $list = glob($pat);
        if (!$list) continue;
        foreach ($list as $f) {
            if (@unlink($f)) $removed++;
        }
    }
    return $removed;
}

function cleanupTmpCache(): void {
    $now = time();
    $patterns = ['/tmp/botc_*.json', '/tmp/af_*.txt', '/tmp/sm_*.txt', '/tmp/ad_*.txt', '/tmp/st_*.txt', '/tmp/ai_cd_*.txt', '/tmp/q_cd_*.txt', '/tmp/quote_*.jpg', '/tmp/aigen_*.jpg', '/tmp/aigen_cd_*.txt', '/tmp/dem_*.jpg', '/tmp/rep_*.txt', '/tmp/db_hb_*.txt'];
    foreach ($patterns as $pat) {
        $list = glob($pat);
        if (!$list) continue;
        foreach ($list as $f) {
            if (($now - (int)@filemtime($f)) > 3600) {
                @unlink($f);
            }
        }
    }
}

/**
 * Быстрый и отказоустойчивый вызов VK API (HTTP/2 + IPv4 + TCP_NODELAY + авто-повтор при обрыве сокета)
 */
function vkApi(string $method, array $params = []): array {
    static $ch = null;
    static $lastUsed = 0;

    $params['access_token'] = VK_GROUP_TOKEN;
    $params['v'] = VK_API_VERSION;
    $url = 'https://api.vk.com/method/' . $method;
    $postFields = http_build_query($params);

    // Сбрасываем старый Keep-Alive хендл, если он простаивал дольше 45 сек
    if ($ch !== null && (time() - $lastUsed) > 45) {
        curl_close($ch);
        $ch = null;
    }

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        if ($ch === null) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_POST              => true,
                CURLOPT_RETURNTRANSFER    => true,
                CURLOPT_CONNECTTIMEOUT    => 2,
                CURLOPT_TIMEOUT           => 5,
                CURLOPT_NOSIGNAL          => 1,
                CURLOPT_LOW_SPEED_LIMIT   => 1,
                CURLOPT_LOW_SPEED_TIME    => 4,
                CURLOPT_TCP_NODELAY       => 1,
                CURLOPT_DNS_CACHE_TIMEOUT => 3600,
                CURLOPT_SSL_SESSIONID_CACHE => 1,
                CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
                CURLOPT_TCP_KEEPALIVE     => 1,
                CURLOPT_FORBID_REUSE      => false,
                CURLOPT_HTTP_VERSION      => CURL_HTTP_VERSION_2_0,
                CURLOPT_ENCODING          => '',
            ]);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        $res = curl_exec($ch);
        $lastUsed = time();

        if ($res !== false) {
            $decoded = json_decode((string)$res, true) ?? [];
            if (isset($decoded['error'])) {
                $code = $decoded['error']['error_code'] ?? 0;
                $msg  = $decoded['error']['error_msg'] ?? '';
                // При временном лимите VK API (code 6 / 10) делаем короткую паузу и 1 повтор
                if (($code === 6 || $code === 10) && $attempt === 1) {
                    usleep(250000);
                    continue;
                }
                file_put_contents('php://stderr', "[VK API ERROR {$code}] {$method}: {$msg}" . PHP_EOL);
            }
            return $decoded;
        }

        $err = curl_error($ch);
        curl_close($ch);
        $ch = null;
        if ($attempt === 2) {
            file_put_contents('php://stderr', "[CURL ERROR] {$method}: {$err}" . PHP_EOL);
        }
    }

    return [];
}

/**
 * Параллельный вызов VK API через curl_multi (до 10 запросов одновременно)
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
                CURLOPT_POST              => true,
                CURLOPT_POSTFIELDS        => http_build_query($params),
                CURLOPT_RETURNTRANSFER    => true,
                CURLOPT_CONNECTTIMEOUT    => 2,
                CURLOPT_TIMEOUT           => 5,
                CURLOPT_NOSIGNAL          => 1,
                CURLOPT_LOW_SPEED_LIMIT   => 1,
                CURLOPT_LOW_SPEED_TIME    => 4,
                CURLOPT_TCP_NODELAY       => 1,
                CURLOPT_DNS_CACHE_TIMEOUT => 3600,
                CURLOPT_SSL_SESSIONID_CACHE => 1,
                CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
                CURLOPT_HTTP_VERSION      => CURL_HTTP_VERSION_2_0,
                CURLOPT_ENCODING          => '',
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


/**
 * Отправка длинного сообщения с умной разбивкой на 2, 3 и более частей (лимит ВК — 4096 символов).
 * Режет аккуратно по абзацам, переносам строк или пробелам, чтобы не рвать слова.
 */
function sendLongMessage(int $peerId, string $message, ?array $keyboard = null, array $extra = [], int $chunkSize = 3500): void {
    $message = trim($message);
    if ($message === '') return;

    if (mb_strlen($message) <= $chunkSize) {
        sendMessage($peerId, $message, $keyboard, $extra);
        return;
    }

    $parts = [];
    $remaining = $message;
    $maxParts = 5;

    while ($remaining !== '' && count($parts) < $maxParts) {
        if (mb_strlen($remaining) <= $chunkSize) {
            $parts[] = trim($remaining);
            break;
        }

        $slice = mb_substr($remaining, 0, $chunkSize);
        $cutPos = false;

        // 1) Стараемся резать по границе абзаца
        $pPos = mb_strrpos($slice, "\n\n");
        if ($pPos !== false && $pPos >= (int)($chunkSize * 0.45)) {
            $cutPos = $pPos;
        }
        // 2) Иначе по переносу строки
        if ($cutPos === false) {
            $nPos = mb_strrpos($slice, "\n");
            if ($nPos !== false && $nPos >= (int)($chunkSize * 0.5)) {
                $cutPos = $nPos;
            }
        }
        // 3) Иначе по концу предложения или пробелу
        if ($cutPos === false) {
            $sPos = mb_strrpos($slice, '. ');
            if ($sPos !== false && $sPos >= (int)($chunkSize * 0.55)) {
                $cutPos = $sPos + 1;
            } else {
                $spPos = mb_strrpos($slice, ' ');
                if ($spPos !== false && $spPos >= (int)($chunkSize * 0.5)) {
                    $cutPos = $spPos;
                }
            }
        }
        if ($cutPos === false) {
            $cutPos = $chunkSize;
        }

        $parts[] = trim(mb_substr($remaining, 0, $cutPos));
        $remaining = ltrim(mb_substr($remaining, $cutPos));
    }

    if ($remaining !== '' && !empty($parts)) {
        $lastIdx = count($parts) - 1;
        $parts[$lastIdx] = mb_substr($parts[$lastIdx], 0, max(100, $chunkSize - 4)) . '...';
    }

    $total = count($parts);
    foreach ($parts as $idx => $part) {
        $isLast = ($idx === $total - 1);
        $prefix = ($total > 1 && $idx > 0) ? "📄 Продолжение (" . ($idx + 1) . "/{$total}):\n\n" : "";
        sendMessage($peerId, $prefix . $part, $isLast ? $keyboard : null, $extra);
        if (!$isLast) {
            usleep(180000); // 180 мс, чтобы ВК сохранил строгий порядок сообщений
        }
    }
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

function isMainOwner(int $vkId): bool {
    return $vkId > 0 && $vkId === MAIN_OWNER_VK_ID;
}

function clearSuperAccessCache(int $vkId): void {
    cacheDel("super_{$vkId}");
}

function hasSuperAccess(PDO $pdo, int $vkId): bool {
    static $superCache = [];
    if ($vkId <= 0) return false;
    if (isset($superCache[$vkId])) return $superCache[$vkId];

    if ($vkId === MAIN_OWNER_VK_ID) {
        return $superCache[$vkId] = true;
    }
    $owners = array_filter(array_map('intval', explode(',', OWNER_VK_ID)));
    if (in_array($vkId, $owners, true)) {
        return $superCache[$vkId] = true;
    }
    $cached = cacheGet("super_{$vkId}", 60);
    if ($cached !== null) {
        return $superCache[$vkId] = ((int)$cached === 1);
    }
    $stmt = $pdo->prepare("SELECT is_superadmin FROM bot_users WHERE vk_id = ?");
    $stmt->execute([$vkId]);
    $res = ((int)$stmt->fetchColumn() === 1);
    cacheSet("super_{$vkId}", $res ? 1 : 0);
    return $superCache[$vkId] = $res;
}
