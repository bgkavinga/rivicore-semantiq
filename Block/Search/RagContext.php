<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Block\Search;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Rivicore\SemantiQ\Model\RagContextHolder;

class RagContext extends Template
{
    public function __construct(
        Context $context,
        private readonly RagContextHolder $holder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getRagContext(): string
    {
        return $this->holder->getContext();
    }
}
