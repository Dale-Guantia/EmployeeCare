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
        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-dark">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="la la-list"></i></div>
                    <div class="text-value">{{ $summaryCards['total_surveys'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Total Surveys</small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="la la-smile"></i></div>
                    <div class="text-value">{{ $summaryCards['very_satisfied'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Very Satisfied</small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="la la-meh"></i></div>
                    <div class="text-value">{{ $summaryCards['satisfied'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Satisfied</small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="la la-frown"></i></div>
                    <div class="text-value">{{ $summaryCards['dissatisfied'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Dissatisfied</small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-md-2">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="la la-angry"></i></div>
                    <div class="text-value">{{ $summaryCards['very_dissatisfied'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Very Dissatisfied</small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-md-2">
            <div class="card text-white" style="background-color: #7c69ef;">
                <div class="card-body">
                    <div class="h1 text-muted text-right"><i class="la la-users"></i></div>
                    <div class="text-value">{{ $summaryCards['staff_rated'] }}</div>
                    <small class="text-muted text-uppercase font-weight-bold">Staff Rated</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    {{-- FIX: Removed the HTML table-responsive wrapper here --}}
                    <table id="surveyResponsesTable" class="table table-striped table-hover text-nowrap w-100">
                        <thead>
                        <tr>
                            <th>Staff Rated</th>
                            <th>Division</th>
                            <th>Service</th>
                            <th>Timeliness</th>
                            <th>Client Handling</th>
                            <th>Quality of Service</th>
                            <th>Overall Satisfaction</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($responses as $response)
                                <tr>
                                    <td>{{ optional($response->staff)->name ?? 'N/A' }}</td>
                                    <td>{{ optional(optional($response->staff)->division)->division_name ?? 'N/A' }}</td>
                                    <td>{{ optional($response->service)->issue_description ?? 'N/A' }}</td>
                                    <td>{{ $response->timeliness_rating ?? 'N/A' }}</td>
                                    <td>{{ $response->handling_rating ?? 'N/A' }}</td>
                                    <td>{{ $response->quality_rating ?? 'N/A' }}</td>
                                    <td>{{ $response->overall_rating ?? 'N/A' }}</td>
                                    <td>{{ optional($response->created_at)->format('m/d/Y - g:i A') ?? 'N/A' }}</td>
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
                    <table id="surveyRatingsTable" class="table table-striped table-hover text-nowrap w-100">
                        <thead>
                        <tr>
                            <th>Staff Name</th>
                            <th>Total Surveys</th>
                            <th>Very Dissatisfied</th>
                            <th>Dissatisfied</th>
                            <th>Satisfied</th>
                            <th>Very Satisfied</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($surveyRatings as $staff)
                                <tr>
                                    <td>{{ $staff->name }}</td>
                                    <td>{{ $staff->total_surveys }}</td>
                                    <td>{{ $staff->very_dissatisfied_count }}</td>
                                    <td>{{ $staff->dissatisfied_count }}</td>
                                    <td>{{ $staff->satisfied_count }}</td>
                                    <td>{{ $staff->very_satisfied_count }}</td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-link text-primary view-details-btn"
                                            data-toggle="modal"
                                            data-target="#surveyDetailsModal"
                                            data-staff='@json($staff)'
                                        >
                                            <i class="la la-eye"></i> View Details
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="downloadSurveyReportModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('page.survey_reports.download_pdf') }}" target="_blank">
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

    <div class="modal fade" id="surveyDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Survey Details for: <strong id="detailStaffName">-</strong>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <p class="mb-3">Total Surveys: <strong id="detailTotalSurveys">0</strong></p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped text-nowrap mb-0">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th class="text-center">
                                        <span class="badge badge-danger w-100 py-2">Very Dissatisfied</span>
                                    </th>
                                    <th class="text-center">
                                        <span class="badge badge-warning w-100 py-2">Dissatisfied</span>
                                    </th>
                                    <th class="text-center">
                                        <span class="badge badge-primary w-100 py-2">Satisfied</span>
                                    </th>
                                    <th class="text-center">
                                        <span class="badge badge-success w-100 py-2">Very Satisfied</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>Timeliness</th>
                                    <td class="text-center" id="timelinessVeryDissatisfied">0</td>
                                    <td class="text-center" id="timelinessDissatisfied">0</td>
                                    <td class="text-center" id="timelinessSatisfied">0</td>
                                    <td class="text-center" id="timelinessVerySatisfied">0</td>
                                </tr>
                                <tr>
                                    <th>Client Handling</th>
                                    <td class="text-center" id="handlingVeryDissatisfied">0</td>
                                    <td class="text-center" id="handlingDissatisfied">0</td>
                                    <td class="text-center" id="handlingSatisfied">0</td>
                                    <td class="text-center" id="handlingVerySatisfied">0</td>
                                </tr>
                                <tr>
                                    <th>Quality of Service</th>
                                    <td class="text-center" id="qualityVeryDissatisfied">0</td>
                                    <td class="text-center" id="qualityDissatisfied">0</td>
                                    <td class="text-center" id="qualitySatisfied">0</td>
                                    <td class="text-center" id="qualityVerySatisfied">0</td>
                                </tr>
                                <tr>
                                    <th>Overall Satisfaction</th>
                                    <td class="text-center" id="overallVeryDissatisfied">0</td>
                                    <td class="text-center" id="overallDissatisfied">0</td>
                                    <td class="text-center" id="overallSatisfied">0</td>
                                    <td class="text-center" id="overallVerySatisfied">0</td>
                                </tr>
                                <tr class="font-weight-bold">
                                    <th>Total Count</th>
                                    <td class="text-center" id="totalVeryDissatisfied">0</td>
                                    <td class="text-center" id="totalDissatisfied">0</td>
                                    <td class="text-center" id="totalSatisfied">0</td>
                                    <td class="text-center" id="totalVerySatisfied">0</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">

    <style>
        div.dataTables_wrapper div.dataTables_length select {
            width: auto;
            display: inline-block;
            min-width: 3.6rem;
        }

        .survey-header-wrap {
            padding-bottom: 15px;
        }
    </style>
@endpush

@push('after_scripts')
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function () {
            const reportModal = document.getElementById('downloadSurveyReportModal');
            if (reportModal) {
                document.body.appendChild(reportModal);
            }

            const detailsModal = document.getElementById('surveyDetailsModal');
            if (detailsModal) {
                document.body.appendChild(detailsModal);
            }

            $('#surveyResponsesTable').DataTable({
                pageLength: 10,
                responsive: false,
                ordering: true,
                searching: true,
                // FIX: Added 'table-responsive' directly to the 'tr' wrapper
                dom:
                    "<'row align-items-center mb-2'<'col-md-6 responses-title'><'col-md-6'f>>" +
                    "<'row'<'col-sm-12 table-responsive'tr>>" +
                    "<'row mt-2'<'col-sm-6'l><'col-sm-6'p>>"
            });

            $('.responses-title').html('<h5 class="mb-0 font-weight-bold">Survey Responses</h5>');

            $('#surveyRatingsTable').DataTable({
                pageLength: 10,
                responsive: false,
                ordering: true,
                searching: true,
                // FIX: Added 'table-responsive' here too
                dom:
                    "<'row align-items-center mb-2'<'col-md-6 ratings-title'><'col-md-6'f>>" +
                    "<'row'<'col-sm-12 table-responsive'tr>>" +
                    "<'row mt-2'<'col-sm-6'l><'col-sm-6'p>>"
            });

            $('.ratings-title').html('<h5 class="mb-0 font-weight-bold">Survey Rating</h5>');

            $(document).on('click', '.view-details-btn', function () {
                const staff = $(this).data('staff') || {};
                const details = staff.details || {};
                const timeliness = details.timeliness || {};
                const handling = details.handling || {};
                const quality = details.quality || {};
                const overall = details.overall || {};

                $('#detailStaffName').text(staff.name || '-');
                $('#detailTotalSurveys').text(staff.total_surveys || 0);

                $('#timelinessVeryDissatisfied').text(timeliness.very_dissatisfied || 0);
                $('#timelinessDissatisfied').text(timeliness.dissatisfied || 0);
                $('#timelinessSatisfied').text(timeliness.satisfied || 0);
                $('#timelinessVerySatisfied').text(timeliness.very_satisfied || 0);

                $('#handlingVeryDissatisfied').text(handling.very_dissatisfied || 0);
                $('#handlingDissatisfied').text(handling.dissatisfied || 0);
                $('#handlingSatisfied').text(handling.satisfied || 0);
                $('#handlingVerySatisfied').text(handling.very_satisfied || 0);

                $('#qualityVeryDissatisfied').text(quality.very_dissatisfied || 0);
                $('#qualityDissatisfied').text(quality.dissatisfied || 0);
                $('#qualitySatisfied').text(quality.satisfied || 0);
                $('#qualityVerySatisfied').text(quality.very_satisfied || 0);

                $('#overallVeryDissatisfied').text(overall.very_dissatisfied || 0);
                $('#overallDissatisfied').text(overall.dissatisfied || 0);
                $('#overallSatisfied').text(overall.satisfied || 0);
                $('#overallVerySatisfied').text(overall.very_satisfied || 0);

                const totalVeryDissatisfied =
                    (timeliness.very_dissatisfied || 0) +
                    (handling.very_dissatisfied || 0) +
                    (quality.very_dissatisfied || 0) +
                    (overall.very_dissatisfied || 0);

                const totalDissatisfied =
                    (timeliness.dissatisfied || 0) +
                    (handling.dissatisfied || 0) +
                    (quality.dissatisfied || 0) +
                    (overall.dissatisfied || 0);

                const totalSatisfied =
                    (timeliness.satisfied || 0) +
                    (handling.satisfied || 0) +
                    (quality.satisfied || 0) +
                    (overall.satisfied || 0);

                const totalVerySatisfied =
                    (timeliness.very_satisfied || 0) +
                    (handling.very_satisfied || 0) +
                    (quality.very_satisfied || 0) +
                    (overall.very_satisfied || 0);

                $('#totalVeryDissatisfied').text(totalVeryDissatisfied);
                $('#totalDissatisfied').text(totalDissatisfied);
                $('#totalSatisfied').text(totalSatisfied);
                $('#totalVerySatisfied').text(totalVerySatisfied);
            });
        });
    </script>
@endpush
