<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Rivicore\SemantiQ\Model\RagContextHolder;

class RagContextObserver implements ObserverInterface
{
    public function __construct(
        private readonly RagContextHolder $holder
    ) {}

    public function execute(Observer $observer): void
    {
        $context = (string) $observer->getEvent()->getContext();
        if ($context !== '') {
            $this->holder->setContext($context);
        }
    }
}
