<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Embedding;

use GuzzleHttp\ClientInterface;
use Magento\Framework\Phrase;
use Rivicore\SemantiQ\Api\EmbeddingProviderInterface;
use Rivicore\SemantiQ\Exception\EmbeddingException;
use Rivicore\SemantiQ\Model\Config;

class OpenAiProvider implements EmbeddingProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/embeddings';

    private const DIMENSIONS = [
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
        'text-embedding-ada-002' => 1536,
    ];

    private ?int $detectedDimension = null;

    public function __construct(
        private readonly Config          $config,
        private readonly ClientInterface $httpClient
    ) {}

    public function embed(string $text): array
    {
        $apiKey = $this->config->getOpenAiApiKey();
        $model  = $this->config->getOpenAiModel();

        if (!$apiKey) {
            throw new EmbeddingException(new Phrase('SemantiQ: OpenAI API key is not configured.'));
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'input' => $text,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return $body['data'][0]['embedding'] ?? [];
        } catch (EmbeddingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new EmbeddingException(new Phrase('SemantiQ: OpenAI embedding failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function getDimension(): int
    {
        $model = $this->config->getOpenAiModel();

        if (isset(self::DIMENSIONS[$model])) {
            return self::DIMENSIONS[$model];
        }

        if ($this->detectedDimension === null) {
            $vector = $this->embed('dimension probe');
            if (empty($vector)) {
                throw new EmbeddingException(
                    new Phrase('SemantiQ: Cannot auto-detect embedding dimension for OpenAI model "%1": empty vector returned.', [$model])
                );
            }
            $this->detectedDimension = count($vector);
        }

        return $this->detectedDimension;
    }
}
