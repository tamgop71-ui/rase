<?php
declare(strict_types=1);

/**
 * Игровые ништяки RASE RUSSIA BOT:
 * 1) Бизнесы с пассивным доходом и прокачкой (!бизнес, !собрать)
 * 2) Колесо Фортуны раз в 6 часов (!колесо / !фортуна)
 * 3) Сундук с кладом в чате — кто первый нажал кнопку, тот забрал куш (!сундук / !дроп)
 */

function ensureBusinessesSchema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $flag = '/tmp/schema_biz_v7.flag';
    if (is_file($flag)) {
        $done = true;
        return;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `bot_businesses` (
                `vk_id` BIGINT UNSIGNED NOT NULL,
                `biz_type` TINYINT UNSIGNED NOT NULL,
                `level` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `last_collect_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `bought_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`vk_id`),
                KEY `idx_biz_type` (`biz_type`, `level`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        @file_put_contents($flag, '1');
        $done = true;
    } catch (Throwable $e) {
        file_put_contents('php://stderr', "[BIZ SCHEMA] " . $e->getMessage() . PHP_EOL);
    }
}

function getBusinessCatalog(): array {
    return [
        1 => ['name' => '🌭 Шаурмечная',    'price' => 15000,   'income' => 1500],
        2 => ['name' => '🔧 Гараж-СТО',     'price' => 50000,   'income' => 5500],
        3 => ['name' => '⛽ АЗС «RASE»',    'price' => 150000,  'income' => 18000],
        4 => ['name' => '🏎 Автосалон',     'price' => 500000,  'income' => 65000],
        5 => ['name' => '🏨 Отель RASE',    'price' => 1500000, 'income' => 210000],
        6 => ['name' => '🏦 Банк RASE',     'price' => 5000000, 'income' => 750000],
    ];
}

function calcBusinessHourlyIncome(int $bizType, int $level): int {
    $cat = getBusinessCatalog();
    $base = (int)($cat[$bizType]['income'] ?? 1500);
    // +35% за каждый уровень выше 1-го
    return (int)round($base * (1.0 + ($level - 1) * 0.35));
}

function calcBusinessUpgradeCost(int $bizType, int $curLevel): int {
    $cat = getBusinessCatalog();
    $basePrice = (int)($cat[$bizType]['price'] ?? 15000);
    return (int)(ceil(($basePrice * (0.55 * $curLevel)) / 500) * 500);
}

function calcBusinessPending(array $row, int $now): array {
    $hourly = calcBusinessHourlyIncome((int)$row['biz_type'], (int)$row['level']);
    $lastTs = !empty($row['last_collect_at']) ? (int)strtotime((string)$row['last_collect_at']) : $now;
    $elapsedSec = max(0, $now - $lastTs);
    $hours = min(24.0, $elapsedSec / 3600.0); // Копится максимум 24 часа
    $earned = ($hours >= 0.05) ? (int)floor($hourly * $hours) : 0; // мин. 3 минуты
    return ['earned' => $earned, 'hourly' => $hourly, 'hours' => round($hours, 1)];
}

function handleBusinessCollect(PDO $pdo, int $peerId, int $fromId): void {
    ensureBusinessesSchema($pdo);
    $st = $pdo->prepare("SELECT * FROM bot_businesses WHERE vk_id = ? LIMIT 1");
    $st->execute([$fromId]);
    $row = $st->fetch();
    if (!$row) {
        sendMessage($peerId, "🏢 У вас пока нет бизнеса! Посмотреть каталог: !бизнес", makeInlineKeyboard([[
            ['label' => '🏢 Каталог бизнесов', 'payload' => ['cmd' => '!бизнес'], 'color' => 'primary']
        ]]));
        return;
    }

    $calc = calcBusinessPending($row, time());
    if ($calc['earned'] <= 0) {
        sendMessage($peerId, "⏳ Касса пока пуста (прибыль появляется через 3 мин. после прошлого сбора). Доход: " . formatMoney($calc['hourly']) . "/ч.");
        return;
    }

    $pdo->prepare("UPDATE bot_businesses SET last_collect_at = NOW() WHERE vk_id = ?")->execute([$fromId]);
    $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$calc['earned'], $fromId]);

    $cat = getBusinessCatalog();
    $bName = $cat[(int)$row['biz_type']]['name'] ?? 'Бизнес';
    $kb = makeInlineKeyboard([[
        ['label' => '🏢 Мой бизнес', 'payload' => ['cmd' => '!бизнес'], 'color' => 'secondary'],
        ['label' => '🎰 В казино', 'payload' => ['cmd' => '!казино 1000'], 'color' => 'primary']
    ]]);
    sendMessage(
        $peerId,
        "💰 " . formatUserMention($pdo, $peerId, $fromId) . " собрал(а) прибыль с «{$bName}» (Ур. {$row['level']}): +" . formatMoney($calc['earned']) . "!",
        $kb,
        ['disable_mentions' => 1]
    );
}

