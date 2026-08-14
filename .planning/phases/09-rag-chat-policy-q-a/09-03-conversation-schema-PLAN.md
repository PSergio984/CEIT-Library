---
phase: 09-rag-chat-policy-q-a
plan: 03
type: execute
wave: 1
depends_on: []
files_modified:
  - database/migrations/2026_08_14_000001_create_ai_conversations_table.php
  - database/migrations/2026_08_14_000002_create_ai_messages_table.php
  - app/Models/Conversation.php
  - app/Models/Message.php
  - database/factories/ConversationFactory.php
  - database/factories/MessageFactory.php
  - tests/Feature/ConversationMessageTest.php
autonomous: true
requirements: [CHAT-02]
must_haves:
  truths:
    - "ai_conversations/ai_messages migrations match ADR 0005 exactly: FK cascade on both user_id and conversation_id, title string(120) nullable, role enum user|assistant, content text, citations json nullable, indexes (user_id, updated_at) and (conversation_id, id) (D-13/D-14)"
    - "Conversation::makeTitle() truncates the first user message to 120 chars with the 'New conversation' fallback (D-18)"
    - "Creating a Message touches() its Conversation so the list orders by updated_at desc (D-16); deletes cascade; no soft deletes (D-17)"
  artifacts:
    - path: database/migrations/2026_08_14_000001_create_ai_conversations_table.php
      provides: "ai_conversations table (user-owned, title 120, (user_id, updated_at) index)"
      contains: "ai_conversations"
    - path: database/migrations/2026_08_14_000002_create_ai_messages_table.php
      provides: "ai_messages table (conversation-owned, role enum, citations json, (conversation_id, id) index)"
      contains: "ai_messages"
    - path: app/Models/Conversation.php
      provides: "Conversation model with messages()/user() relations and makeTitle() static"
      contains: "makeTitle"
    - path: app/Models/Message.php
      provides: "Message model with citations array cast and saved→touch conversation hook"
      contains: "citations"
  key_links:
    - from: app/Models/Message.php
      to: app/Models/Conversation.php
      via: "Message::conversation() BelongsTo; saved hook calls conversation->touch()"
      pattern: "touch"
---

<objective>
Create the CHAT-02 persistence layer per ADR 0005: two migrations (`ai_conversations`, `ai_messages`) following the repo's anonymous-class migration convention, the `Conversation` and `Message` models with the repo's bare-model house style (fillable, `casts()`, typed relations), factories, and `ConversationMessageTest` covering cascade, ordering, touch, and auto-title behavior.

Purpose: The widget (09-04/09-05) persists every turn into these tables and re-renders history from them. This plan delivers the schema + model contracts they compile against.

Output: Migrations, models, factories, and a green `ConversationMessageTest` suite.

Note (R10, verified): do NOT touch `RuleHeader::ruleRegulations()` (its `orderBy('order')` references a non-existent column) anywhere in this phase — the fix is explicitly deferred; nothing in this plan needs that relation.

Commit discipline: each task is one focused commit.
</objective>

