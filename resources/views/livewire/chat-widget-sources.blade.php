<div class="mt-2 pt-2 border-t border-dashed border-base-300">
    <div class="text-[10px] uppercase tracking-wide text-base-content/40 mb-0.5">Sources</div>
    <ol class="space-y-0.5">
        @foreach ($citations as $c)
            <li class="text-[11px] text-base-content/80">
                [{{ $c['n'] }}]
                @if ($c['corpus'] === 'catalog' && $c['url'])
                    <a href="{{ $c['url'] }}" class="link link-primary">{{ $c['title'] }}
                        <span class="font-mono opacity-60">· {{ $c['catalog_code'] }}</span>
                    </a>
                @else
                    {{ $c['title'] }} <span class="text-base-content/40">(rulebook)</span>
                @endif
            </li>
        @endforeach
    </ol>
</div>
