<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Support;

use MaeAndrew\NovaPoshtaAddressResolver\Support\Rules\CaseFoldRule;
use MaeAndrew\NovaPoshtaAddressResolver\Support\Rules\PunctuationRule;
use MaeAndrew\NovaPoshtaAddressResolver\Support\Rules\UkrainianAbbreviationRule;
use MaeAndrew\NovaPoshtaAddressResolver\Support\Rules\UnicodeWhitespaceRule;

final class TextNormalizer
{
    private static ?self $default = null;

    public function __construct(private readonly NormalizationPipeline $pipeline) {}

    public function normalize(string $value): string
    {
        return $this->pipeline->normalize($value);
    }

    public static function default(): self
    {
        return self::$default ??= new self(new NormalizationPipeline([
            new UnicodeWhitespaceRule(),
            new CaseFoldRule(),
            new UkrainianAbbreviationRule(),
            new PunctuationRule(),
            new UnicodeWhitespaceRule(),
        ]));
    }
}
