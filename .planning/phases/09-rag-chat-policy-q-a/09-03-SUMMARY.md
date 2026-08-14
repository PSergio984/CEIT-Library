---
phase: 09-rag-chat-policy-q-a
plan: 03
subsystem: database
tags: [laravel, migrations, eloquent, chat, schema, factories, phpunit]

# Dependency graph
requires:
  - phase: 09-01
    provides: sidecar chat endpoint contract research and AiService seams
  - phase: 09-02
    provides: AiService chatStream()/chatStreamEvents() SSE client the widget will consume
provides:
  - ai_conversations/ai_messages schema per ADR 0005 (cascade FKs, enums, composite indexes)
  - Conversation/Message models with makeTitle(), citations array cast, touch-on-save ordering
  - Conversation/Message factories for the widget tests (09-04/09-05)
  - ConversationMessageTest proving the CHAT-02 persistence contract
affects: [09-04-widget, 09-05-conversation-list, widget test suites, Phase 14 OPS-03 sanitization migration]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Anonymous-class migrations with foreignId()->constrained()->onDelete('cascade'), inline // comments, indexes declared at closure end, dropIfExists in down()"
    - "Bare house-style models: $fillable + casts() + typed relations + HasFactory with @use docblock"
    - "Message::saved() hook touches the parent Conversation to keep the list sorted by updated_at desc"

key-files:
  created:
    - database/migrations/2026_08_14_000001_create_ai_conversations_table.php
    - database/migrations/2026_08_14_000002_create_ai_messages_table.php
    - app/Models/Conversation.php
    - app/Models/Message.php
    - database/factories/ConversationFactory.php
    - database/factories/MessageFactory.php
    - tests/Feature/ConversationMessageTest.php
  modified: []

key-decisions:
  - "Models require explicit $table = 'ai_conversations'/'ai_messages' overrides — Eloquent pluralizes class names (Conversation → conversations), it does not infer the ai_ prefix; the plan's 'no $table override needed' assumption was corrected to honor D-15/ADR 0005"
  - "Models carry the HasFactory trait (house pattern) so factory() works for widget tests"

patterns-established:
  - "Persisted-chat schema pattern: child table keyed by FK to parent ai_ table, cascade on both user and conversation, no soft deletes"
  - "Auto-title contract: Conversation::makeTitle() static, mb_substr(trim(), 0, 120), literal 'New conversation' fallback"

requirements-completed: [CHAT-02]

# Metrics
duration: 35min
completed: 2026-08-14
---

# Phase 9 Plan 3: Conversation Schema Summary

**CHAT-02 persistence layer per ADR 0005: ai_conversations/ai_messages migrations, Conversation/Message models with auto-title, citations cast and touch-on-save ordering, factories, and an 8-test ConversationMessageTest suite**

## Performance

- **Duration:** 35 min
- **Started:** 2026-08-14T13:40:00Z
- **Completed:** 2026-08-14T14:15:00Z
- **Tasks:** 5
- **Files modified:** 7 created

## Accomplishments
- Two anonymous-class migrations matching the repo convention: `ai_conversations` (user_id FK→users cascade, title string(120) nullable, index (user_id, updated_at)) and `ai_messages` (conversation_id FK→ai_conversations cascade, role enum user|assistant, content text, citations json nullable, index (conversation_id, id)) — exactly D-13/D-14.
- `Conversation` model: `user()` BelongsTo, `messages()` HasMany ordered by `id`, static `makeTitle()` truncating the first user message to 120 chars with the literal `'New conversation'` fallback (D-18).
- `Message` model: `citations` array cast, `conversation()` BelongsTo, `saved()` hook touching the parent Conversation so the list orders by `updated_at` desc (D-16); hard delete + cascade only, no soft deletes (D-17).
- `ConversationMessageTest` (8 tests, 14 assertions) proves cascade on conversation and user delete, flat id-asc ordering, touch behavior, title truncation/fallback, citations cast/null default, and factory round-trips.
- Full Laravel suite stays green: 538 passed / 3 skipped (baseline 530 + 8 new).

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration — create_ai_conversations_table** - `7e934d19` (feat)
2. **Task 2: Migration — create_ai_messages_table** - `d29ea815` (feat)
3. **Task 3: Conversation + Message models** - `16948249` (feat)
4. **Task 4: Factories** - `22e39794` (feat)
5. **Task 5: ConversationMessageTest feature tests** - `aaf03c7e` (test)

