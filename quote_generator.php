<?php
declare(strict_types=1);

/**
 * Генератор красивых картинок-цитат (!цитата / !quote) на базе PHP GD + FreeType.
 * Подгружается лениво только при вызове команды.
 */

function quoteFindFont(array $candidates): ?string {
    foreach ($candidates as $path) {
        if (is_file($path)) return $path;
    }
    return null;
}

function quoteDownloadBinary(string $url): ?string {
    if ($url === '') return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_FOLLOWLOCATION    => true,
        CURLOPT_CONNECTTIMEOUT    => 3,
        CURLOPT_TIMEOUT           => 5,
        CURLOPT_NOSIGNAL          => 1,
        CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT         => 'RaseRussiaBot',
    ]);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $code < 200 || $code >= 300 || strlen((string)$data) < 100) {
        return null;
    }
    return (string)$data;
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

/**
 * Перенос строк по ширине в пикселях с учётом UTF-8 и длинных слов
 */
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
                // Если одно слово длиннее строки — режем посимвольно
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

/**
 * Рисует мягкое световое пятно (боке / туманность) на тёмно-фиолетовом фоне
 */
function quoteDrawSoftGlow($img, int $cx, int $cy, int $maxR, int $r, int $g, int $b, int $steps = 16, int $baseAlpha = 123): void {
    for ($i = $steps; $i >= 1; $i--) {
        $rad = (int)round($maxR * ($i / $steps));
        $alpha = min(126, max(95, $baseAlpha + (int)round(($i / $steps) * 3)));
        $col = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
        imagefilledellipse($img, $cx, $cy, $rad * 2, $rad * 2, $col);
    }
}

/**
 * Рисует круглую аватарку с плавным антиалиасингом краёв
 */
function quoteDrawCircularAvatar($canvas, ?string $avatarBytes, int $dstX, int $dstY, int $size, string $fallbackLetter, string $fontBold): void {
    $src = ($avatarBytes !== null) ? @imagecreatefromstring($avatarBytes) : false;

    $sq = imagecreatetruecolor($size, $size);
    imagealphablending($sq, true);
    imagesavealpha($sq, true);

    if ($src !== false) {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $side = min($sw, $sh);
        $sx = (int)floor(($sw - $side) / 2);
        $sy = (int)floor(($sh - $side) / 2);
        imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
        imagedestroy($src);
    } else {
        // Красивая заглушка, если аватар не загрузился
        $bg = imagecolorallocate($sq, 92, 24, 118);
        imagefilledrectangle($sq, 0, 0, $size, $size, $bg);
        $white = imagecolorallocate($sq, 255, 255, 255);
        $letter = mb_strtoupper(mb_substr($fallbackLetter, 0, 1)) ?: 'R';
        $box = imagettfbbox(120, 0, $fontBold, $letter);
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[7] - $box[1]);
        imagettftext($sq, 120, 0, (int)(($size - $tw) / 2), (int)(($size + $th) / 2), $white, $fontBold, $letter);
    }

    // Накладываем круглую маску с плавным краем (1.5px anti-aliasing)
    $center = ($size - 1) / 2.0;
    $radius = ($size / 2.0) - 1.5;

    imagealphablending($canvas, true);
    for ($y = 0; $y < $size; $y++) {
        $dy = $y - $center;
        $dy2 = $dy * $dy;
        for ($x = 0; $x < $size; $x++) {
            $dx = $x - $center;
            $dist = sqrt($dx * $dx + $dy2);
            if ($dist > $radius + 1.5) continue;

            $rgb = imagecolorat($sq, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            if ($dist <= $radius) {
                $col = imagecolorallocate($canvas, $r, $g, $b);
                imagesetpixel($canvas, $dstX + $x, $dstY + $y, $col);
            } else {
                $frac = ($dist - $radius) / 1.5; // 0..1
                $alpha = (int)round($frac * 127);
                $col = imagecolorallocatealpha($canvas, $r, $g, $b, min(127, max(0, $alpha)));
                imagesetpixel($canvas, $dstX + $x, $dstY + $y, $col);
            }
        }
    }
    imagedestroy($sq);
}

/**
 * Генерирует JPEG-файл цитаты и возвращает путь к нему (или null при ошибке)
 */
