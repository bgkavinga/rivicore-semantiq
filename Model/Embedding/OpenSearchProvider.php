<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Embedding;

use Magento\Elasticsearch\Model\Config as EsConfig;
use Magento\Framework\Phrase;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Rivicore\SemantiQ\Api\EmbeddingProviderInterface;
use Rivicore\SemantiQ\Exception\EmbeddingException;
use Rivicore\SemantiQ\Model\Config;

/**
 * Embedding provider that uses OpenSearch ML Commons.
 *
 * Requires a text-embedding model deployed in the same OpenSearch cluster
 * that is already configured as Magento's search engine.
 *
 * Deploy a model via the ML Commons API, then paste its model ID into
 * Stores → Configuration → Rivicore → SemantiQ → Embedding Provider.
 *
 * Predict endpoint: POST /_plugins/_ml/models/{modelId}/predict
 * Request:  {"text_docs": ["input text"]}
 * Response: {"inference_results":[{"output":[{"name":"sentence_embedding","data":[...]}]}]}
 */
class OpenSearchProvider implements EmbeddingProviderInterface
{
    private ?Client $client = null;

    public function __construct(
        private readonly Config   $semantiqConfig,
        private readonly EsConfig $esConfig
    ) {}

    public function embed(string $text): array
    {
        $modelId = $this->semantiqConfig->getOpenSearchMlModelId();
        if ($modelId === '') {
            throw new EmbeddingException(new Phrase(
                'SemantiQ: OpenSearch ML model ID is not configured. '
                . 'Set it under Embedding Provider → OpenSearch ML Model ID.'
            ));
        }

        try {
            $response = $this->getClient()->ml()->predictModel([
                'model_id' => $modelId,
                'body'     => ['text_docs' => [$text]],
            ]);

            return $this->extractVector($response);
        } catch (EmbeddingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $cause = $e instanceof \Exception ? $e : new \RuntimeException($e->getMessage(), $e->getCode());
            throw new EmbeddingException(
                new Phrase('SemantiQ: OpenSearch ML predict failed: %1', [$e->getMessage()]),
                $cause
            );
        }
    }

    public function getDimension(): int
    {
        return $this->semantiqConfig->getOpenSearchMlDimension();
    }

    /**
     * Parse the ML Commons predict response into a float vector.
     *
     * Handles both response shapes:
     *   - inference_results[0].output[0].data  (standard text-embedding model)
     *   - inference_results[0].output[0].dataAsMap.sentence_embedding (sparse models)
     *
     * @throws EmbeddingException
     */
    private function extractVector(array $response): array
    {
        $outputs = $response['inference_results'][0]['output'] ?? [];

        if (empty($outputs)) {
            throw new EmbeddingException(new Phrase(
                'SemantiQ: Unexpected OpenSearch ML response format — no inference_results[0].output.'
            ));
        }

        // Prefer the output explicitly named "sentence_embedding"
        foreach ($outputs as $output) {
            if (($output['name'] ?? '') === 'sentence_embedding'
                && isset($output['data'])
                && is_array($output['data'])
            ) {
                return array_map('floatval', $output['data']);
            }
        }

        // Fall back to first FLOAT32 output with a 1-D shape
        foreach ($outputs as $output) {
            if (($output['data_type'] ?? '') === 'FLOAT32'
                && isset($output['data'])
                && is_array($output['data'])
                && count($output['shape'] ?? []) === 1
            ) {
                return array_map('floatval', $output['data']);
            }
        }

        throw new EmbeddingException(new Phrase(
            'SemantiQ: Could not extract a float vector from OpenSearch ML response.'
        ));
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $customHost = $this->semantiqConfig->getVectorOpenSearchHost();

            if ($customHost !== '') {
                $parsed   = parse_url($customHost);
                $scheme   = $parsed['scheme'] ?? 'http';
                $hostPart = $parsed['host'] ?? $customHost;
                $portPart = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                $username = $this->semantiqConfig->getVectorOpenSearchUsername();
                $password = $this->semantiqConfig->getVectorOpenSearchPassword();
                $auth     = ($username !== '' && $password !== '')
                    ? $username . ':' . $password . '@'
                    : '';
                $host = $scheme . '://' . $auth . $hostPart . $portPart;
            } else {
                $options  = $this->esConfig->prepareClientOptions();
                $hostname = preg_replace('/https?:\/\//i', '', (string) $options['hostname']);
                $protocol = parse_url((string) $options['hostname'], PHP_URL_SCHEME) ?: 'http';
                $port     = !empty($options['port']) ? ':' . $options['port'] : '';
                $auth     = '';
                if (!empty($options['enableAuth']) && (int) $options['enableAuth'] === 1) {
                    $auth = $options['username'] . ':' . $options['password'] . '@';
                }
                $host = $protocol . '://' . $auth . $hostname . $port;
            }

            $this->client = ClientBuilder::fromConfig(['hosts' => [$host]], true);
        }

        return $this->client;
    }
}
