<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Api\Data;

interface VectorSearchResultInterface
{
    public function getId(): string;

    public function getEntityId(): int;

    public function getEntityType(): string;

    public function getStoreId(): int;

    public function getScore(): float;

    /** @return array<string, mixed> */
    public function getPayload(): array;
}
