<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Llm;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Api\LlmProviderInterface;
use Rivicore\SemantiQ\Model\Config;

class OllamaProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly Config           $config,
        private readonly ClientInterface  $httpClient,
        private readonly LoggerInterface  $logger
    ) {}

    public function generateContext(string $query, array $results): string
    {
        $baseUrl  = $this->config->getLlmOllamaBaseUrl();
        $model    = $this->config->getLlmOllamaModel();
        $context  = $this->buildContext($results);
        $template = $this->config->getRagPromptTemplate();
        $prompt   = str_replace(['{{query}}', '{{context}}'], [$query, $context], $template);

        try {
            $response = $this->httpClient->request('POST', "{$baseUrl}/api/chat", [
                'json' => [
                    'model'    => $model,
                    'stream'   => false,
                    'messages' => [
                        ['role' => 'user',      'content' => $prompt],
                        ['role' => 'assistant', 'content' => '<div>'],
                    ],
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return '<div>' . trim($body['message']['content'] ?? '');
        } catch (\Throwable $e) {
            $this->logger->error('SemantiQ OllamaProvider error: ' . $e->getMessage(), ['exception' => $e]);
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
            $payload     = $r->getPayload();
            $name        = $payload['name'] ?? ('Item #' . $r->getEntityId());
            $description = $payload['description'] ?? '';
            $urlKey      = $payload['url_key'] ?? '';

            $line = $description !== ''
                ? sprintf('%d. **%s**: %s', $i + 1, $name, $description)
                : sprintf('%d. **%s**', $i + 1, $name);
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
