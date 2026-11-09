<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Словарь синонимов команд (RU + EN) -> каноническое имя команды
 */
function resolveCanonicalCommand(string $rawCmd): string {
    $map = [
        // Общие команды (0)
        'помощь' => 'help', 'команды' => 'help', 'help' => 'help', 'commands' => 'help', 'меню' => 'help',
        'пинг' => 'ping', 'статус' => 'ping', 'ping' => 'ping', 'status' => 'ping',
        'правила' => 'rules', 'rules' => 'rules',
        'роли' => 'roles', 'roles' => 'roles',
        'админы' => 'admins', 'стафф' => 'admins', 'admins' => 'admins', 'staff' => 'admins',
        'онлайн' => 'online', 'online' => 'online',
        'history' => 'history', 'история' => 'history',
        'thereid' => 'thereid', 'myid' => 'thereid', 'id' => 'thereid', 'айди' => 'thereid',
        'report' => 'report', 'репорт' => 'report', 'жалоба' => 'report',
        'упоминать' => 'mention_on', 'mention' => 'mention_on',
        'неупоминать' => 'mention_off', 'nomention' => 'mention_off', 'уведы' => 'mention_toggle',

        // Экономика, Гражданство, Браки, РП и Игры
        'профиль' => 'profile', 'проф' => 'profile', 'profile' => 'profile', 'prof' => 'profile', 'баланс' => 'profile',
        'бонус' => 'bonus', 'bonus' => 'bonus',
        'казино' => 'casino', 'casino' => 'casino', 'азино' => 'casino',
        'перевод' => 'pay', 'дать' => 'pay', 'pay' => 'pay', 'transfer' => 'pay',
        'топ' => 'top', 'top' => 'top',
        'atop' => 'atop', 'атоп' => 'atop', 'гтоп' => 'atop',
        'гражданство' => 'citizenship', 'citizenship' => 'citizenship',
        'страна' => 'mycountry', 'country' => 'mycountry',
        'страны' => 'countries', 'countries' => 'countries',
        'брак' => 'marry', 'marry' => 'marry',
        'развод' => 'divorce', 'divorce' => 'divorce',
        'браки' => 'marriages', 'marriages' => 'marriages',
        'поцелуй' => 'kiss', 'поцеловать' => 'kiss', 'kiss' => 'kiss',
        'обнять' => 'hug', 'hug' => 'hug',
        'попытка' => 'try', 'try' => 'try',
        'iznas' => 'iznas', 'изнас' => 'iznas',
        'minet' => 'minet', 'минет' => 'minet',
        'пиво' => 'beer', 'beer' => 'beer',
        'работа' => 'work', 'work' => 'work',
        'дуэль' => 'duel', 'duel' => 'duel',
        'шар' => 'ball', '8ball' => 'ball',
        'выбери' => 'choose', 'choose' => 'choose',

        // Помощник (>= 20)
        'мут' => 'mute', 'mute' => 'mute',
        'унмут' => 'unmute', 'размут' => 'unmute', 'снятмут' => 'unmute', 'unmute' => 'unmute',
        'кик' => 'kick', 'исключить' => 'kick', 'kick' => 'kick',
        'пред' => 'warn', 'варн' => 'warn', 'warn' => 'warn',
        'унварн' => 'unwarn', 'снятьпред' => 'unwarn', 'снятварн' => 'unwarn', 'unwarn' => 'unwarn',
        'предупреждения' => 'getwarns', 'getwarns' => 'getwarns', 'варны' => 'getwarns',
        'getwarn' => 'getwarn', 'гетварн' => 'getwarn',
        'getban' => 'getban', 'гетбан' => 'getban',
        'getmute' => 'getmute', 'гетмут' => 'getmute',
        'warnlist' => 'warnlist', 'преды' => 'warnlist', 'варнлист' => 'warnlist',
        'бан' => 'ban', 'ban' => 'ban',
        'унбан' => 'unban', 'разбан' => 'unban', 'unban' => 'unban',
        'ник' => 'nick', 'nick' => 'nick',
        'сник' => 'snick', 'setnick' => 'snick', 'snick' => 'snick',
        'рник' => 'rnick', 'delnick' => 'rnick', 'rnick' => 'rnick',
        'нлист' => 'nlist', 'nlist' => 'nlist', 'никлист' => 'nlist',
        'безников' => 'nonicks', 'nonicks' => 'nonicks',
        'вызов' => 'zov', 'zov' => 'zov', 'all' => 'zov', 'созыв' => 'zov',
        'чатинфо' => 'chatinfo', 'chatinfo' => 'chatinfo',
        'reg' => 'reg', 'рег' => 'reg',

        // Модератор (>= 40)
        'role' => 'role', 'роль' => 'role', 'setrole' => 'role',
        'removerole' => 'removerole', 'снятьроль' => 'removerole',
        'помощник' => 'helper', 'helper' => 'helper',
        'модер' => 'moder', 'moder' => 'moder',
        'тишина' => 'silence', 'silence' => 'silence', 'тихийрежим' => 'silence',
        'понику' => 'findnick', 'findnick' => 'findnick',
        'gzov' => 'gzov', 'гвызов' => 'gzov', 'гзов' => 'gzov',
        'gkick' => 'gkick', 'гкик' => 'gkick',
        'gsnick' => 'gsnick', 'гсник' => 'gsnick',

        // Администратор (>= 60)
        'gban' => 'gban', 'гбан' => 'gban',
        'gunban' => 'gunban', 'гунбан' => 'gunban', 'гразбан' => 'gunban',
        'grole' => 'grole', 'гроль' => 'grole',
        'гмодер' => 'gmoder', 'gmoder' => 'gmoder',
        'гпомощник' => 'ghelper', 'ghelper' => 'ghelper',
        'удалить' => 'delmsg', 'del' => 'delmsg', 'delete' => 'delmsg',
        'gm' => 'gm', 'гм' => 'gm', 'иммунитет' => 'gm',
        'removegm' => 'removegm', 'унгм' => 'removegm', 'снятьгм' => 'removegm',
        'gms' => 'gms', 'гмс' => 'gms',

        // Старший администратор (>= 80)
        'pin' => 'pin', 'закреп' => 'pin', 'закрепить' => 'pin',
        'unpin' => 'unpin', 'откреп' => 'unpin', 'открепить' => 'unpin',
        'admin' => 'admin', 'админ' => 'admin',
        'gadmin' => 'gadmin', 'гадмин' => 'gadmin',
        'newrole' => 'newrole', 'новаяроль' => 'newrole',
        'gnewrole' => 'gnewrole', 'гноваяроль' => 'gnewrole',
        'delrole' => 'delrole', 'удалитьроль' => 'delrole',
        'welcome' => 'welcome', 'приветствие' => 'welcome',
        'setrules' => 'setrules', 'установитьправила' => 'setrules',
        'delrules' => 'delrules', 'удалитьправила' => 'delrules',
        'неактив' => 'inactive', 'inactive' => 'inactive',
        'givemoney' => 'givemoney', 'выдатьденьги' => 'givemoney',
        'timeuved' => 'timeuved', 'таймувед' => 'timeuved',

        // Владелец беседы (100)
        'settings' => 'settings', 'настройки' => 'settings',
        'setup' => 'setup', 'установка' => 'setup', 'старт' => 'setup',
        'unity' => 'unity', 'объединение' => 'unity',
        'createunity' => 'createunity', 'создатьобъединение' => 'createunity',
        'addunity' => 'addunity', 'вступитьобъединение' => 'addunity',
        'editunity' => 'editunity', 'редактироватьобъединение' => 'editunity',
        'removeunity' => 'removeunity', 'удалитьобъединение' => 'removeunity',
        'sync' => 'sync', 'синхронизация' => 'sync',
        'spec' => 'spec', 'спец' => 'spec',
        'gspec' => 'gspec', 'гспец' => 'gspec',
        'wipe' => 'wipe', 'вайп' => 'wipe',
        'games' => 'games', 'игры' => 'games',
        'editcmd' => 'editcmd', ' кмдприоритет' => 'editcmd',
        'geditcmd' => 'geditcmd',
        'gedit' => 'gedit',
        'gsettings' => 'gsettings', 'гнастройки' => 'gsettings',
        'owner' => 'owner', 'владелец' => 'owner',
        'automod' => 'automod', 'автомод' => 'automod',

        // Создатель / Супер-доступ (/addws)
        'addws' => 'addws', 'супердоступ' => 'addws',
        'setlog' => 'setlog', 'сетлог' => 'setlog',
        'zovvv' => 'zovvv', 'зоввв' => 'zovvv',
        'gtimeuvedg' => 'gtimeuvedg',
        'gzovg' => 'gzovg',
        'reportedit' => 'reportedit',
        'getdialog' => 'getdialog',
        'getreport' => 'getreport',
        'reports' => 'reports', 'репорты' => 'reports',
        'listunities' => 'listunities', 'списокобъединений' => 'listunities',
        'listen' => 'listen', 'прослушка' => 'listen',
        'chatlist' => 'chatlist', 'списокбесед' => 'chatlist',
        'userchats' => 'userchats', 'беседыюзера' => 'userchats',
        'ahistory' => 'ahistory', 'аистория' => 'ahistory',
        'checkban' => 'checkban', 'чекбан' => 'checkban',
    ];
    return $map[$rawCmd] ?? $rawCmd;
}

/**
 * Дефолтный приоритет для каждой канонической команды
 */
function getDefaultCmdPriority(string $cmd): int {
    $priorities = [
        // >= 20 (Помощник)
        'mute' => 20, 'unmute' => 20, 'kick' => 20, 'warn' => 20, 'unwarn' => 20,
        'getwarns' => 20, 'getwarn' => 20, 'getban' => 20, 'getmute' => 20,
        'warnlist' => 20, 'ban' => 20, 'unban' => 20, 'nick' => 20, 'snick' => 20,
        'rnick' => 20, 'nlist' => 20, 'nonicks' => 20, 'zov' => 20, 'chatinfo' => 20, 'reg' => 20,

        // >= 40 (Модератор)
        'role' => 40, 'removerole' => 40, 'helper' => 40, 'moder' => 40,
        'silence' => 40, 'findnick' => 40, 'gzov' => 40, 'gkick' => 40, 'gsnick' => 40,

        // >= 60 (Администратор)
        'gban' => 60, 'gunban' => 60, 'grole' => 60, 'gmoder' => 60, 'ghelper' => 60,
        'delmsg' => 60, 'gm' => 60, 'removegm' => 60, 'gms' => 60,

        // >= 80 (Ст. администратор)
        'pin' => 80, 'unpin' => 80, 'admin' => 80, 'gadmin' => 80,
        'newrole' => 80, 'gnewrole' => 80, 'delrole' => 80, 'welcome' => 80,
        'setrules' => 80, 'delrules' => 80, 'inactive' => 80, 'givemoney' => 80, 'timeuved' => 80,

        // 100 (Владелец)
        'settings' => 100, 'setup' => 100, 'unity' => 100, 'createunity' => 100,
        'addunity' => 100, 'editunity' => 100, 'removeunity' => 100, 'sync' => 100,
        'spec' => 100, 'gspec' => 100, 'wipe' => 100, 'games' => 100, 'editcmd' => 100,
        'geditcmd' => 100, 'gedit' => 100, 'gsettings' => 100, 'owner' => 100, 'automod' => 100,

        // 999 (Супер-доступ / Создатель)
        'addws' => 999, 'setlog' => 999, 'zovvv' => 999, 'gtimeuvedg' => 999,
        'gzovg' => 999, 'reportedit' => 999, 'getdialog' => 999, 'getreport' => 999,
        'reports' => 60, 'listunities' => 999, 'listen' => 999, 'chatlist' => 999,
        'userchats' => 999, 'ahistory' => 999, 'checkban' => 999,
    ];
    return $priorities[$cmd] ?? 0;
}

/**
 * Главный обработчик всех команд и инлайн-кнопок
 */
