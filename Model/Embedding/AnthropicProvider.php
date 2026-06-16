<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Embedding;

use GuzzleHttp\ClientInterface;
use Magento\Framework\Phrase;
use Rivicore\SemantiQ\Api\EmbeddingProviderInterface;
use Rivicore\SemantiQ\Exception\EmbeddingException;
use Rivicore\SemantiQ\Model\Config;

/**
 * Experimental: Anthropic has no dedicated embedding API.
 * This provider uses a Claude model to produce a JSON float array via a structured prompt.
 * It is significantly slower and more expensive than a dedicated embedding model.
 * Recommend using OpenAI, Bedrock, or Ollama for production embedding workloads.
 */
class AnthropicProvider implements EmbeddingProviderInterface
{
    private const API_URL  = 'https://api.anthropic.com/v1/messages';
    private const DIMENSION = 256;

    public function __construct(
        private readonly Config          $config,
        private readonly ClientInterface $httpClient
    ) {}

    public function embed(string $text): array
    {
        $apiKey = $this->config->getAnthropicApiKey();
        $model  = $this->config->getAnthropicModel();

        if (!$apiKey) {
            throw new EmbeddingException(new Phrase('SemantiQ: Anthropic API key is not configured.'));
        }

        $prompt = sprintf(
            'Produce a %d-dimensional semantic embedding vector for the following text as a JSON array of floats. Output ONLY the JSON array, no explanation. Text: %s',
            self::DIMENSION,
            substr($text, 0, 1000)
        );

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => $model,
                    'max_tokens' => 4096,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
            ]);

            $body    = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $content = $body['content'][0]['text'] ?? '[]';
            $vector  = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($vector)) {
                throw new EmbeddingException(new Phrase('SemantiQ: Anthropic did not return a valid embedding array.'));
            }

            return array_map('floatval', $vector);
        } catch (EmbeddingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new EmbeddingException(new Phrase('SemantiQ: Anthropic embedding failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function getDimension(): int
    {
        return self::DIMENSION;
    }
}
