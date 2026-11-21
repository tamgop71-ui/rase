<?php
declare(strict_types=1);

/**
 * Обработчик общих, напоминаний, опросов, AFK, экономики, кейсов, промокодов, квестов и РП.
 */
function handleFunAndGeneralCommands(PDO $pdo, string $cmd, string $args, int $peerId, int $fromId, array $ctx, array $msg, int $myPriority, bool $isSuper): bool {
    switch ($cmd) {
        // ==========================================
        // 1. ОБЩИЕ КОМАНДЫ, НАПОМИНАЛКИ, AFK, ОПРОСЫ
        // ==========================================
        case 'ping':
            $totalMs = isset($GLOBALS['_req_start']) ? round((microtime(true) - $GLOBALS['_req_start']) * 1000, 1) : 1.0;
            sendMessage($peerId, "🏓 Понг! Бот RASE RUSSIA работает исправно.\n⚡ Скорость ответа: {$totalMs} мс\n🕒 Сервер: " . date('d.m.Y H:i:s') . " МСК");
            return true;

        case 'start':
            if ($myPriority >= 100 || $isSuper || (int)$ctx['chat']['owner_vk_id'] === 0) {
                executeCommand($pdo, 'setup', '', $peerId, $fromId, $ctx, $msg);
            } else {
                sendMessage($peerId, "👋 Бот уже работает в беседе! Напишите !помощь для списка команд.");
            }
            return true;

        case 'help':
            $cat = mb_strtolower(trim($args));
            $kb = makeInlineKeyboard([
                [
                    ['label' => '👤 Основное', 'payload' => ['cmd' => '!help base'], 'color' => 'primary'],
                    ['label' => '🎮 Игры и Кейсы', 'payload' => ['cmd' => '!help games'], 'color' => 'positive']
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
                $txt = "🎮 Игры, Экономика, Кейсы и РП (префиксы: ! / . + -):\n\n"
                     . "💰 Экономика и Квесты:\n"
                     . "• !профиль (!profile) | !бонус (!bonus) | !работа (!work)\n"
                     . "• !квесты (!quests) — ежедневные задания с наградами\n"
                     . "• !кейс [1/2/3] (!case) | !магазин (!shop) — кейсы и привилегии\n"
                     . "• !клан (!clan) / !кланы — семьи и общий общак\n"
                     . "• !промо [код] (!promo) — активировать промокод\n"
                     . "• !банк / !положить [сумма] / !снять [сумма]\n"
                     . "• !перевод [ID] [сумма] | !ограбить [ID] | !реп [ID]\n"
                     . "• !топ / !atop — рейтинги (баланс/сообщения/символы/стикеры)\n\n"
                     . "🎰 Азарт и Мини-игры:\n"
                     . "• !казино [ставка] | !слоты [ставка]\n"
                     . "• !рулетка [красное/черное/зеленое] [ставка]\n"
                     . "• !дуэль [ID] [ставка] | !монетка [орёл/решка] [ставка]\n"
                     . "• !рандом (!roll) | !шар | !выбери\n\n"
                     . "❤️ Социальное и РП:\n"
                     . "• !гражданство [страна] / !страна / !страны\n"
                     . "• !брак [ID] / !развод / !браки | !стикеры | !эмодзи\n"
                     . "• !поцелуй / !обнять / !попытка / !пиво / !iznas / !minet";
            } elseif ($cat === 'mod20') {
                $txt = "🛡 Команды Помощника (приоритет ≥ 20):\n\n"
                     . "• !стата (ID) / !чатинфо / !reg [ID] / !актив [дни] (KPI)\n"
                     . "• !мут [ID] [мин] (причина) / !унмут [ID]\n"
                     . "• !кик [ID] (причина) — исключить из чата\n"
                     . "• !пред (!варн) [ID] (причина) / !унварн [ID]\n"
                     . "• !предупреждения (!getwarn) / !getban / !getmute [ID]\n"
                     . "• !warnlist (!преды) / !муты (!mutelist) / !историяварнов [ID]\n"
                     . "• !ник / !сник / !рник / !нлист / !безников / !checknicks\n"
                     . "• !вызов (текст) | !заметка [ключ]";
            } elseif ($cat === 'mod40') {
                $txt = "⚔ Команды Модератора (приоритет ≥ 40):\n\n"
                     . "• !бан [ID] (дни) (причина) / !унбан [ID] / !banlist\n"
                     . "• !role [ID] [приоритет/название] / !removerole [ID]\n"
                     . "• !помощник [ID] / !роли\n"
                     . "• !тишина [время / выкл] | !слоумод [сек] — контроль чата\n"
                     . "• !очистить [ID / кол-во] — удалить последние сообщения\n"
                     . "• !понику [часть ника] — поиск по нику\n"
                     . "• !gzov / !gkick / !gsnick / !грник / !gremoverole";
            } elseif ($cat === 'adm') {
                $txt = "⚡ Администратор (60+) и Гл. Админ (80+):\n\n"
                     . "Приоритет ≥ 60:\n"
                     . "• !модер / !grole / !гпомощник / !гмодер [ID]\n"
                     . "• !gban / !gunban [ID] | !удалить (пересланное)\n"
                     . "• !gm [ID] / !removegm [ID] / !gms — иммунитет\n"
                     . "• !history [ID] / !reports / !getreport\n\n"
                     . "Приоритет ≥ 80:\n"
                     . "• !whitelist [+/- домен] — белый список ссылок автомода\n"
                     . "• !фильтр [+/- слово] — фильтр запрещённых слов\n"
                     . "• !триггер [+слово = ответ] — автоответчик в чате\n"
                     . "• !создатьпромо [КОД] [СУММА] [КОЛ-ВО] — промокоды\n"
                     . "• !pin / !unpin | !admin / !gadmin | !newrole / !delrole\n"
                     . "• !welcome / !setrules / !delrules / !заметки\n"
                     . "• !неактив [дни] (кик) | !givemoney | !timeuved";
            } elseif ($cat === 'owner') {
                $txt = "👑 Владелец беседы (100) и Супер-доступ (/addws):\n\n"
                     . "• !setup / !settings / !gsettings / !gedit\n"
                     . "• !антислив (!antidrain) | !антирейд (!antiraid) — защита чата\n"
                     . "• !automod / !антифлуд / !games / !уведы\n"
                     . "• !unity / !unites / !createunity / !addunity / !editunity / !removeunity / !deleteunity\n"
                     . "• !sync / !spec / !gspec / !wipe / !migration\n"
                     . "• !editcmd / !geditcmd / !owner [ID] / !никэмодзи\n"
                     . "• Супер-доступ: !addws, !vip, !setlog, !zovvv, !gzovg, !gtimeuvedg, !getdialog, !reportedit, !listunities, !listen, !chatlist, !userchats, !ahistory, !checkban";
            } else {
                $txt = "📋 Справка по командам RASE RUSSIA BOT:\n"
                     . "Префиксы: ! / . + - (на русском и английском)\n\n"
                     . "• !пинг / !статус — скорость отклика и статус бота\n"
                     . "• !напомни [время] [текст] — напоминалка (пример: !напомни 2ч обзвон)\n"
                     . "• !напомни лс [время] [текст] — личное напоминание в ЛС\n"
                     . "• !напоминания — список ваших активных напоминаний\n"
                     . "• !опрос [Вопрос | Вар 1 | Вар 2] — создать голосование\n"
                     . "• !афк (причина) — режим «отошёл»\n"
                     . "• !правила / !роли / !админы / !онлайн / !thereid\n"
                     . "• /report [текст] — отправить тикет администрации\n\n"
                     . "👇 Выберите раздел на кнопках ниже:";
            }
            sendMessage($peerId, $txt, $kb);
            return true;

        case 'remind':
            $raw = trim($args);
            if ($raw === '') {
                sendMessage($peerId, "⏰ Примеры напоминаний:\n• !напомни 2ч обзвон на лидерку (пришлёт в чат)\n• !напомни лс 30м проверить отчёты (пришлёт в ЛС)\n• !напомни 18:30 собрание администрации\n• !напомни 1ч30м закрыть заявки");
                return true;
            }
            $isDm = false;
            if (preg_match('/^(лс|dm|pm|мне)\s+(.+)$/isu', $raw, $m)) {
                $isDm = true;
                $raw = trim($m[2]);
            }
            if (!preg_match('/^(\S+)\s+(.+)$/isu', $raw, $m)) {
                sendMessage($peerId, "⚠️ Укажите время и текст напоминания! Пример: !напомни 2ч обзвон на лидерку");
                return true;
            }
            $timeToken = $m[1];
            $remText = mb_substr(trim($m[2]), 0, 500);
            $remindTs = parseReminderTime($timeToken);
            if ($remindTs === null) {
                sendMessage($peerId, "⚠️ Не удалось распознать время «{$timeToken}». Используйте формат: 10м, 2ч, 1ч30м, 1д или 18:30.");
                return true;
            }
            $targetPeer = $isDm ? $fromId : $peerId;
            $remindAt = date('Y-m-d H:i:s', $remindTs);
            $pdo->prepare("INSERT INTO bot_reminders (peer_id, user_vk_id, text, is_dm, remind_at, is_sent) VALUES (?, ?, ?, ?, ?, 0)")
                ->execute([$targetPeer, $fromId, $remText, $isDm ? 1 : 0, $remindAt]);
            $remId = (int)$pdo->lastInsertId();
            $where = $isDm ? "в личные сообщения" : "в этот чат";
            sendMessage($peerId, "⏰ Напоминание #{$remId} установлено!\n📅 Сработает: " . date('d.m.Y в H:i', $remindTs) . " МСК ({$where})\n📝 Текст: {$remText}");
            return true;

        case 'reminders':
            $sub = trim($args);
            if (preg_match('/^(del|удалить|отмена)\s+(\d+)$/iu', $sub, $m)) {
                $rid = (int)$m[2];
                $stmt = $pdo->prepare("DELETE FROM bot_reminders WHERE id = ? AND user_vk_id = ? AND is_sent = 0");
                $stmt->execute([$rid, $fromId]);
                sendMessage($peerId, $stmt->rowCount() > 0 ? "🗑 Напоминание #{$rid} отменено." : "❌ Активное напоминание #{$rid} не найдено.");
                return true;
            }
            $stmt = $pdo->prepare("SELECT * FROM bot_reminders WHERE user_vk_id = ? AND is_sent = 0 ORDER BY remind_at ASC LIMIT 15");
            $stmt->execute([$fromId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "⏰ У вас нет активных напоминаний. Создать: !напомни 2ч текст");
                return true;
            }
            $lines = ["⏰ Ваши активные напоминания:"];
            foreach ($rows as $r) {
                $dt = date('d.m H:i', strtotime($r['remind_at']));
                $wh = (int)$r['is_dm'] ? 'ЛС' : 'Чат';
                $lines[] = "• #{$r['id']} [{$dt} | {$wh}] — " . mb_substr($r['text'], 0, 60);
            }
            $lines[] = "\nОтменить: !напоминания del [ID]";
            sendMessage($peerId, implode("\n", $lines));
            return true;

        case 'afk':
            $reason = trim($args) !== '' ? mb_substr(trim($args), 0, 120) : 'Отошёл по делам';
            $pdo->prepare("UPDATE bot_users SET afk_reason = ?, afk_since = NOW() WHERE vk_id = ?")->execute([$reason, $fromId]);
            setAfkCache($fromId, ['r' => $reason, 't' => date('H:i')]);
            sendMessage($peerId, "💤 " . formatUserMention($pdo, $peerId, $fromId) . " перешёл(ла) в режим AFK.\n💬 Причина: {$reason}");
            return true;

        case 'poll':
            $raw = trim($args);
            // Голосование по нажатию инлайн-кнопки: !poll vote [pollId] [optIndex]
            if (preg_match('/^vote\s+(\d+)\s+(\d+)$/i', $raw, $m)) {
                $pollId = (int)$m[1];
                $optIdx = (int)$m[2];
                $stmtP = $pdo->prepare("SELECT * FROM bot_polls WHERE id = ? AND peer_id = ?");
                $stmtP->execute([$pollId, $peerId]);
                $poll = $stmtP->fetch();
                if (!$poll) {
                    sendMessage($peerId, "❌ Опрос #{$pollId} не найден.");
                    return true;
                }
                $opts = json_decode((string)$poll['options_json'], true) ?: [];
                if (!isset($opts[$optIdx])) return true;

                $pdo->prepare("REPLACE INTO bot_poll_votes (poll_id, vk_id, option_index) VALUES (?, ?, ?)")
                    ->execute([$pollId, $fromId, $optIdx]);

                // Подсчёт результатов
                $stmtV = $pdo->prepare("SELECT option_index, COUNT(*) AS cnt FROM bot_poll_votes WHERE poll_id = ? GROUP BY option_index");
                $stmtV->execute([$pollId]);
                $counts = [];
                $totalVotes = 0;
                while ($r = $stmtV->fetch()) {
                    $counts[(int)$r['option_index']] = (int)$r['cnt'];
                    $totalVotes += (int)$r['cnt'];
                }
                $lines = ["📊 Опрос #{$pollId}: {$poll['question']}"];
                $kbRows = [];
                foreach ($opts as $idx => $label) {
                    $c = $counts[$idx] ?? 0;
                    $pct = $totalVotes > 0 ? round(($c / $totalVotes) * 100) : 0;
                    $lines[] = ($idx + 1) . ". {$label} — {$c} ({$pct}%)";
                    $kbRows[] = [['label' => ($idx + 1) . ". {$label} ({$c})", 'payload' => ['cmd' => "!poll vote {$pollId} {$idx}"], 'color' => 'primary']];
                }
                $lines[] = "\n👥 Всего голосов: {$totalVotes}";
                sendMessage($peerId, implode("\n", $lines), makeInlineKeyboard($kbRows));
                return true;
            }

            // Создание нового опроса: !опрос Вопрос | Вар1 | Вар2
            $parts = array_values(array_filter(array_map('trim', explode('|', $raw))));
            if (count($parts) < 3) {
                sendMessage($peerId, "📊 Использование: !опрос Вопрос | Вариант 1 | Вариант 2 (до 5 вариантов)");
                return true;
            }
            $question = mb_substr($parts[0], 0, 200);
            $options = array_slice($parts, 1, 5);
            $pdo->prepare("INSERT INTO bot_polls (peer_id, author_vk_id, question, options_json) VALUES (?, ?, ?, ?)")
                ->execute([$peerId, $fromId, $question, json_encode($options, JSON_UNESCAPED_UNICODE)]);
            $pollId = (int)$pdo->lastInsertId();
            $lines = ["📊 Новый опрос #{$pollId}: {$question}"];
            $kbRows = [];
            foreach ($options as $idx => $label) {
                $lines[] = ($idx + 1) . ". {$label} — 0 (0%)";
                $kbRows[] = [['label' => ($idx + 1) . ". {$label}", 'payload' => ['cmd' => "!poll vote {$pollId} {$idx}"], 'color' => 'primary']];
            }
            sendMessage($peerId, implode("\n", $lines), makeInlineKeyboard($kbRows));
            return true;

        case 'thereid':
            sendMessage($peerId, "🆔 ID этой беседы: {$peerId}\n👤 Ваш VK ID: {$fromId}");
            return true;

        case 'rules':
            $rules = trim((string)($ctx['chat']['rules_text'] ?? ''));
            if ($rules === '') {
                $rules = "1. Уважайте других участников беседы.\n2. Запрещён спам, флуд и реклама.\n3. Соблюдайте правила проекта RASE RUSSIA.";
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
                $out[] = "• " . formatUserMention($pdo, $peerId, (int)$r['vk_id']) . " — {$rName} ({$r['role_priority']})";
            }
            sendMessage($peerId, implode("\n", $out), null, ['disable_mentions' => 1]);
            return true;

        case 'online':
            $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId, 'fields' => 'online,first_name,last_name']);
            $profiles = $res['response']['profiles'] ?? [];
            $onlineIds = [];
            foreach ($profiles as $p) {
                if (!empty($p['online'])) $onlineIds[] = (int)$p['id'];
            }
            if (empty($onlineIds)) {
                sendMessage($peerId, "🌐 Сейчас нет участников онлайн (либо у бота нет прав админа беседы).");
                return true;
            }
            $slice = array_slice($onlineIds, 0, 50);
            preloadUsers($pdo, $peerId, $slice);
            $onlineList = array_map(fn($uid) => "• " . formatUserMention($pdo, $peerId, $uid), $slice);
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
        // 2. ПРОФИЛЬ, ЭКОНОМИКА, КЕЙСЫ, ПРОМОКОДЫ, КВЕСТЫ, ИГРЫ
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
            $clanName = 'Нет (!кланы)';
            if (!empty($g['clan_id'])) {
                $stmtCl = $pdo->prepare("SELECT name FROM bot_clans WHERE id = ?");
                $stmtCl->execute([(int)$g['clan_id']]);
                if ($clF = $stmtCl->fetchColumn()) $clanName = $clF;
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
                 . "🏰 Семья/Клан: {$clanName}\n"
                 . "💍 Брак: {$marriageStr}\n"
                 . "✉️ Сообщений: {$m['messages_count']} (Всего: {$g['total_messages']})\n"
                 . "📅 В беседе с: {$regDate} МСК";
            sendMessage($peerId, $txt, null, ['disable_mentions' => 1]);
            return true;

        case 'quests':
            $dMsgs = (int)$ctx['global']['daily_msgs'];
            $dGames = (int)$ctx['global']['daily_games'];
            $claimed = (int)$ctx['global']['quest_claimed'];
            $q1Done = ($dMsgs >= 25);
            $q2Done = ($dGames >= 3);

            if (mb_strtolower(trim($args)) === 'claim') {
                if ($claimed === 1) {
                    sendMessage($peerId, "✅ Вы уже забрали награду за сегодняшние квесты!");
                    return true;
                }
                if (!$q1Done || !$q2Done) {
                    sendMessage($peerId, "⏳ Выполните оба задания, чтобы забрать награду!");
                    return true;
                }
                $reward = ((int)$ctx['global']['is_vip'] === 1) ? 7500 : 5000;
                $pdo->prepare("UPDATE bot_users SET balance = balance + ?, reputation = reputation + 2, quest_claimed = 1 WHERE vk_id = ?")
                    ->execute([$reward, $fromId]);
                sendMessage($peerId, "🎉 Поздравляем! Вы выполнили ежедневные квесты и получили " . formatMoney($reward) . " + 2 ⭐ репутации!");
                return true;
            }

            $s1 = $q1Done ? '✅' : '⏳';
            $s2 = $q2Done ? '✅' : '⏳';
            $reward = ((int)$ctx['global']['is_vip'] === 1) ? '7 500 ₽' : '5 000 ₽';
            $status = $claimed ? "\n\n🏆 Награда за сегодня уже получена!" : "\n\n🎁 Награда: {$reward} + 2 ⭐ репутации";
            $kb = (!$claimed && $q1Done && $q2Done)
                ? makeInlineKeyboard([[['label' => '🎁 Забрать награду', 'payload' => ['cmd' => '!quests claim'], 'color' => 'positive']]])
                : null;
            sendMessage($peerId, "📜 Ежедневные квесты:\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "{$s1} 1. Написать 25 сообщений за день: " . min(25, $dMsgs) . "/25\n"
                . "{$s2} 2. Сыграть 3 раза в мини-игры (казино/слоты/рулетка/кейс): " . min(3, $dGames) . "/3{$status}", $kb);
            return true;

        case 'case':
            $type = mb_strtolower(trim($args));
            $cases = [
                '1' => ['name' => 'Обычный кейс',  'price' => 2500,  'min' => 500,   'max' => 8000,   'vipChance' => 1],
                '2' => ['name' => 'Премиум кейс',  'price' => 10000, 'min' => 2500,  'max' => 35000,  'vipChance' => 3],
                '3' => ['name' => 'Элитный кейс',  'price' => 50000, 'min' => 15000, 'max' => 200000, 'vipChance' => 8],
            ];
            if (!isset($cases[$type])) {
                $kb = makeInlineKeyboard([[
                    ['label' => '📦 Обычный (2.5к)', 'payload' => ['cmd' => '!кейс 1'], 'color' => 'secondary'],
                    ['label' => '💼 Премиум (10к)', 'payload' => ['cmd' => '!кейс 2'], 'color' => 'primary'],
                    ['label' => '👑 Элитный (50к)', 'payload' => ['cmd' => '!кейс 3'], 'color' => 'positive']
                ]]);
                sendMessage($peerId, "🎁 Магазин кейсов RASE RUSSIA:\n1. 📦 Обычный — 2 500 ₽ (выигрыш до 8 000 ₽)\n2. 💼 Премиум — 10 000 ₽ (выигрыш до 35 000 ₽ + шанс на VIP)\n3. 👑 Элитный — 50 000 ₽ (выигрыш до 200 000 ₽ + шанс на VIP)\n\nНажмите на кнопку или введите !кейс [1/2/3]:", $kb);
                return true;
            }
            $c = $cases[$type];
            $bal = (int)$ctx['global']['balance'];
            if ($bal < $c['price']) {
                sendMessage($peerId, "❌ Недостаточно средств для «{$c['name']}». Нужно: " . formatMoney($c['price']) . ", у вас: " . formatMoney($bal));
                return true;
            }
            // Розыгрыш награды
            $roll = random_int(1, 100);
            if ($roll <= $c['vipChance'] && (int)$ctx['global']['is_vip'] === 0) {
                $pdo->prepare("UPDATE bot_users SET balance = balance - ?, is_vip = 1, daily_games = daily_games + 1 WHERE vk_id = ?")
                    ->execute([$c['price'], $fromId]);
                sendMessage($peerId, "🎁 Вы открыли «{$c['name']}»...\n💎 НЕВЕРОЯТНАЯ УДАЧА! Вам выпал постоянный VIP-статус!");
                return true;
            }
            $prize = ($roll >= 92) ? $c['max'] : random_int($c['min'], (int)round($c['price'] * 1.45));
            $repBonus = ($roll >= 75) ? random_int(1, 3) : 0;
            $newBal = $bal - $c['price'] + $prize;
            $pdo->prepare("UPDATE bot_users SET balance = ?, reputation = reputation + ?, daily_games = daily_games + 1 WHERE vk_id = ?")
                ->execute([$newBal, $repBonus, $fromId]);
            $repStr = $repBonus > 0 ? " + {$repBonus} ⭐ репутации" : "";
            sendMessage($peerId, "🎁 Вы открыли «{$c['name']}»!\n🎉 Выпало: " . formatMoney($prize) . "{$repStr}\n💰 Ваш баланс: " . formatMoney($newBal));
            return true;

        case 'promo':
            $code = mb_strtoupper(trim($args));
            if ($code === '') {
                sendMessage($peerId, "🎟 Введите промокод: !промо [КОД]");
                return true;
            }
            $stmt = $pdo->prepare("SELECT * FROM bot_promocodes WHERE code = ? AND uses_left > 0");
            $stmt->execute([$code]);
            $promo = $stmt->fetch();
            if (!$promo) {
                sendMessage($peerId, "❌ Промокод «{$code}» не существует или уже закончился.");
                return true;
            }
            $stmtUsed = $pdo->prepare("SELECT 1 FROM bot_promo_uses WHERE promo_id = ? AND vk_id = ?");
            $stmtUsed->execute([(int)$promo['id'], $fromId]);
            if ($stmtUsed->fetchColumn()) {
                sendMessage($peerId, "⚠️ Вы уже активировали промокод «{$code}».");
                return true;
            }
            $pdo->prepare("INSERT INTO bot_promo_uses (promo_id, vk_id) VALUES (?, ?)")->execute([(int)$promo['id'], $fromId]);
            $pdo->prepare("UPDATE bot_promocodes SET uses_left = uses_left - 1 WHERE id = ?")->execute([(int)$promo['id']]);
            $reward = (int)$promo['reward'];
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$reward, $fromId]);
            sendMessage($peerId, "🎉 Промокод «{$code}» активирован!\n💰 На ваш баланс начислено: " . formatMoney($reward) . "!");
            return true;

        case 'bonus':
            $lastBonus = $ctx['global']['last_bonus'] ?? null;
            if ($lastBonus !== null && (time() - strtotime($lastBonus)) < 86400) {
                $rem = 86400 - (time() - strtotime($lastBonus));
                $h = (int)floor($rem / 3600); $min = (int)floor(($rem % 3600) / 60);
                sendMessage($peerId, "⏳ Вы уже забирали бонус. Следующий через {$h} ч. {$min} мин.");
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
                $min = (int)ceil(($cd - (time() - strtotime($lastWork))) / 60);
                sendMessage($peerId, "⏳ Вы устали! Следующая смена через {$min} мин.");
                return true;
            }
            $earn = random_int(350, 1200);
            $jobs = [
                "отработали рейс дальнобойщиком в RASE RUSSIA",
                "провели ночную смену в ППС",
                "починили спорткар в тюнинг-ателье",
                "доставили материалы на склад фракции",
                "провели успешный эфир в СМИ"
            ];
            $pdo->prepare("UPDATE bot_users SET balance = balance + ?, last_work = NOW() WHERE vk_id = ?")->execute([$earn, $fromId]);
            sendMessage($peerId, "💼 Вы " . $jobs[array_rand($jobs)] . " и заработали " . formatMoney($earn) . "!");
            return true;

        case 'bank':
            sendMessage($peerId, "🏦 Банковский счёт:\n• Наличные: " . formatMoney((int)$ctx['global']['balance']) . "\n• В банке (защищены от ограблений): " . formatMoney((int)$ctx['global']['bank_balance']) . "\n\nКоманды: !положить [сумма] | !снять [сумма]");
            return true;

        case 'deposit':
            $bal = (int)$ctx['global']['balance'];
            $amt = parseAmount($args, $bal);
            if ($amt === null || $amt <= 0 || $amt > $bal) {
                sendMessage($peerId, "⚠️ Укажите корректную сумму (на руках: " . formatMoney($bal) . ").");
                return true;
            }
            $pdo->prepare("UPDATE bot_users SET balance = balance - ?, bank_balance = bank_balance + ? WHERE vk_id = ?")->execute([$amt, $amt, $fromId]);
            sendMessage($peerId, "🏦 Вы положили в банк " . formatMoney($amt) . ". В банке: " . formatMoney((int)$ctx['global']['bank_balance'] + $amt));
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
            if (!$targetId || $targetId === $fromId) { sendMessage($peerId, "🥷 Использование: !ограбить [ID/упоминание]"); return true; }
            $lastRob = $ctx['global']['last_rob'] ?? null;
            if ($lastRob !== null && (time() - strtotime($lastRob)) < 1800) {
                $rem = (int)ceil((1800 - (time() - strtotime($lastRob))) / 60);
                sendMessage($peerId, "🚔 Полиция начеку! Повторить через {$rem} мин.");
                return true;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $shieldUntil = $tCtx['global']['rob_shield_until'] ?? null;
            if ($shieldUntil && strtotime($shieldUntil) > time()) {
                sendMessage($peerId, "🛡 У этого пользователя активен щит от ограблений до " . date('d.m H:i', strtotime($shieldUntil)) . " МСК!");
                return true;
            }
            $tBal = (int)$tCtx['global']['balance'];
            if ($tBal < 500) { sendMessage($peerId, "🥷 У цели на руках меньше 500 ₽."); return true; }
            $pdo->prepare("UPDATE bot_users SET last_rob = NOW() WHERE vk_id = ?")->execute([$fromId]);
            $uTarget = formatUserMention($pdo, $peerId, $targetId);
            if (random_int(1, 100) <= 45) {
                $stolen = min((int)round($tBal * (random_int(10, 25) / 100)), 50000);
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$stolen, $targetId]);
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$stolen, $fromId]);
                sendMessage($peerId, "🥷 Успешное ограбление! Вы вытащили у {$uTarget} " . formatMoney($stolen) . "!");
            } else {
                $fine = min((int)$ctx['global']['balance'], random_int(300, 1200));
                $pdo->prepare("UPDATE bot_users SET balance = GREATEST(0, balance - ?) WHERE vk_id = ?")->execute([$fine, $fromId]);
                sendMessage($peerId, "🚔 Вас поймали при попытке ограбить {$uTarget}! Штраф: " . formatMoney($fine) . ".");
            }
            return true;

        case 'casino':
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal);
            if ($bet === null || $bet <= 0) { sendMessage($peerId, "🎰 Примеры: !казино 500, !казино 5к, !казино все"); return true; }
            if ($bet > $bal) { sendMessage($peerId, "Недостаточно средств. Ваш баланс: " . formatMoney($bal) . "."); return true; }
            $multipliers = [0.0, 0.1, 0.25, 0.5, 0.5, 0.75, 1.0, 1.25, 1.5, 2.0, 2.5, 5.0];
            $mult = $multipliers[array_rand($multipliers)];
            $win = (int)round($bet * $mult);
            $diff = $win - $bet;
            $newBal = $bal + $diff;
            $pdo->prepare("UPDATE bot_users SET balance = ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$newBal, $fromId]);
            if ($mult >= 1.0) {
                sendMessage($peerId, "🎰 Множитель {$mult}×!\n✅ Выигрыш: " . formatMoney($win) . " (чистыми +" . formatMoney($diff) . ")\n💰 Баланс: " . formatMoney($newBal));
            } else {
                sendMessage($peerId, "🎰 Множитель {$mult}×.\n❌ Проигрыш: " . formatMoney(abs($diff)) . "\n💰 Баланс: " . formatMoney($newBal));
            }
            return true;

        case 'slots':
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal);
            if ($bet === null || $bet <= 0 || $bet > $bal) { sendMessage($peerId, "🎰 Использование: !слоты [ставка] (баланс: " . formatMoney($bal) . ")"); return true; }
            $icons = ['🍒', '🍋', '🍇', '🔔', '💎', '7️⃣'];
            $r1 = $icons[array_rand($icons)]; $r2 = $icons[array_rand($icons)]; $r3 = $icons[array_rand($icons)];
            if ($r1 === $r2 && $r2 === $r3) {
                $mult = ($r1 === '7️⃣') ? 10 : (($r1 === '💎') ? 5 : 3);
                $win = $bet * $mult; $newBal = $bal + $win - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎰 [ {$r1} | {$r2} | {$r3} ]\n🔥 ДЖЕКПОТ x{$mult}! Выигрыш: " . formatMoney($win) . "\n💰 Баланс: " . formatMoney($newBal));
            } elseif ($r1 === $r2 || $r2 === $r3 || $r1 === $r3) {
                $win = (int)round($bet * 1.3); $newBal = $bal + $win - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎰 [ {$r1} | {$r2} | {$r3} ]\n✅ Пара (x1.3)! Выигрыш: " . formatMoney($win) . "\n💰 Баланс: " . formatMoney($newBal));
            } else {
                $newBal = $bal - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎰 [ {$r1} | {$r2} | {$r3} ]\n❌ Проигрыш: " . formatMoney($bet) . "\n💰 Баланс: " . formatMoney($newBal));
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
            if ($bet === null || $bet <= 0 || $bet > $bal) { sendMessage($peerId, "Недостаточно средств. Баланс: " . formatMoney($bal)); return true; }
            $choice = in_array($colorRaw, ['черное', 'чёрное', 'black'], true) ? 'black' : (in_array($colorRaw, ['зеленое', 'зелёное', 'green'], true) ? 'green' : 'red');
            $num = random_int(0, 36);
            $resColor = ($num === 0) ? 'green' : (($num % 2 === 0) ? 'black' : 'red');
            $cEmoji = ($resColor === 'green') ? '🟢 Зелёное (0)' : (($resColor === 'red') ? "🔴 Красное ({$num})" : "⚫ Чёрное ({$num})");
            if ($choice === $resColor) {
                $mult = ($resColor === 'green') ? 14 : 2;
                $win = $bet * $mult; $newBal = $bal + $win - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎡 Выпало: {$cEmoji}\n✅ Победа (x{$mult})! Выигрыш: " . formatMoney($win) . "\n💰 Баланс: " . formatMoney($newBal));
            } else {
                $newBal = $bal - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🎡 Выпало: {$cEmoji}\n❌ Проигрыш: " . formatMoney($bet) . "\n💰 Баланс: " . formatMoney($newBal));
            }
            return true;

        case 'pay':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) { sendMessage($peerId, "⚠️ Использование: !перевод [ID/упоминание] [сумма]"); return true; }
            $bal = (int)$ctx['global']['balance'];
            $amount = parseAmount($args, $bal);
            if ($amount === null || $amount <= 0) { sendMessage($peerId, "⚠️ Укажите корректную сумму (500, 5к, 1кк)."); return true; }
            if ($amount > $bal) { sendMessage($peerId, "Недостаточно средств. Баланс: " . formatMoney($bal) . "."); return true; }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$amount, $fromId]);
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$amount, $targetId]);
            sendMessage($peerId, "💸 Перевод выполнен: " . formatMoney($amount) . " отправлено " . formatUserMention($pdo, $peerId, $targetId) . ".");
            return true;

        case 'duel':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) { sendMessage($peerId, "🔫 Использование: !дуэль [ID/упоминание] [ставка]"); return true; }
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal) ?? 500;
            if ($bet <= 0 || $bet > $bal) { sendMessage($peerId, "Недостаточно средств. Баланс: " . formatMoney($bal)); return true; }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            if ((int)$tCtx['global']['balance'] < $bet) { sendMessage($peerId, "У соперника недостаточно наличных."); return true; }
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            $u2 = formatUserMention($pdo, $peerId, $targetId);
            if (random_int(0, 1) === 1) {
                $pdo->prepare("UPDATE bot_users SET balance = balance + ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$bet, $fromId]);
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$bet, $targetId]);
                sendMessage($peerId, "🔫 Дуэль {$u1} vs {$u2}!\n🏆 Победил {$u1} и забрал банк " . formatMoney($bet * 2) . "!");
            } else {
                $pdo->prepare("UPDATE bot_users SET balance = balance - ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$bet, $fromId]);
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
                    $title = "🌍 Глобальный топ богачей:"; $unit = "₽";
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
                $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['vk_id']) . " — " . number_format((int)$r['val'], 0, '.', ' ') . " {$unit}";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
            return true;

        case 'rep':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) { sendMessage($peerId, "⭐ Использование: !реп @упоминание"); return true; }
            $repFile = "/tmp/rep_{$fromId}.txt";
            if (is_file($repFile) && (time() - (int)@file_get_contents($repFile)) < 3600) {
                $remM = (int)ceil((3600 - (time() - (int)@file_get_contents($repFile))) / 60);
                sendMessage($peerId, "⏳ Повышать репутацию можно раз в час. Подождите ещё {$remM} мин.");
                return true;
            }
            @file_put_contents($repFile, (string)time());
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_users SET reputation = reputation + 1 WHERE vk_id = ?")->execute([$targetId]);
            sendMessage($peerId, "⭐ " . formatUserMention($pdo, $peerId, $fromId) . " повысил(а) репутацию " . formatUserMention($pdo, $peerId, $targetId) . " (+1)!");
            return true;

        case 'stickers':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ($targetId === $fromId) ? $ctx : ensureUserAndMember($pdo, $peerId, $targetId);
            sendMessage($peerId, "🎭 Стикеры " . formatUserMention($pdo, $peerId, $targetId) . ":\n• В беседе: {$tCtx['member']['stickers_count']} шт.\n• Всего: {$tCtx['global']['total_stickers']} шт.");
            return true;

        case 'emoji':
            if ((int)$ctx['global']['is_vip'] === 0 && !$isSuper && $myPriority < 80) {
                sendMessage($peerId, "💎 Установка эмодзи в никнейм доступна VIP и Главным администраторам.");
                return true;
            }
            $e = mb_substr(trim($args), 0, 8);
            $pdo->prepare("UPDATE bot_chat_members SET emoji = ? WHERE peer_id = ? AND vk_id = ?")->execute([$e !== '' ? $e : null, $peerId, $fromId]);
            unset($GLOBALS['_mention_cache']["{$peerId}:{$fromId}"]);
            sendMessage($peerId, $e !== '' ? "🌟 Ваш эмодзи установлен: {$e}" : "🗑 Эмодзи удалён.");
            return true;

        case 'enick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $e = mb_substr(trim($args), 0, 8);
            if (!$targetId) { sendMessage($peerId, "⚠️ Использование: !никэмодзи [ID/упоминание] [смайл]"); return true; }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_chat_members SET emoji = ? WHERE peer_id = ? AND vk_id = ?")->execute([$e !== '' ? $e : null, $peerId, $targetId]);
            unset($GLOBALS['_mention_cache']["{$peerId}:{$targetId}"]);
            sendMessage($peerId, "✅ Эмодзи обновлён: " . formatUserMention($pdo, $peerId, $targetId));
            return true;

        // ==========================================
        // 3. СТРАНЫ, БРАКИ, РП И УТИЛИТЫ
        // ==========================================
        case 'citizenship':
            $cInput = trim($args);
            if ($cInput === '') { sendMessage($peerId, "🏳 Использование: !гражданство [ID/название]"); return true; }
            if (ctype_digit($cInput)) {
                $stmt = $pdo->prepare("SELECT * FROM bot_countries WHERE id = ?"); $stmt->execute([(int)$cInput]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM bot_countries WHERE LOWER(name) = LOWER(?)"); $stmt->execute([$cInput]);
            }
            $country = $stmt->fetch();
            if (!$country) {
                $cleanName = mb_substr($cInput, 0, 40);
                $pdo->prepare("INSERT INTO bot_countries (name, president_vk_id) VALUES (?, ?)")->execute([$cleanName, $fromId]);
                $cid = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE bot_users SET country_id = ? WHERE vk_id = ?")->execute([$cid, $fromId]);
                sendMessage($peerId, "🏛 Вы основали государство «{$cleanName}» (ID: {$cid})!");
            } else {
                $pdo->prepare("UPDATE bot_users SET country_id = ? WHERE vk_id = ?")->execute([(int)$country['id'], $fromId]);
                sendMessage($peerId, "🏳 Вы получили гражданство страны «{$country['name']}»!");
            }
            return true;

        case 'mycountry':
            $cid = (int)($ctx['global']['country_id'] ?? 0);
            if ($cid <= 0) { sendMessage($peerId, "🏳 У вас нет гражданства (!страны)."); return true; }
            $stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM bot_users u WHERE u.country_id = c.id) AS pop FROM bot_countries c WHERE c.id = ?");
            $stmt->execute([$cid]);
            $c = $stmt->fetch();
            sendMessage($peerId, $c ? "🏳 Ваше государство: {$c['name']} (ID: {$c['id']})\n👥 Граждан: {$c['pop']} чел." : "🏳 Не найдено.");
            return true;

        case 'countries':
            $page = max(1, (int)trim($args)); $limit = 10; $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT c.id, c.name, COUNT(u.vk_id) AS pop FROM bot_countries c LEFT JOIN bot_users u ON u.country_id = c.id GROUP BY c.id ORDER BY pop DESC, c.id ASC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "🏳 Стран нет (Страница {$page})."); return true; }
            $lines = ["🌍 Государства (Страница {$page}):"];
            foreach ($rows as $r) $lines[] = "• [{$r['id']}] {$r['name']} — {$r['pop']} гражд.";
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!страны ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!страны ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb);
            return true;

        case 'marry':
            $sub = mb_strtolower(trim($args));
            if (preg_match('/^(accept|decline)\s+(\d+)$/', $sub, $m)) {
                $act = $m[1]; $proposerId = (int)$m[2];
                $stmtP = $pdo->prepare("SELECT * FROM bot_marriage_proposals WHERE from_vk_id = ? AND to_vk_id = ?");
                $stmtP->execute([$proposerId, $fromId]);
                if (!$stmtP->fetch()) { sendMessage($peerId, "⚠️ Предложение не найдено."); return true; }
                $pdo->prepare("DELETE FROM bot_marriage_proposals WHERE from_vk_id IN (?, ?) OR to_vk_id IN (?, ?)")->execute([$fromId, $proposerId, $fromId, $proposerId]);
                $u1 = formatUserMention($pdo, $peerId, $proposerId); $u2 = formatUserMention($pdo, $peerId, $fromId);
                if ($act === 'accept') {
                    $pdo->prepare("UPDATE bot_users SET spouse_vk_id = ?, marriage_date = NOW() WHERE vk_id = ?")->execute([$proposerId, $fromId]);
                    $pdo->prepare("UPDATE bot_users SET spouse_vk_id = ?, marriage_date = NOW() WHERE vk_id = ?")->execute([$fromId, $proposerId]);
                    sendMessage($peerId, "💍 Горько! {$u1} и {$u2} вступили в брак!");
                } else {
                    sendMessage($peerId, "💔 {$u2} отклонил(а) предложение от {$u1}.");
                }
                return true;
            }
            if (!empty($ctx['global']['spouse_vk_id'])) { sendMessage($peerId, "💍 Вы уже в браке (!развод)."); return true; }
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) { sendMessage($peerId, "💍 Использование: !брак [ID/упоминание]"); return true; }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            if (!empty($tCtx['global']['spouse_vk_id'])) { sendMessage($peerId, "💔 Пользователь уже в браке."); return true; }
            $pdo->prepare("REPLACE INTO bot_marriage_proposals (peer_id, from_vk_id, to_vk_id) VALUES (?, ?, ?)")->execute([$peerId, $fromId, $targetId]);
            $kb = makeInlineKeyboard([[
                ['label' => '💍 Принять', 'payload' => ['cmd' => "!marry accept {$fromId}"], 'color' => 'positive'],
                ['label' => '💔 Отклонить', 'payload' => ['cmd' => "!marry decline {$fromId}"], 'color' => 'negative']
            ]]);
            sendMessage($peerId, "💐 " . formatUserMention($pdo, $peerId, $targetId) . ", пользователь " . formatUserMention($pdo, $peerId, $fromId) . " предлагает вам руку и сердце!", $kb);
            return true;

        case 'divorce':
            $spouseId = (int)($ctx['global']['spouse_vk_id'] ?? 0);
            if ($spouseId <= 0) { sendMessage($peerId, "⚠️ Вы не состоите в браке."); return true; }
            $pdo->prepare("UPDATE bot_users SET spouse_vk_id = NULL, marriage_date = NULL WHERE vk_id IN (?, ?)")->execute([$fromId, $spouseId]);
            sendMessage($peerId, "💔 Ваш брак с " . formatUserMention($pdo, $peerId, $spouseId) . " расторгнут.");
            return true;

        case 'marriages':
            $page = max(1, (int)trim($args)); $limit = 10; $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT u1.vk_id AS u1_id, u1.spouse_vk_id AS u2_id, u1.marriage_date FROM bot_users u1 JOIN bot_chat_members m ON m.vk_id = u1.vk_id AND m.peer_id = ? WHERE u1.spouse_vk_id IS NOT NULL AND u1.vk_id < u1.spouse_vk_id ORDER BY u1.marriage_date DESC LIMIT ? OFFSET ?");
            $stmt->execute([$peerId, $limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "💍 Активных браков нет (Страница {$page})."); return true; }
            preloadUsers($pdo, $peerId, array_merge(array_column($rows, 'u1_id'), array_column($rows, 'u2_id')));
            $lines = ["💍 Активные браки (Страница {$page}):"];
            $i = $offset + 1;
            foreach ($rows as $r) {
                $dt = $r['marriage_date'] ? date('d.m.Y', strtotime($r['marriage_date'])) : '';
                $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['u1_id']) . " ❤️ " . formatUserMention($pdo, $peerId, (int)$r['u2_id']) . " ({$dt})";
                $i++;
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!браки ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!браки ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
            return true;

        case 'kiss': case 'hug': case 'iznas': case 'minet':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Укажите участника беседы."); return true; }
            $u1 = formatUserMention($pdo, $peerId, $fromId); $u2 = formatUserMention($pdo, $peerId, $targetId);
            $actions = [
                'kiss'  => "💋 {$u1} страстно поцеловал(а) {$u2}!",
                'hug'   => "🤗 {$u1} крепко обнял(а) {$u2}!",
                'iznas' => "🔞 {$u1} жёстко надругался(ась) над {$u2}!",
                'minet' => "🍓 {$u1} доставил(а) незабываемое удовольствие {$u2}!"
            ];
            sendMessage($peerId, $actions[$cmd]);
            return true;

        case 'beer':
            sendMessage($peerId, "🍺 " . formatUserMention($pdo, $peerId, $fromId) . " открыл(а) баночку холодного пенного за здоровье чата!");
            return true;

        case 'try':
            if ($args === '') { sendMessage($peerId, "🎲 Пример: !попытка взломать сейф"); return true; }
            $ok = (random_int(0, 1) === 1) ? "✅ Удачно" : "❌ Неудачно";
            sendMessage($peerId, "🎲 " . formatUserMention($pdo, $peerId, $fromId) . " попробовал(а) {$args} — [{$ok}]");
            return true;

        case 'roll':
            $max = ctype_digit(trim($args)) ? max(2, min(1000000, (int)trim($args))) : 100;
            sendMessage($peerId, "🎲 Случайное число (1–{$max}): " . random_int(1, $max));
            return true;

        case 'ball':
            if ($args === '') { sendMessage($peerId, "🔮 Пример: !шар мне повезёт?"); return true; }
            $ans = ['Бесспорно!', 'Предрешено!', 'Вероятнее всего.', 'Знаки говорят — да.', 'Спроси позже.', 'Даже не думай.', 'Мой ответ — нет.', 'Весьма сомнительно.'];
            sendMessage($peerId, "🔮 Магический шар говорит: " . $ans[array_rand($ans)]);
            return true;

        case 'choose':
            $parts = array_values(array_filter(array_map('trim', preg_split('/\s+или\s+|\s+or\s+|,/iu', $args))));
            if (count($parts) < 2) { sendMessage($peerId, "🤔 Пример: !выбери чай или кофе"); return true; }
            sendMessage($peerId, "🤔 Я выбираю: " . $parts[array_rand($parts)]);
            return true;

        case 'shop':
            $item = mb_strtolower(trim($args));
            $bal = (int)$ctx['global']['balance'];
            if ($item === '1' || $item === 'shield' || $item === 'щит') {
                if ($bal < 15000) { sendMessage($peerId, "❌ Нужно 15 000 ₽. Ваш баланс: " . formatMoney($bal)); return true; }
                $pdo->prepare("UPDATE bot_users SET balance = balance - 15000, rob_shield_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE vk_id = ?")->execute([$fromId]);
                sendMessage($peerId, "🛡 Вы купили «Щит от ограблений» на 24 часа! Ваши наличные в безопасности.");
                return true;
            }
            if ($item === '2' || $item === 'vip') {
                if ((int)$ctx['global']['is_vip'] === 1) { sendMessage($peerId, "💎 У вас уже есть постоянный VIP-статус!"); return true; }
                if ($bal < 250000) { sendMessage($peerId, "❌ Нужно 250 000 ₽. Ваш баланс: " . formatMoney($bal)); return true; }
                $pdo->prepare("UPDATE bot_users SET balance = balance - 250000, is_vip = 1 WHERE vk_id = ?")->execute([$fromId]);
                sendMessage($peerId, "💎 Поздравляем! Вы приобрели постоянный VIP-статус (бонус x2.5, КД работы x0.5, эмодзи в никнейм)!");
                return true;
            }
            if ($item === '3' || $item === 'unwarn' || $item === 'варн') {
                $stmtW = $pdo->prepare("SELECT id FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1 ORDER BY id DESC LIMIT 1");
                $stmtW->execute([$peerId, $fromId]);
                $wid = $stmtW->fetchColumn();
                if (!$wid) { sendMessage($peerId, "✅ У вас нет активных предупреждений в этой беседе."); return true; }
                if ($bal < 50000) { sendMessage($peerId, "❌ Нужно 50 000 ₽. Ваш баланс: " . formatMoney($bal)); return true; }
                $pdo->prepare("UPDATE bot_users SET balance = balance - 50000 WHERE vk_id = ?")->execute([$fromId]);
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE id = ?")->execute([$wid]);
                sendMessage($peerId, "🧹 Вы выкупили и сняли 1 активное предупреждение за 50 000 ₽!");
                return true;
            }
            $kb = makeInlineKeyboard([
                [
                    ['label' => '🛡 Щит 24ч (15к)', 'payload' => ['cmd' => '!магазин 1'], 'color' => 'primary'],
                    ['label' => '💎 VIP (250к)', 'payload' => ['cmd' => '!магазин 2'], 'color' => 'positive']
                ],
                [
                    ['label' => '🧹 Снять варн (50к)', 'payload' => ['cmd' => '!магазин 3'], 'color' => 'secondary'],
                    ['label' => '🎁 Кейсы', 'payload' => ['cmd' => '!кейс'], 'color' => 'secondary']
                ]
            ]);
            sendMessage($peerId, "🛒 Магазин привилегий RASE RUSSIA (Ваш баланс: " . formatMoney($bal) . "):\n"
                . "1. 🛡 Щит от ограблений на 24ч — 15 000 ₽\n"
                . "2. 💎 Постоянный VIP-статус — 250 000 ₽\n"
                . "3. 🧹 Снять 1 активный варн — 50 000 ₽\n\n"
                . "Нажмите на кнопку или введите !магазин [номер]:", $kb);
            return true;

        case 'coin':
            if (!preg_match('/^(ор[её]л|решка|heads|tails)\s+(\S+)$/iu', trim($args), $m)) {
                sendMessage($peerId, "🪙 Использование: !монетка [орёл/решка] [ставка]");
                return true;
            }
            $pick = preg_match('/^(ор[её]л|heads)$/iu', $m[1]) ? 'heads' : 'tails';
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($m[2], $bal);
            if ($bet === null || $bet <= 0 || $bet > $bal) {
                sendMessage($peerId, "Недостаточно средств. Ваш баланс: " . formatMoney($bal));
                return true;
            }
            $drop = (random_int(0, 1) === 1) ? 'heads' : 'tails';
            $dropRu = ($drop === 'heads') ? '🦅 Орёл' : '🪙 Решка';
            if ($pick === $drop) {
                $newBal = $bal + $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🪙 Выпало: {$dropRu}!\n✅ Победа (x2)! Выигрыш: +" . formatMoney($bet) . "\n💰 Баланс: " . formatMoney($newBal));
            } else {
                $newBal = $bal - $bet;
                $pdo->prepare("UPDATE bot_users SET balance = ?, daily_games = daily_games + 1 WHERE vk_id = ?")->execute([$newBal, $fromId]);
                sendMessage($peerId, "🪙 Выпало: {$dropRu}.\n❌ Проигрыш: " . formatMoney($bet) . "\n💰 Баланс: " . formatMoney($newBal));
            }
            return true;

        case 'clan':
            $raw = trim($args);
            $myClanId = (int)($ctx['global']['clan_id'] ?? 0);

            if ($raw === '') {
                if ($myClanId <= 0) {
                    sendMessage($peerId, "🏰 Вы не состоите в семье/клане.\n• Создать (10 000 ₽): !клан создать [название]\n• Вступить: !клан вступить [ID]\n• Топ семей: !кланы");
                    return true;
                }
                $stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM bot_users u WHERE u.clan_id = c.id) AS mems FROM bot_clans c WHERE c.id = ?");
                $stmt->execute([$myClanId]);
                $cl = $stmt->fetch();
                if (!$cl) { sendMessage($peerId, "🏰 Клан не найден."); return true; }
                $ldr = formatUserMention($pdo, $peerId, (int)$cl['leader_vk_id']);
                sendMessage($peerId, "🏰 Семья «{$cl['name']}» (ID: #{$cl['id']})\n"
                    . "👑 Глава: {$ldr}\n"
                    . "👥 Участников: {$cl['mems']}\n"
                    . "💰 Общак: " . formatMoney((int)$cl['treasury']) . "\n\n"
                    . "Команды: !клан положить [сумма] | !клан состав | !клан выйти", null, ['disable_mentions' => 1]);
                return true;
            }

            if (preg_match('/^(создать|create)\s+(.+)$/isu', $raw, $m)) {
                if ($myClanId > 0) { sendMessage($peerId, "⚠️ Сначала выйдите из текущего клана (!клан выйти)."); return true; }
                $bal = (int)$ctx['global']['balance'];
                if ($bal < 10000) { sendMessage($peerId, "❌ Создание семьи стоит 10 000 ₽. У вас: " . formatMoney($bal)); return true; }
                $cName = mb_substr(trim($m[2]), 0, 40);
                try {
                    $pdo->prepare("INSERT INTO bot_clans (name, leader_vk_id, treasury) VALUES (?, ?, 0)")->execute([$cName, $fromId]);
                    $cid = (int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE bot_users SET balance = balance - 10000, clan_id = ? WHERE vk_id = ?")->execute([$cid, $fromId]);
                    sendMessage($peerId, "🏰 Семья «{$cName}» (ID: #{$cid}) успешно создана! Приглашайте друзей: !клан вступить {$cid}");
                } catch (PDOException $e) {
                    sendMessage($peerId, "❌ Семья с таким названием уже существует.");
                }
                return true;
            }

            if (preg_match('/^(вступить|join)\s+(\d+)$/iu', $raw, $m)) {
                if ($myClanId > 0) { sendMessage($peerId, "⚠️ Вы уже в клане! Сначала введите !клан выйти."); return true; }
                $cid = (int)$m[2];
                $stmt = $pdo->prepare("SELECT id, name FROM bot_clans WHERE id = ?");
                $stmt->execute([$cid]);
                $cl = $stmt->fetch();
                if (!$cl) { sendMessage($peerId, "❌ Клан #{$cid} не найден."); return true; }
                $pdo->prepare("UPDATE bot_users SET clan_id = ? WHERE vk_id = ?")->execute([$cid, $fromId]);
                sendMessage($peerId, "✅ Вы вступили в семью «{$cl['name']}» (#{$cid})!");
                return true;
            }

            if (in_array(mb_strtolower($raw), ['выйти', 'leave'], true)) {
                if ($myClanId <= 0) { sendMessage($peerId, "⚠️ Вы не состоите в клане."); return true; }
                $pdo->prepare("UPDATE bot_users SET clan_id = NULL WHERE vk_id = ?")->execute([$fromId]);
                sendMessage($peerId, "🚪 Вы покинули семью.");
                return true;
            }

            if (in_array(mb_strtolower($raw), ['состав', 'members', 'list'], true)) {
                if ($myClanId <= 0) { sendMessage($peerId, "⚠️ Вы не состоите в клане."); return true; }
                $stmt = $pdo->prepare("SELECT vk_id FROM bot_users WHERE clan_id = ? LIMIT 30");
                $stmt->execute([$myClanId]);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                preloadUsers($pdo, $peerId, $ids);
                $lines = ["👥 Состав семьи (" . count($ids) . "):"];
                foreach ($ids as $uid) $lines[] = "• " . formatUserMention($pdo, $peerId, (int)$uid);
                sendMessage($peerId, implode("\n", $lines), null, ['disable_mentions' => 1]);
                return true;
            }

            if (preg_match('/^(положить|внести|dep|deposit)\s+(\S+)$/iu', $raw, $m)) {
                if ($myClanId <= 0) { sendMessage($peerId, "⚠️ Вы не состоите в клане."); return true; }
                $bal = (int)$ctx['global']['balance'];
                $amt = parseAmount($m[2], $bal);
                if ($amt === null || $amt <= 0 || $amt > $bal) { sendMessage($peerId, "⚠️ Неверная сумма (ваш баланс: " . formatMoney($bal) . ")."); return true; }
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$amt, $fromId]);
                $pdo->prepare("UPDATE bot_clans SET treasury = treasury + ? WHERE id = ?")->execute([$amt, $myClanId]);
                sendMessage($peerId, "💰 Вы внесли " . formatMoney($amt) . " в общак семьи!");
                return true;
            }

            sendMessage($peerId, "🏰 Команды семьи:\n• !клан создать [название]\n• !клан вступить [ID]\n• !клан положить [сумма]\n• !клан состав | !клан выйти | !кланы");
            return true;

        case 'clans':
            $rows = $pdo->query("SELECT c.id, c.name, c.treasury, (SELECT COUNT(*) FROM bot_users u WHERE u.clan_id = c.id) AS mems FROM bot_clans c ORDER BY c.treasury DESC, mems DESC LIMIT 10")->fetchAll();
            if (empty($rows)) { sendMessage($peerId, "🏰 Семей пока нет. Создайте первую: !клан создать [название]"); return true; }
            $lines = ["🏆 Топ семей / кланов RASE RUSSIA:"];
            $i = 1;
            foreach ($rows as $r) {
                $lines[] = "{$i}. [#{$r['id']}] «{$r['name']}» — 💰 " . formatMoney((int)$r['treasury']) . " | 👥 {$r['mems']} чел.";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines));
            return true;

    }

    return false;
}