function handleBusinessCommand(PDO $pdo, int $peerId, int $fromId, string $args, array $ctx): void {
    ensureBusinessesSchema($pdo);
    $cat = getBusinessCatalog();
    $raw = mb_strtolower(trim($args));
    $parts = preg_split('/\s+/u', $raw);
    $sub = $parts[0] ?? '';

    if (in_array($sub, ['собрать', 'прибыль', 'касса', 'collect'], true)) {
        handleBusinessCollect($pdo, $peerId, $fromId);
        return;
    }

    if (in_array($sub, ['топ', 'top'], true)) {
        $rows = $pdo->query("SELECT vk_id, biz_type, level FROM bot_businesses ORDER BY biz_type DESC, level DESC LIMIT 10")->fetchAll();
        if (empty($rows)) {
            sendMessage($peerId, "🏆 Топ бизнесменов пока пуст. Купите первый бизнес командой !бизнес!");
            return;
        }
        preloadUsers($pdo, $peerId, array_map(fn($r) => (int)$r['vk_id'], $rows));
        $lines = ["🏆 Топ бизнесменов RASE RUSSIA:"];
        $i = 1;
        foreach ($rows as $r) {
            $bt = (int)$r['biz_type'];
            $lv = (int)$r['level'];
            $bn = $cat[$bt]['name'] ?? 'Бизнес';
            $inc = calcBusinessHourlyIncome($bt, $lv);
            $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['vk_id']) . " — {$bn} (Ур. {$lv}) · " . formatMoney($inc) . "/ч";
            $i++;
        }
        sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
        return;
    }

    $st = $pdo->prepare("SELECT * FROM bot_businesses WHERE vk_id = ? LIMIT 1");
    $st->execute([$fromId]);
    $myBiz = $st->fetch() ?: null;

    // Покупка бизнеса: !бизнес купить [1-6]
    if (in_array($sub, ['купить', 'buy'], true)) {
        $num = (int)($parts[1] ?? 0);
        if (!isset($cat[$num])) {
            sendMessage($peerId, "⚠️ Укажите номер бизнеса от 1 до 6. Пример: !бизнес купить 1");
            return;
        }
        if ($myBiz !== null) {
            sendMessage($peerId, "ℹ️ У вас уже есть бизнес! Чтобы купить другой, сначала продайте текущий: !бизнес продать");
            return;
        }
        $price = (int)$cat[$num]['price'];
        $bal = (int)($ctx['global']['balance'] ?? 0);
        if ($bal < $price) {
            sendMessage($peerId, "💸 Недостаточно средств! Цена «{$cat[$num]['name']}»: " . formatMoney($price) . ", у вас: " . formatMoney($bal) . ".");
            return;
        }
        $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$price, $fromId]);
        $pdo->prepare("REPLACE INTO bot_businesses (vk_id, biz_type, level, last_collect_at) VALUES (?, ?, 1, NOW())")->execute([$fromId, $num]);

        $kb = makeInlineKeyboard([[
            ['label' => '🏢 Мой бизнес', 'payload' => ['cmd' => '!бизнес'], 'color' => 'primary'],
            ['label' => '💰 Собрать прибыль', 'payload' => ['cmd' => '!собрать'], 'color' => 'positive']
        ]]);
        sendMessage(
            $peerId,
            "🎉 Поздравляем! Вы купили «{$cat[$num]['name']}» за " . formatMoney($price) . "!\n"
            . "📈 Доход: " . formatMoney((int)$cat[$num]['income']) . "/час (копится до 24ч).\n"
            . "💰 Забирать прибыль: !собрать",
            $kb
        );
        return;
    }

    // Улучшение бизнеса: !бизнес улучшить
    if (in_array($sub, ['улучшить', 'ап', 'up', 'upgrade'], true)) {
        if (!$myBiz) {
            sendMessage($peerId, "⚠️ У вас пока нет бизнеса! Посмотреть каталог: !бизнес");
            return;
        }
        $curLvl = (int)$myBiz['level'];
        if ($curLvl >= 10) {
            sendMessage($peerId, "👑 Ваш бизнес уже прокачан до максимального 10-го уровня!");
            return;
        }
        $bt = (int)$myBiz['biz_type'];
        $cost = calcBusinessUpgradeCost($bt, $curLvl);
        $bal = (int)($ctx['global']['balance'] ?? 0);
        if ($bal < $cost) {
            sendMessage($peerId, "💸 Недостаточно средств для улучшения до Ур. " . ($curLvl + 1) . "!\nНужно: " . formatMoney($cost) . ", у вас: " . formatMoney($bal) . ".");
            return;
        }
        $newLvl = $curLvl + 1;
        $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$cost, $fromId]);
        $pdo->prepare("UPDATE bot_businesses SET level = ? WHERE vk_id = ?")->execute([$newLvl, $fromId]);
        $newInc = calcBusinessHourlyIncome($bt, $newLvl);

        $kb = makeInlineKeyboard([[
            ['label' => '🏢 Мой бизнес', 'payload' => ['cmd' => '!бизнес'], 'color' => 'primary']
        ]]);
        sendMessage(
            $peerId,
            "⬆️ Бизнес «{$cat[$bt]['name']}» улучшен до Ур. {$newLvl} за " . formatMoney($cost) . "!\n"
            . "📈 Новый доход: " . formatMoney($newInc) . "/час!",
            $kb
        );
        return;
    }

    // Продажа бизнеса: !бизнес продать
    if (in_array($sub, ['продать', 'sell'], true)) {
        if (!$myBiz) {
            sendMessage($peerId, "⚠️ У вас нет бизнеса для продажи.");
            return;
        }
        $bt = (int)$myBiz['biz_type'];
        $refund = (int)floor(((int)$cat[$bt]['price']) * 0.60);
        $pdo->prepare("DELETE FROM bot_businesses WHERE vk_id = ?")->execute([$fromId]);
        $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$refund, $fromId]);
        sendMessage($peerId, "🤝 Вы продали «{$cat[$bt]['name']}» и получили " . formatMoney($refund) . " (60% стоимости).");
        return;
    }

    // Показать свой бизнес или каталог
    if ($myBiz && $sub !== 'каталог' && $sub !== 'магазин') {
        $bt = (int)$myBiz['biz_type'];
        $lv = (int)$myBiz['level'];
        $bName = $cat[$bt]['name'] ?? 'Бизнес';
        $calc = calcBusinessPending($myBiz, time());
        $upStr = ($lv < 10) ? ("⬆️ Улучшение до Ур. " . ($lv + 1) . ": " . formatMoney(calcBusinessUpgradeCost($bt, $lv))) : "👑 Максимальный уровень (10/10)";

        $row1 = [['label' => '💰 Собрать (' . formatMoney($calc['earned']) . ')', 'payload' => ['cmd' => '!собрать'], 'color' => 'positive']];
        if ($lv < 10) {
            $row1[] = ['label' => '⬆️ Улучшить', 'payload' => ['cmd' => '!бизнес улучшить'], 'color' => 'primary'];
        }
        $kb = makeInlineKeyboard([
            $row1,
            [
                ['label' => '📋 Каталог', 'payload' => ['cmd' => '!бизнес каталог'], 'color' => 'secondary'],
                ['label' => '🏆 Топ бизнесов', 'payload' => ['cmd' => '!бизнес топ'], 'color' => 'secondary']
            ]
        ]);
        sendMessage(
            $peerId,
            "🏢 Ваш бизнес: {$bName}\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "⭐ Уровень: {$lv} / 10\n"
            . "📈 Доход: " . formatMoney($calc['hourly']) . "/час\n"
            . "💵 В кассе: " . formatMoney($calc['earned']) . " (копилось {$calc['hours']}ч / макс 24ч)\n"
            . "{$upStr}\n"
            . "💡 Продать (возврат 60%): !бизнес продать",
            $kb
        );
        return;
    }

    // Каталог бизнесов
    $lines = ["🏢 Каталог бизнесов RASE RUSSIA:", "━━━━━━━━━━━━━━━━━━"];
    foreach ($cat as $id => $b) {
        $lines[] = "{$id}. {$b['name']} — " . formatMoney($b['price']) . " (📈 " . formatMoney($b['income']) . "/ч)";
    }
    $lines[] = "\n💡 Купить: !бизнес купить [номер] | Прокачка до 10 ур. (+35% за уровень)";
    $kb = makeInlineKeyboard([
        [
            ['label' => '1. Шаурмечная', 'payload' => ['cmd' => '!бизнес купить 1'], 'color' => 'secondary'],
            ['label' => '2. Гараж-СТО', 'payload' => ['cmd' => '!бизнес купить 2'], 'color' => 'secondary'],
            ['label' => '3. АЗС', 'payload' => ['cmd' => '!бизнес купить 3'], 'color' => 'primary'],
        ],
        [
            ['label' => '4. Автосалон', 'payload' => ['cmd' => '!бизнес купить 4'], 'color' => 'primary'],
            ['label' => '5. Отель', 'payload' => ['cmd' => '!бизнес купить 5'], 'color' => 'positive'],
            ['label' => '6. Банк', 'payload' => ['cmd' => '!бизнес купить 6'], 'color' => 'positive'],
        ],
        [
            ['label' => '🏆 Топ бизнесменов', 'payload' => ['cmd' => '!бизнес топ'], 'color' => 'secondary']
        ]
    ]);
    sendMessage($peerId, implode("\n", $lines), $kb);
}

