<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Tests\Unit;

use MaeAndrew\NovaPoshtaAddressResolver\AddressResolver;
use MaeAndrew\NovaPoshtaAddressResolver\AI\ChainAddressAiInterpreter;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts\AddressAiInterpreter;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiAddressHints;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiRankedCandidate;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\AiRanking;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Redaction\AddressRedactor;
use MaeAndrew\NovaPoshtaAddressResolver\AI\StructuredAddressAiInterpreter;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Enums\ResolutionStatus;
use MaeAndrew\NovaPoshtaAddressResolver\Tests\Support\FakeStructuredAiProvider;
use MaeAndrew\NovaPoshtaAddressResolver\Tests\Support\FixtureLoader;
use MaeAndrew\NovaPoshtaAddressResolver\Tests\Support\FixtureLocationProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AiExtensionTest extends TestCase
{
    public function testStructuredInterpreterParsesHintsAndRedactsSensitiveText(): void
    {
        $provider = new FakeStructuredAiProvider([
            'parse_address' => [
                'city' => 'Київ',
                'region' => null,
                'district' => null,
                'warehouse_type' => 'branch',
                'warehouse_number' => 285,
                'warehouse_text' => 'відділення 285',
                'confidence' => 0.96,
                'uncertainties' => [],
            ],
        ]);
        $interpreter = new StructuredAddressAiInterpreter($provider);

        $hints = $interpreter->parse(AddressInput::fromText(
            'Київ, відділення №285, +380 67 123 45 67, order #ABC-12',
        ));

        self::assertSame('київ', mb_strtolower($hints->city ?? '', 'UTF-8'));
        self::assertTrue($hints->isUsable());
        self::assertStringContainsString('[REDACTED_PHONE]', $provider->calls[0]->userText);
        self::assertStringContainsString('[REDACTED_ORDER]', $provider->calls[0]->userText);
        self::assertStringNotContainsString('380 67 123 45 67', $provider->calls[0]->userText);
    }

    public function testUnknownAiCandidateIdCannotResolveAddress(): void
    {
        $provider = new FixtureLocationProvider(FixtureLoader::settlements(), FixtureLoader::warehouses());
        $interpreter = new class implements AddressAiInterpreter {
            public function parse(AddressInput $input): AiAddressHints
            {
                return new AiAddressHints('Київ', null, null, 'branch', null, 'відділення', 0.96);
            }

            public function rank(AddressInput $input, array $candidates): AiRanking
            {
                return new AiRanking([new AiRankedCandidate('invented-reference', 1.0)], 'hallucinated');
            }
        };

        $result = (new AddressResolver($provider, aiInterpreter: $interpreter))->resolve(
            AddressInput::fromText('Київ, відділення'),
        );

        self::assertSame(ResolutionStatus::AMBIGUOUS, $result->status);
        self::assertNull($result->warehouse);
        self::assertContains('ai_unknown_candidate_id', $result->diagnosticCodes());
    }

    public function testKnownAiCandidateCanResolveOnlyAfterProviderValidation(): void
    {
        $provider = new FixtureLocationProvider(FixtureLoader::settlements(), FixtureLoader::warehouses());
        $interpreter = new class implements AddressAiInterpreter {
            public function parse(AddressInput $input): AiAddressHints
            {
                return new AiAddressHints('Київ', null, null, 'branch', null, 'відділення', 0.96);
            }

            public function rank(AddressInput $input, array $candidates): AiRanking
            {
                return new AiRanking([
                    new AiRankedCandidate('fixture-kyiv-285', 0.99),
                    new AiRankedCandidate('fixture-kyiv-12', 0.12),
                ], 'known provider candidate');
            }
        };

        $result = (new AddressResolver($provider, aiInterpreter: $interpreter))->resolve(
            AddressInput::fromText('Київ, відділення'),
        );

        self::assertSame(ResolutionStatus::RESOLVED, $result->status);
        self::assertSame('fixture-kyiv-285', $result->warehouse?->ref);
        self::assertContains('resolved_by_validated_ai_rank', $result->diagnosticCodes());
    }

    public function testLowConfidenceAiHintsKeepAmbiguousResult(): void
    {
        $provider = new FixtureLocationProvider(FixtureLoader::settlements(), FixtureLoader::warehouses());
        $interpreter = new class implements AddressAiInterpreter {
            public function parse(AddressInput $input): AiAddressHints
            {
                return new AiAddressHints('Київ', null, null, 'unknown', null, null, 0.41, ['warehouse_not_provided']);
            }

            public function rank(AddressInput $input, array $candidates): AiRanking
            {
                throw new RuntimeException('Rank must not run for low-confidence parse.');
            }
        };

        $result = (new AddressResolver($provider, aiInterpreter: $interpreter))->resolve(
            AddressInput::fromText('Київ'),
        );

        self::assertSame(ResolutionStatus::AMBIGUOUS, $result->status);
        self::assertContains('ai_low_confidence', $result->diagnosticCodes());
    }

    public function testFallbackUsesTheNextInterpreterAfterFailure(): void
    {
        $failed = new class implements AddressAiInterpreter {
            public function parse(AddressInput $input): AiAddressHints
            {
                throw new RuntimeException('first failed');
            }

            public function rank(AddressInput $input, array $candidates): AiRanking
            {
                throw new RuntimeException('first failed');
            }
        };
        $working = new class implements AddressAiInterpreter {
            public function parse(AddressInput $input): AiAddressHints
            {
                return new AiAddressHints('Київ', null, null, 'branch', 285, 'відділення 285', 0.95);
            }

            public function rank(AddressInput $input, array $candidates): AiRanking
            {
                return new AiRanking([]);
            }
        };

        $hints = (new ChainAddressAiInterpreter([$failed, $working]))->parse(AddressInput::fromText('Київ 285'));

        self::assertSame('Київ', $hints->city);
        self::assertSame(0.95, $hints->confidence);
    }

    public function testRedactorRemovesPhoneEmailAndOrderIdentifiers(): void
    {
        $redacted = (new AddressRedactor())->redact(
            'Київ, +380 67 123 45 67, user@example.test, замовлення №A-15',
        );

        self::assertStringContainsString('[REDACTED_PHONE]', $redacted);
        self::assertStringContainsString('[REDACTED_EMAIL]', $redacted);
        self::assertStringContainsString('[REDACTED_ORDER]', $redacted);
        self::assertStringNotContainsString('user@example.test', $redacted);
    }
}
