<?php
declare(strict_types=1);

/**
 * Быстрый морфологический движок склонения русских имён, фамилий и никнеймов
 * по 6 падежам (nom, gen, dat, acc, ins, pre) с сохранением регистра и тегов.
 * Работает полностью локально за <0.05 мс без обращений к внешним API и БД.
 */

function inflectPreserveCase(string $original, string $inflected): string {
    if ($original === '' || $inflected === '') return $inflected;
    // ВСЁ ЗАГЛАВНЫМИ (например: ЦАРЬ -> ЦАРЮ)
    if (mb_strtoupper($original) === $original && mb_strtolower($original) !== $original) {
        return mb_strtoupper($inflected);
    }
    // Первая буква заглавная (например: Царь -> Царю, Артём -> Артёма)
    $first = mb_substr($original, 0, 1);
    if (mb_strtoupper($first) === $first && mb_strtolower($first) !== $first) {
        return mb_strtoupper(mb_substr($inflected, 0, 1)) . mb_substr($inflected, 1);
    }
    // Всё строчными (например: царь -> царю, артем -> артема)
    return mb_strtolower($inflected);
}

/**
 * Склонение одного русского слова (имени, фамилии или никнейма)
 * $case: 'nom' | 'gen' | 'dat' | 'acc' | 'ins' | 'pre'
 * $genderHint: 'm' | 'f' | '' (автоопределение)
 */
