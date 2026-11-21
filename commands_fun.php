<?php
declare(strict_types=1);

/**
 * Обработчик общих, игровых, экономических, РП и социальных команд.
 * Возвращает true, если команда обработана.
 */
function handleFunAndGeneralCommands(PDO $pdo, string $cmd, string $args, int $peerId, int $fromId, array $ctx, array $msg, int $myPriority, bool $isSuper): bool {
    switch ($cmd) {
        // ==========================================
        // 1. ОБЩИЕ КОМАНДЫ
        // ==========================================
        case 'ping':
            $t0 = microtime(true);
            $pdo->query('SELECT 1');
            $dbMs = round((microtime(true) - $t0) * 1000, 1);
            $totalMs = isset($GLOBALS['_req_start']) ? round((microtime(true) - $GLOBALS['_req_start']) * 1000, 1) : $dbMs;
            sendMessage($peerId, "🏓 Понг! Бот работает молниеносно.\n⚡ Обработка: {$totalMs} мс | БД: {$dbMs} мс\n🕒 Сервер: " . date('d.m.Y H:i:s') . " МСК");
            return true;

        case 'start':
            if ($myPriority >= 100 || $isSuper || (int)$ctx['chat']['owner_vk_id'] === 0) {
                executeCommand($pdo, 'setup', '', $peerId, $fromId, $ctx, $msg);
            } else {
                sendMessage($peerId, "👋 Бот уже работает в этой беседе! Введите !помощь для просмотра команд.");
            }
            return true;

        case 'help':
            $cat = mb_strtolower(trim($args));
            $kb = makeInlineKeyboard([
                [
                    ['label' => '👤 Основное', 'payload' => ['cmd' => '!help base'], 'color' => 'primary'],
                    ['label' => '🎮 Игры и Экономика', 'payload' => ['cmd' => '!help games'], 'color' => 'positive']
                ],
                [
                    ['label' => '🛡 Помощник (20+)', 'payload' => ['cmd' => '!help mod20'], 'color' => 'secondary'],
                    ['label' => '⚔ Модер (40+)', 'payload' => ['cmd' => '!help mod40'], 'color' => 'secondary']
                ],
                [
                    ['label' => '⚡ Админ (60-80+)', 'payload' => ['cmd' => '!help adm'], 'color' => 'secondary'],
                    ['label' => '👑 Владелец (100)', 'payload' => ['cmd' => '!help owner'], 'color' => 'negative']
                ]
            ]);

            if ($cat === 'games') {
                $txt = "🎮 Игры, Экономика и РП (префиксы: ! / . + -):\n\n"
                     . "💰 Экономика:\n"
                     . "• !профиль (!profile) — баланс, банк, репутация, страна\n"
                     . "• !бонус (!bonus) — ежедневный бонус 1 000 ₽\n"
                     . "• !работа (!work) — заработать (раз в 10 мин)\n"
                     . "• !банк / !положить [сумма] / !снять [сумма]\n"
                     . "• !перевод [ID] [сумма] / !ограбить [ID]\n"
                     . "• !топ / !atop — рейтинги (баланс/сообщения/символы/стикеры)\n\n"
                     . "🎰 Азарт и Мини-игры:\n"
                     . "• !казино [ставка] (до x5) | !слоты [ставка]\n"
                     . "• !рулетка [красное/черное/зеленое] [ставка]\n"
                     . "• !дуэль [ID] [ставка] | !рандом (!roll)\n"
                     . "• !шар [вопрос] | !выбери [А или Б]\n\n"
                     . "❤️ Социальное и РП:\n"
                     . "• !гражданство [страна] / !страна / !страны\n"
                     . "• !брак [ID] / !развод / !браки | !реп [ID]\n"
                     . "• !поцелуй / !обнять / !попытка / !пиво / !iznas / !minet\n"
                     . "• !стикеры [ID] | !эмодзи [смайл]";
            } elseif ($cat === 'mod20') {
                $txt = "🛡 Команды Помощника (приоритет ≥ 20):\n\n"
                     . "• !стата (ID) / !чатинфо / !reg [ID]\n"
                     . "• !мут [ID] [мин] (причина) / !унмут [ID]\n"
                     . "• !кик [ID] (причина) — исключить из чата\n"
                     . "• !пред (!варн) [ID] (причина) / !унварн [ID]\n"
                     . "• !предупреждения (!getwarn) / !getban / !getmute [ID]\n"
                     . "• !warnlist (!преды) / !историяварнов [ID]\n"
                     . "• !ник / !сник / !рник / !нлист / !безников / !checknicks\n"
                     . "• !вызов (текст) — созвать участников\n"
                     . "• !заметка [имя] — показать заметку беседы";
            } elseif ($cat === 'mod40') {
                $txt = "⚔ Команды Модератора (приоритет ≥ 40):\n\n"
                     . "• !бан [ID] (дни) (причина) / !унбан [ID] / !banlist\n"
                     . "• !role [ID] [приоритет/название] / !removerole [ID]\n"
                     . "• !помощник [ID] / !роли\n"
                     . "• !тишина [время / выкл] — режим тишины\n"
                     . "• !очистить [кол-во / ID] — удалить последние сообщения\n"
                     . "• !понику [часть ника] — поиск по нику\n"
                     . "• !gzov / !gkick / !gsnick / !грник / !gremoverole";
            } elseif ($cat === 'adm') {
                $txt = "⚡ Администратор (60+) и Гл. Админ (80+):\n\n"
                     . "Приоритет ≥ 60:\n"
                     . "• !модер / !grole / !гпомощник / !гмодер [ID]\n"
                     . "• !gban / !gunban [ID] — глобальный бан по объединению\n"
                     . "• !удалить (пересланное) — удалить сообщение\n"
                     . "• !gm [ID] / !removegm [ID] / !gms — иммунитет\n"
                     . "• !history [ID] / !reports / !getreport\n\n"
                     . "Приоритет ≥ 80:\n"
                     . "• !pin / !unpin — закреп сообщений\n"
                     . "• !admin / !gadmin [ID]\n"
                     . "• !newrole / !gnewrole / !delrole / !gdelrole\n"
                     . "• !welcome / !setrules / !delrules / !заметки\n"
                     . "• !неактив [дни] (кик) / !givemoney / !timeuved";
            } elseif ($cat === 'owner') {
                $txt = "👑 Владелец беседы (100) и Супер-доступ (/addws):\n\n"
                     . "• !setup / !settings / !gsettings / !gedit\n"
                     . "• !unity / !unites / !createunity / !addunity / !editunity / !removeunity / !deleteunity\n"
                     . "• !sync / !spec / !gspec / !wipe / !migration\n"
                     . "• !games / !automod / !антифлуд / !уведы\n"
                     . "• !editcmd / !geditcmd / !owner [ID] / !никэмодзи\n"
                     . "• Супер-доступ: !addws, !vip, !setlog, !zovvv, !gzovg, !gtimeuvedg, !getdialog, !reportedit, !listunities, !listen, !chatlist, !userchats, !ahistory, !checkban";
            } else {
                $txt = "📋 Справка по командам RAZE RUSSIA BOT (v5.0 IDEAL):\n"
                     . "Префиксы: ! / . + - (на русском и английском)\n\n"
                     . "• !пинг / !статус — скорость и статус бота\n"
                     . "• !правила — правила беседы\n"
                     . "• !роли / !админы / !онлайн — инфо о чате\n"
                     . "• !стата / !профиль — ваша статистика и баланс\n"
                     . "• !history — история своих наказаний\n"
                     . "• !thereid / !myid — ID беседы и ваш VK ID\n"
                     . "• /report [текст] — отправить тикет администрации\n"
                     . "• !упоминать / !неупоминать — настройки вызова\n\n"
                     . "👇 Выберите раздел на кнопках ниже:";
            }
            sendMessage($peerId, $txt, $kb);
            return true;

        case 'thereid':
            sendMessage($peerId, "🆔 ID этой беседы: {$peerId}\n👤 Ваш VK ID: {$fromId}");
            return true;

        case 'rules':
            $rules = trim((string)($ctx['chat']['rules_text'] ?? ''));
            if ($rules === '') {
                $rules = "1. Уважайте других участников беседы.\n2. Запрещён спам, флуд и реклама.\n3. Соблюдайте правила проекта RAZE RUSSIA.";
            }
            sendMessage($peerId, "📜 Правила беседы:\n" . $rules);
            return true;

        case 'roles':
            $roles = getChatRoles($pdo, $peerId);
            $lines = ["🛡 Список доступных ролей (в скобках указан приоритет):"];
            foreach ($roles as $prio => $rName) {
                $lines[] = "• {$rName} ({$prio})";
            }
            sendMessage($peerId, implode("\n", $lines));
            return true;

        case 'admins':
            $stmt = $pdo->prepare("SELECT vk_id, role_priority FROM bot_chat_members WHERE peer_id = ? AND role_priority > 0 ORDER BY role_priority DESC");
            $stmt->execute([$peerId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "🛡 В этой беседе ещё не назначена администрация. Владелец может ввести !setup или !sync.");
                return true;
            }
            preloadUsers($pdo, $peerId, array_column($rows, 'vk_id'));
            $out = ["🛡 Список руководителей беседы:"];
            foreach ($rows as $r) {
                $rName = getRoleName($pdo, $peerId, (int)$r['role_priority']);
                $mention = formatUserMention($pdo, $peerId, (int)$r['vk_id']);
                $out[] = "• {$mention} — {$rName} ({$r['role_priority']})";
            }
            sendMessage($peerId, implode("\n", $out), null, ['disable_mentions' => 1]);
            return true;

        case 'online':
            $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId, 'fields' => 'online,first_name,last_name']);
            $profiles = $res['response']['profiles'] ?? [];
            $onlineIds = [];
            foreach ($profiles as $p) {
                if (!empty($p['online'])) {
                    $onlineIds[] = (int)$p['id'];
                }
            }
            if (empty($onlineIds)) {
                sendMessage($peerId, "🌐 Сейчас нет участников онлайн (либо боту не выданы права администратора беседы).");
                return true;
            }
            $slice = array_slice($onlineIds, 0, 50);
            preloadUsers($pdo, $peerId, $slice);
            $onlineList = [];
            foreach ($slice as $uid) {
                $onlineList[] = "• " . formatUserMention($pdo, $peerId, $uid);
            }
            sendMessage($peerId, "🟢 Участники онлайн (" . count($onlineIds) . "):\n" . implode("\n", $onlineList), null, ['disable_mentions' => 1]);
            return true;

        case 'mention_on':
            $pdo->prepare("UPDATE bot_chat_members SET mention_opt_in = 1 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            sendMessage($peerId, "🔔 Вы снова будете упоминаться в !вызов и !гвызов.");
            return true;

        case 'mention_off':
            $pdo->prepare("UPDATE bot_chat_members SET mention_opt_in = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            sendMessage($peerId, "🔕 Вы больше не будете упомянуты в !вызов и !гвызов.");
            return true;

        case 'mentions':
            if ($myPriority < 80 && !$isSuper) {
                $new = (int)$ctx['member']['mention_opt_in'] ? 0 : 1;
                $pdo->prepare("UPDATE bot_chat_members SET mention_opt_in = ? WHERE peer_id = ? AND vk_id = ?")->execute([$new, $peerId, $fromId]);
                sendMessage($peerId, $new ? "🔔 Упоминания для вас включены." : "🔕 Вы больше не будете упомянуты в !вызов и !гвызов.");
            } else {
                $newChat = (int)($ctx['chat']['mentions_enabled'] ?? 1) ? 0 : 1;
                $pdo->prepare("UPDATE bot_chats SET mentions_enabled = ? WHERE peer_id = ?")->execute([$newChat, $peerId]);
                sendMessage($peerId, $newChat ? "🔔 Массовые упоминания (!gzovg) в беседе включены." : "🔕 Массовые упоминания в беседе отключены.");
            }
            return true;

        // ==========================================
        // 2. ПРОФИЛЬ, ЭКОНОМИКА, БАНК, ОГРАБЛЕНИЯ, ИГРЫ
        // ==========================================
        case 'profile':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ($targetId === $fromId) ? $ctx : ensureUserAndMember($pdo, $peerId, $targetId);
            $g = $tCtx['global'];
            $m = $tCtx['member'];

            $rName = getRoleName($pdo, $peerId, (int)$m['role_priority']);
            $countryName = 'Не выбрана (!страны)';
            if (!empty($g['country_id'])) {
                $stmtC = $pdo->prepare("SELECT name FROM bot_countries WHERE id = ?");
                $stmtC->execute([(int)$g['country_id']]);
                if ($cFetch = $stmtC->fetchColumn()) $countryName = $cFetch;
            }
            $marriageStr = 'Холост / Не замужем';
            if (!empty($g['spouse_vk_id'])) {
                $marriageStr = "В браке с " . formatUserMention($pdo, $peerId, (int)$g['spouse_vk_id']);
            }
            $vipBadge = ((int)$g['is_vip'] === 1 || (int)$g['is_superadmin'] === 1) ? ' 💎 [VIP]' : '';
            $mention = formatUserMention($pdo, $peerId, $targetId);
            $regDate = date('d.m.Y H:i', strtotime($m['joined_at'] ?? 'now'));

            $txt = "👤 Профиль {$mention}{$vipBadge}:\n"
                 . "━━━━━━━━━━━━━━━━━━\n"
                 . "🛡 Роль: {$rName} ({$m['role_priority']})\n"
                 . "💰 Наличные: " . formatMoney((int)$g['balance']) . "\n"
                 . "🏦 В банке: " . formatMoney((int)$g['bank_balance']) . "\n"
                 . "⭐ Репутация: {$g['reputation']}\n"
                 . "🏳 Гражданство: {$countryName}\n"
                 . "💍 Брак: {$marriageStr}\n"
                 . "✉️ Сообщений: {$m['messages_count']} (Всего: {$g['total_messages']})\n"
                 . "📅 В беседе с: {$regDate} МСК";
            sendMessage($peerId, $txt, null, ['disable_mentions' => 1]);
            return true;

        case 'bonus':
            $lastBonus = $ctx['global']['last_bonus'] ?? null;
            if ($lastBonus !== null && (time() - strtotime($lastBonus)) < 86400) {
                $rem = 86400 - (time() - strtotime($lastBonus));
                $h = (int)floor($rem / 3600);
                $min = (int)floor(($rem % 3600) / 60);
                sendMessage($peerId, "⏳ Вы уже забирали бонус. Следующий доступен через {$h} ч. {$min} мин.");
                return true;
            }
            $reward = ((int)$ctx['global']['is_vip'] === 1) ? 2500 : 1000;
            $pdo->prepare("UPDATE bot_users SET balance = balance + ?, last_bonus = NOW() WHERE vk_id = ?")->execute([$reward, $fromId]);
            sendMessage($peerId, "🎁 Вы получили ежедневный бонус: " . formatMoney($reward) . "!");
            return true;

        case 'work':
            $lastWork = $ctx['global']['last_work'] ?? null;
            $cd = ((int)$ctx['global']['is_vip'] === 1) ? 300 : 600;
            if ($lastWork !== null && (time() - strtotime($lastWork)) < $cd) {
                $rem = $cd - (time() - strtotime($lastWork));
                $min = (int)ceil($rem / 60);
                sendMessage($peerId, "⏳ Вы устали! Следующая смена через {$min} мин.");
                return true;
            }
            $earn = random_int(350, 1200);
            $jobs = [
                "отработали рейс дальнобойщиком в RAZE RUSSIA",
                "провели ночную смену в ППС",
                "починили спорткар в тюнинг-ателье",
                "доставили материалы на склад фракции",
                "провели успешный эфир в СМИ"
            ];
            $job = $jobs[array_rand($jobs)];
            $pdo->prepare("UPDATE bot_users SET balance = balance + ?, last_work = NOW() WHERE vk_id = ?")->execute([$earn, $fromId]);
            sendMessage($peerId, "💼 Вы {$job} и заработали " . formatMoney($earn) . "!");
            return true;

        case 'bank':
            $bal = (int)$ctx['global']['balance'];
            $bank = (int)$ctx['global']['bank_balance'];
            sendMessage($peerId, "🏦 Банковский счёт:\n• Наличные: " . formatMoney($bal) . "\n• В банке (защищены от ограблений): " . formatMoney($bank) . "\n\nКоманды: !положить [сумма] | !снять [сумма]");
            return true;

        case 'deposit':
            $bal = (int)$ctx['global']['balance'];
            $amt = parseAmount($args, $bal);
            if ($amt === null || $amt <= 0 || $amt > $bal) {
                sendMessage($peerId, "⚠️ Укажите корректную сумму (у вас на руках: " . formatMoney($bal) . ").");
                return true;
            }
            $pdo->prepare("UPDATE bot_users SET balance = balance - ?, bank_balance = bank_balance + ? WHERE vk_id = ?")->execute([$amt, $amt, $fromId]);
            sendMessage($peerId, "🏦 Вы положили в банк " . formatMoney($amt) . ". Теперь в банке: " . formatMoney((int)$ctx['global']['bank_balance'] + $amt));
            return true;

        case 'withdraw':
            $bank = (int)$ctx['global']['bank_balance'];
            $amt = parseAmount($args, $bank);
            if ($amt === null || $amt <= 0 || $amt > $bank) {
                sendMessage($peerId, "⚠️ Укажите корректную сумму (в банке: " . formatMoney($bank) . ").");
                return true;
            }
            $pdo->prepare("UPDATE bot_users SET bank_balance = bank_balance - ?, balance = balance + ? WHERE vk_id = ?")->execute([$amt, $amt, $fromId]);
            sendMessage($peerId, "🏦 Вы сняли со счёта " . formatMoney($amt) . ". На руках: " . formatMoney((int)$ctx['global']['balance'] + $amt));
            return true;

        case 'rob':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "🥷 Использование: !ограбить [ID/упоминание]");
                return true;
            }
            $lastRob = $ctx['global']['last_rob'] ?? null;
            if ($lastRob !== null && (time() - strtotime($lastRob)) < 1800) {
                $rem = (int)ceil((1800 - (time() - strtotime($lastRob))) / 60);
                sendMessage($peerId, "🚔 Полиция начеку! Повторить ограбление можно через {$rem} мин.");
                return true;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $tBal = (int)$tCtx['global']['balance'];
            if ($tBal < 500) {
                sendMessage($peerId, "🥷 У жертвы на руках слишком мало наличных (меньше 500 ₽).");
                return true;
            }
            $pdo->prepare("UPDATE bot_users SET last_rob = NOW() WHERE vk_id = ?")->execute([$fromId]);
            $uTarget = formatUserMention($pdo, $peerId, $targetId);
            if (random_int(1, 100) <= 45) {
                $stolen = min((int)round($tBal * (random_int(10, 25) / 100)), 50000);
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$stolen, $targetId]);
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$stolen, $fromId]);
                sendMessage($peerId, "🥷 Успешное ограбление! Вы незаметно вытащили у {$uTarget} " . formatMoney($stolen) . "!");
            } else {
                $fine = min((int)$ctx['global']['balance'], random_int(300, 1200));
                $pdo->prepare("UPDATE bot_users SET balance = GREATEST(0, balance - ?) WHERE vk_id = ?")->execute([$fine, $fromId]);
                sendMessage($peerId, "🚔 Вас поймали с поличным при попытке ограбить {$uTarget}! Штраф: " . formatMoney($fine) . ".");
            }
            return true;

        case 'casino':
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal);
            if ($bet === null || $bet <= 0) {
                sendMessage($peerId, "🎰 Укажите ставку! Примеры: !казино 500, !казино 5к, !казино все");
                return true;
            }
            if ($bet > $bal) {
                sendMessage($peerId, "Недостаточно средств. Ваш баланс: " . formatMoney($bal) . ".");
                return true;
            }
            $multipliers = [0.0, 0.1, 0.25, 0.5, 0.5, 0.75, 1.0, 1.25, 1.5, 2.0, 2.5, 5.0];
            $mult = $multipliers[array_rand($multipliers)];
            $win = (int)round($bet * $mult);
            $diff = $win - $bet;
            $newBal = $bal + $diff;
            $pdo->prepare("UPDATE bot_users SET balance = ? WHERE vk_id = ?")->execute([$newBal, $fromId]);
            if ($mult >= 1.0) {
                sendMessage($peerId, "🎰 Выпал множитель {$mult}×!\n✅ Выигрыш: " . formatMoney($win) . " (чистыми +" . formatMoney($diff) . ")\n💰 Баланс: " . formatMoney($newBal));
            } else {
                sendMessage($peerId, "🎰 Выпал множитель {$mult}×.\n❌ Проигрыш: " . formatMoney(abs($diff)) . "\n💰 Баланс: " . formatMoney($newBal));
            }
            return true;

        case 'slots':
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal);
            if ($bet === null || $bet <= 0 || $bet > $bal) {
                sendMessage($peerId, "🎰 Использование: !слоты [ставка] (ваш баланс: " . formatMoney($bal) . ")");
                return true;
            }
            $icons = ['🍒', '🍋', '🍇', '🔔', '💎', '7️⃣'];
            $r1 = $icons[array_rand($icons)];
            $r2 = $icons[array_rand($icons)];
            $r3 = $icons[array_rand($icons)];
            if ($r1 === $r2 && $r2 === $r3) {
                $mult = ($r1 === '7️⃣') ? 10 : (($r1 === '💎') ? 5 : 3);
                $win = $bet * $mult;
                $newBal = $bal + $win - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ? WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎰 [ {$r1} | {$r2} | {$r3} ]\n🔥 ДЖЕКПОТ x{$mult}! Выигрыш: " . formatMoney($win) . "\n💰 Баланс: " . formatMoney($newBal));
            } elseif ($r1 === $r2 || $r2 === $r3 || $r1 === $r3) {
                $win = (int)round($bet * 1.3);
                $newBal = $bal + $win - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ? WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎰 [ {$r1} | {$r2} | {$r3} ]\n✅ Пара совпала (x1.3)! Выигрыш: " . formatMoney($win) . "\n💰 Баланс: " . formatMoney($newBal));
            } else {
                $newBal = $bal - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ? WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎰 [ {$r1} | {$r2} | {$r3} ]\n❌ Не повезло. Проигрыш: " . formatMoney($bet) . "\n💰 Баланс: " . formatMoney($newBal));
            }
            return true;

        case 'roulette':
            if (!preg_match('/^(красное|черное|чёрное|зеленое|зелёное|red|black|green)\s+(\S+)$/iu', trim($args), $m)) {
                sendMessage($peerId, "🎡 Использование: !рулетка [красное/черное/зеленое] [ставка]");
                return true;
            }
            $colorRaw = mb_strtolower($m[1]);
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($m[2], $bal);
            if ($bet === null || $bet <= 0 || $bet > $bal) {
                sendMessage($peerId, "Недостаточно средств. Ваш баланс: " . formatMoney($bal));
                return true;
            }
            $choice = 'red';
            if (in_array($colorRaw, ['черное', 'чёрное', 'black'], true)) $choice = 'black';
            elseif (in_array($colorRaw, ['зеленое', 'зелёное', 'green'], true)) $choice = 'green';

            $num = random_int(0, 36);
            $resColor = ($num === 0) ? 'green' : (($num % 2 === 0) ? 'black' : 'red');
            $cEmoji = ($resColor === 'green') ? '🟢 Зелёное (0)' : (($resColor === 'red') ? "🔴 Красное ({$num})" : "⚫ Чёрное ({$num})");

            if ($choice === $resColor) {
                $mult = ($resColor === 'green') ? 14 : 2;
                $win = $bet * $mult;
                $newBal = $bal + $win - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ? WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎡 Выпало: {$cEmoji}\n✅ Победа (x{$mult})! Выигрыш: " . formatMoney($win) . "\n💰 Баланс: " . formatMoney($newBal));
            } else {
                $newBal = $bal - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ? WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎡 Выпало: {$cEmoji}\n❌ Вы проиграли " . formatMoney($bet) . ".\n💰 Баланс: " . formatMoney($newBal));
            }
            return true;

        case 'pay':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "⚠️ Использование: !перевод [ID/упоминание] [сумма]");
                return true;
            }
            $bal = (int)$ctx['global']['balance'];
            $amount = parseAmount($args, $bal);
            if ($amount === null || $amount <= 0) {
                sendMessage($peerId, "⚠️ Укажите корректную сумму (например: 500, 5к, 1кк).");
                return true;
            }
            if ($amount > $bal) {
                sendMessage($peerId, "Недостаточно средств. Ваш баланс: " . formatMoney($bal) . ".");
                return true;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$amount, $fromId]);
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$amount, $targetId]);
            $targetMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "💸 Перевод выполнен: " . formatMoney($amount) . " отправлено пользователю {$targetMention}.");
            return true;

        case 'duel':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "🔫 Использование: !дуэль [ID/упоминание] [ставка]");
                return true;
            }
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal) ?? 500;
            if ($bet <= 0 || $bet > $bal) {
                sendMessage($peerId, "Недостаточно средств. Ваш баланс: " . formatMoney($bal));
                return true;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            if ((int)$tCtx['global']['balance'] < $bet) {
                sendMessage($peerId, "У соперника недостаточно наличных для этой ставки.");
                return true;
            }
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            $u2 = formatUserMention($pdo, $peerId, $targetId);
            if (random_int(0, 1) === 1) {
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$bet, $fromId]);
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$bet, $targetId]);
                sendMessage($peerId, "🔫 Дуэль {$u1} vs {$u2}!\n🏆 Победил {$u1} и забрал банк " . formatMoney($bet * 2) . "!");
            } else {
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$bet, $fromId]);
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$bet, $targetId]);
                sendMessage($peerId, "🔫 Дуэль {$u1} vs {$u2}!\n🏆 Победил {$u2} и забрал банк " . formatMoney($bet * 2) . "!");
            }
            return true;

        case 'top':
        case 'atop':
            $isGlobal = ($cmd === 'atop');
            $mode = mb_strtolower(trim($args));
            $prefix = $isGlobal ? '!atop' : '!top';
            $kb = makeInlineKeyboard([
                [
                    ['label' => '💰 Баланс', 'payload' => ['cmd' => "{$prefix} balance"], 'color' => 'primary'],
                    ['label' => '✉️ Сообщения', 'payload' => ['cmd' => "{$prefix} msg"], 'color' => 'secondary']
                ],
                [
                    ['label' => '🔤 Символы', 'payload' => ['cmd' => "{$prefix} chars"], 'color' => 'secondary'],
                    ['label' => '🎭 Стикеры', 'payload' => ['cmd' => "{$prefix} stickers"], 'color' => 'secondary']
                ]
            ]);

            if ($isGlobal) {
                if ($mode === 'msg') {
                    $rows = $pdo->query("SELECT vk_id, total_messages AS val FROM bot_users ORDER BY total_messages DESC LIMIT 10")->fetchAll();
                    $title = "🌍 Глобальный топ по сообщениям:"; $unit = "сообщ.";
                } elseif ($mode === 'chars') {
                    $rows = $pdo->query("SELECT vk_id, total_chars AS val FROM bot_users ORDER BY total_chars DESC LIMIT 10")->fetchAll();
                    $title = "🌍 Глобальный топ по символам:"; $unit = "симв.";
                } elseif ($mode === 'stickers') {
                    $rows = $pdo->query("SELECT vk_id, total_stickers AS val FROM bot_users ORDER BY total_stickers DESC LIMIT 10")->fetchAll();
                    $title = "🌍 Глобальный топ по стикерам:"; $unit = "стик.";
                } else {
                    $rows = $pdo->query("SELECT vk_id, (balance + bank_balance) AS val FROM bot_users ORDER BY val DESC LIMIT 10")->fetchAll();
                    $title = "🌍 Глобальный топ богачей (наличные + банк):"; $unit = "₽";
                }
            } else {
                if ($mode === 'msg') {
                    $stmt = $pdo->prepare("SELECT vk_id, messages_count AS val FROM bot_chat_members WHERE peer_id = ? ORDER BY messages_count DESC LIMIT 10");
                    $stmt->execute([$peerId]); $rows = $stmt->fetchAll();
                    $title = "👑 Топ беседы по сообщениям:"; $unit = "сообщ.";
                } elseif ($mode === 'chars') {
                    $stmt = $pdo->prepare("SELECT vk_id, chars_count AS val FROM bot_chat_members WHERE peer_id = ? ORDER BY chars_count DESC LIMIT 10");
                    $stmt->execute([$peerId]); $rows = $stmt->fetchAll();
                    $title = "👑 Топ беседы по символам:"; $unit = "симв.";
                } elseif ($mode === 'stickers') {
                    $stmt = $pdo->prepare("SELECT vk_id, stickers_count AS val FROM bot_chat_members WHERE peer_id = ? ORDER BY stickers_count DESC LIMIT 10");
                    $stmt->execute([$peerId]); $rows = $stmt->fetchAll();
                    $title = "👑 Топ беседы по стикерам:"; $unit = "стик.";
                } else {
                    $stmt = $pdo->prepare("SELECT m.vk_id, (u.balance + u.bank_balance) AS val FROM bot_chat_members m JOIN bot_users u ON u.vk_id = m.vk_id WHERE m.peer_id = ? ORDER BY val DESC LIMIT 10");
                    $stmt->execute([$peerId]); $rows = $stmt->fetchAll();
                    $title = "👑 Лучшие игроки беседы по балансу:"; $unit = "₽";
                }
            }

            preloadUsers($pdo, $peerId, array_column($rows, 'vk_id'));
            $lines = [$title];
            $i = 1;
            foreach ($rows as $r) {
                $mention = formatUserMention($pdo, $peerId, (int)$r['vk_id']);
                $lines[] = "{$i}. {$mention} — " . number_format((int)$r['val'], 0, '.', ' ') . " {$unit}";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
            return true;

        case 'rep':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "⭐ Укажите участника, чтобы повысить ему репутацию: !реп @упоминание");
                return true;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_users SET reputation = reputation + 1 WHERE vk_id = ?")->execute([$targetId]);
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            $u2 = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "⭐ {$u1} повысил(а) репутацию пользователю {$u2} (+1)!");
            return true;

        case 'stickers':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ($targetId === $fromId) ? $ctx : ensureUserAndMember($pdo, $peerId, $targetId);
            $uM = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "🎭 Статистика стикеров {$uM}:\n• В этой беседе отправлено: {$tCtx['member']['stickers_count']} шт.\n• Всего во всех чатах бота: {$tCtx['global']['total_stickers']} шт.");
            return true;

        case 'emoji':
            if ((int)$ctx['global']['is_vip'] === 0 && !$isSuper && $myPriority < 80) {
                sendMessage($peerId, "💎 Установка эмодзи в никнейм доступна VIP-пользователям и Главным администраторам.");
                return true;
            }
            $e = mb_substr(trim($args), 0, 8);
            $pdo->prepare("UPDATE bot_chat_members SET emoji = ? WHERE peer_id = ? AND vk_id = ?")->execute([$e !== '' ? $e : null, $peerId, $fromId]);
            sendMessage($peerId, $e !== '' ? "🌟 Ваш эмодзи в никнейме установлен: {$e}" : "🗑 Эмодзи из никнейма удалён.");
            return true;

        case 'enick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $e = mb_substr(trim($args), 0, 8);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !никэмодзи [ID/упоминание] [смайл]");
                return true;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_chat_members SET emoji = ? WHERE peer_id = ? AND vk_id = ?")->execute([$e !== '' ? $e : null, $peerId, $targetId]);
            sendMessage($peerId, "✅ Эмодзи пользователя " . formatUserMention($pdo, $peerId, $targetId) . " обновлён.");
            return true;

        // ==========================================
        // 3. СТРАНЫ, БРАКИ, РП И УТИЛИТЫ
        // ==========================================
        case 'citizenship':
            $cInput = trim($args);
            if ($cInput === '') {
                sendMessage($peerId, "🏳 Укажите ID или название страны: !гражданство Россия (!страны)");
                return true;
            }
            if (ctype_digit($cInput)) {
                $stmt = $pdo->prepare("SELECT * FROM bot_countries WHERE id = ?");
                $stmt->execute([(int)$cInput]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM bot_countries WHERE LOWER(name) = LOWER(?)");
                $stmt->execute([$cInput]);
            }
            $country = $stmt->fetch();
            if (!$country) {
                $cleanName = mb_substr($cInput, 0, 40);
                $pdo->prepare("INSERT INTO bot_countries (name, president_vk_id) VALUES (?, ?)")->execute([$cleanName, $fromId]);
                $cid = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE bot_users SET country_id = ? WHERE vk_id = ?")->execute([$cid, $fromId]);
                sendMessage($peerId, "🏛 Вы основали новое государство «{$cleanName}» (ID: {$cid}) и стали его президентом!");
            } else {
                $pdo->prepare("UPDATE bot_users SET country_id = ? WHERE vk_id = ?")->execute([(int)$country['id'], $fromId]);
                sendMessage($peerId, "🏳 Вы успешно получили гражданство страны «{$country['name']}»!");
            }
            return true;

        case 'mycountry':
            $cid = (int)($ctx['global']['country_id'] ?? 0);
            if ($cid <= 0) {
                sendMessage($peerId, "🏳 У вас ещё нет гражданства. Используйте !гражданство [название] или !страны.");
                return true;
            }
            $stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM bot_users u WHERE u.country_id = c.id) AS pop FROM bot_countries c WHERE c.id = ?");
            $stmt->execute([$cid]);
            $c = $stmt->fetch();
            if (!$c) { sendMessage($peerId, "🏳 Государство не найдено."); return true; }
            sendMessage($peerId, "🏳 Ваше государство: {$c['name']} (ID: {$c['id']})\n👥 Граждан: {$c['pop']} чел.");
            return true;

        case 'countries':
            $page = max(1, (int)trim($args));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT c.id, c.name, COUNT(u.vk_id) AS pop FROM bot_countries c LEFT JOIN bot_users u ON u.country_id = c.id GROUP BY c.id ORDER BY pop DESC, c.id ASC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "🏳 На странице {$page} стран не найдено.");
                return true;
            }
            $lines = ["🌍 Список государств (Страница {$page}):"];
            foreach ($rows as $r) {
                $lines[] = "• [{$r['id']}] {$r['name']} — {$r['pop']} гражд.";
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!страны ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!страны ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb);
            return true;

        case 'marry':
            $sub = mb_strtolower(trim($args));
            if (preg_match('/^(accept|decline)\s+(\d+)$/', $sub, $m)) {
                $act = $m[1];
                $proposerId = (int)$m[2];
                $stmtP = $pdo->prepare("SELECT * FROM bot_marriage_proposals WHERE from_vk_id = ? AND to_vk_id = ?");
                $stmtP->execute([$proposerId, $fromId]);
                if (!$stmtP->fetch()) {
                    sendMessage($peerId, "⚠️ Предложение о браке не найдено или уже устарело.");
                    return true;
                }
                $pdo->prepare("DELETE FROM bot_marriage_proposals WHERE from_vk_id IN (?, ?) OR to_vk_id IN (?, ?)")
                    ->execute([$fromId, $proposerId, $fromId, $proposerId]);
                $u1 = formatUserMention($pdo, $peerId, $proposerId);
                $u2 = formatUserMention($pdo, $peerId, $fromId);
                if ($act === 'accept') {
                    $pdo->prepare("UPDATE bot_users SET spouse_vk_id = ?, marriage_date = NOW() WHERE vk_id = ?")->execute([$proposerId, $fromId]);
                    $pdo->prepare("UPDATE bot_users SET spouse_vk_id = ?, marriage_date = NOW() WHERE vk_id = ?")->execute([$fromId, $proposerId]);
                    sendMessage($peerId, "💍 Горько! {$u1} и {$u2} вступили в законный брак!");
                } else {
                    sendMessage($peerId, "💔 {$u2} отклонил(а) предложение руки и сердца от {$u1}.");
                }
                return true;
            }
            if (!empty($ctx['global']['spouse_vk_id'])) {
                sendMessage($peerId, "💍 Вы уже состоите в браке! Для расторжения используйте !развод.");
                return true;
            }
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "💍 Использование: !брак [ID/упоминание]");
                return true;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            if (!empty($tCtx['global']['spouse_vk_id'])) {
                sendMessage($peerId, "💔 Этот пользователь уже состоит в браке.");
                return true;
            }
            $pdo->prepare("REPLACE INTO bot_marriage_proposals (peer_id, from_vk_id, to_vk_id) VALUES (?, ?, ?)")->execute([$peerId, $fromId, $targetId]);
            $uFrom = formatUserMention($pdo, $peerId, $fromId);
            $uTo   = formatUserMention($pdo, $peerId, $targetId);
            $kb = makeInlineKeyboard([[
                ['label' => '💍 Принять', 'payload' => ['cmd' => "!marry accept {$fromId}"], 'color' => 'positive'],
                ['label' => '💔 Отклонить', 'payload' => ['cmd' => "!marry decline {$fromId}"], 'color' => 'negative']
            ]]);
            sendMessage($peerId, "💐 {$uTo}, пользователь {$uFrom} предлагает вам руку и сердце!", $kb);
            return true;

        case 'divorce':
            $spouseId = (int)($ctx['global']['spouse_vk_id'] ?? 0);
            if ($spouseId <= 0) {
                sendMessage($peerId, "⚠️ Вы не состоите в браке.");
                return true;
            }
            $pdo->prepare("UPDATE bot_users SET spouse_vk_id = NULL, marriage_date = NULL WHERE vk_id IN (?, ?)")->execute([$fromId, $spouseId]);
            sendMessage($peerId, "💔 Ваш брак с " . formatUserMention($pdo, $peerId, $spouseId) . " официально расторгнут.");
            return true;

        case 'marriages':
            $page = max(1, (int)trim($args));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT u1.vk_id AS u1_id, u1.spouse_vk_id AS u2_id, u1.marriage_date
                                   FROM bot_users u1
                                   JOIN bot_chat_members m ON m.vk_id = u1.vk_id AND m.peer_id = ?
                                   WHERE u1.spouse_vk_id IS NOT NULL AND u1.vk_id < u1.spouse_vk_id
                                   ORDER BY u1.marriage_date DESC LIMIT ? OFFSET ?");
            $stmt->execute([$peerId, $limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "💍 В этой беседе нет активных браков (Страница {$page}).");
                return true;
            }
            $allIds = array_merge(array_column($rows, 'u1_id'), array_column($rows, 'u2_id'));
            preloadUsers($pdo, $peerId, $allIds);
            $lines = ["💍 Активные браки беседы (Страница {$page}):"];
            $i = $offset + 1;
            foreach ($rows as $r) {
                $p1 = formatUserMention($pdo, $peerId, (int)$r['u1_id']);
                $p2 = formatUserMention($pdo, $peerId, (int)$r['u2_id']);
                $dt = $r['marriage_date'] ? date('d.m.Y', strtotime($r['marriage_date'])) : '';
                $lines[] = "{$i}. {$p1} ❤️ {$p2} ({$dt})";
                $i++;
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!браки ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!браки ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
            return true;

        case 'kiss':
        case 'hug':
        case 'iznas':
        case 'minet':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите участника беседы (упоминанием или пересланным сообщением).");
                return true;
            }
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            $u2 = formatUserMention($pdo, $peerId, $targetId);
            $actions = [
                'kiss'  => "💋 {$u1} страстно поцеловал(а) {$u2}!",
                'hug'   => "🤗 {$u1} крепко обнял(а) {$u2}!",
                'iznas' => "🔞 {$u1} жёстко надругался(ась) над {$u2}!",
                'minet' => "🍓 {$u1} доставил(а) незабываемое удовольствие {$u2}!"
            ];
            sendMessage($peerId, $actions[$cmd]);
            return true;

        case 'beer':
            sendMessage($peerId, "🍺 " . formatUserMention($pdo, $peerId, $fromId) . " открыл(а) баночку холодного пенного и выпил(а) за здоровье чата!");
            return true;

        case 'try':
            if ($args === '') {
                sendMessage($peerId, "🎲 Укажите действие! Пример: !попытка взломать банк");
                return true;
            }
            $ok = (random_int(0, 1) === 1) ? "✅ Удачно" : "❌ Неудачно";
            sendMessage($peerId, "🎲 " . formatUserMention($pdo, $peerId, $fromId) . " попробовал(а) {$args} — [{$ok}]");
            return true;

        case 'roll':
            $max = ctype_digit(trim($args)) ? max(2, min(1000000, (int)trim($args))) : 100;
            sendMessage($peerId, "🎲 Случайное число (1–{$max}): " . random_int(1, $max));
            return true;

        case 'ball':
            if ($args === '') {
                sendMessage($peerId, "🔮 Задайте вопрос! Пример: !шар мне сегодня повезёт?");
                return true;
            }
            $ans = ['Бесспорно!', 'Предрешено!', 'Вероятнее всего.', 'Знаки говорят — да.', 'Спроси позже.', 'Даже не думай.', 'Мой ответ — нет.', 'Весьма сомнительно.'];
            sendMessage($peerId, "🔮 Магический шар говорит: " . $ans[array_rand($ans)]);
            return true;

        case 'choose':
            $parts = array_values(array_filter(array_map('trim', preg_split('/\s+или\s+|\s+or\s+|,/iu', $args))));
            if (count($parts) < 2) {
                sendMessage($peerId, "🤔 Пример: !выбери чай или кофе");
                return true;
            }
            sendMessage($peerId, "🤔 Я выбираю: " . $parts[array_rand($parts)]);
            return true;
    }

    return false;
}
