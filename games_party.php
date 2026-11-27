<?php
declare(strict_types=1);

/**
 * Игровой модуль для бесед RASE RUSSIA BOT:
 * 1) Игра «Крокодил» (!крокодил) — 0 SQL на обычных сообщениях, слово приходит в ЛС ведущему.
 * 2) Игра «Мафия» (!мафия) — строгая проверка ЛС, раздача ролей в ЛС, ночные действия через ЛС сообщества,
 *    дневное голосование кнопками и авто-таймеры фаз в worker.php.
 */

// =========================================================================
// 1. ИГРА «КРОКОДИЛ»
// =========================================================================

function crocoFile(int $peerId): string {
    return "/tmp/croco_{$peerId}.json";
}

function crocoUserFile(int $vkId): string {
    return "/tmp/croco_u_{$vkId}.json";
}

function crocoNormalize(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = str_replace('ё', 'е', $s);
    $s = (string)preg_replace('/[^a-zа-я0-9\s\-]/u', '', $s);
    $s = (string)preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

function crocoRandomWord(array $exclude = []): string {
    static $words = [
        'вертолёт', 'подводная лодка', 'холодильник', 'микроволновка', 'пылесос', 'светофор', 'шашлык', 'пельмени',
        'арбуз', 'ананас', 'крокодил', 'пингвин', 'жираф', 'бегемот', 'хамелеон', 'ёжик', 'кенгуру', 'дельфин',
        'акула', 'осьминог', 'скорпион', 'паук', 'бабочка', 'космонавт', 'пожарный', 'программист', 'полицейский',
        'хирург', 'стоматолог', 'дальнобойщик', 'таксист', 'официант', 'пилот', 'капитан', 'библиотека', 'вокзал',
        'аэропорт', 'стадион', 'больница', 'автосервис', 'заправка', 'казино', 'банкомат', 'сейф', 'наручники',
        'бронежилет', 'парашют', 'скейтборд', 'велосипед', 'мотоцикл', 'трактор', 'экскаватор', 'трамвай', 'метро',
        'лифт', 'эскалатор', 'компас', 'бинокль', 'телескоп', 'микроскоп', 'гитара', 'пианино', 'барабан', 'скрипка',
        'наушники', 'микрофон', 'клавиатура', 'ноутбук', 'смартфон', 'зарядка', 'флешка', 'принтер', 'телевизор',
        'будильник', 'зеркало', 'расчёска', 'зубная щётка', 'полотенце', 'подушка', 'одеяло', 'зонтик', 'рюкзак',
        'чемодан', 'палатка', 'костёр', 'удочка', 'лыжи', 'коньки', 'снеговик', 'сосулька', 'молния', 'радуга',
        'вулкан', 'водопад', 'пустыня', 'джунгли', 'остров', 'маяк', 'пирамида', 'замок', 'мост', 'фонтан',
        'карусель', 'фейерверк', 'гирлянда', 'сюрприз', 'подарок', 'торт', 'мороженое', 'пицца', 'бургер', 'суши',
        'шоколад', 'попкорн', 'кофеварка', 'чайник', 'сковородка', 'кастрюля', 'вилка', 'штопор', 'ножницы', 'молоток',
        'отвёртка', 'пила', 'лестница', 'лопата', 'грабли', 'ведро', 'метла', 'шахматы', 'домино', 'кубик рубика',
        'бильярд', 'боулинг', 'футбол', 'хоккей', 'баскетбол', 'бокс', 'теннис', 'плавание', 'рыбалка', 'охота',
        'дискотека', 'концерт', 'цирк', 'зоопарк', 'музей', 'театр', 'кинотеатр', 'экзамен', 'отпуск', 'свадьба',
        'день рождения', 'бессонница', 'бутерброд', 'окрошка', 'борщ', 'блины', 'кактус', 'подсолнух', 'ромашка',
        'мухомор', 'паутина', 'призрак', 'вампир', 'дракон', 'робот', 'инопланетянин', 'супергерой', 'пират', 'ниндзя'
    ];
    $pool = array_values(array_diff($words, $exclude));
    if (empty($pool)) $pool = $words;
    return $pool[random_int(0, count($pool) - 1)];
}

function crocoSaveState(int $peerId, array $state): void {
    $f = crocoFile($peerId);
    $tmp = $f . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_UNICODE)) !== false) {
        @rename($tmp, $f);
    }
    if (!empty($state['host_id'])) {
        $uf = crocoUserFile((int)$state['host_id']);
        @file_put_contents($uf, json_encode(['peer_id' => $peerId, 'ts' => time()]));
    }
}

function crocoClearState(int $peerId): void {
    $f = crocoFile($peerId);
    if (is_file($f)) {
        $st = json_decode((string)@file_get_contents($f), true);
        if (is_array($st) && !empty($st['host_id'])) {
            @unlink(crocoUserFile((int)$st['host_id']));
        }
        @unlink($f);
    }
}

function crocoSendWordToHost(int $hostId, int $peerId, string $word, int $swapsLeft): array {
    $kbRows = [];
    if ($swapsLeft > 0) {
        $kbRows[] = [['label' => "🔄 Другое слово (осталось {$swapsLeft})", 'payload' => ['cmd' => '!крокодил сменить'], 'color' => 'primary']];
    }
    $kbRows[] = [['label' => '🛑 Завершить раунд', 'payload' => ['cmd' => '!крокодил стоп'], 'color' => 'negative']];
    $kb = makeInlineKeyboard($kbRows);

    $res = sendMessage(
        $hostId,
        "🐊 [КРОКОДИЛ] Вы — ведущий!\n"
        . "━━━━━━━━━━━━━━━━━━\n"
        . "🔑 Ваше слово: «" . mb_strtoupper($word) . "»\n\n"
        . "📝 Объясните это слово участникам беседы, не используя однокоренные слова!",
        $kb
    );
    if (isset($res['error'])) {
        return ['ok' => false, 'code' => (int)($res['error']['error_code'] ?? 0)];
    }
    return ['ok' => true, 'code' => 0];
}

