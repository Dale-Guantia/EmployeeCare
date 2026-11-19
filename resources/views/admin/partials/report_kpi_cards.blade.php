<div class="reports-kpis ec-dashboard-stat-grid">
    @foreach($cards as $card)
        @php($tone = $card['tone'] ?? (isset($card['background']) ? 'purple' : (['bg-success' => 'green', 'bg-warning' => 'amber', 'bg-danger' => 'red', 'bg-secondary' => 'purple'][$card['color'] ?? ''] ?? 'blue')))
        <section class="card ec-dashboard-stat ec-stat-{{ $tone }}" aria-label="{{ $card['label'] }}">
            <div class="card-body">
                <div class="ec-dashboard-stat-heading"><h2>{{ $card['label'] }}</h2><i class="la {{ $card['icon'] }}" aria-hidden="true"></i></div>
                <div class="ec-dashboard-stat-value" data-report-kpi="{{ $card['key'] }}" @if(isset($card['id'])) id="{{ $card['id'] }}" @endif>@if($card['duration'] ?? false)@include('admin.partials.resolution_average', ['formatted' => $card['value']])@else{{ $card['value'] }}@endif</div>
                @if(isset($card['note']))<p class="mb-0">{{ $card['note'] }}</p>@endif
            </div>
        </section>
    @endforeach
</div>
