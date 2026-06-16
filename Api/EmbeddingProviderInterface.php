<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Api;

use Rivicore\SemantiQ\Exception\EmbeddingException;

interface EmbeddingProviderInterface
{
    /**
     * Produce a float vector for the given text.
     *
     * @param string $text
     * @return float[]
     * @throws EmbeddingException
     */
    public function embed(string $text): array;

    /**
     * Vector dimension produced by this provider/model.
     */
    public function getDimension(): int;
}