// =========================================================================
// 2. КОЛЕСО ФОРТУНЫ (!колесо / !фортуна) — 0 SQL на проверке кулдауна!
// =========================================================================

function handleFortuneWheel(PDO $pdo, int $peerId, int $fromId, array $ctx): void {
    $isVip = ((int)($ctx['global']['is_vip'] ?? 0) === 1);
    $cdSec = $isVip ? 10800 : 21600; // 3 ч для VIP, 6 ч для обычных
    $cdFile = "/tmp/wheel_{$fromId}.txt";
    $now = time();

    if (is_file($cdFile)) {
        $last = (int)@file_get_contents($cdFile);
        $diff = $now - $last;
        if ($diff < $cdSec) {
            $rem = $cdSec - $diff;
            $h = (int)floor($rem / 3600);
            $m = (int)floor(($rem % 3600) / 60);
            sendMessage($peerId, "🎡 Вы уже крутили Колесо Фортуны! Следующий бесплатный спин через {$h} ч. {$m} мин." . (!$isVip ? "\n💎 С VIP-статусом откат в 2 раза быстрее (3 ч)." : ""));
            return;
        }
    }
    @file_put_contents($cdFile, (string)$now);

    $roll = random_int(1, 100);
    $uM = formatUserMention($pdo, $peerId, $fromId);
    $kb = makeInlineKeyboard([[
        ['label' => '🎰 В казино (1к)', 'payload' => ['cmd' => '!казино 1000'], 'color' => 'primary'],
        ['label' => '🎁 Кейсы', 'payload' => ['cmd' => '!кейс'], 'color' => 'secondary']
    ]]);

    if ($roll <= 2) {
        if (!$isVip) {
            $pdo->prepare("UPDATE bot_users SET is_vip = 1 WHERE vk_id = ?")->execute([$fromId]);
            sendMessage($peerId, "🎡 {$uM} крутит Колесо Фортуны...\n💎 ЛЕГЕНДАРНЫЙ СЕКТОР! Вы выиграли постоянный VIP-статус!", $kb, ['disable_mentions' => 1]);
        } else {
            $pdo->prepare("UPDATE bot_users SET balance = balance + 30000 WHERE vk_id = ?")->execute([$fromId]);
            sendMessage($peerId, "🎡 {$uM} крутит Колесо Фортуны...\n💎 ЛЕГЕНДАРНЫЙ СЕКТОР! Так как VIP уже есть, вам начислено +30 000 ₽!", $kb, ['disable_mentions' => 1]);
        }
        return;
    } elseif ($roll <= 10) {
        $prize = 25000;
        $label = "🔥 ДЖЕКПОТ! +" . formatMoney($prize);
        $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$prize, $fromId]);
    } elseif ($roll <= 25) {
        $prize = 10000;
        $label = "💰 Крупный выигрыш! +" . formatMoney($prize);
        $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$prize, $fromId]);
    } elseif ($roll <= 40) {
        $rep = 5;
        $prize = 3000;
        $label = "⭐ +{$rep} репутации и +" . formatMoney($prize);
        $pdo->prepare("UPDATE bot_users SET balance = balance + ?, reputation = reputation + ? WHERE vk_id = ?")->execute([$prize, $rep, $fromId]);
    } elseif ($roll <= 70) {
        $prize = random_int(3000, 6500);
        $label = "💵 Выигрыш: +" . formatMoney($prize);
        $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$prize, $fromId]);
    } else {
        $prize = random_int(1500, 2900);
        $label = "🪙 Небольшой приз: +" . formatMoney($prize);
        $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$prize, $fromId]);
    }

    sendMessage($peerId, "🎡 {$uM} крутит Колесо Фортуны...\n🎉 Выпало: {$label}!", $kb, ['disable_mentions' => 1]);
}

