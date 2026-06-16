<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class VectorDocument
{
    private const TABLE = 'rivicore_semantiq_index';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {}

    public function upsert(string $entityType, int $entityId, int $storeId, string $vectorId): void
    {
        $connection = $this->resource->getConnection();
        $connection->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'store_id'    => $storeId,
                'vector_id'   => $vectorId,
            ],
            ['vector_id', 'indexed_at']
        );
    }

    public function deleteByEntityType(string $entityType, int $storeId = 0): void
    {
        $connection = $this->resource->getConnection();
        $connection->delete(
            $this->resource->getTableName(self::TABLE),
            ['entity_type = ?' => $entityType, 'store_id = ?' => $storeId]
        );
    }

    public function deleteByIds(string $entityType, array $entityIds, int $storeId = 0): array
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        // Fetch vector IDs first so we can delete them from the vector backend
        $select = $connection->select()
            ->from($table, ['vector_id'])
            ->where('entity_type = ?', $entityType)
            ->where('entity_id IN (?)', $entityIds)
            ->where('store_id = ?', $storeId);

        $vectorIds = $connection->fetchCol($select);

        $connection->delete($table, [
            'entity_type = ?' => $entityType,
            'entity_id IN (?)' => $entityIds,
            'store_id = ?' => $storeId,
        ]);

        return $vectorIds;
    }
}
