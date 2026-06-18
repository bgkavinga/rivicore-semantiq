<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Llm;

use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Api\LlmProviderInterface;
use Rivicore\SemantiQ\Model\Config;

class BedrockProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {}

    public function generateContext(string $query, array $results): string
    {
        if (!class_exists(\Aws\Sdk::class)) {
            return '';
        }

        $model  = $this->config->getLlmBedrockModel();
        $region = $this->config->getLlmBedrockRegion();
        $apiKey = $this->config->getLlmBedrockApiKey();

        $client = new \Aws\BedrockRuntime\BedrockRuntimeClient([
            'region'                 => $region,
            'version'                => 'latest',
            'token'                  => \Aws\Token\BedrockTokenProvider::fromTokenValue($apiKey),
            'auth_scheme_preference' => [\Aws\Token\BedrockTokenProvider::BEARER_AUTH],
        ]);
        $context = $this->buildContext($results);
        $template = $this->config->getRagPromptTemplate();
        $prompt  = str_replace(['{{query}}', '{{context}}'], [$query, $context], $template);

         $payload = [
                'messages'       => [['role' => 'user', 'content' => [['text' => $prompt]]],['role' => 'assistant', 'content' => [['text' => '<div>']]]],
                'inferenceConfig' => ['maxTokens' => 512],
        ];

        try {
            $result = $client->invokeModel([
                'modelId'     => $model,
                'contentType' => 'application/json',
                'accept'      => 'application/json',
                'body'        => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            $body = json_decode((string) $result['body'], true, 512, JSON_THROW_ON_ERROR);

            return '<div>' . trim($body['output']['message']['content'][0]['text'] ?? '');
        } catch (\Throwable $e) {
            $this->logger->error('BedrockProvider::generateContext failed: ' . $e->getMessage(), ['exception' => $e]);
            return '';
        }
    }

    /** @param VectorSearchResultInterface[] $results */
    private function buildContext(array $results): string
    {
        $max      = $this->config->getRagMaxContextDocs();
        $slice    = array_slice($results, 0, $max);
        $storeUrl = $this->config->getStoreBaseUrl(!empty($slice) ? $slice[0]->getStoreId() : 0);

        $products = [];
        foreach ($slice as $r) {
            $payload     = $r->getPayload();
            $urlKey      = $payload['url_key'] ?? '';
            $description = $payload['description'] ?? ($payload['short_description'] ?? '');

            $product = [
                'entity_id'   => $r->getEntityId(),
                'name'        => $payload['name'] ?? ('Product #' . $r->getEntityId()),
                'url'         => $urlKey ? $storeUrl . $urlKey . '.html' : null,
                'description' => $description ? strip_tags($description) : null,
                'sku'         => $payload['sku'] ?? null,
                'price'       => $payload['price'] ?? null,
            ];

            foreach (['url', 'description', 'sku', 'price'] as $optional) {
                if ($product[$optional] === null) {
                    unset($product[$optional]);
                }
            }

            $products[] = $product;
        }

        return json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
