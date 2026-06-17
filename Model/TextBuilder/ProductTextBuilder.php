<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\TextBuilder;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Rivicore\SemantiQ\Model\Config;

class ProductTextBuilder
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Config            $config
    ) {}

    /**
     * Build text strings for the given product IDs (or all products if empty).
     *
     * @param int[] $ids  Leave empty for full build.
     * @param int   $storeId
     * @return array<int, string>  Map of entity_id => concatenated text
     */
    public function build(array $ids = [], int $storeId = 0): array
    {
        $attributes = $this->config->getIndexAttributes();

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect($attributes);
        $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['in' => [
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ]]);
        // Exclude products that are opted out
        $collection->addAttributeToFilter('semantiq_exclude', [
            ['null' => true],
            ['neq'  => 1],
        ], 'left');

        if (!empty($ids)) {
            $collection->addFieldToFilter('entity_id', ['in' => $ids]);
        }

        $result = [];
        foreach ($collection as $product) {
            $parts = [];
            foreach ($attributes as $attrCode) {
                $value = $product->getData($attrCode);
                if ($value !== null && $value !== '') {
                    $parts[] = strip_tags((string) $value);
                }
            }
            $text = preg_replace('/\s+/', ' ', trim(implode(' ', $parts)));
            if ($text !== '') {
                $result[(int) $product->getId()] = mb_substr($text, 0, $this->config->getMaxTextChars());
            }
        }

        return $result;
    }

    /**
     * Return metadata payload for a product (used as vector store document payload).
     */
    public function buildPayload(array $ids = [], int $storeId = 0): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['name', 'url_key', 'sku', 'description', 'price']);
        if (!empty($ids)) {
            $collection->addFieldToFilter('entity_id', ['in' => $ids]);
        }

        $payloads = [];
        foreach ($collection as $product) {
            $payloads[(int) $product->getId()] = [
                'name'        => (string) $product->getName(),
                'sku'         => (string) $product->getSku(),
                'url_key'     => (string) $product->getUrlKey(),
                'description' => substr(strip_tags((string) $product->getDescription()), 0, 500),
            ];
        }
        return $payloads;
    }
}