function generateQuoteImage(string $quoteText, string $authorName, ?string $avatarUrl, int $timestamp): ?string {
    if (!extension_loaded('gd') || !function_exists('imagettftext')) {
        file_put_contents('php://stderr', "[QUOTE ERROR] PHP GD + FreeType not available." . PHP_EOL);
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
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    // 1. Глубокий тёмно-фиолетовый атмосферный градиент (как на референсе)
    for ($y = 0; $y < $h; $y++) {
        $t = $y / ($h - 1);
        $r = (int)round(58 - 28 * $t);
        $g = (int)round(4 + 3 * $t);
        $b = (int)round(68 - 30 * $t);
        $lineCol = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $w, $y, $lineCol);
    }

    // 2. Мягкие пурпурные/малиновые облака и боке
    quoteDrawSoftGlow($img, 760, 220, 420, 145, 10, 108, 18, 121);
    quoteDrawSoftGlow($img, 1080, 460, 380, 128, 8, 92, 16, 121);
    quoteDrawSoftGlow($img, 360, 560, 340, 95, 12, 110, 14, 122);
    quoteDrawSoftGlow($img, 960, 110, 290, 175, 18, 120, 14, 122);

    // Небольшие светящиеся искорки в нижней части фона
    for ($i = 0; $i < 42; $i++) {
        $px = random_int(40, $w - 40);
        $py = random_int(440, $h - 20);
        $pr = random_int(2, 6);
        $pCol = imagecolorallocatealpha($img, 235, 70, 170, random_int(75, 115));
        imagefilledellipse($img, $px, $py, $pr * 2, $pr * 2, $pCol);
    }

    // 3. Круглая аватарка слева
    $avatarBytes = ($avatarUrl !== null && $avatarUrl !== '') ? quoteDownloadBinary($avatarUrl) : null;
    $avatarSize = 360;
    $avatarX = 56;
    $avatarY = 165;
    quoteDrawCircularAvatar($img, $avatarBytes, $avatarX, $avatarY, $avatarSize, $authorName, $fontBold);

    // 4. Имя автора и дата/время внизу слева
    $white = imagecolorallocate($img, 255, 255, 255);
    $subWhite = imagecolorallocatealpha($img, 240, 230, 245, 22);
    $shadowCol = imagecolorallocatealpha($img, 0, 0, 0, 75);

    $cleanAuthor = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $authorName));
    if ($cleanAuthor === '') $cleanAuthor = 'Пользователь';
    if (mb_strlen($cleanAuthor) > 26) {
        $cleanAuthor = mb_substr($cleanAuthor, 0, 25) . '…';
    }
    $dateStr = quoteFormatRuDate($timestamp);

    imagettftext($img, 28, 0, 62, 622, $shadowCol, $fontBold, $cleanAuthor);
    imagettftext($img, 28, 0, 60, 620, $white, $fontBold, $cleanAuthor);

    imagettftext($img, 16, 0, 61, 656, $shadowCol, $fontReg, $dateStr);
    imagettftext($img, 16, 0, 60, 655, $subWhite, $fontReg, $dateStr);

    // 5. Крупные стилизованные кавычки “ и ”
    imagettftext($img, 86, 0, 462, 267, $shadowCol, $fontSerif, "“");
    imagettftext($img, 86, 0, 460, 265, $white, $fontSerif, "“");

    imagettftext($img, 86, 0, 1142, 597, $shadowCol, $fontSerif, "”");
    imagettftext($img, 86, 0, 1140, 595, $white, $fontSerif, "”");

    // 6. Текст цитаты с автоподбором размера шрифта и центрированием
    $cleanQuote = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $quoteText));
    if ($cleanQuote === '') $cleanQuote = '…';
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

    // Ограничиваем, если строк всё же больше 10
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
    $ok = imagejpeg($img, $outPath, 92);
    imagedestroy($img);

    return $ok ? $outPath : null;
}

/**
 * Загрузка готовой картинки в VK и получение ['attachment' => 'photo...', 'error' => null|string]
 */
