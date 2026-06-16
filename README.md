# Rivicore SemantiQ — Semantic Vector Search for Adobe Commerce

SemantiQ replaces Adobe Commerce's default keyword search with semantic vector search. Instead of matching keywords, it understands the *meaning* of a search query and returns products and CMS pages that are conceptually relevant — even when the exact words do not appear in the product name or description.

When a shopper types "something warm for winter camping", SemantiQ finds sleeping bags, thermal jackets, and base layers rather than only items literally containing those words.

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [How It Works](#how-it-works)
4. [Configuration Reference](#configuration-reference)
   - [General Settings](#general-settings)
   - [Vector Store Backend](#vector-store-backend)
   - [Embedding Provider](#embedding-provider)
   - [LLM Provider (RAG)](#llm-provider-rag)
5. [External Service Setup](#external-service-setup)
   - [OpenSearch ML Commons (built-in embeddings)](#1-opensearch-ml-commons-built-in-embeddings)
   - [OpenSearch as Vector Store](#2-opensearch-as-vector-store)
   - [ChromaDB](#3-chromadb)
   - [AWS Bedrock Knowledge Base](#4-aws-bedrock-knowledge-base)
   - [OpenAI](#5-openai)
   - [AWS Bedrock Embeddings (Titan)](#6-aws-bedrock-embeddings-titan)
   - [Ollama (local)](#7-ollama-local)
   - [Anthropic Claude](#8-anthropic-claude)
6. [Indexing](#indexing)
7. [Excluding Entities from the Index](#excluding-entities-from-the-index)
8. [RAG Context Block (Frontend)](#rag-context-block-frontend)
9. [Recommended Combinations](#recommended-combinations)
10. [Troubleshooting](#troubleshooting)

---

## Requirements

| Requirement | Version |
|---|---|
| Adobe Commerce / Magento Open Source | 2.4.6+ |
| PHP | 8.2, 8.3, or 8.4 |
| OpenSearch or Elasticsearch | 2.x (already required by Magento) |
| Guzzle HTTP | 7.x (bundled with Magento) |

Optional (depending on chosen backend):
- AWS account with Bedrock and/or OpenSearch Service
- OpenAI API account
- Anthropic API account
- ChromaDB server
- Ollama installation

---

## Installation

### Via Composer (recommended)

```bash
composer require rivicore/module-semantiq
```

If the package is not yet on Packagist, add the VCS repository first:

```bash
composer config repositories.rivicore-semantiq vcs https://github.com/bgkavinga/rivicore-semantiq
composer require rivicore/module-semantiq
```

### Enable and set up

```bash
# 1. Enable the module
bin/magento module:enable Rivicore_SemantiQ

# 2. Apply database schema and data patches
bin/magento setup:upgrade

# 3. Regenerate DI container
bin/magento setup:di:compile

# 4. Flush cache
bin/magento cache:flush
```

After installation, go to **Stores → Configuration → Rivicore → SemantiQ Semantic Search** to configure the module.

---

## How It Works

```
Product/CMS text  ──▶  Embedding Provider  ──▶  float[] vector  ──▶  Vector Store
                                                                            │
Shopper query  ────▶  Embedding Provider  ──▶  float[] vector  ──┐        │
                                                                   └──▶  kNN search  ──▶  Ranked product IDs
                                                                                               │
                                                                            (optional RAG)     │
                                                                   LLM Provider  ◀────────────┘
                                                                        │
                                                                  Context summary  ──▶  Frontend event
```

1. **Indexing** — product and CMS page text is converted to numeric vectors by the embedding provider and stored in the vector store.
2. **Search** — when a shopper submits a query, it is embedded using the same provider, and the vector store returns the nearest neighbours by cosine similarity.
3. **RAG (optional)** — the top results are passed to an LLM, which generates a short contextual summary dispatched as a Magento event for display in a frontend block.

The existing Elasticsearch/OpenSearch search is preserved as an automatic fallback if semantic search is disabled or encounters an error.

---

## Configuration Reference

Navigate to **Stores → Configuration → Rivicore → SemantiQ Semantic Search**.

---

### General Settings

#### Enable SemantiQ
- **Type**: Yes/No
- **Default**: No
- **What it does**: Master switch. When set to **Yes**, all storefront search queries are routed through the vector search pipeline. When set to **No**, the standard Magento Elasticsearch search is used.
- **Note**: Set up the vector store and run a full reindex *before* enabling this. Enabling with an empty index returns no results.

#### Index Products
- **Type**: Yes/No
- **Default**: Yes
- **What it does**: Controls whether products are included in the vector index. Disable this if you only want to use SemantiQ for CMS page search.

#### Index CMS Pages
- **Type**: Yes/No
- **Default**: Yes
- **What it does**: Controls whether CMS pages are included in the vector index. CMS pages appear alongside products in search results.

#### Enable RAG (LLM Context)
- **Type**: Yes/No
- **Default**: No
- **What it does**: When enabled, the top vector search results are sent to the configured LLM provider, which produces a short contextual summary. The summary is dispatched via the Magento event `rivicore_semantiq_rag_context_ready` for use in a frontend block. This does not affect the ranked product results — it only generates an optional explanatory text.
- **Cost warning**: Each search query incurs one additional LLM API call. At high traffic volumes this can be expensive. Consider enabling only for specific pages via a custom observer.

#### Product Attributes to Index
- **Type**: Multi-select
- **Default**: `name`, `description`
- **What it does**: The selected product attribute values are concatenated into a single text string, which is then embedded. Adding more attributes gives the model richer context but also increases the text length sent to the embedding provider (and therefore cost/latency for token-based APIs like OpenAI).
- **Recommended attributes**: `name`, `description`, `short_description`, `manufacturer`, `color`, `size`
- **Scope**: Global (per Magento installation, not per store view)

#### Max Search Results
- **Type**: Integer
- **Default**: 20
- **What it does**: The maximum number of products returned by a vector search. This is the value sent to the vector store as the `k` (number of nearest neighbours) parameter.

---

### Vector Store Backend

The vector store holds the numeric embeddings and performs the k-nearest-neighbour (kNN) search at query time.

Navigate to the **Vector Store Backend** group.

#### Backend
- **Type**: Select
- **Options**: `OpenSearch (built-in)`, `ChromaDB`, `AWS Bedrock Knowledge Base`
- **Default**: `OpenSearch (built-in)`
- **What it does**: Selects where vectors are stored and searched. See [External Service Setup](#external-service-setup) for how to configure each backend.

---

#### OpenSearch fields (shown when Backend = OpenSearch)

##### OpenSearch Index Name
- **Type**: Text
- **Default**: `semantiq_vectors`
- **What it does**: The name of the dedicated OpenSearch index used to store vectors. This is separate from Magento's own catalog search index (`magento2_product_1`, etc.) to avoid conflicts. You can change this if the default name conflicts with an existing index.

---

#### ChromaDB fields (shown when Backend = ChromaDB)

##### ChromaDB URL
- **Type**: Text
- **Example**: `http://localhost:8000`
- **What it does**: The base URL of your ChromaDB HTTP server. Must be reachable from the PHP/Magento server. See [ChromaDB setup](#3-chromadb) for installation instructions.

##### ChromaDB Collection Name
- **Type**: Text
- **Default**: `semantiq`
- **What it does**: The name of the ChromaDB collection used to store vectors. Created automatically on first index run if it does not already exist.

---

#### AWS Bedrock Knowledge Base fields (shown when Backend = AWS Bedrock Knowledge Base)

##### AWS Region
- **Type**: Text
- **Example**: `us-east-1`
- **What it does**: The AWS region where the Bedrock Knowledge Base is provisioned. Must match the region used when creating the Knowledge Base in the AWS console.

##### Knowledge Base ID
- **Type**: Text
- **Example**: `ABCDEF1234`
- **What it does**: The unique identifier of the Bedrock Knowledge Base. Found in the AWS console under **Amazon Bedrock → Knowledge Bases**.

##### AWS Access Key ID / AWS Secret Access Key
- **Type**: Password (encrypted at rest)
- **What it does**: IAM credentials with permission to call `bedrock:Retrieve`, `bedrock:IngestKnowledgeBaseDocuments`, and `bedrock:DeleteKnowledgeBaseDocuments` on the target Knowledge Base. See [AWS Bedrock Knowledge Base setup](#4-aws-bedrock-knowledge-base) for the required IAM policy.

---

### Embedding Provider

The embedding provider converts text into numeric vectors. The same provider must be used for both indexing and search — changing the provider after indexing requires a full reindex.

Navigate to the **Embedding Provider** group.

#### Provider
- **Type**: Select
- **Options**: `OpenSearch ML Commons (built-in)`, `OpenAI`, `AWS Bedrock (Titan)`, `Ollama (local)`, `Anthropic Claude (experimental)`
- **Default**: `OpenAI`
- **Important**: All documents in the vector store must use vectors from the same provider and model. If you switch providers, you must run `bin/magento indexer:reindex rivicore_semantiq` to rebuild the entire index with the new vectors before re-enabling the module.

---

#### OpenSearch ML Commons fields (shown when Provider = OpenSearch ML Commons)

##### ML Model ID
- **Type**: Text
- **Example**: `bQ1J8ooBpBj3wT4HVemV`
- **What it does**: The ID of the text-embedding model deployed in your OpenSearch cluster via ML Commons. The model must be in `DEPLOYED` status. See [OpenSearch ML Commons setup](#1-opensearch-ml-commons-built-in-embeddings) for how to deploy a model and find its ID.

##### Embedding Dimension
- **Type**: Integer
- **Default**: `768`
- **What it does**: Must match the output dimension of the deployed model exactly. Common values:
  - SBERT / all-MiniLM / BGE-small: `384`
  - most SBERT-large / BGE-base: `768`
  - OpenAI-compatible / BGE-large: `1024` or `1536`
- **Note**: If this does not match the actual model output, the OpenSearch index mapping will reject vectors and indexing will fail.

---

#### OpenAI fields (shown when Provider = OpenAI)

##### OpenAI API Key
- **Type**: Password (encrypted at rest)
- **What it does**: Your OpenAI API secret key (`sk-...`). See [OpenAI setup](#5-openai).

##### OpenAI Embedding Model
- **Type**: Text
- **Default**: `text-embedding-3-small`
- **Options**:
  | Model | Dimension | Best for |
  |---|---|---|
  | `text-embedding-3-small` | 1536 | Best price/quality ratio for most use cases |
  | `text-embedding-3-large` | 3072 | Highest accuracy, higher cost |
  | `text-embedding-ada-002` | 1536 | Legacy, lower performance than v3 |

---

#### AWS Bedrock (Titan) fields (shown when Provider = AWS Bedrock)

##### AWS Region
- **Type**: Text
- **Example**: `us-east-1`

##### Bedrock Embedding Model ID
- **Type**: Text
- **Default**: `amazon.titan-embed-text-v2:0`
- **Options**:
  | Model ID | Dimension | Notes |
  |---|---|---|
  | `amazon.titan-embed-text-v2:0` | 1024 | Recommended — supports up to 8,192 tokens |
  | `amazon.titan-embed-text-v1` | 1536 | Older model, max 8,192 tokens |
  | `cohere.embed-english-v3` | 1024 | English-only, strong performance |
  | `cohere.embed-multilingual-v3` | 1024 | 100+ languages |

##### AWS Access Key ID / AWS Secret Access Key
- **Type**: Password (encrypted at rest)
- **Required IAM permissions**: `bedrock:InvokeModel` on the target model ARN.

---

#### Ollama fields (shown when Provider = Ollama)

##### Ollama Base URL
- **Type**: Text
- **Default**: `http://localhost:11434`
- **What it does**: Base URL of the Ollama HTTP server. The PHP/Magento server must be able to reach this URL. If Ollama runs on a different machine, replace `localhost` with the server's IP or hostname.

##### Ollama Embedding Model
- **Type**: Text
- **Default**: `nomic-embed-text`
- **Options**:
  | Model | Dimension | Notes |
  |---|---|---|
  | `nomic-embed-text` | 768 | Good general-purpose model, small footprint |
  | `mxbai-embed-large` | 1024 | Higher accuracy, requires more RAM |
  | `bge-m3` | 1024 | Multilingual, strong cross-lingual retrieval |
  | `all-minilm` | 384 | Very fast, lower quality |

---

#### Anthropic fields (shown when Provider = Anthropic Claude) — Experimental

##### Anthropic API Key
- **Type**: Password (encrypted at rest)

##### Anthropic Model
- **Type**: Text
- **Default**: `claude-haiku-4-5-20251001`
- **Warning**: Anthropic does not provide a dedicated embedding API. This provider sends the text to a Claude model with a structured prompt asking for a JSON float array. This is significantly slower, more expensive, and less accurate than a dedicated embedding model. Use OpenAI, Bedrock, or Ollama for production deployments.

---

### LLM Provider (RAG)

The LLM provider is only used when **Enable RAG** is set to **Yes**. It takes the top vector search results and the original query and produces a short contextual summary.

All configuration fields mirror the Embedding Provider section — provider selection plus credentials and model name for the chosen provider.

#### Shared RAG Options

##### Max Context Documents
- **Type**: Integer
- **Default**: `5`
- **What it does**: How many of the top-ranked search results are included in the LLM context window. Higher values give the LLM more information but increase token usage and latency.

##### RAG Prompt Template
- **Type**: Textarea
- **Placeholders**: `{{query}}` (the shopper's search query), `{{context}}` (numbered list of top products/pages)
- **Default**:
  ```
  You are a helpful shopping assistant. Based on the following product information,
  provide a brief and helpful recommendation for the search query "{{query}}".

  Products:
  {{context}}

  Provide a 2-3 sentence summary highlighting the most relevant products for this search.
  ```
- **What it does**: The full prompt sent to the LLM. Customise it to match your brand voice or to focus on specific aspects of the products.

---

## External Service Setup

---

### 1. OpenSearch ML Commons (built-in embeddings)

OpenSearch ML Commons allows you to deploy a text-embedding model directly inside your OpenSearch cluster — no external API calls required at search time.

#### Prerequisites
- OpenSearch 2.4 or later with the ML Commons plugin enabled (it is enabled by default on AWS OpenSearch Service and on the OpenSearch Docker images).
- The cluster must have at least one data node with enough RAM for the model (~1–4 GB depending on model size).

#### Step 1 — Enable ML on all nodes (self-hosted only)

Add this to `opensearch.yml` on every node, then restart:

```yaml
plugins.ml_commons.only_run_on_ml_node: false
plugins.ml_commons.native_memory_threshold: 99
```

For AWS OpenSearch Service, these settings are already configured.

#### Step 2 — Register the model

SemantiQ works with any text-embedding model that can be deployed via ML Commons. The recommended starting point is Hugging Face `sentence-transformers/all-MiniLM-L6-v2` (768 dimensions), which is pre-registered in OpenSearch's model registry.

**Option A: Use the pre-built model registry (recommended)**

```bash
# Register a pre-built sentence-transformers model
POST /_plugins/_ml/models/_register
{
  "name": "huggingface/sentence-transformers/all-MiniLM-L6-v2",
  "version": "1.0.1",
  "model_format": "TORCH_SCRIPT"
}
```

The response contains a `task_id`. Check the task status:

```bash
GET /_plugins/_ml/tasks/<task_id>
```

When `state` is `COMPLETED`, the response includes the `model_id`. Copy this value — you will need it in the SemantiQ admin config.

**Option B: Register a custom model from a URL**

```bash
POST /_plugins/_ml/models/_register
{
  "name": "my-custom-embedding-model",
  "version": "1.0.0",
  "model_format": "TORCH_SCRIPT",
  "model_task_type": "TEXT_EMBEDDING",
  "model_config": {
    "model_type": "bert",
    "embedding_dimension": 768,
    "framework_type": "sentence_transformers"
  },
  "url": "https://your-server.com/model.zip"
}
```

#### Step 3 — Deploy the model

```bash
POST /_plugins/_ml/models/<model_id>/_deploy
```

Check deployment status:

```bash
GET /_plugins/_ml/tasks/<deploy_task_id>
```

Wait until `state` is `COMPLETED`.

#### Step 4 — Verify the model is working

```bash
POST /_plugins/_ml/models/<model_id>/predict
{
  "text_docs": ["test product description"]
}
```

The response should contain an `inference_results` array with a `data` field of floats. Count the floats to confirm the embedding dimension.

#### Step 5 — Configure SemantiQ

- **Embedding Provider** → `OpenSearch ML Commons (built-in)`
- **ML Model ID** → the `model_id` from step 2
- **Embedding Dimension** → the dimension confirmed in step 4 (e.g. `768`)

#### Finding an existing model ID

```bash
GET /_plugins/_ml/models/_search
{
  "query": { "match_all": {} },
  "size": 20
}
```

Look for models with `"model_state": "DEPLOYED"`.

---

### 2. OpenSearch as Vector Store

When **Backend = OpenSearch (built-in)**, SemantiQ creates a dedicated index in the same OpenSearch cluster that Magento already uses. No additional server is required.

#### Prerequisites
- The **k-NN plugin** must be installed. It is included by default in OpenSearch 1.0+ and AWS OpenSearch Service.

Verify the plugin is present:

```bash
GET /_cat/plugins?v&h=name,component,version
```

Look for a line containing `opensearch-knn`.

#### What SemantiQ creates automatically

When you run the indexer for the first time, SemantiQ creates an index named `semantiq_vectors` (or whatever you configured in **OpenSearch Index Name**) with the following mapping:

```json
{
  "settings": {
    "index.knn": true
  },
  "mappings": {
    "properties": {
      "vector":      { "type": "knn_vector", "dimension": <your_dimension> },
      "entity_type": { "type": "keyword" },
      "entity_id":   { "type": "integer" },
      "store_id":    { "type": "integer" },
      "payload":     { "type": "object", "enabled": false }
    }
  }
}
```

No manual index creation is needed. If the index already exists with a different mapping, drop it first:

```bash
DELETE /semantiq_vectors
```

Then rerun the Magento indexer.

---

### 3. ChromaDB

ChromaDB is an open-source, self-hosted vector database. It is the simplest option if you prefer not to use the OpenSearch cluster for vector storage.

#### Option A — Docker (recommended)

```bash
docker run -d \
  --name chromadb \
  -p 8000:8000 \
  -v chroma-data:/chroma/chroma \
  chromadb/chroma:latest
```

Verify it is running:

```bash
curl http://localhost:8000/api/v1/heartbeat
# Expected: {"nanosecond heartbeat": <timestamp>}
```

#### Option B — pip

```bash
pip install chromadb
chroma run --host 0.0.0.0 --port 8000 --path ./chroma-data
```

#### Option C — Docker Compose

```yaml
version: '3.8'
services:
  chromadb:
    image: chromadb/chroma:latest
    ports:
      - "8000:8000"
    volumes:
      - chroma-data:/chroma/chroma
    environment:
      - CHROMA_SERVER_HTTP_PORT=8000
volumes:
  chroma-data:
```

```bash
docker compose up -d
```

#### Networking

The ChromaDB server must be reachable from the machine running PHP/Magento. If Magento runs in Docker, use the container name as the hostname, or use the host machine's IP address.

#### Configure SemantiQ

- **Backend** → `ChromaDB`
- **ChromaDB URL** → `http://chromadb:8000` (Docker) or `http://192.168.x.x:8000` (remote server) or `http://localhost:8000` (same machine)
- **ChromaDB Collection Name** → `semantiq` (or any name — created automatically)

#### Persistence

Ensure the Docker volume (`chroma-data`) persists across container restarts. Without a volume, the vector index is lost every time the container is restarted.

---

### 4. AWS Bedrock Knowledge Base

AWS Bedrock Knowledge Bases is a fully managed RAG and vector search service. Bedrock handles chunking, embedding, and vector storage automatically. SemantiQ uses it in "ingest + retrieve" mode, bypassing Bedrock's built-in RAG generation in favour of its own LLM provider.

#### Step 1 — Create a Knowledge Base in the AWS Console

1. Open the **AWS Console** → **Amazon Bedrock** → **Knowledge Bases** → **Create knowledge base**.
2. **Name**: e.g. `semantiq-products`
3. **IAM role**: Let AWS create a new role, or use an existing one.
4. **Data source**: Choose **Inline** (SemantiQ pushes documents directly via the API, no S3 bucket needed).
5. **Embedding model**: Choose `Amazon Titan Text Embeddings V2` (or any supported model — note the dimension).
6. **Vector store**: Choose `Amazon OpenSearch Serverless` (AWS-managed) or bring your own OpenSearch/Pinecone/RDS Aurora cluster.
7. Complete creation and note the **Knowledge Base ID** (format: `ABCDEF1234`).

#### Step 2 — Create an IAM User with the required permissions

In **IAM → Users → Create user**:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "bedrock:Retrieve",
        "bedrock:IngestKnowledgeBaseDocuments",
        "bedrock:DeleteKnowledgeBaseDocuments",
        "bedrock:GetKnowledgeBase"
      ],
      "Resource": "arn:aws:bedrock:<region>:<account-id>:knowledge-base/<knowledge-base-id>"
    }
  ]
}
```

Create an **Access Key** for this user and note the Access Key ID and Secret Access Key.

#### Step 3 — Configure SemantiQ

- **Backend** → `AWS Bedrock Knowledge Base`
- **AWS Region** → the region where the Knowledge Base was created (e.g. `us-east-1`)
- **Knowledge Base ID** → the ID from step 1 (e.g. `ABCDEF1234`)
- **AWS Access Key ID** / **AWS Secret Access Key** → from step 2

#### Step 4 — Install the AWS SDK

The AWS SDK is not bundled with Magento. Install it:

```bash
composer require aws/aws-sdk-php
```

---

### 5. OpenAI

#### Step 1 — Create an API key

1. Go to [platform.openai.com/api-keys](https://platform.openai.com/api-keys).
2. Click **Create new secret key**.
3. Give it a name (e.g. `semantiq-magento`) and click **Create secret key**.
4. Copy the key immediately — it is only shown once.

#### Step 2 — Set billing limits (recommended)

1. In the OpenAI dashboard, go to **Settings → Billing → Usage limits**.
2. Set a **monthly budget** to prevent unexpected charges. Embedding calls are inexpensive ($0.02–$0.13 per million tokens depending on model), but a large catalog with frequent reindexing can add up.

#### Step 3 — Configure SemantiQ

- **Embedding Provider** → `OpenAI`
- **OpenAI API Key** → the key from step 1
- **OpenAI Embedding Model** → `text-embedding-3-small` (recommended) or `text-embedding-3-large`

#### Cost estimate

`text-embedding-3-small` costs $0.02 per million tokens. A typical product description with name is ~100 tokens. A catalog of 10,000 products ≈ 1 million tokens ≈ **$0.02 per full reindex**. Daily incremental updates cost a fraction of this.

---

### 6. AWS Bedrock Embeddings (Titan)

Use this when you prefer to keep data within AWS and do not want to send product information to a third party.

#### Step 1 — Enable model access in AWS Bedrock

1. Open the **AWS Console** → **Amazon Bedrock** → **Model access**.
2. Click **Manage model access**.
3. Enable **Amazon Titan Text Embeddings V2** (and any other models you plan to use).
4. Click **Save changes**. Access is granted immediately for Titan models.

#### Step 2 — Create an IAM User

In **IAM → Users → Create user**:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["bedrock:InvokeModel"],
      "Resource": [
        "arn:aws:bedrock:*::foundation-model/amazon.titan-embed-text-v2:0",
        "arn:aws:bedrock:*::foundation-model/amazon.titan-embed-text-v1",
        "arn:aws:bedrock:*::foundation-model/cohere.embed-english-v3",
        "arn:aws:bedrock:*::foundation-model/cohere.embed-multilingual-v3"
      ]
    }
  ]
}
```

Create an **Access Key** for the user.

#### Step 3 — Install the AWS SDK

```bash
composer require aws/aws-sdk-php
```

#### Step 4 — Configure SemantiQ

- **Embedding Provider** → `AWS Bedrock (Titan)`
- **AWS Region** → e.g. `us-east-1` (must be a region where Bedrock is available)
- **Bedrock Embedding Model ID** → `amazon.titan-embed-text-v2:0` (recommended)
- **AWS Access Key ID** / **AWS Secret Access Key** → from step 2

#### Input limits

| Model | Max input tokens |
|---|---|
| `amazon.titan-embed-text-v2:0` | 8,192 |
| `amazon.titan-embed-text-v1` | 8,192 |
| `cohere.embed-english-v3` | 512 |

If product descriptions exceed the token limit, the text is silently truncated by the API. Configure **Product Attributes to Index** to keep concatenated text under the limit.

---

### 7. Ollama (local)

Ollama runs open-source embedding models completely locally. No internet connection, no API keys, no per-token cost. Suitable for development, on-premise deployments, or privacy-sensitive catalogs.

#### Step 1 — Install Ollama

**macOS:**
```bash
brew install ollama
# or download from https://ollama.com/download
```

**Linux:**
```bash
curl -fsSL https://ollama.com/install.sh | sh
```

**Windows:** Download the installer from [ollama.com/download](https://ollama.com/download).

#### Step 2 — Pull an embedding model

```bash
# Recommended: nomic-embed-text (768 dimensions, ~274 MB)
ollama pull nomic-embed-text

# Higher quality (1024 dimensions, ~670 MB)
ollama pull mxbai-embed-large

# Multilingual (1024 dimensions, ~1.2 GB)
ollama pull bge-m3
```

#### Step 3 — Start the Ollama server

```bash
ollama serve
```

The server starts on `http://localhost:11434` by default. To listen on all interfaces (so other machines can reach it):

```bash
OLLAMA_HOST=0.0.0.0 ollama serve
```

Verify it is running:

```bash
curl http://localhost:11434/api/embeddings \
  -d '{"model": "nomic-embed-text", "prompt": "test"}'
```

The response should contain an `embedding` field with 768 floats.

#### Step 4 — Run as a system service (Linux)

```bash
sudo systemctl enable ollama
sudo systemctl start ollama
```

#### Step 5 — Configure SemantiQ

- **Embedding Provider** → `Ollama (local)`
- **Ollama Base URL** → `http://localhost:11434` (or the server's IP if running remotely)
- **Ollama Embedding Model** → `nomic-embed-text`

#### RAM requirements

| Model | Disk | RAM needed |
|---|---|---|
| `all-minilm` | ~46 MB | ~256 MB |
| `nomic-embed-text` | ~274 MB | ~512 MB |
| `mxbai-embed-large` | ~670 MB | ~1 GB |
| `bge-m3` | ~1.2 GB | ~2 GB |

---

### 8. Anthropic Claude

#### Step 1 — Create an API key

1. Go to [console.anthropic.com](https://console.anthropic.com).
2. Navigate to **API Keys** → **Create Key**.
3. Name it (e.g. `semantiq-magento`) and copy the key.

#### Step 2 — Add credits

Anthropic requires prepaid credits. Go to **Billing** → **Add credits** and add at least $5 to start.

#### Step 3 — Configure SemantiQ

For **LLM / RAG**:
- **LLM Provider** → `Anthropic Claude`
- **Anthropic API Key** → key from step 1
- **Anthropic Model** → `claude-haiku-4-5-20251001` (fastest/cheapest) or `claude-sonnet-4-6` (higher quality)

For **Embedding** (experimental only — not recommended for production):
- **Embedding Provider** → `Anthropic Claude (experimental)`
- **Anthropic API Key** → same key
- **Anthropic Model** → `claude-haiku-4-5-20251001`

**Why embedding via Anthropic is experimental**: Anthropic does not offer a dedicated vector embedding API. SemantiQ works around this by prompting Claude to output a JSON float array, but this approach is slow (~1–3 seconds per product), expensive (chat token pricing vs. embedding pricing), and produces inconsistent vector quality. Use a dedicated embedding provider instead.

---

## Indexing

The SemantiQ indexer converts product and CMS page text into vectors and stores them in the configured vector store.

### Full reindex

Run this after initial setup or after changing the embedding provider or indexed attributes:

```bash
bin/magento indexer:reindex rivicore_semantiq
```

This re-embeds every enabled, visible, non-excluded product across all store views, and every active non-excluded CMS page. For a catalog of 10,000 products, expect 5–30 minutes depending on the embedding provider's latency.

### Incremental reindex (automatic)

SemantiQ subscribes to changes in the following database tables via Magento's MView (materialised view) system:

- `catalog_product_entity` and EAV attribute tables — triggers on product saves
- `cms_page` — triggers on CMS page saves

When a product or CMS page is saved in the admin, it is added to the reindex backlog and processed by the next cron run.

To process the backlog immediately:

```bash
bin/magento indexer:reindex rivicore_semantiq
```

### Indexer status

```bash
bin/magento indexer:status rivicore_semantiq
```

### Check indexed document count

```bash
# MySQL
SELECT entity_type, store_id, COUNT(*) as count
FROM rivicore_semantiq_index
GROUP BY entity_type, store_id;
```

---

## Excluding Entities from the Index

### Excluding a product

1. Open the product in **Catalog → Products → Edit**.
2. Scroll to the **Search Engine Optimization** section.
3. Set **Exclude from SemantiQ Index** to **Yes**.
4. Save the product.

The product is removed from the vector store on the next reindex.

### Excluding a CMS page

1. Open the page in **Content → Pages → Edit**.
2. Scroll to the **Design** section.
3. Set **Exclude from SemantiQ Index** to **Yes**.
4. Save the page.

### Disabling indexing for all products or all CMS pages

Use the global toggles in **General Settings**:
- **Index Products** → `No` — no products will be indexed (or reindexed)
- **Index CMS Pages** → `No` — no CMS pages will be indexed (or reindexed)

---

## RAG Context Block (Frontend)

When **Enable RAG** is set to **Yes**, SemantiQ dispatches a Magento event after each successful search:

```
rivicore_semantiq_rag_context_ready
```

**Event data:**
- `query` — the original search query string
- `context` — the LLM-generated summary paragraph
- `results` — array of `VectorSearchResultInterface` objects

To display the context on the search results page, create an observer in your theme or custom module:

```xml
<!-- etc/events.xml -->
<event name="rivicore_semantiq_rag_context_ready">
    <observer name="my_theme_semantiq_context"
              instance="MyVendor\MyTheme\Observer\SemantiQContextObserver"/>
</event>
```

```php
// Observer/SemantiQContextObserver.php
class SemantiQContextObserver implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $context = $observer->getData('context');
        // Store in registry, session, or cache for the block to read
        $this->registry->register('semantiq_rag_context', $context);
    }
}
```

---

## Recommended Combinations

| Use case | Vector Store | Embedding | LLM (RAG) |
|---|---|---|---|
| Quickest setup, no extra infra | OpenSearch (built-in) | OpenSearch ML Commons | OpenAI |
| Best accuracy, affordable | OpenSearch | OpenAI `text-embedding-3-small` | OpenAI `gpt-4o-mini` |
| All data stays in AWS | Bedrock KB | Bedrock Titan V2 | Bedrock Claude Haiku |
| Fully on-premise, no external calls | OpenSearch | Ollama `nomic-embed-text` | Ollama `llama3` |
| Development / local testing | ChromaDB | Ollama `nomic-embed-text` | Ollama `llama3` |

---

## Troubleshooting

### Search returns no results after enabling

1. Check the indexer ran successfully: `bin/magento indexer:status rivicore_semantiq`
2. Check the index has documents: `SELECT COUNT(*) FROM rivicore_semantiq_index;`
3. Check `var/log/system.log` and `var/log/exception.log` for embedding or vector store errors.
4. Verify the embedding provider credentials are correct by checking `var/log/system.log` for `SemantiQ:` prefixed messages.

### `knn_vector` mapping error on OpenSearch

The k-NN plugin is not installed or enabled. Verify:

```bash
GET /_cat/plugins?v
```

If `opensearch-knn` is missing, install it (self-hosted) or use AWS OpenSearch Service which includes it by default.

### Dimension mismatch error

The **Embedding Dimension** config value does not match the model's actual output. Check the model's documentation or run a test predict call (see [OpenSearch ML Commons](#1-opensearch-ml-commons-built-in-embeddings) step 4). Drop the OpenSearch index and reindex:

```bash
# Drop the vector index
curl -X DELETE http://localhost:9200/semantiq_vectors

# Reindex
bin/magento indexer:reindex rivicore_semantiq
```

### Switching embedding providers

After changing the provider (or model), the existing vectors are incompatible. You must:

1. Set the correct **Embedding Dimension** for the new provider.
2. Disable SemantiQ (`General Settings → Enable SemantiQ → No`).
3. Delete the existing vector index (OpenSearch index, ChromaDB collection, or Bedrock KB documents).
4. Run a full reindex: `bin/magento indexer:reindex rivicore_semantiq`
5. Re-enable SemantiQ.

### AWS SDK not found (Bedrock)

```bash
composer require aws/aws-sdk-php
bin/magento setup:di:compile
bin/magento cache:flush
```

### Ollama connection refused

Ensure Ollama is running (`ollama serve`) and the model is pulled (`ollama pull nomic-embed-text`). If Magento runs in Docker, `localhost` in the config refers to the container — use the host machine's IP or Docker network hostname instead.

### Search falls back to Elasticsearch silently

All errors in the vector search pipeline are caught and logged, and the request falls through to the standard Elasticsearch search. Check `var/log/system.log` for `SemantiQ:` prefixed error lines to identify the root cause.
