<?php
declare(strict_types=1);

/**
 * Быстрый генератор картинок (!цитата, !паспорт, !дем) на PHP GD + FreeType.
 * Оптимизации:
 * - Кэширование статичных фонов в /tmp (0.01 сек вместо 60+ альфа-эллипсов на каждом запросе)
 * - Нативная C-маска круглой аватарки без 130 000 итераций в PHP (~100x быстрее)
 * - Кэш аватарок в /tmp/ava_{id}.bin (30 мин)
 * - 3 попытки загрузки в VK API с HTTP/2 и TCP_NODELAY
 */

function quoteFindFont(array $candidates): ?string {
    foreach ($candidates as $path) {
        if (is_file($path)) return $path;
    }
    return null;
}

function quoteSanitizeText(string $text): string {
    $text = (string)preg_replace('/[\x{200B}-\x{200D}\x{FE0E}\x{FE0F}\x{2060}\x{FEFF}]/u', '', $text);
    $text = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
    return trim($text);
}

function quoteDownloadBinary(string $url, int $cacheVkId = 0): ?string {
    if ($url === '') return null;
    $cacheFile = $cacheVkId > 0 ? "/tmp/ava_{$cacheVkId}.bin" : null;
    if ($cacheFile !== null && is_file($cacheFile) && (time() - (int)@filemtime($cacheFile)) < 1800) {
        $cached = @file_get_contents($cacheFile);
        if (is_string($cached) && strlen($cached) >= 100) {
            return $cached;
        }
    }

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_FOLLOWLOCATION    => true,
            CURLOPT_MAXREDIRS         => 5,
            CURLOPT_CONNECTTIMEOUT    => 2,
            CURLOPT_TIMEOUT           => 5,
            CURLOPT_NOSIGNAL          => 1,
            CURLOPT_TCP_NODELAY       => 1,
            CURLOPT_DNS_CACHE_TIMEOUT => 3600,
            CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER    => false,
            CURLOPT_SSL_VERIFYHOST    => 0,
            CURLOPT_USERAGENT         => 'Mozilla/5.0 RaseRussiaBot',
        ]);
        $data = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($data !== false && $code >= 200 && $code < 300 && strlen((string)$data) >= 100) {
            if ($cacheFile !== null) {
                $tmp = $cacheFile . '.' . getmypid() . '.tmp';
                if (@file_put_contents($tmp, $data) !== false) @rename($tmp, $cacheFile);
            }
            return (string)$data;
        }
        if ($attempt === 1) usleep(100000);
    }
    return null;
}

function quoteFormatRuDate(int $ts): string {
    static $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    $d = (int)date('j', $ts);
    $m = $months[(int)date('n', $ts)] ?? 'января';
    $y = date('Y', $ts);
    $hm = date('H:i', $ts);
    return "{$d} {$m} {$y}, {$hm} (GMT+3)";
}

