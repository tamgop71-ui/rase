<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/commands_fun.php';
require_once __DIR__ . '/commands_mod.php';

/**
 * Полный словарь синонимов команд (RU + EN) -> каноническое имя
 */
function resolveCanonicalCommand(string $rawCmd): string {
    static $map = [
        // Общие (0)
        'помощь' => 'help', 'команды' => 'help', 'help' => 'help', 'cmds' => 'help', 'commands' => 'help', 'меню' => 'help',
        'пинг' => 'ping', 'статус' => 'ping', 'ping' => 'ping', 'status' => 'ping', 'test' => 'ping', 'тест' => 'ping',
        'начать' => 'start', 'start' => 'start',
        'правила' => 'rules', 'rules' => 'rules',
        'роли' => 'roles', 'roles' => 'roles', 'rolelist' => 'roles', 'listrole' => 'roles',
        'админы' => 'admins', 'стафф' => 'admins', 'admins' => 'admins', 'staff' => 'admins',
        'онлайн' => 'online', 'online' => 'online',
        'thereid' => 'thereid', 'myid' => 'thereid', 'id' => 'thereid', 'айди' => 'thereid',
        'упоминать' => 'mention_on', 'mention' => 'mention_on',
        'неупоминать' => 'mention_off', 'nomention' => 'mention_off', 'nomentions' => 'mention_off',
        'уведы' => 'mentions', 'mentions' => 'mentions',

        // Игры, Экономика, Банк, Социалка и РП
        'профиль' => 'profile', 'проф' => 'profile', 'profile' => 'profile', 'prof' => 'profile', 'баланс' => 'profile',
        'бонус' => 'bonus', 'bonus' => 'bonus',
        'работа' => 'work', 'work' => 'work',
        'банк' => 'bank', 'bank' => 'bank',
        'положить' => 'deposit', 'депозит' => 'deposit', 'deposit' => 'deposit', 'dep' => 'deposit',
        'снять' => 'withdraw', 'вывести' => 'withdraw', 'withdraw' => 'withdraw',
        'ограбить' => 'rob', 'грабить' => 'rob', 'rob' => 'rob',
        'казино' => 'casino', 'casino' => 'casino',
        'слоты' => 'slots', 'slots' => 'slots',
        'рулетка' => 'roulette', 'roulette' => 'roulette',
        'перевод' => 'pay', 'дать' => 'pay', 'pay' => 'pay', 'transfer' => 'pay',
        'дуэль' => 'duel', 'duel' => 'duel',
        'топ' => 'top', 'top' => 'top',
        'atop' => 'atop', 'атоп' => 'atop', 'гтоп' => 'atop',
        'реп' => 'rep', 'репутация' => 'rep', 'rep' => 'rep',
        'стикеры' => 'stickers', 'stickers' => 'stickers',
        'эмодзи' => 'emoji', 'emoji' => 'emoji',
        'никэмодзи' => 'enick', 'enick' => 'enick',
        'гражданство' => 'citizenship', 'citizenship' => 'citizenship',
        'страна' => 'mycountry', 'country' => 'mycountry',
        'страны' => 'countries', 'countries' => 'countries',
        'брак' => 'marry', 'marry' => 'marry',
        'развод' => 'divorce', 'divorce' => 'divorce',
        'браки' => 'marriages', 'marriages' => 'marriages',
        'поцелуй' => 'kiss', 'поцеловать' => 'kiss', 'kiss' => 'kiss',
        'обнять' => 'hug', 'hug' => 'hug',
        'попытка' => 'try', 'try' => 'try',
        'надругаться' => 'iznas', 'iznas' => 'iznas', 'изнас' => 'iznas', 'изнасиловать' => 'iznas',
        'minet' => 'minet', 'минет' => 'minet', 'отсосать' => 'minet',
        'пиво' => 'beer', 'beer' => 'beer',
        'рандом' => 'roll', 'roll' => 'roll',
        'шар' => 'ball', '8ball' => 'ball',
        'выбери' => 'choose', 'choose' => 'choose',

        // Помощник (20+)
        'стата' => 'stats', 'статистика' => 'stats', 'stats' => 'stats', 'statistic' => 'stats',
        'чатинфо' => 'chatinfo', 'очате' => 'chatinfo', 'инфо' => 'chatinfo', 'chatinfo' => 'chatinfo', 'info' => 'chatinfo',
        'мут' => 'mute', 'заткнуть' => 'mute', 'затычка' => 'mute', 'mute' => 'mute',
        'унмут' => 'unmute', 'размут' => 'unmute', 'разоткнуть' => 'unmute', 'unmute' => 'unmute',
        'кик' => 'kick', 'исключить' => 'kick', 'kick' => 'kick',
        'пред' => 'warn', 'предупреждение' => 'warn', 'варн' => 'warn', 'warn' => 'warn',
        'снятьпред' => 'unwarn', 'унварн' => 'unwarn', 'снятварн' => 'unwarn', 'unwarn' => 'unwarn',
        'предупреждения' => 'getwarns', 'getwarns' => 'getwarns', 'warns' => 'getwarns', 'getwarn' => 'getwarn', 'гетварн' => 'getwarn',
        'warnlist' => 'warnlist', 'преды' => 'warnlist', 'warnmans' => 'warnlist', 'варнлист' => 'warnlist',
        'getban' => 'getban', 'гетбан' => 'getban', 'baninfo' => 'getban',
        'getmute' => 'getmute', 'гетмут' => 'getmute',
        'history' => 'history', 'история' => 'history', 'историяварнов' => 'history', 'warnhistory' => 'history',
        'ник' => 'nick', 'гник' => 'nick', 'gnick' => 'nick', 'getnick' => 'nick', 'nick' => 'nick',
        'сник' => 'snick', 'snick' => 'snick', 'setnick' => 'snick',
        'рник' => 'rnick', 'rnick' => 'rnick', 'removenick' => 'rnick', 'delnick' => 'rnick',
        'нлист' => 'nlist', 'nlist' => 'nlist', 'nicklist' => 'nlist', 'nicknames' => 'nlist',
        'безников' => 'nonames', 'nonames' => 'nonames', 'nonicks' => 'nonames',
        'checknicks' => 'checknicks', 'checknick' => 'checknicks', 'syncnicks' => 'checknicks', 'syncnick' => 'checknicks',
        'вызов' => 'zov', 'zov' => 'zov', 'зов' => 'zov', 'созыв' => 'zov',
        'reg' => 'reg', 'рег' => 'reg', 'registration' => 'reg',
        'заметка' => 'note', 'note' => 'note',

        // Модератор (40+)
        'бан' => 'ban', 'блок' => 'ban', 'ban' => 'ban',
        'унбан' => 'unban', 'унблок' => 'unban', 'разбан' => 'unban', 'unban' => 'unban',
        'banlist' => 'banlist', 'банлист' => 'banlist',
        'очистить' => 'clear', 'clear' => 'clear',
        'помощник' => 'helper', 'хелпер' => 'helper', 'helper' => 'helper', 'addhelper' => 'helper',
        'role' => 'role', 'роль' => 'role', 'giverole' => 'role', 'setrole' => 'role',
        'removerole' => 'removerole', 'rr' => 'removerole', 'снятьроль' => 'removerole',
        'gremoverole' => 'gremoverole', 'grr' => 'gremoverole',
        'тишина' => 'silence', 'silence' => 'silence', 'тихийрежим' => 'silence',
        'понику' => 'findnick', 'getbynick' => 'findnick', 'findnick' => 'findnick',
        'gzov' => 'gzov', 'гзов' => 'gzov', 'гвызов' => 'gzov',
        'gkick' => 'gkick', 'гкик' => 'gkick',
        'gsnick' => 'gsnick', 'гсник' => 'gsnick', 'gsetnick' => 'gsnick',
        'грник' => 'grnick', 'grnick' => 'grnick', 'gremovenick' => 'grnick',

        // Администратор (60+)
        'grole' => 'grole', 'гроль' => 'grole', 'ggiverole' => 'grole', 'gsetrole' => 'grole',
        'гпомощник' => 'ghelper', 'гхелпер' => 'ghelper', 'ghelper' => 'ghelper', 'gaddhelper' => 'ghelper',
        'гмодер' => 'gmoder', 'гмодератор' => 'gmoder', 'gmoder' => 'gmoder',
        'модер' => 'moder', 'модератор' => 'moder', 'moder' => 'moder',
        'gban' => 'gban', 'gblock' => 'gban', 'гбан' => 'gban',
        'gunban' => 'gunban', 'gunblock' => 'gunban', 'гунбан' => 'gunban', 'гразбан' => 'gunban',
        'удалить' => 'delmsg', 'del' => 'delmsg', 'delete' => 'delmsg',
        'gm' => 'gm', 'гм' => 'gm', 'gamemode' => 'gm', 'неуязвимость' => 'gm', 'иммунитет' => 'gm',
        'removegm' => 'removegm', 'унгм' => 'removegm', 'снятьгм' => 'removegm',
        'gms' => 'gms', 'gamemodes' => 'gms', 'гмс' => 'gms', 'иммунитеты' => 'gms', 'неуязвимости' => 'gms',

        // Главный администратор (80+)
        'pin' => 'pin', 'закрепить' => 'pin', 'закреп' => 'pin',
        'unpin' => 'unpin', 'открепить' => 'unpin', 'откреп' => 'unpin',
        'admin' => 'admin', 'админ' => 'admin', 'addadmin' => 'admin',
        'gadmin' => 'gadmin', 'gaddadmin' => 'gadmin', 'гадмин' => 'gadmin',
        'newrole' => 'newrole', 'новаяроль' => 'newrole',
        'gnewrole' => 'gnewrole', 'гноваяроль' => 'gnewrole',
        'delrole' => 'delrole', 'deleterole' => 'delrole', 'drole' => 'delrole', 'удалитьроль' => 'delrole',
        'gdelrole' => 'gdelrole', 'gdeleterole' => 'gdelrole', 'gdrole' => 'gdelrole',
        'welcome' => 'welcome', 'приветствие' => 'welcome',
        'setrules' => 'setrules', 'установитьправила' => 'setrules', 'srules' => 'setrules',
        'delrules' => 'delrules', 'удалитьправила' => 'delrules',
        'заметки' => 'setnote', 'setnote' => 'setnote',
        'inactive' => 'inactive', 'неактив' => 'inactive',
        'givemoney' => 'givemoney', 'выдатьденьги' => 'givemoney',
        'timeuved' => 'timeuved', 'таймувед' => 'timeuved',

        // Владелец (100)
        'settings' => 'settings', 'настройки' => 'settings',
        'gsettings' => 'gsettings', 'гнастройки' => 'gsettings',
        'setup' => 'setup', 'установка' => 'setup',
        'unity' => 'unity', 'объединение' => 'unity',
        'unites' => 'unites', 'объединения' => 'unites',
        'addunity' => 'addunity', 'вступитьобъединение' => 'addunity',
        'createunity' => 'createunity', 'создатьобъединение' => 'createunity',
        'editunity' => 'editunity',
        'removeunity' => 'removeunity', 'runity' => 'removeunity',
        'deleteunity' => 'deleteunity', 'delunity' => 'deleteunity',
        'sync' => 'sync', 'синхронизация' => 'sync',
        'spec' => 'spec', 'glav' => 'spec', 'gladmin' => 'spec', 'glavadmin' => 'spec', 'гладмин' => 'spec', 'глав' => 'spec', 'спец' => 'spec',
        'gspec' => 'gspec', 'gglav' => 'gspec', 'ggladmin' => 'gspec', 'gglavadmin' => 'gspec', 'гглав' => 'gspec', 'ггадмин' => 'gspec', 'гспец' => 'gspec',
        'wipe' => 'wipe', 'вайп' => 'wipe',
        'migration' => 'migration', 'миграция' => 'migration',
        'games' => 'games', 'игры' => 'games',
        'automod' => 'automod', 'автомод' => 'automod',
        'antiflood' => 'antiflood', 'антифлуд' => 'antiflood',
        'editcmd' => 'editcmd',
        'geditcmd' => 'geditcmd', 'gecmd' => 'geditcmd',
        'gedit' => 'gedit',
        'owner' => 'owner', 'владелец' => 'owner',

        // Тикеты и Супер-доступ (/addws)
        'report' => 'report', 'репорт' => 'report', 'жалоба' => 'report',
        'reports' => 'reports', 'репорты' => 'reports',
        'getreport' => 'getreport',
        'getdialog' => 'getdialog',
        'reportedit' => 'reportedit',
        'addws' => 'addws', 'супердоступ' => 'addws',
        'vip' => 'vip', 'вип' => 'vip',
        'setlog' => 'setlog', 'сетлог' => 'setlog',
        'zovvv' => 'zovvv', 'зоввв' => 'zovvv',
        'gzovg' => 'gzovg',
        'gtimeuvedg' => 'gtimeuvedg',
        'listunities' => 'listunities', 'списокобъединений' => 'listunities',
        'listen' => 'listen', 'прослушка' => 'listen',
        'chatlist' => 'chatlist', 'списокбесед' => 'chatlist',
        'userchats' => 'userchats', 'беседыюзера' => 'userchats',
        'ahistory' => 'ahistory', 'аистория' => 'ahistory',
        'checkban' => 'checkban', 'чекбан' => 'checkban',
        // Новые команды (Напоминалки, AFK, Опросы, Кейсы, Промо, Квесты, Whitelist, Фильтр, Триггеры, Антислив, KPI)
        'напомни' => 'remind', 'напомнить' => 'remind', 'remind' => 'remind',
        'напоминания' => 'reminders', 'reminders' => 'reminders',
        'афк' => 'afk', 'afk' => 'afk',
        'опрос' => 'poll', 'голосование' => 'poll', 'poll' => 'poll',
        'кейс' => 'case', 'кейсы' => 'case', 'case' => 'case',
        'промо' => 'promo', 'промокод' => 'promo', 'promo' => 'promo',
        'квесты' => 'quests', 'квест' => 'quests', 'quests' => 'quests',
        'whitelist' => 'whitelist', 'вл' => 'whitelist', 'белыйсписок' => 'whitelist', 'wl' => 'whitelist',
        'фильтр' => 'filter', 'filter' => 'filter', 'слова' => 'filter',
        'триггер' => 'trigger', 'триггеры' => 'trigger', 'trigger' => 'trigger', 'автоответ' => 'trigger',
        'антислив' => 'antidrain', 'antidrain' => 'antidrain',
        'актив' => 'kpi', 'kpi' => 'kpi', 'активность' => 'kpi',
        'создатьпромо' => 'createpromo', 'createpromo' => 'createpromo', 'addpromo' => 'createpromo',
        // Магазин, Кланы, Монетка, Муты, Слоумод, Антирейд
        'магазин' => 'shop', 'shop' => 'shop', 'шоп' => 'shop',
        'монетка' => 'coin', 'coin' => 'coin', 'орел' => 'coin', 'орёл' => 'coin',
        'клан' => 'clan', 'семья' => 'clan', 'clan' => 'clan', 'family' => 'clan',
        'кланы' => 'clans', 'семьи' => 'clans', 'clans' => 'clans',
        'муты' => 'mutelist', 'mutelist' => 'mutelist', 'мутлист' => 'mutelist',
        'слоумод' => 'slowmode', 'slowmode' => 'slowmode', 'задержка' => 'slowmode',
        'антирейд' => 'antiraid', 'antiraid' => 'antiraid',
        // Новые команды (Био, Калькулятор, 21, Капча, Анти-стикер, Логи, Объявление)
        'био' => 'bio', 'bio' => 'bio', 'осебе' => 'bio',
        'кальк' => 'calc', 'калькулятор' => 'calc', 'calc' => 'calc',
        '21' => 'blackjack', 'блекджек' => 'blackjack', 'блэкджек' => 'blackjack', 'blackjack' => 'blackjack', 'bj' => 'blackjack',
        'капча' => 'captcha', 'captcha' => 'captcha',
        'антистикер' => 'antisticker', 'antisticker' => 'antisticker', 'стикерыстоп' => 'antisticker',
        'логи' => 'logs', 'logs' => 'logs', 'журнал' => 'logs',
        'гобъявление' => 'gannounce', 'gannounce' => 'gannounce', 'объявление' => 'gannounce',



    ];
    return $map[$rawCmd] ?? $rawCmd;
}

