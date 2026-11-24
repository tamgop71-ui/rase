<?php
declare(strict_types=1);

/**
 * Модуль опроса игрового сервера SA-MP / CRMP по UDP Query протоколу (!serv).
 * Подгружается лениво только при вызове команды (0 мс нагрузки на остальные команды).
 */

define('DEFAULT_CRMP_IP',   getenv('CRMP_SERVER_IP') ?: '80.242.59.112');
define('DEFAULT_CRMP_PORT', (int)(getenv('CRMP_SERVER_PORT') ?: 7609));

function sampToUtf8(string $str): string {
    if ($str === '') return '';
    // Убираем цветовые теги SA-MP вида {FF0000}
    $str = (string)preg_replace('/\{[0-9A-Fa-f]{6}\}/', '', $str);
    if (mb_check_encoding($str, 'UTF-8')) {
        return trim($str);
    }
    $conv = @mb_convert_encoding($str, 'UTF-8', 'Windows-1251');
    if (is_string($conv) && $conv !== '') {
        return trim($conv);
    }
    $conv2 = @iconv('CP1251', 'UTF-8//IGNORE', $str);
    return trim(is_string($conv2) ? $conv2 : $str);
}

function sampBuildPacket(string $ip, int $port, string $opcode, string $extra = ''): ?string {
    $resolved = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : gethostbyname($ip);
    if (!filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return null;
    }
    $octets = explode('.', $resolved);
    return 'SAMP'
        . chr((int)$octets[0])
        . chr((int)$octets[1])
        . chr((int)$octets[2])
        . chr((int)$octets[3])
        . chr($port & 0xFF)
        . chr(($port >> 8) & 0xFF)
        . $opcode
        . $extra;
}

function sampUdpRequest($sock, string $packet, int $maxBytes = 4096): ?string {
    $written = @fwrite($sock, $packet);
    if ($written === false) return null;
    $resp = @fread($sock, $maxBytes);
    if ($resp === false || strlen($resp) < 11) return null;
    if (substr($resp, 0, 4) !== 'SAMP') return null;
    return $resp;
}

/**
 * Опрашивает SA-MP / CRMP сервер: инфо (i), правила (r), пинг (p) и при необходимости список игроков (c)
 */
