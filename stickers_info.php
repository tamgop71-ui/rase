<?php
declare(strict_types=1);

/**
 * Модуль просмотра стикеров пользователя ВКонтакте (!стикеры) в стиле чат-менеджера:
 * - Бесплатные, Платные, Коллекционные стикерпаки с кликабельными названиями
 * - Общая стоимость в рублях (1 голос = 7₽) и голосах
 * - Дата и время последнего добавления стикерпака (относительно + GMT+3)
 * - Кэширование в /tmp на 5 минут для мгновенного ответа
 *
 * Требует переменную окружения VK_USER_TOKEN (токен пользователя от официального клиента ВК:
 * VK Android / Kate Mobile / VK Me), т.к. VK API не отдаёт store.getProducts токену сообщества.
 */

define('VK_VOTE_PRICE_RUB', 7);

function stickersRelativeTimeRu(int $ts): string {
    $diff = max(0, time() - $ts);
    if ($diff < 60) return 'только что';
    if ($diff < 3600) {
        $m = (int)floor($diff / 60);
        return $m . ' ' . stickersPlural($m, ['минуту', 'минуты', 'минут']) . ' назад';
    }
    if ($diff < 86400) {
        $h = (int)floor($diff / 3600);
        return $h . ' ' . stickersPlural($h, ['час', 'часа', 'часов']) . ' назад';
    }
    $days = (int)floor($diff / 86400);
    if ($days < 30) {
        return $days . ' ' . stickersPlural($days, ['день', 'дня', 'дней']) . ' назад';
    }
    $months = (int)floor($days / 30);
    if ($months < 12) {
        return $months . ' ' . stickersPlural($months, ['месяц', 'месяца', 'месяцев']) . ' назад';
    }
    $years = (int)floor($days / 365);
    return $years . ' ' . stickersPlural($years, ['год', 'года', 'лет']) . ' назад';
}

function stickersPlural(int $n, array $forms): string {
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $forms[2];
    if ($n1 > 1 && $n1 < 5) return $forms[1];
    if ($n1 === 1) return $forms[0];
    return $forms[2];
}

function stickersFormatDateShort(int $ts): string {
    static $months = [
        1 => 'янв', 2 => 'фев', 3 => 'мар', 4 => 'апр',
        5 => 'мая', 6 => 'июн', 7 => 'июл', 8 => 'авг',
        9 => 'сен', 10 => 'окт', 11 => 'ноя', 12 => 'дек'
    ];
    $d = (int)date('j', $ts);
    $m = $months[(int)date('n', $ts)] ?? 'янв';
    $hm = date('H:i', $ts);
    return "{$d} {$m}, {$hm} GMT+3";
}

function stickersFormatPackLink(array $pack): string {
    $title = trim((string)($pack['title'] ?? 'Стикерпак'));
    $url = trim((string)($pack['url'] ?? ''));
    if ($url !== '' && str_starts_with($url, 'http')) {
        return "[{$url}|«{$title}»]";
    }
    return "«{$title}»";
}

/**
 * Подгружает справочник цен стикеров из gifts.getCatalog (кэш на 6 часов в /tmp)
 */
function stickersGetPriceCatalog(int $sampleUserId): array {
    $cached = cacheGet('vk_stickers_prices_v1', 21600);
    if (is_array($cached) && !empty($cached)) {
        return $cached;
    }

    $res = vkUserApi('gifts.getCatalog', [
        'user_id' => $sampleUserId,
        'no_inapp' => 0,
    ]);
    $prices = [];
    foreach (($res['response'] ?? []) as $section) {
        $secName = (string)($section['name'] ?? '');
        foreach (($section['items'] ?? []) as $item) {
            $gift = $item['gift'] ?? [];
            $pid = (int)($gift['stickers_product_id'] ?? 0);
            if ($pid <= 0) continue;
            $price = (int)($item['price'] ?? 0);
            $prices[$pid] = [
                'price'   => $price,
                'section' => $secName,
            ];
        }
    }
    if (!empty($prices)) {
        cacheSet('vk_stickers_prices_v1', $prices);
    }
    return $prices;
}

/**
 * Получает и классифицирует стикерпаки пользователя ВКонтакте
 */
