<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Data;

use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;

class VectorSearchResult implements VectorSearchResultInterface
{
    public function __construct(
        private readonly string $id,
        private readonly int    $entityId,
        private readonly string $entityType,
        private readonly int    $storeId,
        private readonly float  $score,
        private readonly array  $payload = []
    ) {}

    public function getId(): string         { return $this->id; }
    public function getEntityId(): int      { return $this->entityId; }
    public function getEntityType(): string { return $this->entityType; }
    public function getStoreId(): int       { return $this->storeId; }
    public function getScore(): float       { return $this->score; }
    public function getPayload(): array     { return $this->payload; }
}