// =========================================================================
// 3. СУНДУК С КЛАДОМ В ЧАТЕ (!сундук [сумма] / !дроп) — кто первый нажал!
// =========================================================================

function handleChatChest(PDO $pdo, int $peerId, int $fromId, string $args, int $myPriority, bool $isSuper, array $ctx): void {
    if ($peerId <= 2000000000) {
        sendMessage($peerId, "🎁 Сундуки с кладом работают только в групповых беседах!");
        return;
    }

    $chestFile = "/tmp/chest_{$peerId}.json";
    $raw = trim($args);
    $parts = preg_split('/\s+/u', mb_strtolower($raw));
    $sub = $parts[0] ?? '';

    // 1. Забрать клад: !сундук забрать [token]
    if (in_array($sub, ['забрать', 'открыть', 'claim', 'take'], true)) {
        $tok = (string)($parts[1] ?? '');
        if (!is_file($chestFile)) {
            sendMessage($peerId, "💨 Опоздал! Сундук уже забрали.");
            return;
        }
        $chest = json_decode((string)@file_get_contents($chestFile), true);
        if (!is_array($chest) || ($tok !== '' && ($chest['token'] ?? '') !== $tok)) {
            sendMessage($peerId, "💨 Этот сундук уже пуст!");
            return;
        }
        if (!$isSuper && (int)($chest['creator_id'] ?? 0) === $fromId) {
            sendMessage($peerId, "🤨 Нельзя забирать собственный сундук — пусть его найдут другие игроки!");
            return;
        }

        // Атомарно удаляем файл сундука, чтобы двое не могли забрать одновременно
        if (!@unlink($chestFile)) {
            sendMessage($peerId, "💨 Кто-то оказался на долю секунды быстрее!");
            return;
        }

        $amount = (int)$chest['amount'];
        $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$amount, $fromId]);
        $uWin = formatUserMention($pdo, $peerId, $fromId);
        sendMessage($peerId, "🎉 {$uWin} быстрее всех открыл(а) сундук и забрал(а) " . formatMoney($amount) . "!", null, ['disable_mentions' => 1]);
        return;
    }

    // 2. Создать сундук: !сундук [сумма]
    if (is_file($chestFile)) {
        $old = json_decode((string)@file_get_contents($chestFile), true);
        if (is_array($old) && (time() - (int)($old['ts'] ?? 0)) < 300) {
            $kb = makeInlineKeyboard([[
                ['label' => '🎁 Забрать клад (' . formatMoney((int)$old['amount']) . ')!', 'payload' => ['cmd' => "!сундук забрать {$old['token']}"], 'color' => 'positive']
            ]]);
            sendMessage($peerId, "🎁 В беседе уже лежит нераскрытый сундук на " . formatMoney((int)$old['amount']) . "! Жмите быстрее:", $kb);
            return;
        }
    }

    $bal = (int)($ctx['global']['balance'] ?? 0);
    $amount = ($raw !== '') ? parseAmount($raw, $isSuper ? 10000000 : $bal) : 5000;
    if ($amount === null || $amount < 500) {
        sendMessage($peerId, "🎁 Использование: !сундук [сумма от 500 ₽]\nПример: !сундук 10к — сбросит в чат сундук с кнопкой «Забрать клад!»");
        return;
    }
    if (!$isSuper) {
        if ($amount > $bal) {
            sendMessage($peerId, "💸 Недостаточно средств на балансе! У вас: " . formatMoney($bal) . ".");
            return;
        }
        $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$amount, $fromId]);
    } else {
        $amount = min(10000000, $amount);
    }

    $token = bin2hex(random_bytes(4));
    $chestData = [
        'token'      => $token,
        'amount'     => $amount,
        'creator_id' => $fromId,
        'ts'         => time(),
    ];
    $tmp = $chestFile . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode($chestData)) !== false) {
        @rename($tmp, $chestFile);
    }

    $uAuthor = formatUserMention($pdo, $peerId, $fromId);
    $kb = makeInlineKeyboard([[
        ['label' => '🎁 Забрать клад!', 'payload' => ['cmd' => "!сундук забрать {$token}"], 'color' => 'positive']
    ]]);
    sendMessage(
        $peerId,
        "🎁 [КЛАД В БЕСЕДЕ] {$uAuthor} сбросил(а) сундук на " . formatMoney($amount) . "!\n"
        . "⚡ Кто первый нажмёт на кнопку ниже — забирает всю сумму!",
        $kb,
        ['disable_mentions' => 1]
    );
}
