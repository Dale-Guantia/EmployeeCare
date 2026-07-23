@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">
            <span class="text-capitalize">{{ $title }}</span>
        </h2>

        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#downloadReportModal">
            <i class="la la-download"></i> Download Report
        </button>
    </section>
@endsection

@section('content')
    @if($periodError)
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-warning py-2 mb-0">{{ $periodError }}</div>
            </div>
        </div>
    @endif

    <div class="row mb-3 align-items-end" id="reportsPeriodControl">
        <div class="col-auto">
            <label for="periodSelect" class="small text-muted text-uppercase font-weight-bold mb-1">Date Range</label>
            <select id="periodSelect" class="form-control form-control-sm">
                <option value="all" {{ $activePeriod === 'all' ? 'selected' : '' }}>All Time</option>
                <option value="month" {{ $activePeriod === 'month' ? 'selected' : '' }}>This Month</option>
                <option value="half" {{ $activePeriod === 'half' ? 'selected' : '' }}>Semestral</option>
                <option value="custom" {{ $activePeriod === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
        </div>

        <div class="col-auto" id="halfToggleWrap" style="{{ $activePeriod === 'half' ? '' : 'display:none;' }}">
            <label for="halfSelect" class="small text-muted text-uppercase font-weight-bold mb-1">Half</label>
            <select id="halfSelect" class="form-control form-control-sm">
                <option value="h1" {{ $activeHalf === 'h1' ? 'selected' : '' }}>Jan &ndash; Jun {{ $activeYear }}</option>
                <option value="h2" {{ $activeHalf === 'h2' ? 'selected' : '' }}>Jul &ndash; Dec {{ $activeYear }}</option>
            </select>
        </div>

        <div class="col-auto" id="customRangeWrap" style="{{ $activePeriod === 'custom' ? '' : 'display:none;' }}">
            <div class="form-row align-items-end">
                <div class="col-auto">
                    <label for="customStart" class="small text-muted text-uppercase font-weight-bold mb-1">Start Date</label>
                    <input type="date" id="customStart" class="form-control form-control-sm" value="{{ request('start') }}">
                </div>
                <div class="col-auto">
                    <label for="customEnd" class="small text-muted text-uppercase font-weight-bold mb-1">End Date</label>
                    <input type="date" id="customEnd" class="form-control form-control-sm" value="{{ request('end') }}">
                </div>
                <div class="col-auto">
                    <button type="button" id="customApplyBtn" class="btn btn-sm btn-primary">Apply</button>
                </div>
            </div>
        </div>

        <div class="col-auto ml-auto">
            <small class="text-muted">Data shown for: {{ $reportWidgetStart->format('M j, Y') }} &ndash; {{ $reportWidgetEnd->format('M j, Y') }}</small>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-dark">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-ticket"></i></div>
                    <div class="text-value" id="kpiTotal">{{ $reportKpis['total'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Total Tickets</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-list-alt"></i></div>
                    <div class="text-value" id="kpiResolved">{{ $reportKpis['resolved'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Resolved</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-hourglass-half"></i></div>
                    <div class="text-value" id="kpiPending">{{ $reportKpis['pending'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white" style="background-color: #9966FF;">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-user-clock"></i></div>
                    <div class="text-value" id="kpiAvgResolution">{{ $reportKpis['avg_resolution_formatted'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Avg Resolution</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white" style="background-color: {{ $reportKpis['overdue_pct'] >= 20 ? '#de4759' : '#41ba96' }};">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-exclamation-circle"></i></div>
                    <div class="text-value" id="kpiOverdue">{{ $reportKpis['overdue_pct'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Overdue (SLA)</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-undo-alt"></i></div>
                    <div class="text-value" id="kpiReopenRate">{{ $reportKpis['reopen_rate'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Reopen Rate</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Ticket Volume Trend</strong></div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="volumeTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Resolution Time Distribution</strong></div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="resolutionDistributionChart"></canvas>
                    </div>
                </div>
            </div>
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

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Status Funnel</strong></div>
                <div class="card-body" style="min-height: 320px;">
                    <div style="height: 280px; position: relative;">
                        <canvas id="statusFunnelChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card h-100" style="font-size: 0.85rem;">
                <div class="card-header"><strong>Overdue vs On-Time by Issue Type</strong></div>
                <div class="card-body table-responsive" style="min-height: 320px; max-height: 320px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead><tr><th>Issue</th><th>Total</th><th>Overdue</th><th>%</th></tr></thead>
                        <tbody>
                        @forelse($reportSlaBreakdown['by_issue'] as $issueName => $row)
                            <tr>
                                <td>{{ $issueName }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>{{ $row['overdue'] }}</td>
                                <td>{{ $row['total'] > 0 ? round(($row['overdue'] / $row['total']) * 100, 1) : 0 }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No data for this range</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong>Reassignment by Division</strong></div>
                <div class="card-body">
                    <div style="height: 220px; position: relative;">
                        <canvas id="reassignmentByDivisionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 320px;">
                    <div class="text-value">{{ $reportReassignment['rate'] }}%</div>
                    <small class="text-muted text-uppercase font-weight-bold">Reassignment Rate</small>
                    <div class="text-muted small mt-1">{{ $reportReassignment['reassigned_count'] }} of {{ $reportReassignment['total'] }} tickets</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    {{-- FIX: Removed the HTML table-responsive wrapper here --}}
                    <table id="latestTicketsTable" class="table table-striped table-hover text-nowrap w-100">
                        <thead>
                        <tr>
                            <th>Reference ID</th>
                            <th>Created by</th>
                            <th>Issue</th>
                            <th>Custom Issue</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($latestTickets as $ticket)
                            <tr>
                                <td>{{ $ticket->reference_id ?? 'N/A'}}</td>
                                <td>{{ $ticket->user->name ?? 'N/A'}}</td>
                                <td>{{ $ticket->issue->issue_description ?? 'N/A'}}</td>
                                <td>{{ $ticket->custom_issue ?? 'N/A'}}</td>
                                <td>{{ $ticket->message ?? 'N/A'}}</td>
                                <td>
                                    @if($ticket->status)
                                        <span class="badge" style="background-color: {{ $ticket->status->status_color }}; color: #000000;">
                                            {{ $ticket->status->status_name }}
                                        </span>
                                    @else
                                        <span>N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if($ticket->priority)
                                        <span class="badge" style="background-color: {{ $ticket->priority->priority_color }}; color: #000000;">
                                            {{ $ticket->priority->priority_name }}
                                        </span>
                                    @else
                                        <span>N/A</span>
                                    @endif
                                </td>
                                <td>{{ optional($ticket->created_at)->format('m/d/Y - g:i A') ?? 'N/A' }}</td>
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
                    {{-- FIX: Removed the HTML table-responsive wrapper here --}}
                    <p class="text-muted small mb-2">Totals below are all-time, not limited to the period above.</p>
                    <table id="userActivityTable" class="table table-striped table-hover text-nowrap w-100">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Division</th>
                            <th>Assigned</th>
                            <th>Total Resolved Tickets</th>
                            <th>Overdue Tickets</th>
                            <th>Avg Resolution</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td>{{ $user->name ?? 'N/A'}}</td>
                                <td>{{ $user->division->division_name ?? 'N/A'}}</td>
                                <td>{{ $user->assigned_count }}</td>
                                <td>{{ $user->resolved_tickets_count}}</td>
                                <td>{{ $user->overdue_tickets_count}}</td>
                                <td>{{ $user->avg_resolution_formatted }}</td>
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
                    {{-- FIX: Removed the HTML table-responsive wrapper here --}}
                    <table id="ticketsPerDivisionTable" class="table table-striped table-hover text-nowrap w-100">
                        <thead>
                        <tr>
                            <th>Issue description</th>
                            <th>Division</th>
                            <th>Total tickets</th>
                            <th>Average resolve time per ticket</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($ticketOverview as $row)
                            <tr>
                                <td>{{ $row->issue_description ?? 'N/A' }}</td>
                                <td>{{ $row->division_name ?? 'N/A' }}</td>
                                <td>{{ $row->total_tickets }}</td>
                                <td>{{ $row->formatted_time }}</td>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label>Start Date <span class="text-danger">*</span></label>
                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="{{ now()->startOfYear()->format('Y-m-d') }}"
                            required
                        >
                    </div>

                    <div class="form-group">
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
                    <button type="button" class="btn btn-light" data-dismiss="modal">
                        Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('after_styles')

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">

<style>

/* Align DataTables controls nicely */
div.dataTables_wrapper div.dataTables_length select {
    width: auto;
    display: inline-block;
    min-width: 3.6rem;
}

</style>

@endpush

@push('after_scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const reportVolumeTrendLabels = @json($reportVolumeTrend['labels']);
const reportVolumeTrendCounts = @json($reportVolumeTrend['counts']);
const reportResolutionLabels = @json($reportResolutionDistribution['labels']);
const reportResolutionCounts = @json($reportResolutionDistribution['counts']);
const reportColorPalette = ['#FF6384', '#36A2EB', '#4BC0C0', '#FFCE56', '#9966FF', '#FF9F40', '#C9CBCF', '#4D5360'];
const reportSlaDeptLabels = @json($reportSlaBreakdown['department_labels']);
const reportSlaDeptOverdue = @json($reportSlaBreakdown['department_overdue']);
const reportSlaDeptOnTime = @json($reportSlaBreakdown['department_on_time']);
const reportStatusFunnelLabels = @json($reportStatusFunnel['labels']);
const reportStatusFunnelCounts = @json($reportStatusFunnel['counts']);
const reportReassignDivisionLabels = @json($reportReassignment['division_labels']);
const reportReassignDivisionRates = @json($reportReassignment['division_rates']);

function buildReportsUrl(params) {
    const url = new URL(window.location.href);
    url.search = '';
    Object.keys(params).forEach(function (key) {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
            url.searchParams.set(key, params[key]);
        }
    });
    return url.toString();
}

function initVolumeTrendChart() {
    const el = document.getElementById('volumeTrendChart');
    if (!el || !reportVolumeTrendLabels.length) return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: reportVolumeTrendLabels,
            datasets: [{
                label: 'Tickets Created',
                data: reportVolumeTrendCounts,
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                fill: true,
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

function initResolutionDistributionChart() {
    const el = document.getElementById('resolutionDistributionChart');
    if (!el || !reportResolutionLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportResolutionLabels,
            datasets: [{
                label: 'Resolved Tickets',
                data: reportResolutionCounts,
                backgroundColor: reportColorPalette
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

// Not called while the by-department chart is hidden (see the conditional block wrapping the department chart above).
function initSlaByDepartmentChart() {
    const el = document.getElementById('slaByDepartmentChart');
    if (!el || !reportSlaDeptLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportSlaDeptLabels,
            datasets: [
                { label: 'On-Time', data: reportSlaDeptOnTime, backgroundColor: '#28a745' },
                { label: 'Overdue', data: reportSlaDeptOverdue, backgroundColor: '#dc3545' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

function initStatusFunnelChart() {
    const el = document.getElementById('statusFunnelChart');
    if (!el || !reportStatusFunnelLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportStatusFunnelLabels,
            datasets: [{
                label: 'Tickets',
                data: reportStatusFunnelCounts,
                backgroundColor: reportColorPalette
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

function initReassignmentByDivisionChart() {
    const el = document.getElementById('reassignmentByDivisionChart');
    if (!el || !reportReassignDivisionLabels.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: reportReassignDivisionLabels,
            datasets: [{
                label: 'Reassignment Rate %',
                data: reportReassignDivisionRates,
                backgroundColor: '#FF9F40'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });
}

$(document).ready(function () {
    initVolumeTrendChart();
    initResolutionDistributionChart();
    initStatusFunnelChart();
    initReassignmentByDivisionChart();

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

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>

    $(document).ready(function () {
        const modal = document.getElementById('downloadReportModal');
        if (modal) {
            document.body.appendChild(modal);
        }
    });

    $(document).ready(function () {

        let LatestTicketTable = $('#latestTicketsTable').DataTable({
            pageLength: 10,
            responsive: false,
            ordering: true,
            searching: true,

            // FIX: Added 'table-responsive' directly to the 'tr' (table rows) wrapper part of the DOM string
            dom:
            "<'row align-items-center'<'col-md-6 latest-title'><'col-md-6'f>>" +
            "<'row'<'col-sm-12 table-responsive'tr>>" +
            "<'row'<'col-sm-6'l><'col-sm-6'p>>"
        });

        $('.latest-title').html('<h5 class="mb-2 font-weight-bold">Latest Tickets</h5>');

        let UserActivityTable = $('#userActivityTable').DataTable({
            pageLength: 10,
            responsive: false,
            ordering: true,
            searching: true,

            // FIX: Added 'table-responsive' here too
            dom:
            "<'row align-items-center'<'col-md-6 user-activity'><'col-md-6'f>>" +
            "<'row'<'col-sm-12 table-responsive'tr>>" +
            "<'row'<'col-sm-6'l><'col-sm-6'p>>"
        });

        $('.user-activity').html('<h5 class="mb-2 font-weight-bold">User Activity</h5>');

        let TicketOverviewTable = $('#ticketsPerDivisionTable').DataTable({
            pageLength: 10,
            responsive: false,
            ordering: true,
            searching: true,

            // FIX: Added 'table-responsive' here too
            dom:
            "<'row align-items-center'<'col-md-6 overview-title'><'col-md-6'f>>" +
            "<'row'<'col-sm-12 table-responsive'tr>>" +
            "<'row'<'col-sm-6'l><'col-sm-6'p>>"
            });

        $('.overview-title').html('<h5 class="mb-2 font-weight-bold">Tickets per Category Overview</h5>');
    });
</script>

@endpush
