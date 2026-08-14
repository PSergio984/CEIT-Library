# Conversation history schema: two tables, single owner, viewability-only

Phase 9 persists chat history (CHAT-02) in two Laravel tables with hard-delete cascade, matching the repo's existing conventions (`foreignId()->constrained()->cascadeOnDelete()`, snake_case table names, enum columns). Models are named `Conversation` and `Message` (CONTEXT.md ubiquitous language) mapped to `ai_` tables to keep them clear of future domain tables.

`ai_conversations` — `id`, `user_id` (FK→users, cascade delete, NOT NULL), `title` (string 120, nullable), `timestamps()`. Index `(user_id, updated_at)`.

`ai_messages` — `id`, `conversation_id` (FK→ai_conversations, cascade delete), `role` (enum `user`/`assistant` — only two roles exist, mirroring the `account_status` enum pattern), `content` (text), `citations` (JSON, nullable — the source list behind the `[N]` markers, carried only on assistant rows so the CHAT-02 history view re-renders citations), `timestamps()`. Index `(conversation_id, id)` for ordered loading.

Ownership is **single-owner, auth-scoped**: every Conversation belongs to exactly one User (`user_id` required — guests get no conversations); all loads are scoped to `auth()->id()` at the query layer. Ordering is flat — no threading (CONTEXT.md defines a Message as one exchange, not a chain): messages load by `(conversation_id, id)` ascending (id = insertion order); the conversation list sorts by `updated_at` descending, maintained by `touch()` on new Messages — no denormalized activity column at this scale.

Deletion is **hard delete + cascade** — deleting a Conversation removes its Messages; the repo has no soft-delete precedent and none is introduced (already satisfies user-requested erasure). No retention TTL in Phase 9: conversations persist indefinitely; OPS-03 (PII sanitization/anonymization, Phase 14) will add its own columns/job in a later migration without conflict — nothing here blocks it.

Titles are **auto-generated, not user-edited** in Phase 9: `title` is set at creation from the first user Message, truncated to 120 chars (fallback "New conversation"); the nullable column leaves room for a future rename/empty-state path. No rename UI exists in Phase 9.

History loading is **viewability-only**: opening a Conversation renders its Messages as bubbles; the sidecar never receives them — ADR 0004 locks the request as single-turn, and CHAT-02 requires persistence, not model memory. No truncation/rollup logic ships in Phase 9; the reserved `history: [{role, content}]` field (ADR 0004) would be populated from these rows if a future milestone adds multi-turn. Request metadata (mode/corpus/top_k/latency) is not stored per message — nothing in Phase 9 consumes it.

_Considered (rejected):_ a normalized `ai_message_citations` table (JSON column is sufficient and phase-14-compatible; the source list is display data, not relational); nullable `user_id` guest conversations (no guest chat in Phase 9); `SoftDeletes` (no repo precedent; hard delete already erases user data); denormalized `last_activity_at` (touch() is free and sufficient); storing per-message request metadata (no consumer in Phase 9).
