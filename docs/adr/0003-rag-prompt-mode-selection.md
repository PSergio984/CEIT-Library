# RAG prompt modes: port rag + citations + question; skip summarize

The prompt sources live in WSL at `~/workspace/rag-search-engine/cli/augmented_generation_cli.py` (four modes: rag / summarize / citations / question) plus the `RAGBase` `INSTRUCTIONS` grounding prompt in `D:\ai-eng\llm-zc\rag_helper.py`; all are Webflyx/movie-domain and must be domain-parameterized for the library. We port three modes into the sidecar `app/rag.py`:

- **citations** — required: SEARCH-03 (numbered `[N]` citations) and carries the SEARCH-04 refusal line ("I don't have enough information").
- **question** — the conversational widget tone for CHAT-01.
- **rag** — the baseline one-shot answer mode (RAGBase's `rag()` shape).

**summarize is skipped**: it maps to CHAT-07 ("summarize a paper/policy topic/results"), which REQUIREMENTS.md defers to a future milestone by scoping decision. The citations mode already synthesizes multiple sources with citations, so the gap is deliberate, not accidental.

Both source codebases enforce refusal only through prompt language — a programmatic empty-retrieval branch is a Phase 9 decision tracked on the "Pin citation and grounding rules" ticket.
