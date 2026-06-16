<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\TextBuilder;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;

class CmsPageTextBuilder
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {}

    /**
     * @param int[] $ids
     * @return array<int, string>
     */
    public function build(array $ids = []): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->addFieldToFilter('semantiq_exclude', [['null' => true], ['eq' => 0]]);

        if (!empty($ids)) {
            $collection->addFieldToFilter('page_id', ['in' => $ids]);
        }

        $result = [];
        foreach ($collection as $page) {
            $parts = array_filter([
                (string) $page->getTitle(),
                (string) $page->getContentHeading(),
                strip_tags((string) $page->getContent()),
                (string) $page->getMetaKeywords(),
                (string) $page->getMetaDescription(),
            ]);

            $text = preg_replace('/\s+/', ' ', trim(implode(' ', $parts)));
            if ($text !== '') {
                $result[(int) $page->getId()] = $text;
            }
        }

        return $result;
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function buildPayload(array $ids = []): array
    {
        $collection = $this->collectionFactory->create();
        if (!empty($ids)) {
            $collection->addFieldToFilter('page_id', ['in' => $ids]);
        }

        $payloads = [];
        foreach ($collection as $page) {
            $payloads[(int) $page->getId()] = [
                'name'       => (string) $page->getTitle(),
                'identifier' => (string) $page->getIdentifier(),
            ];
        }
        return $payloads;
    }
}