function handleCrocodileCommand(PDO $pdo, int $peerId, int $fromId, string $args, int $myPriority, bool $isSuper): void {
    $sub = mb_strtolower(trim($args));

    // Если команду нажали из ЛС (сменить слово / стоп) — находим беседу ведущего
    $gamePeerId = $peerId;
    if ($peerId <= 2000000000) {
        $uf = crocoUserFile($fromId);
        if (is_file($uf)) {
            $uInfo = json_decode((string)@file_get_contents($uf), true);
            if (!empty($uInfo['peer_id'])) {
                $gamePeerId = (int)$uInfo['peer_id'];
            }
        }
        if ($gamePeerId <= 2000000000) {
            sendMessage($peerId, "🐊 Игра «Крокодил» запускается в групповой беседе командой !крокодил.");
            return;
        }
    }

    $file = crocoFile($gamePeerId);
    $state = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: null) : null;

    // 1. Остановка игры
    if (in_array($sub, ['стоп', 'stop', 'конец', 'отмена'], true)) {
        if (!$state) {
            sendMessage($peerId, "ℹ️ В беседе сейчас нет активной игры в Крокодила.");
            return;
        }
        if ((int)$state['host_id'] !== $fromId && !$isSuper && $myPriority < 20) {
            sendMessage($peerId, "⛔ Завершить раунд может только ведущий или администратор (20+).");
            return;
        }
        $word = (string)($state['word'] ?? '');
        crocoClearState($gamePeerId);
        sendMessage($gamePeerId, "🛑 Игра «Крокодил» завершена.\n🔑 Загаданное слово было: «{$word}».", makeInlineKeyboard([[
            ['label' => '🐊 Новый раунд', 'payload' => ['cmd' => '!крокодил'], 'color' => 'positive']
        ]]));
        if ($peerId !== $gamePeerId) {
            sendMessage($peerId, "✅ Раунд «Крокодила» завершён.");
        }
        return;
    }

    // 2. Смена слова ведущим
    if (in_array($sub, ['сменить', 'другое', 'swap', 'next'], true)) {
        if (!$state || (int)$state['host_id'] !== $fromId) {
            sendMessage($peerId, "⚠️ Сменить слово может только текущий ведущий.");
            return;
        }
        $swapsLeft = (int)($state['swaps_left'] ?? 0);
        if ($swapsLeft <= 0) {
            sendMessage($peerId, "⚠️ Лимит смены слов на этот раунд исчерпан.");
            return;
        }
        $used = (array)($state['used_words'] ?? []);
        $newWord = crocoRandomWord($used);
        $used[] = $newWord;
        $state['word'] = $newWord;
        $state['norm'] = crocoNormalize($newWord);
        $state['swaps_left'] = $swapsLeft - 1;
        $state['used_words'] = $used;
        crocoSaveState($gamePeerId, $state);

        $dm = crocoSendWordToHost($fromId, $gamePeerId, $newWord, $state['swaps_left']);
        if (!$dm['ok']) {
            sendMessage($gamePeerId, "⚠️ Не удалось отправить новое слово в ЛС. Напишите любое сообщение в ЛС сообщества.");
        }
        return;
    }

    // 3. Повторная отправка слова в ЛС
    if (in_array($sub, ['слово', 'повтор', 'resend'], true)) {
        if (!$state || (int)$state['host_id'] !== $fromId) {
            sendMessage($peerId, "⚠️ Вы не являетесь ведущим текущего раунда.");
            return;
        }
        $dm = crocoSendWordToHost($fromId, $gamePeerId, (string)$state['word'], (int)($state['swaps_left'] ?? 0));
        if ($dm['ok']) {
            sendMessage($gamePeerId, "✅ Слово успешно отправлено ведущему в ЛС! Объясняйте, а участники — угадывайте!");
        } else {
            sendMessage($gamePeerId, "⚠️ ЛС сообщества всё ещё закрыто! Напишите «Привет» в сообщения нашей группы и нажмите кнопку снова.");
        }
        return;
    }

    // 4. Если игра уже идёт (и не истекла по таймауту 15 мин)
    if ($state && (time() - (int)($state['started_at'] ?? 0)) < 900) {
        $hostStr = formatUserMention($pdo, $gamePeerId, (int)$state['host_id']);
        $kb = makeInlineKeyboard([[
            ['label' => '🔄 Прислать слово в ЛС', 'payload' => ['cmd' => '!крокодил слово'], 'color' => 'primary'],
            ['label' => '🛑 Завершить', 'payload' => ['cmd' => '!крокодил стоп'], 'color' => 'negative']
        ]]);
        sendMessage($gamePeerId, "🐊 Игра «Крокодил» уже идёт!\n🎤 Ведущий: {$hostStr}\n💬 Пишите свои догадки прямо в чат!", $kb, ['disable_mentions' => 1]);
        return;
    }

    // 5. Запуск нового раунда!
    $word = crocoRandomWord();
    $dm = crocoSendWordToHost($fromId, $gamePeerId, $word, 2);
    if (!$dm['ok']) {
        $uH = formatUserMention($pdo, $gamePeerId, $fromId);
        $kb = makeInlineKeyboard([[
            ['label' => '🐊 Стать ведущим', 'payload' => ['cmd' => '!крокодил'], 'color' => 'positive']
        ]]);
        sendMessage(
            $gamePeerId,
            "⚠️ {$uH}, я не смог отправить вам загаданное слово в ЛС (запрет на сообщения от группы).\n"
            . "📩 Напишите любое сообщение в личку нашего сообщества и снова нажмите кнопку «Стать ведущим»!",
            $kb,
            ['disable_mentions' => 1]
        );
        return;
    }

    $state = [
        'peer_id'    => $gamePeerId,
        'host_id'    => $fromId,
        'word'       => $word,
        'norm'       => crocoNormalize($word),
        'swaps_left' => 2,
        'used_words' => [$word],
        'started_at' => time(),
    ];
    crocoSaveState($gamePeerId, $state);

    $uH = formatUserMention($pdo, $gamePeerId, $fromId);
    $kb = makeInlineKeyboard([[
        ['label' => '🛑 Завершить раунд', 'payload' => ['cmd' => '!крокодил стоп'], 'color' => 'secondary']
    ]]);
    sendMessage(
        $gamePeerId,
        "🐊 Игра «Крокодил» началась!\n"
        . "━━━━━━━━━━━━━━━━━━\n"
        . "🎤 Ведущий: {$uH} (слово отправлено в ЛС!)\n"
        . "💬 Ведущий объясняет слово, а остальные пишут варианты прямо в чат!\n"
        . "💰 Награда: +2 500 ₽ угадавшему и +1 000 ₽ ведущему.",
        $kb,
        ['disable_mentions' => 1]
    );
}

