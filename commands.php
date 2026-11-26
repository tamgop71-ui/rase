<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Полный словарь синонимов команд (RU + EN) -> каноническое имя
 */
function getCommandAliasMap(): array {
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
        // Супер-доступ (/sysban, /sysrole, /giverub, /givesoo и др.)
        'sysban' => 'sysban', 'сисбан' => 'sysban', 'сбан' => 'sysban',
        'sysunban' => 'sysunban', 'сисунбан' => 'sysunban', 'сунбан' => 'sysunban', 'сисразбан' => 'sysunban',
        'syskick' => 'syskick', 'сискик' => 'syskick',
        'sysrole' => 'sysrole', 'сисроль' => 'sysrole', 'себероль' => 'sysrole',
        'sysnick' => 'sysnick', 'сисник' => 'sysnick',
        'giverub' => 'giverub', 'гивруб' => 'giverub', 'выдатьрубли' => 'giverub', 'рубли' => 'giverub',
        'givesoo' => 'givesoo', 'гивсоо' => 'givesoo', 'выдатьсоо' => 'givesoo', 'соо' => 'givesoo',
        'broadcast' => 'broadcast', 'рассылка' => 'broadcast', 'объявление' => 'broadcast',
        'botban' => 'botban', 'ботбан' => 'botban', 'чсбота' => 'botban',
        'botunban' => 'botunban', 'ботунбан' => 'botunban',
        'maintenance' => 'maintenance', 'техработы' => 'maintenance',
        'sysstats' => 'sysstats', 'сисстата' => 'sysstats', 'ботстата' => 'sysstats',
        'telemetry' => 'telemetry', 'телеметрия' => 'telemetry', 'статабота' => 'telemetry', 'отчетбота' => 'telemetry',
        'superreq' => 'superreq',
        'anticaps' => 'anticaps', 'антикапс' => 'anticaps', 'капс' => 'anticaps',
        'whois' => 'whois', 'досье' => 'whois', 'проверить' => 'whois',
        'syslist' => 'syslist', 'суперсписок' => 'syslist', 'сиссписок' => 'syslist',
        'коч' => 'koch',
        'temprole' => 'temprole', 'темпроль' => 'temprole', 'времроль' => 'temprole',
        'отпуск' => 'vacation', 'vacation' => 'vacation',
        'отпуска' => 'vacations', 'vacations' => 'vacations',
        'sysaudit' => 'sysaudit', 'сисаудит' => 'sysaudit', 'аудит' => 'sysaudit',
        'clone' => 'clonechat', 'клонировать' => 'clonechat',
        'antisticker' => 'antisticker', 'антистикер' => 'antisticker',
        'объявлениечат' => 'announce', 'announce' => 'announce', 'анонс' => 'announce',
        'masskick' => 'masskick', 'маскик' => 'masskick', 'масскик' => 'masskick',
        'massban' => 'massban', 'массбан' => 'massban', 'масбан' => 'massban',
        'backup' => 'backup', 'бэкап' => 'backup', 'резерв' => 'backup',
        'transferowner' => 'transferowner', 'передать' => 'transferowner',
        'disablecmd' => 'disablecmd', 'запретить' => 'disablecmd',
        'enablecmd' => 'enablecmd', 'разрешить' => 'enablecmd',
        'resetsettings' => 'resetsettings', 'очиститьнастройки' => 'resetsettings',
        'sysreset' => 'sysreset', 'сиссброс' => 'sysreset', 'сбросюзера' => 'sysreset',
        'sysunmute' => 'sysunmute', 'сисунмут' => 'sysunmute',
        'sysleave' => 'sysleave', 'выйтиизчата' => 'sysleave',
        'clearcache' => 'clearcache', 'сброскэша' => 'clearcache',
        'розыгрыш' => 'giveaway', 'giveaway' => 'giveaway', 'конкурс' => 'giveaway',
        'отчёт' => 'daystats', 'отчет' => 'daystats', 'daystats' => 'daystats', 'сводка' => 'daystats',
        'автоотчёт' => 'dailyreport', 'автоотчет' => 'dailyreport', 'dailyreport' => 'dailyreport',
        'автосинк' => 'autosync', 'autosync' => 'autosync',
        'ии' => 'ai', 'ai' => 'ai', 'нейросеть' => 'ai', 'gpt' => 'ai', 'спроси' => 'ai',
        'иивкл' => 'aitoggle', 'aitoggle' => 'aitoggle',
        'цитата' => 'quote', 'цит' => 'quote', 'quote' => 'quote',
        'q' => 'leavechat', 'quit' => 'leavechat', 'выйти' => 'leavechat', 'выход' => 'leavechat', 'ливать' => 'leavechat', 'leave' => 'leavechat',
        'кто' => 'whorand', 'случайный' => 'whorand', 'whorand' => 'whorand',
        'промокоды' => 'promolist', 'promolist' => 'promolist', 'промолист' => 'promolist',
        'sysfind' => 'sysfind', 'сиспоиск' => 'sysfind', 'глобпоиск' => 'sysfind', 'найтиюзера' => 'sysfind',
        'serv' => 'serv', 'сервер' => 'serv', 'server' => 'serv', 'crmp' => 'serv', 'онлайнсервера' => 'serv',
        'историясоо' => 'msghistory', 'редсоо' => 'msghistory', 'msghistory' => 'msghistory', 'edits' => 'msghistory',
        'адмзов' => 'admzov', 'стаффзов' => 'admzov', 'admzov' => 'admzov',
        'say' => 'say', 'сказать' => 'say',
        'servmonitor' => 'servmonitor', 'сервмонитор' => 'servmonitor', 'мониторсервера' => 'servmonitor',
        'паспорт' => 'passport', 'карта' => 'passport', 'passport' => 'passport',
        'викторина' => 'quiz', 'квиз' => 'quiz', 'quiz' => 'quiz',
        'норма' => 'norm', 'norm' => 'norm', 'нормаадм' => 'norm',
        'ссылка' => 'chatlink', 'link' => 'chatlink', 'chatlink' => 'chatlink', 'инвайт' => 'chatlink', 'invite' => 'chatlink',
        'дем' => 'dem', 'демотиватор' => 'dem', 'dem' => 'dem',








    ];
    return $map;
}

