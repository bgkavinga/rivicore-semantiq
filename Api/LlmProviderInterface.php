<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Api;

use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;

interface LlmProviderInterface
{
    /**
     * Given the user query and top-k vector results, produce a contextual summary.
     *
     * @param string                        $query
     * @param VectorSearchResultInterface[] $results
     * @return string
     */
    public function generateContext(string $query, array $results): string;
}
