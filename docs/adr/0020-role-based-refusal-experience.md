# Role-denied operational questions fall to the canonical grounding refusal — no new string, no detection

When a user without operational depth asks a copy-level question, the answer is the **existing canonical grounding refusal** ("I don't have enough information"), rendered in-stream like every other refusal. The enforcement is **structural, not textual**: for `role=student` requests the operations tool is never registered (ADR 0017) and the corpora are bibliographic-only, so the model has neither live facts nor document ground and the established empty-grounding path fires on its own. No second refusal constant, no ops-intent query sniffing, no stub tools narrating denial text.

**The near-miss boundary closes staff-only too**: a student asking "how many copies does X have?" gets the same refusal even though the count is public on availability cards. Chat's operational surface is one tier-gated whole (CHAT-03); serving public aggregates through a student-side path would add a second delivery mechanism and blur the exact line the phase exists to draw. The cards remain where students see counts.

Librarians only meet this refusal when their tool call fails or matches nothing — already specified by ADR 0017 (errors degrade to the grounding refusal, never guessed numbers).

_Considered (rejected):_ **a dedicated fixed string** ("copy-level details are staff-only") — more informative wording, but it requires detecting operational intent on student turns (the rejected server-side sniffing pattern) or a denial-stub tool whose model-narrated wording is not guaranteed verbatim; **admitting public counts into student answers** — a second facts-delivery path that erases the role boundary; **silently answering from stale corpus data** — violates Grounding outright.
