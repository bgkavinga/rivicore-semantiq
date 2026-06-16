<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\VectorStore;

use Rivicore\SemantiQ\Api\VectorStoreInterface;
use Rivicore\SemantiQ\Model\Config;

class Pool
{
    /** @param array<string, VectorStoreInterface> $adapters */
    public function __construct(
        private readonly Config $config,
        private readonly array  $adapters = []
    ) {}

    public function getStore(): VectorStoreInterface
    {
        $key = $this->config->getVectorStoreBackend();
        if (!isset($this->adapters[$key])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown SemantiQ vector store backend: "%s". Available: %s', $key, implode(', ', array_keys($this->adapters)))
            );
        }
        return $this->adapters[$key];
    }
}