## Files Created/Modified
- `database/migrations/2026_08_14_000001_create_ai_conversations_table.php` - ai_conversations schema (D-13): user-owned, cascade, title 120 nullable, (user_id, updated_at) index
- `database/migrations/2026_08_14_000002_create_ai_messages_table.php` - ai_messages schema (D-14): conversation-owned, role enum, citations json, (conversation_id, id) index
- `app/Models/Conversation.php` - fillable user_id/title, user()/messages() relations, makeTitle() static, table override to ai_conversations
- `app/Models/Message.php` - fillable, citations array cast, conversation() relation, saved→touch() hook, table override to ai_messages
- `database/factories/ConversationFactory.php` - user_id via User::factory(), faker title
- `database/factories/MessageFactory.php` - conversation_id via Conversation::factory(), role assistant, citations null
- `tests/Feature/ConversationMessageTest.php` - 8 tests covering cascade, ordering, touch, auto-title, citations cast, factories

## Decisions Made
- **$table overrides on both models (deviation from plan text):** the plan and PATTERNS.md asserted Eloquent infers `ai_conversations`/`ai_messages` from class names with no `$table` override needed. That is incorrect — Eloquent pluralizes class names (`Conversation` → `conversations`). The explicit `protected $table = 'ai_conversations'/'ai_messages'` is required to honor D-15/ADR 0005 (models mapped to the ai_ tables). Without it, the persistence layer silently targets non-existent `conversations`/`messages` tables.
- **HasFactory trait added to both models** per the house pattern (`@use HasFactory<...>` like User/RuleRegulation) — required for `Conversation::factory()`/`Message::factory()` used by this plan's factories and the widget tests in 09-04/09-05.
- Otherwise followed the plan as specified.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Explicit $table overrides required for ai_ table mapping**
- **Found during:** Task 4 (factories smoke command)
- **Issue:** The plan's Task 3 spec asserted no `$table` override is needed because "Eloquent infers tables ai_conversations/ai_messages from class names". Eloquent actually infers `conversations`/`messages`; the Task 4 tinker smoke failed with `SQLSTATE[42P01]: relation "conversations" does not exist`, confirming the models would read/write the wrong tables and violate D-15.
- **Fix:** Added `protected $table = 'ai_conversations';` to Conversation and `protected $table = 'ai_messages';` to Message.
- **Files modified:** app/Models/Conversation.php, app/Models/Message.php
- **Verification:** Factory smoke command prints both shapes; all 8 ConversationMessageTest tests pass against the real ai_ tables.
- **Committed in:** `16948249` (models) and `22e39794` (factory task commit covering the fixes)

**2. [Rule 2 - Missing Critical] HasFactory trait missing on models**
- **Found during:** Task 4 (factories smoke command)
- **Issue:** `BadMethodCallException: Call to undefined method App\Models\Conversation::factory()` — models lacked the HasFactory trait, breaking the plan's factory pattern and the Task 5 `it_creates_rows_via_factories` test.
- **Fix:** Added `use HasFactory` with the house `@use HasFactory<Factory>` docblock on both models.
- **Files modified:** app/Models/Conversation.php, app/Models/Message.php
- **Verification:** `Conversation::factory()->make()` / `Message::factory()->make()` succeed; factory test passes.
- **Committed in:** `22e39794` (Task 4 commit)

**3. [Rule 1 - Bug] Tinker smoke command ran against the dev pgsql database**
- **Found during:** Task 4 (verify step)
- **Issue:** `php artisan tinker` without `--env=testing` boots the local dev connection (pgsql `librarydb`), which has no ai_ tables, so the plan's verbatim smoke command failed and would have polluted the dev DB with rows.
- **Fix:** Ran the smoke against a throwaway sqlite file (migrated + tinker with DB env overrides) — same assertion semantics, zero dev-DB side effects.
- **Verification:** Smoke printed both factory shapes without error.
- **Committed in:** `22e39794` (Task 4 commit)

---

**Total deviations:** 3 auto-fixed (2 missing critical, 1 bug)
**Impact on plan:** All fixes were necessary for the persistence layer to target the ADR 0005 tables and for factories to function. No scope creep; R10 untouched.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- CHAT-02 persistence contract is unit-proven: schema (D-13/D-14), ownership/cascade (D-17), ordering (D-16), touch, auto-title (D-18) — the widget plans 09-04/09-05 can compile against `Conversation`/`Message` and the factories with confidence.
- No blockers. R10 note respected: no code path in this plan touches `RuleHeader::ruleRegulations()`.
- Reminder for widget plans: auth-scoped loads (`Conversation::where('user_id', auth()->id())`) and lazy conversation creation on first message (D-32) are widget responsibilities, not model scope.

---
*Phase: 09-rag-chat-policy-q-a*
*Completed: 2026-08-14*
