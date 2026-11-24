@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">
            <span class="text-capitalize">{{ $title }}</span>
        </h2>

        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#downloadReportModal">
            <i class="la la-download"></i> Download Report
        </button>
    </section>
@endsection

@section('content')
<div class="ec-analytics ec-reports">
    @if($periodError)
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-warning py-2 mb-0">{{ $periodError }}</div>
            </div>
        </div>
    @endif

    <div class="row mb-3 align-items-end" id="reportsPeriodControl">
        <div class="col-auto">
            <label for="periodSelect" class="small text-muted text-uppercase fw-bold mb-1">Date Range</label>
            <select id="periodSelect" class="form-control form-control-sm">
                <option value="all" {{ $activePeriod === 'all' ? 'selected' : '' }}>All Time</option>
                <option value="month" {{ $activePeriod === 'month' ? 'selected' : '' }}>This Month</option>
                <option value="half" {{ $activePeriod === 'half' ? 'selected' : '' }}>Semestral</option>
                <option value="custom" {{ $activePeriod === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
        </div>

        @include('admin.office_filters')

        <div class="col-auto" id="halfToggleWrap" style="{{ $activePeriod === 'half' ? '' : 'display:none;' }}">
            <label for="halfSelect" class="small text-muted text-uppercase fw-bold mb-1">Half</label>
            <select id="halfSelect" class="form-control form-control-sm">
                <option value="h1" {{ $activeHalf === 'h1' ? 'selected' : '' }}>Jan &ndash; Jun {{ $activeYear }}</option>
                <option value="h2" {{ $activeHalf === 'h2' ? 'selected' : '' }}>Jul &ndash; Dec {{ $activeYear }}</option>
            </select>
        </div>

        <div class="col-auto" id="customRangeWrap" style="{{ $activePeriod === 'custom' ? '' : 'display:none;' }}">
            <div class="row g-3 align-items-end">
                <div class="col-auto">
                    <label for="customStart" class="small text-muted text-uppercase fw-bold mb-1">Start Date</label>
                    <input type="date" id="customStart" class="form-control form-control-sm" value="{{ request('start') }}">
                </div>
                <div class="col-auto">
                    <label for="customEnd" class="small text-muted text-uppercase fw-bold mb-1">End Date</label>
                    <input type="date" id="customEnd" class="form-control form-control-sm" value="{{ request('end') }}">
                </div>
                <div class="col-auto">
                    <button type="button" id="customApplyBtn" class="btn btn-sm btn-primary">Apply</button>
                </div>
            </div>
        </div>

        <div class="col-auto ms-auto">
            <small class="text-muted">Data shown for: {{ $reportWidgetStart->format('M j, Y') }} &ndash; {{ $reportWidgetEnd->format('M j, Y') }}</small>
        </div>
    </div>

    @php
        $cards = [
            ['key' => 'visitors', 'id' => 'kpiVisitors', 'label' => 'Visitors', 'value' => $reportVisitors, 'icon' => 'la-users', 'tone' => 'slate', 'note' => 'Signed in today; selected office'],
            ['key' => 'total', 'id' => 'kpiTotal', 'label' => 'Total Tickets', 'value' => $reportKpis['total'], 'icon' => 'la-ticket', 'tone' => 'blue', 'note' => 'Created in the selected period'],
            ['key' => 'resolved', 'id' => 'kpiResolved', 'label' => 'Resolved', 'value' => $reportKpis['resolved'], 'icon' => 'la-check-circle', 'tone' => 'green', 'note' => 'Currently resolved tickets'],
            ['key' => 'pending', 'id' => 'kpiPending', 'label' => 'Pending', 'value' => $reportKpis['pending'], 'icon' => 'la-hourglass-half', 'tone' => 'amber', 'note' => 'Tickets in Pending status'],
            ['key' => 'overdue', 'id' => 'kpiOverdue', 'label' => 'Overdue (SLA)', 'value' => $reportKpis['overdue_pct'] . '%', 'icon' => 'la-exclamation-circle', 'tone' => 'red', 'note' => 'Open overdue and resolved late'],
            ['key' => 'reopen', 'id' => 'kpiReopenRate', 'label' => 'Reopen Rate', 'value' => $reportKpis['reopen_rate'] . '%', 'icon' => 'la-undo-alt', 'tone' => 'blue', 'note' => 'Reopened or marked as previously reopened'],
        ];
    @endphp
    @include('admin.partials.report_kpi_cards', ['cards' => $cards])

    <div class="row g-4 mb-4 ec-reports-chart-row">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header"><div><strong>Ticket Volume Trend</strong><div class="ec-chart-subtitle">Tickets created over time</div></div></div>
                <div class="card-body">
                    <div class="ec-reports-chart-canvas" data-analytics-chart-panel>
                        <canvas id="volumeTrendChart" role="img" aria-label="Ticket counts over time for the global filters"></canvas>
                        <p class="ec-chart-empty" data-analytics-chart-empty hidden>No tickets created for these filters.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header justify-content-between gap-2">
                    <div><strong>Tickets Overview</strong><div class="ec-chart-subtitle">Current status of selected tickets</div></div>
                    <span class="ec-chart-total">{{ $reportKpis['total'] }} tickets</span>
                </div>
                <div class="card-body">
                    <div class="ec-reports-chart-canvas" data-analytics-chart-panel>
                        <canvas id="statusChart" role="img" aria-label="Ticket counts by status for the global filters"></canvas>
                        <p class="ec-chart-empty" data-analytics-chart-empty hidden>No tickets for these filters.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4 ec-reports-metric-row">
        <div class="col-xl-6">
            @include('admin.partials.average_resolution_card', ['kpis' => $reportKpis, 'resolutionTone' => 'green'])
        </div>
        <div class="col-xl-6">
            @include('admin.partials.reassignment_rate_card', ['rate' => $reportReassignment['rate'],
                'reassigned' => $reportReassignment['reassigned_count'], 'total' => $reportReassignment['total']])
        </div>
    </div>

    {{--
        Overdue vs On-Time by Department chart hidden: the org is currently
        single-department (City Human Resource Development Office is the
        only department in the system), so a per-department breakdown adds
        no signal. Kept in code (not deleted) so it can be restored once
        multiple departments are active — ReportsController::buildSlaBreakdownWidget()
        still computes 'department_labels'/'department_overdue'/'department_on_time'
        for this chart, unchanged.
    --}}
    @if(false)
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Overdue vs On-Time by Department</strong></div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="slaByDepartmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row mb-4">
        <div class="col-12">
            <div class="card mb-0" data-report-sla>
                <div class="card-header"><div><strong>SLA by Issue Type</strong><div class="ec-chart-subtitle">Includes open and resolved tickets</div></div></div>
                <div class="card-body">
                    <div class="ec-sla-table-scroll" tabindex="0" role="region" aria-label="SLA by issue type">
                    <table class="table table-hover ec-report-table ec-sla-table">
                        <caption class="visually-hidden">Overdue ticket counts and percentages by issue type for the global filters.</caption>
                        <thead><tr><th scope="col">Issue</th><th scope="col">Total</th><th scope="col">Overdue</th><th scope="col">Overdue %</th></tr></thead>
                        <tbody>
                        @forelse($reportSlaBreakdown['by_issue'] as $issueName => $row)
                            <tr>
                                <td>{{ $issueName }}</td>
                                <td class="ec-table-number">{{ $row['total'] }}</td>
                                <td><span class="ec-sla-count {{ $row['overdue'] > 0 ? 'ec-sla-late' : 'ec-sla-clear' }}">{{ $row['overdue'] }}</span></td>
                                <td class="ec-table-number">{{ $row['total'] > 0 ? round(($row['overdue'] / $row['total']) * 100, 1) : 0 }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No tickets for these filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    @include('admin.partials.latest_tickets_table')
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted small mb-2">Totals reflect the selected date range and office.</p>
                    <table id="userActivityTable" class="table table-hover w-100 ec-report-table" data-report-table aria-label="User Activity">
                        <thead>
                        <tr>
                            <th class="control report-details-cell"><span class="visually-hidden">Details</span></th>
                            <th data-priority="1">Name</th>
                            <th data-priority="5">Division</th>
                            <th>Assigned</th>
                            <th data-priority="2">Resolved</th>
                            <th data-priority="3">Overdue</th>
                            <th>Avg Resolution</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td class="report-details-cell"><button type="button" class="btn btn-sm btn-outline-secondary report-details-toggle" aria-label="Show additional staff details" aria-expanded="false">+</button></td>
                                <td>{{ $user->name ?? 'N/A'}}</td>
                                <td>{{ $user->division->division_name ?? 'N/A'}}</td>
                                <td>{{ $user->assigned_count }}</td>
                                <td>{{ $user->resolved_tickets_count}}</td>
                                <td>{{ $user->overdue_tickets_count}}</td>
                                <td data-order="{{ $user->avg_resolution_minutes ?? -1 }}">@include('admin.partials.resolution_average', ['formatted' => $user->avg_resolution_formatted])</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <table id="ticketsPerDivisionTable" class="table table-hover w-100 ec-report-table" data-report-table aria-label="Tickets per Category Overview">
                        <thead>
                        <tr>
                            <th class="control report-details-cell"><span class="visually-hidden">Details</span></th>
                            <th data-priority="1">Issue description</th>
                            <th data-priority="5">Division</th>
                            <th data-priority="2">Total tickets</th>
                            <th>Average resolve time per ticket</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($ticketOverview as $row)
                            <tr>
                                <td class="report-details-cell"><button type="button" class="btn btn-sm btn-outline-secondary report-details-toggle" aria-label="Show additional category details" aria-expanded="false">+</button></td>
                                <td>{{ $row->issue_description ?? 'N/A' }}</td>
                                <td>{{ $row->division_name ?? 'N/A' }}</td>
                                <td>{{ $row->total_tickets }}</td>
                                <td data-order="{{ $row->avg_resolve_minutes ?? -1 }}">@include('admin.partials.resolution_average', ['formatted' => $row->formatted_time])</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>

<div class="modal fade" id="downloadReportModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('page.reports.download_pdf') }}" target="_blank">
            @csrf

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Date Range</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @if($officeFiltersEnabled)
                        <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                        <input type="hidden" name="division_id" value="{{ $selectedDivisionId }}">
                    @endif
                    <div class="mb-3">
                        <label>Start Date <span class="text-danger">*</span></label>
                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="{{ now()->startOfYear()->format('Y-m-d') }}"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label>End Date <span class="text-danger">*</span></label>
                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="{{ now()->format('Y-m-d') }}"
                            required
                        >
                    </div>

                    <div class="form-check">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="include_zero_activity"
                            name="include_zero_activity"
                            value="1"
                        >
                        <label class="form-check-label" for="include_zero_activity">
                            Include records with zero activity
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        Generate Report
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('after_styles')
@basset(public_path('css/analytics.css'))
@basset(public_path('css/reassignment-card.css'))

@basset('https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css')
@basset('https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css')



@endpush

@push('after_scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

@php
    $reportCharts = [
        'volume' => ['canvasId' => 'volumeTrendChart', 'labels' => $reportVolumeTrend['labels'], 'counts' => $reportVolumeTrend['counts']],
        'status' => $reportTicketsOverview,
    ];
@endphp
<script type="application/json" data-report-charts>@json($reportCharts)</script>
@basset(public_path('js/analytics-charts.js'))

<script>
function buildReportsUrl(params) {
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
    return url.toString();
}

$(document).ready(function () {

    const periodSelect = document.getElementById('periodSelect');
    const halfSelect = document.getElementById('halfSelect');
    const halfWrap = document.getElementById('halfToggleWrap');
    const customWrap = document.getElementById('customRangeWrap');
    const customStart = document.getElementById('customStart');
    const customEnd = document.getElementById('customEnd');
    const customApplyBtn = document.getElementById('customApplyBtn');

    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            const period = periodSelect.value;
            if (halfWrap) halfWrap.style.display = period === 'half' ? '' : 'none';
            if (customWrap) customWrap.style.display = period === 'custom' ? '' : 'none';

            if (period === 'all' || period === 'month') {
                window.location.href = buildReportsUrl({ period: period });
            } else if (period === 'half') {
                window.location.href = buildReportsUrl({ period: 'half', half: halfSelect.value });
            }
        });
    }

    if (halfSelect) {
        halfSelect.addEventListener('change', function () {
            window.location.href = buildReportsUrl({ period: 'half', half: halfSelect.value });
        });
    }

    if (customApplyBtn) {
        customApplyBtn.addEventListener('click', function () {
            window.location.href = buildReportsUrl({
                period: 'custom',
                start: customStart.value,
                end: customEnd.value
            });
        });
    }
});
</script>

@basset('https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js')
@basset('https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js')
@include('admin.partials.report_table_assets')

<script>

    $(document).ready(function () {
        const modal = document.getElementById('downloadReportModal');
        if (modal) {
            document.body.appendChild(modal);
        }
    });

</script>

@endpush
