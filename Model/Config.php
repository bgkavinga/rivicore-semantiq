<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PREFIX = 'rivicore_semantiq/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    // -------------------------------------------------------------------------
    // General
    // -------------------------------------------------------------------------

    public function isEnabled(?string $scopeCode = null): bool
    {
        return $this->flag('general/enabled', $scopeCode);
    }

    public function isIndexProducts(?string $scopeCode = null): bool
    {
        return $this->flag('general/index_products', $scopeCode);
    }

    public function isIndexCmsPages(?string $scopeCode = null): bool
    {
        return $this->flag('general/index_cms_pages', $scopeCode);
    }

    public function isRagEnabled(?string $scopeCode = null): bool
    {
        return $this->flag('general/rag_enabled', $scopeCode);
    }

    /** @return string[] attribute codes */
    public function getIndexAttributes(?string $scopeCode = null): array
    {
        $raw = $this->value('general/index_attributes', $scopeCode);
        if (!$raw) {
            return ['name', 'description'];
        }
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    public function getSearchResultSize(?string $scopeCode = null): int
    {
        return (int) ($this->value('general/search_result_size', $scopeCode) ?: 20);
    }

    // -------------------------------------------------------------------------
    // Vector Store
    // -------------------------------------------------------------------------

    public function getVectorStoreBackend(): string
    {
        return (string) ($this->value('vector_store/backend') ?: 'opensearch');
    }

    public function getOpenSearchIndexName(): string
    {
        return (string) ($this->value('vector_store/opensearch_index_name') ?: 'semantiq_vectors');
    }

    public function getVectorOpenSearchHost(): string
    {
        return trim((string) $this->value('vector_store/opensearch_host'));
    }

    public function getVectorOpenSearchUsername(): string
    {
        return (string) $this->value('vector_store/opensearch_username');
    }

    public function getVectorOpenSearchPassword(): string
    {
        return (string) $this->value('vector_store/opensearch_password');
    }

    public function getChromaDbUrl(): string
    {
        return (string) $this->value('vector_store/chromadb_url');
    }

    public function getChromaDbCollection(): string
    {
        return (string) ($this->value('vector_store/chromadb_collection') ?: 'semantiq');
    }

    public function getBedrockKbRegion(): string
    {
        return (string) $this->value('vector_store/bedrock_kb_region');
    }

    public function getBedrockKbId(): string
    {
        return (string) $this->value('vector_store/bedrock_kb_id');
    }

    public function getBedrockKbAccessKey(): string
    {
        return (string) $this->value('vector_store/bedrock_kb_access_key');
    }

    public function getBedrockKbSecretKey(): string
    {
        return (string) $this->value('vector_store/bedrock_kb_secret_key');
    }

    // -------------------------------------------------------------------------
    // Embedding Provider
    // -------------------------------------------------------------------------

    public function getEmbeddingProvider(): string
    {
        return (string) ($this->value('embedding/provider') ?: 'openai');
    }

    public function getOpenSearchMlModelId(): string
    {
        return (string) $this->value('embedding/opensearch_ml_model_id');
    }

    public function getOpenSearchMlDimension(): int
    {
        return (int) ($this->value('embedding/opensearch_ml_dimension') ?: 768);
    }

    public function getOpenAiApiKey(): string
    {
        return (string) $this->value('embedding/openai_api_key');
    }

    public function getOpenAiModel(): string
    {
        return (string) ($this->value('embedding/openai_model') ?: 'text-embedding-3-small');
    }

    public function getBedrockEmbedRegion(): string
    {
        return (string) $this->value('embedding/bedrock_region');
    }

    public function getBedrockEmbedModel(): string
    {
        return (string) ($this->value('embedding/bedrock_model') ?: 'amazon.titan-embed-text-v2:0');
    }

    public function getBedrockEmbedAccessKey(): string
    {
        return (string) $this->value('embedding/bedrock_access_key');
    }

    public function getBedrockEmbedSecretKey(): string
    {
        return (string) $this->value('embedding/bedrock_secret_key');
    }

    public function getOllamaBaseUrl(): string
    {
        return rtrim((string) ($this->value('embedding/ollama_base_url') ?: 'http://localhost:11434'), '/');
    }

    public function getOllamaModel(): string
    {
        return (string) ($this->value('embedding/ollama_model') ?: 'nomic-embed-text');
    }

    public function getAnthropicApiKey(): string
    {
        return (string) $this->value('embedding/anthropic_api_key');
    }

    public function getAnthropicModel(): string
    {
        return (string) ($this->value('embedding/anthropic_model') ?: 'claude-haiku-4-5-20251001');
    }

    // -------------------------------------------------------------------------
    // LLM Provider
    // -------------------------------------------------------------------------

    public function getLlmProvider(): string
    {
        return (string) ($this->value('llm/llm_provider') ?: 'openai');
    }

    public function getLlmOpenAiApiKey(): string
    {
        return (string) $this->value('llm/llm_openai_api_key');
    }

    public function getLlmOpenAiModel(): string
    {
        return (string) ($this->value('llm/llm_openai_model') ?: 'gpt-4o-mini');
    }

    public function getLlmBedrockRegion(): string
    {
        return (string) $this->value('llm/llm_bedrock_region');
    }

    public function getLlmBedrockModel(): string
    {
        return (string) ($this->value('llm/llm_bedrock_model') ?: 'anthropic.claude-3-haiku-20240307-v1:0');
    }

    public function getLlmBedrockAccessKey(): string
    {
        return (string) $this->value('llm/llm_bedrock_access_key');
    }

    public function getLlmBedrockSecretKey(): string
    {
        return (string) $this->value('llm/llm_bedrock_secret_key');
    }

    public function getLlmOllamaBaseUrl(): string
    {
        return rtrim((string) ($this->value('llm/llm_ollama_base_url') ?: 'http://localhost:11434'), '/');
    }

    public function getLlmOllamaModel(): string
    {
        return (string) ($this->value('llm/llm_ollama_model') ?: 'llama3');
    }

    public function getLlmAnthropicApiKey(): string
    {
        return (string) $this->value('llm/llm_anthropic_api_key');
    }

    public function getLlmAnthropicModel(): string
    {
        return (string) ($this->value('llm/llm_anthropic_model') ?: 'claude-haiku-4-5-20251001');
    }

    public function getRagMaxContextDocs(): int
    {
        return (int) ($this->value('llm/rag_max_context_docs') ?: 5);
    }

    public function getRagPromptTemplate(): string
    {
        return (string) $this->value('llm/rag_prompt_template');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function value(string $path, ?string $scopeCode = null): mixed
    {
        $scope = $scopeCode ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        return $this->scopeConfig->getValue(self::XML_PREFIX . $path, $scope, $scopeCode);
    }

    private function flag(string $path, ?string $scopeCode = null): bool
    {
        $scope = $scopeCode ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        return $this->scopeConfig->isSetFlag(self::XML_PREFIX . $path, $scope, $scopeCode);
    }
}