function fetchVkUserStickers(int $targetId, bool $forceFresh = false): array {
    if (VK_USER_TOKEN === '') {
        return ['ok' => false, 'reason' => 'no_token'];
    }

    $cacheKey = "vk_st_{$targetId}";
    if (!$forceFresh) {
        $cached = cacheGet($cacheKey, 300);
        if (is_array($cached)) {
            return $cached;
        }
    }

    // 1. Запрашиваем купленные/добавленные стикерпаки пользователя
    $res = vkUserApi('store.getProducts', [
        'type'     => 'stickers',
        'user_id'  => $targetId,
        'extended' => 1,
        'filters'  => 'purchased',
    ]);

    if (isset($res['error'])) {
        $code = (int)($res['error']['error_code'] ?? 0);
        $msg  = (string)($res['error']['error_msg'] ?? 'VK API error');
        return ['ok' => false, 'reason' => 'api_error', 'code' => $code, 'msg' => $msg];
    }

    $items = $res['response']['items'] ?? ($res['response'] ?? []);
    if (!is_array($items)) {
        $items = [];
    }

    // Подгружаем каталог цен на случай, если в store.getProducts не вернулось поле price
    $priceCatalog = stickersGetPriceCatalog($targetId);

    $freePacks = [];
    $paidPacks = [];
    $collPacks = [];
    $totalVotes = 0;
    $lastPurchaseTs = 0;

    foreach ($items as $it) {
        if (!is_array($it)) continue;
        // Пропускаем стили стикеров (они дублируют базовый пак), если это не отдельный платный стиль
        $pid = (int)($it['id'] ?? 0);
        if ($pid <= 0) continue;

        $title = trim((string)($it['title'] ?? ''));
        if ($title === '') continue;

        $url = trim((string)($it['url'] ?? ''));
        if ($url === '' && !empty($it['base_url'])) {
            $url = (string)$it['base_url'];
        }

        $pDate = (int)($it['purchase_date'] ?? 0);
        if ($pDate > $lastPurchaseTs) {
            $lastPurchaseTs = $pDate;
        }

        // Определяем цену в голосах
        $votes = 0;
        if (isset($it['price']) && is_numeric($it['price'])) {
            $votes = (int)$it['price'];
        } elseif (isset($it['price']['votes'])) {
            $votes = (int)$it['price']['votes'];
        } elseif (isset($priceCatalog[$pid]['price'])) {
            $votes = (int)$priceCatalog[$pid]['price'];
        }

        $isFreeFlag = !empty($it['is_free']) || ($votes <= 0);
        $isPromo    = !empty($it['promoted'])
                   || !empty($it['is_promo'])
                   || !empty($it['is_exclusive'])
                   || (!empty($it['copyright']) && $votes <= 0 && empty($it['can_purchase']));
        $catSection = (string)($priceCatalog[$pid]['section'] ?? '');
        if (str_contains(mb_strtolower($catSection), 'promo') || str_contains(mb_strtolower($catSection), 'exclusive') || str_contains(mb_strtolower($catSection), 'collection')) {
            $isPromo = true;
        }

        $packObj = [
            'id'    => $pid,
            'title' => $title,
            'url'   => $url,
            'votes' => $votes,
            'ts'    => $pDate,
        ];

        if ($votes > 0) {
            $paidPacks[] = $packObj;
            $totalVotes += $votes;
        } elseif ($isPromo || ($isFreeFlag && isset($it['can_purchase']) && (int)$it['can_purchase'] === 0 && !empty($it['purchase_date']))) {
            $collPacks[] = $packObj;
        } else {
            $freePacks[] = $packObj;
        }
    }

    $result = [
        'ok'           => true,
        'free'         => $freePacks,
        'paid'         => $paidPacks,
        'collectible'  => $collPacks,
        'total_votes'  => $totalVotes,
        'total_rub'    => $totalVotes * VK_VOTE_PRICE_RUB,
        'last_ts'      => $lastPurchaseTs,
    ];

    cacheSet($cacheKey, $result);
    return $result;
}

