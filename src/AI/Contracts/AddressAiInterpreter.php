<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts;

use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiAddressHints;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiRanking;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Candidate;

interface AddressAiInterpreter
{
    public function parse(AddressInput $input): AiAddressHints;

    /**
     * @param list<Candidate> $candidates
     */
    public function rank(AddressInput $input, array $candidates): AiRanking;
}
