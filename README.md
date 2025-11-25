# RAG Manager Overview
This Symfony-based portal orchestrates how technical teams prepare and oversee Retrieval-Augmented Generation (RAG) content pipelines linked to their GitHub repositories.

## Purpose
- Provide a single **control plane** to connect to a GitHub repo, browse its tree, and flag documents as **approved for ingestion**.
- Keep governance and visibility on what enters the RAG knowledge base, while a dedicated Python service runs ingestion and vectorization.
- Reduce manual oversight by centralizing status tracking, auditability, and handoff between product, ops, and data teams.
- Provide a chat based interface to ask the RAG system through a local or remote LLM.
- Provide a "Knowledge Graph" based on LightRag's to overview the global knowledge tree.

## Scope and Boundaries
- In-scope: GitHub repo linkage, repository tree visualization, document eligibility marking ("ingestable"), and ingestion initiation/monitoring.
- Out-of-scope: The ingestion engine itself (handled by an external Python project: RedwanK/rag-ingestor) and downstream RAG serving or retrieval logic.
- Deployment context: Symfony web app; integrates with GitHub APIs and delegates ingestion calls to the Python backend.

## Target Users and Outcomes
- **Project/operations leads:** ensure only validated content flows into RAG; gain traceability and readiness signals.
- **Tech leads/data owners:** configure repo access, review eligibility, and trigger ingestion without diving into infrastructure.
- **Contributors:** see whether their documentation is cleared for RAG and what remains pending.

## Value Proposition
- **Repository-aware UX:** tree view mirrors GitHub structure to avoid mismatches and missed files.
- **Separation of concerns:** Symfony app handles control and visibility; Python service focuses on ingestion performance.
- **Faster onboarding:** clear cues on what is “OK” to ingest and what still needs review.

## Collaboration Model
1. Link a GitHub repository and sync its structure.
2. Mark eligible documents as “OK” for ingestion within the UI.
3. Dispatch ingestion jobs to the external Python service and monitor completion signals.

# Documentations

- Installation : [docs/technical/00-installation.md](./docs/technical/00-installation.md)

Functional documentations are available under `docs/functional`. I'm writing down here my dev planned features and roadmap.