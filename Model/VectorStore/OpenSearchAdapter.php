<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\VectorStore;

use Magento\Elasticsearch\Model\Config as EsConfig;
use Magento\Framework\Phrase;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Rivicore\SemantiQ\Api\Data\VectorDocumentInterface;
use Rivicore\SemantiQ\Api\VectorStoreInterface;
use Rivicore\SemantiQ\Exception\VectorStoreException;
use Rivicore\SemantiQ\Model\Config;
use Rivicore\SemantiQ\Model\Data\VectorSearchResult;

class OpenSearchAdapter implements VectorStoreInterface
{
    /**
     * Multiplier applied to the hybrid_bm25_weight config value to compute the
     * maximum BM25 score boost added to the raw kNN cosine score.
     *
     * At the default hybrid_bm25_weight of 0.3 this yields a maximum boost of
     * 0.3 × 0.07 ≈ 0.021 — enough to re-rank keyword matches without
     * overriding the cosine-similarity threshold.
     */
    private const BM25_RERANK_FACTOR = 0.07;

    private ?Client $client       = null;
    private ?bool   $knnAvailable = null;
    private ?string $baseUrl      = null;

    public function __construct(
        private readonly Config   $semantiqConfig,
        private readonly EsConfig $esConfig
    ) {}

    // -------------------------------------------------------------------------
    // Index lifecycle
    // -------------------------------------------------------------------------