function uploadPhotoToVkMessage(int $peerId, string $filePath): array {
    // Для ботов в беседах VK API рекомендует peer_id = 0; при ошибке пробуем с реальным peer_id
    $srv = vkApi('photos.getMessagesUploadServer', ['peer_id' => 0]);
    if (empty($srv['response']['upload_url']) && $peerId > 0) {
        $srv = vkApi('photos.getMessagesUploadServer', ['peer_id' => $peerId]);
    }

    $uploadUrl = (string)($srv['response']['upload_url'] ?? '');
    if ($uploadUrl === '') {
        $code = (int)($srv['error']['error_code'] ?? 0);
        $msg  = (string)($srv['error']['error_msg'] ?? 'empty upload_url');
        file_put_contents('php://stderr', "[QUOTE UPLOAD] getMessagesUploadServer failed ({$code}): {$msg}" . PHP_EOL);
        if ($code === 15 || $code === 27 || $code === 5) {
            return [
                'attachment' => null,
                'error' => "У токена сообщества ВК нет права «Фотографии» (код {$code}). Пересоздайте ключ доступа в управлении группой ВК с галочками «Сообщения» + «Фотографии» и обновите VK_GROUP_TOKEN на BotHost."
            ];
        }
        return ['attachment' => null, 'error' => "VK getMessagesUploadServer (код {$code}): {$msg}"];
    }

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST              => true,
        CURLOPT_POSTFIELDS        => ['photo' => new CURLFile($filePath, 'image/jpeg', 'quote.jpg')],
        CURLOPT_HTTPHEADER        => ['Expect:'],
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_FOLLOWLOCATION    => true,
        CURLOPT_CONNECTTIMEOUT    => 4,
        CURLOPT_TIMEOUT           => 12,
        CURLOPT_NOSIGNAL          => 1,
        CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT         => 'RaseRussiaBot',
    ]);
    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $raw === '') {
        file_put_contents('php://stderr', "[QUOTE UPLOAD] HTTP upload failed: {$curlErr}" . PHP_EOL);
        return ['attachment' => null, 'error' => "Ошибка отправки файла на сервер ВК: {$curlErr}"];
    }

    $up = json_decode((string)$raw, true);
    if (!is_array($up) || empty($up['photo']) || $up['photo'] === '[]' || empty($up['hash']) || !isset($up['server'])) {
        file_put_contents('php://stderr', "[QUOTE UPLOAD] Invalid upload response: " . mb_substr((string)$raw, 0, 300) . PHP_EOL);
        return ['attachment' => null, 'error' => "Сервер ВК отклонил файл изображения."];
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
        file_put_contents('php://stderr', "[QUOTE UPLOAD] saveMessagesPhoto failed ({$code}): {$msg}" . PHP_EOL);
        return ['attachment' => null, 'error' => "VK saveMessagesPhoto (код {$code}): {$msg}"];
    }

    $att = "photo{$p['owner_id']}_{$p['id']}";
    if (!empty($p['access_key'])) {
        $att .= '_' . $p['access_key'];
    }
    return ['attachment' => $att, 'error' => null];
}

/**
 * Генератор классического мема-демотиватора (!дем [верх] | [низ]) на PHP GD
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

    // Масштабируем исходное фото под рамку 840px по ширине
    $boxW = 840;
    $boxH = (int)round($sh * ($boxW / max(1, $sw)));
    $boxH = max(320, min(840, $boxH));

    $padX = 80;
    $padTop = 65;
    $canvasW = $boxW + ($padX * 2);

    $cleanTop = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $topText));
    $cleanBot = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $bottomText));
    if ($cleanTop === '') $cleanTop = ' ';

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

    // Белая тонкая двойная рамка вокруг картинки
    imagerectangle($img, $padX - 8, $padTop - 8, $padX + $boxW + 7, $padTop + $boxH + 7, $white);
    imagerectangle($img, $padX - 7, $padTop - 7, $padX + $boxW + 6, $padTop + $boxH + 6, $white);

    // Вставляем исходное фото с сохранением пропорций (cover)
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

    // Рисуем верхний текст
    $curY = $padTop + $boxH + 42 + (int)round($topSize);
    foreach ($topLines as $line) {
        $b = imagettfbbox($topSize, 0, $fontSerif, $line);
        $lw = abs($b[2] - $b[0]);
        $lx = (int)round(($canvasW - $lw) / 2);
        imagettftext($img, $topSize, 0, $lx, $curY, $white, $fontSerif, $line);
        $curY += $topLineH;
    }

    // Рисуем нижний подзаголовок
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
    $ok = imagejpeg($img, $outPath, 92);
    imagedestroy($img);
    return $ok ? $outPath : null;
}

/**
 * Извлечение URL наибольшего фото из вложений сообщения (или ответа/пересланного)
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
