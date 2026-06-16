<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class VectorBackend implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'opensearch', 'label' => __('OpenSearch (built-in)')],
            ['value' => 'chromadb',   'label' => __('ChromaDB')],
            ['value' => 'bedrock_kb', 'label' => __('AWS Bedrock Knowledge Base')],
        ];
    }
}
