<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver;

use MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts\AddressAiInterpreter;
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\AddressParser;
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;
use MaeAndrew\NovaPoshtaAddressResolver\Contracts\MatchingStrategy;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Candidate;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Diagnostic;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\MatchResult;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ParsedAddress;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ProviderHealth;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionPolicy;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionResult;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Settlement;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\SettlementQuery;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\Warehouse;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\WarehouseQuery;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\ResolutionStatus;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\WarehouseType;
use MaeAndrew\NovaPoshtaAddressResolver\Exceptions\InvalidProviderResponseException;
use MaeAndrew\NovaPoshtaAddressResolver\Matching\BalancedMatchingStrategy;
use MaeAndrew\NovaPoshtaAddressResolver\Parsing\UkrainianAddressParser;
use Throwable;

final class AddressResolver
{
    private readonly AddressParser $parser;
    private readonly MatchingStrategy $matchingStrategy;
    private readonly ResolutionPolicy $policy;

    public function __construct(
        private readonly LocationProvider $locationProvider,
        ?AddressParser $parser = null,
        ?MatchingStrategy $matchingStrategy = null,
        ?ResolutionPolicy $policy = null,
        private readonly ?AddressAiInterpreter $aiInterpreter = null,
    ) {
        $this->parser = $parser ?? new UkrainianAddressParser();
        $this->matchingStrategy = $matchingStrategy ?? new BalancedMatchingStrategy();
        $this->policy = $policy ?? new ResolutionPolicy();
    }

    public function resolve(AddressInput $input): ResolutionResult
    {
        $deterministic = $this->resolveDeterministic($input);

        if ($this->aiInterpreter === null
            || $deterministic->isResolved()
            || $deterministic->status === ResolutionStatus::PROVIDER_ERROR
            || $deterministic->status === ResolutionStatus::INVALID_INPUT) {
            return $deterministic;
        }

        return $this->resolveWithAi($input, $deterministic);
    }