function quoteWrapText(string $text, string $font, float $fontSize, int $maxWidth): array {
    $paragraphs = preg_split('/\r?\n/u', trim($text));
    $lines = [];

    foreach ($paragraphs as $para) {
        $para = trim($para);
        if ($para === '') {
            $lines[] = '';
            continue;
        }
        $words = preg_split('/\s+/u', $para);
        $current = '';

        foreach ($words as $word) {
            $test = ($current === '') ? $word : ($current . ' ' . $word);
            $box = imagettfbbox($fontSize, 0, $font, $test);
            $w = abs($box[2] - $box[0]);

            if ($w <= $maxWidth) {
                $current = $test;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                $wBox = imagettfbbox($fontSize, 0, $font, $word);
                if (abs($wBox[2] - $wBox[0]) > $maxWidth) {
                    $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
                    $chunk = '';
                    foreach ($chars as $ch) {
                        $tChunk = $chunk . $ch;
                        $cBox = imagettfbbox($fontSize, 0, $font, $tChunk);
                        if (abs($cBox[2] - $cBox[0]) > $maxWidth && $chunk !== '') {
                            $lines[] = $chunk;
                            $chunk = $ch;
                        } else {
                            $chunk = $tChunk;
                        }
                    }
                    $current = $chunk;
                } else {
                    $current = $word;
                }
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
    }

    return $lines;
}

function quoteDrawSoftGlow($img, int $cx, int $cy, int $maxR, int $r, int $g, int $b, int $steps = 12, int $baseAlpha = 122): void {
    for ($i = $steps; $i >= 1; $i--) {
        $rad = (int)round($maxR * ($i / $steps));
        $alpha = min(126, max(95, $baseAlpha + (int)round(($i / $steps) * 3)));
        $col = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
        imagefilledellipse($img, $cx, $cy, $rad * 2, $rad * 2, $col);
    }
}

/**
 * Сверхбыстрая круглая аватарка (чистый C-код libgd, < 2 мс вместо 2500 мс на PHP-циклах)
 */
function quoteDrawCircularAvatar($canvas, ?string $avatarBytes, int $dstX, int $dstY, int $size, string $fallbackLetter, string $fontBold): void {
    $src = ($avatarBytes !== null) ? @imagecreatefromstring($avatarBytes) : false;

    $sq = imagecreatetruecolor($size, $size);
    imagealphablending($sq, false);
    imagesavealpha($sq, false);

    if ($src !== false) {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $side = min($sw, $sh);
        $sx = (int)floor(($sw - $side) / 2);
        $sy = (int)floor(($sh - $side) / 2);
        imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
        imagedestroy($src);
    } else {
        $bg = imagecolorallocate($sq, 92, 24, 118);
        imagefilledrectangle($sq, 0, 0, $size, $size, $bg);
        imagealphablending($sq, true);
        $white = imagecolorallocate($sq, 255, 255, 255);
        $letter = mb_strtoupper(mb_substr($fallbackLetter, 0, 1)) ?: 'R';
        $fontSize = (int)round($size * 0.34);
        $box = imagettfbbox($fontSize, 0, $fontBold, $letter);
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[7] - $box[1]);
        imagettftext($sq, $fontSize, 0, (int)(($size - $tw) / 2), (int)(($size + $th) / 2), $white, $fontBold, $letter);
        imagealphablending($sq, false);
    }

    // Создаём круглую маску средствами C (libgd)
    $mask = imagecreatetruecolor($size, $size);
    $keyColor = imagecolorallocate($mask, 1, 254, 2); // редкий ключевой цвет
    $holeColor = imagecolorallocate($mask, 0, 0, 0);
    imagefilledrectangle($mask, 0, 0, $size, $size, $keyColor);
    imagefilledellipse($mask, (int)($size / 2), (int)($size / 2), $size - 2, $size - 2, $holeColor);
    imagecolortransparent($mask, $holeColor);

    // Вырезаем углы на $sq ключевым цветом и копируем круг на $canvas
    imagecopy($sq, $mask, 0, 0, 0, 0, $size, $size);
    imagedestroy($mask);

    $sqKey = imagecolorallocate($sq, 1, 254, 2);
    imagecolortransparent($sq, $sqKey);
    imagealphablending($canvas, true);
    imagecopy($canvas, $sq, $dstX, $dstY, 0, 0, $size, $size);
    imagedestroy($sq);

    // Тонкое сглаживающее кольцо по краю круга
    $edge = imagecolorallocatealpha($canvas, 255, 255, 255, 92);
    imageellipse($canvas, $dstX + (int)($size / 2), $dstY + (int)($size / 2), $size - 2, $size - 2, $edge);
}

/**
 * Кэшированный фон для !цитата (генерируется 1 раз и переиспользуется за ~5 мс)
 */
function quoteGetCachedBg(int $w, int $h) {
    $bgFile = '/tmp/quote_bg_v8.png';
    if (is_file($bgFile)) {
        $loaded = @imagecreatefrompng($bgFile);
        if ($loaded !== false) {
            imagealphablending($loaded, true);
            imagesavealpha($loaded, true);
            return $loaded;
        }
    }

    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    for ($y = 0; $y < $h; $y++) {
        $t = $y / ($h - 1);
        $r = (int)round(58 - 28 * $t);
        $g = (int)round(4 + 3 * $t);
        $b = (int)round(68 - 30 * $t);
        imageline($img, 0, $y, $w, $y, imagecolorallocate($img, $r, $g, $b));
    }

    quoteDrawSoftGlow($img, 760, 220, 420, 145, 10, 108, 12, 121);
    quoteDrawSoftGlow($img, 1080, 460, 380, 128, 8, 92, 10, 121);
    quoteDrawSoftGlow($img, 360, 560, 340, 95, 12, 110, 10, 122);
    quoteDrawSoftGlow($img, 960, 110, 290, 175, 18, 120, 10, 122);

    // Статичные мягкие искорки
    mt_srand(2026);
    for ($i = 0; $i < 38; $i++) {
        $px = mt_rand(40, $w - 40);
        $py = mt_rand(440, $h - 20);
        $pr = mt_rand(2, 6);
        $pCol = imagecolorallocatealpha($img, 235, 70, 170, mt_rand(75, 115));
        imagefilledellipse($img, $px, $py, $pr * 2, $pr * 2, $pCol);
    }

    $tmp = $bgFile . '.' . getmypid() . '.tmp';
    if (@imagepng($img, $tmp, 1)) {
        @rename($tmp, $bgFile);
    }
    return $img;
}

/**
 * Кэшированный фон для !паспорт (генерируется 1 раз и переиспользуется за ~5 мс)
 */
function passportGetCachedBg(int $w, int $h) {
    $bgFile = '/tmp/pass_bg_v8.png';
    if (is_file($bgFile)) {
        $loaded = @imagecreatefrompng($bgFile);
        if ($loaded !== false) {
            imagealphablending($loaded, true);
            imagesavealpha($loaded, true);
            return $loaded;
        }
    }

    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    for ($y = 0; $y < $h; $y++) {
        $t = $y / ($h - 1);
        $r = (int)round(22 + 12 * $t);
        $g = (int)round(14 + 6 * $t);
        $b = (int)round(36 + 18 * $t);
        imageline($img, 0, $y, $w, $y, imagecolorallocate($img, $r, $g, $b));
    }

    quoteDrawSoftGlow($img, 180, 140, 320, 120, 28, 165, 10, 121);
    quoteDrawSoftGlow($img, 1020, 520, 360, 20, 145, 115, 10, 122);

    $panelBg   = imagecolorallocatealpha($img, 16, 18, 28, 28);
    $panelEdge = imagecolorallocatealpha($img, 145, 95, 225, 65);
    $cardEdge  = imagecolorallocatealpha($img, 120, 130, 165, 85);

    imagefilledrectangle($img, 36, 36, $w - 36, $h - 36, $panelBg);
    imagerectangle($img, 36, 36, $w - 36, $h - 36, $panelEdge);
    imagerectangle($img, 37, 37, $w - 37, $h - 37, $panelEdge);
    imageline($img, 64, 96, $w - 64, 96, $cardEdge);

    $tmp = $bgFile . '.' . getmypid() . '.tmp';
    if (@imagepng($img, $tmp, 1)) {
        @rename($tmp, $bgFile);
    }
    return $img;
}

/**
 * Генерирует JPEG-файл цитаты и возвращает путь к нему (или null при ошибке)
 */
function generateQuoteImage(string $quoteText, string $authorName, ?string $avatarUrl, int $timestamp, int $authorVkId = 0): ?string {
    if (!extension_loaded('gd') || !function_exists('imagettftext')) {
        file_put_contents('php://stderr', "[QUOTE ERROR] PHP GD + FreeType not available." . PHP_EOL);
        return null;
    }

    $quoteText  = quoteSanitizeText($quoteText) ?: $quoteText;
    $authorName = quoteSanitizeText($authorName) ?: 'Игрок';

    $fontBold = quoteFindFont([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
    ]);
    $fontReg = quoteFindFont([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
    ]) ?? $fontBold;
    $fontSerif = quoteFindFont([
        '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSerif-Bold.ttf',
    ]) ?? $fontBold;

    if ($fontBold === null) {
        file_put_contents('php://stderr', "[QUOTE ERROR] DejaVu fonts not found." . PHP_EOL);
        return null;
    }

    $w = 1280;
    $h = 720;
    $img = quoteGetCachedBg($w, $h);

    // Круглая аватарка слева (с кэшем бинарника в /tmp/ava_{id}.bin)
    $avatarBytes = ($avatarUrl !== null && $avatarUrl !== '') ? quoteDownloadBinary($avatarUrl, $authorVkId) : null;
    quoteDrawCircularAvatar($img, $avatarBytes, 56, 165, 360, $authorName, $fontBold);

    // Имя автора и дата/время внизу слева
    $white     = imagecolorallocate($img, 255, 255, 255);
    $subWhite  = imagecolorallocatealpha($img, 240, 230, 245, 22);
    $shadowCol = imagecolorallocatealpha($img, 0, 0, 0, 75);

    $cleanAuthor = mb_strlen($authorName) > 26 ? (mb_substr($authorName, 0, 25) . '…') : $authorName;
    $dateStr = quoteFormatRuDate($timestamp);

    imagettftext($img, 28, 0, 62, 622, $shadowCol, $fontBold, $cleanAuthor);
    imagettftext($img, 28, 0, 60, 620, $white, $fontBold, $cleanAuthor);

    imagettftext($img, 16, 0, 61, 656, $shadowCol, $fontReg, $dateStr);
    imagettftext($img, 16, 0, 60, 655, $subWhite, $fontReg, $dateStr);

    // Крупные кавычки
    imagettftext($img, 86, 0, 462, 267, $shadowCol, $fontSerif, "“");
    imagettftext($img, 86, 0, 460, 265, $white, $fontSerif, "“");

    imagettftext($img, 86, 0, 1142, 597, $shadowCol, $fontSerif, "”");
    imagettftext($img, 86, 0, 1140, 595, $white, $fontSerif, "”");

    // Текст цитаты с автоподбором размера шрифта
    $cleanQuote = $quoteText !== '' ? $quoteText : '…';
    if (mb_strlen($cleanQuote) > 360) {
        $cleanQuote = mb_substr($cleanQuote, 0, 357) . '...';
    }

    $areaX = 505;
    $areaW = 635;
    $areaTop = 235;
    $areaH = 310;

    $chosenSize = 22.0;
    $chosenLines = [];
    $chosenLineH = 32;

    foreach ([38.0, 34.0, 30.0, 26.0, 23.0, 20.0] as $sz) {
        $lines = quoteWrapText($cleanQuote, $fontBold, $sz, $areaW);
        $lineH = (int)round($sz * 1.45);
        $totalH = count($lines) * $lineH;
        $chosenSize = $sz;
        $chosenLines = $lines;
        $chosenLineH = $lineH;
        if ($totalH <= $areaH && count($lines) <= 9) {
            break;
        }
    }

    if (count($chosenLines) > 10) {
        $chosenLines = array_slice($chosenLines, 0, 10);
        $chosenLines[9] = mb_substr($chosenLines[9], 0, max(1, mb_strlen($chosenLines[9]) - 3)) . '...';
    }

    $totalBlockH = count($chosenLines) * $chosenLineH;
    $startY = $areaTop + (int)round(($areaH - $totalBlockH) / 2) + (int)round($chosenSize);

    foreach ($chosenLines as $idx => $line) {
        if ($line === '') continue;
        $box = imagettfbbox($chosenSize, 0, $fontBold, $line);
        $lw = abs($box[2] - $box[0]);
        $lx = $areaX + (int)round(($areaW - $lw) / 2);
        $ly = $startY + ($idx * $chosenLineH);

        imagettftext($img, $chosenSize, 0, $lx + 2, $ly + 2, $shadowCol, $fontBold, $line);
        imagettftext($img, $chosenSize, 0, $lx, $ly, $white, $fontBold, $line);
    }

    $outPath = '/tmp/quote_' . getmypid() . '_' . random_int(1000, 999999) . '.jpg';
    $ok = imagejpeg($img, $outPath, 86);
    imagedestroy($img);

    return $ok ? $outPath : null;
}

/**
 * Генератор визуального паспорта игрока (!паспорт / !карта)
 */
function generatePassportImage(array $info): ?string {
    if (!extension_loaded('gd') || !function_exists('imagettftext')) {
        return null;
    }

    $fontBold = quoteFindFont([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
    ]);
    $fontReg = quoteFindFont([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
    ]) ?? $fontBold;

    if ($fontBold === null) return null;

    $w = 1200;
    $h = 630;
    $img = passportGetCachedBg($w, $h);

    $cardBg    = imagecolorallocatealpha($img, 28, 30, 46, 32);
    $cardEdge  = imagecolorallocatealpha($img, 120, 130, 165, 85);
    $white     = imagecolorallocate($img, 255, 255, 255);
    $muted     = imagecolorallocate($img, 168, 175, 198);
    $accent    = imagecolorallocate($img, 185, 125, 255);
    $gold      = imagecolorallocate($img, 255, 206, 86);
    $emerald   = imagecolorallocate($img, 74, 222, 128);
    $shadow    = imagecolorallocatealpha($img, 0, 0, 0, 70);

    // Верхняя шапка паспорта
    imagettftext($img, 15, 0, 66, 78, $accent, $fontBold, "RASE RUSSIA  |  ПАСПОРТ УЧАСТНИКА");
    $vkId = (int)($info['vk_id'] ?? 0);
    $idLabel = "ID: {$vkId}";
    $idBox = imagettfbbox(15, 0, $fontBold, $idLabel);
    $idW = abs($idBox[2] - $idBox[0]);
    imagettftext($img, 15, 0, $w - 66 - $idW, 78, $muted, $fontBold, $idLabel);

    // Круглая аватарка слева + декоративное кольцо
    $avSize = 230;
    $avX = 78;
    $avY = 140;
    $ringCol = (!empty($info['is_vip']) || !empty($info['is_super'])) ? $gold : $accent;
    imagefilledellipse($img, $avX + (int)($avSize / 2), $avY + (int)($avSize / 2), $avSize + 12, $avSize + 12, $ringCol);

    $avatarBytes = !empty($info['avatar_url']) ? quoteDownloadBinary((string)$info['avatar_url'], $vkId) : null;
    $cleanName = quoteSanitizeText((string)($info['name'] ?? 'Игрок')) ?: 'Игрок';

    quoteDrawCircularAvatar($img, $avatarBytes, $avX, $avY, $avSize, $cleanName, $fontBold);

    // Бейдж роли под аватаркой
    $roleText = quoteSanitizeText((string)($info['role'] ?? 'Участник')) ?: 'Участник';
    if (mb_strlen($roleText) > 22) $roleText = mb_substr($roleText, 0, 21) . '…';
    imagefilledrectangle($img, 68, 400, 318, 446, $cardBg);
    imagerectangle($img, 68, 400, 318, 446, $ringCol);
    $rBox = imagettfbbox(14, 0, $fontBold, $roleText);
    $rW = abs($rBox[2] - $rBox[0]);
    imagettftext($img, 14, 0, 68 + (int)((250 - $rW) / 2), 429, $white, $fontBold, $roleText);

    // VIP / Статус под ролью
    $statusText = !empty($info['is_super']) ? "СУПЕР-ДОСТУП" : (!empty($info['is_vip']) ? "VIP СТАТУС" : "УЧАСТНИК ЧАТА");
    $statusCol  = (!empty($info['is_super']) || !empty($info['is_vip'])) ? $gold : $muted;
    $sBox = imagettfbbox(13, 0, $fontBold, $statusText);
    $sW = abs($sBox[2] - $sBox[0]);
    imagettftext($img, 13, 0, 68 + (int)((250 - $sW) / 2), 478, $statusCol, $fontBold, $statusText);

    // Дата регистрации внизу слева
    $regStr = "В беседе с " . (string)($info['reg_date'] ?? date('d.m.Y'));
    $rgBox = imagettfbbox(12, 0, $fontReg, $regStr);
    $rgW = abs($rgBox[2] - $rgBox[0]);
    imagettftext($img, 12, 0, 68 + (int)((250 - $rgW) / 2), 515, $muted, $fontReg, $regStr);

    // Правая колонка: Имя + 6 плиток
    $dispName = mb_strlen($cleanName) > 26 ? (mb_substr($cleanName, 0, 25) . '…') : $cleanName;
    imagettftext($img, 30, 0, 362, 168, $shadow, $fontBold, $dispName);
    imagettftext($img, 30, 0, 360, 166, $white, $fontBold, $dispName);

    $cleanStr = function (string $val, int $max = 22): string {
        $v = quoteSanitizeText($val);
        if ($v === '') return '—';
        return mb_strlen($v) > $max ? (mb_substr($v, 0, $max - 1) . '…') : $v;
    };

    $tiles = [
        ['label' => 'НАЛИЧНЫЕ',    'val' => $cleanStr((string)($info['cash'] ?? '0 ₽'), 20),       'col' => $emerald],
        ['label' => 'В БАНКЕ',     'val' => $cleanStr((string)($info['bank'] ?? '0 ₽'), 20),       'col' => $emerald],
        ['label' => 'СЕМЬЯ/КЛАН',  'val' => $cleanStr((string)($info['clan'] ?? 'Нет'), 22),       'col' => $white],
        ['label' => 'ГРАЖДАНСТВО', 'val' => $cleanStr((string)($info['country'] ?? 'Нет'), 22),    'col' => $white],
        ['label' => 'СООБЩЕНИЯ',   'val' => $cleanStr((string)($info['messages'] ?? '0'), 22),     'col' => $accent],
        ['label' => 'РЕПУТАЦИЯ',   'val' => $cleanStr((string)($info['reputation'] ?? '0'), 20),   'col' => $gold],
    ];

    $startX = 360;
    $startY = 200;
    $tileW = 372;
    $tileH = 98;
    $gapX = 24;
    $gapY = 20;

    foreach ($tiles as $idx => $tile) {
        $colIdx = $idx % 2;
        $rowIdx = (int)floor($idx / 2);
        $tx = $startX + $colIdx * ($tileW + $gapX);
        $ty = $startY + $rowIdx * ($tileH + $gapY);

        imagefilledrectangle($img, $tx, $ty, $tx + $tileW, $ty + $tileH, $cardBg);
        imagerectangle($img, $tx, $ty, $tx + $tileW, $ty + $tileH, $cardEdge);

        imagettftext($img, 12, 0, $tx + 20, $ty + 32, $muted, $fontBold, $tile['label']);
        imagettftext($img, 20, 0, $tx + 20, $ty + 72, $tile['col'], $fontBold, $tile['val']);
    }

    $marriage = $cleanStr((string)($info['marriage'] ?? 'Холост / Не замужем'), 46);
    imagettftext($img, 13, 0, 360, 568, $muted, $fontReg, "Семейное положение: {$marriage}");

    $outPath = '/tmp/pass_' . getmypid() . '_' . random_int(1000, 999999) . '.jpg';
    $ok = imagejpeg($img, $outPath, 86);
    imagedestroy($img);
    return $ok ? $outPath : null;
}

/**
 * Генератор классического демотиватора (!дем)
 */
function generateDemotivatorImage(string $photoUrl, string $topText, string $bottomText = ''): ?string {
    if (!extension_loaded('gd') || !function_exists('imagettftext')) {
        return null;
    }

    $fontSerif = quoteFindFont([
        '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    ]);
    $fontSans = quoteFindFont([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    ]) ?? $fontSerif;

    if ($fontSerif === null) return null;

    $bytes = quoteDownloadBinary($photoUrl);
    if ($bytes === null) return null;
    $src = @imagecreatefromstring($bytes);
    if ($src === false) return null;

    $sw = imagesx($src);
    $sh = imagesy($src);

    $boxW = 840;
    $boxH = (int)round($sh * ($boxW / max(1, $sw)));
    $boxH = max(320, min(840, $boxH));

    $padX = 80;
    $padTop = 65;
    $canvasW = $boxW + ($padX * 2);

    $cleanTop = quoteSanitizeText($topText) ?: ' ';
    $cleanBot = quoteSanitizeText($bottomText);

    $topSize = 44.0;
    $topLines = quoteWrapText($cleanTop, $fontSerif, $topSize, $canvasW - 100);
    if (count($topLines) > 3) {
        $topSize = 34.0;
        $topLines = array_slice(quoteWrapText($cleanTop, $fontSerif, $topSize, $canvasW - 100), 0, 4);
    }
    $topLineH = (int)round($topSize * 1.35);

    $botSize = 24.0;
    $botLines = $cleanBot !== '' ? array_slice(quoteWrapText($cleanBot, $fontSans, $botSize, $canvasW - 120), 0, 4) : [];
    $botLineH = (int)round($botSize * 1.4);

    $textBlockH = (count($topLines) * $topLineH) + (!empty($botLines) ? (22 + count($botLines) * $botLineH) : 0);
    $canvasH = $padTop + $boxH + 45 + $textBlockH + 55;

    $img = imagecreatetruecolor($canvasW, $canvasH);
    $black = imagecolorallocate($img, 0, 0, 0);
    $white = imagecolorallocate($img, 255, 255, 255);
    $softWhite = imagecolorallocate($img, 232, 232, 232);
    imagefilledrectangle($img, 0, 0, $canvasW, $canvasH, $black);

    imagerectangle($img, $padX - 8, $padTop - 8, $padX + $boxW + 7, $padTop + $boxH + 7, $white);
    imagerectangle($img, $padX - 7, $padTop - 7, $padX + $boxW + 6, $padTop + $boxH + 6, $white);

    $srcRatio = $sw / max(1, $sh);
    $dstRatio = $boxW / max(1, $boxH);
    if ($srcRatio > $dstRatio) {
        $cropH = $sh;
        $cropW = (int)round($sh * $dstRatio);
        $cropX = (int)floor(($sw - $cropW) / 2);
        $cropY = 0;
    } else {
        $cropW = $sw;
        $cropH = (int)round($sw / $dstRatio);
        $cropX = 0;
        $cropY = (int)floor(($sh - $cropH) / 2);
    }
    imagecopyresampled($img, $src, $padX, $padTop, $cropX, $cropY, $boxW, $boxH, $cropW, $cropH);
    imagedestroy($src);

    $curY = $padTop + $boxH + 42 + (int)round($topSize);
    foreach ($topLines as $line) {
        $b = imagettfbbox($topSize, 0, $fontSerif, $line);
        $lw = abs($b[2] - $b[0]);
        $lx = (int)round(($canvasW - $lw) / 2);
        imagettftext($img, $topSize, 0, $lx, $curY, $white, $fontSerif, $line);
        $curY += $topLineH;
    }

    if (!empty($botLines)) {
        $curY += 10;
        foreach ($botLines as $line) {
            $b = imagettfbbox($botSize, 0, $fontSans, $line);
            $lw = abs($b[2] - $b[0]);
            $lx = (int)round(($canvasW - $lw) / 2);
            imagettftext($img, $botSize, 0, $lx, $curY, $softWhite, $fontSans, $line);
            $curY += $botLineH;
        }
    }

    $outPath = '/tmp/dem_' . getmypid() . '_' . random_int(1000, 999999) . '.jpg';
    $ok = imagejpeg($img, $outPath, 86);
    imagedestroy($img);
    return $ok ? $outPath : null;
}

/**
 * Извлечение URL наибольшего фото из вложений сообщения
 */
function extractPhotoUrlFromMessage(array $msg): ?string {
    $sources = [$msg];
    if (!empty($msg['reply_message'])) $sources[] = $msg['reply_message'];
    if (!empty($msg['fwd_messages'][0])) $sources[] = $msg['fwd_messages'][0];

    foreach ($sources as $src) {
        foreach (($src['attachments'] ?? []) as $att) {
            if (($att['type'] ?? '') === 'photo' && !empty($att['photo']['sizes'])) {
                $bestUrl = null;
                $bestArea = -1;
                foreach ($att['photo']['sizes'] as $sz) {
                    $area = (int)($sz['width'] ?? 0) * (int)($sz['height'] ?? 0);
                    if ($area > $bestArea && !empty($sz['url'])) {
                        $bestArea = $area;
                        $bestUrl = (string)$sz['url'];
                    }
                }
                if ($bestUrl !== null) return $bestUrl;
            }
        }
    }
    return null;
}

/**
 * Безотказная загрузка готовой картинки в VK (3 попытки, HTTP/2 + TCP_NODELAY)
 */
function uploadPhotoToVkMessage(int $peerId, string $filePath): array {
    if (!is_file($filePath) || (int)@filesize($filePath) < 100) {
        return ['attachment' => null, 'error' => 'Сгенерированный файл изображения пуст.'];
    }

    $lastError = 'Неизвестная ошибка загрузки.';
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $tryPeer = ($attempt === 2 && $peerId > 0) ? $peerId : 0;
        $srv = vkApi('photos.getMessagesUploadServer', ['peer_id' => $tryPeer]);
        if (empty($srv['response']['upload_url']) && $tryPeer === 0 && $peerId > 0) {
            $srv = vkApi('photos.getMessagesUploadServer', ['peer_id' => $peerId]);
        }

        $uploadUrl = (string)($srv['response']['upload_url'] ?? '');
        if ($uploadUrl === '') {
            $code = (int)($srv['error']['error_code'] ?? 0);
            $msg  = (string)($srv['error']['error_msg'] ?? 'empty upload_url');
            if ($code === 15 || $code === 27 || $code === 5) {
                return [
                    'attachment' => null,
                    'error' => "У токена сообщества ВК нет права «Фотографии» (код {$code})."
                ];
            }
            $lastError = "VK getMessagesUploadServer (код {$code}): {$msg}";
            usleep(180000 * $attempt);
            continue;
        }

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST              => true,
            CURLOPT_POSTFIELDS        => ['photo' => new CURLFile($filePath, 'image/jpeg', 'image.jpg')],
            CURLOPT_HTTPHEADER        => ['Expect:'],
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_FOLLOWLOCATION    => true,
            CURLOPT_CONNECTTIMEOUT    => 3,
            CURLOPT_TIMEOUT           => 8,
            CURLOPT_NOSIGNAL          => 1,
            CURLOPT_TCP_NODELAY       => 1,
            CURLOPT_DNS_CACHE_TIMEOUT => 3600,
            CURLOPT_HTTP_VERSION      => CURL_HTTP_VERSION_2_0,
            CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER    => false,
            CURLOPT_SSL_VERIFYHOST    => 0,
            CURLOPT_USERAGENT         => 'RaseRussiaBot',
        ]);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            $lastError = "Ошибка отправки файла на сервер ВК: {$curlErr}";
            usleep(180000 * $attempt);
            continue;
        }

        $up = json_decode((string)$raw, true);
        if (!is_array($up) || empty($up['photo']) || $up['photo'] === '[]' || empty($up['hash']) || !isset($up['server'])) {
            $lastError = "Сервер ВК отклонил файл изображения.";
            usleep(180000 * $attempt);
            continue;
        }

        $saved = vkApi('photos.saveMessagesPhoto', [
            'photo'  => $up['photo'],
            'server' => $up['server'],
            'hash'   => $up['hash'],
        ]);

        $p = $saved['response'][0] ?? null;
        if (empty($p['owner_id']) || empty($p['id'])) {
            $code = (int)($saved['error']['error_code'] ?? 0);
            $msg  = (string)($saved['error']['error_msg'] ?? 'save failed');
            $lastError = "VK saveMessagesPhoto (код {$code}): {$msg}";
            usleep(180000 * $attempt);
            continue;
        }

        $att = "photo{$p['owner_id']}_{$p['id']}";
        if (!empty($p['access_key'])) {
            $att .= "_{$p['access_key']}";
        }
        return ['attachment' => $att, 'error' => null];
    }

    file_put_contents('php://stderr', "[PHOTO UPLOAD FAILED x3] {$lastError}" . PHP_EOL);
    return ['attachment' => null, 'error' => $lastError];
}
