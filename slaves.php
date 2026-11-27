<?php
declare(strict_types=1);

/**
 * Система рабов RASE RUSSIA BOT:
 * - Покупка игроков в рабство (!купитьраба / !раб купить) — супер-доступ покупать нельзя!
 * - Рост цены с каждой покупкой (x1.5)
 * - Цепи на 24 часа (!цепи) — защита от покупки/выкупа на сутки
 * - Выкуп себя или другого игрока (!выкупиться / !выкупить)
 * - Добровольное освобождение (!отпуститьраба)
 * - Пассивный доход рабов (!рабы собрать) и рейтинг хозяев (!топхозяев)
 */

define('SLAVE_BASE_PRICE', 5000);

function ensureSlavesSchema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $flag = '/tmp/schema_slaves_v6.flag';
    if (is_file($flag)) {
        $done = true;
        return;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `bot_slaves` (
                `slave_vk_id` BIGINT UNSIGNED NOT NULL,
                `owner_vk_id` BIGINT UNSIGNED NOT NULL,
                `price` BIGINT UNSIGNED NOT NULL DEFAULT 5000,
                `purchases_count` INT UNSIGNED NOT NULL DEFAULT 1,
                `chained_until` DATETIME DEFAULT NULL,
                `last_collect_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `bought_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`slave_vk_id`),
                KEY `idx_owner` (`owner_vk_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        @file_put_contents($flag, '1');
        $done = true;
    } catch (Throwable $e) {
        file_put_contents('php://stderr', "[SLAVES SCHEMA] " . $e->getMessage() . PHP_EOL);
    }
}

function slaveRoundPrice(float $val): int {
    return max(SLAVE_BASE_PRICE, (int)(ceil($val / 500) * 500));
}

function getSlaveRow(PDO $pdo, int $slaveId): ?array {
    ensureSlavesSchema($pdo);
    $st = $pdo->prepare("SELECT * FROM bot_slaves WHERE slave_vk_id = ? LIMIT 1");
    $st->execute([$slaveId]);
    $row = $st->fetch();
    return is_array($row) ? $row : null;
}

function formatChainRemaining(?string $chainedUntil): ?string {
    if (empty($chainedUntil)) return null;
    $ts = strtotime($chainedUntil);
    if ($ts === false || $ts <= time()) return null;
    $diff = $ts - time();
    $h = (int)floor($diff / 3600);
    $m = (int)floor(($diff % 3600) / 60);
    return ($h > 0 ? "{$h} ч. " : "") . "{$m} мин.";
}

function calcSlavePendingIncome(array $row, int $now): int {
    $price = max(SLAVE_BASE_PRICE, (int)$row['price']);
    $lastTs = !empty($row['last_collect_at']) ? (int)strtotime((string)$row['last_collect_at']) : $now;
    $elapsedSec = max(0, $now - $lastTs);
    $hours = min(12.0, $elapsedSec / 3600.0); // Копится максимум 12 часов
    if ($hours < 0.08) return 0; // меньше ~5 минут
    // 10% от цены раба в час (макс 250 000 ₽/час с одного раба для баланса экономики)
    $perHour = min(250000, max(250, (int)round($price * 0.10)));
    return (int)floor($perHour * $hours);
}

function handleSlaveBuy(PDO $pdo, int $peerId, int $fromId, string $args, array $msg, array $ctx): void {
    ensureSlavesSchema($pdo);
    $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
    if (!$targetId) {
        sendMessage($peerId, "⛓ Использование: !купитьраба [ID/упоминание/ответ]\n💡 С каждой покупкой цена раба растёт в 1.5 раза. После покупки можно повесить цепи на 24ч командой !цепи.");
        return;
    }
    if ($targetId === $fromId) {
        sendMessage($peerId, "🤨 Нельзя купить самого себя в рабство!");
        return;
    }
    if ($targetId <= 0) {
        sendMessage($peerId, "⚠️ Нельзя покупать ботов и сообщества.");
        return;
    }
    if (hasSuperAccess($pdo, $targetId)) {
        sendMessage($peerId, "⛔ Пользователей с супер-доступом нельзя покупать в рабство!");
        return;
    }

    ensureUserAndMember($pdo, $peerId, $targetId);
    $existing = getSlaveRow($pdo, $targetId);

    if ($existing !== null) {
        if ((int)$existing['owner_vk_id'] === $fromId) {
            sendMessage($peerId, "ℹ️ " . formatUserMention($pdo, $peerId, $targetId) . " уже является вашим рабом!\n⛓ Повесить цепи на 24ч: !цепи {$targetId}");
            return;
        }
        $chainRem = formatChainRemaining($existing['chained_until'] ?? null);
        if ($chainRem !== null) {
            sendMessage($peerId, "⛓ Нельзя купить " . formatUserMention($pdo, $peerId, $targetId, 'acc') . " — на нём(ней) надеты цепи!\n⏳ Осталось: {$chainRem}.");
            return;
        }
        $buyPrice = max(SLAVE_BASE_PRICE, (int)$existing['price']);
        $oldOwnerId = (int)$existing['owner_vk_id'];
        $purchases = (int)$existing['purchases_count'] + 1;
    } else {
        $buyPrice = SLAVE_BASE_PRICE;
        $oldOwnerId = 0;
        $purchases = 1;
    }

    $balance = (int)($ctx['global']['balance'] ?? 0);
    if ($balance < $buyPrice) {
        sendMessage($peerId, "💸 Недостаточно средств! Цена покупки: " . formatMoney($buyPrice) . ", у вас: " . formatMoney($balance) . ".");
        return;
    }

    // Новая цена раба после покупки вырастает в 1.5 раза
    $nextPrice = min(500000000, slaveRoundPrice($buyPrice * 1.5));

    $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$buyPrice, $fromId]);

    $compNote = '';
    if ($oldOwnerId > 0) {
        $comp = (int)floor($buyPrice * 0.70);
        $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$comp, $oldOwnerId]);
        $compNote = "\n💼 Прошлый хозяин (" . formatUserMention($pdo, $peerId, $oldOwnerId) . ") получил компенсацию: " . formatMoney($comp);
    }

    $pdo->prepare("
        REPLACE INTO bot_slaves (slave_vk_id, owner_vk_id, price, purchases_count, chained_until, last_collect_at)
        VALUES (?, ?, ?, ?, NULL, NOW())
    ")->execute([$targetId, $fromId, $nextPrice, $purchases]);

    $chainCost = max(3000, (int)(round($nextPrice * 0.40 / 500) * 500));
    $kb = makeInlineKeyboard([[
        ['label' => "⛓ Цепи 24ч (" . formatMoney($chainCost) . ")", 'payload' => ['cmd' => "!цепи {$targetId}"], 'color' => 'primary'],
        ['label' => '📋 Мои рабы', 'payload' => ['cmd' => '!рабы'], 'color' => 'secondary']
    ]]);

    $uBuyer = formatUserMention($pdo, $peerId, $fromId);
    $uSlaveAcc = formatUserMention($pdo, $peerId, $targetId, 'acc');
    sendMessage(
        $peerId,
        "⛓ {$uBuyer} купил(а) {$uSlaveAcc} в рабство!\n"
        . "━━━━━━━━━━━━━━━━━━\n"
        . "💰 Заплачено: " . formatMoney($buyPrice) . "\n"
        . "📈 Новая цена раба: " . formatMoney($nextPrice) . " (покупка #{$purchases})"
        . $compNote . "\n"
        . "💡 Кинуть цепи на 24ч: !цепи {$targetId}",
        $kb,
        ['disable_mentions' => 1]
    );
}

function handleSlaveChain(PDO $pdo, int $peerId, int $fromId, string $args, array $msg, array $ctx): void {
    ensureSlavesSchema($pdo);
    $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
    if (!$targetId) {
        sendMessage($peerId, "⛓ Использование: !цепи [ID/упоминание раба]\nЗащищает раба от покупки и выкупа на 24 часа.");
        return;
    }
    $row = getSlaveRow($pdo, $targetId);
    if (!$row || (int)$row['owner_vk_id'] !== $fromId) {
        sendMessage($peerId, "⚠️ Этот игрок не является вашим рабом.");
        return;
    }
    $chainRem = formatChainRemaining($row['chained_until'] ?? null);
    if ($chainRem !== null) {
        sendMessage($peerId, "⛓ На " . formatUserMention($pdo, $peerId, $targetId, 'gen') . " уже надеты цепи! Осталось: {$chainRem}.");
        return;
    }

    $price = max(SLAVE_BASE_PRICE, (int)$row['price']);
    $chainCost = max(3000, (int)(round($price * 0.40 / 500) * 500));
    $balance = (int)($ctx['global']['balance'] ?? 0);
    if ($balance < $chainCost) {
        sendMessage($peerId, "💸 Недостаточно средств для покупки цепей!\nНужно: " . formatMoney($chainCost) . ", у вас: " . formatMoney($balance) . ".");
        return;
    }

    $until = date('Y-m-d H:i:s', time() + 86400);
    $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$chainCost, $fromId]);
    $pdo->prepare("UPDATE bot_slaves SET chained_until = ? WHERE slave_vk_id = ?")->execute([$until, $targetId]);

    sendMessage(
        $peerId,
        "⛓ " . formatUserMention($pdo, $peerId, $fromId) . " надел(а) цепи на " . formatUserMention($pdo, $peerId, $targetId, 'acc') . "!\n"
        . "💰 Стоимость: " . formatMoney($chainCost) . "\n"
        . "🔒 Защита от покупки и выкупа: 24 часа (до " . date('d.m H:i', time() + 86400) . " МСК).",
        null,
        ['disable_mentions' => 1]
    );
}

function handleSlaveBuyout(PDO $pdo, int $peerId, int $fromId, string $args, array $msg, array $ctx): void {
    ensureSlavesSchema($pdo);
    $targetId = trim($args) !== '' || !empty($msg['reply_message']) ? extractTargetUser($pdo, $peerId, $msg, $args) : $fromId;
    if (!$targetId) $targetId = $fromId;

    $row = getSlaveRow($pdo, $targetId);
    if (!$row) {
        sendMessage($peerId, $targetId === $fromId
            ? "🕊 Вы и так свободный человек!"
            : "🕊 Этот игрок сейчас свободен и не находится в рабстве.");
        return;
    }
    $ownerId = (int)$row['owner_vk_id'];
    if ($ownerId === $fromId) {
        sendMessage($peerId, "ℹ️ Это ваш собственный раб. Чтобы отпустить его бесплатно, введите: !отпуститьраба {$targetId}");
        return;
    }

    $chainRem = formatChainRemaining($row['chained_until'] ?? null);
    if ($chainRem !== null) {
        sendMessage($peerId, "⛓ Нельзя выкупить " . ($targetId === $fromId ? "себя" : formatUserMention($pdo, $peerId, $targetId, 'acc')) . " — действуют цепи!\n⏳ Осталось: {$chainRem}.");
        return;
    }

    $price = max(SLAVE_BASE_PRICE, (int)$row['price']);
    $buyoutCost = slaveRoundPrice($price * 1.25);
    $balance = (int)($ctx['global']['balance'] ?? 0);
    if ($balance < $buyoutCost) {
        sendMessage($peerId, "💸 Недостаточно средств для выкупа!\nНужно: " . formatMoney($buyoutCost) . ", у вас: " . formatMoney($balance) . ".");
        return;
    }

    $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$buyoutCost, $fromId]);
    $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$buyoutCost, $ownerId]);
    $pdo->prepare("DELETE FROM bot_slaves WHERE slave_vk_id = ?")->execute([$targetId]);

    $uActor = formatUserMention($pdo, $peerId, $fromId);
    $uOwner = formatUserMention($pdo, $peerId, $ownerId);
    if ($targetId === $fromId) {
        sendMessage($peerId, "🕊 {$uActor} выкупил(а) себя из рабства у {$uOwner} за " . formatMoney($buyoutCost) . " и обрёл(а) свободу!", null, ['disable_mentions' => 1]);
    } else {
        $uSlaveAcc = formatUserMention($pdo, $peerId, $targetId, 'acc');
        sendMessage($peerId, "🕊 {$uActor} выкупил(а) {$uSlaveAcc} на свободу у {$uOwner} за " . formatMoney($buyoutCost) . "!", null, ['disable_mentions' => 1]);
    }
}

function handleSlaveRelease(PDO $pdo, int $peerId, int $fromId, string $args, array $msg): void {
    ensureSlavesSchema($pdo);
    $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
    if (!$targetId) {
        sendMessage($peerId, "🕊 Использование: !отпуститьраба [ID/упоминание]");
        return;
    }
    $row = getSlaveRow($pdo, $targetId);
    if (!$row || (int)$row['owner_vk_id'] !== $fromId) {
        sendMessage($peerId, "⚠️ Этот игрок не является вашим рабом.");
        return;
    }
    $pdo->prepare("DELETE FROM bot_slaves WHERE slave_vk_id = ?")->execute([$targetId]);
    sendMessage($peerId, "🕊 " . formatUserMention($pdo, $peerId, $fromId) . " отпустил(а) " . formatUserMention($pdo, $peerId, $targetId, 'acc') . " на свободу!", null, ['disable_mentions' => 1]);
}

function handleSlavesCollect(PDO $pdo, int $peerId, int $fromId): void {
    ensureSlavesSchema($pdo);
    $st = $pdo->prepare("SELECT * FROM bot_slaves WHERE owner_vk_id = ?");
    $st->execute([$fromId]);
    $slaves = $st->fetchAll();
    if (empty($slaves)) {
        sendMessage($peerId, "ℹ️ У вас пока нет рабов. Купите кого-нибудь командой !купитьраба [ID/упоминание].");
        return;
    }
    $now = time();
    $total = 0;
    foreach ($slaves as $s) {
        $total += calcSlavePendingIncome($s, $now);
    }
    if ($total <= 0) {
        sendMessage($peerId, "⏳ Рабы ещё не успели заработать достаточно денег. Загляните чуть позже (доход копится до 12 часов).");
        return;
    }
    $pdo->prepare("UPDATE bot_slaves SET last_collect_at = NOW() WHERE owner_vk_id = ?")->execute([$fromId]);
    $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$total, $fromId]);
    sendMessage($peerId, "💰 " . formatUserMention($pdo, $peerId, $fromId) . " собрал(а) доход с рабов (" . count($slaves) . " чел.): +" . formatMoney($total) . "!", null, ['disable_mentions' => 1]);
}

function handleSlavesList(PDO $pdo, int $peerId, int $fromId, string $args, array $msg, array $ctx): void {
    ensureSlavesSchema($pdo);
    $low = mb_strtolower(trim($args));
    if (in_array($low, ['собрать', 'сбор', 'доход', 'collect'], true)) {
        handleSlavesCollect($pdo, $peerId, $fromId);
        return;
    }
    if (in_array($low, ['топ', 'top'], true)) {
        handleSlavesTop($pdo, $peerId);
        return;
    }

    $targetId = (trim($args) !== '' || !empty($msg['reply_message'])) ? (extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId) : $fromId;

    $myMaster = getSlaveRow($pdo, $targetId);
    $st = $pdo->prepare("SELECT * FROM bot_slaves WHERE owner_vk_id = ? ORDER BY price DESC LIMIT 20");
    $st->execute([$targetId]);
    $slaves = $st->fetchAll();

    $idsToPreload = array_map(fn($s) => (int)$s['slave_vk_id'], $slaves);
    if ($myMaster) $idsToPreload[] = (int)$myMaster['owner_vk_id'];
    if (!empty($idsToPreload)) preloadUsers($pdo, $peerId, $idsToPreload);

    $uTargetGen = formatUserMention($pdo, $peerId, $targetId, 'gen');
    $lines = ["⛓ Рабство {$uTargetGen}", "━━━━━━━━━━━━━━━━━━"];

    if ($myMaster) {
        $mId = (int)$myMaster['owner_vk_id'];
        $chRem = formatChainRemaining($myMaster['chained_until'] ?? null);
        $chStr = $chRem ? " | ⛓ Цепи: {$chRem}" : "";
        $bOut = slaveRoundPrice((int)$myMaster['price'] * 1.25);
        $lines[] = "👤 Статус: в рабстве у " . formatUserMention($pdo, $peerId, $mId);
        $lines[] = "💰 Цена перекупки: " . formatMoney((int)$myMaster['price']) . " | 🕊 Выкуп: " . formatMoney($bOut) . $chStr;
    } else {
        $lines[] = "🕊 Статус: свободный человек (стартовая цена: " . formatMoney(SLAVE_BASE_PRICE) . ")";
    }

    $now = time();
    $pendingTotal = 0;
    if (empty($slaves)) {
        $lines[] = "\n📋 Своих рабов пока нет.\nКупить: !купитьраба [ID/упоминание]";
    } else {
        $lines[] = "\n📋 Рабы (" . count($slaves) . "):";
        $i = 1;
        foreach ($slaves as $s) {
            $sid = (int)$s['slave_vk_id'];
            $p = (int)$s['price'];
            $inc = calcSlavePendingIncome($s, $now);
            $pendingTotal += $inc;
            $chRem = formatChainRemaining($s['chained_until'] ?? null);
            $chTag = $chRem ? " [⛓ {$chRem}]" : "";
            $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, $sid) . " — " . formatMoney($p) . $chTag;
            $i++;
        }
        $lines[] = "\n💵 Накопленный доход: " . formatMoney($pendingTotal);
    }

    $kbRows = [];
    if ($targetId === $fromId) {
        $row1 = [];
        if (!empty($slaves)) {
            $row1[] = ['label' => '💰 Собрать доход', 'payload' => ['cmd' => '!рабы собрать'], 'color' => 'positive'];
        }
        if ($myMaster && formatChainRemaining($myMaster['chained_until'] ?? null) === null) {
            $row1[] = ['label' => '🕊 Выкупиться', 'payload' => ['cmd' => '!выкупиться'], 'color' => 'primary'];
        }
        $row1[] = ['label' => '🏆 Топ хозяев', 'payload' => ['cmd' => '!топхозяев'], 'color' => 'secondary'];
        $kbRows[] = $row1;
    }
    $kb = !empty($kbRows) ? makeInlineKeyboard($kbRows) : null;
    sendLongMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
}

function handleSlavesTop(PDO $pdo, int $peerId): void {
    ensureSlavesSchema($pdo);
    $rows = $pdo->query("
        SELECT owner_vk_id, COUNT(*) AS cnt, SUM(price) AS total_val
        FROM bot_slaves
        GROUP BY owner_vk_id
        ORDER BY cnt DESC, total_val DESC
        LIMIT 10
    ")->fetchAll();

    if (empty($rows)) {
        sendMessage($peerId, "🏆 Топ хозяев пока пуст. Станьте первым: !купитьраба [ID/упоминание]");
        return;
    }
    $uids = array_map(fn($r) => (int)$r['owner_vk_id'], $rows);
    preloadUsers($pdo, $peerId, $uids);

    $lines = ["🏆 Топ хозяев рабов:"];
    $i = 1;
    foreach ($rows as $r) {
        $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['owner_vk_id'])
                 . " — {$r['cnt']} раб. (стоимость: " . formatMoney((int)$r['total_val']) . ")";
        $i++;
    }
    sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
}
