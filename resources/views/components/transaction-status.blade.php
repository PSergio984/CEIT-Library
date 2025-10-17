@props(['status'])

@if ($status === 'expired')
	<span class="text-red-500 font-bold text-sm">
		{{ strtoupper($status) }}
	</span>
@elseif ($status === 'completed')
	<span class="text-success font-medium text-sm">
		{{ strtoupper($status) }}
	</span>
@elseif ($status === 'started')
	<span class="text-blue-500 font-medium text-sm">
		{{ strtoupper($status) }}
	</span>@else
	<span class="text-base-content font-medium text-sm">
		{{ strtoupper($status) }}
	</span>
@endif