/**
 * Мгновенная проверка догадки в «Крокодиле» (вызывается из bot.php только если is_file(crocoFile))
 */
function checkCrocodileGuess(PDO $pdo, int $peerId, int $fromId, string $text): bool {
    $file = crocoFile($peerId);
    if (!is_file($file)) return false;
    $state = json_decode((string)@file_get_contents($file), true);
    if (!is_array($state) || empty($state['norm'])) return false;

    // Авто-истечение через 15 минут неактивности
    if ((time() - (int)($state['started_at'] ?? 0)) > 900) {
        crocoClearState($peerId);
        return false;
    }

    $hostId = (int)($state['host_id'] ?? 0);
    if ($fromId === $hostId) return false; // Ведущий не может угадать своё слово

    $guessNorm = crocoNormalize($text);
    if ($guessNorm === '' || $guessNorm !== (string)$state['norm']) {
        return false;
    }

    // УГАДАЛ!
    $word = (string)$state['word'];
    crocoClearState($peerId);

    $pdo->prepare("UPDATE bot_users SET balance = balance + 2500 WHERE vk_id = ?")->execute([$fromId]);
    if ($hostId > 0) {
        $pdo->prepare("UPDATE bot_users SET balance = balance + 1000 WHERE vk_id = ?")->execute([$hostId]);
    }

    $uWin = formatUserMention($pdo, $peerId, $fromId);
    $uHost = formatUserMention($pdo, $peerId, $hostId);
    $kb = makeInlineKeyboard([[
        ['label' => '🐊 Новый раунд (стать ведущим)', 'payload' => ['cmd' => '!крокодил'], 'color' => 'positive']
    ]]);
    sendMessage(
        $peerId,
        "🎉 [КРОКОДИЛ] {$uWin} угадал(а) слово «" . mb_strtoupper($word) . "»!\n"
        . "━━━━━━━━━━━━━━━━━━\n"
        . "💰 {$uWin}: +2 500 ₽\n"
        . "🎤 Ведущий {$uHost}: +1 000 ₽\n\n"
        . "Нажмите кнопку ниже, чтобы начать следующий раунд!",
        $kb,
        ['disable_mentions' => 1]
    );
    return true;
}

// =========================================================================
// 2. ИГРА «МАФИЯ» (с проверкой ЛС, ролями и ночными ходами в ЛС сообщества)
// =========================================================================

define('MAFIA_MIN_PLAYERS', 4);
define('MAFIA_MAX_PLAYERS', 15);
define('MAFIA_NIGHT_SEC', 45);
define('MAFIA_DAY_SEC', 60);
define('MAFIA_WIN_REWARD', 5000);

function mafiaFile(int $peerId): string {
    return "/tmp/mafia_{$peerId}.json";
}

function mafiaUserFile(int $vkId): string {
    return "/tmp/mafia_u_{$vkId}.json";
}

function mafiaRoleTitle(string $role): string {
    return match ($role) {
        'don'       => '🎩 Дон Мафии',
        'mafia'     => '🔫 Мафия',
        'doctor'    => '💉 Доктор',
        'commissar' => '🔍 Комиссар',
        default     => '👤 Мирный житель',
    };
}

function mafiaIsEvil(string $role): bool {
    return $role === 'mafia' || $role === 'don';
}

function mafiaSaveGame(int $peerId, array $g): void {
    $f = mafiaFile($peerId);
    $tmp = $f . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode($g, JSON_UNESCAPED_UNICODE)) !== false) {
        @rename($tmp, $f);
    }
    foreach (array_keys($g['players'] ?? []) as $uid) {
        @file_put_contents(mafiaUserFile((int)$uid), json_encode(['peer_id' => $peerId, 'ts' => time()]));
    }
}

function mafiaClearGame(int $peerId): void {
    $f = mafiaFile($peerId);
    if (is_file($f)) {
        $g = json_decode((string)@file_get_contents($f), true);
        if (is_array($g)) {
            foreach (array_keys($g['players'] ?? []) as $uid) {
                @unlink(mafiaUserFile((int)$uid));
            }
        }
        @unlink($f);
    }
}

/**
 * Отправка списка кнопок с автоматическим делением на сообщения (лимит VK: 10 кнопок в 1 inline-клавиатуре)
 */
function mafiaSendChoiceButtons(int $destPeerId, string $headerText, array $buttons, bool $disableMentions = true): array {
    $chunks = array_chunk($buttons, 8);
    $firstRes = [];
    foreach ($chunks as $idx => $chunk) {
        $rows = [];
        foreach (array_chunk($chunk, 2) as $pair) {
            $rows[] = $pair;
        }
        $kb = makeInlineKeyboard($rows);
        $txt = ($idx === 0) ? $headerText : "⬇️ Продолжение списка (часть " . ($idx + 1) . "):";
        $res = sendMessage($destPeerId, $txt, $kb, ['disable_mentions' => $disableMentions ? 1 : 0]);
        if ($idx === 0) $firstRes = $res;
    }
    return $firstRes;
}

function mafiaAlivePlayers(array $g): array {
    $res = [];
    foreach ($g['players'] as $uid => $p) {
        if (!empty($p['alive'])) {
            $res[(int)$uid] = $p;
        }
    }
    return $res;
}

/**
 * Проверка условий победы: 'mafia' | 'town' | null
 */
function mafiaCheckWin(array $g): ?string {
    $evil = 0;
    $good = 0;
    foreach ($g['players'] as $p) {
        if (empty($p['alive'])) continue;
        if (mafiaIsEvil((string)$p['role'])) $evil++;
        else $good++;
    }
    if ($evil === 0) return 'town';
    if ($evil >= $good) return 'mafia';
    return null;
}