    private function resolveDeterministic(AddressInput $input): ResolutionResult
    {
        $parsed = $this->parser->parse($input);

        if (!$input->hasMeaningfulData() || ($parsed->city === '' && $parsed->settlementRef === null)) {
            return new ResolutionResult(
                ResolutionStatus::INVALID_INPUT,
                null,
                null,
                0.0,
                [],
                $parsed,
                [new Diagnostic(
                    'invalid_input',
                    'The address does not contain a meaningful settlement or provider reference.',
                    'warning',
                )],
            );
        }

        $query = AddressQuery::fromParsed($parsed);
        $health = $this->checkProvider($parsed);

        if ($health instanceof ResolutionResult) {
            return $health;
        }

        $providerName = $health->providerName;
        $diagnostics = $this->diagnostics($health->diagnostics);

        try {
            [$settlements, $settlementDiagnostics, $providerName] = $this->fetchSettlements($query, $providerName);
            $diagnostics = [...$diagnostics, ...$settlementDiagnostics];

            $settlementMatches = $this->matchingStrategy->match($query, $settlements);
            $settlementCandidates = $this->positiveCandidates($settlementMatches);
            $settlementTop = $settlementMatches->top();

            if ($settlementTop === null
                || $settlementTop->settlement === null
                || $settlementTop->score < $this->policy->settlementMinimumScore) {
                $code = $query->settlementRef !== null
                    ? 'settlement_reference_not_found'
                    : 'settlement_not_found';

                return new ResolutionResult(
                    $query->settlementRef !== null ? ResolutionStatus::INVALID_INPUT : ResolutionStatus::NOT_FOUND,
                    null,
                    null,
                    $settlementTop?->score ?? 0.0,
                    $settlementCandidates,
                    $parsed,
                    [...$diagnostics, new Diagnostic($code, 'No provider settlement matched the address.', 'warning')],
                    $providerName,
                );
            }

            $settlementSecond = $settlementMatches->second();
            if ($settlementSecond !== null
                && $settlementTop->score - $settlementSecond->score < $this->policy->ambiguityMargin) {
                return new ResolutionResult(
                    ResolutionStatus::AMBIGUOUS,
                    null,
                    null,
                    $settlementTop->score,
                    $settlementCandidates,
                    $parsed,
                    [...$diagnostics, new Diagnostic(
                        'ambiguous_settlement',
                        'More than one settlement remains plausible.',
                        'warning',
                    )],
                    $providerName,
                );
            }

            $settlement = $settlementTop->settlement;
            [$warehouses, $warehouseDiagnostics, $providerName] = $this->fetchWarehouses(
                $settlement,
                $query,
                $providerName,
            );
            $diagnostics = [...$diagnostics, ...$warehouseDiagnostics];

            if ($warehouses === []) {
                return new ResolutionResult(
                    ResolutionStatus::NOT_FOUND,
                    $settlement,
                    null,
                    0.0,
                    [],
                    $parsed,
                    [...$diagnostics, new Diagnostic(
                        'warehouse_not_found',
                        'The provider returned no active warehouses for the settlement.',
                        'warning',
                    )],
                    $providerName,
                );
            }

            $warehouseMatches = $this->matchingStrategy->match($query, $warehouses);
            $warehouseCandidates = $this->positiveCandidates($warehouseMatches);
            $warehouseTop = $warehouseMatches->top();
            $plausibleWarehouses = $warehouseMatches->above($this->policy->minimumCandidateScore);

            if ($warehouseTop === null || $plausibleWarehouses === []) {
                $hasWarehouseHint = $query->hasWarehouseHint();
                $status = $hasWarehouseHint ? ResolutionStatus::NOT_FOUND : ResolutionStatus::AMBIGUOUS;
                $code = $hasWarehouseHint ? 'warehouse_not_found' : 'warehouse_not_specified';

                return new ResolutionResult(
                    $status,
                    $settlement,
                    null,
                    $warehouseTop?->score ?? 0.0,
                    $hasWarehouseHint ? [] : $warehouseCandidates,
                    $parsed,
                    [...$diagnostics, new Diagnostic(
                        $code,
                        $hasWarehouseHint
                            ? 'No warehouse matched the requested type, number, or address.'
                            : 'A warehouse must be selected before the address can be resolved.',
                        'warning',
                    )],
                    $providerName,
                );
            }

            if (!$query->hasWarehouseHint()) {
                return new ResolutionResult(
                    ResolutionStatus::AMBIGUOUS,
                    $settlement,
                    null,
                    $warehouseTop->score,
                    $warehouseCandidates,
                    $parsed,
                    [...$diagnostics, new Diagnostic(
                        'warehouse_not_specified',
                        'A warehouse must be selected before the address can be resolved.',
                        'warning',
                    )],
                    $providerName,
                );
            }

            $suggestionOnly = method_exists($this->matchingStrategy, 'isSuggestionOnly')
                && $this->matchingStrategy->isSuggestionOnly();

            if (!$suggestionOnly && $this->policy->canAutoResolve($warehouseMatches)) {
                return new ResolutionResult(
                    ResolutionStatus::RESOLVED,
                    $settlement,
                    $warehouseTop->warehouse,
                    $warehouseTop->score,
                    $warehouseCandidates,
                    $parsed,
                    [...$diagnostics, new Diagnostic(
                        'resolved_by_deterministic_match',
                        'Settlement and warehouse passed the configured resolution policy.',
                    )],
                    $providerName,
                );
            }

            return new ResolutionResult(
                ResolutionStatus::AMBIGUOUS,
                $settlement,
                null,
                $warehouseTop->score,
                $warehouseCandidates,
                $parsed,
                [...$diagnostics, new Diagnostic(
                    'ambiguous_warehouse',
                    'Warehouse candidates did not pass the configured resolution policy.',
                    'warning',
                )],
                $providerName,
            );
        } catch (Throwable $exception) {
            return new ResolutionResult(
                ResolutionStatus::PROVIDER_ERROR,
                null,
                null,
                0.0,
                [],
                $parsed,
                [...$diagnostics, new Diagnostic(
                    'provider_error',
                    'The location provider failed while resolving the address.',
                    'error',
                    ['exception' => $exception::class],
                )],
                $providerName,
            );
        }
    }

