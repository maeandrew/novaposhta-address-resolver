<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\OpenAI;

use MaeAndrew\NovaPoshtaAddressResolver\AI\Contracts\StructuredAiProvider;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\StructuredAiResponse;
use MaeAndrew\NovaPoshtaAddressResolver\AI\DTO\StructuredPrompt;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\AiProviderException;
use MaeAndrew\NovaPoshtaAddressResolver\AI\Exceptions\InvalidStructuredResponseException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class OpenAiStructuredAiProvider implements StructuredAiProvider
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
        private readonly string $endpoint = 'https://api.openai.com/v1/responses',
        private readonly bool $store = false,
    ) {
        if (trim($this->apiKey) === '') {
            throw new \InvalidArgumentException('An OpenAI API key is required.');
        }

        if (trim($this->model) === '') {
            throw new \InvalidArgumentException('An OpenAI model is required.');
        }

        if (filter_var($this->endpoint, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('The OpenAI endpoint must be a valid URL.');
        }
    }

    public function generate(StructuredPrompt $prompt): StructuredAiResponse
    {
        $startedAt = microtime(true);
        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt->systemInstruction],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt->userText],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $prompt->schemaName,
                    'strict' => true,
                    'schema' => $prompt->schema,
                ],
            ],
            'store' => $this->store,
        ];

        try {
            $request = $this->requestFactory
                ->createRequest('POST', $this->endpoint)
                ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                )));

            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new AiProviderException(
                'The OpenAI HTTP request failed.',
                ['provider' => 'openai'],
                $exception,
            );
        } catch (\JsonException $exception) {
            throw new AiProviderException(
                'The OpenAI request could not be encoded as JSON.',
                ['provider' => 'openai'],
                $exception,
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new AiProviderException(
                'The OpenAI API returned an error response.',
                [
                    'provider' => 'openai',
                    'status_code' => $statusCode,
                ],
            );
        }

        try {
            $responseData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidStructuredResponseException(
                'The OpenAI API returned invalid JSON.',
                ['provider' => 'openai', 'status_code' => $statusCode],
                $exception,
            );
        }

        if (!is_array($responseData)) {
            throw new InvalidStructuredResponseException(
                'The OpenAI API returned a response with the wrong shape.',
                ['provider' => 'openai', 'status_code' => $statusCode],
            );
        }

        return StructuredAiResponse::fromJson(
            $this->extractOutputText($responseData),
            'openai',
            $latencyMs,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractOutputText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $item) {
                if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) {
                    continue;
                }

                foreach ($item['content'] as $content) {
                    if (is_array($content) && isset($content['text']) && is_string($content['text'])) {
                        return $content['text'];
                    }
                }
            }
        }

        throw new InvalidStructuredResponseException(
            'The OpenAI response did not contain structured output text.',
            ['provider' => 'openai'],
        );
    }
}
