<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rivicore\SemantiQ\Api\VectorStoreInterface;
use Rivicore\SemantiQ\Model\Config;
use Rivicore\SemantiQ\Model\Data\VectorDocument;
use Rivicore\SemantiQ\Model\Embedding\Pool as EmbeddingPool;
use Rivicore\SemantiQ\Model\ResourceModel\VectorDocument as VectorDocumentResource;
use Rivicore\SemantiQ\Model\TextBuilder\CmsPageTextBuilder;
use Rivicore\SemantiQ\Model\TextBuilder\ProductTextBuilder;
use Rivicore\SemantiQ\Model\VectorStore\Pool as VectorStorePool;

class SemantiQIndexer implements ActionInterface, MviewActionInterface
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly Config                 $config,
        private readonly EmbeddingPool          $embeddingPool,
        private readonly VectorStorePool        $vectorStorePool,
        private readonly ProductTextBuilder     $productTextBuilder,
        private readonly CmsPageTextBuilder     $cmsPageTextBuilder,
        private readonly VectorDocumentResource $vectorDocumentResource,
        private readonly StoreManagerInterface  $storeManager,
        private readonly LoggerInterface        $logger
    ) {}

    /**
     * Full reindex — triggered by bin/magento indexer:reindex rivicore_semantiq
     */
    public function executeFull(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $embeddingProvider = $this->embeddingPool->getProvider();
        $vectorStore       = $this->vectorStorePool->getStore();

        $vectorStore->createIndex($embeddingProvider->getDimension());

        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();

            if ($this->config->isIndexProducts()) {
                $this->indexProducts([], $storeId, $embeddingProvider, $vectorStore);
            }

            if ($this->config->isIndexCmsPages()) {
                $this->indexCmsPages([], $storeId, $embeddingProvider, $vectorStore);
            }
        }
    }

    /**
     * Partial reindex for a list of IDs (MviewActionInterface).
     * IDs may be product entity_ids or CMS page_ids depending on the triggering subscription.
     */
    public function execute($ids): void
    {
        if (!$this->config->isEnabled() || empty($ids)) {
            return;
        }

        $ids             = array_map('intval', $ids);
        $embeddingProvider = $this->embeddingPool->getProvider();
        $vectorStore       = $this->vectorStorePool->getStore();

        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();

            // Try as products first, then as CMS pages
            if ($this->config->isIndexProducts()) {
                $this->indexProducts($ids, $storeId, $embeddingProvider, $vectorStore);
            }

            if ($this->config->isIndexCmsPages()) {
                $this->indexCmsPages($ids, $storeId, $embeddingProvider, $vectorStore);
            }
        }
    }

    /**
     * ActionInterface: reindex a list of entity IDs (used by indexer:reindex with --ids)
     */
    public function executeList(array $ids): void
    {
        $this->execute($ids);
    }

    /**
     * ActionInterface: reindex a single row
     */
    public function executeRow($id): void
    {
        $this->execute([$id]);
    }

    // -------------------------------------------------------------------------

    private function indexProducts(
        array   $ids,
        int     $storeId,
        mixed   $embeddingProvider,
        VectorStoreInterface $vectorStore
    ): void {
        $texts    = $this->productTextBuilder->build($ids, $storeId);
        $payloads = $this->productTextBuilder->buildPayload(array_keys($texts), $storeId);

        foreach (array_chunk($texts, self::BATCH_SIZE, true) as $batch) {
            $documents = [];
            foreach ($batch as $entityId => $text) {
                try {
                    $vector   = $embeddingProvider->embed($text);
                    $vectorId = "product_{$entityId}_{$storeId}";

                    $documents[] = new VectorDocument(
                        id: $vectorId,
                        entityType: 'product',
                        entityId: $entityId,
                        storeId: $storeId,
                        vector: $vector,
                        payload: $payloads[$entityId] ?? [],
                        textContent: $text
                    );

                    $this->vectorDocumentResource->upsert('product', $entityId, $storeId, $vectorId);
                } catch (\Throwable $e) {
                    $this->logger->error('SemantiQ: failed to embed product ' . $entityId . ': ' . $e->getMessage());
                }
            }

            if (!empty($documents)) {
                try {
                    $vectorStore->upsert($documents);
                } catch (\Throwable $e) {
                    $this->logger->error('SemantiQ: failed to upsert product batch: ' . $e->getMessage());
                }
            }
        }
    }

    private function indexCmsPages(
        array   $ids,
        int     $storeId,
        mixed   $embeddingProvider,
        VectorStoreInterface $vectorStore
    ): void {
        $texts    = $this->cmsPageTextBuilder->build($ids);
        $payloads = $this->cmsPageTextBuilder->buildPayload(array_keys($texts));

        foreach (array_chunk($texts, self::BATCH_SIZE, true) as $batch) {
            $documents = [];
            foreach ($batch as $entityId => $text) {
                try {
                    $vector   = $embeddingProvider->embed($text);
                    $vectorId = "cms_page_{$entityId}_{$storeId}";

                    $documents[] = new VectorDocument(
                        id: $vectorId,
                        entityType: 'cms_page',
                        entityId: $entityId,
                        storeId: $storeId,
                        vector: $vector,
                        payload: $payloads[$entityId] ?? [],
                        textContent: $text
                    );

                    $this->vectorDocumentResource->upsert('cms_page', $entityId, $storeId, $vectorId);
                } catch (\Throwable $e) {
                    $this->logger->error('SemantiQ: failed to embed CMS page ' . $entityId . ': ' . $e->getMessage());
                }
            }

            if (!empty($documents)) {
                try {
                    $vectorStore->upsert($documents);
                } catch (\Throwable $e) {
                    $this->logger->error('SemantiQ: failed to upsert CMS page batch: ' . $e->getMessage());
                }
            }
        }
    }
}
