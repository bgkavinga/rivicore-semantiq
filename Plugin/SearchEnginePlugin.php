<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\ResponseInterface;
use Magento\Framework\Search\SearchEngineInterface;
use Psr\Log\LoggerInterface;
use Rivicore\SemantiQ\Model\Config;
use Rivicore\SemantiQ\Model\VectorSearch;

class SearchEnginePlugin
{
    private const INTERCEPTED_REQUESTS = [
        'quick_search_container',
        'catalogsearch_fulltext',
    ];

    public function __construct(
        private readonly Config          $config,
        private readonly VectorSearch    $vectorSearch,
        private readonly LoggerInterface $logger
    ) {}

    public function aroundSearch(
        SearchEngineInterface $subject,
        callable              $proceed,
        RequestInterface      $request
    ): ResponseInterface {
        if (!$this->config->isEnabled()
            || !in_array($request->getName(), self::INTERCEPTED_REQUESTS, true)
        ) {
            return $proceed($request);
        }

        try {
            return $this->vectorSearch->execute($request);
        } catch (\Throwable $e) {
            $this->logger->error('SemantiQ: vector search failed, falling back to ES: ' . $e->getMessage());
            return $proceed($request);
        }
    }
}
