@extends(backpack_view('blank'))

@php

$latestTickets = App\Models\Ticket::latest()->take(10)->get();

$abbreviationMap = [
    'Administrative'           => 'Admin',
    'Claims and Benefits'      => 'Claims',
    'Information Technology'   => 'IT',
    'Learning and Development' => 'L&D',
    'Performance Management'   => 'PM',
    'Payroll'                  => 'Payroll',
    'Records'                  => 'Records',
    'RSP'                      => 'RSP',
];

// Define specific colors for each abbreviation
$colorPalette = [
    'Admin'     => '#FF6384', // Pinkish
    'Claims'    => '#36A2EB', // Blue
    'IT'        => '#4BC0C0', // Teal
    'L&D'       => '#FFCE56', // Yellow
    'PM'        => '#9966FF', // Purple
    'Payroll'   => '#FF9F40', // Orange
    'Records'   => '#C9CBCF', // Grey
    'RSP'       => '#4D5360', // Dark Grey
];

$ticketsPerDivisionRaw = \App\Models\Division::leftJoin('tickets', 'tickets.division_id', '=', 'divisions.id')
    ->join('departments', 'divisions.department_id', '=', 'departments.id')
    ->where('divisions.division_name', '!=', 'Department Head')
    ->selectRaw('divisions.division_name, COUNT(tickets.id) as total')
    ->groupBy('divisions.division_name')
    ->orderBy('divisions.division_name')
    ->get();

$divisionLabels = [];
$divisionCounts = [];
$divisionColors = [];

foreach ($ticketsPerDivisionRaw as $item) {
    $shortName = $abbreviationMap[$item->division_name] ?? $item->division_name;
    $divisionLabels[] = $shortName;
    $divisionCounts[] = $item->total;
    $divisionColors[] = $colorPalette[$shortName] ?? '#36A2EB';
}

$statusData = \App\Models\Status::withCount('tickets')->get();
$labels = $statusData->pluck('status_name');
$data = $statusData->pluck('tickets_count');
$colors = $statusData->pluck('status_color');

// --- Reports widgets, fixed to all-time ---
// Reuses App\Services\TicketReportWidgets — the exact same SLA/overdue
// rule, resolution-time bucketing, and reassignment scoping that
// ReportsController uses for the Reports page's "All Time" period, so this
// logic lives in one place instead of drifting between the two pages.

$dashResolvedStatusId = (int) \App\Models\Status::where('status_name', 'Resolved')->value('id');
$dashPendingStatusId = (int) \App\Models\Status::where('status_name', 'Pending')->value('id');
$dashReopenedStatusId = (int) \App\Models\Status::where('status_name', 'Reopened')->value('id');

$dashAllTickets = \App\Models\Ticket::with(['department', 'division', 'issue'])
    ->get(['id', 'status_id', 'department_id', 'division_id', 'issue_id', 'custom_issue', 'created_at', 'resolved_at']);

$dashEarliestCreatedAt = $dashAllTickets->min('created_at');
$dashWidgetStart = $dashEarliestCreatedAt ? \Carbon\Carbon::parse($dashEarliestCreatedAt)->startOfDay() : now()->startOfDay();
$dashWidgetEnd = now()->endOfDay();

$dashVolumeTrend = \App\Services\TicketReportWidgets::buildVolumeTrendWidget($dashAllTickets, $dashWidgetStart, $dashWidgetEnd, 'month');
$volumeTrendLabels = $dashVolumeTrend['labels'];
$volumeTrendCounts = $dashVolumeTrend['counts'];

$dashResolutionDistribution = \App\Services\TicketReportWidgets::buildResolutionDistributionWidget($dashAllTickets, $dashResolvedStatusId);
$resolutionLabels = $dashResolutionDistribution['labels'];
$resolutionCounts = $dashResolutionDistribution['counts'];

$dashSlaBreakdown = \App\Services\TicketReportWidgets::buildSlaBreakdownWidget($dashAllTickets, $dashResolvedStatusId);
$overdueByIssue = $dashSlaBreakdown['by_issue'];

$dashReassignment = \App\Services\TicketReportWidgets::buildReassignmentWidget($dashAllTickets, $dashWidgetStart, $dashWidgetEnd);
$reassignedCount = $dashReassignment['reassigned_count'];
$reassignmentTotal = $dashReassignment['total'];
$reassignmentRate = $dashReassignment['rate'];
$reassignDivisionLabels = $dashReassignment['division_labels'];
$reassignDivisionRates = $dashReassignment['division_rates'];

$dashKpis = \App\Services\TicketReportWidgets::buildKpiWidget($dashAllTickets, $dashResolvedStatusId, $dashPendingStatusId, $dashReopenedStatusId);
@endphp

@section('content')

    <div class="container-fluid">
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
                            <div class="text-value">{{ \App\Models\Ticket::count() }}</div><small class="text-muted text-uppercase font-weight-bold">Total Tickets</small>
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
                        Resolution Time Distribution <small class="text-muted">(all time)</small>
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
                                                <span class="badge" style="background-color: {{ $ticket->status->status_color }}; color: #444;">
                                                    {{ $ticket->status->status_name }}
                                                </span>
                                            @else
                                                <span>N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($ticket->priority)
                                                <span class="badge" style="background-color: {{ $ticket->priority->priority_color }}; color: #444;">
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

        {{-- Reports widgets (all-time) --}}
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-header font-weight-bold">
                        Ticket Volume Trend <small class="text-muted">(all time)</small>
                    </div>
                    <div class="card-body" style="height:300px; position: relative;">
                        <canvas id="dashVolumeTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header font-weight-bold">
                        Overdue vs On-Time by Issue Type <small class="text-muted">(all time)</small>
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
                        <div class="text-muted small mt-1">{{ $reassignedCount }} of {{ $reassignmentTotal }} tickets (all time)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-header font-weight-bold">
                        Reassignment by Division <small class="text-muted">(all time)</small>
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

const divisionLabels = {!! json_encode($divisionLabels) !!};
const divisionCounts = {!! json_encode($divisionCounts) !!};
const divisionColors = {!! json_encode($divisionColors) !!};

const statusLabels = @json($labels);
const statusData = @json($data);
const statusColors = @json($colors);

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

// --- Reports widgets (all-time), mirrored from the Reports page ---

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
});

</script>

@endsection
