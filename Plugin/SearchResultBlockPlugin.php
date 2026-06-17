<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Plugin;

use Magento\CatalogSearch\Block\Result;
use Magento\Framework\View\LayoutInterface;
use Rivicore\SemantiQ\Model\RagContextHolder;

class SearchResultBlockPlugin
{
    public function __construct(
        private readonly RagContextHolder $holder,
        private readonly LayoutInterface  $layout
    ) {}

    /**
     * Prepend the AI summary card once search.result has finished rendering.
     * By the time afterToHtml fires, result.phtml has already called
     * getProductListHtml(), which triggers the search engine, the LLM call,
     * and the event that populates RagContextHolder — so the context is ready.
     */
    public function afterToHtml(Result $subject, string $result): string
    {
        if (!$this->holder->hasContext()) {
            return $result;
        }

        $block = $this->layout->getBlock('semantiq.rag.context');
        if (!$block) {
            return $result;
        }

        return $block->toHtml() . $result;
    }
}
