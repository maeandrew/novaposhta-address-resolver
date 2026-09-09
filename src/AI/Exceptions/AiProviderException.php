<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
