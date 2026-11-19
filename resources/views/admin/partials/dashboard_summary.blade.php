@php
    $summaryCards = [
        ['label' => 'Total tickets', 'value' => $dashboardMetrics['total'], 'tone' => 'blue', 'icon' => 'ticket', 'note' => 'Created in the selected period'],
        ['label' => 'Open tickets', 'value' => $dashboardMetrics['open'], 'tone' => 'amber', 'icon' => 'folder-open', 'note' => 'All unresolved statuses'],
        ['label' => 'Unassigned', 'value' => $dashboardMetrics['unassigned'], 'tone' => 'slate', 'icon' => 'user-clock', 'note' => 'Open with no assignee or Unassigned status'],
        ['label' => 'Open overdue', 'value' => $dashboardMetrics['overdue_open'], 'tone' => 'red', 'icon' => 'exclamation-circle', 'note' => 'Open at or past the 72-hour deadline'],
        ['label' => 'Due within 24h', 'value' => $dashboardMetrics['due_soon'], 'tone' => 'amber', 'icon' => 'hourglass-half', 'note' => 'Open and not yet overdue'],
        ['label' => 'Average Resolution Time', 'value' => $dashboardMetrics['avg_resolution_formatted'], 'tone' => 'green', 'icon' => 'clock',
            'duration' => true, 'note' => $dashboardMetrics['timed_resolved'] . ' valid timed resolutions; creation to resolution'],
    ];
@endphp
<div class="ec-dashboard-stat-grid" data-dashboard-summary>
    @foreach($summaryCards as $stat)
        <section class="card ec-dashboard-stat ec-stat-{{ $stat['tone'] }}" aria-label="{{ $stat['label'] }}">
            <div class="card-body">
                <div class="ec-dashboard-stat-heading"><h2>{{ $stat['label'] }}</h2><i class="la la-{{ $stat['icon'] }}" aria-hidden="true"></i></div>
                <div class="ec-dashboard-stat-value">@if($stat['duration'] ?? false)@include('admin.partials.resolution_average', ['formatted' => $stat['value']])@else{{ $stat['value'] }}@endif</div>
                <p class="mb-0">{{ $stat['note'] }}</p>
            </div>
        </section>
    @endforeach
</div>
