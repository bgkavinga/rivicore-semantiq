<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\VectorStore;

use GuzzleHttp\ClientInterface;
use Magento\Framework\Phrase;
use Rivicore\SemantiQ\Api\Data\VectorDocumentInterface;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Api\VectorStoreInterface;
use Rivicore\SemantiQ\Exception\VectorStoreException;
use Rivicore\SemantiQ\Model\Config;
use Rivicore\SemantiQ\Model\Data\VectorSearchResult;

class ChromaDbAdapter implements VectorStoreInterface
{
    public function __construct(
        private readonly Config          $config,
        private readonly ClientInterface $httpClient
    ) {}

    public function createIndex(int $dimension): void
    {
        $url        = $this->config->getChromaDbUrl();
        $collection = $this->config->getChromaDbCollection();

        try {
            $this->httpClient->request('POST', "{$url}/api/v1/collections", [
                'json' => ['name' => $collection, 'metadata' => ['dimension' => $dimension]],
            ]);
        } catch (\Throwable $e) {
            // 409 Conflict means the collection already exists — that is fine
            if (str_contains($e->getMessage(), '409')) {
                return;
            }
            throw new VectorStoreException(
                new Phrase('SemantiQ: ChromaDB createIndex failed: %1', [$e->getMessage()]),
                $e
            );
        }
    }

    public function deleteIndex(): void
    {
        $url        = $this->config->getChromaDbUrl();
        $collection = $this->config->getChromaDbCollection();

        try {
            $this->httpClient->request('DELETE', "{$url}/api/v1/collections/{$collection}");
        } catch (\Throwable $e) {
            // Ignore "not found" on delete
        }
    }

    public function upsert(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $url        = $this->config->getChromaDbUrl();
        $collection = $this->config->getChromaDbCollection();

        $ids         = [];
        $embeddings  = [];
        $metadatas   = [];

        foreach ($documents as $doc) {
            $ids[]        = $doc->getId();
            $embeddings[] = $doc->getVector();
            $metadatas[]  = array_merge($doc->getPayload(), [
                'entity_type' => $doc->getEntityType(),
                'entity_id'   => $doc->getEntityId(),
                'store_id'    => $doc->getStoreId(),
            ]);
        }

        try {
            $this->httpClient->request('POST', "{$url}/api/v1/collections/{$collection}/upsert", [
                'json' => [
                    'ids'        => $ids,
                    'embeddings' => $embeddings,
                    'metadatas'  => $metadatas,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: ChromaDB upsert failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function delete(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $url        = $this->config->getChromaDbUrl();
        $collection = $this->config->getChromaDbCollection();

        try {
            $this->httpClient->request('POST', "{$url}/api/v1/collections/{$collection}/delete", [
                'json' => ['ids' => $ids],
            ]);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: ChromaDB delete failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function search(array $queryVector, int $topK, array $filters = [], string $queryText = ''): array
    {
        $url        = $this->config->getChromaDbUrl();
        $collection = $this->config->getChromaDbCollection();

        $payload = [
            'query_embeddings' => [$queryVector],
            'n_results'        => $topK,
            'include'          => ['metadatas', 'distances'],
        ];

        if (!empty($filters)) {
            $payload['where'] = $filters;
        }

        try {
            $response = $this->httpClient->request('POST', "{$url}/api/v1/collections/{$collection}/query", [
                'json' => $payload,
            ]);
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: ChromaDB search failed: %1', [$e->getMessage()]), $e);
        }

        $results = [];
        $ids       = $body['ids'][0]       ?? [];
        $metadatas = $body['metadatas'][0] ?? [];
        $distances = $body['distances'][0] ?? [];

        foreach ($ids as $i => $id) {
            $meta = $metadatas[$i] ?? [];
            // ChromaDB returns L2 distance; convert to a similarity score (1 - distance)
            $score = 1.0 - ($distances[$i] ?? 0.0);

            $results[] = new VectorSearchResult(
                id: $id,
                entityId: (int) ($meta['entity_id'] ?? 0),
                entityType: (string) ($meta['entity_type'] ?? 'product'),
                storeId: (int) ($meta['store_id'] ?? 0),
                score: $score,
                payload: $meta
            );
        }

        return $results;
    }
}