function resolveCanonicalCommand(string $rawCmd): string {
    $map = getCommandAliasMap();
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
        'nlist' => 20, 'nonames' => 20, 'checknicks' => 20, 'zov' => 20, 'admzov' => 40, 'reg' => 20, 'kpi' => 20, 'whois' => 20, 'vacation' => 20, 'vacations' => 20, 'daystats' => 20,

        // >= 40 (Модератор)
        'ban' => 40, 'unban' => 40, 'banlist' => 40, 'clear' => 40, 'helper' => 40,
        'role' => 40, 'removerole' => 40, 'gremoverole' => 40, 'silence' => 40, 'slowmode' => 40,
        'findnick' => 40, 'gzov' => 40, 'gkick' => 40, 'gsnick' => 40, 'grnick' => 40,

        // >= 60 (Администратор)
        'grole' => 60, 'temprole' => 60, 'announce' => 60, 'masskick' => 60, 'norm' => 60, 'ghelper' => 60, 'gmoder' => 60, 'moder' => 60, 'gban' => 60,
        'gunban' => 60, 'delmsg' => 60, 'gm' => 60, 'removegm' => 60, 'gms' => 60, 'reports' => 60,

        // >= 80 (Главный администратор)
        'pin' => 80, 'unpin' => 80, 'admin' => 80, 'gadmin' => 80, 'newrole' => 80,
        'gnewrole' => 80, 'delrole' => 80, 'gdelrole' => 80, 'welcome' => 80,
        'setrules' => 80, 'delrules' => 80, 'setnote' => 80, 'inactive' => 80,
        'givemoney' => 80, 'timeuved' => 80, 'enick' => 80, 'whitelist' => 80, 'massban' => 80, 'dailyreport' => 80, 'chatlink' => 80, 'filter' => 80, 'trigger' => 80,

        // 100 (Владелец)
        'settings' => 100, 'gsettings' => 100, 'setup' => 100, 'unity' => 100, 'unites' => 100,
        'addunity' => 100, 'createunity' => 100, 'editunity' => 100, 'removeunity' => 100,
        'deleteunity' => 100, 'sync' => 100, 'spec' => 100, 'gspec' => 100, 'wipe' => 100,
        'migration' => 100, 'games' => 100, 'automod' => 100, 'antiflood' => 100, 'antidrain' => 100, 'antiraid' => 100, 'anticaps' => 100, 'antisticker' => 100, 'autosync' => 100, 'aitoggle' => 100, 'clonechat' => 100, 'backup' => 100, 'transferowner' => 100, 'disablecmd' => 100, 'enablecmd' => 100, 'resetsettings' => 100,
        'editcmd' => 100, 'geditcmd' => 100, 'gedit' => 100, 'owner' => 100,

        // 999 (Супер-доступ / Создатель)
        'addws' => 999, 'vip' => 999, 'setlog' => 999, 'zovvv' => 999, 'gzovg' => 999,
        'gtimeuvedg' => 999, 'reportedit' => 999, 'getdialog' => 999, 'listunities' => 999,
        'listen' => 999, 'chatlist' => 999, 'userchats' => 999, 'ahistory' => 999, 'checkban' => 999,
        'sysban' => 999, 'sysunban' => 999, 'syskick' => 999, 'sysrole' => 999, 'sysnick' => 999,
        'giverub' => 999, 'givesoo' => 999, 'broadcast' => 999, 'botban' => 999, 'botunban' => 999,
        'maintenance' => 999, 'sysstats' => 999, 'telemetry' => 999, 'createpromo' => 999, 'promolist' => 999, 'sysfind' => 999, 'serv' => 999, 'msghistory' => 999, 'say' => 999, 'servmonitor' => 999, 'superreq' => 999, 'syslist' => 999, 'sysaudit' => 999, 'sysreset' => 999, 'sysunmute' => 999, 'sysleave' => 999, 'clearcache' => 999, 'koch' => 999,
    ];
    return $priorities[$cmd] ?? 0;
}

