<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Support;

use MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts\StructuredAiProvider;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\StructuredAiResponse;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\StructuredPrompt;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\AiProviderException;

final class FakeStructuredAiProvider implements StructuredAiProvider
{
    /**
     * @param array<string, array<string, mixed>> $responses
     */
    public function __construct(
        private readonly array $responses,
        private readonly bool $fail = false,
    ) {}

    /** @var list<StructuredPrompt> */
    public array $calls = [];

    public function generate(StructuredPrompt $prompt): StructuredAiResponse
    {
        $this->calls[] = $prompt;

        if ($this->fail) {
            throw new AiProviderException('Synthetic AI provider failure.');
        }

        if (!isset($this->responses[$prompt->task])) {
            throw new AiProviderException('Synthetic AI response is missing.');
        }

        return new StructuredAiResponse($this->responses[$prompt->task], 'fake-ai');
    }
}
