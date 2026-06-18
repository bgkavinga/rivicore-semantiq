<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Llm;

use GuzzleHttp\ClientInterface;
use Magento\Framework\Phrase;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Api\LlmProviderInterface;
use Rivicore\SemantiQ\Model\Config;

class OpenAiProvider implements LlmProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly Config          $config,
        private readonly ClientInterface $httpClient
    ) {}

    public function generateContext(string $query, array $results): string
    {
        $apiKey = $this->config->getLlmOpenAiApiKey();
        $model  = $this->config->getLlmOpenAiModel();

        if (!$apiKey) {
            return '';
        }

        $context  = $this->buildContext($results);
        $template = $this->config->getRagPromptTemplate();
        $prompt   = str_replace(['{{query}}', '{{context}}'], [$query, $context], $template);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'    => $model,
                    'messages' => [
                        ['role' => 'user',      'content' => $prompt],
                        ['role' => 'assistant', 'content' => '<div>'],
                    ],
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return '<div>' . trim($body['choices'][0]['message']['content'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param VectorSearchResultInterface[] $results */
    private function buildContext(array $results): string
    {
        $lines    = [];
        $max      = $this->config->getRagMaxContextDocs();
        $slice    = array_slice($results, 0, $max);
        $storeUrl = $this->config->getStoreBaseUrl(!empty($slice) ? $slice[0]->getStoreId() : 0);

        foreach ($slice as $i => $r) {
            $payload = $r->getPayload();
            $name    = $payload['name'] ?? ('Product #' . $r->getEntityId());
            $desc    = $payload['description'] ?? '';
            $urlKey  = $payload['url_key'] ?? '';

            $line = sprintf('%d. **%s**%s', $i + 1, $name, $desc ? ': ' . substr(strip_tags($desc), 0, 200) : '');
            if ($urlKey) {
                $line .= "\n   URL: " . $storeUrl . $urlKey . '.html';
            }
            $lines[] = $line;
        }

        if (!empty($lines)) {
            array_unshift(
                $lines,
                "Instructions: Respond with an HTML fragment only — no markdown, no ```html fences, no <html>/<body> tags. Use <ul><li> for lists, <strong> for product names, and <a href=\"URL\"> for product links so the user can open them directly. Each product below has a precomposed URL ready to use.\n"
            );
        }

        return implode("\n", $lines);
    }
}
