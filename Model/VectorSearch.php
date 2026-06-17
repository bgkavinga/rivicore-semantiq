<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model;

use Magento\Framework\Api\AttributeValue;
use Magento\Framework\Api\Search\Document;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\ResponseInterface;
use Psr\Log\LoggerInterface;
use Rivicore\SemantiQ\Model\Embedding\Pool as EmbeddingPool;
use Rivicore\SemantiQ\Model\Llm\Pool as LlmPool;
use Rivicore\SemantiQ\Model\VectorStore\Pool as VectorStorePool;

class VectorSearch
{
    public function __construct(
        private readonly Config        $config,
        private readonly EmbeddingPool $embeddingPool,
        private readonly VectorStorePool $vectorStorePool,
        private readonly LlmPool       $llmPool,
        private readonly EventManager  $eventManager,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(RequestInterface $request): ResponseInterface
    {
        $queryText = $this->extractQueryText($request->getQuery());
        if ($queryText === '') {
            return $this->emptyResponse();
        }

        $storeId = $this->resolveStoreId($request->getDimensions());
        $topK    = max($request->getFrom() + $request->getSize(), $this->config->getSearchResultSize());

        // 1. Embed the query
        $queryVector = $this->embeddingPool->getProvider()->embed($queryText);

        // 2. Vector search — products only on the storefront
        $results = $this->vectorStorePool->getStore()->search(
            $queryVector,
            $topK,
            ['entity_type' => 'product', 'store_id' => $storeId]
        );

        // 3. Drop results below the configured similarity threshold
        $minScore = $this->config->getMinScore();
        if ($minScore > 0.0) {
            $results = array_values(array_filter($results, fn($r) => $r->getScore() >= $minScore));
        }

        // 4. Optional RAG — fire-and-forget; result dispatched as event for frontend block
        if ($this->config->isRagEnabled() && !empty($results)) {
            try {
                $ragContext = $this->llmPool->getProvider()->generateContext($queryText, $results);
                $this->eventManager->dispatch('rivicore_semantiq_rag_context_ready', [
                    'query'   => $queryText,
                    'context' => $ragContext,
                    'results' => $results,
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('SemantiQ: RAG context generation failed: ' . $e->getMessage());
            }
        }

        // 5. Build Magento QueryResponse
        return $this->buildResponse($results);
    }

    private function extractQueryText(QueryInterface $query): string
    {
        // MatchQuery, FuzzyQuery, WildcardQuery all expose getValue()
        if (method_exists($query, 'getValue')) {
            return trim((string) $query->getValue());
        }

        // BoolQuery: drill into must/should clauses
        if (method_exists($query, 'getMust')) {
            foreach ($query->getMust() as $clause) {
                $text = $this->extractQueryText($clause);
                if ($text !== '') {
                    return $text;
                }
            }
        }
        if (method_exists($query, 'getShould')) {
            foreach ($query->getShould() as $clause) {
                $text = $this->extractQueryText($clause);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function resolveStoreId(array $dimensions): int
    {
        foreach ($dimensions as $dimension) {
            if ($dimension->getName() === 'scope') {
                return (int) $dimension->getValue();
            }
        }
        return 0;
    }

    private function buildResponse(array $results): QueryResponse
    {
        $documents = [];
        foreach ($results as $result) {
            $scoreAttr = new AttributeValue();
            $scoreAttr->setAttributeCode('score');
            $scoreAttr->setValue($result->getScore());

            $doc = new Document();
            $doc->setId($result->getEntityId());
            $doc->setCustomAttributes(['score' => $scoreAttr]);

            $documents[] = $doc;
        }

        return new QueryResponse($documents, new Aggregation([]), count($documents));
    }

    private function emptyResponse(): QueryResponse
    {
        return new QueryResponse([], new Aggregation([]), 0);
    }
}
