<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PREFIX = 'rivicore_semantiq/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
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
            return ['name', 'short_description', 'description'];
        }
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    public function getMaxTextChars(?string $scopeCode = null): int
    {
        return (int) ($this->value('general/max_text_chars', $scopeCode) ?: 2000);
    }

    public function getSearchResultSize(?string $scopeCode = null): int
    {
        return (int) ($this->value('general/search_result_size', $scopeCode) ?: 20);
    }

    public function getMinScore(?string $scopeCode = null): float
    {
        return (float) ($this->value('general/min_score', $scopeCode) ?? 0.70);
    }

    public function isHybridEnabled(?string $scopeCode = null): bool
    {
        return $this->flag('general/hybrid_enabled', $scopeCode);
    }

    public function getHybridBm25Weight(?string $scopeCode = null): float
    {
        return (float) ($this->value('general/hybrid_bm25_weight', $scopeCode) ?? 0.3);
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
        return $this->secret('vector_store/bedrock_kb_access_key');
    }

    public function getBedrockKbSecretKey(): string
    {
        return $this->secret('vector_store/bedrock_kb_secret_key');
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
        return (int) ($this->value('embedding/opensearch_ml_dimension') ?: 384);
    }

    public function getOpenAiApiKey(): string
    {
        return $this->secret('embedding/openai_api_key');
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
        return $this->secret('embedding/bedrock_access_key');
    }

    public function getBedrockEmbedSecretKey(): string
    {
        return $this->secret('embedding/bedrock_secret_key');
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
        return $this->secret('embedding/anthropic_api_key');
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
        return $this->secret('llm/llm_openai_api_key');
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

    public function getLlmBedrockApiKey(): string
    {
        return $this->secret('llm/llm_bedrock_api_key');
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
        return $this->secret('llm/llm_anthropic_api_key');
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

    public function getStoreBaseUrl(int $storeId = 0): string
    {
        $scope = $storeId > 0 ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $url   = (string) $this->scopeConfig->getValue('web/unsecure/base_url', $scope, $storeId ?: null);
        return $url ? rtrim($url, '/') . '/' : '';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function value(string $path, ?string $scopeCode = null): mixed
    {
        $scope = $scopeCode ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        return $this->scopeConfig->getValue(self::XML_PREFIX . $path, $scope, $scopeCode);
    }

    private function secret(string $path, ?string $scopeCode = null): string
    {
        return (string) $this->encryptor->decrypt($this->value($path, $scopeCode));
    }

    private function flag(string $path, ?string $scopeCode = null): bool
    {
        $scope = $scopeCode ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        return $this->scopeConfig->isSetFlag(self::XML_PREFIX . $path, $scope, $scopeCode);
    }
}
