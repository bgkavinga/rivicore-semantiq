<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Api;

use Rivicore\SemantiQ\Api\Data\VectorDocumentInterface;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Exception\VectorStoreException;

interface VectorStoreInterface
{
    /**
     * Create (or ensure) the vector index/collection in the backend.
     *
     * @throws VectorStoreException
     */
    public function createIndex(int $dimension): void;

    /**
     * Drop the vector index/collection.
     */
    public function deleteIndex(): void;

    /**
     * Upsert one or more documents (with their pre-computed vectors).
     *
     * @param VectorDocumentInterface[] $documents
     * @throws VectorStoreException
     */
    public function upsert(array $documents): void;

    /**
     * Delete documents by their IDs.
     *
     * @param string[] $ids
     * @throws VectorStoreException
     */
    public function delete(array $ids): void;

    /**
     * k-NN similarity search.
     *
     * @param float[]              $queryVector
     * @param int                  $topK
     * @param array<string, mixed> $filters   e.g. ['entity_type' => 'product', 'store_id' => 1]
     * @param string               $queryText Raw query string; used by adapters that support hybrid search.
     * @return VectorSearchResultInterface[]
     * @throws VectorStoreException
     */
    public function search(array $queryVector, int $topK, array $filters = [], string $queryText = ''): array;
}
