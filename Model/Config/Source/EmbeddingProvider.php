<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EmbeddingProvider implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'opensearch', 'label' => __('OpenSearch ML Commons (built-in)')],
            ['value' => 'openai',     'label' => __('OpenAI')],
            ['value' => 'bedrock',    'label' => __('AWS Bedrock (Titan)')],
            ['value' => 'ollama',     'label' => __('Ollama (local)')],
            ['value' => 'anthropic',  'label' => __('Anthropic Claude (experimental)')],
        ];
    }
}
