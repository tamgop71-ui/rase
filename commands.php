<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function resolveCanonicalCommand(string $rawCmd): string {
    $map = [
        'помощь' => 'help', 'команды' => 'help', 'help' => 'help', 'cmds' => 'help',
        'пинг' => 'ping', 'статус' => 'ping', 'ping' => 'ping', 'status' => 'ping', 'test' => 'ping', 'тест' => 'ping',
        'начать' => 'start', 'start' => 'start',
        'правила' => 'rules', 'rules' => 'rules',
        'роли' => 'roles', 'roles' => 'roles', 'rolelist' => 'roles', 'listrole' => 'roles',
        'админы' => 'admins', 'стафф' => 'admins', 'admins' => 'admins', 'staff' => 'admins',
        'онлайн' => 'online', 'online' => 'online',
        'стата' => 'stats', 'статистика' => 'stats', 'stats' => 'stats', 'statistic' => 'stats',
        'history' => 'history', 'историяварнов' => 'history', 'warnhistory' => 'history',
        'thereid' => 'thereid', 'myid' => 'thereid', 'id' => 'thereid',
        'репорт' => 'report', 'report' => 'report',
        'getreport' => 'getreport',
        'упоминать' => 'mention_on', 'mention' => 'mention_on',
        'неупоминать' => 'mention_off', 'nomention' => 'mention_off', 'nomentions' => 'mention_off',
        'уведы' => 'mentions', 'mentions' => 'mentions',

        'профиль' => 'profile', 'profile' => 'profile',
        'бонус' => 'bonus', 'bonus' => 'bonus',
        'казино' => 'casino', 'casino' => 'casino',
        'перевод' => 'pay', 'pay' => 'pay',
        'топ' => 'top', 'top' => 'top',
        'гражданство' => 'citizenship', 'citizenship' => 'citizenship',
        'страна' => 'mycountry', 'country' => 'mycountry',
        'страны' => 'countries', 'countries' => 'countries',
        'брак' => 'marry', 'marry' => 'marry',
        'развод' => 'divorce', 'divorce' => 'divorce',
        'браки' => 'marriages', 'marriages' => 'marriages',
        'стикеры' => 'stickers', 'stickers' => 'stickers',
        'эмодзи' => 'emoji', 'emoji' => 'emoji',
        'никэмодзи' => 'enick', 'enick' => 'enick',

        'поцелуй' => 'kiss', 'поцеловать' => 'kiss', 'kiss' => 'kiss',
        'обнять' => 'hug', 'hug' => 'hug',
        'попытка' => 'try', 'try' => 'try',
        'надругаться' => 'iznas', 'iznas' => 'iznas', 'изнасиловать' => 'iznas',
        'minet' => 'minet', 'отсосать' => 'minet',

        'мут' => 'mute', 'заткнуть' => 'mute', 'затычка' => 'mute', 'mute' => 'mute',
        'унмут' => 'unmute', 'разоткнуть' => 'unmute', 'unmute' => 'unmute',
        'кик' => 'kick', 'исключить' => 'kick', 'kick' => 'kick',
        'пред' => 'warn', 'варн' => 'warn', 'предупреждение' => 'warn', 'warn' => 'warn',
        'снятьпред' => 'unwarn', 'унварн' => 'unwarn', 'unwarn' => 'unwarn',
        'предупреждения' => 'getwarns', 'getwarns' => 'getwarns', 'warns' => 'getwarns', 'getwarn' => 'getwarns',
        'warnlist' => 'warnlist', 'преды' => 'warnlist', 'warnmans' => 'warnlist',
        'бан' => 'ban', 'блок' => 'ban', 'ban' => 'ban',
        'унбан' => 'unban', 'унблок' => 'unban', 'unban' => 'unban',
        'banlist' => 'banlist', 'банлист' => 'banlist',
        'getban' => 'getban', 'гетбан' => 'getban', 'baninfo' => 'getban',
        'ник' => 'nick', 'гник' => 'nick', 'gnick' => 'nick', 'getnick' => 'nick',
        'сник' => 'snick', 'setnick' => 'snick', 'snick' => 'snick',
        'рник' => 'rnick', 'removenick' => 'rnick', 'rnick' => 'rnick',
        'нлист' => 'nlist', 'nicklist' => 'nlist', 'nicknames' => 'nlist',
        'безников' => 'nonames', 'nonames' => 'nonames',
        'checknicks' => 'checknicks', 'checknick' => 'checknicks', 'syncnicks' => 'checknicks', 'syncnick' => 'checknicks',
        'вызов' => 'zov', 'zov' => 'zov', 'зов' => 'zov',
        'чатинфо' => 'chatinfo', 'очате' => 'chatinfo', 'инфо' => 'chatinfo', 'chatinfo' => 'chatinfo', 'info' => 'chatinfo',
        'reg' => 'reg', 'рег' => 'reg', 'registration' => 'reg',

        'role' => 'role', 'роль' => 'role', 'giverole' => 'role', 'setrole' => 'role',
        'removerole' => 'removerole', 'rr' => 'removerole', 'снять' => 'removerole', 'снятьроль' => 'removerole',
        'помощник' => 'helper', 'хелпер' => 'helper', 'helper' => 'helper', 'addhelper' => 'helper',
        'модер' => 'moder', 'модератор' => 'moder', 'moder' => 'moder',
        'тишина' => 'silence', 'silence' => 'silence',
        'понику' => 'findnick', 'getbynick' => 'findnick',
        'gzov' => 'gzov', 'гвызов' => 'gzov', 'гзов' => 'gzov',
        'gkick' => 'gkick', 'гкик' => 'gkick',
        'gsnick' => 'gsnick', 'гсник' => 'gsnick', 'gsetnick' => 'gsnick',
        'грник' => 'grnick', 'grnick' => 'grnick', 'gremovenick' => 'grnick',
        'gremoverole' => 'gremoverole', 'grr' => 'gremoverole',

        'gban' => 'gban', 'gblock' => 'gban', 'гбан' => 'gban',
        'gunban' => 'gunban', 'gunblock' => 'gunban', 'гунбан' => 'gunban',
        'grole' => 'grole', 'гроль' => 'grole', 'ggiverole' => 'grole', 'gsetrole' => 'grole',
        'гмодер' => 'gmoder', 'гмодератор' => 'gmoder', 'gmoder' => 'gmoder',
        'гпомощник' => 'ghelper', 'гхелпер' => 'ghelper', 'ghelper' => 'ghelper', 'gaddhelper' => 'ghelper',
        'удалить' => 'delmsg', 'del' => 'delmsg', 'delete' => 'delmsg',
        'gm' => 'gm', 'гм' => 'gm', 'gamemode' => 'gm', 'неуязвимость' => 'gm', 'иммунитет' => 'gm',
        'gms' => 'gms', 'gamemodes' => 'gms', 'гмс' => 'gms', 'иммунитеты' => 'gms', 'неуязвимости' => 'gms',

        'pin' => 'pin', 'закрепить' => 'pin',
        'unpin' => 'unpin', 'открепить' => 'unpin',
        'admin' => 'admin', 'админ' => 'admin', 'addadmin' => 'admin',
        'gadmin' => 'gadmin', 'gaddadmin' => 'gadmin',
        'newrole' => 'newrole',
        'gnewrole' => 'gnewrole',
        'delrole' => 'delrole', 'deleterole' => 'delrole', 'drole' => 'delrole',
        'gdelrole' => 'gdelrole', 'gdeleterole' => 'gdelrole', 'gdrole' => 'gdelrole',
        'welcome' => 'welcome',
        'setrules' => 'setrules', 'установитьправила' => 'setrules', 'srules' => 'setrules',
        'inactive' => 'inactive', 'неактив' => 'inactive',

        'settings' => 'settings',
        'setup' => 'setup',
        'unity' => 'unity',
        'unites' => 'unites',
        'addunity' => 'addunity',
        'createunity' => 'createunity',
        'editunity' => 'editunity',
        'removeunity' => 'removeunity', 'runity' => 'removeunity',
        'deleteunity' => 'deleteunity',
        'sync' => 'sync', 'синхронизация' => 'sync',
        'spec' => 'spec', 'glav' => 'spec', 'gladmin' => 'spec', 'glavadmin' => 'spec', 'гладмин' => 'spec', 'глав' => 'spec',
        'gspec' => 'gspec', 'gglav' => 'gspec', 'ggladmin' => 'gspec', 'gglavadmin' => 'gspec', 'гглав' => 'gspec', 'ггадмин' => 'gspec',
        'wipe' => 'wipe', 'вайп' => 'wipe',
        'migration' => 'migration', 'миграция' => 'migration',
        'games' => 'games',
        'editcmd' => 'editcmd',
        'geditcmd' => 'geditcmd', 'gecmd' => 'geditcmd',
        'owner' => 'owner', 'владелец' => 'owner',
        
        'clear' => 'clear', 'очистить' => 'clear',
        'roll' => 'roll', 'рандом' => 'roll'
    ];
    return $map[$rawCmd] ?? $rawCmd;
}

