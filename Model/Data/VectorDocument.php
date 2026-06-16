<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Data;

use Rivicore\SemantiQ\Api\Data\VectorDocumentInterface;

class VectorDocument implements VectorDocumentInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $entityType,
        private readonly int    $entityId,
        private readonly int    $storeId,
        private readonly array  $vector,
        private readonly array  $payload = []
    ) {}

    public function getId(): string       { return $this->id; }
    public function getEntityType(): string { return $this->entityType; }
    public function getEntityId(): int    { return $this->entityId; }
    public function getStoreId(): int     { return $this->storeId; }
    public function getVector(): array    { return $this->vector; }
    public function getPayload(): array   { return $this->payload; }
}
