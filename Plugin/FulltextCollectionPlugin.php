<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\Exception\StateException;
use Rivicore\SemantiQ\Model\Config;

class FulltextCollectionPlugin
{
    public function __construct(
        private readonly Config $config
    ) {}

    /**
     * Return an empty array instead of throwing when SemantiQ is active and the
     * aggregation bucket is missing. Layered navigation will render with no filters,
     * which is the correct behaviour for vector search results.
     */
    public function aroundGetFacetedData(
        Collection $subject,
        callable   $proceed,
        string     $field
    ): array {
        if (!$this->config->isEnabled()) {
            return $proceed($field);
        }

        try {
            return $proceed($field);
        } catch (StateException $e) {
            return [];
        }
    }
}