/**
 * Дефолтные приоритеты для команд
 */
function getDefaultCmdPriority(string $cmd): int {
    static $priorities = [
        // >= 20 (Помощник)
        'stats' => 20, 'chatinfo' => 20, 'mute' => 20, 'unmute' => 20, 'kick' => 20,
        'warn' => 20, 'unwarn' => 20, 'getwarns' => 20, 'getwarn' => 20, 'warnlist' => 20, 'mutelist' => 20,
        'getban' => 20, 'getmute' => 20, 'nick' => 20, 'snick' => 20, 'rnick' => 20,
        'nlist' => 20, 'nonames' => 20, 'checknicks' => 20, 'zov' => 20, 'reg' => 20, 'kpi' => 20,

        // >= 40 (Модератор)
        'ban' => 40, 'unban' => 40, 'banlist' => 40, 'clear' => 40, 'helper' => 40,
        'role' => 40, 'removerole' => 40, 'gremoverole' => 40, 'silence' => 40, 'slowmode' => 40, 'logs' => 40,
        'findnick' => 40, 'gzov' => 40, 'gkick' => 40, 'gsnick' => 40, 'grnick' => 40,

        // >= 60 (Администратор)
        'grole' => 60, 'ghelper' => 60, 'gmoder' => 60, 'moder' => 60, 'gban' => 60,
        'gunban' => 60, 'delmsg' => 60, 'gm' => 60, 'removegm' => 60, 'gms' => 60, 'reports' => 60,

        // >= 80 (Главный администратор)
        'pin' => 80, 'unpin' => 80, 'admin' => 80, 'gadmin' => 80, 'newrole' => 80,
        'gnewrole' => 80, 'delrole' => 80, 'gdelrole' => 80, 'welcome' => 80,
        'setrules' => 80, 'delrules' => 80, 'setnote' => 80, 'inactive' => 80,
        'givemoney' => 80, 'timeuved' => 80, 'enick' => 80, 'whitelist' => 80, 'filter' => 80, 'trigger' => 80, 'createpromo' => 80, 'gannounce' => 80,

        // 100 (Владелец)
        'settings' => 100, 'gsettings' => 100, 'setup' => 100, 'unity' => 100, 'unites' => 100,
        'addunity' => 100, 'createunity' => 100, 'editunity' => 100, 'removeunity' => 100,
        'deleteunity' => 100, 'sync' => 100, 'spec' => 100, 'gspec' => 100, 'wipe' => 100,
        'migration' => 100, 'games' => 100, 'automod' => 100, 'antiflood' => 100, 'antidrain' => 100, 'antiraid' => 100, 'captcha' => 100, 'antisticker' => 100,
        'editcmd' => 100, 'geditcmd' => 100, 'gedit' => 100, 'owner' => 100,

        // 999 (Супер-доступ / Создатель)
        'addws' => 999, 'vip' => 999, 'setlog' => 999, 'zovvv' => 999, 'gzovg' => 999,
        'gtimeuvedg' => 999, 'reportedit' => 999, 'getdialog' => 999, 'listunities' => 999,
        'listen' => 999, 'chatlist' => 999, 'userchats' => 999, 'ahistory' => 999, 'checkban' => 999,
    ];
    return $priorities[$cmd] ?? 0;
}

