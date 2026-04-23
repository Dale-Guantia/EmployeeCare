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
    <div class="row">
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-dark">
                <div class="card-body" style="">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-users"></i></div>
                        <div class="text-value">{{ \App\Models\User::count() }}</div><small class="text-muted text-uppercase font-weight-bold">Users</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white" style="background-color: #7c69ef;">
                    <div class="card-body">
                        <div class="h1 text-muted text-right"><i class="nav-icon la la-ticket"></i></div>
                            <div class="text-value">{{ \App\Models\Ticket::count() }}</div><small class="text-muted text-uppercase font-weight-bold">Total Tickets</small>
                    </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-ticket"></i></div>
                        <div class="text-value">{{ \App\Models\Ticket::where('status_id', 1)->count() }}</div><small class="text-muted text-uppercase font-weight-bold">Resolved Tickets</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-ticket"></i></div>
                        <div class="text-value">{{ \App\Models\Ticket::where('status_id', 2)->count() }}</div><small class="text-muted text-uppercase font-weight-bold">Pending Tickets</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white" style="background-color: #abbcd5;">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-ticket"></i></div>
                        <div class="text-value">{{ \App\Models\Ticket::where('status_id', 3)->count() }}</div><small class="text-muted text-uppercase font-weight-bold">Unassigned Tickets</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="nav-icon la la-ticket"></i></div>
                        <div class="text-value">{{ \App\Models\Ticket::where('status_id', 4)->count() }}</div><small class="text-muted text-uppercase font-weight-bold">Reopened Tickets</small>
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
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    {{-- FIX: Removed the HTML table-responsive wrapper here --}}
                    <table id="userActivityTable" class="table table-striped table-hover text-nowrap w-100">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Division</th>
                            <th>Total Resolved Tickets</th>
                            <th>Overdue Tickets</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td>{{ $user->name ?? 'N/A'}}</td>
                                <td>{{ $user->division->division_name ?? 'N/A'}}</td>
                                <td>{{ $user->resolved_tickets_count}}</td>
                                <td>{{ $user->overdue_tickets_count}}</td>
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
