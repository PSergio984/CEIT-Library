@props(['rows' => 5, 'cols' => 5])

<div {{ $attributes->merge(['class' => 'w-full animate-pulse']) }}>
    <div class="overflow-x-auto bg-base-100 rounded-2xl border border-base-300 shadow-sm">
        <table class="table w-full border-separate border-spacing-0">
            {{-- Skeleton Header --}}
            <thead>
                <tr class="bg-base-200/50">
                    @for($i = 0; $i < $cols; $i++)
                        <th class="py-4 px-4 border-b border-base-300 text-left">
                            <div class="h-4 bg-base-300 rounded-lg w-2/3"></div>
                        </th>
                    @endfor
                </tr>
            </thead>
            
            {{-- Skeleton Body --}}
            <tbody>
                @for($r = 0; $r < $rows; $r++)
                    <tr class="hover:bg-base-200/5 transition-colors">
                        @for($c = 0; $c < $cols; $c++)
                            <td class="py-4 px-4 border-b border-base-200/50 text-left">
                                <div class="flex items-center gap-3">
                                    @if($c === 0)
                                        <div class="w-10 h-10 bg-base-200 rounded-xl flex-shrink-0"></div>
                                    @endif
                                    <div class="flex-1 space-y-2">
                                        <div class="h-3 bg-base-200 rounded-lg w-full"></div>
                                        @if($c === 1)
                                            <div class="h-2 bg-base-200/60 rounded-lg w-3/4"></div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        @endfor
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>
