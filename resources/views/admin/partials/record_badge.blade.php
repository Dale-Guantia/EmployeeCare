@if($record)
    @php
        $color = $record->getAttribute($kind . '_color');
        $color = is_string($color) && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color) ? $color : '#e2e8f0';
        $hex = substr($color, 1);
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        $brightness = (hexdec(substr($hex, 0, 2)) * 299 + hexdec(substr($hex, 2, 2)) * 587 + hexdec(substr($hex, 4, 2)) * 114) / 1000;
    @endphp
    <span class="badge ec-record-badge" style="background-color: {{ $color }}; color: {{ $brightness >= 150 ? '#17212f' : '#ffffff' }};">{{ $record->getAttribute($kind . '_name') }}</span>
@else
    <span class="text-muted">—</span>
@endif