<execution_context>
@$HOME/.codex/get-shit-done/workflows/execute-plan.md
@$HOME/.codex/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-rag-chat-policy-q-a/09-RESEARCH.md
@.planning/phases/09-rag-chat-policy-q-a/09-CONTEXT.md
@.planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md
@.planning/phases/09-rag-chat-policy-q-a/09-VALIDATION.md
@docs/adr/0005-conversation-history-schema.md
@database/migrations/2026_06_15_234457_create_notification_preferences_table.php
@app/Models/NotificationPreference.php
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan introduces user-owned chat data at rest.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Cross-user access to conversation rows | HIGH | `ai_conversations.user_id` is NOT NULL FK→users with cascade; every load is auth-scoped at the query layer (enforced in the widget plan 09-04, which owns all reads). Model tests here verify the FK/cascade contract; the widget test suite proves user isolation. |
| T-02 Message content PII at rest | MED | Out of Phase 9 scope by decision — Phase 14 (OPS-03) owns PII sanitization. This plan adds no logging of content and no TTL (D-17). |
| T-03 Orphan rows from partial writes | MED | FK `conversation_id` cascade + `user_id` cascade; hard delete only, no soft deletes (D-17). Verified by cascade tests. |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: Migration — create_ai_conversations_table</name>
  <files>database/migrations/2026_08_14_000001_create_ai_conversations_table.php</files>
  <read_first>database/migrations/2026_06_15_234457_create_notification_preferences_table.php (verbatim style template), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.1 ANALOG A)</read_first>
  <action>
  - Create the anonymous-class migration `2026_08_14_000001_create_ai_conversations_table.php` with `Schema::create('ai_conversations', ...)`.
  - Columns in order: `$table->id()`; `$table->foreignId('user_id')->constrained()->onDelete('cascade')`; `$table->string('title', 120)->nullable()` with inline comment `// auto-title from first user message (D-18)`; `$table->timestamps()`.
  - At the END of the closure: `$table->index(['user_id', 'updated_at'])` (NOT unique — multiple conversations per user).
  - `down()`: `Schema::dropIfExists('ai_conversations')`.
  </action>
  <verify>php artisan migrate:fresh --env=testing</verify>
  <acceptance_criteria>
  - File exists at the locked path with `Schema::create('ai_conversations', ...)`
  - Contains `$table->string('title', 120)->nullable()`, `->constrained()->onDelete('cascade')` on `user_id`, and `$table->index(['user_id', 'updated_at'])`
  - `php artisan migrate:fresh --env=testing` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: Migration — create_ai_messages_table</name>
  <files>database/migrations/2026_08_14_000002_create_ai_messages_table.php</files>
  <read_first>database/migrations/2026_05_21_000001_create_push_subscriptions_table.php (FK-child style template), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.1 ANALOG B + CHANGE)</read_first>
  <action>
  - Create the anonymous-class migration `2026_08_14_000002_create_ai_messages_table.php` with `Schema::create('ai_messages', ...)`.
  - Columns in order: `$table->id()`; `$table->foreignId('conversation_id')->constrained('ai_conversations')->onDelete('cascade')`; `$table->enum('role', ['user', 'assistant'])` with inline comment `// user|assistant`; `$table->text('content')`; `$table->json('citations')->nullable()` with inline comment `// [{n,id,corpus,title,url,catalog_code}] (assistant rows)`; `$table->timestamps()`.
  - At the END of the closure: `$table->index(['conversation_id', 'id'])`.
  - `down()`: `Schema::dropIfExists('ai_messages')`.
  - No soft-delete columns, no unique constraints (D-17).
  </action>
  <verify>php artisan migrate:fresh --env=testing</verify>
  <acceptance_criteria>
  - File exists with `Schema::create('ai_messages', ...)`; `conversation_id` is `foreignId(...)->constrained('ai_conversations')->onDelete('cascade')`
  - Contains `$table->enum('role', ['user', 'assistant'])`, `$table->text('content')`, `$table->json('citations')->nullable()`, and `$table->index(['conversation_id', 'id'])`
  - `php artisan migrate:fresh --env=testing` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: Conversation + Message models</name>
  <files>app/Models/Conversation.php, app/Models/Message.php</files>
  <read_first>app/Models/NotificationPreference.php (house style: fillable + casts() method + typed relations), app/Models/PushSubscription.php, .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.2)</read_first>
  <action>
  - `app/Models/Conversation.php` (table `ai_conversations` inferred from class name — no `$table` override): `protected $fillable = ['user_id', 'title'];`; relations `user(): BelongsTo` → `User::class` and `messages(): HasMany` → `Message::class` with `->orderBy('id')` (flat ordering, D-16); static `makeTitle(string $content): string` returning `mb_substr(trim($content), 0, 120)` with the literal fallback `'New conversation'` when the truncated title is blank.
  - `app/Models/Message.php` (table `ai_messages` inferred): `protected $fillable = ['conversation_id', 'role', 'content', 'citations'];`; `casts()` returning `['citations' => 'array']`; relation `conversation(): BelongsTo` → `Conversation::class`; `booted()` hook: `static::saved(fn (Message $message) => $message->conversation?->touch());` (null-safe — model tests may build messages without a persisted conversation).
  - No `user()` relation on Message (single owner via conversation, D-15); no soft deletes (D-17).
  </action>
  <verify>php -l app/Models/Conversation.php; php -l app/Models/Message.php</verify>
  <acceptance_criteria>
  - `Conversation` has `$fillable = ['user_id', 'title']`, `messages()` ordered by `id`, `user()`, and `makeTitle()` containing `mb_substr(trim($content), 0, 120)` and the literal `'New conversation'`
  - `Message` has `casts()` with `'citations' => 'array'`, `conversation()`, and a `booted()` saved hook calling `->touch()` on the conversation
  - `php -l` exits 0 on both files
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: Factories</name>
  <files>database/factories/ConversationFactory.php, database/factories/MessageFactory.php</files>
  <read_first>database/factories/RuleRegulationFactory.php (parent-FK factory style), database/factories/UserFactory.php, .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.3)</read_first>
  <action>
  - `database/factories/ConversationFactory.php`: `@extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>` docblock; `definition()` → `['user_id' => User::factory(), 'title' => $this->faker->sentence(5)]`.
  - `database/factories/MessageFactory.php`: `definition()` → `['conversation_id' => Conversation::factory(), 'role' => 'assistant', 'content' => $this->faker->paragraph(2), 'citations' => null]`.
  </action>
  <verify>php artisan tinker --execute="dump(\App\Models\Conversation::factory()->make()->toArray(), \App\Models\Message::factory()->make()->toArray())"</verify>
  <acceptance_criteria>
  - Both factory files exist with `definition(): array` returning the locked keys
  - `MessageFactory` references `Conversation::factory()` for `conversation_id`; `ConversationFactory` references `User::factory()` for `user_id`
  - The tinker smoke command prints both factory shapes without error
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 5: ConversationMessageTest feature tests</name>
  <files>tests/Feature/ConversationMessageTest.php</files>
  <read_first>tests/Feature/SidecarLiveTest.php (class/test attribute style), tests/Traits/CreatesTestDatabase.php (DB trait convention), app/Models/Conversation.php, app/Models/Message.php</read_first>
  <action>
  - Create `tests/Feature/ConversationMessageTest.php` (PHPUnit-attribute style, `use RefreshDatabase` per repo test convention) covering:
    1. `it_cascades_messages_when_conversation_deleted` — delete a Conversation → `assertDatabaseMissing('ai_messages', ...)` for its messages.
    2. `it_cascades_conversations_and_messages_when_user_deleted` — delete the User → both tables empty for that user.
    3. `it_orders_messages_by_id_asc` — create 3 messages (id 1..3, e.g. via `->create()` in sequence) → `$conversation->messages()->pluck('id')` equals `[1, 2, 3]`.
    4. `it_touches_conversation_when_message_saved` — capture `$conversation->updated_at`, `usleep(1_100_000)` (or manually set timestamps via `Model::withoutTimestamps` on the conversation), create a Message → `$conversation->fresh()->updated_at` is newer.
    5. `it_truncates_auto_title_to_120_chars` — `Conversation::makeTitle(str_repeat('a', 150))` returns a 120-char string.
    6. `it_falls_back_to_new_conversation_title` — `Conversation::makeTitle('   ')` returns the literal `'New conversation'`.
    7. `it_casts_citations_to_array_and_defaults_null` — Message with `citations => null` → `null`; with a payload array → same array back.
    8. `it_creates_rows_via_factories` — `Conversation::factory()->has(Message::factory()->count(2))->create()` lands 1 conversation + 2 messages for the acting user.
  </action>
  <verify>php artisan test --filter=ConversationMessageTest</verify>
  <acceptance_criteria>
  - All 8 tests exist and pass: `php artisan test --filter=ConversationMessageTest` exits 0
  - The file uses `RefreshDatabase` and contains `makeTitle` assertions for both the 120-char truncation and the `'New conversation'` fallback
  - No test in this file reads from `RuleHeader` or `RuleRegulation` (R10 untouched)
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `php artisan migrate:fresh --env=testing` — exits 0 with both new tables
- [ ] `php artisan test --filter=ConversationMessageTest` — 8 passing
- [ ] `php artisan test` — full Laravel suite stays green (523 passed / 3 skipped baseline)
- [ ] `php artisan migrate:status` — both new migrations listed as Ran
</verification>

<success_criteria>
- All 5 tasks complete
- CHAT-02's persistence contract (schema, ownership, ordering, touch, auto-title) exists and is unit-proven before the widget consumes it
</success_criteria>

<output>
After completion, create `.planning/phases/09-rag-chat-policy-q-a/09-03-SUMMARY.md`
</output>
