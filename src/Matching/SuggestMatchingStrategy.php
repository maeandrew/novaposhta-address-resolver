<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Matching;

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\SuggestionOnlyMatchingStrategy;

final class SuggestMatchingStrategy extends BalancedMatchingStrategy implements SuggestionOnlyMatchingStrategy
{
    public function isSuggestionOnly(): bool
    {
        return true;
    }
}
