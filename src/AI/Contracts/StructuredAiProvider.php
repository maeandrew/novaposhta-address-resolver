<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts;

use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\StructuredAiResponse;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\StructuredPrompt;

interface StructuredAiProvider
{
    public function generate(StructuredPrompt $prompt): StructuredAiResponse;
}
