<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Embedding;

use GuzzleHttp\ClientInterface;
use Magento\Framework\Phrase;
use Rivicore\SemantiQ\Api\EmbeddingProviderInterface;
use Rivicore\SemantiQ\Exception\EmbeddingException;
use Rivicore\SemantiQ\Model\Config;

class OllamaProvider implements EmbeddingProviderInterface
{
    private const DIMENSIONS = [
        'nomic-embed-text' => 768,
        'mxbai-embed-large' => 1024,
        'all-minilm'        => 384,
        'bge-large'         => 1024,
        'bge-m3'            => 1024,
    ];

    private ?int $detectedDimension = null;

    public function __construct(
        private readonly Config          $config,
        private readonly ClientInterface $httpClient
    ) {}

    public function embed(string $text): array
    {
        $baseUrl = $this->config->getOllamaBaseUrl();
        $model   = $this->config->getOllamaModel();

        try {
            $response = $this->httpClient->request('POST', "{$baseUrl}/api/embeddings", [
                'json' => [
                    'model'  => $model,
                    'prompt' => $text,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return $body['embedding'] ?? [];
        } catch (EmbeddingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new EmbeddingException(new Phrase('SemantiQ: Ollama embedding failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function getDimension(): int
    {
        $model = $this->config->getOllamaModel();

        if (isset(self::DIMENSIONS[$model])) {
            return self::DIMENSIONS[$model];
        }

        if ($this->detectedDimension === null) {
            $vector = $this->embed('dimension probe');
            if (empty($vector)) {
                throw new EmbeddingException(
                    new Phrase('SemantiQ: Cannot auto-detect embedding dimension for Ollama model "%1": empty vector returned.', [$model])
                );
            }
            $this->detectedDimension = count($vector);
        }

        return $this->detectedDimension;
    }
}