    public function createIndex(int $dimension): void
    {
        $client    = $this->getClient();
        $indexName = $this->semantiqConfig->getOpenSearchIndexName();

        if ($client->indices()->exists(['index' => $indexName])) {
            $mapping     = $client->indices()->getMapping(['index' => $indexName]);
            $props       = $mapping[$indexName]['mappings']['properties'] ?? [];
            $existingDim = $props['vector']['dimension'] ?? $props['vector']['dims'] ?? null;

            if ($existingDim !== null && (int) $existingDim !== $dimension) {
                // Dimension changed — drop and recreate.
                $client->indices()->delete(['index' => $indexName]);
            } else {
                // Index is valid; add text_content field if missing.
                if (!isset($props['text_content'])) {
                    $client->indices()->putMapping([
                        'index' => $indexName,
                        'body'  => ['properties' => ['text_content' => $this->textContentMapping()]],
                    ]);
                }
                return;
            }
        }

        $vectorMapping = $this->isKnnPluginAvailable()
            ? ['type' => 'knn_vector', 'dimension' => $dimension, 'method' => ['name' => 'hnsw', 'space_type' => 'cosinesimil', 'engine' => 'lucene']]
            : ['type' => 'dense_vector', 'dims' => $dimension];

        $settings = $this->isKnnPluginAvailable()
            ? ['index' => ['knn' => true, 'knn.algo_param.ef_search' => 100, 'number_of_shards' => 1, 'number_of_replicas' => 0]]
            : ['index' => ['number_of_shards' => 1, 'number_of_replicas' => 0]];

        try {
            $client->indices()->create([
                'index' => $indexName,
                'body'  => [
                    'settings' => $settings,
                    'mappings' => [
                        'properties' => [
                            'vector'       => $vectorMapping,
                            'text_content' => $this->textContentMapping(),
                            'entity_type'  => ['type' => 'keyword'],
                            'entity_id'    => ['type' => 'integer'],
                            'store_id'     => ['type' => 'integer'],
                            'payload'      => ['type' => 'object', 'enabled' => false],
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            throw new VectorStoreException(
                new Phrase('SemantiQ: Failed to create OpenSearch index "%1": %2', [$indexName, $e->getMessage()]),
                $e
            );
        }
    }

    public function deleteIndex(): void
    {
        $client    = $this->getClient();
        $indexName = $this->semantiqConfig->getOpenSearchIndexName();

        if ($client->indices()->exists(['index' => $indexName])) {
            $client->indices()->delete(['index' => $indexName]);
        }
    }

    // -------------------------------------------------------------------------
    // Document operations
    // -------------------------------------------------------------------------

    public function upsert(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $indexName = $this->semantiqConfig->getOpenSearchIndexName();
        $body      = [];

        foreach ($documents as $doc) {
            $body[] = ['index' => ['_index' => $indexName, '_id' => $doc->getId()]];
            $body[] = [
                'vector'       => $doc->getVector(),
                'text_content' => $doc->getTextContent(),
                'entity_type'  => $doc->getEntityType(),
                'entity_id'    => $doc->getEntityId(),
                'store_id'     => $doc->getStoreId(),
                'payload'      => $doc->getPayload(),
            ];
        }

        try {
            $response = $this->getClient()->bulk(['body' => $body]);
            if (!empty($response['errors'])) {
                $errors = [];
                foreach ($response['items'] ?? [] as $item) {
                    $op = $item['index'] ?? $item['create'] ?? [];
                    if (!empty($op['error'])) {
                        $errors[] = ($op['_id'] ?? '?') . ': ' . json_encode($op['error']);
                    }
                }
                throw new VectorStoreException(
                    new Phrase('SemantiQ: Bulk upsert errors: %1', [implode(' | ', array_slice($errors, 0, 3))])
                );
            }
        } catch (VectorStoreException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: OpenSearch upsert failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function delete(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $indexName = $this->semantiqConfig->getOpenSearchIndexName();
        $body      = [];

        foreach ($ids as $id) {
            $body[] = ['delete' => ['_index' => $indexName, '_id' => $id]];
        }

        $this->getClient()->bulk(['body' => $body]);
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    public function search(array $queryVector, int $topK, array $filters = [], string $queryText = ''): array
    {
        $indexName = $this->semantiqConfig->getOpenSearchIndexName();
        $useHybrid = $this->semantiqConfig->isHybridEnabled()
            && $this->isKnnPluginAvailable()
            && $queryText !== '';

        if ($useHybrid) {
            return $this->hybridSearch($indexName, $queryVector, $topK, $filters, $queryText);
        }

        if ($this->isKnnPluginAvailable()) {
            $params = $this->buildKnnSearchParams($indexName, $queryVector, $topK, $filters);
        } else {
            $params = $this->buildScriptScoreSearchParams($indexName, $queryVector, $topK, $filters);
        }

        try {
            $response = $this->getClient()->search($params);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: OpenSearch search failed: %1', [$e->getMessage()]), $e);
        }

        $results = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $src       = $hit['_source'];
            $results[] = new VectorSearchResult(
                id:         $hit['_id'],
                entityId:   (int)    $src['entity_id'],
                entityType: (string) $src['entity_type'],
                storeId:    (int)    $src['store_id'],
                score:      (float)  $hit['_score'],
                payload:    $src['payload'] ?? []
            );
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private — hybrid search (PHP-side scoring)
    // -------------------------------------------------------------------------

    /**
     * Two-query hybrid search:
     *   1. kNN query  → top-K candidates ranked by raw cosine similarity
     *   2. BM25 query → keyword-matching candidates (used only for re-ranking)
     *   3. PHP merge  → final_score = knn_cosine + normalised_bm25 × max_boost
     *
     * The raw cosine score is the primary relevance signal; BM25 adds a small
     * boost (≤ max_boost) to re-rank keyword matches within the candidate set.
     * The admin-configurable min_score threshold is applied on the combined score
     * in VectorSearch::execute().
     */
    private function hybridSearch(
        string $indexName,
        array  $queryVector,
        int    $topK,
        array  $filters,
        string $queryText
    ): array {
        // 1. kNN search — primary relevance, raw cosine scores preserved
        $knnParams   = $this->buildKnnSearchParams($indexName, $queryVector, $topK, $filters);
        try {
            $knnResponse = $this->getClient()->search($knnParams);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: kNN search failed: %1', [$e->getMessage()]), $e);
        }

        // 2. BM25 keyword search — find which entity_ids have keyword overlap
        $filterClauses = [];
        foreach ($filters as $field => $value) {
            $filterClauses[] = ['term' => [$field => $value]];
        }
        $bm25Query = empty($filterClauses)
            ? ['match' => ['text_content' => $queryText]]
            : ['bool' => ['must' => ['match' => ['text_content' => $queryText]], 'filter' => $filterClauses]];

        try {
            $bm25Response = $this->getClient()->search([
                'index' => $indexName,
                'body'  => [
                    'size'    => $topK,
                    '_source' => ['entity_id'],
                    'query'   => $bm25Query,
                ],
            ]);
        } catch (\Throwable) {
            $bm25Response = [];
        }

        // 3. Build normalised BM25 map: entity_id → [0, 1]
        //    Normalise against the top BM25 score so the best keyword match = 1.0.
        $bm25Hits = $bm25Response['hits']['hits'] ?? [];
        $maxBm25  = !empty($bm25Hits) ? (float) $bm25Hits[0]['_score'] : 0.0;
        $bm25Map  = [];
        foreach ($bm25Hits as $hit) {
            $eid             = (int) ($hit['_source']['entity_id'] ?? 0);
            $bm25Map[$eid]   = $maxBm25 > 0.0 ? (float) $hit['_score'] / $maxBm25 : 0.0;
        }

        // 4. Merge: final_score = knn_cosine + bm25_boost
        $maxBoost = $this->semantiqConfig->getHybridBm25Weight() * self::BM25_RERANK_FACTOR;
        $results  = [];
        foreach ($knnResponse['hits']['hits'] ?? [] as $hit) {
            $src      = $hit['_source'];
            $eid      = (int) $src['entity_id'];
            $knnScore = (float) $hit['_score'];
            $boost    = ($bm25Map[$eid] ?? 0.0) * $maxBoost;

            $results[] = new VectorSearchResult(
                id:         $hit['_id'],
                entityId:   $eid,
                entityType: (string) $src['entity_type'],
                storeId:    (int)    $src['store_id'],
                score:      $knnScore + $boost,
                payload:    $src['payload'] ?? []
            );
        }

        usort($results, fn($a, $b) => $b->getScore() <=> $a->getScore());

        return array_slice($results, 0, $topK);
    }

    // -------------------------------------------------------------------------
    // Private — pure kNN and script-score query builders
    // -------------------------------------------------------------------------

    private function buildKnnSearchParams(string $indexName, array $queryVector, int $topK, array $filters): array
    {
        $knnQuery = ['vector' => $queryVector, 'k' => $topK];

        if (!empty($filters)) {
            $filterClauses = [];
            foreach ($filters as $field => $value) {
                $filterClauses[] = ['term' => [$field => $value]];
            }
            $knnQuery['filter'] = ['bool' => ['must' => $filterClauses]];
        }

        return [
            'index' => $indexName,
            'body'  => [
                'size'    => $topK,
                '_source' => ['entity_type', 'entity_id', 'store_id', 'payload'],
                'query'   => ['knn' => ['vector' => $knnQuery]],
            ],
        ];
    }

    private function buildScriptScoreSearchParams(string $indexName, array $queryVector, int $topK, array $filters): array
    {
        if (!empty($filters)) {
            $filterClauses = [];
            foreach ($filters as $field => $value) {
                $filterClauses[] = ['term' => [$field => $value]];
            }
            $innerQuery = ['bool' => ['must' => $filterClauses]];
        } else {
            $innerQuery = ['match_all' => (object) []];
        }

        return [
            'index' => $indexName,
            'body'  => [
                'size'    => $topK,
                '_source' => ['entity_type', 'entity_id', 'store_id', 'payload'],
                'query'   => [
                    'script_score' => [
                        'query'  => $innerQuery,
                        'script' => [
                            'source' => "cosineSimilarity(params.query_vector, 'vector') + 1.0",
                            'params' => ['query_vector' => $queryVector],
                        ],
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Private — index and connection helpers
    // -------------------------------------------------------------------------

    private function textContentMapping(): array
    {
        return ['type' => 'text', 'analyzer' => 'english'];
    }

    private function isKnnPluginAvailable(): bool
    {
        if ($this->knnAvailable === null) {
            try {
                $rows               = $this->getClient()->cat()->plugins(['h' => 'component']);
                $names              = array_column($rows, 'component');
                $this->knnAvailable = in_array('opensearch-knn', $names, true);
            } catch (\Throwable) {
                $this->knnAvailable = false;
            }
        }

        return $this->knnAvailable;
    }

    private function getBaseUrl(): string
    {
        if ($this->baseUrl === null) {
            $customHost = $this->semantiqConfig->getVectorOpenSearchHost();

            if ($customHost !== '') {
                $parsed   = parse_url($customHost);
                $scheme   = $parsed['scheme'] ?? 'http';
                $host     = $parsed['host'] ?? $customHost;
                $port     = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                $username = $this->semantiqConfig->getVectorOpenSearchUsername();
                $password = $this->semantiqConfig->getVectorOpenSearchPassword();
                $auth     = ($username !== '' && $password !== '') ? $username . ':' . $password . '@' : '';
                $this->baseUrl = $scheme . '://' . $auth . $host . $port;
            } else {
                $options  = $this->esConfig->prepareClientOptions();
                $hostname = preg_replace('/https?:\/\//i', '', (string) $options['hostname']);
                $protocol = parse_url((string) $options['hostname'], PHP_URL_SCHEME) ?: 'http';
                $port     = !empty($options['port']) ? ':' . $options['port'] : '';
                $auth     = '';
                if (!empty($options['enableAuth']) && (int) $options['enableAuth'] === 1) {
                    $auth = $options['username'] . ':' . $options['password'] . '@';
                }
                $this->baseUrl = $protocol . '://' . $auth . $hostname . $port;
            }
        }

        return $this->baseUrl;
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = ClientBuilder::fromConfig(['hosts' => [$this->getBaseUrl()]], true);
        }

        return $this->client;
    }
}