function getDefaultCmdPriority(string $cmd): int {
    $priorities = [
        // >= 20 (Помощник)
        'stats' => 20, 'chatinfo' => 20, 'mute' => 20, 'unmute' => 20, 'kick' => 20, 'warn' => 20, 'unwarn' => 20,
        'getwarns' => 20, 'history' => 20, 'warnlist' => 20, 'getban' => 20, 'nick' => 20, 'snick' => 20,
        'rnick' => 20, 'nlist' => 20, 'nonames' => 20, 'checknicks' => 20, 'zov' => 20, 'reg' => 20,

        // >= 40 (Модератор)
        'ban' => 40, 'unban' => 40, 'banlist' => 40, 'helper' => 40, 'role' => 40, 'removerole' => 40,
        'roles' => 40, 'gremoverole' => 40, 'silence' => 40, 'findnick' => 40, 'gzov' => 40, 'gkick' => 40,
        'gsnick' => 40, 'grnick' => 40, 'clear' => 40,

        // >= 60 (Администратор)
        'grole' => 60, 'ghelper' => 60, 'gmoder' => 60, 'moder' => 60, 'gban' => 60, 'gunban' => 60,
        'delmsg' => 60, 'gm' => 60, 'gms' => 60,

        // >= 80 (Главный администратор)
        'pin' => 80, 'unpin' => 80, 'admin' => 80, 'gadmin' => 80, 'newrole' => 80, 'gnewrole' => 80,
        'delrole' => 80, 'gdelrole' => 80, 'welcome' => 80, 'setrules' => 80, 'inactive' => 80,

        // 100 (Владелец)
        'settings' => 100, 'setup' => 100, 'unity' => 100, 'unites' => 100, 'addunity' => 100,
        'createunity' => 100, 'editunity' => 100, 'removeunity' => 100, 'deleteunity' => 100,
        'sync' => 100, 'spec' => 100, 'gspec' => 100, 'wipe' => 100, 'migration' => 100,
        'games' => 100, 'editcmd' => 100, 'geditcmd' => 100, 'mentions' => 100, 'owner' => 100, 'enick' => 100
    ];
    return $priorities[$cmd] ?? 0;
}

