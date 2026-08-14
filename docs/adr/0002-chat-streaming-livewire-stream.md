# Chat streaming: Livewire native stream(), not Reverb or a raw SSE route

CHAT-01 requires streamed assistant responses in the in-app widget. Options were Livewire 4's built-in `$this->stream()`, a raw Laravel SSE route (`response()->eventStream()`), and Laravel Reverb over WebSockets. We chose **Livewire 4 native streaming**: the widget action POSTs once to the sidecar, the sidecar returns an SSE stream (`POST /chat/stream`, Starlette `StreamingResponse`, `data:` lines ending `[DONE]`, behind the existing X-Sidecar-Token middleware), and the Laravel side reads it via `Http::toStream()`, emitting `$this->stream(content: $chunk, replace: false)` per token chunk.

Vendor-verified: Livewire 4.4 `SupportStreaming` opens the response as `text/event-stream` with `X-Livewire-Stream: true` and flushes JSON per chunk; the JS client consumes via `fetch` + `getReader()`. Reverb adds a websocket server + Echo client for a single-user widget — rejected as overkill; the repo has no broadcasting config. The raw SSE route stays as a fallback if we ever bypass Livewire.

> **Correction (2026-08-14, research-verified):** Laravel 13 has no `Http::toStream()` — the streaming response is consumed via `Response::resource()` (read with `fgets`/streaming reads). ADR 0004 D-11 is amended accordingly; the Livewire `$this->stream()` emission is unchanged.
