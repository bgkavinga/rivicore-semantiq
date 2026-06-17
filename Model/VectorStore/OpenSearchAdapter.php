<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\VectorStore;

use Magento\Elasticsearch\Model\Config as EsConfig;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Rivicore\SemantiQ\Api\Data\VectorDocumentInterface;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Api\VectorStoreInterface;
use Rivicore\SemantiQ\Exception\VectorStoreException;
use Rivicore\SemantiQ\Model\Config;
use Rivicore\SemantiQ\Model\Data\VectorSearchResult;
use Magento\Framework\Phrase;

class OpenSearchAdapter implements VectorStoreInterface
{
    private ?Client $client = null;
    private ?bool $knnAvailable = null;

    public function __construct(
        private readonly Config   $semantiqConfig,
        private readonly EsConfig $esConfig
    ) {}

    public function createIndex(int $dimension): void
    {
        $client = $this->getClient();
        $indexName = $this->semantiqConfig->getOpenSearchIndexName();

        if ($client->indices()->exists(['index' => $indexName])) {
            $mapping     = $client->indices()->getMapping(['index' => $indexName]);
            $existingDim = $mapping[$indexName]['mappings']['properties']['vector']['dimension']
                ?? $mapping[$indexName]['mappings']['properties']['vector']['dims']
                ?? null;

            if ($existingDim === null || (int) $existingDim === $dimension) {
                return;
            }

            // Dimension changed (e.g. embedding model swapped) — drop and recreate.
            $client->indices()->delete(['index' => $indexName]);
        }

        if ($this->isKnnPluginAvailable()) {
            $params = [
                'index' => $indexName,
                'body'  => [
                    'settings' => [
                        'index' => [
                            'knn'                      => true,
                            'knn.algo_param.ef_search' => 100,
                            'number_of_shards'         => 1,
                            'number_of_replicas'       => 0,
                        ],
                    ],
                    'mappings' => [
                        'properties' => [
                            'vector'      => [
                                'type'      => 'knn_vector',
                                'dimension' => $dimension,
                                'method'    => [
                                    'name'       => 'hnsw',
                                    'space_type' => 'cosinesimil',
                                    'engine'     => 'lucene',
                                ],
                            ],
                            'entity_type' => ['type' => 'keyword'],
                            'entity_id'   => ['type' => 'integer'],
                            'store_id'    => ['type' => 'integer'],
                            'payload'     => ['type' => 'object', 'enabled' => false],
                        ],
                    ],
                ],
            ];
        } else {
            $params = [
                'index' => $indexName,
                'body'  => [
                    'settings' => [
                        'index' => [
                            'number_of_shards'   => 1,
                            'number_of_replicas' => 0,
                        ],
                    ],
                    'mappings' => [
                        'properties' => [
                            'vector'      => [
                                'type' => 'dense_vector',
                                'dims' => $dimension,
                            ],
                            'entity_type' => ['type' => 'keyword'],
                            'entity_id'   => ['type' => 'integer'],
                            'store_id'    => ['type' => 'integer'],
                            'payload'     => ['type' => 'object', 'enabled' => false],
                        ],
                    ],
                ],
            ];
        }

        try {
            $client->indices()->create($params);
        } catch (\Throwable $e) {
            throw new VectorStoreException(
                new Phrase('SemantiQ: Failed to create OpenSearch index "%1": %2', [$indexName, $e->getMessage()]),
                $e
            );
        }
    }

    public function deleteIndex(): void
    {
        $client = $this->getClient();
        $indexName = $this->semantiqConfig->getOpenSearchIndexName();

        if (!$client->indices()->exists(['index' => $indexName])) {
            return;
        }

        $client->indices()->delete(['index' => $indexName]);
    }

    public function upsert(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $indexName = $this->semantiqConfig->getOpenSearchIndexName();
        $body = [];

        foreach ($documents as $doc) {
            $body[] = ['index' => ['_index' => $indexName, '_id' => $doc->getId()]];
            $body[] = [
                'vector'      => $doc->getVector(),
                'entity_type' => $doc->getEntityType(),
                'entity_id'   => $doc->getEntityId(),
                'store_id'    => $doc->getStoreId(),
                'payload'     => $doc->getPayload(),
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
        $body = [];

        foreach ($ids as $id) {
            $body[] = ['delete' => ['_index' => $indexName, '_id' => $id]];
        }

        $this->getClient()->bulk(['body' => $body]);
    }

    public function search(array $queryVector, int $topK, array $filters = []): array
    {
        $indexName = $this->semantiqConfig->getOpenSearchIndexName();

        if ($this->isKnnPluginAvailable()) {
            $knnQuery = [
                'vector' => $queryVector,
                'k'      => $topK,
            ];

            if (!empty($filters)) {
                $filterClauses = [];
                foreach ($filters as $field => $value) {
                    $filterClauses[] = ['term' => [$field => $value]];
                }
                $knnQuery['filter'] = ['bool' => ['must' => $filterClauses]];
            }

            $query = ['knn' => ['vector' => $knnQuery]];
        } else {
            if (!empty($filters)) {
                $filterClauses = [];
                foreach ($filters as $field => $value) {
                    $filterClauses[] = ['term' => [$field => $value]];
                }
                $innerQuery = ['bool' => ['must' => $filterClauses]];
            } else {
                $innerQuery = ['match_all' => (object) []];
            }

            $query = [
                'script_score' => [
                    'query'  => $innerQuery,
                    'script' => [
                        'source' => "cosineSimilarity(params.query_vector, 'vector') + 1.0",
                        'params' => ['query_vector' => $queryVector],
                    ],
                ],
            ];
        }

        $params = [
            'index' => $indexName,
            'body'  => [
                'size'    => $topK,
                'query'   => $query,
                '_source' => ['entity_type', 'entity_id', 'store_id', 'payload'],
            ],
        ];

        try {
            $response = $this->getClient()->search($params);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: OpenSearch search failed: %1', [$e->getMessage()]), $e);
        }

        $results = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $src = $hit['_source'];
            $results[] = new VectorSearchResult(
                id: $hit['_id'],
                entityId: (int) $src['entity_id'],
                entityType: (string) $src['entity_type'],
                storeId: (int) $src['store_id'],
                score: (float) $hit['_score'],
                payload: $src['payload'] ?? []
            );
        }

        return $results;
    }

    private function isKnnPluginAvailable(): bool
    {
        if ($this->knnAvailable === null) {
            try {
                $rows = $this->getClient()->cat()->plugins(['h' => 'component']);
                $names = array_column($rows, 'component');
                $this->knnAvailable = in_array('opensearch-knn', $names, true);
            } catch (\Throwable $e) {
                $this->knnAvailable = false;
            }
        }

        return $this->knnAvailable;
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
