<?php
declare(strict_types=1);

/**
 * Модуль ИИ-помощника для команды !ии (!ai).
 * Работает из коробки через открытый шлюз без регистрации и ключей.
 * При желании можно задать переменную AI_API_KEY (Groq / Gemini / OpenAI-совместимый) для максимальной скорости.
 */

function aiHttpPostJson(string $url, array $payload, array $headers = [], int $timeout = 10): array {
    $ch = curl_init($url);
    $allHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_POST              => true,
        CURLOPT_POSTFIELDS        => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER        => $allHeaders,
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_CONNECTTIMEOUT    => 4,
        CURLOPT_TIMEOUT           => $timeout,
        CURLOPT_NOSIGNAL          => 1,
        CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
        CURLOPT_TCP_NODELAY       => 1,
        CURLOPT_FOLLOWLOCATION    => true,
        CURLOPT_USERAGENT         => 'RaseRussiaBot',
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['ok' => ($raw !== false && $code >= 200 && $code < 300), 'code' => $code, 'raw' => (string)$raw, 'err' => $err];
}

function aiHttpGet(string $url, int $timeout = 10): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_CONNECTTIMEOUT    => 4,
        CURLOPT_TIMEOUT           => $timeout,
        CURLOPT_NOSIGNAL          => 1,
        CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
        CURLOPT_TCP_NODELAY       => 1,
        CURLOPT_FOLLOWLOCATION    => true,
        CURLOPT_USERAGENT         => 'RaseRussiaBot',
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['ok' => ($raw !== false && $code >= 200 && $code < 300), 'code' => $code, 'raw' => (string)$raw, 'err' => $err];
}

function cleanAiAnswer(string $text): string {
    $text = trim($text);
    // Убираем возможные теги рассуждений <think>...</think>
    $text = preg_replace('/<think>.*?<\/think>/isu', '', $text);
    // Убираем лишний markdown, который плохо читается в беседах ВК
    $text = preg_replace('/^#{1,6}\s*/mu', '', (string)$text);
    $text = str_replace(['**', '__', '```'], '', (string)$text);
    $text = preg_replace("/\n{3,}/", "\n\n", (string)$text);
    $text = trim((string)$text);

    if (mb_strlen($text) > 1400) {
        $text = mb_substr($text, 0, 1397) . '...';
    }
    return $text;
}

function askAiAssistant(string $question, string $contextText = ''): ?string {
    @set_time_limit(25);

    $systemPrompt = "Ты умный, дружелюбный и лаконичный ИИ-помощник в беседе ВКонтакте игрового сообщества RASE RUSSIA. "
                  . "Отвечай на русском языке, понятно, по делу и живым языком без лишней воды. "
                  . "Старайся укладываться в 1–6 предложений, не используй сложное Markdown-форматирование.";

    $userPrompt = $question;
    if ($contextText !== '') {
        $userPrompt = "Контекст из пересланного сообщения: «" . mb_substr($contextText, 0, 500) . "»\n\nВопрос: " . $question;
    }

    $apiKey = trim((string)(getenv('AI_API_KEY') ?: ''));
    $customUrl = trim((string)(getenv('AI_API_URL') ?: ''));
    $customModel = trim((string)(getenv('AI_MODEL') ?: ''));

    // 1. Если задан AI_API_KEY — используем выделенный быстрый провайдер
    if ($apiKey !== '') {
        // Google Gemini API (ключ начинается с AIza)
        if (str_starts_with($apiKey, 'AIza')) {
            $model = $customModel !== '' ? $customModel : 'gemini-2.5-flash';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
            $res = aiHttpPostJson($url, [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                'generationConfig' => ['maxOutputTokens' => 500, 'temperature' => 0.7]
            ], [], 10);
            if ($res['ok']) {
                $data = json_decode($res['raw'], true);
                $ans = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (trim((string)$ans) !== '') return cleanAiAnswer((string)$ans);
            }
        } else {
            // Groq (gsk_...) или любой OpenAI-совместимый шлюз
            $isGroq = str_starts_with($apiKey, 'gsk_');
            $url = $customUrl !== '' ? $customUrl : ($isGroq ? 'https://api.groq.com/openai/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions');
            $model = $customModel !== '' ? $customModel : ($isGroq ? 'llama-3.3-70b-versatile' : 'gpt-4o-mini');
            $res = aiHttpPostJson($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7
            ], ['Authorization: Bearer ' . $apiKey], 10);
            if ($res['ok']) {
                $data = json_decode($res['raw'], true);
                $ans = $data['choices'][0]['message']['content'] ?? '';
                if (trim((string)$ans) !== '') return cleanAiAnswer((string)$ans);
            }
        }
    }

    // 2. Бесплатный открытый шлюз без ключей (работает сразу из коробки)
    // Попытка А: OpenAI-совместимый POST Pollinations
    $res1 = aiHttpPostJson('https://text.pollinations.ai/openai', [
        'model' => 'openai',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'seed' => random_int(1, 999999),
        'private' => true
    ], [], 11);

    if ($res1['ok']) {
        $data = json_decode($res1['raw'], true);
        $ans = $data['choices'][0]['message']['content'] ?? '';
        if (trim((string)$ans) !== '') {
            return cleanAiAnswer((string)$ans);
        }
    }

    // Попытка Б: простой GET Pollinations (резервный канал)
    $getUrl = 'https://text.pollinations.ai/' . rawurlencode(mb_substr($userPrompt, 0, 600))
            . '?model=openai&system=' . rawurlencode($systemPrompt);
    $res2 = aiHttpGet($getUrl, 10);
    if ($res2['ok']) {
        $rawText = trim($res2['raw']);
        if ($rawText !== '' && !str_starts_with($rawText, '<!DOCTYPE') && !str_starts_with($rawText, '<html')) {
            return cleanAiAnswer($rawText);
        }
    }

    return null;
}
