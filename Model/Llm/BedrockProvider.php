<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Llm;

use Magento\Framework\Phrase;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Api\LlmProviderInterface;
use Rivicore\SemantiQ\Model\Config;

class BedrockProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function generateContext(string $query, array $results): string
    {
        if (!class_exists(\Aws\Sdk::class)) {
            return '';
        }

        $model = $this->config->getLlmBedrockModel();
        $sdk   = new \Aws\Sdk([
            'region'      => $this->config->getLlmBedrockRegion(),
            'version'     => 'latest',
            'credentials' => [
                'key'    => $this->config->getLlmBedrockAccessKey(),
                'secret' => $this->config->getLlmBedrockSecretKey(),
            ],
        ]);

        $client  = $sdk->createBedrockRuntime();
        $context = $this->buildContext($results);
        $template = $this->config->getRagPromptTemplate();
        $prompt  = str_replace(['{{query}}', '{{context}}'], [$query, $context], $template);

        // Anthropic-on-Bedrock message format
        $isAnthropic = str_contains($model, 'anthropic');
        $payload = $isAnthropic
            ? ['anthropic_version' => 'bedrock-2023-05-31', 'max_tokens' => 512, 'messages' => [['role' => 'user', 'content' => $prompt]]]
            : ['prompt' => "\n\nHuman: {$prompt}\n\nAssistant:", 'max_tokens_to_sample' => 512];

        try {
            $result = $client->invokeModel([
                'modelId'     => $model,
                'contentType' => 'application/json',
                'accept'      => 'application/json',
                'body'        => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            $body = json_decode((string) $result['body'], true, 512, JSON_THROW_ON_ERROR);

            if ($isAnthropic) {
                return trim($body['content'][0]['text'] ?? '');
            }
            return trim($body['completion'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param VectorSearchResultInterface[] $results */
    private function buildContext(array $results): string
    {
        $lines = [];
        $max   = $this->config->getRagMaxContextDocs();
        foreach (array_slice($results, 0, $max) as $i => $r) {
            $payload = $r->getPayload();
            $name    = $payload['name'] ?? ('Product #' . $r->getEntityId());
            $lines[] = sprintf('%d. %s', $i + 1, $name);
        }
        return implode("\n", $lines);
    }
}
