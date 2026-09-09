<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\OpenAI\Tests;

use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\StructuredPrompt;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\AiProviderException;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\InvalidStructuredResponseException;
use MaeAndrew\NovaPoshtaAddressResolver\OpenAI\OpenAiStructuredAiProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class OpenAiStructuredAiProviderTest extends TestCase
{
    public function testItSendsStructuredResponsesRequestAndParsesOutputText(): void
    {
        $client = new FakeClient(new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'output_text' => json_encode([
                    'city' => 'Київ',
                    'region' => null,
                    'district' => null,
                    'warehouse_type' => 'branch',
                    'warehouse_number' => 285,
                    'warehouse_text' => 'відділення 285',
                    'confidence' => 0.96,
                    'uncertainties' => [],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ));
        $factory = new Psr17Factory();
        $provider = new OpenAiStructuredAiProvider(
            $client,
            $factory,
            $factory,
            'test-key',
            'test-model',
            'https://example.test/v1/responses',
        );

        $result = $provider->generate(StructuredPrompt::forAddressParsing('Київ, відділення 285'));

        self::assertSame('Київ', $result->data['city']);
        self::assertSame('openai', $result->providerName);
        self::assertNotNull($result->latencyMs);
        self::assertNotNull($client->request);
        self::assertSame('POST', $client->request->getMethod());
        self::assertSame('Bearer test-key', $client->request->getHeaderLine('Authorization'));

        $requestData = json_decode((string) $client->request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('test-model', $requestData['model']);
        self::assertSame('json_schema', $requestData['text']['format']['type']);
        self::assertSame('address_hints', $requestData['text']['format']['name']);
        self::assertFalse($requestData['store']);
    }

    public function testItSupportsNestedResponsesOutput(): void
    {
        $client = new FakeClient(new Response(
            200,
            [],
            json_encode([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => '{"ranked_candidates":[],"reason":"none"}',
                    ]],
                ]],
            ], JSON_THROW_ON_ERROR),
        ));
        $factory = new Psr17Factory();
        $provider = new OpenAiStructuredAiProvider(
            $client,
            $factory,
            $factory,
            'test-key',
        );

        $result = $provider->generate(StructuredPrompt::forCandidateRanking('Київ', []));

        self::assertSame([], $result->data['ranked_candidates']);
    }

    public function testItDoesNotExposeResponseBodyOnHttpFailure(): void
    {
        $client = new FakeClient(new Response(429, [], '{"error":"secret provider details"}'));
        $factory = new Psr17Factory();
        $provider = new OpenAiStructuredAiProvider($client, $factory, $factory, 'test-key');

        try {
            $provider->generate(StructuredPrompt::forAddressParsing('Київ'));
            self::fail('Expected an AI provider exception.');
        } catch (AiProviderException $exception) {
            self::assertSame(429, $exception->context['status_code']);
            self::assertStringNotContainsString('secret provider details', $exception->getMessage());
        }
    }

    public function testItRejectsResponsesWithoutStructuredText(): void
    {
        $client = new FakeClient(new Response(200, [], '{"output":[]}'));
        $factory = new Psr17Factory();
        $provider = new OpenAiStructuredAiProvider($client, $factory, $factory, 'test-key');

        $this->expectException(InvalidStructuredResponseException::class);
        $provider->generate(StructuredPrompt::forAddressParsing('Київ'));
    }
}

final class FakeClient implements ClientInterface
{
    public ?RequestInterface $request = null;

    public function __construct(private readonly ResponseInterface $response) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return $this->response;
    }
}
