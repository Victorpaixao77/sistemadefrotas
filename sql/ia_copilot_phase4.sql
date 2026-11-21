-- Centro de Inteligência — Fase 4 (RAG vetorial)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/ia_copilot_phase4.sql

ALTER TABLE ia_documento_chunks
    ADD COLUMN embedding JSON NULL COMMENT 'Vetor de embedding (JSON array)' AFTER chunk_text;
