<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Llm;

use GuzzleHttp\ClientInterface;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Api\LlmProviderInterface;
use Rivicore\SemantiQ\Model\Config;

class AnthropicProvider implements LlmProviderInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly Config          $config,
        private readonly ClientInterface $httpClient
    ) {}

    public function generateContext(string $query, array $results): string
    {
        $apiKey = $this->config->getLlmAnthropicApiKey();
        $model  = $this->config->getLlmAnthropicModel();

        if (!$apiKey) {
            return '';
        }

        $context  = $this->buildContext($results);
        $template = $this->config->getRagPromptTemplate();
        $prompt   = str_replace(['{{query}}', '{{context}}'], [$query, $context], $template);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => $model,
                    'max_tokens' => 512,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return trim($body['content'][0]['text'] ?? '');
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
            $desc    = $payload['description'] ?? '';
            $lines[] = sprintf('%d. **%s**%s', $i + 1, $name, $desc ? ': ' . substr(strip_tags($desc), 0, 200) : '');
        }
        return implode("\n", $lines);
    }
}
