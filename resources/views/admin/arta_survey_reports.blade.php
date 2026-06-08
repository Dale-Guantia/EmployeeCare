@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid d-flex justify-content-between align-items-center mb-3 survey-header-wrap">
        <h2 class="mb-0">
            <span class="text-capitalize">{{ $title }}</span>
        </h2>

        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#downloadSurveyReportModal">
            <i class="la la-download"></i> Download Report
        </button>
    </section>
@endsection

@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    {{-- Notice: No .table-responsive wrapper here --}}
                    <table id="surveyResponsesTable" class="table table-striped table-hover text-nowrap w-100">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client Type</th>
                            <th>Service Availed</th>
                            <th>CC1</th>
                            <th>CC2</th>
                            <th>CC3</th>
                            <th>SQD0</th>
                            <th>SQD1</th>
                            <th>SQD2</th>
                            <th>SQD3</th>
                            <th>SQD4</th>
                            <th>SQD5</th>
                            <th>SQD6</th>
                            <th>SQD7</th>
                            <th>SQD8</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($responses as $response)
                                <tr>
                                    <td>{{ optional($response->created_at)->format('m/d/Y - g:i A') ?? 'N/A' }}</td>
                                    <td>{{ $response->client_type ?? 'N/A' }}</td>
                                    <td>{{ $response->service_availed ?? 'N/A' }}</td>
                                    <td>{{ $response->cc1 ?? 'N/A' }}</td>
                                    <td>{{ $response->cc2 ?? 'N/A' }}</td>
                                    <td>{{ $response->cc3 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd0 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd1 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd2 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd3 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd4 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd5 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd6 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd7 ?? 'N/A' }}</td>
                                    <td>{{ $response->sqd8 ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal for PDF Download --}}
    <div class="modal fade" id="downloadSurveyReportModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('page.arta_survey_reports.download_pdf') }}" target="_blank">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Select Date Range for ARTA Report</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label>Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="{{ now()->startOfYear()->format('Y-m-d') }}" required>
                        </div>
                        <div class="form-group">
                            <label>End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('after_styles')
    {{-- DataTables Core + Responsive CSS --}}
    <link rel="stylesheet" href="{{ asset('public-assets/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public-assets/css/responsive.bootstrap4.min.css') }}">

    <style>
        /* Custom Styling for DataTables Responsive Plus/Minus Buttons */
        table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
        table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
            background-color: #28a745 !important; /* Green plus */
            color: white !important;
            border: none !important;
            box-shadow: none !important;
            border-radius: 50% !important;
            content: '+' !important;
            font-weight: bold !important;

            /* ADDED FIX: Force a perfect square so border-radius makes a perfect circle */
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 20px !important;
            height: 20px !important;
            line-height: 20px !important;
            font-size: 16px !important;
        }

        table.dataTable.dtr-inline.collapsed > tbody > tr.parent > td.dtr-control:before,
        table.dataTable.dtr-inline.collapsed > tbody > tr.parent > th.dtr-control:before {
            background-color: #dc3545 !important; /* Red minus */
            content: '-' !important;
        }
    </style>
@endpush

@push('after_scripts')
    {{-- DataTables Core + Responsive JS --}}
    <script src="{{ asset('public-assets/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('public-assets/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('public-assets/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('public-assets/js/responsive.bootstrap4.min.js') }}"></script>

    <script>
        $(document).ready(function () {
            const reportModal = document.getElementById('downloadSurveyReportModal');
            if (reportModal) document.body.appendChild(reportModal);

            $('#surveyResponsesTable').DataTable({
                pageLength: 10,
                responsive: true, // Activated Responsive feature
                ordering: true,
                searching: true,
                // Removed 'table-responsive' wrapper class from the DOM string
                dom: "<'row align-items-center mb-2'<'col-md-6 responses-title'><'col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row mt-2'<'col-sm-6'l><'col-sm-6'p>>"
            });
            $('.responses-title').html('<h5 class="mb-0 font-weight-bold">ARTA Responses</h5>');

            $('#surveyRatingsTable').DataTable({
                pageLength: 10,
                responsive: true, // Activated Responsive feature
                ordering: true,
                searching: true,
                // Removed 'table-responsive' wrapper class from the DOM string
                dom: "<'row align-items-center mb-2'<'col-md-6 ratings-title'><'col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row mt-2'<'col-sm-6'l><'col-sm-6'p>>"
            });
            $('.ratings-title').html('<h5 class="mb-0 font-weight-bold">Satisfaction grouped by Service</h5>');
        });
    </script>
@endpush