    private function resolveWithAi(AddressInput $input, ResolutionResult $deterministic): ResolutionResult
    {
        $aiInterpreter = $this->aiInterpreter;
        if ($aiInterpreter === null) {
            return $deterministic;
        }

        try {
            $hints = $aiInterpreter->parse($input);
        } catch (Throwable $exception) {
            return $this->withDiagnostic($deterministic, new Diagnostic(
                'ai_failure',
                'The configured AI interpreter failed; the deterministic result was retained.',
                'warning',
                ['exception' => $exception::class],
            ));
        }

        if (!$hints->isUsable()) {
            return $this->withDiagnostic($deterministic, new Diagnostic(
                'ai_low_confidence',
                'AI hints did not reach the minimum confidence required for a retry.',
                'info',
                ['confidence' => $hints->confidence],
            ));
        }

        $aiResult = $this->resolveDeterministic($hints->toAddressInput($input));
        $base = $this->preferAiResult($deterministic, $aiResult);

        if ($base->candidates === [] || $base->settlement === null) {
            return $this->withDiagnostic($base, new Diagnostic(
                'ai_parse_applied',
                'AI address hints were validated through the deterministic provider pipeline.',
                'info',
            ));
        }

        try {
            $ranking = $aiInterpreter->rank($input, $base->candidates);
        } catch (Throwable $exception) {
            return $this->withDiagnostic($base, new Diagnostic(
                'ai_failure',
                'The configured AI ranker failed; the deterministic result was retained.',
                'warning',
                ['exception' => $exception::class],
            ));
        }

        $validation = $ranking->validateAgainst(array_map(
            static fn(Candidate $candidate): string => $candidate->id,
            $base->candidates,
        ));

        if (!$validation->accepted || $validation->ranking === null) {
            return $this->withDiagnostic($base, new Diagnostic(
                'ai_unknown_candidate_id',
                'AI ranking referenced a candidate that was not supplied by the provider.',
                'warning',
                ['candidate_ids' => $validation->unknownCandidateIds],
            ));
        }

        $validatedRanking = $validation->ranking;
        $top = $validatedRanking->top();
        if ($top === null || !$validatedRanking->canAutoSelect(
            $this->policy->autoResolveThreshold,
            $this->policy->ambiguityMargin,
        )) {
            return $this->withDiagnostic($base, new Diagnostic(
                'ai_rank_not_confident',
                'AI ranking was validated but did not pass the resolution policy.',
                'info',
            ));
        }

        $candidate = null;
        foreach ($base->candidates as $possibleCandidate) {
            if ($possibleCandidate->id === $top->candidateId) {
                $candidate = $possibleCandidate;
                break;
            }
        }

        if ($candidate === null
            || $candidate->warehouse === null
            || $candidate->score < $this->policy->minimumCandidateScore
            || $candidate->warehouse->settlementRef !== $base->settlement->ref) {
            return $this->withDiagnostic($base, new Diagnostic(
                'ai_candidate_not_resolvable',
                'The AI-selected candidate did not pass provider and deterministic validation.',
                'warning',
            ));
        }

        return new ResolutionResult(
            ResolutionStatus::RESOLVED,
            $base->settlement,
            $candidate->warehouse,
            min($candidate->score, $top->score),
            $base->candidates,
            $base->parsedAddress,
            [...$base->diagnostics, new Diagnostic(
                'resolved_by_validated_ai_rank',
                'A known provider candidate passed the AI and deterministic resolution policies.',
                'info',
            )],
            $base->providerName,
        );
    }

    private function preferAiResult(ResolutionResult $deterministic, ResolutionResult $aiResult): ResolutionResult
    {
        if ($aiResult->isResolved()) {
            return $aiResult;
        }

        if ($deterministic->status === ResolutionStatus::NOT_FOUND
            && $aiResult->status !== ResolutionStatus::NOT_FOUND
            && $aiResult->status !== ResolutionStatus::PROVIDER_ERROR) {
            return $aiResult;
        }

        if ($deterministic->needsReview()
            && $aiResult->needsReview()
            && $aiResult->confidence > $deterministic->confidence) {
            return $aiResult;
        }

        return $deterministic;
    }

    private function withDiagnostic(ResolutionResult $result, Diagnostic $diagnostic): ResolutionResult
    {
        return new ResolutionResult(
            $result->status,
            $result->settlement,
            $result->warehouse,
            $result->confidence,
            $result->candidates,
            $result->parsedAddress,
            [...$result->diagnostics, $diagnostic],
            $result->providerName,
        );
    }

    private function checkProvider(ParsedAddress $parsed): ProviderHealth|ResolutionResult
    {
        try {
            $health = $this->locationProvider->healthCheck();
        } catch (Throwable $exception) {
            return new ResolutionResult(
                ResolutionStatus::PROVIDER_ERROR,
                null,
                null,
                0.0,
                [],
                $parsed,
                [new Diagnostic(
                    'provider_health_error',
                    'The location provider health check failed.',
                    'error',
                    ['exception' => $exception::class],
                )],
            );
        }

        if (!$health->healthy) {
            return new ResolutionResult(
                ResolutionStatus::PROVIDER_ERROR,
                null,
                null,
                0.0,
                [],
                $parsed,
                [...$this->diagnostics($health->diagnostics), new Diagnostic(
                    'provider_unhealthy',
                    $health->message ?? 'The location provider is unavailable.',
                    'error',
                )],
                $health->providerName,
            );
        }

        return $health;
    }