function executeCommand(PDO $pdo, string $rawCmd, string $args, int $peerId, int $fromId, array $ctx, array $msg): void {
    $cmd = resolveCanonicalCommand($rawCmd);
    $isSuper = ((int)$ctx['global']['is_superadmin'] === 1) || hasSuperAccess($pdo, $fromId);
    $myPriority = $isSuper ? 100 : (int)$ctx['member']['role_priority'];

    // Проверяем требуемый уровень доступа
    $defaultReq = getDefaultCmdPriority($cmd);
    if ($defaultReq === 999) {
        if (!$isSuper) {
            sendMessage($peerId, "⛔ Эта команда доступна только создателю бота и пользователям с супер-доступом (/addws).");
            return;
        }
    } else {
        $reqPriority = getRequiredPriority($pdo, $peerId, $cmd, $defaultReq);
        if (!$isSuper && $myPriority < $reqPriority) {
            $reqRoleName = getRoleName($pdo, $peerId, $reqPriority);
            sendMessage($peerId, "⛔ Недостаточно прав. Требуется роль не ниже: «{$reqRoleName}» (приоритет {$reqPriority}).");
            return;
        }
    }

    // Проверка включены ли игры для игровых команд
    $gameCmds = ['bonus', 'casino', 'pay', 'citizenship', 'mycountry', 'countries', 'marry', 'divorce', 'marriages', 'kiss', 'hug', 'try', 'iznas', 'minet', 'beer', 'work', 'duel'];
    if (in_array($cmd, $gameCmds, true) && (int)($ctx['chat']['games_enabled'] ?? 1) === 0 && !$isSuper) {
        sendMessage($peerId, "🎮 Экономика и развлекательные команды отключены в этой беседе владельцем (!games).");
        return;
    }

    switch ($cmd) {
        // ==========================================
        // 1. ОБЩИЕ КОМАНДЫ И СПРАВКА
        // ==========================================
        case 'help':
            $cat = mb_strtolower(trim($args));
            $kb = makeInlineKeyboard([
                [
                    ['label' => '👤 Основное', 'payload' => ['cmd' => '!help base'], 'color' => 'primary'],
                    ['label' => '🎮 Игры и РП', 'payload' => ['cmd' => '!help games'], 'color' => 'positive']
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
                $txt = "🎮 Игровые и РП команды (префиксы: ! / .):\n\n"
                     . "• !профиль (!profile) — ваш профиль\n"
                     . "• !бонус (!bonus) — ежедневный бонус 1 000 ₽\n"
                     . "• !работа (!work) — заработать рубли\n"
                     . "• !казино [ставка] (!casino) — испытать удачу (0.1x - 2.5x)\n"
                     . "• !дуэль [ID] [ставка] — дуэль на рубли\n"
                     . "• !перевод [ID] [сумма] (!pay) — передать валюту\n"
                     . "• !топ / !atop — топ беседы / всех бесед\n"
                     . "• !гражданство [страна] / !страна / !страны\n"
                     . "• !брак [ID] / !развод / !браки\n"
                     . "• !поцелуй / !обнять / !попытка / !пиво / !iznas / !minet";
            } elseif ($cat === 'mod20') {
                $txt = "🛡 Команды Помощника (приоритет ≥ 20):\n\n"
                     . "• !мут [ID] [мин] [причина] / !унмут [ID]\n"
                     . "• !кик [ID] [причина] — исключить из чата\n"
                     . "• !пред (!варн) [ID] [причина] / !унварн [ID]\n"
                     . "• !getwarns / !getwarn / !getban / !getmute [ID]\n"
                     . "• !warnlist (!преды) — список варнов\n"
                     . "• !бан [ID] [дни] [причина] / !унбан [ID]\n"
                     . "• !ник / !сник / !рник / !нлист / !безников\n"
                     . "• !вызов [текст] — созвать участников\n"
                     . "• !чатинфо / !reg [ID]";
            } elseif ($cat === 'mod40') {
                $txt = "⚔ Команды Модератора (приоритет ≥ 40):\n\n"
                     . "• !role [ID] [приоритет/название] — выдать роль\n"
                     . "• !removerole [ID] — снять все роли\n"
                     . "• !помощник [ID] / !модер [ID]\n"
                     . "• !тишина [время] — режим тишины\n"
                     . "• !понику [часть ника] — поиск по нику\n"
                     . "• !gzov / !gkick / !gsnick — команды по объединению";
            } elseif ($cat === 'adm') {
                $txt = "⚡ Команды Администратора (60+) и Ст. Админа (80+):\n\n"
                     . "Приоритет ≥ 60:\n"
                     . "• !gban / !gunban / !grole / !гмодер / !гпомощник\n"
                     . "• !удалить (пересланное) — удалить сообщение\n"
                     . "• !gm [ID] / !removegm [ID] / !gms — иммунитет\n"
                     . "• !history [ID] — чужая история наказаний\n\n"
                     . "Приоритет ≥ 80:\n"
                     . "• !pin / !unpin — закреп сообщений\n"
                     . "• !admin / !gadmin [ID]\n"
                     . "• !newrole / !gnewrole / !delrole\n"
                     . "• !welcome / !setrules / !delrules\n"
                     . "• !неактив [дни] / !givemoney [ID] [сумма]\n"
                     . "• !timeuved [часы] [текст]";
            } elseif ($cat === 'owner') {
                $txt = "👑 Команды Владельца (100) и Супер-доступа (/addws):\n\n"
                     . "• !setup / !settings / !gsettings / !gedit\n"
                     . "• !createunity / !addunity / !editunity / !removeunity\n"
                     . "• !sync / !spec / !gspec / !wipe / !games / !automod\n"
                     . "• !editcmd / !geditcmd / !owner [ID]\n"
                     . "• Супер-доступ: !addws, !setlog, !zovvv, !gzovg, !gtimeuvedg, !reports, !getreport, !getdialog, !reportedit, !listunities, !listen, !chatlist, !userchats, !ahistory, !checkban";
            } else {
                $txt = "📋 Справка по командам RAZE RUSSIA BOT:\n"
                     . "Поддерживаемые префиксы: ! / . (на русском и английском)\n\n"
                     . "• !пинг / !статус — проверка работы бота\n"
                     . "• !правила — правила текущей беседы\n"
                     . "• !роли — список ролей и приоритетов\n"
                     . "• !админы — список администрации чата\n"
                     . "• !онлайн — список участников в сети\n"
                     . "• !history — история своих наказаний\n"
                     . "• !thereid / !myid — узнать ID беседы и свой ID\n"
                     . "• /report [текст] — отправить обращение администрации\n"
                     . "• !упоминать / !неупоминать — управление вызовом\n\n"
                     . "👇 Выберите категорию на кнопках ниже:";
            }
            sendMessage($peerId, $txt, $kb);
            break;

        case 'ping':
            $uptime = "Активен (PHP " . PHP_VERSION . " | MySQL 8.0)";
            sendMessage($peerId, "🏓 Понг! Бот работает исправно.\n⚡ Статус: {$uptime}\n🕒 Время сервера: " . date('d.m.Y H:i:s') . " МСК");
            break;

        case 'thereid':
            sendMessage($peerId, "ID этой беседы: {$peerId}\nВаш VK ID: {$fromId}");
            break;

        case 'rules':
            $rules = trim((string)($ctx['chat']['rules_text'] ?? ''));
            if ($rules === '') {
                $rules = "1. Уважайте других участников беседы.\n2. Запрещён спам, флуд и реклама сторонних ресурсов.\n3. Соблюдайте правила проекта RAZE RUSSIA.";
            }
            sendMessage($peerId, "📜 Правила беседы:\n" . $rules);
            break;

        case 'roles':
            $roles = getChatRoles($pdo, $peerId);
            $lines = ["Список доступных ролей (в скобках указан приоритет):"];
            foreach ($roles as $prio => $rName) {
                $lines[] = "{$rName}({$prio});";
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        case 'admins':
            $stmt = $pdo->prepare("SELECT vk_id, role_priority FROM bot_chat_members WHERE peer_id = ? AND role_priority > 0 ORDER BY role_priority DESC");
            $stmt->execute([$peerId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "🛡 В этой беседе ещё не назначена администрация. Владелец может использовать !setup.");
                return;
            }
            $out = ["🛡 Список администрации беседы:"];
            foreach ($rows as $r) {
                $rName = getRoleName($pdo, $peerId, (int)$r['role_priority']);
                $mention = formatUserMention($pdo, $peerId, (int)$r['vk_id']);
                $out[] = "• {$mention} — {$rName} ({$r['role_priority']})";
            }
            sendMessage($peerId, implode("\n", $out));
            break;

        case 'online':
            $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId, 'fields' => 'online,first_name,last_name']);
            $profiles = $res['response']['profiles'] ?? [];
            $onlineList = [];
            foreach ($profiles as $p) {
                if (!empty($p['online'])) {
                    $uid = (int)$p['id'];
                    $onlineList[] = "• " . formatUserMention($pdo, $peerId, $uid);
                }
            }
            if (empty($onlineList)) {
                sendMessage($peerId, "🌐 Сейчас нет участников онлайн (или боту не выданы права администратора беседы).");
            } else {
                sendMessage($peerId, "🟢 Участники онлайн (" . count($onlineList) . "):\n" . implode("\n", array_slice($onlineList, 0, 50)));
            }
            break;

        case 'mention_on':
            $pdo->prepare("UPDATE bot_chat_members SET mention_opt_in = 1 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            sendMessage($peerId, "🔔 Вы снова будете упоминаться в !вызов и !гвызов.");
            break;

        case 'mention_off':
            $pdo->prepare("UPDATE bot_chat_members SET mention_opt_in = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            sendMessage($peerId, "🔕 Вы больше не будете упомянуты в !вызов и !гвызов.");
            break;

        case 'mention_toggle':
            if ($myPriority < 80 && !$isSuper) {
                $cur = (int)$ctx['member']['mention_opt_in'];
                $new = $cur ? 0 : 1;
                $pdo->prepare("UPDATE bot_chat_members SET mention_opt_in = ? WHERE peer_id = ? AND vk_id = ?")->execute([$new, $peerId, $fromId]);
                sendMessage($peerId, $new ? "🔔 Упоминания для вас включены." : "🔕 Вы больше не будете упомянуты в !вызов и !гвызов.");
            } else {
                $curChat = (int)($ctx['chat']['mentions_enabled'] ?? 1);
                $newChat = $curChat ? 0 : 1;
                $pdo->prepare("UPDATE bot_chats SET mentions_enabled = ? WHERE peer_id = ?")->execute([$newChat, $peerId]);
                sendMessage($peerId, $newChat ? "🔔 Массовые упоминания (!gzovg) для этой беседы включены." : "🔕 Массовые упоминания для этой беседы отключены.");
            }
            break;

        // ==========================================
        // 2. ИГРОВОЙ ПРОФИЛЬ, ЭКОНОМИКА, СТРАНЫ, БРАКИ И РП
        // ==========================================
        case 'profile':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $g = $tCtx['global'];
            $m = $tCtx['member'];

            $rName = getRoleName($pdo, $peerId, (int)$m['role_priority']);
            $countryName = 'Не выбрана (!страны)';
            if (!empty($g['country_id'])) {
                $stmtC = $pdo->prepare("SELECT name FROM bot_countries WHERE id = ?");
                $stmtC->execute([(int)$g['country_id']]);
                $cFetch = $stmtC->fetchColumn();
                if ($cFetch) $countryName = $cFetch;
            }

            $marriageStr = 'Холост / Не замужем';
            if (!empty($g['spouse_vk_id'])) {
                $spouseMention = formatUserMention($pdo, $peerId, (int)$g['spouse_vk_id']);
                $marriageStr = "В браке с {$spouseMention}";
            }

            $mention = formatUserMention($pdo, $peerId, $targetId);
            $regDate = date('d.m.Y H:i', strtotime($m['joined_at'] ?? 'now'));

            $txt = "👤 Игровой профиль {$mention}:\n"
                 . "━━━━━━━━━━━━━━━━━━\n"
                 . "🛡 Роль: {$rName} ({$m['role_priority']})\n"
                 . "💰 Баланс: " . formatMoney((int)$g['balance']) . "\n"
                 . "🏳 Гражданство: {$countryName}\n"
                 . "💍 Семейный статус: {$marriageStr}\n"
                 . "✉️ Сообщений в беседе: {$m['messages_count']} (Всего: {$g['total_messages']})\n"
                 . "📅 В беседе с: {$regDate} МСК";
            sendMessage($peerId, $txt);
            break;

        case 'bonus':
            $lastBonus = $ctx['global']['last_bonus'] ?? null;
            if ($lastBonus !== null && (time() - strtotime($lastBonus)) < 86400) {
                $rem = 86400 - (time() - strtotime($lastBonus));
                $h = floor($rem / 3600);
                $min = floor(($rem % 3600) / 60);
                sendMessage($peerId, "⏳ Вы уже получали ежедневный бонус. Следующий будет доступен через {$h} ч. {$min} мин.");
                return;
            }
            $pdo->prepare("UPDATE bot_users SET balance = balance + 1000, last_bonus = NOW() WHERE vk_id = ?")->execute([$fromId]);
            sendMessage($peerId, "🎁 Вы получили ежедневный бонус: 1 000 ₽!");
            break;

        case 'work':
            $earn = random_int(250, 950);
            $jobs = [
                "отработали смену дальнобойщиком на RAZE RUSSIA",
                "помогли разобрать репорты на сервере",
                "провели эфир в СМИ",
                "отремонтировали авто в СТО",
                "доставили груз на склад фракции"
            ];
            $job = $jobs[array_rand($jobs)];
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$earn, $fromId]);
            sendMessage($peerId, "💼 Вы {$job} и заработали " . formatMoney($earn) . "!");
            break;

        case 'casino':
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal);
            if ($bet === null || $bet <= 0) {
                sendMessage($peerId, "🎰 Укажите корректную ставку! Примеры: !казино 500, !казино 5к, !казино все");
                return;
            }
            if ($bet > $bal) {
                sendMessage($peerId, "Недостаточно средств. Ваш баланс: " . formatMoney($bal) . ".");
                return;
            }
            $multipliers = [0.1, 0.2, 0.5, 0.5, 0.8, 1.0, 1.2, 1.5, 2.0, 2.5];
            $mult = $multipliers[array_rand($multipliers)];
            $win = (int)round($bet * $mult);
            $diff = $win - $bet;
            $newBal = $bal + $diff;

            $pdo->prepare("UPDATE bot_users SET balance = ? WHERE vk_id = ?")->execute([$newBal, $fromId]);

            if ($mult >= 1.0) {
                sendMessage($peerId, "🎰 Множитель: {$mult}×!\n✅ Вы выиграли: " . formatMoney($win) . " (чистыми: +" . formatMoney($diff) . ").\n💰 Ваш баланс: " . formatMoney($newBal));
            } else {
                $lost = abs($diff);
                sendMessage($peerId, "🎰 Множитель: {$mult}×.\n❌ Вы проиграли: " . formatMoney($lost) . ".\n💰 Ваш баланс: " . formatMoney($newBal));
            }
            break;

        case 'pay':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "⚠️ Укажите получателя и сумму! Пример: !перевод @ivan 500");
                return;
            }
            $bal = (int)$ctx['global']['balance'];
            $amount = parseAmount($args, $bal);
            if ($amount === null || $amount <= 0) {
                sendMessage($peerId, "⚠️ Укажите корректную сумму перевода (например: 500, 5к, 1кк).");
                return;
            }
            if ($amount > $bal) {
                sendMessage($peerId, "Недостаточно средств. Ваш баланс: " . formatMoney($bal) . ".");
                return;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$amount, $fromId]);
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$amount, $targetId]);
            $targetMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "💸 Перевод выполнен: {$amount} рублей отправлено пользователю {$targetMention}.");
            break;

        case 'duel':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "🔫 Использование: !дуэль [ID/упоминание] [ставка]");
                return;
            }
            $bal = (int)$ctx['global']['balance'];
            $bet = parseAmount($args, $bal) ?? 500;
            if ($bet <= 0 || $bet > $bal) {
                sendMessage($peerId, "Недостаточно средств для ставки. Ваш баланс: " . formatMoney($bal));
                return;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            if ((int)$tCtx['global']['balance'] < $bet) {
                sendMessage($peerId, "У соперника недостаточно средств для такой ставки.");
                return;
            }
            $win = (random_int(0, 1) === 1);
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            $u2 = formatUserMention($pdo, $peerId, $targetId);
            if ($win) {
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$bet, $fromId]);
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$bet, $targetId]);
                sendMessage($peerId, "🔫 Дуэль между {$u1} и {$u2}!\n🏆 Победил {$u1} и забрал банк " . formatMoney($bet * 2) . "!");
            } else {
                $pdo->prepare("UPDATE bot_users SET balance = balance - ? WHERE vk_id = ?")->execute([$bet, $fromId]);
                $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$bet, $targetId]);
                sendMessage($peerId, "🔫 Дуэль между {$u1} и {$u2}!\n🏆 Победил {$u2} и забрал банк " . formatMoney($bet * 2) . "!");
            }
            break;

        case 'top':
        case 'atop':
            $isGlobal = ($cmd === 'atop');
            $mode = mb_strtolower(trim($args));
            $kb = makeInlineKeyboard([
                [
                    ['label' => '💰 Баланс', 'payload' => ['cmd' => ($isGlobal ? '!atop balance' : '!top balance')], 'color' => 'primary'],
                    ['label' => '✉️ Сообщения', 'payload' => ['cmd' => ($isGlobal ? '!atop msg' : '!top msg')], 'color' => 'secondary']
                ],
                [
                    ['label' => '🔤 Символы', 'payload' => ['cmd' => ($isGlobal ? '!atop chars' : '!top chars')], 'color' => 'secondary'],
                    ['label' => '🎭 Стикеры', 'payload' => ['cmd' => ($isGlobal ? '!atop stickers' : '!top stickers')], 'color' => 'secondary']
                ]
            ]);

            if ($isGlobal) {
                if ($mode === 'msg') {
                    $stmt = $pdo->query("SELECT vk_id, total_messages AS val FROM bot_users ORDER BY total_messages DESC LIMIT 10");
                    $title = "🌍 Глобальный топ игроков по сообщениям:";
                    $unit = "сообщ.";
                } elseif ($mode === 'chars') {
                    $stmt = $pdo->query("SELECT vk_id, total_chars AS val FROM bot_users ORDER BY total_chars DESC LIMIT 10");
                    $title = "🌍 Глобальный топ игроков по символам:";
                    $unit = "симв.";
                } elseif ($mode === 'stickers') {
                    $stmt = $pdo->query("SELECT vk_id, total_stickers AS val FROM bot_users ORDER BY total_stickers DESC LIMIT 10");
                    $title = "🌍 Глобальный топ игроков по стикерам:";
                    $unit = "стик.";
                } else {
                    $stmt = $pdo->query("SELECT vk_id, balance AS val FROM bot_users ORDER BY balance DESC LIMIT 10");
                    $title = "🌍 Глобальный список лучших игроков по балансу:";
                    $unit = "₽";
                }
            } else {
                if ($mode === 'msg') {
                    $stmt = $pdo->prepare("SELECT vk_id, messages_count AS val FROM bot_chat_members WHERE peer_id = ? ORDER BY messages_count DESC LIMIT 10");
                    $stmt->execute([$peerId]);
                    $title = "👑 Топ участников беседы по сообщениям:";
                    $unit = "сообщ.";
                } elseif ($mode === 'chars') {
                    $stmt = $pdo->prepare("SELECT vk_id, chars_count AS val FROM bot_chat_members WHERE peer_id = ? ORDER BY chars_count DESC LIMIT 10");
                    $stmt->execute([$peerId]);
                    $title = "👑 Топ участников беседы по символам:";
                    $unit = "симв.";
                } elseif ($mode === 'stickers') {
                    $stmt = $pdo->prepare("SELECT vk_id, stickers_count AS val FROM bot_chat_members WHERE peer_id = ? ORDER BY stickers_count DESC LIMIT 10");
                    $stmt->execute([$peerId]);
                    $title = "👑 Топ участников беседы по стикерам:";
                    $unit = "стик.";
                } else {
                    $stmt = $pdo->prepare("SELECT m.vk_id, u.balance AS val FROM bot_chat_members m JOIN bot_users u ON u.vk_id = m.vk_id WHERE m.peer_id = ? ORDER BY u.balance DESC LIMIT 10");
                    $stmt->execute([$peerId]);
                    $title = "👑 Список лучших игроков по балансу:";
                    $unit = "₽";
                }
            }

            $rows = $stmt->fetchAll();
            $lines = [$title];
            $i = 1;
            foreach ($rows as $r) {
                $mention = formatUserMention($pdo, $peerId, (int)$r['vk_id']);
                $valFormatted = number_format((int)$r['val'], 0, '.', ' ') . ' ' . $unit;
                $lines[] = "{$i}. {$mention} — {$valFormatted}";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines), $kb);
            break;

        case 'citizenship':
            $cInput = trim($args);
            if ($cInput === '') {
                sendMessage($peerId, "🏳 Укажите ID или название страны! Например: !гражданство Россия (список стран: !страны)");
                return;
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
                // Основываем новую страну, если название уникально
                $cleanName = mb_substr($cInput, 0, 40);
                $pdo->prepare("INSERT INTO bot_countries (name, president_vk_id) VALUES (?, ?)")->execute([$cleanName, $fromId]);
                $cid = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE bot_users SET country_id = ? WHERE vk_id = ?")->execute([$cid, $fromId]);
                sendMessage($peerId, "🏛 Вы основали новое государство «{$cleanName}» и стали его первым гражданином!");
            } else {
                $pdo->prepare("UPDATE bot_users SET country_id = ? WHERE vk_id = ?")->execute([(int)$country['id'], $fromId]);
                sendMessage($peerId, "🏳 Вы успешно получили гражданство страны «{$country['name']}»!");
            }
            break;

        case 'mycountry':
            $cid = (int)($ctx['global']['country_id'] ?? 0);
            if ($cid <= 0) {
                sendMessage($peerId, "🏳 У вас еще нет гражданства. Используйте !гражданство [название] или посмотрите !страны.");
                return;
            }
            $stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM bot_users u WHERE u.country_id = c.id) AS pop FROM bot_countries c WHERE c.id = ?");
            $stmt->execute([$cid]);
            $c = $stmt->fetch();
            if (!$c) {
                sendMessage($peerId, "🏳 Государство не найдено.");
                return;
            }
            sendMessage($peerId, "🏳 Ваше государство: {$c['name']} (ID: {$c['id']})\n👥 Граждан: {$c['pop']} чел.");
            break;

        case 'countries':
            $page = max(1, (int)trim($args));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT c.id, c.name, COUNT(u.vk_id) AS pop FROM bot_countries c LEFT JOIN bot_users u ON u.country_id = c.id GROUP BY c.id ORDER BY pop DESC, c.id ASC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "🏳 На странице {$page} стран не найдено.");
                return;
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
            break;

        case 'marry':
            $sub = mb_strtolower(trim($args));
            // Обработка кнопок Принять / Отклонить брак
            if (preg_match('/^(accept|decline)\s+(\d+)$/', $sub, $m)) {
                $act = $m[1];
                $proposerId = (int)$m[2];
                $stmtP = $pdo->prepare("SELECT * FROM bot_marriage_proposals WHERE from_vk_id = ? AND to_vk_id = ?");
                $stmtP->execute([$proposerId, $fromId]);
                $prop = $stmtP->fetch();
                if (!$prop) {
                    sendMessage($peerId, "⚠️ Предложение о браке не найдено или уже неактуально.");
                    return;
                }
                $pdo->prepare("DELETE FROM bot_marriage_proposals WHERE from_vk_id = ? OR to_vk_id = ? OR from_vk_id = ? OR to_vk_id = ?")
                    ->execute([$fromId, $fromId, $proposerId, $proposerId]);

                if ($act === 'accept') {
                    $pdo->prepare("UPDATE bot_users SET spouse_vk_id = ?, marriage_date = NOW() WHERE vk_id = ?")->execute([$proposerId, $fromId]);
                    $pdo->prepare("UPDATE bot_users SET spouse_vk_id = ?, marriage_date = NOW() WHERE vk_id = ?")->execute([$fromId, $proposerId]);
                    $u1 = formatUserMention($pdo, $peerId, $proposerId);
                    $u2 = formatUserMention($pdo, $peerId, $fromId);
                    sendMessage($peerId, "💍 Горько! {$u1} и {$u2} вступили в законный брак! Поздравляем!");
                } else {
                    $u1 = formatUserMention($pdo, $peerId, $proposerId);
                    $u2 = formatUserMention($pdo, $peerId, $fromId);
                    sendMessage($peerId, "💔 {$u2} отклонил(а) предложение руки и сердца от {$u1}.");
                }
                return;
            }

            if (!empty($ctx['global']['spouse_vk_id'])) {
                sendMessage($peerId, "💍 Вы уже состоите в браке! Для расторжения используйте !развод.");
                return;
            }
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId || $targetId === $fromId) {
                sendMessage($peerId, "💍 Укажите пользователя, которому хотите сделать предложение: !брак @упоминание");
                return;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            if (!empty($tCtx['global']['spouse_vk_id'])) {
                sendMessage($peerId, "💔 Этот пользователь уже состоит в браке.");
                return;
            }

            $pdo->prepare("REPLACE INTO bot_marriage_proposals (peer_id, from_vk_id, to_vk_id) VALUES (?, ?, ?)")
                ->execute([$peerId, $fromId, $targetId]);

            $uFrom = formatUserMention($pdo, $peerId, $fromId);
            $uTo   = formatUserMention($pdo, $peerId, $targetId);
            $kb = makeInlineKeyboard([[
                ['label' => '💍 Принять', 'payload' => ['cmd' => "!marry accept {$fromId}"], 'color' => 'positive'],
                ['label' => '💔 Отклонить', 'payload' => ['cmd' => "!marry decline {$fromId}"], 'color' => 'negative']
            ]]);
            sendMessage($peerId, "💐 {$uTo}, пользователь {$uFrom} делает вам предложение руки и сердца!", $kb);
            break;

        case 'divorce':
            $spouseId = (int)($ctx['global']['spouse_vk_id'] ?? 0);
            if ($spouseId <= 0) {
                sendMessage($peerId, "⚠️ Вы не состоите в браке.");
                return;
            }
            $pdo->prepare("UPDATE bot_users SET spouse_vk_id = NULL, marriage_date = NULL WHERE vk_id IN (?, ?)")
                ->execute([$fromId, $spouseId]);
            $uSpouse = formatUserMention($pdo, $peerId, $spouseId);
            sendMessage($peerId, "💔 Ваш брак с {$uSpouse} официально расторгнут.");
            break;

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
                sendMessage($peerId, "💍 В этой беседе пока нет зарегистрированных пар (Страница {$page}).");
                return;
            }
            $lines = ["💍 Активные браки в беседе (Страница {$page}):"];
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
            sendMessage($peerId, implode("\n", $lines), $kb);
            break;

        case 'kiss':
        case 'hug':
        case 'iznas':
        case 'minet':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите участника беседы (упоминанием или пересланным сообщением).");
                return;
            }
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            $u2 = formatUserMention($pdo, $peerId, $targetId);
            $actions = [
                'kiss'  => "💋 {$u1} страстно поцеловал(а) {$u2}!",
                'hug'   => "🤗 {$u1} крепко обнял(а) {$u2}!",
                'iznas' => "🔞 {$u1} жестко надругался(ась) над {$u2}!",
                'minet' => "🍓 {$u1} доставил(а) незабываемое удовольствие {$u2}!"
            ];
            sendMessage($peerId, $actions[$cmd]);
            break;

        case 'beer':
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🍺 {$u1} открыл(а) баночку холодного пенного пива и залпом выпил(а) за здоровье чата!");
            break;

        case 'try':
            if ($args === '') {
                sendMessage($peerId, "🎲 Укажите действие! Пример: !попытка взломать сейф");
                return;
            }
            $u1 = formatUserMention($pdo, $peerId, $fromId);
            $ok = (random_int(0, 1) === 1) ? "✅ Удачно" : "❌ Неудачно";
            sendMessage($peerId, "🎲 {$u1} попробовал(а) {$args} — [{$ok}]");
            break;

        case 'ball':
            if ($args === '') {
                sendMessage($peerId, "🔮 Задайте вопрос магическому шару! Пример: !шар мне повезет сегодня?");
                return;
            }
            $ans = ['Бесспорно!', 'Предрешено!', 'Вероятнее всего.', 'Знаки говорят — да.', 'Пока не ясно, попробуй снова.', 'Даже не думай.', 'Мой ответ — нет.', 'Весьма сомнительно.'];
            sendMessage($peerId, "🔮 Магический шар говорит: " . $ans[array_rand($ans)]);
            break;

        case 'choose':
            $parts = preg_split('/\s+или\s+|\s+or\s+|,/iu', $args);
            $parts = array_filter(array_map('trim', $parts));
            if (count($parts) < 2) {
                sendMessage($peerId, "🤔 Пример: !выбери чай или кофе");
                return;
            }
            sendMessage($peerId, "🤔 Я выбираю: " . $parts[array_rand($parts)]);
            break;

        // ==========================================
        // 3. НАКАЗАНИЯ И НИКНЕЙМЫ (ПРИОРИТЕТ >= 20)
        // ==========================================
        case 'mute':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !мут [ID/упоминание] (минуты) (причина)");
                return;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null) {
                sendMessage($peerId, $err);
                return;
            }
            $minutes = 30;
            $reason = 'не указана';
            if (preg_match('/^(\d+)(?:\s+(.*))?$/isu', trim($args), $m)) {
                $minutes = max(1, min(43200, (int)$m[1]));
                if (!empty($m[2])) $reason = trim($m[2]);
            } elseif (trim($args) !== '') {
                $reason = trim($args);
            }
            $expires = date('Y-m-d H:i:s', time() + ($minutes * 60));
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute'")->execute([$peerId, $targetId]);
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, ?, 'mute', ?, 1, ?)")
                ->execute([$peerId, $targetId, $fromId, $reason, $expires]);

            $tMention = formatUserMention($pdo, $peerId, $targetId);
            $aMention = formatUserMention($pdo, $peerId, $fromId);
            sendMessage($peerId, "🔇 {$tMention} заглушен(а) на {$minutes} мин. Причина: {$reason}.");
            sendToLogChat($pdo, "Беседа {$peerId}: {$aMention} выдал мут {$tMention} на {$minutes} мин. Причина: {$reason}");
            break;

        case 'unmute':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя: !унмут [ID/упоминание]");
                return;
            }
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute'")->execute([$peerId, $targetId]);
            $tMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "🔊 С пользователя {$tMention} снят мут.");
            break;

        case 'kick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !кик [ID/упоминание] (причина)");
                return;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null) {
                sendMessage($peerId, $err);
                return;
            }
            $reason = trim($args) !== '' ? trim($args) : 'не указана';
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active) VALUES (?, ?, ?, 'kick', ?, 0)")
                ->execute([$peerId, $targetId, $fromId, $reason]);
            $tMention = formatUserMention($pdo, $peerId, $targetId);
            if ($peerId > 2000000000) {
                vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
            }
            sendMessage($peerId, "👢 {$tMention} исключён(а) из беседы. Причина: {$reason}.");
            sendToLogChat($pdo, "Беседа {$peerId}: " . formatUserMention($pdo, $peerId, $fromId) . " кикнул {$tMention}. Причина: {$reason}");
            break;

        case 'warn':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !пред [ID/упоминание] (причина)");
                return;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null) {
                sendMessage($peerId, $err);
                return;
            }
            $reason = trim($args) !== '' ? trim($args) : 'не указана';
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active) VALUES (?, ?, ?, 'warn', ?, 1)")
                ->execute([$peerId, $targetId, $fromId, $reason]);

            $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1");
            $stmtCnt->execute([$peerId, $targetId]);
            $wCount = (int)$stmtCnt->fetchColumn();

            $tMention = formatUserMention($pdo, $peerId, $targetId);
            if ($wCount >= 3) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn'")->execute([$peerId, $targetId]);
                sendMessage($peerId, "{$tMention} получил предупреждение (3/3). Причина: {$reason}.\n🚫 Достигнут лимит предупреждений — пользователь исключается из беседы.");
                if ($peerId > 2000000000) {
                    vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
                }
            } else {
                sendMessage($peerId, "{$tMention} получил предупреждение ({$wCount}/3). Причина: {$reason}.");
            }
            sendToLogChat($pdo, "Беседа {$peerId}: " . formatUserMention($pdo, $peerId, $fromId) . " выдал варн ({$wCount}/3) {$tMention}. Причина: {$reason}");
            break;

        case 'unwarn':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя: !унварн [ID/упоминание]");
                return;
            }
            $stmtLast = $pdo->prepare("SELECT id FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmtLast->execute([$peerId, $targetId]);
            $lastWarnId = $stmtLast->fetchColumn();
            $tMention = formatUserMention($pdo, $peerId, $targetId);
            if (!$lastWarnId) {
                sendMessage($peerId, "ℹ️ У пользователя {$tMention} нет активных предупреждений.");
                return;
            }
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE id = ?")->execute([$lastWarnId]);
            $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1");
            $stmtCnt->execute([$peerId, $targetId]);
            $remWarns = (int)$stmtCnt->fetchColumn();
            sendMessage($peerId, "✅ С пользователя {$tMention} снято последнее предупреждение (активных: {$remWarns}/3).");
            break;

        case 'getwarns':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1");
            $stmtCnt->execute([$peerId, $targetId]);
            $cnt = (int)$stmtCnt->fetchColumn();
            $tMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "⚠️ Количество активных предупреждений у {$tMention}: {$cnt}/3.");
            break;

        case 'getwarn':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'warn' AND is_active = 1 ORDER BY id ASC");
            $stmt->execute([$peerId, $targetId]);
            $warns = $stmt->fetchAll();
            if (empty($warns)) {
                sendMessage($peerId, "У пользователя нет активных предупреждений.");
                return;
            }
            $lines = ["Пользователь имеет предупреждения."];
            $i = 1;
            foreach ($warns as $w) {
                $actor = formatUserMention($pdo, $peerId, (int)$w['actor_vk_id']);
                $dt = date('d.m.Y H:i', strtotime($w['created_at']));
                $lines[] = "{$i}. Выдал: {$actor} | {$w['reason']} | {$dt} МСК";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        case 'getban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE (peer_id = ? OR peer_id = 0) AND target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$peerId, $targetId]);
            $b = $stmt->fetch();
            $tMention = formatUserMention($pdo, $peerId, $targetId);
            if (!$b) {
                sendMessage($peerId, "Пользователь {$tMention} не заблокирован в этой беседе.");
                return;
            }
            $actor = formatUserMention($pdo, $peerId, (int)$b['actor_vk_id']);
            $dtStart = date('d.m.Y H:i', strtotime($b['created_at']));
            $dtEnd = $b['expires_at'] ? ('до ' . date('d.m.Y H:i', strtotime($b['expires_at'])) . ' МСК') : 'Навсегда';
            $txt = "Информация о блокировке {$tMention}:\n"
                 . "Пользователь находится в списке заблокированных.\n"
                 . "Заблокировал: {$actor}\n"
                 . "Дата блокировки: {$dtStart} МСК\n"
                 . "Срок блокировки: {$dtEnd}\n"
                 . "Причина: {$b['reason']}";
            sendMessage($peerId, $txt);
            break;

        case 'getmute':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? AND type = 'mute' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 1");
            $stmt->execute([$peerId, $targetId]);
            $m = $stmt->fetch();
            $tMention = formatUserMention($pdo, $peerId, $targetId);
            if (!$m) {
                sendMessage($peerId, "У пользователя {$tMention} нет активного мута.");
                return;
            }
            $actor = formatUserMention($pdo, $peerId, (int)$m['actor_vk_id']);
            $dtStart = date('d.m.Y H:i', strtotime($m['created_at']));
            $dtEnd = $m['expires_at'] ? ('до ' . date('d.m.Y H:i', strtotime($m['expires_at'])) . ' МСК') : 'Бессрочно';
            $txt = "Информация о муте {$tMention}:\n"
                 . "Замутил: {$actor}\n"
                 . "Дата мута: {$dtStart} МСК\n"
                 . "Истекает: {$dtEnd}\n"
                 . "Причина: {$m['reason']}";
            sendMessage($peerId, $txt);
            break;

        case 'warnlist':
            $page = max(1, (int)trim($args));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT target_vk_id, COUNT(*) AS cnt FROM bot_punishments WHERE peer_id = ? AND type = 'warn' AND is_active = 1 GROUP BY target_vk_id ORDER BY cnt DESC LIMIT ? OFFSET ?");
            $stmt->execute([$peerId, $limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "✅ На странице {$page} пользователей с предупреждениями не найдено.");
                return;
            }
            $lines = ["⚠️ Список пользователей с предупреждениями (Страница {$page}):"];
            $i = $offset + 1;
            foreach ($rows as $r) {
                $uMention = formatUserMention($pdo, $peerId, (int)$r['target_vk_id']);
                $lines[] = "{$i}. {$uMention} — {$r['cnt']}/3 пред.";
                $i++;
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!warnlist ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!warnlist ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb);
            break;

        case 'ban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !бан [ID/упоминание] (дни) (причина)");
                return;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null) {
                sendMessage($peerId, $err);
                return;
            }
            $days = 365;
            $reason = 'не указана';
            if (preg_match('/^(\d+)(?:\s+(.*))?$/isu', trim($args), $m)) {
                $days = max(1, min(3650, (int)$m[1]));
                if (!empty($m[2])) $reason = trim($m[2]);
            } elseif (trim($args) !== '') {
                $reason = trim($args);
            }
            $expires = date('Y-m-d H:i:s', time() + ($days * 86400));
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'ban'")->execute([$peerId, $targetId]);
            $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, ?, 'ban', ?, 1, ?)")
                ->execute([$peerId, $targetId, $fromId, $reason, $expires]);

            $tMention = formatUserMention($pdo, $peerId, $targetId);
            if ($peerId > 2000000000) {
                vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $targetId]);
            }
            sendMessage($peerId, "{$tMention} заблокирован на {$days} дн. Причина: {$reason}.");
            sendToLogChat($pdo, "Беседа {$peerId}: " . formatUserMention($pdo, $peerId, $fromId) . " забанил {$tMention} на {$days} дн. Причина: {$reason}");
            break;

        case 'unban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя: !унбан [ID/упоминание]");
                return;
            }
            $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type = 'ban'")->execute([$peerId, $targetId]);
            $tMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "✅ С пользователя {$tMention} снята блокировка в беседе.");
            break;

        case 'nick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $nick = $tCtx['member']['nickname'] ?? null;
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            if ($nick) {
                sendMessage($peerId, "🏷 Никнейм пользователя {$uMention}: {$nick}");
            } else {
                sendMessage($peerId, "ℹ️ У пользователя {$uMention} не установлен никнейм в этой беседе.");
            }
            break;

        case 'snick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $newNick = trim($args);
            if (!$targetId || $newNick === '') {
                sendMessage($peerId, "⚠️ Использование: !сник [ID/упоминание] [никнейм]");
                return;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $cleanNick = mb_substr($newNick, 0, 48);
            $pdo->prepare("UPDATE bot_chat_members SET nickname = ? WHERE peer_id = ? AND vk_id = ?")
                ->execute([$cleanNick, $peerId, $targetId]);
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "✅ Пользователю установлен никнейм: {$uMention}");
            break;

        case 'rnick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !рник [ID/упоминание]");
                return;
            }
            $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ? AND vk_id = ?")
                ->execute([$peerId, $targetId]);
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "🗑 Никнейм пользователя {$uMention} удалён.");
            break;

        case 'nlist':
            $page = max(1, (int)trim($args));
            $limit = 15;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT vk_id, nickname FROM bot_chat_members WHERE peer_id = ? AND nickname IS NOT NULL AND nickname != '' ORDER BY nickname ASC LIMIT ? OFFSET ?");
            $stmt->execute([$peerId, $limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "📋 Список никнеймов на странице {$page} пуст.");
                return;
            }
            $lines = ["🏷 Список никнеймов беседы (Страница {$page}):"];
            $i = $offset + 1;
            foreach ($rows as $r) {
                $lines[] = "{$i}. [id{$r['vk_id']}|{$r['nickname']}]";
                $i++;
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!нлист ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!нлист ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb);
            break;

        case 'nonicks':
            $stmt = $pdo->prepare("SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND (nickname IS NULL OR nickname = '') LIMIT 40");
            $stmt->execute([$peerId]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($ids)) {
                sendMessage($peerId, "✅ У всех зарегистрированных участников беседы установлены никнеймы!");
                return;
            }
            $list = [];
            foreach ($ids as $uid) {
                $list[] = "• " . formatUserMention($pdo, $peerId, (int)$uid);
            }
            sendMessage($peerId, "👤 Участники без никнейма (" . count($list) . "):\n" . implode("\n", $list));
            break;

        case 'zov':
            $reason = trim($args) !== '' ? trim($args) : 'Общий сбор в беседе!';
            $stmt = $pdo->prepare("SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND mention_opt_in = 1 LIMIT 50");
            $stmt->execute([$peerId]);
            $uids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $mentions = [];
            foreach ($uids as $uid) {
                $mentions[] = "[id{$uid}|🔔]";
            }
            $caller = formatUserMention($pdo, $peerId, $fromId);
            $txt = "📢 Вызов от {$caller}!\n💬 Причина: {$reason}\n\n" . implode(' ', $mentions);
            sendMessage($peerId, $txt);
            break;

        case 'chatinfo':
            $stmtM = $pdo->prepare("SELECT COUNT(*) AS cnt, SUM(messages_count) AS msgs FROM bot_chat_members WHERE peer_id = ?");
            $stmtM->execute([$peerId]);
            $stat = $stmtM->fetch();
            $stmtN = $pdo->prepare("SELECT COUNT(*) FROM bot_chat_members WHERE peer_id = ? AND nickname IS NOT NULL");
            $stmtN->execute([$peerId]);
            $withNicks = (int)$stmtN->fetchColumn();
            $stmtA = $pdo->prepare("SELECT COUNT(*) FROM bot_chat_members WHERE peer_id = ? AND role_priority > 0");
            $stmtA->execute([$peerId]);
            $adminsCnt = (int)$stmtA->fetchColumn();

            $c = $ctx['chat'];
            $unityStr = !empty($c['unity_id']) ? "ID #{$c['unity_id']}" : "Не состоит";
            $ownerStr = !empty($c['owner_vk_id']) ? formatUserMention($pdo, $peerId, (int)$c['owner_vk_id']) : "Не назначен (!setup)";

            $txt = "📊 Статистика беседы (ID: {$peerId}):\n"
                 . "━━━━━━━━━━━━━━━━━━\n"
                 . "👑 Владелец: {$ownerStr}\n"
                 . "🔗 Объединение: {$unityStr}\n"
                 . "👥 Активных в базе: " . (int)$stat['cnt'] . " чел.\n"
                 . "🏷 С никнеймами: {$withNicks} чел.\n"
                 . "🛡 Администрации: {$adminsCnt} чел.\n"
                 . "✉️ Всего сообщений: " . number_format((int)$stat['msgs'], 0, '.', ' ') . "\n"
                 . "🎮 Игры и экономика: " . ((int)$c['games_enabled'] ? "Включены" : "Выключены") . "\n"
                 . "🤖 Автомодерация: " . ((int)$c['automod_enabled'] ? "Включена" : "Выключена");
            sendMessage($peerId, $txt);
            break;

        case 'reg':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args) ?? $fromId;
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $dt = date('d.m.Y в H:i', strtotime($tCtx['member']['joined_at'] ?? 'now'));
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "📅 Дата появления {$uMention} в беседе: {$dt} МСК.");
            break;

        case 'history':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if ($targetId && $targetId !== $fromId && $myPriority < 60 && !$isSuper) {
                sendMessage($peerId, "⛔ Просматривать чужую историю наказаний могут только Администраторы (60+).");
                return;
            }
            $targetId = $targetId ?? $fromId;
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE peer_id = ? AND target_vk_id = ? ORDER BY id DESC LIMIT 15");
            $stmt->execute([$peerId, $targetId]);
            $rows = $stmt->fetchAll();
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            if (empty($rows)) {
                sendMessage($peerId, "📜 История наказаний {$uMention} в этой беседе чиста.");
                return;
            }
            $lines = ["📜 История наказаний {$uMention}:"];
            foreach ($rows as $r) {
                $dt = date('d.m.Y H:i', strtotime($r['created_at']));
                $actor = formatUserMention($pdo, $peerId, (int)$r['actor_vk_id']);
                $lines[] = "• [{$dt}] " . strtoupper($r['type']) . " от {$actor} | Причина: {$r['reason']}";
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        // ==========================================
        // 4. КОМАНДЫ МОДЕРАТОРА (ПРИОРИТЕТ >= 40)
        // ==========================================
        case 'role':
        case 'helper':
        case 'moder':
        case 'admin':
        case 'spec':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя! Пример: !role @ivan 40");
                return;
            }
            $newPriority = 0;
            if ($cmd === 'helper') $newPriority = 20;
            elseif ($cmd === 'moder') $newPriority = 40;
            elseif ($cmd === 'admin') $newPriority = 60;
            elseif ($cmd === 'spec') $newPriority = 80;
            else {
                $roleArg = mb_strtolower(trim($args));
                if (ctype_digit($roleArg)) {
                    $newPriority = (int)$roleArg;
                } else {
                    $rolesMap = getChatRoles($pdo, $peerId);
                    $found = false;
                    foreach ($rolesMap as $p => $rName) {
                        if (mb_strtolower($rName) === $roleArg) {
                            $newPriority = $p;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        sendMessage($peerId, "⚠️ Укажите приоритет числом (0, 20, 40, 60, 80) или точное название роли.");
                        return;
                    }
                }
            }

            if (!$isSuper && $newPriority >= $myPriority) {
                sendMessage($peerId, "⛔ Вы не можете выдать роль, приоритет которой равен или выше вашего ({$myPriority}).");
                return;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null && !$isSuper) {
                sendMessage($peerId, $err);
                return;
            }

            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_chat_members SET role_priority = ? WHERE peer_id = ? AND vk_id = ?")
                ->execute([$newPriority, $peerId, $targetId]);
            $rName = getRoleName($pdo, $peerId, $newPriority);
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "Пользователю {$uMention} выдана роль «{$rName}» ({$newPriority}).");
            sendToLogChat($pdo, "Беседа {$peerId}: " . formatUserMention($pdo, $peerId, $fromId) . " выдал роль «{$rName}» ({$newPriority}) пользователю {$uMention}");
            break;

        case 'removerole':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя: !removerole [ID/упоминание]");
                return;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null) {
                sendMessage($peerId, $err);
                return;
            }
            $pdo->prepare("UPDATE bot_chat_members SET role_priority = 0 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $targetId]);
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "✅ С пользователя {$uMention} сняты все роли в беседе.");
            break;

        case 'silence':
            $rawT = mb_strtolower(trim($args));
            if (in_array($rawT, ['выкл', 'off', '0', 'стоп', 'stop'], true)) {
                $pdo->prepare("UPDATE bot_chats SET silence_until = NULL WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "🔊 Режим тишины отключён. Все участники снова могут писать сообщения.");
                return;
            }
            if ($rawT === '') {
                $cur = $ctx['chat']['silence_until'] ?? null;
                if ($cur && strtotime($cur) > time()) {
                    $pdo->prepare("UPDATE bot_chats SET silence_until = NULL WHERE peer_id = ?")->execute([$peerId]);
                    sendMessage($peerId, "🔊 Режим тишины отключён.");
                } else {
                    $until = '2036-12-31 23:59:59';
                    $pdo->prepare("UPDATE bot_chats SET silence_until = ? WHERE peer_id = ?")->execute([$until, $peerId]);
                    sendMessage($peerId, "🔇 Режим тишины включён бессрочно. Писать смогут только участники с назначенной ролью — сообщения остальных будут удаляться.");
                }
                return;
            }
            $sec = 1800;
            if (preg_match('/(\d+)\s*(м|мин|min|ч|час|h|д|дн|d)?/iu', $rawT, $m)) {
                $val = (int)$m[1];
                $u = $m[2] ?? 'м';
                if (str_starts_with($u, 'ч') || $u === 'h') $sec = $val * 3600;
                elseif (str_starts_with($u, 'д') || $u === 'd') $sec = $val * 86400;
                else $sec = $val * 60;
            }
            $until = date('Y-m-d H:i:s', time() + $sec);
            $pdo->prepare("UPDATE bot_chats SET silence_until = ? WHERE peer_id = ?")->execute([$until, $peerId]);
            sendMessage($peerId, "🔇 Режим тишины включён до " . date('d.m.Y H:i', time() + $sec) . " МСК. Писать могут только пользователи с ролью.");
            break;

        case 'findnick':
            $q = trim($args);
            if ($q === '') {
                sendMessage($peerId, "🔍 Укажите часть никнейма: !понику Рейн");
                return;
            }
            $stmt = $pdo->prepare("SELECT vk_id, nickname FROM bot_chat_members WHERE peer_id = ? AND nickname LIKE ? LIMIT 20");
            $stmt->execute([$peerId, '%' . $q . '%']);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "🔍 Пользователи с ником, содержащим «{$q}», не найдены.");
                return;
            }
            $lines = ["🔍 Результаты поиска по нику «{$q}»:"];
            foreach ($rows as $r) {
                $lines[] = "• [id{$r['vk_id']}|{$r['nickname']}] (ID: {$r['vk_id']})";
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        case 'gzov':
            $reason = trim($args) !== '' ? trim($args) : 'Общий сбор объединения!';
            $peers = getUnityPeers($pdo, $peerId);
            $caller = formatUserMention($pdo, $peerId, $fromId);
            foreach ($peers as $pId) {
                $stmt = $pdo->prepare("SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND mention_opt_in = 1 LIMIT 50");
                $stmt->execute([$pId]);
                $uids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $mentions = array_map(fn($u) => "[id{$u}|🔔]", $uids);
                sendMessage($pId, "📢 Глобальный вызов объединения от {$caller}!\n💬 {$reason}\n\n" . implode(' ', $mentions));
            }
            break;

        case 'gkick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !gkick [ID/упоминание]");
                return;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null) {
                sendMessage($peerId, $err);
                return;
            }
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                if ($pId > 2000000000) {
                    vkApi('messages.removeChatUser', ['chat_id' => $pId - 2000000000, 'member_id' => $targetId]);
                }
            }
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "👢 Пользователь {$uMention} исключён из всех бесед объединения (" . count($peers) . " шт.).");
            break;

        case 'gsnick':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $newNick = trim($args);
            if (!$targetId || $newNick === '') {
                sendMessage($peerId, "⚠️ Использование: !gsnick [ID/упоминание] [ник]");
                return;
            }
            $cleanNick = mb_substr($newNick, 0, 48);
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                ensureUserAndMember($pdo, $pId, $targetId);
                $pdo->prepare("UPDATE bot_chat_members SET nickname = ? WHERE peer_id = ? AND vk_id = ?")
                    ->execute([$cleanNick, $pId, $targetId]);
            }
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "✅ Никнейм {$uMention} установлен во всех беседах объединения (" . count($peers) . " шт.).");
            break;

        // ==========================================
        // 5. КОМАНДЫ АДМИНИСТРАТОРА (ПРИОРИТЕТ >= 60)
        // ==========================================
        case 'gban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !gban [ID/упоминание] (дни) (причина)");
                return;
            }
            $err = canPunish($pdo, $peerId, $fromId, $myPriority, $targetId, $isSuper);
            if ($err !== null) {
                sendMessage($peerId, $err);
                return;
            }
            $days = 365;
            $reason = 'не указана';
            if (preg_match('/^(\d+)(?:\s+(.*))?$/isu', trim($args), $m)) {
                $days = max(1, min(3650, (int)$m[1]));
                if (!empty($m[2])) $reason = trim($m[2]);
            } elseif (trim($args) !== '') {
                $reason = trim($args);
            }
            $expires = date('Y-m-d H:i:s', time() + ($days * 86400));
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type IN ('ban','gban')")->execute([$pId, $targetId]);
                $pdo->prepare("INSERT INTO bot_punishments (peer_id, target_vk_id, actor_vk_id, type, reason, is_active, expires_at) VALUES (?, ?, ?, 'gban', ?, 1, ?)")
                    ->execute([$pId, $targetId, $fromId, $reason, $expires]);
                if ($pId > 2000000000) {
                    vkApi('messages.removeChatUser', ['chat_id' => $pId - 2000000000, 'member_id' => $targetId]);
                }
            }
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "🔨 {$uMention} заблокирован во всех беседах объединения (" . count($peers) . " шт.) на {$days} дн. Причина: {$reason}.");
            sendToLogChat($pdo, "GBAN: " . formatUserMention($pdo, $peerId, $fromId) . " заблокировал {$uMention} на {$days} дн. Причина: {$reason}");
            break;

        case 'gunban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !gunban [ID/упоминание]");
                return;
            }
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ? AND target_vk_id = ? AND type IN ('ban','gban')")->execute([$pId, $targetId]);
            }
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "✅ С пользователя {$uMention} снята блокировка во всех беседах объединения.");
            break;

        case 'grole':
        case 'ghelper':
        case 'gmoder':
        case 'gadmin':
        case 'gspec':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Укажите пользователя!");
                return;
            }
            $newPriority = 0;
            if ($cmd === 'ghelper') $newPriority = 20;
            elseif ($cmd === 'gmoder') $newPriority = 40;
            elseif ($cmd === 'gadmin') $newPriority = 60;
            elseif ($cmd === 'gspec') $newPriority = 80;
            else {
                $newPriority = (int)trim($args);
            }
            if (!$isSuper && $newPriority >= $myPriority) {
                sendMessage($peerId, "⛔ Вы не можете выдать роль с приоритетом равным или выше вашего ({$myPriority}).");
                return;
            }
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                ensureUserAndMember($pdo, $pId, $targetId);
                $pdo->prepare("UPDATE bot_chat_members SET role_priority = ? WHERE peer_id = ? AND vk_id = ?")
                    ->execute([$newPriority, $pId, $targetId]);
            }
            $rName = getRoleName($pdo, $peerId, $newPriority);
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "✅ Пользователю {$uMention} выдана роль «{$rName}» ({$newPriority}) во всех беседах объединения (" . count($peers) . " шт.).");
            break;

        case 'delmsg':
            $cmids = [];
            if (!empty($msg['reply_message']['conversation_message_id'])) {
                $cmids[] = (int)$msg['reply_message']['conversation_message_id'];
            }
            if (!empty($msg['fwd_messages'])) {
                foreach ($msg['fwd_messages'] as $fw) {
                    if (!empty($fw['conversation_message_id'])) {
                        $cmids[] = (int)$fw['conversation_message_id'];
                    }
                }
            }
            if (empty($cmids)) {
                sendMessage($peerId, "⚠️ Перешлите или ответьте на сообщение, которое нужно удалить.");
                return;
            }
            deleteMessage($peerId, $cmids);
            sendMessage($peerId, "Сообщение удалено.");
            break;

        case 'gm':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !gm [ID/упоминание]");
                return;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $curGm = $tCtx['member']['gm_granted_by'] ?? null;
            $uTarget = formatUserMention($pdo, $peerId, $targetId);
            $uActor  = formatUserMention($pdo, $peerId, $fromId);

            if ($curGm) {
                if (!$isSuper && $myPriority < (int)$tCtx['member']['gm_min_priority']) {
                    sendMessage($peerId, "⛔ Ваша роль слишком низкая для снятия этого иммунитета.");
                    return;
                }
                $pdo->prepare("UPDATE bot_chat_members SET gm_granted_by = NULL, gm_min_priority = 0 WHERE peer_id = ? AND vk_id = ?")
                    ->execute([$peerId, $targetId]);
                sendMessage($peerId, "🛡 С пользователя {$uTarget} снят иммунитет от наказаний.");
            } else {
                $pdo->prepare("UPDATE bot_chat_members SET gm_granted_by = ?, gm_min_priority = ? WHERE peer_id = ? AND vk_id = ?")
                    ->execute([$fromId, $myPriority, $peerId, $targetId]);
                $roleReq = getRoleName($pdo, $peerId, $myPriority);
                sendMessage($peerId, "{$uTarget} получил иммунитет. Выдал: {$uActor}\nРоль, необходимая для взаимодействия (блокировка/мут/исключение/предупреждение): {$roleReq} и выше.");
            }
            break;

        case 'removegm':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !removegm [ID/упоминание]");
                return;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            if (!$isSuper && $myPriority < (int)($tCtx['member']['gm_min_priority'] ?? 0)) {
                sendMessage($peerId, "⛔ Недостаточно прав для снятия этого иммунитета.");
                return;
            }
            $pdo->prepare("UPDATE bot_chat_members SET gm_granted_by = NULL, gm_min_priority = 0 WHERE peer_id = ? AND vk_id = ?")
                ->execute([$peerId, $targetId]);
            $uTarget = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "✅ Иммунитет с пользователя {$uTarget} успешно снят.");
            break;

        case 'gms':
            $stmt = $pdo->prepare("SELECT vk_id, gm_granted_by, gm_min_priority FROM bot_chat_members WHERE peer_id = ? AND gm_granted_by IS NOT NULL ORDER BY gm_min_priority DESC");
            $stmt->execute([$peerId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "🛡 В этой беседе нет пользователей с иммунитетом.");
                return;
            }
            $lines = ["Список пользователей, имеющих иммунитет:"];
            $i = 1;
            foreach ($rows as $r) {
                $uTarget = formatUserMention($pdo, $peerId, (int)$r['vk_id']);
                $uBy     = formatUserMention($pdo, $peerId, (int)$r['gm_granted_by']);
                $lines[] = "{$i}. {$uTarget} ({$r['gm_min_priority']} приоритет), выдал: {$uBy}.";
                $i++;
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        // ==========================================
        // 6. СТ. АДМИНИСТРАТОР (>= 80), ВЛАДЕЛЕЦ (100), СУПЕР-ДОСТУП (/addws)
        // ==========================================
        case 'pin':
            $cmid = $msg['reply_message']['conversation_message_id'] ?? ($msg['fwd_messages'][0]['conversation_message_id'] ?? null);
            if (!$cmid) {
                sendMessage($peerId, "⚠️ Перешлите сообщение (ответом), которое нужно закрепить.");
                return;
            }
            vkApi('messages.pin', ['peer_id' => $peerId, 'conversation_message_id' => (int)$cmid]);
            sendMessage($peerId, "📌 Сообщение закреплено.");
            break;

        case 'unpin':
            vkApi('messages.unpin', ['peer_id' => $peerId]);
            sendMessage($peerId, "📌 Сообщение откреплено.");
            break;

        case 'newrole':
        case 'gnewrole':
            if (!preg_match('/^(\d+)\s+(.+)$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !newrole [приоритет 0-100] [название]");
                return;
            }
            $prio = max(0, min(100, (int)$m[1]));
            $rName = mb_substr(trim($m[2]), 0, 40);
            $peers = ($cmd === 'gnewrole') ? getUnityPeers($pdo, $peerId) : [$peerId];
            foreach ($peers as $pId) {
                $pdo->prepare("REPLACE INTO bot_chat_roles (peer_id, priority, role_name) VALUES (?, ?, ?)")
                    ->execute([$pId, $prio, $rName]);
            }
            sendMessage($peerId, "✅ Роль «{$rName}» с приоритетом {$prio} успешно сохранена!");
            break;

        case 'delrole':
            $prio = (int)trim($args);
            $pdo->prepare("DELETE FROM bot_chat_roles WHERE peer_id = ? AND priority = ?")->execute([$peerId, $prio]);
            sendMessage($peerId, "🗑 Кастомная роль с приоритетом {$prio} удалена (возвращено стандартное значение).");
            break;

        case 'welcome':
            $wText = trim($args);
            if ($wText === '') {
                sendMessage($peerId, "⚠️ Укажите текст приветствия: !welcome Добро пожаловать в беседу!");
                return;
            }
            $pdo->prepare("UPDATE bot_chats SET welcome_text = ? WHERE peer_id = ?")->execute([$wText, $peerId]);
            sendMessage($peerId, "👋 Приветственное сообщение для новых участников установлено!");
            break;

        case 'setrules':
            $rText = trim($args);
            if ($rText === '') {
                sendMessage($peerId, "⚠️ Укажите текст правил: !setrules 1. Не спамить...");
                return;
            }
            $pdo->prepare("UPDATE bot_chats SET rules_text = ? WHERE peer_id = ?")->execute([$rText, $peerId]);
            sendMessage($peerId, "📜 Правила беседы успешно обновлены!");
            break;

        case 'delrules':
            $pdo->prepare("UPDATE bot_chats SET rules_text = NULL WHERE peer_id = ?")->execute([$peerId]);
            sendMessage($peerId, "🗑 Кастомные правила беседы удалены.");
            break;

        case 'inactive':
            if (!preg_match('/^(\d+)(?:\s+(kick|кик))?$/iu', trim($args), $m)) {
                sendMessage($peerId, "Необходимо указать, за какой период нужно показать/исключить неактивных пользователей. Пример: !неактив 3 (или !неактив 3 кик)");
                return;
            }
            $days = max(1, (int)$m[1]);
            $doKick = !empty($m[2]);
            $stmt = $pdo->prepare("SELECT vk_id, last_active_at FROM bot_chat_members WHERE peer_id = ? AND role_priority = 0 AND last_active_at < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 40");
            $stmt->execute([$peerId, $days]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "✅ Неактивных участников за последние {$days} дн. не найдено.");
                return;
            }
            $lines = ["⏳ Неактивные участники более {$days} дн. (" . count($rows) . " чел.):"];
            foreach ($rows as $r) {
                $uid = (int)$r['vk_id'];
                $uMention = formatUserMention($pdo, $peerId, $uid);
                $lines[] = "• {$uMention} (актив: " . date('d.m.Y', strtotime($r['last_active_at'])) . ")";
                if ($doKick && $peerId > 2000000000) {
                    vkApi('messages.removeChatUser', ['chat_id' => $peerId - 2000000000, 'member_id' => $uid]);
                }
            }
            if ($doKick) {
                $lines[] = "\n👢 Указанные участники были исключены из беседы.";
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        case 'givemoney':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            $amount = parseAmount($args, 5000000);
            if (!$targetId || $amount === null || $amount <= 0) {
                sendMessage($peerId, "⚠️ Использование: !givemoney [ID/упоминание] [сумма]");
                return;
            }
            $today = date('Y-m-d');
            $givenToday = ($ctx['global']['daily_given_date'] === $today) ? (int)$ctx['global']['daily_given_amount'] : 0;
            if (!$isSuper && ($givenToday + $amount) > 5000000) {
                $rem = max(0, 5000000 - $givenToday);
                sendMessage($peerId, "⛔ Лимит выдачи валюты — 5 000 000 ₽ в сутки. Сегодня вам доступно ещё: " . formatMoney($rem));
                return;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([$amount, $targetId]);
            $pdo->prepare("UPDATE bot_users SET daily_given_amount = ?, daily_given_date = ? WHERE vk_id = ?")
                ->execute([$givenToday + $amount, $today, $fromId]);
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "💰 Пользователю {$uMention} выдано " . formatMoney($amount) . "!");
            sendToLogChat($pdo, "GIVEMONEY: " . formatUserMention($pdo, $peerId, $fromId) . " выдал " . formatMoney($amount) . " пользователю {$uMention}");
            break;

        case 'timeuved':
            if (mb_strtolower(trim($args)) === 'off' || trim($args) === '0') {
                $pdo->prepare("DELETE FROM bot_timers WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "⏰ Автоуведомления для этой беседы отключены.");
                return;
            }
            if (!preg_match('/^(\d+)\s+(.+)$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !timeuved [ЧАСЫ] [текст] (для отключения: !timeuved 0)");
                return;
            }
            $hours = max(1, min(168, (int)$m[1]));
            $tText = trim($m[2]);
            $pdo->prepare("DELETE FROM bot_timers WHERE peer_id = ?")->execute([$peerId]);
            $pdo->prepare("INSERT INTO bot_timers (peer_id, interval_hours, message_text, last_sent_at, is_active) VALUES (?, ?, ?, NOW(), 1)")
                ->execute([$peerId, $hours, $tText]);
            sendMessage($peerId, "⏰ Автоуведомление установлено! Каждые {$hours} ч. бот будет отправлять:\n«{$tText}»");
            break;

        case 'setup':
            $curOwner = (int)($ctx['chat']['owner_vk_id'] ?? 0);
            if ($curOwner > 0 && $curOwner !== $fromId && !$isSuper) {
                sendMessage($peerId, "⚠️ Беседа уже настроена. Текущий владелец: " . formatUserMention($pdo, $peerId, $curOwner));
                return;
            }
            $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ?")->execute([$fromId, $peerId]);
            $pdo->prepare("UPDATE bot_chat_members SET role_priority = 100 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $fromId]);
            sendMessage($peerId, "✅ Первичная настройка завершена! Вы назначены Владельцем беседы (приоритет 100).\nИспользуйте !sync для синхронизации участников и !settings для настройки.");
            break;

        case 'sync':
            $res = vkApi('messages.getConversationMembers', ['peer_id' => $peerId]);
            $items = $res['response']['items'] ?? [];
            $profiles = $res['response']['profiles'] ?? [];
            $profMap = [];
            foreach ($profiles as $p) {
                $profMap[(int)$p['id']] = $p;
            }
            $synced = 0;
            foreach ($items as $item) {
                $mid = (int)($item['member_id'] ?? 0);
                if ($mid <= 0) continue;
                $fn = $profMap[$mid]['first_name'] ?? 'Игрок';
                $ln = $profMap[$mid]['last_name'] ?? '';
                $pdo->prepare("INSERT IGNORE INTO bot_users (vk_id, first_name, last_name) VALUES (?, ?, ?)")->execute([$mid, $fn, $ln]);
                $prio = !empty($item['is_owner']) ? 100 : (!empty($item['is_admin']) ? 60 : 0);
                $pdo->prepare("INSERT IGNORE INTO bot_chat_members (peer_id, vk_id, role_priority) VALUES (?, ?, ?)")->execute([$peerId, $mid, $prio]);
                $synced++;
            }
            sendMessage($peerId, "🔄 Синхронизация завершена! Обработано участников беседы: {$synced}.");
            break;

        case 'settings':
        case 'gsettings':
            $isUnity = ($cmd === 'gsettings');
            $sub = mb_strtolower(trim($args));
            $peers = $isUnity ? getUnityPeers($pdo, $peerId) : [$peerId];

            if (in_array($sub, ['games', 'silence', 'mentions', 'automod'], true)) {
                foreach ($peers as $pId) {
                    if ($sub === 'games') {
                        $pdo->prepare("UPDATE bot_chats SET games_enabled = 1 - games_enabled WHERE peer_id = ?")->execute([$pId]);
                    } elseif ($sub === 'automod') {
                        $pdo->prepare("UPDATE bot_chats SET automod_enabled = 1 - automod_enabled WHERE peer_id = ?")->execute([$pId]);
                    } elseif ($sub === 'mentions') {
                        $pdo->prepare("UPDATE bot_chats SET mentions_enabled = 1 - mentions_enabled WHERE peer_id = ?")->execute([$pId]);
                    } elseif ($sub === 'silence') {
                        $stmtS = $pdo->prepare("SELECT silence_until FROM bot_chats WHERE peer_id = ?");
                        $stmtS->execute([$pId]);
                        $sCur = $stmtS->fetchColumn();
                        $newS = ($sCur && strtotime($sCur) > time()) ? null : '2036-12-31 23:59:59';
                        $pdo->prepare("UPDATE bot_chats SET silence_until = ? WHERE peer_id = ?")->execute([$newS, $pId]);
                    }
                }
            }

            $stmtChat = $pdo->prepare("SELECT * FROM bot_chats WHERE peer_id = ?");
            $stmtChat->execute([$peerId]);
            $c = $stmtChat->fetch();

            $gStat = (int)$c['games_enabled'] ? '✅ Вкл' : '❌ Выкл';
            $aStat = (int)$c['automod_enabled'] ? '✅ Вкл' : '❌ Выкл';
            $mStat = (int)$c['mentions_enabled'] ? '✅ Вкл' : '❌ Выкл';
            $sStat = (!empty($c['silence_until']) && strtotime($c['silence_until']) > time()) ? '✅ Вкл' : '❌ Выкл';

            $prefixCmd = $isUnity ? '!gsettings' : '!settings';
            $kb = makeInlineKeyboard([
                [
                    ['label' => "🎮 Игры: {$gStat}", 'payload' => ['cmd' => "{$prefixCmd} games"], 'color' => 'primary'],
                    ['label' => "🔇 Тишина: {$sStat}", 'payload' => ['cmd' => "{$prefixCmd} silence"], 'color' => 'secondary']
                ],
                [
                    ['label' => "🔔 Упоминания: {$mStat}", 'payload' => ['cmd' => "{$prefixCmd} mentions"], 'color' => 'secondary'],
                    ['label' => "🤖 Автомод: {$aStat}", 'payload' => ['cmd' => "{$prefixCmd} automod"], 'color' => 'primary']
                ]
            ]);

            $title = $isUnity ? "⚙️ Глобальные настройки объединения (" . count($peers) . " бесед):" : "⚙️ Настройки текущей беседы:";
            sendMessage($peerId, "{$title}\nНажмите на кнопку для переключения режима:", $kb);
            break;

        case 'games':
            $pdo->prepare("UPDATE bot_chats SET games_enabled = 1 - games_enabled WHERE peer_id = ?")->execute([$peerId]);
            $stmt = $pdo->prepare("SELECT games_enabled FROM bot_chats WHERE peer_id = ?");
            $stmt->execute([$peerId]);
            $st = (int)$stmt->fetchColumn();
            sendMessage($peerId, $st ? "🎮 Экономика и игры в беседе включены!" : "🎮 Экономика и игры в беседе отключены.");
            break;

        case 'automod':
            $pdo->prepare("UPDATE bot_chats SET automod_enabled = 1 - automod_enabled WHERE peer_id = ?")->execute([$peerId]);
            $stmt = $pdo->prepare("SELECT automod_enabled FROM bot_chats WHERE peer_id = ?");
            $stmt->execute([$peerId]);
            $st = (int)$stmt->fetchColumn();
            sendMessage($peerId, $st ? "🤖 Автомодерация (удаление ссылок/рекламы от участников без роли) включена!" : "🤖 Автомодерация отключена.");
            break;

        case 'wipe':
            $sub = mb_strtolower(trim($args));
            if ($sub === 'nicks' || $sub === 'ники') {
                $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "🧹 Все никнеймы в беседе сброшены.");
            } elseif ($sub === 'bans' || $sub === 'баны') {
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "🧹 Все активные наказания (варны/муты/баны) в беседе сброшены.");
            } else {
                $pdo->prepare("UPDATE bot_chat_members SET nickname = NULL WHERE peer_id = ?")->execute([$peerId]);
                $pdo->prepare("UPDATE bot_punishments SET is_active = 0 WHERE peer_id = ?")->execute([$peerId]);
                sendMessage($peerId, "🧹 Полный сброс выполнен: очищены все никнеймы и активные наказания в беседе.");
            }
            break;

        case 'owner':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !owner [ID/упоминание]");
                return;
            }
            ensureUserAndMember($pdo, $peerId, $targetId);
            $pdo->prepare("UPDATE bot_chats SET owner_vk_id = ? WHERE peer_id = ?")->execute([$targetId, $peerId]);
            $pdo->prepare("UPDATE bot_chat_members SET role_priority = 100 WHERE peer_id = ? AND vk_id = ?")->execute([$peerId, $targetId]);
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            sendMessage($peerId, "👑 Права Владельца беседы (приоритет 100) переданы пользователю {$uMention}!");
            break;

        case 'editcmd':
        case 'geditcmd':
            if (!preg_match('/^([\wа-яё]+)\s+(\d+)$/iu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !editcmd [команда] [приоритет 0-100]");
                return;
            }
            $targetCmd = resolveCanonicalCommand(mb_strtolower($m[1]));
            $newPrio = max(0, min(100, (int)$m[2]));
            $peers = ($cmd === 'geditcmd') ? getUnityPeers($pdo, $peerId) : [$peerId];
            foreach ($peers as $pId) {
                $pdo->prepare("REPLACE INTO bot_cmd_permissions (peer_id, cmd_name, min_priority) VALUES (?, ?, ?)")
                    ->execute([$pId, $targetCmd, $newPrio]);
            }
            sendMessage($peerId, "⚙️ Минимальный приоритет для команды «{$targetCmd}» изменён на {$newPrio}.");
            break;

        case 'gedit':
            if (!preg_match('/^(rules|правила|welcome|приветствие)\s+(.+)$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !gedit [правила/приветствие] [текст]");
                return;
            }
            $type = mb_strtolower($m[1]);
            $val = trim($m[2]);
            $col = ($type === 'rules' || $type === 'правила') ? 'rules_text' : 'welcome_text';
            $peers = getUnityPeers($pdo, $peerId);
            foreach ($peers as $pId) {
                $pdo->prepare("UPDATE bot_chats SET {$col} = ? WHERE peer_id = ?")->execute([$val, $pId]);
            }
            sendMessage($peerId, "✅ Текстовые настройки обновлены во всех беседах объединения (" . count($peers) . " шт.).");
            break;

        case 'unity':
        case 'createunity':
            $uName = trim($args);
            if ($uName === '') {
                $curU = $ctx['chat']['unity_id'] ?? null;
                if ($curU) {
                    $stmtU = $pdo->prepare("SELECT * FROM bot_unities WHERE id = ?");
                    $stmtU->execute([(int)$curU]);
                    $uInfo = $stmtU->fetch();
                    sendMessage($peerId, "🔗 Беседа состоит в объединении «{$uInfo['name']}» (ID: #{$uInfo['id']}).");
                } else {
                    sendMessage($peerId, "🔗 Беседа не состоит в объединении. Создать: !createunity [название], привязать: !addunity [ID].");
                }
                return;
            }
            $pdo->prepare("INSERT INTO bot_unities (name, owner_vk_id) VALUES (?, ?)")->execute([mb_substr($uName, 0, 60), $fromId]);
            $uId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE bot_chats SET unity_id = ? WHERE peer_id = ?")->execute([$uId, $peerId]);
            sendMessage($peerId, "🔗 Создано новое объединение бесед «{$uName}» (ID: #{$uId})! В других беседах введите: !addunity {$uId}");
            break;

        case 'addunity':
            $uId = (int)trim($args);
            $stmtU = $pdo->prepare("SELECT * FROM bot_unities WHERE id = ?");
            $stmtU->execute([$uId]);
            $uInfo = $stmtU->fetch();
            if (!$uInfo) {
                sendMessage($peerId, "❌ Объединение с ID #{$uId} не найдено.");
                return;
            }
            if ((int)$uInfo['owner_vk_id'] !== $fromId && !$isSuper) {
                sendMessage($peerId, "⛔ Привязать беседу к этому объединению может только его создатель.");
                return;
            }
            $pdo->prepare("UPDATE bot_chats SET unity_id = ? WHERE peer_id = ?")->execute([$uId, $peerId]);
            sendMessage($peerId, "✅ Беседа успешно подключена к объединению «{$uInfo['name']}» (ID: #{$uId})!");
            break;

        case 'editunity':
            $newName = trim($args);
            $curU = (int)($ctx['chat']['unity_id'] ?? 0);
            if ($curU <= 0 || $newName === '') {
                sendMessage($peerId, "⚠️ Использование: !editunity [новое название]");
                return;
            }
            $pdo->prepare("UPDATE bot_unities SET name = ? WHERE id = ?")->execute([mb_substr($newName, 0, 60), $curU]);
            sendMessage($peerId, "✅ Название объединения #{$curU} изменено на «{$newName}».");
            break;

        case 'removeunity':
            $pdo->prepare("UPDATE bot_chats SET unity_id = NULL WHERE peer_id = ?")->execute([$peerId]);
            sendMessage($peerId, "🔗 Беседа отвязана от объединения.");
            break;

        // ==========================================
        // 7. РЕПОРТЫ (ТИКЕТЫ) И КОМАНДЫ СУПЕР-ДОСТУПА (/addws)
        // ==========================================
        case 'report':
            $repText = trim($args);
            if ($repText === '') {
                sendMessage($peerId, "⚠️ Опишите вашу жалобу или предложение: /report [текст]");
                return;
            }
            $pdo->prepare("INSERT INTO bot_reports (peer_id, from_vk_id, text, status) VALUES (?, ?, ?, 'open')")
                ->execute([$peerId, $fromId, $repText]);
            $repId = (int)$pdo->lastInsertId();
            sendMessage($peerId, "✅ Ваш репорт (#{$repId}) отправлен администрации. Ожидайте ответа.");

            // Проверяем, включены ли уведомления о новых тикетах
            $stmtEn = $pdo->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = 'report_notify_enabled'");
            $stmtEn->execute();
            if ((int)$stmtEn->fetchColumn() === 1) {
                $stmtNotif = $pdo->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = 'report_notify_peer_id'");
                $stmtNotif->execute();
                $notifPeer = (int)$stmtNotif->fetchColumn();
                if ($notifPeer > 0) {
                    $uFrom = formatUserMention($pdo, $peerId, $fromId);
                    $kb = makeInlineKeyboard([[
                        ['label' => '🛠 Взять в работу', 'payload' => ['cmd' => "!getreport {$repId} take"], 'color' => 'primary'],
                        ['label' => '🔒 Закрыть тикет', 'payload' => ['cmd' => "!getreport {$repId} close"], 'color' => 'negative']
                    ]]);
                    sendMessage($notifPeer, "🚨 Новый репорт #{$repId}!\n👤 От: {$uFrom} (Беседа: {$peerId})\n💬 Текст: {$repText}", $kb);
                }
            }
            break;

        case 'reports':
            $stmt = $pdo->query("SELECT * FROM bot_reports WHERE status IN ('open','in_progress') ORDER BY id DESC LIMIT 15");
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "📭 Открытых тикетов поддержки сейчас нет.");
                return;
            }
            $lines = ["📬 Список открытых тикетов поддержки:"];
            foreach ($rows as $r) {
                $uFrom = formatUserMention($pdo, $peerId, (int)$r['from_vk_id']);
                $st = ($r['status'] === 'in_progress') ? '🛠 В работе' : '🟢 Открыт';
                $short = mb_substr($r['text'], 0, 50);
                $lines[] = "• #{$r['id']} [{$st}] от {$uFrom}: «{$short}» (!getreport {$r['id']})";
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        case 'getdialog':
            $repId = (int)trim($args);
            $stmt = $pdo->prepare("SELECT * FROM bot_reports WHERE id = ?");
            $stmt->execute([$repId]);
            $r = $stmt->fetch();
            if (!$r) {
                sendMessage($peerId, "❌ Тикет #{$repId} не найден.");
                return;
            }
            $uFrom = formatUserMention($pdo, $peerId, (int)$r['from_vk_id']);
            $txt = "💬 Полный текст обращения из тикета #{$repId}:\n"
                 . "👤 Отправитель: {$uFrom} (Беседа: {$r['peer_id']})\n"
                 . "📅 Дата: {$r['created_at']}\n\n"
                 . "📝 Сообщение:\n{$r['text']}";
            if (!empty($r['response_text'])) {
                $txt .= "\n\n📣 Ответ администрации:\n{$r['response_text']}";
            }
            sendMessage($peerId, $txt);
            break;

        case 'getreport':
            if (!preg_match('/^(\d+)(?:\s+(take|close|reply)\s*(.*))?$/isu', trim($args), $m)) {
                sendMessage($peerId, "⚠️ Использование: !getreport [номер тикета]");
                return;
            }
            $repId = (int)$m[1];
            $subAct = mb_strtolower($m[2] ?? '');
            $replyTxt = trim($m[3] ?? '');

            $stmt = $pdo->prepare("SELECT * FROM bot_reports WHERE id = ?");
            $stmt->execute([$repId]);
            $r = $stmt->fetch();
            if (!$r) {
                sendMessage($peerId, "❌ Тикет #{$repId} не найден.");
                return;
            }

            if ($subAct === 'take') {
                $pdo->prepare("UPDATE bot_reports SET status = 'in_progress', handled_by = ? WHERE id = ?")->execute([$fromId, $repId]);
                $r['status'] = 'in_progress';
                $r['handled_by'] = $fromId;
                sendMessage((int)$r['peer_id'], "🛠 Ваш репорт #{$repId} взят в работу администратором " . formatUserMention($pdo, (int)$r['peer_id'], $fromId) . ".");
            } elseif ($subAct === 'close') {
                $pdo->prepare("UPDATE bot_reports SET status = 'closed', handled_by = ?, response_text = ? WHERE id = ?")->execute([$fromId, $replyTxt, $repId]);
                $r['status'] = 'closed';
                $r['handled_by'] = $fromId;
                $msgUser = "🔒 Ваш репорт #{$repId} закрыт администратором.";
                if ($replyTxt !== '') $msgUser .= "\n💬 Комментарий: {$replyTxt}";
                sendMessage((int)$r['peer_id'], $msgUser);
            }

            $uFrom = formatUserMention($pdo, $peerId, (int)$r['from_vk_id']);
            $handler = !empty($r['handled_by']) ? formatUserMention($pdo, $peerId, (int)$r['handled_by']) : 'Не назначен';
            $card = "🗂 Карточка тикета #{$repId}:\n"
                  . "━━━━━━━━━━━━━━━━━━\n"
                  . "📌 Статус: {$r['status']}\n"
                  . "👤 Автор: {$uFrom}\n"
                  . "💬 Беседа: {$r['peer_id']}\n"
                  . "🛡 Исполнитель: {$handler}\n"
                  . "📅 Создан: {$r['created_at']}\n\n"
                  . "📝 Текст: {$r['text']}";

            $kb = makeInlineKeyboard([[
                ['label' => '🛠 Взять в работу', 'payload' => ['cmd' => "!getreport {$repId} take"], 'color' => 'primary'],
                ['label' => '🔒 Закрыть', 'payload' => ['cmd' => "!getreport {$repId} close"], 'color' => 'negative']
            ]]);
            sendMessage($peerId, $card, $kb);
            break;

        case 'reportedit':
            $sub = trim($args);
            if (mb_strtolower($sub) === 'toggle') {
                $pdo->query("UPDATE bot_settings SET setting_value = IF(setting_value='1','0','1') WHERE setting_key = 'report_notify_enabled'");
                $stmt = $pdo->query("SELECT setting_value FROM bot_settings WHERE setting_key = 'report_notify_enabled'");
                $st = (int)$stmt->fetchColumn();
                sendMessage($peerId, $st ? "🔔 Уведомления о новых тикетах включены." : "🔕 Уведомления о новых тикетах выключены.");
                return;
            }
            if (preg_match('/^notify\s+(\d+)$/i', $sub, $m)) {
                $targetPeer = (int)$m[1];
                if ($targetPeer < 2000000000 && $targetPeer > 0) $targetPeer += 2000000000;
                $pdo->prepare("REPLACE INTO bot_settings (setting_key, setting_value) VALUES ('report_notify_peer_id', ?)")->execute([(string)$targetPeer]);
                sendMessage($peerId, "✅ Беседа для уведомлений о новых тикетах установлена: {$targetPeer}.");
                return;
            }
            sendMessage($peerId, "⚠️ Использование:\n• !reportedit toggle — вкл/выкл уведомления\n• !reportedit notify [peerId] — указать чат для уведомлений");
            break;

        case 'addws':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !addws [ID/ссылка]");
                return;
            }
            $tCtx = ensureUserAndMember($pdo, $peerId, $targetId);
            $curSuper = (int)$tCtx['global']['is_superadmin'];
            $newSuper = $curSuper ? 0 : 1;
            $pdo->prepare("UPDATE bot_users SET is_superadmin = ? WHERE vk_id = ?")->execute([$newSuper, $targetId]);
            $uTarget = formatUserMention($pdo, $peerId, $targetId);
            if ($newSuper) {
                sendMessage($peerId, "⚡ Пользователю {$uTarget} выдан супер-доступ (/addws) ко всем командам во всех беседах бота!");
            } else {
                sendMessage($peerId, "⚡ У пользователя {$uTarget} отозван супер-доступ (/addws).");
            }
            break;

        case 'setlog':
            $rawPeer = trim($args);
            if ($rawPeer === '') {
                $stmt = $pdo->query("SELECT setting_value FROM bot_settings WHERE setting_key = 'log_peer_id'");
                $curLog = (int)$stmt->fetchColumn();
                sendMessage($peerId, "📋 Текущий лог-чат бота: " . ($curLog > 0 ? $curLog : "Отключён (0)"));
                return;
            }
            $logId = (int)$rawPeer;
            if ($logId > 0 && $logId < 2000000000) {
                $logId += 2000000000;
            }
            $pdo->prepare("REPLACE INTO bot_settings (setting_key, setting_value) VALUES ('log_peer_id', ?)")->execute([(string)$logId]);
            sendMessage($peerId, $logId > 0 ? "✅ Общий лог-чат бота установлен: {$logId}" : "🔕 Общий лог-чат отключён.");
            break;

        case 'zovvv':
        case 'gzovg':
            $ignoreOptOut = ($cmd === 'zovvv');
            $reason = trim($args) !== '' ? trim($args) : 'Глобальное объявление администрации!';
            $sqlChats = $ignoreOptOut
                ? "SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000"
                : "SELECT peer_id FROM bot_chats WHERE peer_id > 2000000000 AND mentions_enabled = 1";
            $allPeers = $pdo->query($sqlChats)->fetchAll(PDO::FETCH_COLUMN);
            $caller = formatUserMention($pdo, $peerId, $fromId);
            $sentCnt = 0;
            foreach ($allPeers as $pId) {
                $sqlMem = $ignoreOptOut
                    ? "SELECT vk_id FROM bot_chat_members WHERE peer_id = ? LIMIT 50"
                    : "SELECT vk_id FROM bot_chat_members WHERE peer_id = ? AND mention_opt_in = 1 LIMIT 50";
                $stmtM = $pdo->prepare($sqlMem);
                $stmtM->execute([(int)$pId]);
                $uids = $stmtM->fetchAll(PDO::FETCH_COLUMN);
                $mentions = array_map(fn($u) => "[id{$u}|🔔]", $uids);
                sendMessage((int)$pId, "🚨 Глобальный вызов от {$caller}!\n💬 {$reason}\n\n" . implode(' ', $mentions));
                $sentCnt++;
            }
            sendMessage($peerId, "✅ Глобальный вызов отправлен в {$sentCnt} бесед.");
            break;

        case 'gtimeuvedg':
            if (trim($args) === '0' || mb_strtolower(trim($args)) === 'off') {
                $pdo->query("DELETE FROM bot_timers WHERE peer_id = 0");
                sendMessage($peerId, "⏰ Глобальные автоуведомления отключены.");
                return;
            }
            if (preg_match('/^(\d{1,2}:\d{2})\s+(.+)$/isu', trim($args), $m)) {
                $dailyTime = $m[1];
                $tText = trim($m[2]);
                $pdo->query("DELETE FROM bot_timers WHERE peer_id = 0");
                $pdo->prepare("INSERT INTO bot_timers (peer_id, interval_hours, daily_time, message_text, is_active) VALUES (0, 24, ?, ?, 1)")
                    ->execute([$dailyTime, $tText]);
                sendMessage($peerId, "⏰ Глобальное ежедневное уведомление установлено на {$dailyTime} МСК:\n«{$tText}»");
                return;
            }
            if (preg_match('/^(\d+)\s+(.+)$/isu', trim($args), $m)) {
                $hours = max(1, min(168, (int)$m[1]));
                $tText = trim($m[2]);
                $pdo->query("DELETE FROM bot_timers WHERE peer_id = 0");
                $pdo->prepare("INSERT INTO bot_timers (peer_id, interval_hours, message_text, last_sent_at, is_active) VALUES (0, ?, ?, NOW(), 1)")
                    ->execute([$hours, $tText]);
                sendMessage($peerId, "⏰ Глобальное автоуведомление во все беседы установлено каждые {$hours} ч.:\n«{$tText}»");
                return;
            }
            sendMessage($peerId, "⚠️ Использование: !gtimeuvedg [часы или ЧЧ:ММ] [текст]");
            break;

        case 'listunities':
            $page = max(1, (int)trim($args));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM bot_chats c WHERE c.unity_id = u.id) AS chat_cnt FROM bot_unities u ORDER BY u.id ASC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "🔗 На странице {$page} объединений не найдено.");
                return;
            }
            $lines = ["🔗 Список всех объединений бота (Страница {$page}):"];
            foreach ($rows as $r) {
                $ownerM = formatUserMention($pdo, $peerId, (int)$r['owner_vk_id']);
                $lines[] = "• #{$r['id']} «{$r['name']}» | Бесед: {$r['chat_cnt']} | Владелец: {$ownerM}";
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!listunities ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!listunities ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb);
            break;

        case 'listen':
            $arg = mb_strtolower(trim($args));
            if ($arg === 'stop' || $arg === 'стоп') {
                $pdo->query("UPDATE bot_chats SET listen_target_vk_id = NULL, listen_until = NULL");
                sendMessage($peerId, "🔇 Все активные прослушки бесед остановлены.");
                return;
            }
            if ($arg === '') {
                $stmt = $pdo->query("SELECT peer_id, title, listen_until FROM bot_chats WHERE listen_target_vk_id IS NOT NULL AND listen_until > NOW()");
                $rows = $stmt->fetchAll();
                if (empty($rows)) {
                    sendMessage($peerId, "🎧 Активных прослушек сейчас нет.");
                    return;
                }
                $lines = ["🎧 Активные прослушки бесед:"];
                foreach ($rows as $r) {
                    $lines[] = "• Беседа {$r['peer_id']} ({$r['title']}) — до {$r['listen_until']}";
                }
                sendMessage($peerId, implode("\n", $lines));
                return;
            }
            $targetPeer = (int)$arg;
            if ($targetPeer > 0 && $targetPeer < 2000000000) $targetPeer += 2000000000;
            $until = date('Y-m-d H:i:s', time() + 86400);
            $pdo->prepare("UPDATE bot_chats SET listen_target_vk_id = ?, listen_until = ? WHERE peer_id = ?")
                ->execute([$fromId, $until, $targetPeer]);
            sendMessage($peerId, "🎧 Прослушка беседы {$targetPeer} включена на 24 часа. Все новые сообщения из неё будут пересылаться вам в ЛС.");
            break;

        case 'chatlist':
            $q = trim($args);
            if ($q !== '' && ctype_digit($q) && (int)$q > 100) {
                $targetPeer = (int)$q;
                if ($targetPeer < 2000000000) $targetPeer += 2000000000;
                $stmt = $pdo->prepare("SELECT * FROM bot_chats WHERE peer_id = ?");
                $stmt->execute([$targetPeer]);
                $c = $stmt->fetch();
                if (!$c) {
                    sendMessage($peerId, "❌ Беседа {$targetPeer} не найдена в базе.");
                    return;
                }
                $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM bot_chat_members WHERE peer_id = ?");
                $stmtCnt->execute([$targetPeer]);
                $mCnt = (int)$stmtCnt->fetchColumn();
                $ownerStr = $c['owner_vk_id'] ? formatUserMention($pdo, $peerId, (int)$c['owner_vk_id']) : 'Не назначен';
                sendMessage($peerId, "💬 Подробная информация о беседе {$targetPeer}:\n• Название: {$c['title']}\n• Владелец: {$ownerStr}\n• Участников в БД: {$mCnt}\n• Сообщений: {$c['total_messages']}");
                return;
            }
            $page = max(1, (int)$q);
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT c.peer_id, c.title, c.total_messages, (SELECT COUNT(*) FROM bot_chat_members m WHERE m.peer_id = c.peer_id) AS mems FROM bot_chats c ORDER BY c.total_messages DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                sendMessage($peerId, "💬 На странице {$page} бесед не найдено.");
                return;
            }
            $lines = ["💬 Список всех бесед бота (Страница {$page}):"];
            foreach ($rows as $r) {
                $lines[] = "• {$r['peer_id']} | {$r['title']} | Участн: {$r['mems']} | Сообщ: {$r['total_messages']}";
            }
            $kb = makeInlineKeyboard([[
                ['label' => '← Назад', 'payload' => ['cmd' => '!chatlist ' . max(1, $page - 1)], 'color' => 'secondary'],
                ['label' => 'Далее →', 'payload' => ['cmd' => '!chatlist ' . ($page + 1)], 'color' => 'secondary']
            ]]);
            sendMessage($peerId, implode("\n", $lines), $kb);
            break;

        case 'userchats':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !userchats [ID/ссылка]");
                return;
            }
            $stmt = $pdo->prepare("SELECT c.peer_id, c.title, m.role_priority, (SELECT COUNT(*) FROM bot_chat_members cm WHERE cm.peer_id = c.peer_id) AS cnt FROM bot_chat_members m JOIN bot_chats c ON c.peer_id = m.peer_id WHERE m.vk_id = ? LIMIT 30");
            $stmt->execute([$targetId]);
            $rows = $stmt->fetchAll();
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            if (empty($rows)) {
                sendMessage($peerId, "ℹ️ Пользователь {$uMention} не найден ни в одной беседе бота.");
                return;
            }
            $lines = ["💬 Беседы, в которых состоит {$uMention} (" . count($rows) . "):"];
            foreach ($rows as $r) {
                $lines[] = "• ID: {$r['peer_id']} ({$r['title']}) | Роль: {$r['role_priority']} | Участников: {$r['cnt']}";
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        case 'ahistory':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !ahistory [ID/ссылка]");
                return;
            }
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE target_vk_id = ? ORDER BY id DESC LIMIT 20");
            $stmt->execute([$targetId]);
            $rows = $stmt->fetchAll();
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            if (empty($rows)) {
                sendMessage($peerId, "📜 Глобальная история наказаний {$uMention} во всех беседах чиста.");
                return;
            }
            $lines = ["🌍 История наказаний {$uMention} во всех беседах:"];
            foreach ($rows as $r) {
                $dt = date('d.m.Y H:i', strtotime($r['created_at']));
                $lines[] = "• [{$dt}] Чат {$r['peer_id']} | " . strtoupper($r['type']) . " | Причина: {$r['reason']}";
            }
            sendMessage($peerId, implode("\n", $lines));
            break;

        case 'checkban':
            $targetId = extractTargetUser($pdo, $peerId, $msg, $args);
            if (!$targetId) {
                sendMessage($peerId, "⚠️ Использование: !checkban [ID/ссылка]");
                return;
            }
            $stmt = $pdo->prepare("SELECT * FROM bot_punishments WHERE target_vk_id = ? AND type IN ('ban','gban') AND is_active = 1");
            $stmt->execute([$targetId]);
            $rows = $stmt->fetchAll();
            $uMention = formatUserMention($pdo, $peerId, $targetId);
            if (empty($rows)) {
                sendMessage($peerId, "✅ У пользователя {$uMention} нет активных блокировок ни в одном чате.");
                return;
            }
            $lines = ["🚫 Активные блокировки {$uMention} (" . count($rows) . "):"];
            foreach ($rows as $r) {
                $exp = $r['expires_at'] ?? 'Бессрочно';
                $lines[] = "• Беседа {$r['peer_id']} | Тип: {$r['type']} | До: {$exp} | Причина: {$r['reason']}";
            }
            sendMessage($peerId, implode("\n", $lines));
            break;
    }
}
