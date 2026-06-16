<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model\VectorStore;

use Magento\Framework\Phrase;
use Rivicore\SemantiQ\Api\Data\VectorDocumentInterface;
use Rivicore\SemantiQ\Api\Data\VectorSearchResultInterface;
use Rivicore\SemantiQ\Api\VectorStoreInterface;
use Rivicore\SemantiQ\Exception\VectorStoreException;
use Rivicore\SemantiQ\Model\Config;
use Rivicore\SemantiQ\Model\Data\VectorSearchResult;

/**
 * AWS Bedrock Knowledge Base vector store adapter.
 *
 * Note: Bedrock KB manages its own index creation and deletion through the AWS console/CLI.
 * createIndex() and deleteIndex() are no-ops here. Upsert uses the Bedrock Agent Runtime
 * IngestKnowledgeBaseDocuments API; search uses RetrieveAndGenerate (retrieve-only mode).
 *
 * Requires AWS SDK for PHP: aws/aws-sdk-php
 */
class BedrockKbAdapter implements VectorStoreInterface
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function createIndex(int $dimension): void
    {
        // Bedrock Knowledge Bases are provisioned via the AWS console/CLI, not at runtime.
    }

    public function deleteIndex(): void
    {
        // Not managed at runtime.
    }

    public function upsert(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $client = $this->buildAgentClient();
        $kbId   = $this->config->getBedrockKbId();

        // Build document chunks for ingestion
        $docItems = [];
        foreach ($documents as $doc) {
            $text = implode(' ', array_filter([
                $doc->getPayload()['name']        ?? '',
                $doc->getPayload()['description'] ?? '',
            ]));

            $docItems[] = [
                'content'  => ['dataSourceType' => 'INLINE', 'inlineContent' => [
                    'type'        => 'TEXT',
                    'textContent' => ['data' => $text],
                ]],
                'metadata' => ['inlineAttributes' => [
                    ['key' => 'entity_id',   'value' => ['type' => 'NUMBER',  'numberValue' => $doc->getEntityId()]],
                    ['key' => 'entity_type', 'value' => ['type' => 'STRING',  'stringValue' => $doc->getEntityType()]],
                    ['key' => 'store_id',    'value' => ['type' => 'NUMBER',  'numberValue' => $doc->getStoreId()]],
                    ['key' => 'doc_id',      'value' => ['type' => 'STRING',  'stringValue' => $doc->getId()]],
                ]],
            ];
        }

        try {
            $client->ingestKnowledgeBaseDocuments([
                'knowledgeBaseId' => $kbId,
                'documents'       => $docItems,
            ]);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: Bedrock KB upsert failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function delete(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $client = $this->buildAgentClient();
        $kbId   = $this->config->getBedrockKbId();

        try {
            $client->deleteKnowledgeBaseDocuments([
                'knowledgeBaseId' => $kbId,
                'documentIdentifiers' => array_map(fn($id) => [
                    'dataSourceType' => 'INLINE',
                    'inlineDocumentIdentifier' => ['documentId' => $id],
                ], $ids),
            ]);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: Bedrock KB delete failed: %1', [$e->getMessage()]), $e);
        }
    }

    public function search(array $queryVector, int $topK, array $filters = []): array
    {
        $client = $this->buildRuntimeClient();
        $kbId   = $this->config->getBedrockKbId();

        // Bedrock KB Retrieve API performs the vector search internally
        $params = [
            'knowledgeBaseId'         => $kbId,
            'retrievalQuery'          => ['text' => $filters['_query_text'] ?? ''],
            'retrievalConfiguration'  => [
                'vectorSearchConfiguration' => [
                    'numberOfResults' => $topK,
                ],
            ],
        ];

        if (!empty($filters['entity_type'])) {
            $params['retrievalConfiguration']['vectorSearchConfiguration']['filter'] = [
                'equals' => ['key' => 'entity_type', 'value' => $filters['entity_type']],
            ];
        }

        try {
            $response = $client->retrieve($params);
        } catch (\Throwable $e) {
            throw new VectorStoreException(new Phrase('SemantiQ: Bedrock KB search failed: %1', [$e->getMessage()]), $e);
        }

        $results = [];
        foreach ($response['retrievalResults'] ?? [] as $item) {
            $attrs     = [];
            foreach ($item['metadata'] ?? [] as $k => $v) {
                $attrs[$k] = $v;
            }

            $results[] = new VectorSearchResult(
                id: (string) ($attrs['doc_id'] ?? uniqid('bkb_', true)),
                entityId: (int) ($attrs['entity_id'] ?? 0),
                entityType: (string) ($attrs['entity_type'] ?? 'product'),
                storeId: (int) ($attrs['store_id'] ?? 0),
                score: (float) ($item['score'] ?? 0.0),
                payload: $attrs
            );
        }

        return $results;
    }

    private function buildAgentClient(): mixed
    {
        return $this->buildSdkClient('BedrockAgent');
    }

    private function buildRuntimeClient(): mixed
    {
        return $this->buildSdkClient('BedrockAgentRuntime');
    }

    private function buildSdkClient(string $service): mixed
    {
        if (!class_exists(\Aws\Sdk::class)) {
            throw new VectorStoreException(new Phrase(
                'SemantiQ: AWS SDK for PHP is not installed. Run: composer require aws/aws-sdk-php'
            ));
        }

        $sdk = new \Aws\Sdk([
            'region'      => $this->config->getBedrockKbRegion(),
            'version'     => 'latest',
            'credentials' => [
                'key'    => $this->config->getBedrockKbAccessKey(),
                'secret' => $this->config->getBedrockKbSecretKey(),
            ],
        ]);

        return $sdk->createClient($service);
    }
}
