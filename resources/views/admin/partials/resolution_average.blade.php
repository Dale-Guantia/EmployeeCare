@if($formatted === '—')
    <span title="No resolved tickets with a recorded resolution time" aria-label="No resolved tickets with a recorded resolution time">—</span>
@else
    {{ $formatted }}
@endif
