<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LlmProvider implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'openai',    'label' => __('OpenAI')],
            ['value' => 'bedrock',   'label' => __('AWS Bedrock')],
            ['value' => 'ollama',    'label' => __('Ollama (local)')],
            ['value' => 'anthropic', 'label' => __('Anthropic Claude')],
        ];
    }
}
