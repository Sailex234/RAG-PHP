# RAG-PHP

A Retrieval-Augmented Generation (RAG) pipeline built from scratch in PHP — no LangChain, no framework, no managed vector-DB service. Every layer (chunking, embeddings, vector search, prompt assembly, grounding) is implemented by hand, on top of a locally-hosted LLM (Ollama) and Postgres with `pgvector`.

It answers questions **strictly from your own documents**, citing the exact source fragment for every claim, and explicitly refuses to answer when the information isn't in the corpus — instead of guessing.

```
$ php bin/ask.php "¿Cuándo abre la matrícula 2026?"
El período de inscripción al ciclo lectivo 2026 se abre el 3 de marzo de 2026. [FRAGMENTO 5]
Fuentes: [FRAGMENTO 5]

$ php bin/ask.php "¿Cuál es la capital de Francia?"
No encuentro esa información.
[Confianza baja en esta respuesta]
```

## Why this exists

This project was built to understand RAG at the implementation level: how text actually gets split into chunks, what an embedding vector represents, why cosine similarity is the right comparison, how HNSW indexing makes similarity search fast at scale, and how to constrain an LLM to only answer from retrieved context (anti-hallucination prompting). Everything runs 100% locally — no API keys, no cloud costs.

## How it works

**Ingestion** (`bin/ingest.php`, run once per document)

```
samples/txt/*.txt
    → Chunker            splits text into ~800-char chunks with 100-char overlap
    → EmbeddingClient     Ollama /api/embed (nomic-embed-text) → 768-dim vector per chunk
    → VectorStore::insert()  stored in Postgres (pgvector, HNSW index)
```

**Query** (`bin/ask.php`, run per question)

```
question
    → EmbeddingClient     question → 768-dim vector
    → VectorStore::search()  top-5 most similar chunks (cosine similarity via HNSW)
    → RagPipeline          builds a prompt: retrieved context + anti-hallucination system prompt
    → OllamaClient         llama3.1:8b, constrained to schemas/respuesta_rag.json (format + temperature: 0)
    → RagResponseValidator  validates the JSON against the schema (safety net behind constrained decoding)
    → structured array     {respuesta, citas[], confianza_alta, advertencia} → printed by bin/ask.php
```

If no relevant chunk is found, the pipeline short-circuits before ever calling the LLM and returns "No encuentro esa información." directly.

### Anti-hallucination prompt

```
Respondé SOLO con el CONTEXTO proporcionado. Reglas:
1) El campo "respuesta" debe citar cada afirmación con [FRAGMENTO #ID].
2) El campo "citas" debe listar los IDs de FRAGMENTO realmente usados en la respuesta.
3) "confianza_alta" es true solo si el contexto respalda la respuesta con claridad, false si es parcial o dudosa.
4) Si la información no está en el contexto, poné en "respuesta" exactamente "No encuentro esa información.",
   "citas" vacío y "confianza_alta" false.
5) "advertencia" es un texto breve si hay dudas o información incompleta, o null si no aplica.
6) NO inventes. NO uses conocimiento previo al contexto.
```

### Structured output (JSON Schema)

`RagPipeline::ask()` returns a fixed-shape array, not raw text:

```php
[
    'respuesta'      => string,   // answer text, citing [FRAGMENTO #ID]
    'citas'          => int[],    // FRAGMENTO IDs actually used
    'confianza_alta' => bool,     // true if the context clearly supports the answer
    'advertencia'    => ?string,  // short caveat if uncertain, null otherwise
]
```

The shape is enforced two ways: Ollama's native constrained decoding (`format` set to the full JSON Schema, `temperature: 0`) forces the model to only sample tokens that produce valid JSON, and `RagResponseValidator` re-validates the decoded array against `schemas/respuesta_rag.json` (`opis/json-schema`) as a cheap safety net — it catches shape drift even if the model or Ollama version changes, though it can't verify that `citas` semantically matches what's cited in `respuesta`.

## Stack

| Component | Role |
|---|---|
| PHP 8.2 | Core language — typed, `strict_types`, constructor-promoted readonly DI |
| [Ollama](https://ollama.com) | Local model server — `llama3.1:8b` for generation, `nomic-embed-text` for embeddings |
| PostgreSQL 17 + [pgvector](https://github.com/pgvector/pgvector) | Vector storage and cosine-similarity search via an HNSW index |
| Guzzle | HTTP client for the Ollama API |
| PHPUnit / PHPStan | Unit testing and static analysis |
| Docker Compose | Local orchestration (DB + Ollama) |

## Getting started

**Prerequisites:** Docker, PHP 8.2+, Composer.

```bash
git clone https://github.com/Sailex234/RAG-PHP.git
cd RAG-PHP

docker compose up -d           # starts Postgres+pgvector and Ollama
docker exec rag-ollama ollama pull llama3.1:8b
docker exec rag-ollama ollama pull nomic-embed-text

composer install
cp .env.example .env           # defaults already match docker-compose.yml
```

Ingest the sample corpus and ask a question:

```bash
php bin/ingest.php
# ✓ guarani-prueba indexado (9 chunks)

php bin/ask.php "¿Cuándo abre la matrícula 2026?"
```

Drop your own `.txt` files into `samples/txt/` and re-run `bin/ingest.php` to index them.

## Testing

```bash
vendor/bin/phpunit tests/
vendor/bin/phpstan analyse src --level=5
```

## Project structure

```
rag-php/
├── bin/
│   ├── ingest.php        # CLI: document ingestion (txt → chunks → embeddings → pgvector)
│   └── ask.php            # CLI: RAG query (question → embedding → search → LLM → structured answer)
├── infra/
│   └── init.sql            # Postgres schema: documents table, HNSW index
├── samples/txt/            # Sample corpus for the ingestion demo
├── schemas/
│   └── respuesta_rag.json  # JSON Schema for the RAG response shape
├── src/
│   ├── Chunker.php         # Splits text into overlapping chunks
│   ├── EmbeddingClient.php  # Batch embeddings via Ollama /api/embed
│   ├── OllamaClient.php    # Chat completions via Ollama /api/chat, supports format + temperature
│   ├── RagPipeline.php     # Orchestrates the full query flow, returns validated structured JSON
│   ├── RagResponseValidator.php  # Validates the LLM's JSON against schemas/respuesta_rag.json
│   └── VectorStore.php     # pgvector insert + cosine-similarity search
├── tests/
│   ├── ChunkerTest.php              # Unit tests for the chunking algorithm
│   └── RagResponseValidatorTest.php # Tests for the schema validator (valid / invalid responses)
└── docker-compose.yml       # Postgres+pgvector and Ollama services
```

## Design notes

- **HNSW over IVFFlat**: prioritizes fast queries over fast inserts — the right trade-off for RAG, where you ingest once and query many times.
- **Chunk size (800 chars) and overlap (100 chars)** are configurable parameters (`Chunker($size, $overlap)`), not fixed RAG constants — tuned here for a demo corpus of policy-manual-style text.
- **`strict_types` + constructor-promoted `readonly` properties** throughout `src/` for explicit dependency injection and fewer implicit type coercions.

## Roadmap

`evals/` and `prompts/` are scaffolded but not yet built out — planned next steps are automated retrieval/answer evaluation and externalized prompt templates. JSON-schema-validated structured output (`schemas/`) is already implemented — see [Structured output](#structured-output-json-schema) above.

## License

MIT — see [LICENSE](LICENSE).