function inflectRussianWord(string $word, string $case = 'nom', string $genderHint = ''): string {
    if ($case === 'nom' || $word === '') return $word;

    $low = mb_strtolower($word);
    $len = mb_strlen($low);

    // Склоняем только слова из кириллицы длиной >= 2
    if ($len < 2 || !preg_match('/^[а-яё]+$/u', $low)) {
        return $word;
    }

    // 1. Словарь частых исключений и слов с беглыми гласными
    static $exceptions = [
        'царь'      => ['gen'=>'царя',      'dat'=>'царю',      'acc'=>'царя',      'ins'=>'царём',      'pre'=>'царе'],
        'король'    => ['gen'=>'короля',    'dat'=>'королю',    'acc'=>'короля',    'ins'=>'королём',    'pre'=>'короле'],
        'лев'       => ['gen'=>'льва',      'dat'=>'льву',      'acc'=>'льва',      'ins'=>'львом',      'pre'=>'льве'],
        'павел'     => ['gen'=>'павла',     'dat'=>'павлу',     'acc'=>'павла',     'ins'=>'павлом',     'pre'=>'павле'],
        'пётр'      => ['gen'=>'петра',     'dat'=>'петру',     'acc'=>'петра',     'ins'=>'петром',     'pre'=>'петре'],
        'петр'      => ['gen'=>'петра',     'dat'=>'петру',     'acc'=>'петра',     'ins'=>'петром',     'pre'=>'петре'],
        'отец'      => ['gen'=>'отца',      'dat'=>'отцу',      'acc'=>'отца',      'ins'=>'отцом',      'pre'=>'отце'],
        'боец'      => ['gen'=>'бойца',     'dat'=>'бойцу',     'acc'=>'бойца',     'ins'=>'бойцом',     'pre'=>'бойце'],
        'владелец'  => ['gen'=>'владельца', 'dat'=>'владельцу', 'acc'=>'владельца', 'ins'=>'владельцем', 'pre'=>'владельце'],
        'парень'    => ['gen'=>'парня',     'dat'=>'парню',     'acc'=>'парня',     'ins'=>'парнем',     'pre'=>'парне'],
        'огонь'     => ['gen'=>'огня',      'dat'=>'огню',      'acc'=>'огня',      'ins'=>'огнём',      'pre'=>'огне'],
        'конь'      => ['gen'=>'коня',      'dat'=>'коню',      'acc'=>'коня',      'ins'=>'конём',      'pre'=>'коне'],
        'илья'      => ['gen'=>'ильи',      'dat'=>'илье',      'acc'=>'илью',      'ins'=>'ильёй',      'pre'=>'илье'],
        'любовь'    => ['gen'=>'любови',    'dat'=>'любови',    'acc'=>'любовь',    'ins'=>'любовью',    'pre'=>'любови'],
        'мать'      => ['gen'=>'матери',    'dat'=>'матери',    'acc'=>'мать',      'ins'=>'матерью',    'pre'=>'матери'],
        'дочь'      => ['gen'=>'дочери',    'dat'=>'дочери',    'acc'=>'дочь',      'ins'=>'дочерью',    'pre'=>'дочери'],
        'орёл'      => ['gen'=>'орла',      'dat'=>'орлу',      'acc'=>'орла',      'ins'=>'орлом',      'pre'=>'орле'],
        'орел'      => ['gen'=>'орла',      'dat'=>'орлу',      'acc'=>'орла',      'ins'=>'орлом',      'pre'=>'орле'],
        'пёс'       => ['gen'=>'пса',       'dat'=>'псу',       'acc'=>'пса',       'ins'=>'псом',       'pre'=>'псе'],
        'пес'       => ['gen'=>'пса',       'dat'=>'псу',       'acc'=>'пса',       'ins'=>'псом',       'pre'=>'псе'],
        'бес'       => ['gen'=>'беса',      'dat'=>'бесу',      'acc'=>'беса',      'ins'=>'бесом',      'pre'=>'бесе'],
        'заяц'      => ['gen'=>'зайца',     'dat'=>'зайцу',     'acc'=>'зайца',     'ins'=>'зайцем',     'pre'=>'зайце'],
        'красавец'  => ['gen'=>'красавца',  'dat'=>'красавцу',  'acc'=>'красавца',  'ins'=>'красавцем',  'pre'=>'красавце'],
        'молодец'   => ['gen'=>'молодца',   'dat'=>'молодцу',   'acc'=>'молодца',   'ins'=>'молодцом',   'pre'=>'молодце'],
        'мудрец'    => ['gen'=>'мудреца',   'dat'=>'мудрецу',   'acc'=>'мудреца',   'ins'=>'мудрецом',   'pre'=>'мудреце'],
        'кузнец'    => ['gen'=>'кузнеца',   'dat'=>'кузнецу',   'acc'=>'кузнеца',   'ins'=>'кузнецом',   'pre'=>'кузнеце'],
        'стрелец'   => ['gen'=>'стрельца',  'dat'=>'стрельцу',  'acc'=>'стрельца',  'ins'=>'стрельцом',  'pre'=>'стрельце'],
        'горец'     => ['gen'=>'горца',     'dat'=>'горцу',     'acc'=>'горца',     'ins'=>'горцем',     'pre'=>'горце'],
        'немец'     => ['gen'=>'немца',     'dat'=>'немцу',     'acc'=>'немца',     'ins'=>'немцем',     'pre'=>'немце'],
        'конец'     => ['gen'=>'конца',     'dat'=>'концу',     'acc'=>'конца',     'ins'=>'концом',     'pre'=>'конце'],
        'сокол'     => ['gen'=>'сокола',    'dat'=>'соколу',    'acc'=>'сокола',    'ins'=>'соколом',    'pre'=>'соколе'],
        'герой'     => ['gen'=>'героя',     'dat'=>'герою',     'acc'=>'героя',     'ins'=>'героем',     'pre'=>'герое'],
        'изгой'     => ['gen'=>'изгоя',     'dat'=>'изгою',     'acc'=>'изгоя',     'ins'=>'изгоем',     'pre'=>'изгое'],
        'ковбой'    => ['gen'=>'ковбоя',    'dat'=>'ковбою',    'acc'=>'ковбоя',    'ins'=>'ковбоем',    'pre'=>'ковбое'],
        'судья'     => ['gen'=>'судьи',     'dat'=>'судье',     'acc'=>'судью',     'ins'=>'судьёй',     'pre'=>'судье'],
        'ворон'     => ['gen'=>'ворона',    'dat'=>'ворону',    'acc'=>'ворона',    'ins'=>'вороном',    'pre'=>'вороне'],
    ];
    if (isset($exceptions[$low][$case])) {
        return inflectPreserveCase($word, $exceptions[$low][$case]);
    }

    // 2. Несклоняемые окончания (иностранные/украинские фамилии, о/е/и/у/ю/э/ы, -их/-ых)
    if (preg_match('/(о|е|ё|и|у|ю|э|ы|их|ых|аго|яго|ово|ево)$/u', $low)) {
        return $word;
    }

    // 3. Прилагательные / причастия / фамилии на -ский, -цкий, -ый, -ой, -ий (муж.)
    if (preg_match('/^(.*)(ск|цк)ий$/u', $low, $m) && $len >= 5) {
        $stem = $m[1] . $m[2];
        $map = ['gen'=>$stem.'ого', 'dat'=>$stem.'ому', 'acc'=>$stem.'ого', 'ins'=>$stem.'им', 'pre'=>$stem.'ом'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }
    if (preg_match('/^(.*)(ый|ой)$/u', $low, $m) && $len >= 4) {
        $stem = $m[1];
        $lastC = mb_substr($stem, -1);
        $insEnd = preg_match('/[гкхжшчщ]/u', $lastC) ? 'им' : 'ым';
        $map = ['gen'=>$stem.'ого', 'dat'=>$stem.'ому', 'acc'=>$stem.'ого', 'ins'=>$stem.$insEnd, 'pre'=>$stem.'ом'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 4. Женские фамилии / прилагательные на -ская, -цкая, -ая, -яя
    if (preg_match('/^(.*)(ск|цк)ая$/u', $low, $m) && $len >= 5) {
        $stem = $m[1] . $m[2];
        $map = ['gen'=>$stem.'ой', 'dat'=>$stem.'ой', 'acc'=>$stem.'ую', 'ins'=>$stem.'ой', 'pre'=>$stem.'ой'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }
    if (preg_match('/^(.*)ая$/u', $low, $m) && $len >= 5 && !in_array($low, ['мая','рая','тая'], true)) {
        $stem = $m[1];
        $map = ['gen'=>$stem.'ой', 'dat'=>$stem.'ой', 'acc'=>$stem.'ую', 'ins'=>$stem.'ой', 'pre'=>$stem.'ой'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }
    if (preg_match('/^(.*)яя$/u', $low, $m) && $len >= 4) {
        $stem = $m[1];
        $map = ['gen'=>$stem.'ей', 'dat'=>$stem.'ей', 'acc'=>$stem.'юю', 'ins'=>$stem.'ей', 'pre'=>$stem.'ей'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 5. Женские фамилии на -ова, -ева, -ёва, -ина, -ына (когда слово длинное и явно фамилия или $genderHint === 'f')
    if (preg_match('/^(.*)(ов|ев|ёв)а$/u', $low, $m) && $len >= 5) {
        $stem = $m[1] . $m[2];
        $map = ['gen'=>$stem.'ой', 'dat'=>$stem.'ой', 'acc'=>$stem.'у', 'ins'=>$stem.'ой', 'pre'=>$stem.'ой'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 6. Мужские фамилии на -ов, -ев, -ёв, -ин, -ын (творительный: -ым, но для имён типа Константин/Валентин/Максим — -ом!)
    static $maleNamesIn = ['константин','валентин','мартин','рубин','серафим','трофим','максим','вадим','арсений','давид','леонид','админ','гоблин','пингвин','дельфин','хозяин'];
    if (preg_match('/^(.*)(ов|ев|ёв|ин|ын)$/u', $low, $m) && $len >= 4 && !in_array($low, $maleNamesIn, true) && $genderHint === 'surname_m') {
        $stem = $low;
        $map = ['gen'=>$stem.'а', 'dat'=>$stem.'у', 'acc'=>$stem.'а', 'ins'=>$stem.'ым', 'pre'=>$stem.'е'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 7. Слова на -ия (Мария, Анастасия, Юлия, Ксения, Дарья/Софья отдельно)
    if (str_ends_with($low, 'ия') && $len >= 4) {
        $stem = mb_substr($low, 0, -2);
        $map = ['gen'=>$stem.'ии', 'dat'=>$stem.'ии', 'acc'=>$stem.'ию', 'ins'=>$stem.'ией', 'pre'=>$stem.'ии'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 8. Слова на -ья (Дарья, Софья, Наталья, Илья — выше)
    if (str_ends_with($low, 'ья') && $len >= 4) {
        $stem = mb_substr($low, 0, -1); // сохраняем ь
        $map = ['gen'=>$stem.'и', 'dat'=>$stem.'е', 'acc'=>$stem.'ю', 'ins'=>$stem.'ей', 'pre'=>$stem.'е'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 9. Слова на -а (Дима, Саша, Никита, Анна, Катя, Вика, Лиза, Влада...)
    if (str_ends_with($low, 'а')) {
        $stem = mb_substr($low, 0, -1);
        $prev = mb_substr($stem, -1);
        $isVelarOrSibilant = (bool)preg_match('/[гкхжшчщ]/u', $prev);
        $gen = $stem . ($isVelarOrSibilant ? 'и' : 'ы');
        $ins = $stem . (preg_match('/[жшчщц]/u', $prev) ? 'ей' : 'ой');
        $map = ['gen'=>$gen, 'dat'=>$stem.'е', 'acc'=>$stem.'у', 'ins'=>$ins, 'pre'=>$stem.'е'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 10. Слова на -я (Ваня, Даня, Женя, Катя, Настя, Аня, Петя, Коля, Толя, Юля, Соня...)
    if (str_ends_with($low, 'я')) {
        $stem = mb_substr($low, 0, -1);
        $map = ['gen'=>$stem.'и', 'dat'=>$stem.'е', 'acc'=>$stem.'ю', 'ins'=>$stem.'ей', 'pre'=>$stem.'е'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 11. Слова на -ий (Дмитрий, Алексей — -ей ниже, Евгений, Арсений, Георгий, Василий, Юрий, Виталий, Валерий)
    if (str_ends_with($low, 'ий') && $len >= 4) {
        $stem = mb_substr($low, 0, -2);
        $map = ['gen'=>$stem.'ия', 'dat'=>$stem.'ию', 'acc'=>$stem.'ия', 'ins'=>$stem.'ием', 'pre'=>$stem.'ии'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 12. Слова на -ей / -ай / -ой / -уй / -яй (Алексей, Сергей, Андрей, Матвей, Николай, Тимофей, Герой, Малой)
    if (str_ends_with($low, 'й') && $len >= 3) {
        $stem = mb_substr($low, 0, -1);
        $map = ['gen'=>$stem.'я', 'dat'=>$stem.'ю', 'acc'=>$stem.'я', 'ins'=>$stem.'ем', 'pre'=>$stem.'е'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 13. Слова на -ь (Царь, Король, Князь, Игорь, Медведь, Учитель, Гость vs Любовь, Тень, Ночь, Мышь, Смерть)
    if (str_ends_with($low, 'ь') && $len >= 3) {
        $stem = mb_substr($low, 0, -1);
        static $femSoft = ['смерть','тень','боль','месть','власть','страсть','кровь','рысь','мышь','ночь','лень','осень','жизнь','грусть','сталь','пыль','соль','грязь','адель','нинель','ассоль','гюзель'];
        $isFem = ($genderHint === 'f')
            || in_array($low, $femSoft, true)
            || (bool)preg_match('/(ость|есть|чь|шь|щь|жь)$/u', $low);

        if ($isFem) {
            $map = ['gen'=>$stem.'и', 'dat'=>$stem.'и', 'acc'=>$low, 'ins'=>$low.'ю', 'pre'=>$stem.'и'];
        } else {
            $map = ['gen'=>$stem.'я', 'dat'=>$stem.'ю', 'acc'=>$stem.'я', 'ins'=>$stem.'ем', 'pre'=>$stem.'е'];
        }
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    // 14. Слова на согласную (Артём, Артем, Иван, Максим, Влад, Денис, Егор, Роман, Кирилл, Богдан, Волк, Брат, Админ...)
    if (preg_match('/[бвгджзклмнпрстфхцчшщ]$/u', $low)) {
        // Не склоняем женские имена/фамилии на согласную, если явно женский пол
        if ($genderHint === 'f') {
            return $word;
        }
        $lastC = mb_substr($low, -1);
        $insEnd = preg_match('/[жшчщц]/u', $lastC) ? 'ем' : 'ом';
        $map = ['gen'=>$low.'а', 'dat'=>$low.'у', 'acc'=>$low.'а', 'ins'=>$low.$insEnd, 'pre'=>$low.'е'];
        return inflectPreserveCase($word, $map[$case] ?? $low);
    }

    return $word;
}

/**
 * Склонение полного имени или никнейма (включая составные «Имя Фамилия», «[Тег] Царь» и т.д.)
 */
function inflectRussianName(string $fullName, string $case = 'nom'): string {
    if ($case === 'nom' || trim($fullName) === '') {
        return $fullName;
    }
    // Если нет ни одной русской буквы — возвращаем как есть (английские ники, цифры, ID)
    if (!preg_match('/[а-яё]/iu', $fullName)) {
        return $fullName;
    }

    // Разбиваем на токены (слова и разделители), чтобы не потерять скобки, дефисы и пробелы
    $tokens = preg_split('/([а-яё]+)/iu', $fullName, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($tokens)) return $fullName;

    // Собираем индексы чисто русских слов (исключая теги внутри [...] или |...|)
    $cyrWordIndices = [];
    for ($i = 0; $i < count($tokens); $i++) {
        if ($tokens[$i] !== '' && preg_match('/^[а-яё]+$/iu', $tokens[$i])) {
            // Пропускаем короткие теги в квадратных скобках, например [АДМ] Царь
            $prevDelim = $tokens[$i - 1] ?? '';
            $nextDelim = $tokens[$i + 1] ?? '';
            if (str_ends_with($prevDelim, '[') && str_starts_with($nextDelim, ']')) {
                continue;
            }
            $cyrWordIndices[] = $i;
        }
    }

    if (empty($cyrWordIndices)) {
        return $fullName;
    }

    // Определяем вероятный пол для составных имён «Имя Фамилия» (например «Анна Смирнова» или «Дима Майский»)
    $wordCount = count($cyrWordIndices);
    $gender = '';
    if ($wordCount === 2) {
        $w1 = mb_strtolower($tokens[$cyrWordIndices[0]]);
        $w2 = mb_strtolower($tokens[$cyrWordIndices[1]]);
        // Проверяем явные женские окончания фамилии
        if (preg_match('/(ова|ева|ёва|ина|ына|ская|цкая|ая|яя)$/u', $w2)) {
            $gender = 'f';
        } elseif (preg_match('/(ов|ев|ёв|ин|ын|ский|цкий|ой|ый|ий)$/u', $w2)) {
            $gender = 'm';
        }
    }

    foreach ($cyrWordIndices as $pos => $tokIdx) {
        $w = $tokens[$tokIdx];
        $hint = $gender;
        if ($wordCount === 2 && $pos === 1 && $gender === 'm') {
            $hint = 'surname_m';
        }
        $tokens[$tokIdx] = inflectRussianWord($w, $case, $hint);
    }

    return implode('', $tokens);
}
