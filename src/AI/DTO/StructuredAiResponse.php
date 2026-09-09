<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\DTO;

use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\InvalidStructuredResponseException;

final readonly class StructuredAiResponse
{
    /**
     * @param array<string, mixed> $data
     * @param list<string> $diagnostics
     */
    public function __construct(
        public array $data,
        public ?string $providerName = null,
        public ?int $latencyMs = null,
        public array $diagnostics = [],
    ) {}

    public static function fromJson(
        string $json,
        ?string $providerName = null,
        ?int $latencyMs = null,
    ): self {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidStructuredResponseException(
                'AI provider returned invalid JSON.',
                ['provider' => $providerName],
                $exception,
            );
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidStructuredResponseException(
                'AI provider returned a JSON value with the wrong shape.',
                ['provider' => $providerName],
            );
        }

        return new self($data, $providerName, $latencyMs);
    }
}
