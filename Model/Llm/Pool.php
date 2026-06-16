<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Llm;

use Rivicore\SemantiQ\Api\LlmProviderInterface;
use Rivicore\SemantiQ\Model\Config;

class Pool
{
    /** @param array<string, LlmProviderInterface> $providers */
    public function __construct(
        private readonly Config $config,
        private readonly array  $providers = []
    ) {}

    public function getProvider(): LlmProviderInterface
    {
        $key = $this->config->getLlmProvider();
        if (!isset($this->providers[$key])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown SemantiQ LLM provider: "%s". Available: %s', $key, implode(', ', array_keys($this->providers)))
            );
        }
        return $this->providers[$key];
    }
}
