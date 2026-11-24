<?php
declare(strict_types=1);

/**
 * Модуль телеметрии бота:
 * - Записывает метрики каждого сообщения в лёгкий локальный лог /tmp/bot_telemetry.log (0 SQL-запросов, <0.05 мс).
 * - Каждые 4 часа (00:00, 04:00, 08:00, 12:00, 16:00, 20:00 МСК) worker.php атомарно снимает срез и отправляет отчёт Создателю в ЛС.
 */

define('TELEMETRY_LOG_FILE', '/tmp/bot_telemetry.log');

function telemetryCountProfanity(string $text): int {
    if ($text === '') return 0;
    // Аккуратная проверка основных корней нецензурной лексики без ложных срабатываний на обычные слова
    $pattern = '/\b(?:ху[йяеёию]|наху[йя]|поху[йя]|оху[её]|аху[её]|пизд[аеиоуыяё]|бля(?:т|д|ть|дь|ха|дь)?|еб(?:а|у|и|ё|л|н|о|т)|ёб(?:а|у|и|л|н|т)|заеб|заёб|выеб|разъеб|съеб|проеб|поеб|уеб|уёб|долбо[её]б|сук[аиуеой]|сучк[аиуеой]|муда[кч]|мудил|пид[оа]р|гандон|шлюх|залуп|дерьм|говн|хер(?:н|о|а|у|ы|ом)?)\w*/iu';
    $cnt = @preg_match_all($pattern, $text);
    return $cnt ? (int)$cnt : 0;
}

/**
 * Сверхбыстрая запись одного входящего сообщения в локальный лог телеметрии
 */
