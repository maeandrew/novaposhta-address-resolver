<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Parsing;

final class SpokenNumberParser
{
    /** @var array<string, int> */
    private const WORDS = [
        'нуль' => 0,
        'ноль' => 0,
        'один' => 1,
        'одна' => 1,
        'одно' => 1,
        'одне' => 1,
        'два' => 2,
        'дві' => 2,
        'две' => 2,
        'три' => 3,
        'чотири' => 4,
        'четыре' => 4,
        'пять' => 5,
        'пятьох' => 5,
        'п’ять' => 5,
        'п' . "'" . 'ять' => 5,
        'п’ятьох' => 5,
        'шість' => 6,
        'шесть' => 6,
        'сім' => 7,
        'семь' => 7,
        'вісім' => 8,
        'восемь' => 8,
        'дев’ять' => 9,
        'дев' . "'" . 'ять' => 9,
        'девять' => 9,
        'десять' => 10,
        'одинадцять' => 11,
        'одиннадцать' => 11,
        'дванадцять' => 12,
        'двенадцать' => 12,
        'тринадцять' => 13,
        'тринадцать' => 13,
        'чотирнадцять' => 14,
        'четырнадцать' => 14,
        'п’ятнадцять' => 15,
        'п' . "'" . 'ятнадцять' => 15,
        'пятнадцять' => 15,
        'пятнадцать' => 15,
        'шістнадцять' => 16,
        'шістнадцать' => 16,
        'шестнадцать' => 16,
        'сімнадцять' => 17,
        'семнадцать' => 17,
        'вісімнадцять' => 18,
        'восемнадцать' => 18,
        'дев’ятнадцять' => 19,
        'дев' . "'" . 'ятнадцять' => 19,
        'девятнадцять' => 19,
        'девятнадцать' => 19,
        'двадцять' => 20,
        'двадцать' => 20,
        'тридцять' => 30,
        'тридцать' => 30,
        'сорок' => 40,
        'п’ятдесят' => 50,
        'п' . "'" . 'ятдесят' => 50,
        'пятдесят' => 50,
        'пятьдесят' => 50,
        'шістдесят' => 60,
        'шестьдесят' => 60,
        'сімдесят' => 70,
        'семьдесят' => 70,
        'вісімдесят' => 80,
        'восемьдесят' => 80,
        'дев’яносто' => 90,
        'дев' . "'" . 'яносто' => 90,
        'девяносто' => 90,
        'сто' => 100,
        'двісті' => 200,
        'двести' => 200,
        'триста' => 300,
        'чотириста' => 400,
        'четыреста' => 400,
        'п’ятсот' => 500,
        'п' . "'" . 'ятсот' => 500,
        'пятсот' => 500,
        'пятьсот' => 500,
        'шістсот' => 600,
        'шестьсот' => 600,
        'сімсот' => 700,
        'семьсот' => 700,
        'вісімсот' => 800,
        'восемьсот' => 800,
        'дев’ятсот' => 900,
        'дев' . "'" . 'ятсот' => 900,
        'девятьсот' => 900,
        'тисяча' => 1000,
        'тисячі' => 1000,
        'тисяч' => 1000,
        'тысяча' => 1000,
        'тысячи' => 1000,
        'тысяч' => 1000,
    ];

    public function parse(string $value): ?int
    {
        $parsed = $this->parseTokens($this->tokens($value));

        return $parsed['consumed'] === $parsed['total'] && $parsed['consumed'] > 0
            ? $parsed['value']
            : null;
    }

    public function parsePrefix(string $value): ?int
    {
        $tokens = $this->tokens($value);
        $parsed = $this->parseTokens($tokens);

        return $parsed['consumed'] > 0 ? $parsed['value'] : null;
    }

    public function parseSuffix(string $value): ?int
    {
        $tokens = $this->tokens($value);

        for ($offset = 0, $count = count($tokens); $offset < $count; $offset++) {
            $parsed = $this->parseTokens(array_slice($tokens, $offset));

            if ($parsed['consumed'] === $count - $offset && $parsed['consumed'] > 0) {
                return $parsed['value'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     * @return array{value: int, consumed: int, total: int}
     */
    private function parseTokens(array $tokens): array
    {
        $total = 0;
        $current = 0;
        $consumed = 0;

        foreach ($tokens as $token) {
            $word = self::canonicalize($token);

            if (!array_key_exists($word, self::WORDS)) {
                break;
            }

            $part = self::WORDS[$word];

            if ($part === 1000) {
                $total += ($current === 0 ? 1 : $current) * $part;
                $current = 0;
            } else {
                $current += $part;
            }

            $consumed++;
        }

        return [
            'value' => $total + $current,
            'consumed' => $consumed,
            'total' => count($tokens),
        ];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(['’', 'ʼ', '`'], "'", $value);

        $tokens = preg_split('/[\s,.;:()\[\]{}\/-]+/u', trim($value));

        return is_array($tokens)
            ? array_values(array_filter($tokens, static fn(string $token): bool => $token !== ''))
            : [];
    }

    private static function canonicalize(string $word): string
    {
        return str_replace("'", '', $word);
    }
}
