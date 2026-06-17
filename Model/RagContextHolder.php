<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model;

class RagContextHolder
{
    private string $context = '';

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function hasContext(): bool
    {
        return $this->context !== '';
    }
}
