-- infra/init.sql
-- Se ejecuta automáticamente al crear el contenedor por primera vez

-- 1. Habilitar pgvector
CREATE EXTENSION IF NOT EXISTS vector;

-- 2. Habilitar búsqueda full-text (para hybrid search más adelante)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- 3. Tabla principal de documentos
CREATE TABLE documents (
    id         BIGSERIAL PRIMARY KEY,
    source     TEXT        NOT NULL,
    chunk_idx  INT         NOT NULL,
    content    TEXT        NOT NULL,
    metadata   JSONB       DEFAULT '{}'::jsonb,
    embedding  vector(768),
    created_at TIMESTAMPTZ DEFAULT now()
);

-- 4. Índice HNSW para búsqueda por similitud coseno
-- HNSW es más rápido para consultas, IVFFlat es más rápido para inserción
CREATE INDEX documents_embedding_hnsw
    ON documents USING hnsw (embedding vector_cosine_ops);

-- 5. Índices auxiliares
CREATE INDEX ON documents (source);
CREATE INDEX ON documents USING gin (metadata);
