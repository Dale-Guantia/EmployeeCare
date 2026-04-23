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
                <div class="card text-white bg-success">
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
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-building"></i></div>
                            <div class="text-value">{{ \App\Models\Department::count() }}</div><small class="text-muted text-uppercase font-weight-bold">Total Departments</small>
                    </div>
                </div>
            </div>
            <!-- /.col-->
            <div class="col-sm-6 col-md-2">
                <div class="card text-white" style="background-color: #17a2b8;">
                    <div class="card-body">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-sitemap"></i></div>
                            <div class="text-value">{{ \App\Models\Division::count() }}</div><small class="text-muted text-uppercase font-weight-bold">Total Divisions</small>
                    </div>
                </div>
            </div>
            <!-- /.col-->
            <div class="col-sm-6 col-md-2">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-exclamation-circle"></i></div>
                            <div class="text-value">{{ \App\Models\Issue::count() }}</div><small class="text-muted text-uppercase font-weight-bold">Total Issues</small>
                    </div>
                </div>
            </div>
            <!-- /.col-->
        </div>

        {{-- Charts Row --}}
        <div class="row">
            {{-- Tickets per Division --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        Tickets per Division
                    </div>

                    <div style="height:400px;">
                        <canvas id="divisionChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Tickets Overview --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        Tickets Overview
                    </div>

                    <div class="card-body" style="height:400px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Latest Tickets -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Latest Tickets</div>

                    {{-- ADDED: table-responsive wrapper and card-body p-0 for flush edges --}}
                    <div class="card-body p-0">
                        <div class="table-responsive">

                            {{-- ADDED: text-nowrap to prevent ugly word stacking on mobile --}}
                            <table class="table table-bordered text-nowrap mb-0">
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
        </div>
        <!-- End of Latest Tickets -->
    </div>
</div>

@endsection

@section('after_scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

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

</script>

@endsection
