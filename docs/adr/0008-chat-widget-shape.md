# Chat widget shape: floating launcher + drawer, on every authenticated page

The Phase 9 widget (CHAT-01) is a **floating launcher button that opens a slide-in drawer** — variant A of the throwaway prototype (branch `prototype/chat-widget`, issue "Prototype the chat widget"). The human picked it over the docked side panel and the bottom sheet; the drawer's bubble layout, typing-dots streaming indicator, citation chips, and demo-annotated states carry into the spec.

- **Launcher** — a circular primary FAB fixed bottom-right (`btn btn-circle btn-primary`), present on **every authenticated page** (the prototype's two fake pages demonstrated the widget persisting across navigation; ADR 0007 gates it behind the existing `['auth', 'verified']` middleware, so the launcher renders only for authenticated users). Full-width on mobile.
- **Drawer** — slides in from the right, `w-full sm:w-96`, with a header (title + New conversation), the message list, and a pinned input row. Closed by default.
- **Bubbles** — user right-aligned primary, assistant left-aligned soft; assistant answers stream into the bubble via Livewire `$this->stream()` (ADR 0002) with a typing-dots indicator while streaming.
- **Citations** — ADR 0006 chips render under the assistant bubble (catalog link chips with `catalog_code`, policy non-link chips), followed by the numbered Sources list.
- **Refusal** — the canonical string renders in a normal bubble with a demo-annotation line ("no sources matched").
- **Mid-stream failure** — an inline amber banner inside the failed bubble shows the `provider_error` message (ADR 0004) with a Retry button that re-streams the turn (prototype showed replacing the failed turn, not duplicating).
- **New conversation** — a header button resets the drawer to its empty state; the conversation-list UI flow (CHAT-02 viewability) is a separate decision (ticket "Pin the conversation-list UI flow").

_Considered (rejected):_ variant B docked side panel (permanent screen real estate for a single surface); variant C bottom sheet (full-screen takeover on mobile judged heavier than a drawer); a conversation-list inside the drawer (deferred to the conversation-list UI ticket).

Prototype reference: branch `prototype/chat-widget` at commit `395244dc` (throwaway — delete after Phase 9 planning consumes it); screenshots in `.planning/prototype/` on that branch.