    /**
     * @return array{0: list<Settlement>, 1: list<Diagnostic>, 2: ?string}
     */
    private function fetchSettlements(AddressQuery $query, ?string $providerName): array
    {
        $queries = [new SettlementQuery(
            reference: $query->settlementRef,
            name: $query->city !== '' ? $query->city : null,
            normalizedName: $query->city !== '' ? $query->city : null,
            region: $query->region,
            district: $query->district,
        )];

        if ($query->settlementRef === null && $query->city !== '') {
            $queries[] = new SettlementQuery(
                name: $query->city,
                normalizedName: $query->city,
                region: $query->region,
                district: $query->district,
                fuzzy: true,
            );
        }

        $records = [];
        $diagnostics = [];

        foreach ($queries as $index => $settlementQuery) {
            $result = $this->locationProvider->searchSettlements($settlementQuery);
            $providerName = $result->providerName ?? $providerName;
            $diagnostics = [...$diagnostics, ...$this->diagnostics($result->diagnostics)];

            foreach ($result->records as $record) {
                if (!$record instanceof Settlement) {
                    throw new InvalidProviderResponseException('Settlement provider returned a non-settlement record.');
                }

                $records[$record->ref] = $record;
            }

            if ($records !== [] || $index === count($queries) - 1 || $query->settlementRef !== null) {
                break;
            }
        }

        return [array_values($records), $diagnostics, $providerName];
    }

    /**
     * @return array{0: list<Warehouse>, 1: list<Diagnostic>, 2: ?string}
     */
    private function fetchWarehouses(
        Settlement $settlement,
        AddressQuery $query,
        ?string $providerName,
    ): array {
        $queries = [new WarehouseQuery(
            reference: $query->warehouseRef,
            number: $query->warehouseNumber,
            address: $query->streetAddress,
            type: $query->warehouseType === WarehouseType::UNKNOWN ? null : $query->warehouseType->value,
        )];

        if ($query->warehouseRef === null && $query->warehouseNumber !== null) {
            $queries[] = new WarehouseQuery(
                address: $query->streetAddress,
                type: $query->warehouseType === WarehouseType::UNKNOWN ? null : $query->warehouseType->value,
                fuzzy: true,
            );
        }

        $records = [];
        $diagnostics = [];

        foreach ($queries as $index => $warehouseQuery) {
            $result = $this->locationProvider->searchWarehouses($settlement, $warehouseQuery);
            $providerName = $result->providerName ?? $providerName;
            $diagnostics = [...$diagnostics, ...$this->diagnostics($result->diagnostics)];

            foreach ($result->records as $record) {
                if (!$record instanceof Warehouse) {
                    throw new InvalidProviderResponseException('Warehouse provider returned a non-warehouse record.');
                }

                if ($record->settlementRef !== $settlement->ref) {
                    throw new InvalidProviderResponseException(
                        'Warehouse provider returned a record for a different settlement.',
                    );
                }

                if (!$record->isActive) {
                    $diagnostics[] = new Diagnostic(
                        'inactive_warehouse_skipped',
                        'An inactive warehouse was excluded from matching.',
                        'info',
                        ['warehouse_ref' => $record->ref],
                    );
                    continue;
                }

                $records[$record->ref] = $record;
            }

            if ($records !== [] || $index === count($queries) - 1 || $query->warehouseRef !== null) {
                break;
            }
        }

        return [array_values($records), $diagnostics, $providerName];
    }

    /**
     * @return list<Candidate>
     */
    private function positiveCandidates(MatchResult $matches): array
    {
        return array_values(array_filter(
            $matches->candidates,
            static fn(Candidate $candidate): bool => $candidate->score > 0.0,
        ));
    }

    /**
     * @param list<Diagnostic|string> $diagnostics
     * @return list<Diagnostic>
     */
    private function diagnostics(array $diagnostics): array
    {
        return array_map(
            static fn(Diagnostic|string $diagnostic): Diagnostic => $diagnostic instanceof Diagnostic
                ? $diagnostic
                : Diagnostic::fromString($diagnostic),
            $diagnostics,
        );
    }
}
