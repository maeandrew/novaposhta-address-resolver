<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Matching;

final class SuggestMatchingStrategy extends BalancedMatchingStrategy
{
    public function isSuggestionOnly(): bool
    {
        return true;
    }
}
