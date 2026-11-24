@extends(backpack_view('blank'))

@section('content')

    <div class="container-fluid ec-dashboard ec-analytics">
        @if($periodError)
            <div class="row mb-2">
                <div class="col-12">
                    <div class="alert alert-warning py-2 mb-0">{{ $periodError }}</div>
                </div>
            </div>
        @endif

        <div class="row mb-3 align-items-end" id="dashboardPeriodControl">
            <div class="col-auto">
                <label for="dashPeriodSelect" class="small text-muted text-uppercase fw-bold mb-1">Date Range</label>
                <select id="dashPeriodSelect" class="form-control form-control-sm">
                    <option value="all" {{ $activePeriod === 'all' ? 'selected' : '' }}>All Time</option>
                    <option value="month" {{ $activePeriod === 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="half" {{ $activePeriod === 'half' ? 'selected' : '' }}>Semestral</option>
                    <option value="custom" {{ $activePeriod === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
            </div>

        @include('admin.office_filters')

            <div class="col-auto" id="dashHalfToggleWrap" style="{{ $activePeriod === 'half' ? '' : 'display:none;' }}">
                <label for="dashHalfSelect" class="small text-muted text-uppercase fw-bold mb-1">Half</label>
                <select id="dashHalfSelect" class="form-control form-control-sm">
                    <option value="h1" {{ $activeHalf === 'h1' ? 'selected' : '' }}>Jan &ndash; Jun {{ $activeYear }}</option>
                    <option value="h2" {{ $activeHalf === 'h2' ? 'selected' : '' }}>Jul &ndash; Dec {{ $activeYear }}</option>
                </select>
            </div>

            <div class="col-auto" id="dashCustomRangeWrap" style="{{ $activePeriod === 'custom' ? '' : 'display:none;' }}">
                <div class="row g-3 align-items-end">
                    <div class="col-auto">
                        <label for="dashCustomStart" class="small text-muted text-uppercase fw-bold mb-1">Start Date</label>
                        <input type="date" id="dashCustomStart" class="form-control form-control-sm" value="{{ request('start') }}">
                    </div>
                    <div class="col-auto">
                        <label for="dashCustomEnd" class="small text-muted text-uppercase fw-bold mb-1">End Date</label>
                        <input type="date" id="dashCustomEnd" class="form-control form-control-sm" value="{{ request('end') }}">
                    </div>
                    <div class="col-auto">
                        <button type="button" id="dashCustomApplyBtn" class="btn btn-sm btn-primary">Apply</button>
                    </div>
                </div>
            </div>

            <div class="col-auto ms-auto">
                <small>Data shown for: {{ $dashWidgetStart->format('M j, Y') }} &ndash; {{ $dashWidgetEnd->format('M j, Y') }}</small>
            </div>
        </div>

        {{-- <div class="ec-dashboard-filter-note">
            Summaries use ticket creation dates in the selected range. SLA deadlines use exact timestamps, 72 elapsed hours from creation.
            <span>As of {{ $dashboardAsOf->format('M j, Y - g:i A') }}</span>
        </div> --}}
        @if($olderOpenCount > 0)
            <div class="ec-dashboard-scope-notice" data-older-open-notice>
                <i class="la la-info-circle" aria-hidden="true"></i>
                <span>{{ $olderOpenCount }} older open {{ $olderOpenCount === 1 ? 'ticket is' : 'tickets are' }} outside this creation-date range.</span>
                <a href="{{ route('backpack.dashboard', ['period' => 'all', 'department_id' => $selectedDepartmentId, 'division_id' => $selectedDivisionId, 'chart_department_id' => $divisionChartDepartmentId]) }}#dashboardAttention">View All Time</a>
            </div>
        @endif

        @include('admin.partials.dashboard_summary')

        <div class="row g-4 mb-4 ec-dashboard-action-row">
            <div class="col-xl-8">@include('admin.partials.dashboard_attention')</div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <div><strong>Ticket Volume Trend</strong><div class="ec-chart-subtitle">Tickets created over time</div></div>
                    </div>
                    <div class="card-body">
                        <div class="ec-dashboard-chart-canvas" data-analytics-chart-panel>
                            <canvas id="dashVolumeTrendChart" role="img" aria-label="Ticket counts over time for the global filters"></canvas>
                            <p class="ec-chart-empty" data-analytics-chart-empty hidden>No tickets created for these filters.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ec-dashboard-overview-grid">
            <div>
                @include('admin.partials.department_division_chart', ['chartTitle' => 'Tickets per Division',
                    'chartId' => 'divisionChart', 'labels' => $divisionLabels, 'values' => $divisionCounts,
                    'colors' => $divisionColors])
            </div>
            <div>
                <div class="card">
                    <div class="card-header justify-content-between gap-2">
                        <div><strong>Tickets Overview</strong><div class="ec-chart-subtitle">Current status of selected tickets</div></div>
                        <span class="ec-chart-total">{{ $dashKpis['total'] }} tickets</span>
                    </div>
                    <div class="card-body">
                        <div class="ec-dashboard-chart-canvas" data-analytics-chart-panel>
                            <canvas id="statusChart" role="img" aria-label="Ticket counts by status for the global filters"></canvas>
                            <p class="ec-chart-empty" data-analytics-chart-empty hidden>No tickets for these filters.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                @include('admin.partials.staff_leaderboard')
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <section class="card mb-0" data-dashboard-sla>
                    <div class="card-header"><div><strong>Issue Types and Delays</strong><div class="ec-chart-subtitle">Largest delay counts first; open overdue and late resolutions shown separately</div></div></div>
                    <div class="card-body">
                        <div class="ec-sla-table-scroll" tabindex="0" role="region" aria-label="Issue types and delays">
                        <table class="table table-hover ec-report-table ec-sla-table">
                            <thead><tr><th scope="col">Issue</th><th scope="col">Open overdue</th><th scope="col">Resolved late</th><th scope="col">On-time resolution</th></tr></thead>
                            <tbody>
                            @forelse($dashboardIssues as $row)
                                <tr>
                                    <td><strong>{{ $row['subject'] }}</strong><div class="ec-chart-subtitle">{{ $row['total'] }} tickets</div></td>
                                    <td><span class="ec-sla-count {{ $row['overdue_open'] > 0 ? 'ec-sla-late' : 'ec-sla-clear' }}">{{ $row['overdue_open'] }}</span></td>
                                    <td class="ec-table-number">{{ $row['late_resolved'] }}</td>
                                    <td class="ec-table-number">{{ $row['on_time_rate'] === null ? '—' : $row['on_time_rate'] . '%' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No tickets for these filters.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

@endsection
@section('after_styles')
@basset(public_path('css/analytics.css'))
@basset(public_path('css/dashboard-charts.css'))
@basset(public_path('css/reassignment-card.css'))

@basset('https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css')
@basset('https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css')

@endsection

@section('after_scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@basset('https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js')
@basset('https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js')
@include('admin.partials.report_table_assets')
@basset(public_path('js/department-division-charts.js'))

@php
    $dashboardCharts = [
        'status' => ['labels' => $statusLabels, 'counts' => $statusData, 'colors' => $statusColors],
        'volume' => ['labels' => $volumeTrendLabels, 'counts' => $volumeTrendCounts],
    ];
@endphp
<script type="application/json" data-dashboard-charts>@json($dashboardCharts)</script>
@basset(public_path('js/analytics-charts.js'))

<script>
function buildDashboardUrl(params) {
    const url = new URL(window.location.href);
    url.search = '';
    Object.keys(params).forEach(function (key) {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
            url.searchParams.set(key, params[key]);
        }
    });
    const officeDepartment = document.querySelector('[data-report-department]');
    const officeDivision = document.querySelector('[data-report-division]');
    if (officeDepartment?.value) url.searchParams.set('department_id', officeDepartment.value);
    if (officeDivision?.value) url.searchParams.set('division_id', officeDivision.value);
    document.querySelectorAll('[data-division-chart-department]').forEach(chartDepartment => {
        if (chartDepartment.value) url.searchParams.set(chartDepartment.getAttribute('data-division-chart-param') || 'chart_department_id', chartDepartment.value);
    });
    return url.toString();
}

$(document).ready(function () {
    const periodSelect = document.getElementById('dashPeriodSelect');
    const halfSelect = document.getElementById('dashHalfSelect');
    const halfWrap = document.getElementById('dashHalfToggleWrap');
    const customWrap = document.getElementById('dashCustomRangeWrap');
    const customStart = document.getElementById('dashCustomStart');
    const customEnd = document.getElementById('dashCustomEnd');
    const customApplyBtn = document.getElementById('dashCustomApplyBtn');

    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            const period = periodSelect.value;
            if (halfWrap) halfWrap.style.display = period === 'half' ? '' : 'none';
            if (customWrap) customWrap.style.display = period === 'custom' ? '' : 'none';

            if (period === 'all' || period === 'month') {
                window.location.href = buildDashboardUrl({ period: period });
            } else if (period === 'half') {
                window.location.href = buildDashboardUrl({ period: 'half', half: halfSelect.value });
            }
        });
    }

    if (halfSelect) {
        halfSelect.addEventListener('change', function () {
            window.location.href = buildDashboardUrl({ period: 'half', half: halfSelect.value });
        });
    }

    if (customApplyBtn) {
        customApplyBtn.addEventListener('click', function () {
            window.location.href = buildDashboardUrl({
                period: 'custom',
                start: customStart.value,
                end: customEnd.value
            });
        });
    }
});

</script>

@endsection