function handleStickersCommand(PDO $pdo, int $peerId, int $fromId, string $args, array $msg, array $ctx, bool $isSuper): void {
    $raw = trim($args);
    $listCategory = '';
    $cleanArgs = $raw;

    if (preg_match('/^(бесплатные|платные|коллекционные|free|paid|coll|чат|chat)(?:\s+(.*))?$/iu', $raw, $m)) {
        $listCategory = mb_strtolower($m[1]);
        $cleanArgs = trim((string)($m[2] ?? ''));
    }

    $targetId = ($cleanArgs !== '' || !empty($msg['reply_message']) || !empty($msg['fwd_messages'][0]))
        ? (extractTargetUser($pdo, $peerId, $msg, $cleanArgs) ?? $fromId)
        : $fromId;

    $tCtx = ($targetId === $fromId) ? $ctx : ensureUserAndMember($pdo, $peerId, $targetId);
    $uGen = formatUserMention($pdo, $peerId, $targetId, 'gen');

    // Если явно запросили статистику сообщений со стикерами в чате
    if ($listCategory === 'чат' || $listCategory === 'chat') {
        sendMessage(
            $peerId,
            "🎭 Отправлено стикеров {$uGen}:\n• В беседе: {$tCtx['member']['stickers_count']} шт.\n• Всего в боте: {$tCtx['global']['total_stickers']} шт.",
            null,
            ['disable_mentions' => 1]
        );
        return;
    }

    $data = fetchVkUserStickers($targetId);

    if (empty($data['ok'])) {
        $reason = (string)($data['reason'] ?? '');
        $fallback = "🎭 Стикеры {$uGen}:\n• Отправлено в беседе: {$tCtx['member']['stickers_count']} шт.\n• Всего отправлено: {$tCtx['global']['total_stickers']} шт.";
        if ($reason === 'no_token') {
            if ($isSuper) {
                $fallback .= "\n\n⚙️ Для показа наборов стикеров ВК и их стоимости добавьте переменную VK_USER_TOKEN (токен официального клиента ВК) в настройках BotHost.";
            }
        } elseif ($reason === 'api_error') {
            $code = (int)($data['code'] ?? 0);
            if ($code === 30 || $code === 15) {
                $fallback .= "\n\n🔒 Профиль пользователя закрыт или список стикеров недоступен.";
            } elseif ($isSuper) {
                $fallback .= "\n\n⚠️ Ошибка VK User API (код {$code}): {$data['msg']}";
            }
        }
        sendMessage($peerId, $fallback, null, ['disable_mentions' => 1]);
        return;
    }

    // Если запросили полный список конкретной категории (через кнопку)
    if ($listCategory !== '') {
        $catKey = match ($listCategory) {
            'платные', 'paid'             => 'paid',
            'коллекционные', 'coll'       => 'collectible',
            default                       => 'free',
        };
        $catTitle = match ($catKey) {
            'paid'        => '🤑 Платные стикеры',
            'collectible' => '🐹 Коллекционные стикеры',
            default       => '🆓 Бесплатные стикеры',
        };
        $packs = $data[$catKey] ?? [];
        if (empty($packs)) {
            sendMessage($peerId, "{$catTitle} у {$uGen}: нет наборов.", null, ['disable_mentions' => 1]);
            return;
        }
        $itemsStr = array_map(fn($p) => stickersFormatPackLink($p) . ($p['votes'] > 0 ? " ({$p['votes']} гол.)" : ''), $packs);
        sendLongMessage(
            $peerId,
            "{$catTitle} у {$uGen} (" . count($packs) . "):\n" . implode(', ', $itemsStr),
            null,
            ['disable_mentions' => 1, 'dont_parse_links' => 1]
        );
        return;
    }

    // Основной вывод 1-в-1 как на референсе
    $formatPreview = function (array $packs): string {
        if (empty($packs)) return 'нет';
        $first3 = array_slice($packs, 0, 3);
        $links = array_map('stickersFormatPackLink', $first3);
        return implode(', ', $links) . (count($packs) > 3 ? ' и др.' : '');
    };

    $freeCnt = count($data['free']);
    $paidCnt = count($data['paid']);
    $collCnt = count($data['collectible']);
    $rubStr  = number_format((int)$data['total_rub'], 0, '.', ' ');
    $votes   = (int)$data['total_votes'];
    $lastTs  = (int)$data['last_ts'];

    $lines = [
        "Стикеры у {$uGen}:",
        "🆓 Бесплатные ({$freeCnt}): " . $formatPreview($data['free']),
        "🤑 Платные ({$paidCnt}): " . $formatPreview($data['paid']),
        "🐹 Коллекционные ({$collCnt}): " . $formatPreview($data['collectible']),
        "",
        "Другая информация:",
        "💰 Общая сумма: {$rubStr}₽ (в голосах: {$votes})",
    ];

    if ($lastTs > 0) {
        $rel = stickersRelativeTimeRu($lastTs);
        $dt  = stickersFormatDateShort($lastTs);
        $lines[] = "📅 Последнее добавление: {$rel} ({$dt})";
    } else {
        $lines[] = "📅 Последнее добавление: нет данных";
    }

    $kbRow = [];
    if ($paidCnt > 0) $kbRow[] = ['label' => "🤑 Платные ({$paidCnt})", 'payload' => ['cmd' => "!стикеры платные {$targetId}"], 'color' => 'primary'];
    if ($freeCnt > 0) $kbRow[] = ['label' => "🆓 Бесплатные ({$freeCnt})", 'payload' => ['cmd' => "!стикеры бесплатные {$targetId}"], 'color' => 'secondary'];
    if ($collCnt > 0) $kbRow[] = ['label' => "🐹 Коллекц. ({$collCnt})", 'payload' => ['cmd' => "!стикеры коллекционные {$targetId}"], 'color' => 'secondary'];

    $kb = !empty($kbRow) ? makeInlineKeyboard([$kbRow]) : null;
    sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1, 'dont_parse_links' => 1]);
}