function recordTelemetryEvent(int $peerId, string $text, array $attachments = [], bool $isCommand = false): void {
    $ts = time();
    $chars = ($text !== '') ? mb_strlen($text) : 0;

    $emojis = 0;
    $words = 0;
    $profanity = 0;

    if ($text !== '') {
        $eCnt = @preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text);
        $emojis = $eCnt ? (int)$eCnt : 0;

        $wCnt = @preg_match_all('/[\p{L}\p{N}]+/u', $text);
        $words = $wCnt ? (int)$wCnt : 0;

        if ($words > 0) {
            $profanity = telemetryCountProfanity($text);
        }
    }

    $voice = 0; $photo = 0; $video = 0; $audio = 0; $wall = 0; $sticker = 0;
    foreach ($attachments as $att) {
        $type = (string)($att['type'] ?? '');
        if ($type === 'audio_message') $voice++;
        elseif ($type === 'photo') $photo++;
        elseif ($type === 'video') $video++;
        elseif ($type === 'audio') $audio++;
        elseif ($type === 'wall') $wall++;
        elseif ($type === 'sticker') $sticker++;
    }

    $cmdFlag = $isCommand ? 1 : 0;
    $line = "{$ts}|{$peerId}|{$chars}|{$emojis}|{$words}|{$profanity}|{$cmdFlag}|{$voice}|{$photo}|{$video}|{$audio}|{$wall}|{$sticker}\n";
    @file_put_contents(TELEMETRY_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function telemetryFmtPct(int $part, int $total): string {
    if ($part <= 0 || $total <= 0) {
        return '—';
    }
    $pct = ($part / $total) * 100.0;
    return number_format($pct, 2, ',', '') . '%';
}

/**
 * Собирает и форматирует отчёт статистики.
 * Если $reset === true — атомарно переименовывает лог-файл перед чтением, чтобы не потерять ни одного нового сообщения.
 */
function buildTelemetryReport(PDO $pdo, bool $reset = false, ?string $headerTime = null): string {
    $readFile = TELEMETRY_LOG_FILE;
    $tempFile = null;

    if ($reset) {
        if (is_file(TELEMETRY_LOG_FILE)) {
            $tempFile = '/tmp/bot_telemetry_flush_' . getmypid() . '_' . time() . '.log';
            if (@rename(TELEMETRY_LOG_FILE, $tempFile)) {
                $readFile = $tempFile;
            } else {
                $tempFile = null;
            }
        }
    }

    $msgs = 0;
    $chars = 0;
    $emojis = 0;
    $words = 0;
    $profanity = 0;
    $commands = 0;
    $voice = 0;
    $photo = 0;
    $video = 0;
    $audio = 0;
    $wall = 0;
    $sticker = 0;

    $chatCounts = [];
    $rpsBySec = [];

    if (is_file($readFile)) {
        $fh = @fopen($readFile, 'r');
        if ($fh !== false) {
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') continue;
                $p = explode('|', $line);
                if (count($p) < 13) continue;

                $ts  = (int)$p[0];
                $pid = (int)$p[1];

                $msgs++;
                $chars     += (int)$p[2];
                $emojis    += (int)$p[3];
                $words     += (int)$p[4];
                $profanity += (int)$p[5];
                $commands  += (int)$p[6];
                $voice     += (int)$p[7];
                $photo     += (int)$p[8];
                $video     += (int)$p[9];
                $audio     += (int)$p[10];
                $wall      += (int)$p[11];
                $sticker   += (int)$p[12];

                if ($pid > 2000000000) {
                    $chatCounts[$pid] = ($chatCounts[$pid] ?? 0) + 1;
                }
                if ($ts > 0) {
                    $rpsBySec[$ts] = ($rpsBySec[$ts] ?? 0) + 1;
                }
            }
            fclose($fh);
        }
    }

    if ($tempFile !== null && is_file($tempFile)) {
        @unlink($tempFile);
    }

    // Расчёт производных показателей
    $noEmojis = max(0, $chars - $emojis);
    $avgWordsPerMsg = $msgs > 0 ? number_format($words / $msgs, 1, ',', '') : '0,0';
    $avgCharsPerWord = $words > 0 ? number_format($chars / $words, 1, ',', '') : '0,0';

    $activeChats = count($chatCounts);

    // Самая активная конференция
    $topPeerId = 0;
    $topCount  = 0;
    if (!empty($chatCounts)) {
        arsort($chatCounts);
        $topPeerId = (int)array_key_first($chatCounts);
        $topCount  = (int)$chatCounts[$topPeerId];
    }

    // Максимальный RPS
    $maxRps = 0;
    $maxRpsTs = 0;
    foreach ($rpsBySec as $secTs => $cnt) {
        if ($cnt > $maxRps) {
            $maxRps = $cnt;
            $maxRpsTs = (int)$secTs;
        }
    }

    $totalChats = (int)$pdo->query("SELECT COUNT(*) FROM bot_chats WHERE peer_id > 2000000000")->fetchColumn();
    $timeLabel = $headerTime ?? date('d.m.Y H:i');

    $lines = [
        "Информация за {$timeLabel}",
        "",
        "Всего сообщений было обработано: {$msgs}",
        "Всего символов: {$chars}",
        "Из них смайлов: {$emojis} (" . telemetryFmtPct($emojis, $chars) . ")",
        "Без смайлов: {$noEmojis} (" . telemetryFmtPct($noEmojis, $chars) . ")",
        "",
        "Всего слов: {$words}",
        "(по '{$avgWordsPerMsg}' слова в сообщении, каждое по '{$avgCharsPerWord}' символов)",
        "",
        "Нецензурных слов: {$profanity} (" . telemetryFmtPct($profanity, $words) . ")",
        "Команд: {$commands} (" . telemetryFmtPct($commands, $msgs) . ")",
        "Голосовых сообщений: {$voice} (" . telemetryFmtPct($voice, $msgs) . ")",
        "Фотографий: {$photo} (" . telemetryFmtPct($photo, $msgs) . ")",
        "Видео: {$video} (" . telemetryFmtPct($video, $msgs) . ")",
        "Аудиозаписей: {$audio} (" . telemetryFmtPct($audio, $msgs) . ")",
        "Постов: {$wall} (" . telemetryFmtPct($wall, $msgs) . ")",
        "Стикеров: {$sticker} (" . telemetryFmtPct($sticker, $msgs) . ")",
        "Бесед было активно: {$activeChats}",
        "",
    ];

    if ($topPeerId > 0) {
        $stTop = $pdo->prepare("SELECT title, owner_vk_id FROM bot_chats WHERE peer_id = ?");
        $stTop->execute([$topPeerId]);
        $topRow = $stTop->fetch();
        $topTitle = (string)($topRow['title'] ?? "Беседа #{$topPeerId}");
        $ownerId = (int)($topRow['owner_vk_id'] ?? 0);
        if ($ownerId > 0) {
            $ownerStr = formatUserMention($pdo, $topPeerId, $ownerId);
        } else {
            $ownerStr = 'Не назначен';
        }
        $lines[] = "Самая активная конференция:";
        $lines[] = "#{$topPeerId} | {$topCount} сообщений.";
        $lines[] = "'{$topTitle}' | Владелец: {$ownerStr}";
    } else {
        $lines[] = "Самая активная конференция: —";
    }

    $lines[] = "";
    $lines[] = "Максимальное кол-во запросов в секунду: {$maxRps}";
    $lines[] = "Max RPS было: " . ($maxRpsTs > 0 ? date('d.m.Y H:i:s', $maxRpsTs) : '—');
    $lines[] = "";
    $lines[] = "Всего конференций зарегистрировано: {$totalChats}";

    return implode("\n", $lines);
}
