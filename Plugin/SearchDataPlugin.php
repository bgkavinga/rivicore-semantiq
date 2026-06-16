<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Plugin;

use Magento\AdvancedSearch\Block\SearchData;
use Rivicore\SemantiQ\Model\Config;

class SearchDataPlugin
{
    public function __construct(
        private readonly Config $config
    ) {}

    /**
     * Suppress "Did you mean" / search suggestions when SemantiQ is active.
     * Suggestions are generated from the keyword index and are meaningless
     * (and misleading) when results already come from vector search.
     */
    public function afterGetItems(SearchData $subject, array $result): array
    {
        if ($this->config->isEnabled()) {
            return [];
        }
        return $result;
    }
}
