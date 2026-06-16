<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Embedding;

use Magento\Framework\Phrase;
use Rivicore\SemantiQ\Api\EmbeddingProviderInterface;
use Rivicore\SemantiQ\Exception\EmbeddingException;
use Rivicore\SemantiQ\Model\Config;

class BedrockProvider implements EmbeddingProviderInterface
{
    private const DIMENSIONS = [
        'amazon.titan-embed-text-v2:0'  => 1024,
        'amazon.titan-embed-text-v1'    => 1536,
        'cohere.embed-english-v3'       => 1024,
        'cohere.embed-multilingual-v3'  => 1024,
    ];

    public function __construct(
        private readonly Config $config
    ) {}

    public function embed(string $text): array
    {
        if (!class_exists(\Aws\Sdk::class)) {
            throw new EmbeddingException(new Phrase(
                'SemantiQ: AWS SDK for PHP is not installed. Run: composer require aws/aws-sdk-php'
            ));
        }

        $model = $this->config->getBedrockEmbedModel();

        $sdk    = new \Aws\Sdk([
            'region'      => $this->config->getBedrockEmbedRegion(),
            'version'     => 'latest',
            'credentials' => [
                'key'    => $this->config->getBedrockEmbedAccessKey(),
                'secret' => $this->config->getBedrockEmbedSecretKey(),
            ],
        ]);

        $client = $sdk->createBedrockRuntime();

        try {
            $payload = str_contains($model, 'cohere')
                ? json_encode(['texts' => [$text], 'input_type' => 'search_document'], JSON_THROW_ON_ERROR)
                : json_encode(['inputText' => $text], JSON_THROW_ON_ERROR);

            $result = $client->invokeModel([
                'modelId'     => $model,
                'contentType' => 'application/json',
                'accept'      => 'application/json',
                'body'        => $payload,
            ]);

            $body = json_decode((string) $result['body'], true, 512, JSON_THROW_ON_ERROR);

            // Titan response: embedding[], Cohere response: embeddings[][]
            return $body['embedding'] ?? ($body['embeddings'][0] ?? []);
        } catch (EmbeddingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new EmbeddingException(new Phrase('SemantiQ: Bedrock embedding failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function getDimension(): int
    {
        return self::DIMENSIONS[$this->config->getBedrockEmbedModel()] ?? 1024;
    }
}