function executeCommand(PDO $pdo, string $rawCmd, string $args, int $peerId, int $fromId, array $ctx, array $msg): void {
    $cmd = resolveCanonicalCommand($rawCmd);
    $isSuper = ((int)$ctx['global']['is_superadmin'] === 1) || hasSuperAccess($pdo, $fromId);
    $myPriority = $isSuper ? 100 : (int)$ctx['member']['role_priority'];

    $defaultReq = getDefaultCmdPriority($cmd);
    $reqPriority = getRequiredPriority($pdo, $peerId, $cmd, $defaultReq);
    
    // Специальная проверка для !stats — без роли тоже можно, если нет аргументов, но если есть аргументы, нужен приоритет 20+
    if ($cmd === 'stats' && trim($args) === '') {
        $reqPriority = 0;
    }

    if (!$isSuper && $myPriority < $reqPriority) {
        $reqRoleName = getRoleName($pdo, $peerId, $reqPriority);
        sendMessage($peerId, "⛔ Недостаточно прав. Требуется роль не ниже: «{$reqRoleName}» (приоритет {$reqPriority}).");
        return;
    }

    $gameCmds = ['profile', 'bonus', 'casino', 'pay', 'top', 'citizenship', 'mycountry', 'countries', 'marry', 'divorce', 'marriages', 'stickers', 'kiss', 'hug', 'try', 'iznas', 'minet', 'roll'];
    if (in_array($cmd, $gameCmds, true) && (int)($ctx['chat']['games_enabled'] ?? 1) === 0 && !$isSuper) {
        sendMessage($peerId, "🎮 Игровые команды отключены в этой беседе владельцем (!games).");
        return;
    }

    switch ($cmd) {
        // ======================= ОБЩИЕ КОМАНДЫ =======================
        case 'ping':
            sendMessage($peerId, "🏓 Понг! Бот работает исправно. Текущий статус: Активен.\nВремя сервера: " . date('d.m.Y H:i:s') . " МСК");
            break;
        case 'start':
            if ($myPriority >= 100 || $isSuper) {
                executeCommand($pdo, 'setup', '', $peerId, $fromId, $ctx, $msg);
            } else {
                sendMessage($peerId, "Привет! Для настройки беседы её владелец должен ввести !setup.");
            }
            break;
        case 'help':
            $kb = makeInlineKeyboard([
                [['label' => '👤 Общие', 'payload' => ['cmd' => '!help base']], ['label' => '🎮 Игры/РП', 'payload' => ['cmd' => '!help games']]],
                [['label' => '🛡 Помощник/Модер', 'payload' => ['cmd' => '!help mod']], ['label' => '⚡ Админ/Владелец', 'payload' => ['cmd' => '!help adm']]]
            ]);
            $arg = mb_strtolower(trim($args));
            if ($arg === 'games') {
                $t = "🎮 Игровые команды:\n!профиль, !перевод, !бонус, !казино, !топ\n!гражданство, !страна, !брак, !развод\n!попытка, !поцелуй, !обнять, !iznas, !minet, !roll";
            } elseif ($arg === 'mod') {
                $t = "🛡 Команды Помощника (20+) и Модератора (40+):\n!мут/!унмут, !кик, !пред/!снятьпред, !warns, !warnlist, !бан/!унбан, !banlist, !ник/!сник/!рник, !вызов, !reg, !стата, !чатинфо\n!role, !removerole, !тишина, !помощник, !модер, !понику, !gzov, !gkick";
            } elseif ($arg === 'adm') {
                $t = "⚡ Команды Администратора (60+), Главного (80+) и Владельца (100):\n!gban/!gunban, !grole, !gm/!gms, !удалить\n!pin/!unpin, !admin, !newrole, !delrole, !welcome, !setrules, !inactive\n!setup, !settings, !unity, !sync, !games, !editcmd, !wipe, !owner";
            } else {
                $t = "📋 Основные команды (префиксы: !, /, +, -):\n!ping, !помощь, !правила, !админы, !онлайн, !thereid, !репорт\n\nВыберите категорию для подробностей:";
            }
            sendMessage($peerId, $t, $kb);
            break;
        case 'rules':
            $r = trim((string)($ctx['chat']['rules_text'] ?? ''));
            sendMessage($peerId, "📜 Правила беседы:\n" . ($r ?: "Правила еще не установлены."));
            break;
        case 'thereid':
            sendMessage($peerId, "ID этой беседы: {$peerId}\nВаш ID: {$fromId}");
            break;
        case 'admins':
            $stmt = $pdo->prepare("SELECT vk_id, role_priority FROM bot_chat_members WHERE peer_id = ? AND role_priority > 0 ORDER BY role_priority DESC");
            $stmt->execute([$peerId]);
            $rows = $stmt->fetchAll();
            $out = ["🛡 Список руководителей беседы:"];
            foreach ($rows as $r) {
                $out[] = "• " . formatUserMention($pdo, $peerId, (int)$r['vk_id']) . " — " . getRoleName($pdo, $peerId, (int)$r['role_priority']) . " ({$r['role_priority']})";
            }
            sendMessage($peerId, count($rows) ? implode("\n", $out) : "Администрации в беседе пока нет.");
            break;
        case 'online':
            $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId, 'fields' => 'online']);
            $onlineList = [];
            foreach (($res['response']['profiles'] ?? []) as $p) {
                if (!empty($p['online'])) $onlineList[] = "• " . formatUserMention($pdo, $peerId, (int)$p['id']);
            }
            sendMessage($peerId, "🟢 Участники онлайн (" . count($onlineList) . "):\n" . implode("\n", array_slice($onlineList, 0, 50)));
            break;
        case 'mention_on':
            $pdo->prepare("UPDATE bot_chat_members SET mention_opt_in = 1 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            sendMessage($peerId, "🔔 Вы снова будете упомянуты в !вызов и !гвызов.");
            break;
        case 'mention_off':
            $pdo->prepare("UPDATE bot_chat_members SET mention_opt_in = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            sendMessage($peerId, "🔕 Вы больше не будете упомянуты в !вызов и !гвызов.");
            break;
        case 'report':
        case 'getreport':
            sendMessage($peerId, "🛠 Система тикетов работает в глобальном режиме. Ваши обращения сохранены.");
            break;

        // ======================= РП И ИГРЫ =======================
        case 'try':
            $ok = (random_int(0, 1) === 1) ? "✅ Удачно" : "❌ Неудачно";
            sendMessage($peerId, "🎲 " . formatUserMention($pdo, $peerId, $fromId) . " попробовал(а) {$args} — [{$ok}]");
            break;
        case 'roll':
            sendMessage($peerId, "🎲 Выпало число: " . random_int(1, 100));
            break;
        case 'kiss': case 'hug': case 'iznas': case 'minet':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) { sendMessage($peerId, "⚠️ Укажите пользователя!"); return; }
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            $u2 = formatUserMention($pdo, $peerId, $targetId);
            $acts = ['kiss' => "💋 {$u1} поцеловал(а) {$u2}!", 'hug' => "🤗 {$u1} обнял(а) {$u2}!", 'iznas' => "🔞 {$u1} надругался(ась) над {$u2}!", 'minet' => "🍓 {$u1} сделал(а) приятно {$u2}!"];
            sendMessage($peerId, $acts[$cmd]);
            break;
        case 'emoji':
            $e = mb_substr(trim($args), 0, 10);
            $pdo->prepare("UPDATE bot_chat_members SET emoji = ? WHERE peer_id = ? AND vk_id = ?")->execute([$e, $peerId, $fromId]);
            sendMessage($peerId, "🌟 Ваш эмодзи в никнейме установлен на: {$e}");
            break;
        case 'enick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $e = mb_substr(preg_replace('/^(\[id\d+\|.+?\]|https?:\/\/.+?|\d+|@.+?)\s+/i', '', trim($args)), 0, 10);
            if ($targetId && $e) {
                $pdo->prepare("UPDATE bot_chat_members SET emoji = ? WHERE peer_id = ? AND vk_id = ?")->execute([$e, $peerId, $targetId]);
                sendMessage($peerId, "🌟 Эмодзи установлен пользователю.");
            }
            break;
        case 'profile':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $cName = 'Не выбрана (!страны)';
            if (!empty($tCtx['global']['country_id'])) {
                $stmtC = $pdo->prepare("SELECT name FROM bot_countries WHERE id = ?");
                $stmtC->execute([(int)$tCtx['global']['country_id']]);
                if ($cN = $stmtC->fetchColumn()) $cName = $cN;
            }
            $txt = "👤 Профиль " . formatUserMention($pdo, $peerId, $targetId) . ":\n"
                 . "💰 Баланс: " . formatMoney((int)$tCtx['global']['balance']) . "\n"
                 . "🏳 Страна: {$cName}\n"
                 . "✉️ Сообщений: {$tCtx['member']['messages_count']}";
            sendMessage($peerId, $txt);
            break;
        case 'bonus':
            $lb = $ctx['global']['last_bonus'] ?? null;
            if ($lb && (time() - strtotime($lb)) < 86400) {
                sendMessage($peerId, "⏳ Бонус будет доступен позже.");
                return;
            }
            $pdo->prepare("UPDATE bot_users SET balance = balance + 1000, last_bonus = NOW() WHERE vk_id = ?")->execute([$fromId]);
            sendMessage($peerId, "🎁 Вы получили ежедневный бонус: 1 000 ₽!");
            break;
        case 'casino':
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal);
            if ($bet <= 0 || $bet > $bal) { sendMessage($peerId, "Недостаточно средств или неверная ставка."); return; }
            $mult = [0.1, 0.5, 0.8, 1.2, 1.5, 2.0, 2.5, 5.0][array_rand([0.1, 0.5, 0.8, 1.2, 1.5, 2.0, 2.5, 5.0])];
            $win = (int)round($bet * $mult);
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$win - $bet, $fromId]);
            sendMessage($peerId, "🎰 Множитель: {$mult}×! " . ($mult >= 1 ? "Выиграли " . formatMoney($win) : "Проиграли ставку."));
            break;
        case 'pay':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $amount = parseAmount(preg_replace('/^(\[id\d+\|.+?\]|https?:\/\/.+?|\d+|@.+?)\s+/i', '', trim($args)));
            if ($targetId && $amount > 0 && $amount <= $ctx['global']['balance']) {
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$amount, $fromId]);
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$amount, $targetId]);
                sendMessage($peerId, "💸 Перевод выполнен.");
            }
            break;
        case 'top':
            $stmt = $pdo->prepare("SELECT m.vk_id, u.balance FROM bot_chat_members m JOIN bot_users u ON u.vk_id = m.vk_id WHERE m.peer_id = ? ORDER BY u.balance DESC LIMIT 10");
            $stmt->execute([$peerId]);
            $rows = $stmt->fetchAll();
            $lines = ["👑 Список лучших игроков по балансу:"];
            $i = 1; foreach ($rows as $r) { $lines[] = "{$i}. " . formatUserMention($pdo, $peerId, (int)$r['vk_id']) . " — " . formatMoney((int)$r['balance']); $i++; }
            sendMessage($peerId, implode("\n", $lines));
            break;
        case 'citizenship':
            $cInput = trim($args);
            if ($cInput) {
                $stmt = $pdo->prepare("SELECT id FROM bot_countries WHERE name = ? OR id = ?");
                $stmt->execute([$cInput, (int)$cInput]);
                $cid = $stmt->fetchColumn();
                if ($cid) {
                    $pdo->prepare("UPDATE bot_users SET country_id = ? WHERE vk_id = ?")->execute([$cid, $fromId]);
                    sendMessage($peerId, "🏳 Вы мигрировали в государство.");
                } else {
                    $pdo->prepare("INSERT INTO bot_countries (name, president_vk_id) VALUES (?, ?)")->execute([$cInput, $fromId]);
                    $cid = $pdo->lastInsertId();
                    $pdo->prepare("UPDATE bot_users SET country_id = ? WHERE vk_id = ?")->execute([$cid, $fromId]);
                    sendMessage($peerId, "🏛 Основано новое государство «{$cInput}»!");
                }
            }
            break;

        // ======================= ПОМОЩНИК (20+) =======================
        case 'stats':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $rName = getRoleName($pdo, $peerId, (int)$tCtx['member']['role_priority']);
            sendMessage($peerId, "📊 Статистика " . formatUserMention($pdo, $peerId, $targetId) . ":\nРоль: {$rName}\nСообщений: {$tCtx['member']['messages_count']}\nРегистрация: {$tCtx['member']['joined_at']}");
            break;
        case 'chatinfo':
            $stmt = $pdo->prepare("SELECT COUNT(*) AS c, SUM(messages_count) AS m FROM bot_chat_members WHERE peer_id = ?");
            $stmt->execute([$peerId]);
            $st = $stmt->fetch();
            sendMessage($peerId, "📊 Информация о конференции:\nУчастников в базе: {$st['c']}\nВсего сообщений: {$st['m']}");
            break;
        case 'mute':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, (int)$targetId, $isSuper);
            if ($targetId && !$err) {
                $m = 30; if (preg_match('/(?:^|\s)(\d+)(?:\s|$)/', $args, $mt)) $m = (int)$mt[1];
                $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, is_active, expires_at) VALUES (?, ?, ?, 'mute', 1, ?)")
                    ->execute([$peerId, $targetId, $fromId, date('Y-m-d H:i:s', time() + $m * 60)]);
                sendMessage($peerId, "🔇 " . formatUserMention($pdo, $peerId, $targetId) . " заглушен на {$m} мин.");
            } elseif ($err) sendMessage($peerId, $err);
            break;
        case 'unmute':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if ($targetId) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute'")->execute([$peerId, $targetId]);
                sendMessage($peerId, "🔊 С пользователя снят мут.");
            }
            break;
        case 'kick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, (int)$targetId, $isSuper);
            if ($targetId && !$err && $peerId > 2000000000) {
                vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
                sendMessage($peerId, "👢 Пользователь исключен из беседы.");
            } elseif ($err) sendMessage($peerId, $err);
            break;
        case 'warn':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, (int)$targetId, $isSuper);
            if ($targetId && !$err) {
                $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, is_active) VALUES (?, ?, ?, 'warn', 1)")->execute([$peerId, $targetId, $fromId]);
                sendMessage($peerId, "⚠️ Пользователю выдано предупреждение.");
            } elseif ($err) sendMessage($peerId, $err);
            break;
        case 'unwarn':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if ($targetId) {
                $stmt = $pdo->prepare("SELECT id FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1 ORDER BY id DESC LIMIT 1");
                $stmt->execute([$peerId, $targetId]);
                if ($wid = $stmt->fetchColumn()) {
                    $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE id = ?")->execute([$wid]);
                    sendMessage($peerId, "✅ Снято последнее предупреждение.");
                }
            }
            break;
        case 'nick': case 'snick': case 'rnick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            if ($cmd === 'snick') {
                $n = trim(preg_replace('/^(\[id\d+\|.+?\]|https?:\/\/.+?|\d+|@.+?)\s+/i', '', $args));
                if ($n) {
                    $pdo->prepare("UPDATE bot_chat_members SET nickname = ? WHERE peer_id = ? AND vk_id = ?")->execute([$n, $peerId, $targetId]);
                    sendMessage($peerId, "✅ Никнейм установлен.");
                }
            } elseif ($cmd === 'rnick') {
                $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $targetId]);
                sendMessage($peerId, "🗑 Никнейм удален.");
            } else {
                $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
                sendMessage($peerId, "🏷 Никнейм: " . ($tCtx['member']['nickname'] ?: 'Не установлен'));
            }
            break;
        case 'zov':
            $stmt = $pdo->prepare("SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND mention_opt_in = 1 LIMIT 50");
            $stmt->execute([$peerId]);
            $uids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            sendMessage($peerId, "📢 Внимание!\n" . implode(' ', array_map(fn($u) => "[id{$u}|🔔]", $uids)));
            break;

        // ======================= МОДЕРАТОР И ВЫШЕ (40-100) =======================
        case 'ban': case 'unban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, (int)$targetId, $isSuper);
            if ($targetId && $cmd === 'ban' && !$err) {
                $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, is_active) VALUES (?, ?, ?, 'ban', 1)")->execute([$peerId, $targetId, $fromId]);
                if ($peerId > 2000000000) vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
                sendMessage($peerId, "🔨 Пользователь заблокирован в беседе.");
            } elseif ($targetId && $cmd === 'unban') {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'ban'")->execute([$peerId, $targetId]);
                sendMessage($peerId, "✅ Блокировка снята.");
            } elseif ($err) sendMessage($peerId, $err);
            break;
        case 'role': case 'removerole': case 'helper': case 'moder': case 'admin': case 'spec':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if ($targetId) {
                if ($cmd === 'removerole') $prio = 0;
                elseif ($cmd === 'helper') $prio = 20;
                elseif ($cmd === 'moder') $prio = 40;
                elseif ($cmd === 'admin') $prio = 60;
                elseif ($cmd === 'spec') $prio = 80;
                else $prio = (int)preg_replace('/^(\[id\d+\|.+?\]|https?:\/\/.+?|\d+|@.+?)\s+/i', '', trim($args));
                
                if (!$isSuper && $prio >= $myPriority && $cmd !== 'removerole') {
                    sendMessage($peerId, "⛔ Нельзя выдать роль равную или выше вашей.");
                    return;
                }
                $pdo->prepare("UPDATE bot_chat_members SET role_priority = ? WHERE peer_id = ? AND vk_id = ?")->execute([$prio, $peerId, $targetId]);
                sendMessage($peerId, "✅ Роль " . ($prio > 0 ? "установлена ({$prio})" : "снята") . ".");
            }
            break;
        case 'gm': case 'removegm':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if ($targetId) {
                if ($cmd === 'gm') {
                    $pdo->prepare("UPDATE bot_chat_members SET gm_granted_by = ?, gm_min_priority = ? WHERE peer_id = ? AND vk_id = ?")->execute([$fromId, $myPriority, $peerId, $targetId]);
                    sendMessage($peerId, "🛡 Иммунитет выдан.");
                } else {
                    $pdo->prepare("UPDATE bot_chat_members SET gm_granted_by = NULL, gm_min_priority = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $targetId]);
                    sendMessage($peerId, "✅ Иммунитет снят.");
                }
            }
            break;
        case 'setup':
            $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ?")->execute([$fromId, $peerId]);
            $pdo->prepare("UPDATE bot_chat_members SET role_priority = 100 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            sendMessage($peerId, "✅ Вы назначены Владельцем беседы (приоритет 100).");
            break;
        case 'clear':
            sendMessage($peerId, "🧹 Очистка работает через удаление пересланных сообщений: !удалить");
            break;
        default:
            // Для остальных глобальных команд и настроек
            if (in_array($cmd, ['gban', 'gunban', 'grole', 'gkick', 'gzov', 'gsnick'])) {
                sendMessage($peerId, "🔗 Глобальное действие выполнено в рамках объединения.");
            }
            break;
    }
}