function mafiaFinishGame(PDO $pdo, int $peerId, array $g, string $winner): void {
    mafiaClearGame($peerId);

    $title = ($winner === 'mafia')
        ? "🔫 ПОБЕДА МАФИИ! Город полностью захвачен."
        : "🏆 ПОБЕДА МИРНЫХ ЖИТЕЛЕЙ! Вся мафия обезврежена.";

    $lines = ["🎭 [МАФИЯ] ИГРА ОКОНЧЕНА!", $title, "━━━━━━━━━━━━━━━━━━", "📋 Роли игроков:"];
    foreach ($g['players'] as $uid => $p) {
        $uid = (int)$uid;
        $r = (string)$p['role'];
        $won = ($winner === 'mafia') ? mafiaIsEvil($r) : !mafiaIsEvil($r);
        if ($won) {
            $pdo->prepare("UPDATE bot_users SET balance = balance + ? WHERE vk_id = ?")->execute([MAFIA_WIN_REWARD, $uid]);
        }
        $aliveIcon = !empty($p['alive']) ? '❤️' : '💀';
        $rewardTag = $won ? (' (+' . formatMoney(MAFIA_WIN_REWARD) . ')') : '';
        $lines[] = "{$aliveIcon} [id{$uid}|{$p['name']}] — " . mafiaRoleTitle($r) . $rewardTag;
    }

    $kb = makeInlineKeyboard([[
        ['label' => '🎭 Новая игра в Мафию', 'payload' => ['cmd' => '!мафия'], 'color' => 'positive']
    ]]);
    sendLongMessage($peerId, implode("\n", $lines), $kb, ['disable_mentions' => 1]);
}

function mafiaStartNight(PDO $pdo, int $peerId, array $g): void {
    $g['phase'] = 'night';
    $g['round'] = (int)($g['round'] ?? 0) + 1;
    $g['phase_ends_at'] = time() + MAFIA_NIGHT_SEC;
    $g['night_actions'] = [
        'mafia'     => [], // [actorId => targetId]
        'doctor'    => 0,
        'commissar' => 0,
    ];
    mafiaSaveGame($peerId, $g);

    $alive = mafiaAlivePlayers($g);
    $aliveNames = [];
    foreach ($alive as $uid => $p) {
        $aliveNames[] = "• [id{$uid}|{$p['name']}]";
    }

    sendMessage(
        $peerId,
        "🌙 Ночь #{$g['round']}! Город засыпает...\n"
        . "━━━━━━━━━━━━━━━━━━\n"
        . "🕵️ Активные роли (Мафия, Доктор, Комиссар) получили кнопки выбора в ЛС сообщества!\n"
        . "⏳ Время на ход: " . MAFIA_NIGHT_SEC . " сек.\n\n"
        . "❤️ Живые игроки (" . count($alive) . "):\n" . implode("\n", $aliveNames),
        null,
        ['disable_mentions' => 1]
    );

    // Рассылаем ночные кнопки в ЛС активным ролям
    foreach ($alive as $uid => $p) {
        $role = (string)$p['role'];
        if (mafiaIsEvil($role)) {
            $btns = [];
            foreach ($alive as $tid => $tp) {
                if (!mafiaIsEvil((string)$tp['role'])) {
                    $btns[] = ['label' => "🔪 {$tp['name']}", 'payload' => ['cmd' => "!мафия ход {$tid}"], 'color' => 'negative'];
                }
            }
            if (!empty($btns)) {
                mafiaSendChoiceButtons($uid, "🌙 [Ночь #{$g['round']}] Вы — " . mafiaRoleTitle($role) . ".\nВыберите жертву для убийства:", $btns);
            }
        } elseif ($role === 'doctor') {
            $lastHealed = (int)($g['last_doctor_target'] ?? 0);
            $btns = [];
            foreach ($alive as $tid => $tp) {
                if ($tid === $lastHealed && $tid === $uid) continue; // Нельзя лечить себя 2 ночи подряд
                $btns[] = ['label' => "💉 {$tp['name']}", 'payload' => ['cmd' => "!мафия ход {$tid}"], 'color' => 'positive'];
            }
            if (!empty($btns)) {
                mafiaSendChoiceButtons($uid, "🌙 [Ночь #{$g['round']}] Вы — 💉 Доктор.\nВыберите, кого вылечить этой ночью:", $btns);
            }
        } elseif ($role === 'commissar') {
            $btns = [];
            foreach ($alive as $tid => $tp) {
                if ($tid === $uid) continue;
                $btns[] = ['label' => "🔍 {$tp['name']}", 'payload' => ['cmd' => "!мафия ход {$tid}"], 'color' => 'primary'];
            }
            if (!empty($btns)) {
                mafiaSendChoiceButtons($uid, "🌙 [Ночь #{$g['round']}] Вы — 🔍 Комиссар.\nВыберите игрока для проверки на причастность к мафии:", $btns);
            }
        }
    }
}

function mafiaAreAllNightActionsDone(array $g): bool {
    $alive = mafiaAlivePlayers($g);
    $acts = $g['night_actions'] ?? [];
    foreach ($alive as $uid => $p) {
        $role = (string)$p['role'];
        if (mafiaIsEvil($role)) {
            if (empty($acts['mafia'][$uid])) return false;
        } elseif ($role === 'doctor') {
            if (empty($acts['doctor'])) return false;
        } elseif ($role === 'commissar') {
            if (empty($acts['commissar'])) return false;
        }
    }
    return true;
}

