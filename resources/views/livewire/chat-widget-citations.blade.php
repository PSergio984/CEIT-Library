<div class="mt-2 flex flex-wrap gap-1.5">
    @foreach ($citations as $c)
        @if ($c['corpus'] === 'catalog' && $c['url'])
            <a href="{{ $c['url'] }}" wire:navigate class="inline-flex items-center gap-1 rounded-full border border-primary/40 bg-primary/5 text-primary px-2.5 py-0.5 text-[11px] hover:bg-primary/10">
                [{{ $c['n'] }}] {{ $c['title'] }}
                <span class="opacity-60 font-mono">{{ $c['catalog_code'] }}</span>
                @if (! empty($availability[$c['catalog_code'] ?? null] ?? null))
                    <span class="{{ $availability[$c['catalog_code']]['available'] > 0 ? 'text-success' : 'text-error' }} font-medium">{{ $availability[$c['catalog_code']]['available'] }}/{{ $availability[$c['catalog_code']]['total'] }}</span>
                @endif
            </a>
        @else
            <span class="inline-flex items-center gap-1 rounded-full border border-base-300 bg-base-100 px-2.5 py-0.5 text-[11px] text-base-content/80">
                [{{ $c['n'] }}] {{ $c['title'] }}
            </span>
        @endif
    @endforeach
</div>
