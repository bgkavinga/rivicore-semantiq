<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Embedding;

use Rivicore\SemantiQ\Api\EmbeddingProviderInterface;
use Rivicore\SemantiQ\Model\Config;

class Pool
{
    /** @param array<string, EmbeddingProviderInterface> $providers */
    public function __construct(
        private readonly Config $config,
        private readonly array  $providers = []
    ) {}

    public function getProvider(): EmbeddingProviderInterface
    {
        $key = $this->config->getEmbeddingProvider();
        if (!isset($this->providers[$key])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown SemantiQ embedding provider: "%s". Available: %s', $key, implode(', ', array_keys($this->providers)))
            );
        }
        return $this->providers[$key];
    }
}