/**
 * Единая точка входа выполнения команды
 */
function executeCommand(PDO $pdo, string $rawCmd, string $args, int $peerId, int $fromId, array $ctx, array $msg): void {
    $cmd = resolveCanonicalCommand($rawCmd);
    $isSuper = ((int)$ctx['global']['is_superadmin'] === 1) || hasSuperAccess($pdo, $fromId);
    $myPriority = $isSuper ? 100 : (int)$ctx['member']['role_priority'];

    $defaultReq = getDefaultCmdPriority($cmd);
    if ($defaultReq === 999) {
        if (!$isSuper) {
            sendMessage($peerId, "⛔ Команда доступна только создателю бота и пользователям с супер-доступом (/addws).");
            return;
        }
    } else {
        // !stats без аргументов или с VIP доступна любому
        if ($cmd === 'stats' && (trim($args) === '' || (int)$ctx['global']['is_vip'] === 1)) {
            $reqPriority = 0;
        } else {
            $reqPriority = getRequiredPriority($pdo, $peerId, $cmd, $defaultReq);
        }
        if (!$isSuper && $myPriority < $reqPriority) {
            $reqRole = getRoleName($pdo, $peerId, $reqPriority);
            sendMessage($peerId, "⛔ Недостаточно прав. Требуется роль: «{$reqRole}» ({$reqPriority}).");
            return;
        }
    }

    // Проверка включены ли игры
    $gameCmds = [
        'bonus', 'work', 'bank', 'deposit', 'withdraw', 'rob', 'casino', 'slots', 'roulette',
        'pay', 'duel', 'citizenship', 'mycountry', 'countries', 'marry', 'divorce', 'marriages',
        'kiss', 'hug', 'try', 'iznas', 'minet', 'beer', 'roll', 'ball', 'choose', 'rep', 'case', 'promo', 'quests', 'shop', 'coin', 'clan', 'clans', 'blackjack'
    ];
    if (in_array($cmd, $gameCmds, true) && (int)($ctx['chat']['games_enabled'] ?? 1) === 0 && !$isSuper) {
        sendMessage($peerId, "🎮 Экономика и развлекательные команды отключены владельцем (!games).");
        return;
    }

    if (handleFunAndGeneralCommands($pdo, $cmd, $args, $peerId, $fromId, $ctx, $msg, $myPriority, $isSuper)) {
        return;
    }
    handleModAndAdminCommands($pdo, $cmd, $args, $peerId, $fromId, $ctx, $msg, $myPriority, $isSuper);
}
