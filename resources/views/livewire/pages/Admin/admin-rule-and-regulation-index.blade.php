<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Rules and Regulations') }}
        </h2>
    </x-slot>

    <x-mary-accordion>
        {{-- Loop through each header from the database --}}
        @foreach($this->ruleHeaders as $ruleHeader)
            {{-- Create a collapsible section for each header --}}
            <x-mary-collapse :name="$ruleHeader->title" class="bg-base-100">
                <x-slot:heading>{{ $ruleHeader->title }}</x-slot:heading>
                <x-slot:content>
                    <div class="space-y-2 p-4">
                        {{-- Loop through each rule associated with the current header --}}
                        @foreach($ruleHeader->rules as $rule)
                            <div>
                                {{ $rule->rule_header_id }}.{{ $rule->order }}
                                {{ $rule->content }}
                            </div>
                        @endforeach
                    </div>
                </x-slot:content>
            </x-mary-collapse>
        @endforeach
    </x-mary-accordion>
</div>
