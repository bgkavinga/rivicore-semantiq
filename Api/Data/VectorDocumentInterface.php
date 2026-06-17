<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Api\Data;

interface VectorDocumentInterface
{
    public function getId(): string;

    public function getEntityType(): string;

    public function getEntityId(): int;

    public function getStoreId(): int;

    /** @return float[] */
    public function getVector(): array;

    /** @return array<string, mixed> */
    public function getPayload(): array;

    public function getTextContent(): string;
}
