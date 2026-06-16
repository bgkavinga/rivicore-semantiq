<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributes implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {}

    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('frontend_input', ['in' => ['text', 'textarea', 'select', 'multiselect']]);
        $collection->setOrder('frontend_label', 'ASC');

        $options = [];
        foreach ($collection as $attribute) {
            $label = $attribute->getFrontendLabel();
            if (!$label) {
                continue;
            }
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => sprintf('%s (%s)', $label, $attribute->getAttributeCode()),
            ];
        }

        return $options;
    }
}
