<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Contracts;

interface SuggestionOnlyMatchingStrategy
{
    public function isSuggestionOnly(): bool;
}