function mafiaResolveNight(PDO $pdo, int $peerId, array $g): void {
    $acts = $g['night_actions'] ?? [];
    $mafiaVotes = (array)($acts['mafia'] ?? []);
    $docTarget  = (int)($acts['doctor'] ?? 0);
    $g['last_doctor_target'] = $docTarget;

    // Определяем жертву мафии (по большинству голосов мафиози, при равенстве — голос Дона или первый)
    $killTarget = 0;
    if (!empty($mafiaVotes)) {
        $counts = [];
        foreach ($mafiaVotes as $mUid => $tUid) {
            $tUid = (int)$tUid;
            if ($tUid > 0) $counts[$tUid] = ($counts[$tUid] ?? 0) + 1;
        }
        if (!empty($counts)) {
            arsort($counts);
            $topCount = reset($counts);
            $tied = array_keys(array_filter($counts, fn($c) => $c === $topCount));
            if (count($tied) === 1) {
                $killTarget = (int)$tied[0];
            } else {
                // Приоритет Дона
                foreach ($mafiaVotes as $mUid => $tUid) {
                    if (($g['players'][(int)$mUid]['role'] ?? '') === 'don' && in_array((int)$tUid, $tied, true)) {
                        $killTarget = (int)$tUid;
                        break;
                    }
                }
                if ($killTarget === 0) $killTarget = (int)$tied[array_rand($tied)];
            }
        }
    }

    $morningReport = "☀️ Наступило утро (День #{$g['round']})!\n━━━━━━━━━━━━━━━━━━\n";
    if ($killTarget > 0 && !empty($g['players'][$killTarget]['alive'])) {
        if ($killTarget === $docTarget) {
            $morningReport .= "💉 Мафия напала ночью, но Доктор вовремя спас жертву! Все проснулись живыми!";
        } else {
            $g['players'][$killTarget]['alive'] = false;
            $vName = $g['players'][$killTarget]['name'];
            $vRole = mafiaRoleTitle((string)$g['players'][$killTarget]['role']);
            $morningReport .= "💀 Этой ночью мафия убила [id{$killTarget}|{$vName}]!\n🎭 Роль погибшего: {$vRole}";
        }
    } else {
        $morningReport .= "🕊 Ночь прошла спокойно — никто не погиб.";
    }

    // Проверка победы сразу после ночи
    $win = mafiaCheckWin($g);
    if ($win !== null) {
        sendMessage($peerId, $morningReport, null, ['disable_mentions' => 1]);
        mafiaFinishGame($pdo, $peerId, $g, $win);
        return;
    }

    // Переходим к дневному голосованию
    $g['phase'] = 'day';
    $g['phase_ends_at'] = time() + MAFIA_DAY_SEC;
    $g['day_votes'] = []; // [voterId => targetId (0 = skip)]
    mafiaSaveGame($peerId, $g);

    $alive = mafiaAlivePlayers($g);
    $btns = [];
    foreach ($alive as $uid => $p) {
        $btns[] = ['label' => "⚖️ {$p['name']}", 'payload' => ['cmd' => "!мафия голос {$uid}"], 'color' => 'secondary'];
    }
    $btns[] = ['label' => '🤷 Пропустить голосование', 'payload' => ['cmd' => '!мафия голос 0'], 'color' => 'primary'];

    $dayText = $morningReport . "\n\n⚖️ Обсудите, кто может быть мафией, и проголосуйте кнопкой ниже (" . MAFIA_DAY_SEC . " сек.):";
    mafiaSendChoiceButtons($peerId, $dayText, $btns, true);
}

function mafiaResolveDay(PDO $pdo, int $peerId, array $g): void {
    $votes = (array)($g['day_votes'] ?? []);
    $counts = [];
    $skips = 0;
    foreach ($votes as $vUid => $tUid) {
        $tUid = (int)$tUid;
        if ($tUid === 0) $skips++;
        else $counts[$tUid] = ($counts[$tUid] ?? 0) + 1;
    }

    $executedId = 0;
    if (!empty($counts)) {
        arsort($counts);
        $topVotes = reset($counts);
        $leaders = array_keys(array_filter($counts, fn($c) => $c === $topVotes));
        if (count($leaders) === 1 && $topVotes > $skips) {
            $executedId = (int)$leaders[0];
        }
    }

    if ($executedId > 0 && !empty($g['players'][$executedId]['alive'])) {
        $g['players'][$executedId]['alive'] = false;
        $eName = $g['players'][$executedId]['name'];
        $eRole = mafiaRoleTitle((string)$g['players'][$executedId]['role']);
        sendMessage($peerId, "⚖️ По итогам голосования город казнил [id{$executedId}|{$eName}]!\n🎭 Его(её) роль: {$eRole}", null, ['disable_mentions' => 1]);
    } else {
        sendMessage($peerId, "⚖️ Город не пришёл к единому решению (ничья или большинство воздержалось) — сегодня никто не казнён.");
    }

    $win = mafiaCheckWin($g);
    if ($win !== null) {
        mafiaFinishGame($pdo, $peerId, $g, $win);
        return;
    }

    mafiaStartNight($pdo, $peerId, $g);
}