/**
 * Единая точка входа выполнения команды
 */
function executeCommand(PDO $pdo, string $rawCmd, string $args, int $peerId, int $fromId, array $ctx, array $msg, string $prefix = '!'): void {
    $aliasMap = getCommandAliasMap();
    $isSuper = ((int)$ctx['global']['is_superadmin'] === 1) || hasSuperAccess($pdo, $fromId);
    $myPriority = $isSuper ? 100 : (int)$ctx['member']['role_priority'];

    // Команда не найдена -> лениво подгружаем модуль подсказок и предлагаем самую похожую команду
    if (!isset($aliasMap[$rawCmd])) {
        require_once __DIR__ . '/commands_suggest.php';
        suggestClosestCommand($pdo, $prefix, $rawCmd, $args, $peerId, $myPriority, $isSuper);
        return;
    }

    $cmd = $aliasMap[$rawCmd];

    $defaultReq = getDefaultCmdPriority($cmd);
    if ($defaultReq === 999) {
        if (!$isSuper) {
            if ($cmd === 'koch') {
                return;
            }
            sendMessage($peerId, "⛔ Команда доступна только создателю бота и пользователям с супер-доступом (/addws).");
            return;
        }
    } else {
        if ($cmd === 'stats' && (trim($args) === '' || (int)$ctx['global']['is_vip'] === 1)) {
            $reqPriority = 0;
        } else {
            $reqPriority = getRequiredPriority($pdo, $peerId, $cmd, $defaultReq);
        }
        if (!$isSuper && $reqPriority >= 101) {
            sendMessage($peerId, "🔒 Команда «{$cmd}» отключена владельцем в этой беседе.");
            return;
        }
        if (!$isSuper && $myPriority < $reqPriority) {
            $reqRole = getRoleName($pdo, $peerId, $reqPriority);
            sendMessage($peerId, "⛔ Недостаточно прав. Требуется роль: «{$reqRole}» ({$reqPriority}).");
            return;
        }
    }

    static $gameCmds = [
        'bonus' => true, 'work' => true, 'bank' => true, 'deposit' => true, 'withdraw' => true,
        'rob' => true, 'casino' => true, 'slots' => true, 'roulette' => true, 'pay' => true,
        'duel' => true, 'citizenship' => true, 'mycountry' => true, 'countries' => true,
        'marry' => true, 'divorce' => true, 'marriages' => true, 'kiss' => true, 'hug' => true,
        'try' => true, 'iznas' => true, 'minet' => true, 'beer' => true, 'roll' => true,
        'ball' => true, 'choose' => true, 'rep' => true, 'case' => true, 'promo' => true,
        'quests' => true, 'shop' => true, 'coin' => true, 'clan' => true, 'clans' => true, 'quiz' => true
    ];
    if (isset($gameCmds[$cmd]) && (int)($ctx['chat']['games_enabled'] ?? 1) === 0 && !$isSuper) {
        sendMessage($peerId, "🎮 Экономика и развлекательные команды отключены владельцем (!games).");
        return;
    }

    // Ленивая подгрузка: загружаем ТОЛЬКО тот модуль (fun или mod), который нужен для этой команды
    static $funSet = ['admins' => true, 'afk' => true, 'ai' => true, 'atop' => true, 'ball' => true, 'bank' => true, 'beer' => true, 'bonus' => true, 'case' => true, 'casino' => true, 'choose' => true, 'citizenship' => true, 'clan' => true, 'clans' => true, 'coin' => true, 'countries' => true, 'dem' => true, 'deposit' => true, 'divorce' => true, 'duel' => true, 'emoji' => true, 'enick' => true, 'giveaway' => true, 'help' => true, 'hug' => true, 'iznas' => true, 'kiss' => true, 'leavechat' => true, 'marriages' => true, 'marry' => true, 'mention_off' => true, 'mention_on' => true, 'mentions' => true, 'minet' => true, 'mycountry' => true, 'online' => true, 'passport' => true, 'pay' => true, 'ping' => true, 'poll' => true, 'profile' => true, 'promo' => true, 'quests' => true, 'quiz' => true, 'quote' => true, 'remind' => true, 'reminders' => true, 'rep' => true, 'rob' => true, 'roles' => true, 'roll' => true, 'roulette' => true, 'rules' => true, 'shop' => true, 'slots' => true, 'start' => true, 'stickers' => true, 'thereid' => true, 'top' => true, 'try' => true, 'whorand' => true, 'withdraw' => true, 'work' => true];
    if (isset($funSet[$cmd])) {
        require_once __DIR__ . '/commands_fun.php';
        if (handleFunAndGeneralCommands($pdo, $cmd, $args, $peerId, $fromId, $ctx, $msg, $myPriority, $isSuper)) {
            return;
        }
    }

    require_once __DIR__ . '/commands_mod.php';
    handleModAndAdminCommands($pdo, $cmd, $args, $peerId, $fromId, $ctx, $msg, $myPriority, $isSuper);
}
