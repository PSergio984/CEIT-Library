# LLM provider: OpenRouter via the openai SDK

Phase 9 RAG chat needs a chat-completions provider for the sidecar (`app/rag.py`) and, for streaming, the Laravel side. Research compared OpenRouter, direct OpenAI, Groq, Gemini Flash, and Claude Haiku; the existing `D:\ai-eng\llm-zc` RAG precedent runs Groq (`llama-3.3-70b-versatile`) through `OPENAI_BASE_URL`. We chose **OpenRouter** via the openai SDK because it is a drop-in on both Python and PHP at provider-parity prices (zero markup), adds automatic fallback routing and config-only model swaps, and keeps the proven Groq-hosted Llama models one `base_url` line away.

Default model: `meta-llama/llama-3.3-70b-instruct` (cheapest at $0.10/$0.32 per M tokens, same family as the proven `llama-3.3-70b-versatile` precedent, competent multilingual/Taglish). Alternates: `deepseek/deepseek-chat-v3.1` and, as premium, `openai/gpt-5.4-mini`; `:free` variants are too rate-limited for a multi-user demo (a small credit deposit removes per-day caps).

_Considered (rejected):_ Groq direct (fallback if zero-middleman is wanted; one-line change), Gemini Flash (strong Taglish but not SDK-shared with PHP), Claude Haiku (not chat-cheap-optimized).