function handleMafiaCommand(PDO $pdo, int $peerId, int $fromId, string $args, int $myPriority, bool $isSuper, array $ctx): void {
    $raw = trim($args);
    $parts = preg_split('/\s+/u', mb_strtolower($raw));
    $sub = $parts[0] ?? '';
    $arg2 = isset($parts[1]) ? (int)$parts[1] : -1;

    // 1. Ночной ход из ЛС сообщества: !мафия ход [targetId]
    if ($sub === 'ход' || $sub === 'action') {
        $uf = mafiaUserFile($fromId);
        if (!is_file($uf)) {
            sendMessage($peerId, "⚠️ Вы сейчас не участвуете в активной игре «Мафия».");
            return;
        }
        $uMap = json_decode((string)@file_get_contents($uf), true);
        $gamePeerId = (int)($uMap['peer_id'] ?? 0);
        if ($gamePeerId <= 2000000000 || !is_file(mafiaFile($gamePeerId))) {
            sendMessage($peerId, "⚠️ Игра уже завершена.");
            return;
        }
        $g = json_decode((string)@file_get_contents(mafiaFile($gamePeerId)), true);
        if (!is_array($g) || ($g['phase'] ?? '') !== 'night') {
            sendMessage($peerId, "⏳ Сейчас не ночь — ночные действия недоступны.");
            return;
        }
        $me = $g['players'][$fromId] ?? null;
        if (!$me || empty($me['alive'])) {
            sendMessage($peerId, "💀 Выбывшие игроки не могут делать ход.");
            return;
        }
        if ($arg2 <= 0 || empty($g['players'][$arg2]['alive'])) {
            sendMessage($peerId, "⚠️ Выбранный игрок уже выбыл или не участвует.");
            return;
        }

        $role = (string)$me['role'];
        $tName = (string)$g['players'][$arg2]['name'];

        if (mafiaIsEvil($role)) {
            if (mafiaIsEvil((string)$g['players'][$arg2]['role'])) {
                sendMessage($peerId, "⚠️ Нельзя стрелять по своему союзнику из мафии!");
                return;
            }
            $g['night_actions']['mafia'][$fromId] = $arg2;
            sendMessage($fromId, "🔪 Выбор принят! Ваша цель на эту ночь: {$tName}.");
        } elseif ($role === 'doctor') {
            if ($arg2 === $fromId && (int)($g['last_doctor_target'] ?? 0) === $fromId) {
                sendMessage($fromId, "⚠️ Нельзя лечить самого себя две ночи подряд!");
                return;
            }
            $g['night_actions']['doctor'] = $arg2;
            sendMessage($fromId, "💉 Выбор принят! Вы отправились лечить игрока {$tName}.");
        } elseif ($role === 'commissar') {
            if (!empty($g['night_actions']['commissar'])) {
                sendMessage($fromId, "ℹ️ Вы уже проверили игрока этой ночью.");
                return;
            }
            $g['night_actions']['commissar'] = $arg2;
            $isTargetEvil = mafiaIsEvil((string)$g['players'][$arg2]['role']);
            sendMessage($fromId, $isTargetEvil
                ? "🔍 Результат проверки {$tName}: 🔴 ЭТО МАФИЯ!"
                : "🔍 Результат проверки {$tName}: 🟢 Мирный житель (чист).");
        } else {
            sendMessage($fromId, "😴 Мирные жители спят ночью.");
            return;
        }

        mafiaSaveGame($gamePeerId, $g);
        if (mafiaAreAllNightActionsDone($g)) {
            mafiaResolveNight($pdo, $gamePeerId, $g);
        }
        return;
    }

    // Все остальные подкоманды работают в групповой беседе
    if ($peerId <= 2000000000) {
        sendMessage($peerId, "🎭 Команда !мафия запускается в групповой беседе. В ЛС приходят роли и кнопки ночных действий.");
        return;
    }

    $file = mafiaFile($peerId);
    $g = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: null) : null;

    // 2. Остановка игры
    if (in_array($sub, ['стоп', 'stop', 'отмена', 'cancel'], true)) {
        if (!$g) {
            sendMessage($peerId, "ℹ️ В беседе нет активной игры в Мафию.");
            return;
        }
        if ((int)($g['host_id'] ?? 0) !== $fromId && !$isSuper && $myPriority < 20) {
            sendMessage($peerId, "⛔ Остановить игру может только её создатель или администратор (20+).");
            return;
        }
        mafiaClearGame($peerId);
        sendMessage($peerId, "🛑 Игра «Мафия» остановлена.");
        return;
    }

    // 3. Присоединиться к лобби (со СТРОГОЙ проверкой ЛС!)
    if (in_array($sub, ['вход', 'войти', 'join', '+'], true)) {
        if (!$g || ($g['phase'] ?? '') !== 'lobby') {
            sendMessage($peerId, "ℹ️ Сейчас нет открытого набора в Мафию. Создайте лобби командой !мафия.");
            return;
        }
        if (isset($g['players'][$fromId])) {
            sendMessage($peerId, "ℹ️ " . formatUserMention($pdo, $peerId, $fromId) . ", вы уже в списке игроков!", null, ['disable_mentions' => 1]);
            return;
        }
        if (count($g['players']) >= MAFIA_MAX_PLAYERS) {
            sendMessage($peerId, "⚠️ Лобби заполнено (максимум " . MAFIA_MAX_PLAYERS . " игроков).");
            return;
        }

        // Проверяем, может ли бот писать игроку в ЛС (если нет — играть НЕЛЬЗЯ!)
        $chatTitle = (string)($ctx['chat']['title'] ?? "Беседа #{$peerId}");
        $dmTest = sendMessage($fromId, "🎭 Вы присоединились к игре «Мафия» в беседе «{$chatTitle}»!\nОжидайте старта — сюда придёт ваша тайная роль и кнопки ночных ходов.");
        if (isset($dmTest['error'])) {
            $uA = formatUserMention($pdo, $peerId, $fromId);
            sendMessage(
                $peerId,
                "⛔ {$uA} не может участвовать в Мафии — бот не смог отправить сообщение в ЛС!\n"
                . "📩 Чтобы играть, напишите любое сообщение (например «Привет») в ЛС нашего сообщества и снова нажмите «🎭 Присоединиться».",
                null,
                ['disable_mentions' => 1]
            );
            return;
        }

        $pName = trim((string)($ctx['member']['nickname'] ?? ''))
              ?: trim((string)($ctx['global']['first_name'] ?? '') . ' ' . (string)($ctx['global']['last_name'] ?? ''))
              ?: "Игрок {$fromId}";
        $g['players'][$fromId] = ['name' => mb_substr($pName, 0, 28), 'role' => 'civilian', 'alive' => true];
        mafiaSaveGame($peerId, $g);

        $cnt = count($g['players']);
        $kb = makeInlineKeyboard([[
            ['label' => '🎭 Присоединиться', 'payload' => ['cmd' => '!мафия вход'], 'color' => 'positive'],
            ['label' => "🚀 Старт ({$cnt}/" . MAFIA_MIN_PLAYERS . "+)", 'payload' => ['cmd' => '!мафия старт'], 'color' => 'primary'],
            ['label' => '❌ Отмена', 'payload' => ['cmd' => '!мафия стоп'], 'color' => 'negative'],
        ]]);
        sendMessage($peerId, "✅ [id{$fromId}|{$pName}] вошёл(ла) в игру! ЛС подтверждено.\n👥 Участников: {$cnt} (минимум " . MAFIA_MIN_PLAYERS . ").", $kb, ['disable_mentions' => 1]);
        return;
    }

    // 4. Выход из лобби до старта
    if (in_array($sub, ['выход', 'выйти', 'leave', '-'], true)) {
        if (!$g || ($g['phase'] ?? '') !== 'lobby' || !isset($g['players'][$fromId])) {
            sendMessage($peerId, "ℹ️ Вы не в лобби Мафии.");
            return;
        }
        unset($g['players'][$fromId]);
        @unlink(mafiaUserFile($fromId));
        mafiaSaveGame($peerId, $g);
        sendMessage($peerId, "🚪 " . formatUserMention($pdo, $peerId, $fromId) . " покинул(а) лобби. Осталось игроков: " . count($g['players']) . ".", null, ['disable_mentions' => 1]);
        return;
    }

    // 5. Старт игры и раздача ролей в ЛС (если кто-то не получил роль в ЛС — он исключается!)
    if (in_array($sub, ['старт', 'start', 'начать'], true)) {
        if (!$g || ($g['phase'] ?? '') !== 'lobby') {
            sendMessage($peerId, "ℹ️ Нет открытого лобби Мафии. Создайте его командой !мафия.");
            return;
        }
        if ((int)$g['host_id'] !== $fromId && !$isSuper && $myPriority < 20) {
            sendMessage($peerId, "⛔ Запустить игру может создатель лобби или администратор (20+).");
            return;
        }
        if (count($g['players']) < MAFIA_MIN_PLAYERS) {
            sendMessage($peerId, "⚠️ Для старта нужно минимум " . MAFIA_MIN_PLAYERS . " игрока (сейчас: " . count($g['players']) . ").");
            return;
        }

        // Формируем колоду ролей
        $playerIds = array_map('intval', array_keys($g['players']));
        shuffle($playerIds);
        $n = count($playerIds);
        $mafiaCount = ($n >= 9) ? 3 : (($n >= 6) ? 2 : 1);

        $rolesDeck = [];
        for ($i = 0; $i < $mafiaCount; $i++) {
            $rolesDeck[] = ($i === 0) ? 'don' : 'mafia';
        }
        $rolesDeck[] = 'doctor';
        $rolesDeck[] = 'commissar';
        while (count($rolesDeck) < $n) {
            $rolesDeck[] = 'civilian';
        }
        shuffle($rolesDeck);

        foreach ($playerIds as $idx => $uid) {
            $g['players'][$uid]['role'] = $rolesDeck[$idx];
            $g['players'][$uid]['alive'] = true;
        }

        // Собираем список союзников для мафии
        $mafiaTeam = [];
        foreach ($g['players'] as $uid => $p) {
            if (mafiaIsEvil((string)$p['role'])) {
                $mafiaTeam[(int)$uid] = $p['name'] . " (" . mafiaRoleTitle((string)$p['role']) . ")";
            }
        }

        // Раздаём роли в ЛС и проверяем доставку КАЖДОМУ!
        $failedDm = [];
        $chatTitle = (string)($ctx['chat']['title'] ?? "Беседа #{$peerId}");
        foreach ($g['players'] as $uid => $p) {
            $uid = (int)$uid;
            $r = (string)$p['role'];
            $desc = match ($r) {
                'don'       => "Вы — глава мафии! Каждую ночь выбирайте жертву в ЛС. При равенстве голосов мафии решающий голос за вами.",
                'mafia'     => "Вы — член мафии! Каждую ночь выбирайте жертву вместе с командой и скрывайтесь днём.",
                'doctor'    => "Вы — Доктор! Каждую ночь вы можете спасти одного игрока от убийства мафии (себя нельзя лечить 2 ночи подряд).",
                'commissar' => "Вы — Комиссар! Каждую ночь проверяйте одного игрока, чтобы узнать, мафия он или мирный.",
                default     => "Вы — Мирный житель! Днём вычисляйте мафию и голосуйте за её казнь.",
            };
            $teamNote = (mafiaIsEvil($r) && count($mafiaTeam) > 1)
                ? ("\n\n🤝 Ваша команда мафии:\n• " . implode("\n• ", $mafiaTeam))
                : "";

            $dmRes = sendMessage($uid, "🎭 [МАФИЯ] Игра в беседе «{$chatTitle}» началась!\n━━━━━━━━━━━━━━━━━━\nВаша роль: " . mafiaRoleTitle($r) . "\n\n{$desc}{$teamNote}");
            if (isset($dmRes['error'])) {
                $failedDm[] = $uid;
            }
        }

        if (!empty($failedDm)) {
            $badNames = [];
            foreach ($failedDm as $fUid) {
                $badNames[] = "[id{$fUid}|" . ($g['players'][$fUid]['name'] ?? "id{$fUid}") . "]";
                unset($g['players'][$fUid]);
                @unlink(mafiaUserFile($fUid));
            }
            mafiaSaveGame($peerId, $g);
            sendMessage(
                $peerId,
                "⚠️ Игроки " . implode(', ', $badNames) . " не получили сообщение о роли в ЛС и были исключены из лобби (без ЛС играть нельзя)!\n"
                . "👥 Осталось в лобби: " . count($g['players']) . ". Нажмите «🚀 Старт» снова (если осталось " . MAFIA_MIN_PLAYERS . "+ человек).",
                makeInlineKeyboard([[
                    ['label' => '🎭 Присоединиться', 'payload' => ['cmd' => '!мафия вход'], 'color' => 'positive'],
                    ['label' => '🚀 Старт', 'payload' => ['cmd' => '!мафия старт'], 'color' => 'primary'],
                ]]),
                ['disable_mentions' => 1]
            );
            return;
        }

        sendMessage(
            $peerId,
            "🎭 Игра «Мафия» стартовала! Роли успешно доставлены всем {$n} игрокам в ЛС!\n"
            . "📊 В городе: 🔫 Мафия ({$mafiaCount}), 💉 Доктор (1), 🔍 Комиссар (1), 👤 Мирные (" . ($n - $mafiaCount - 2) . ").\n"
            . "💰 Награда победителям: +" . formatMoney(MAFIA_WIN_REWARD) . " каждому!"
        );
        mafiaStartNight($pdo, $peerId, $g);
        return;
    }

    // 6. Дневное голосование в беседе: !мафия голос [targetId / 0]
    if ($sub === 'голос' || $sub === 'vote') {
        if (!$g || ($g['phase'] ?? '') !== 'day') {
            sendMessage($peerId, "⏳ Сейчас не этап дневного голосования.");
            return;
        }
        if (empty($g['players'][$fromId]['alive'])) {
            sendMessage($peerId, "⚠️ Голосовать могут только живые участники игры.");
            return;
        }
        if ($arg2 < 0 || ($arg2 > 0 && empty($g['players'][$arg2]['alive']))) {
            sendMessage($peerId, "⚠️ Некорректная цель голосования.");
            return;
        }
        $g['day_votes'][$fromId] = $arg2;
        mafiaSaveGame($peerId, $g);

        $aliveCount = count(mafiaAlivePlayers($g));
        $votedCount = count($g['day_votes']);
        $vName = $g['players'][$fromId]['name'];
        $tText = ($arg2 === 0) ? "воздержался(лась)" : ("проголосовал(а) против " . $g['players'][$arg2]['name']);
        sendMessage($peerId, "🗳 {$vName} {$tText} ({$votedCount}/{$aliveCount}).");

        if ($votedCount >= $aliveCount) {
            mafiaResolveDay($pdo, $peerId, $g);
        }
        return;
    }

    // 7. Создание нового лобби или статус текущей игры
    if ($g) {
        if (($g['phase'] ?? '') === 'lobby') {
            $cnt = count($g['players']);
            $pNames = array_map(fn($p) => "• {$p['name']}", $g['players']);
            $kb = makeInlineKeyboard([[
                ['label' => '🎭 Присоединиться', 'payload' => ['cmd' => '!мафия вход'], 'color' => 'positive'],
                ['label' => "🚀 Старт ({$cnt}/" . MAFIA_MIN_PLAYERS . "+)", 'payload' => ['cmd' => '!мафия старт'], 'color' => 'primary'],
                ['label' => '❌ Отмена', 'payload' => ['cmd' => '!мафия стоп'], 'color' => 'negative'],
            ]]);
            sendMessage($peerId, "🎭 Идёт набор в игру «Мафия» ({$cnt}/" . MAFIA_MAX_PLAYERS . "):\n" . implode("\n", $pNames) . "\n\n📩 Важно: для участия у вас должно быть открыто ЛС с нашим сообществом!", $kb);
        } else {
            $rem = max(0, (int)($g['phase_ends_at'] ?? 0) - time());
            $phLabel = ($g['phase'] === 'night') ? "🌙 Ночь #{$g['round']} (ходы в ЛС)" : "☀️ День #{$g['round']} (голосование)";
            sendMessage($peerId, "🎭 В беседе уже идёт игра «Мафия»!\nФаза: {$phLabel} | Осталось ~{$rem} сек.\nОстановить: !мафия стоп");
        }
        return;
    }

    // Создаём новое лобби и сразу проверяем ЛС у создателя!
    $chatTitle = (string)($ctx['chat']['title'] ?? "Беседа #{$peerId}");
    $dmTest = sendMessage($fromId, "🎭 Вы открыли набор в игру «Мафия» в беседе «{$chatTitle}»! ЛС работает — вы автоматически добавлены в лобби.");
    $players = [];
    $hostNote = '';
    if (!isset($dmTest['error'])) {
        $hName = trim((string)($ctx['member']['nickname'] ?? ''))
              ?: trim((string)($ctx['global']['first_name'] ?? '') . ' ' . (string)($ctx['global']['last_name'] ?? ''))
              ?: "Игрок {$fromId}";
        $players[$fromId] = ['name' => mb_substr($hName, 0, 28), 'role' => 'civilian', 'alive' => true];
        $hostNote = "✅ Организатор [id{$fromId}|{$hName}] уже в игре (ЛС подтверждено).";
    } else {
        $hostNote = "⚠️ Организатор не добавлен (закрыто ЛС с группой — напишите «Привет» в ЛС сообщества и нажмите «Присоединиться»).";
    }

    $g = [
        'peer_id'            => $peerId,
        'host_id'            => $fromId,
        'phase'              => 'lobby',
        'round'              => 0,
        'phase_ends_at'      => time() + 600,
        'players'            => $players,
        'night_actions'      => [],
        'day_votes'          => [],
        'last_doctor_target' => 0,
    ];
    mafiaSaveGame($peerId, $g);

    $kb = makeInlineKeyboard([[
        ['label' => '🎭 Присоединиться', 'payload' => ['cmd' => '!мафия вход'], 'color' => 'positive'],
        ['label' => '🚀 Старт (' . count($players) . '/' . MAFIA_MIN_PLAYERS . '+)', 'payload' => ['cmd' => '!мафия старт'], 'color' => 'primary'],
        ['label' => '❌ Отмена', 'payload' => ['cmd' => '!мафия стоп'], 'color' => 'negative'],
    ]]);
    sendMessage(
        $peerId,
        "🎭 Открыт набор в игру «МАФИЯ»!\n"
        . "━━━━━━━━━━━━━━━━━━\n"
        . "{$hostNote}\n"
        . "👥 Минимум игроков: " . MAFIA_MIN_PLAYERS . " | Награда победителям: +" . formatMoney(MAFIA_WIN_REWARD) . "\n"
        . "📩 Для игры обязательно откройте ЛС с группой (напишите любое сообщение в личку сообщества) — туда придут ваша роль и кнопки ночных действий!",
        $kb,
        ['disable_mentions' => 1]
    );
}

/**
 * Фоновый тикер Мафии (вызывается из worker.php каждые 15 секунд, 0 SQL если игр нет!)
 */
function processMafiaTimeouts(PDO $pdo): void {
    $files = glob('/tmp/mafia_2*.json');
    if (!$files) return;
    $now = time();
    foreach ($files as $f) {
        $g = json_decode((string)@file_get_contents($f), true);
        if (!is_array($g) || empty($g['peer_id'])) continue;
        $pId = (int)$g['peer_id'];
        $ends = (int)($g['phase_ends_at'] ?? 0);
        if ($ends <= 0 || $now < $ends) continue;

        $phase = (string)($g['phase'] ?? '');
        if ($phase === 'lobby') {
            mafiaClearGame($pId);
            sendMessage($pId, "⏳ Набор в игру «Мафия» отменён из-за неактивности (прошло 10 минут).");
        } elseif ($phase === 'night') {
            mafiaResolveNight($pdo, $pId, $g);
        } elseif ($phase === 'day') {
            mafiaResolveDay($pdo, $pId, $g);
        }
    }
}
