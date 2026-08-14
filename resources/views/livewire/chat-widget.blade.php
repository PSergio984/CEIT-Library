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
            @foreach ($messages as $m)
                @if ($m['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="bg-primary text-primary-content rounded-2xl rounded-br-sm px-4 py-2 max-w-[80%] text-sm whitespace-pre-line">{{ $m['content'] }}</div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="bg-base-200 text-base-content rounded-2xl rounded-bl-sm px-4 py-2 max-w-[85%] text-sm">
                            <div class="whitespace-pre-line">{{ $m['content'] }}</div>

                            @if (! empty($m['citations']))
                                @include('livewire.chat-widget-citations', ['citations' => $m['citations']])
                                @include('livewire.chat-widget-sources', ['citations' => $m['citations']])
                            @endif

                            @if (! empty($m['error']))
                                <div class="mt-2 rounded-lg bg-amber-50 border border-amber-300 px-3 py-2 text-xs text-amber-800 flex items-center justify-between gap-2">
                                    <span>{{ $m['error']['message'] }}</span>
                                    <button type="button" wire:click="retry" class="btn btn-xs btn-warning">Retry</button>
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
                 final re-render wipes it. --}}
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
