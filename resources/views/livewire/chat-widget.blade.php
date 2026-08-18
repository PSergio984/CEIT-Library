{{-- Floating launcher FAB --}}
<div>
    @if (! $open)
        <button type="button" wire:click="toggle" class="fixed bottom-6 right-6 z-40 btn btn-circle btn-primary shadow-2xl w-16 h-16" title="Open chat">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-8 h-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
        </button>
    @endif

{{-- Drawer --}}
<div class="fixed inset-y-0 right-0 z-40 w-full sm:w-96 bg-base-100 border-l border-base-300 shadow-2xl flex flex-col transition-transform duration-300 {{ $open ? '' : 'translate-x-full' }}">
    <div class="flex items-center justify-between px-4 py-3 border-b border-base-200 bg-primary text-primary-content">
        <div>
            <div class="font-semibold leading-tight">CEIT Library Assistant</div>
            <div class="text-xs opacity-80">Grounded in catalog + rulebook</div>
        </div>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="newConversation" class="btn btn-ghost btn-xs text-primary-content" title="New conversation">New</button>
            <button type="button" wire:click="toggle" class="btn btn-ghost btn-circle btn-xs text-primary-content">✕</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-4" x-data="{}" x-init="
        const el = $el;
        const scroll = () => el.scrollTop = el.scrollHeight;
        scroll();
        const obs = new MutationObserver(scroll);
        obs.observe(el, { childList: true, subtree: true, characterData: true });
    ">
        @if ($view === 'list')
            @forelse ($conversations as $c)
                <button type="button" wire:click="openConversation({{ $c->id }})" class="w-full text-left bg-base-200 hover:bg-base-300 rounded-xl px-3 py-2 transition-colors">
                    <div class="text-sm font-medium text-base-content">{{ mb_strimwidth($c->title ?? 'New conversation', 0, 40, '…') }}</div>
                    <div class="text-xs text-base-content/60 mt-0.5">{{ $c->updated_at->diffForHumans() }}</div>
                </button>
            @empty
                <div class="text-center py-10 text-sm text-base-content/60">No conversations yet</div>
            @endforelse
        @else
            @foreach ($messages as $i => $m)
                @if ($m['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="bg-primary text-primary-content rounded-2xl rounded-br-sm px-4 py-2 max-w-[80%] text-sm whitespace-pre-line">{{ $m['content'] }}</div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="bg-base-200 text-base-content rounded-2xl rounded-bl-sm px-4 py-2 max-w-[85%] text-sm">
                            <div class="whitespace-pre-line">{{ $m['content'] }}</div>

                            @if (! empty($m['citations']))
                                @include('livewire.chat-widget-citations', ['citations' => $m['citations'], 'availability' => $this->availabilityMap])
                                @include('livewire.chat-widget-sources', ['citations' => $m['citations']])
                            @endif

                            @if (! empty($m['error']))
                                <div class="mt-2 rounded-lg bg-amber-50 border border-amber-300 px-3 py-2 text-xs text-amber-800 flex items-center justify-between gap-2">
                                    <span>{{ $m['error']['message'] }}</span>
                                    <button type="button" wire:click="retry" class="btn btn-xs btn-warning">Retry</button>
                                </div>
                            @endif

                            @if (empty($m['failed']) && ! $streaming)
                                <div class="mt-2 flex items-center gap-1" wire:key="rating-{{ $i }}">
                                    @if ($m['rating'] === null)
                                        <button type="button" wire:click="rate({{ $i }}, 'up')" class="btn btn-ghost btn-xs btn-circle text-base-content/50 hover:text-success" title="Helpful answer">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.745 1.282h3.126c1.16 0 2.1.94 2.1 2.1 0 .677-.31 1.28-.796 1.66-.482.377-.758 1.003-.677 1.621.095.715.335 1.395.695 2.01.218.37.341.795.341 1.23 0 1.264-.998 2.287-2.243 2.287h-3.237a2.47 2.47 0 0 0-1.98.99l-.766 1.022a2.24 2.24 0 0 1-1.723.882H9.98a1.125 1.125 0 0 1-1.052-1.504c.392-1.063.607-2.09.618-3.096.005-.634-.172-1.267-.528-1.754a1.5 1.5 0 0 0-1.255-.73H6.633a2.25 2.25 0 0 1-2.25-2.25 2.25 2.25 0 0 1 2.25-2.25Z" /></svg>
                                        </button>
                                        <button type="button" wire:click="rate({{ $i }}, 'down')" class="btn btn-ghost btn-xs btn-circle text-base-content/50 hover:text-error" title="Not helpful">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M7.498 15.25H4.372c-1.026 0-1.905-.865-1.905-1.888 0-.284.062-.564.179-.826.279-.623.508-1.287.508-2.006 0-.599-.24-1.15-.628-1.555.003-.01.006-.021.01-.032.283-.61.446-1.29.446-2.006a3.456 3.456 0 0 0-1.08-2.45 4.016 4.016 0 0 1-.92-1.14c-.284-.63-.253-1.36.077-1.96.33-.6.931-.972 1.601-.972h2.421c.691 0 1.35.277 1.832.764A4.443 4.443 0 0 1 10.5 4.75h6.247c.907 0 1.643.715 1.687 1.62.026.483.037.967-.08 1.45-.086.327-.118.663-.097.997.02.288.075.57.165.836.216.642.647 1.187 1.194 1.576.25.179.512.34.764.512.357.243.575.645.575 1.07 0 .404-.155.79-.43 1.09a1.506 1.506 0 0 1-.386.29 1.503 1.503 0 0 0-.688 1.084c-.067.442-.27.847-.568 1.16l-.015.016a2.254 2.254 0 0 0-.439 2.032l.343 1.026a1.501 1.501 0 0 1-1.42 1.94H14.25a2.25 2.25 0 0 1-2.148-1.572l-.391-1.23a3 3 0 0 0-2.223-2.005Z" /></svg>
                                        </button>
                                    @else
                                        <span class="text-xs {{ $m['rating'] === 'up' ? 'text-success' : 'text-error' }}">{{ $m['rating'] === 'up' ? 'Helpful' : 'Not helpful' }}</span>
                                        <button type="button" wire:click="rate({{ $i }}, {{ $m['rating'] === 'up' ? "'down'" : "'up'" }})" class="btn btn-ghost btn-xs text-base-content/40" title="Change rating">Change</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Persistent stream slot (review W-3): Livewire 4 appends
                 streamed chunks to `[wire:stream="ans"]` live, but only if
                 the element already exists in the DOM — a conditionally
                 rendered target silently drops every chunk. This slot is
                 always mounted; the `:empty` variants collapse it to
                 zero height and invisible while idle (a live CSS state, so
                 it snaps open the moment the first chunk lands), and the
                 final re-render wipes it.
                 HAZARD: `:empty` matches only truly empty elements — keep
                 this markup on a single line; any wrapped-formatting edit
                 that leaves whitespace inside the slot resurfaces the idle
                 bubble. --}}
            {{-- Agentic activity slot (W-3 hazard, same rule as the ans slot
                 below): a second persistent stream slot for compact loop-step
                 lines. Kept on a SINGLE line with the :empty collapse so it
                 stays zero-height and invisible while idle and snaps open on
                 the first streamed line; the final re-render wipes it. --}}
            <div class="flex justify-start">
                <div class="bg-base-200 text-base-content rounded-2xl rounded-bl-sm px-4 max-w-[85%]">
                    <div wire:stream="activity" class="text-xs py-2 empty:py-0 empty:invisible space-y-1"></div>
                </div>
            </div>

            <div class="flex justify-start">
                <div class="bg-base-200 text-base-content rounded-2xl rounded-bl-sm px-4 max-w-[85%]">
                    <div wire:stream="ans" class="text-sm whitespace-pre-line py-2 empty:py-0 empty:invisible"></div>
                </div>
            </div>

            @if (empty($messages) && ! $streaming)
                <div class="text-center py-10 text-sm text-base-content/60">Ask about papers or rules</div>
            @endif
        @endif
    </div>

    <form wire:submit="send" class="border-t border-base-200 p-3 flex items-end gap-2 bg-base-100">
        <textarea wire:model="draft" rows="1" class="textarea textarea-bordered textarea-sm flex-1 resize-none text-sm" placeholder="Ask about rules, papers…" wire:loading.attr="disabled" wire:target="send,retry" @if ($streaming) disabled @endif></textarea>
        <button type="submit" class="btn btn-primary btn-circle btn-sm" wire:loading.attr="disabled" wire:target="send,retry" @if ($streaming) disabled @endif title="Send">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
        </button>
    </form>
    </div>
</div>
