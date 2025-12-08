<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Rules and Regulations') }}
        </h2>
    </x-slot>
    <x-mary-accordion>
        @foreach($this->ruleHeaders() as $index => $ruleHeader)
            <x-mary-collapse name="header-{{ $ruleHeader->id }}" class="bg-base-100">
                <x-slot:heading>{{ $ruleHeader->title }}</x-slot:heading>
                <x-slot:content>
                    @foreach($ruleHeader->ruleRegulations as $rule)
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                - {{ $rule->content }}
                            </div>
                        </div>
                    @endforeach
                </x-slot:content>
            </x-mary-collapse>
        @endforeach
    </x-mary-accordion>
</div>