function querySampServer(string $ip, int $port, bool $withPlayers = false): array {
    $resolved = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : gethostbyname($ip);
    if (!filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return ['online' => false, 'error' => 'Некорректный IP-адрес или домен сервера.'];
    }

    $sock = @fsockopen("udp://{$resolved}", $port, $errno, $errstr, 2);
    if (!$sock) {
        return ['online' => false, 'error' => "Не удалось открыть UDP-сокет ({$errno}: {$errstr})."];
    }
    stream_set_timeout($sock, 1, 800000); // 1.8 сек жёсткий таймаут

    // 1. Запрос информации ('i') + замер пинга
    $pktInfo = sampBuildPacket($resolved, $port, 'i');
    $t0 = microtime(true);
    $rawInfo = $pktInfo !== null ? sampUdpRequest($sock, $pktInfo, 4096) : null;
    $pingMs = (int)round((microtime(true) - $t0) * 1000);

    if ($rawInfo === null || strlen($rawInfo) < 16) {
        fclose($sock);
        return ['online' => false, 'ip' => $ip, 'port' => $port, 'error' => 'Сервер не отвечает на UDP Query (выключен, query=0 или закрыт порт).'];
    }

    // Разбор пакета 'i'
    $pos = 11;
    $len = strlen($rawInfo);

    $password   = ord($rawInfo[$pos]); $pos += 1;
    $players    = (int)(unpack('v', substr($rawInfo, $pos, 2))[1] ?? 0); $pos += 2;
    $maxPlayers = (int)(unpack('v', substr($rawInfo, $pos, 2))[1] ?? 0); $pos += 2;

    $readStr4 = function () use ($rawInfo, &$pos, $len): string {
        if ($pos + 4 > $len) return '';
        $slen = (int)(unpack('V', substr($rawInfo, $pos, 4))[1] ?? 0);
        $pos += 4;
        if ($slen <= 0 || $pos + $slen > $len) return '';
        $s = substr($rawInfo, $pos, $slen);
        $pos += $slen;
        return sampToUtf8($s);
    };

    $hostname = $readStr4();
    $gamemode = $readStr4();
    $language = $readStr4();

    // 2. Запрос правил ('r') — версия, карта, время, погода, сайт
    $rules = [];
    $pktRules = sampBuildPacket($resolved, $port, 'r');
    $rawRules = $pktRules !== null ? sampUdpRequest($sock, $pktRules, 4096) : null;
    if ($rawRules !== null && strlen($rawRules) >= 13) {
        $rPos = 11;
        $rLen = strlen($rawRules);
        $rCount = (int)(unpack('v', substr($rawRules, $rPos, 2))[1] ?? 0);
        $rPos += 2;
        for ($i = 0; $i < $rCount && $rPos < $rLen; $i++) {
            $kLen = ord($rawRules[$rPos]); $rPos += 1;
            if ($rPos + $kLen > $rLen) break;
            $k = sampToUtf8(substr($rawRules, $rPos, $kLen));
            $rPos += $kLen;

            if ($rPos >= $rLen) break;
            $vLen = ord($rawRules[$rPos]); $rPos += 1;
            if ($rPos + $vLen > $rLen) break;
            $v = sampToUtf8(substr($rawRules, $rPos, $vLen));
            $rPos += $vLen;

            if ($k !== '') {
                $rules[mb_strtolower($k)] = $v;
            }
        }
    }

    // 3. Опциональный список игроков ('c') — протокол SA-MP/CRMP отдаёт его при онлайне <= 100
    $playerList = [];
    if ($withPlayers && $players > 0 && $players <= 100) {
        $pktClients = sampBuildPacket($resolved, $port, 'c');
        $rawClients = $pktClients !== null ? sampUdpRequest($sock, $pktClients, 8192) : null;
        if ($rawClients !== null && strlen($rawClients) >= 13) {
            $cPos = 11;
            $cLen = strlen($rawClients);
            $cCount = (int)(unpack('v', substr($rawClients, $cPos, 2))[1] ?? 0);
            $cPos += 2;
            for ($i = 0; $i < $cCount && $cPos < $cLen; $i++) {
                $nLen = ord($rawClients[$cPos]); $cPos += 1;
                if ($cPos + $nLen + 4 > $cLen) break;
                $name = sampToUtf8(substr($rawClients, $cPos, $nLen));
                $cPos += $nLen;
                $score = (int)(unpack('l', substr($rawClients, $cPos, 4))[1] ?? 0);
                $cPos += 4;
                $playerList[] = ['name' => $name, 'score' => $score];
            }
        }
    }

    fclose($sock);

    return [
        'online'      => true,
        'ip'          => $ip,
        'port'        => $port,
        'ping'        => $pingMs,
        'password'    => ($password === 1),
        'players'     => $players,
        'max_players' => $maxPlayers,
        'hostname'    => $hostname !== '' ? $hostname : 'CRMP Сервер',
        'gamemode'    => $gamemode !== '' ? $gamemode : '—',
        'language'    => $language !== '' ? $language : '—',
        'rules'       => $rules,
        'player_list' => $playerList,
        'checked_at'  => date('d.m.Y H:i:s'),
    ];
}

function sampProgressBar(int $cur, int $max, int $width = 10): string {
    if ($max <= 0) return str_repeat('░', $width);
    $ratio = max(0.0, min(1.0, $cur / $max));
    $filled = (int)round($ratio * $width);
    return str_repeat('█', $filled) . str_repeat('░', $width - $filled);
}
