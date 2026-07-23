@extends(backpack_view('blank'))

@section('content')

    <div class="container-fluid">
        @if($periodError)
            <div class="row mb-2">
                <div class="col-12">
                    <div class="alert alert-warning py-2 mb-0">{{ $periodError }}</div>
                </div>
            </div>
        @endif

        <div class="row mb-3 align-items-end" id="dashboardPeriodControl">
            <div class="col-auto">
                <label for="dashPeriodSelect" class="small text-muted text-uppercase font-weight-bold mb-1">Date Range</label>
                <select id="dashPeriodSelect" class="form-control form-control-sm">
                    <option value="all" {{ $activePeriod === 'all' ? 'selected' : '' }}>All Time</option>
                    <option value="month" {{ $activePeriod === 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="half" {{ $activePeriod === 'half' ? 'selected' : '' }}>Semestral</option>
                    <option value="custom" {{ $activePeriod === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
            </div>

            <div class="col-auto" id="dashHalfToggleWrap" style="{{ $activePeriod === 'half' ? '' : 'display:none;' }}">
                <label for="dashHalfSelect" class="small text-muted text-uppercase font-weight-bold mb-1">Half</label>
                <select id="dashHalfSelect" class="form-control form-control-sm">
                    <option value="h1" {{ $activeHalf === 'h1' ? 'selected' : '' }}>Jan &ndash; Jun {{ $activeYear }}</option>
                    <option value="h2" {{ $activeHalf === 'h2' ? 'selected' : '' }}>Jul &ndash; Dec {{ $activeYear }}</option>
                </select>
            </div>

            <div class="col-auto" id="dashCustomRangeWrap" style="{{ $activePeriod === 'custom' ? '' : 'display:none;' }}">
                <div class="form-row align-items-end">
                    <div class="col-auto">
                        <label for="dashCustomStart" class="small text-muted text-uppercase font-weight-bold mb-1">Start Date</label>
                        <input type="date" id="dashCustomStart" class="form-control form-control-sm" value="{{ request('start') }}">
                    </div>
                    <div class="col-auto">
                        <label for="dashCustomEnd" class="small text-muted text-uppercase font-weight-bold mb-1">End Date</label>
                        <input type="date" id="dashCustomEnd" class="form-control form-control-sm" value="{{ request('end') }}">
                    </div>
                    <div class="col-auto">
                        <button type="button" id="dashCustomApplyBtn" class="btn btn-sm btn-primary">Apply</button>
                    </div>
                </div>
            </div>

            <div class="col-auto ml-auto">
                <small class="text-muted">Data shown for: {{ $dashWidgetStart->format('M j, Y') }} &ndash; {{ $dashWidgetEnd->format('M j, Y') }}</small>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-6 col-md-2">
                <div class="card text-white bg-dark">
                    <div class="card-body" style="">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-users"></i></div>
                            <div class="text-value">{{ \App\Models\User::activeToday()->count() }}</div><small class="text-muted text-uppercase font-weight-bold">Visitors</small>
                    </div>
                </div>
            </div>
            <!-- /.col-->
            <div class="col-sm-6 col-md-2">
                <div class="card text-white bg-green">
                        <div class="card-body">
                            <div class="h1 text-muted text-right"><i class="nav-icon la la-user"></i></div>
                                <div class="text-value">{{ \App\Models\User::count() }}</div><small class="text-muted text-uppercase font-weight-bold">Total Users</small>
                        </div>
                </div>
            </div>
            <!-- /.col-->
            <div class="col-sm-6 col-md-2">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-ticket"></i></div>
                            <div class="text-value">{{ $dashKpis['total'] }}</div><small class="text-muted text-uppercase font-weight-bold">Total Tickets</small>
                    </div>
                </div>
            </div>
            <!-- /.col-->
            <div class="col-sm-6 col-md-2">
                <div class="card text-white" style="background-color: #9966FF;">
                    <div class="card-body">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-user-clock"></i></div>
                            <div class="text-value">{{ $dashKpis['avg_resolution_formatted'] }}</div><small class="text-muted text-uppercase font-weight-bold">Avg Resolution</small>
                    </div>
                </div>
            </div>
            <!-- /.col-->
            <div class="col-sm-6 col-md-2">
                <div class="card text-white" style="background-color: {{ $dashKpis['overdue_pct'] >= 20 ? '#de4759' : '#41ba96' }};">
                    <div class="card-body">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-exclamation-circle"></i></div>
                            <div class="text-value">{{ $dashKpis['overdue_pct'] }}%</div><small class="text-muted text-uppercase font-weight-bold">Overdue (SLA)</small>
                    </div>
                </div>
            </div>
            <!-- /.col-->
            <div class="col-sm-6 col-md-2">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-undo-alt"></i></div>
                            <div class="text-value">{{ $dashKpis['reopen_rate'] }}%</div><small class="text-muted text-uppercase font-weight-bold">Reopen Rate</small>
                    </div>
                </div>
            </div>
            <!-- /.col-->
        </div>

        {{-- Charts Row --}}
        <div class="row">
            {{-- Tickets per Division --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header font-weight-bold">
                        Tickets per Division
                    </div>

                    <div style="height:300px;">
                        <canvas id="divisionChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Tickets Overview --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header font-weight-bold">
                        Tickets Overview
                    </div>

                    <div class="card-body" style="height:300px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header font-weight-bold">
                        Resolution Time Distribution
                    </div>
                    <div class="card-body" style="height:300px; position: relative;">
                        <canvas id="dashResolutionDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Latest Tickets -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
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
        <!-- End of Latest Tickets -->

        {{-- Reports widgets (scoped to the selected date range) --}}
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-header font-weight-bold">
                        Ticket Volume Trend
                    </div>
                    <div class="card-body" style="height:300px; position: relative;">
                        <canvas id="dashVolumeTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header font-weight-bold">
                        Overdue vs On-Time by Issue Type
                    </div>
                    <div class="card-body table-responsive" style="min-height: 320px; max-height: 320px; overflow-y: auto; font-size: 0.85rem;">
                        <table class="table table-sm">
                            <thead><tr><th>Issue</th><th>Total</th><th>Overdue</th><th>%</th></tr></thead>
                            <tbody>
                            @forelse($overdueByIssue as $issueName => $row)
                                <tr>
                                    <td>{{ $issueName }}</td>
                                    <td>{{ $row['total'] }}</td>
                                    <td>{{ $row['overdue'] }}</td>
                                    <td>{{ $row['total'] > 0 ? round(($row['overdue'] / $row['total']) * 100, 1) : 0 }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 300px;">
                        <div class="text-value">{{ $reassignmentRate }}%</div>
                        <small class="text-muted text-uppercase font-weight-bold">Reassignment Rate</small>
                        <div class="text-muted small mt-1">{{ $reassignedCount }} of {{ $reassignmentTotal }} tickets</div>
                    </div>
                </div>
            </div>
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-header font-weight-bold">
                        Reassignment by Division
                    </div>
                    <div class="card-body" style="min-height: 300x;">
                        <div style="height: 280px; position: relative;">
                            <canvas id="dashReassignmentByDivisionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('after_styles')

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">

@endsection

@section('after_scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>

const divisionLabels = @json($divisionLabels);
const divisionCounts = @json($divisionCounts);
const divisionColors = @json($divisionColors);

const statusLabels = @json($statusLabels);
const statusData = @json($statusData);
const statusColors = @json($statusColors);

const ctx = document.getElementById('divisionChart');

new Chart(ctx, {
    type: 'bar',
    plugins: [ChartDataLabels],
    data: {
        labels: divisionLabels,
        datasets: [{
            label: 'Tickets',
            data: divisionCounts,
            // Use the array here!
            backgroundColor: divisionColors,
            borderColor: divisionColors,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            // 2. Configure the Data Labels
            datalabels: {
                anchor: 'center',      // Position label at the top of the bar
                align: 'top',       // Align it above the anchor point
                color: '#444',      // Label color
                font: {
                    weight: 'bold',
                    size: 12
                },
                formatter: function(value) {
                    return value;   // Simply return the number (e.g., "2")
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                suggestedMax: Math.max(...divisionCounts) + 1, // Gives space for the label
                ticks: { precision: 0 }
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    }
});


const ctxStatus = document.getElementById('statusChart');

new Chart(ctxStatus, {
    type: 'pie',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusData,
            backgroundColor: statusColors,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed + ' tickets';
                    }
                }
            }
        }
    }
});

// --- Reports widgets, scoped to the selected date range ---

const dashReportColorPalette = ['#FF6384', '#36A2EB', '#4BC0C0', '#FFCE56', '#9966FF', '#FF9F40', '#C9CBCF', '#4D5360'];

const dashVolumeTrendLabels = @json($volumeTrendLabels);
const dashVolumeTrendCounts = @json($volumeTrendCounts);
const dashResolutionLabels = @json($resolutionLabels);
const dashResolutionCounts = @json($resolutionCounts);
const dashReassignDivisionLabels = @json($reassignDivisionLabels);
const dashReassignDivisionRates = @json($reassignDivisionRates);

const dashVolumeTrendEl = document.getElementById('dashVolumeTrendChart');
if (dashVolumeTrendEl && dashVolumeTrendLabels.length) {
    new Chart(dashVolumeTrendEl, {
        type: 'line',
        data: {
            labels: dashVolumeTrendLabels,
            datasets: [{
                label: 'Tickets Created',
                data: dashVolumeTrendCounts,
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

const dashResolutionEl = document.getElementById('dashResolutionDistributionChart');
if (dashResolutionEl && dashResolutionLabels.length) {
    new Chart(dashResolutionEl, {
        type: 'bar',
        data: {
            labels: dashResolutionLabels,
            datasets: [{
                label: 'Resolved Tickets',
                data: dashResolutionCounts,
                backgroundColor: dashReportColorPalette
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

const dashReassignDivisionEl = document.getElementById('dashReassignmentByDivisionChart');
if (dashReassignDivisionEl && dashReassignDivisionLabels.length) {
    new Chart(dashReassignDivisionEl, {
        type: 'bar',
        data: {
            labels: dashReassignDivisionLabels,
            datasets: [{
                label: 'Reassignment Rate %',
                data: dashReassignDivisionRates,
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

function buildDashboardUrl(params) {
    const url = new URL(window.location.href);
    url.search = '';
    Object.keys(params).forEach(function (key) {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
            url.searchParams.set(key, params[key]);
        }
    });
    return url.toString();
}

$(document).ready(function () {
    $('#latestTicketsTable').DataTable({
        pageLength: 10,
        responsive: false,
        ordering: true,
        searching: true,
        dom:
        "<'row align-items-center'<'col-md-6 latest-title'><'col-md-6'f>>" +
        "<'row'<'col-sm-12 table-responsive'tr>>" +
        "<'row align-items-center'<'col-sm-4'l><'col-sm-4'><'col-sm-4 text-right'p>>"
    });

    $('.latest-title').html('<h5 class="mb-2 font-weight-bold">Latest Tickets</h5>');

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
