<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI;

use MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts\AddressAiInterpreter;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiAddressHints;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiRanking;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\AiProviderException;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Candidate;
use Throwable;

final class ChainAddressAiInterpreter implements AddressAiInterpreter
{
    /**
     * @param list<AddressAiInterpreter> $interpreters
     */
    public function __construct(private readonly array $interpreters) {}

    public function parse(AddressInput $input): AiAddressHints
    {
        $failures = [];

        foreach ($this->interpreters as $interpreter) {
            try {
                return $interpreter->parse($input);
            } catch (Throwable $exception) {
                $failures[] = $exception::class;
            }
        }

        throw new AiProviderException(
            'All configured AI interpreters failed.',
            ['attempted_interpreters' => $failures],
        );
    }

    /**
     * @param list<Candidate> $candidates
     */
    public function rank(AddressInput $input, array $candidates): AiRanking
    {
        $failures = [];

        foreach ($this->interpreters as $interpreter) {
            try {
                return $interpreter->rank($input, $candidates);
            } catch (Throwable $exception) {
                $failures[] = $exception::class;
            }
        }

        throw new AiProviderException(
            'All configured AI interpreters failed.',
            ['attempted_interpreters' => $failures],
        );
    }
}
