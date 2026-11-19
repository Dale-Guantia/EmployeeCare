@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid d-flex justify-content-between align-items-center mb-3 survey-header-wrap">
        <h2 class="mb-0">
            <span class="text-capitalize">{{ $title }}</span>
        </h2>

        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#downloadSurveyReportModal">
            <i class="la la-download"></i> Download Report
        </button>
    </section>
@endsection

@section('content')
<div class="ec-analytics ec-reports ec-survey-report">
    @include('admin.partials.survey_report_filters', ['showOfficeFilters' => true])
    @php
        $cards = [];
        foreach ([['total_surveys', 'Total Surveys', 'la-list', 'bg-dark'], ['very_satisfied', 'Very Satisfied', 'la-smile', 'bg-success'],
            ['satisfied', 'Satisfied', 'la-meh', 'bg-primary'], ['dissatisfied', 'Dissatisfied', 'la-frown', 'bg-warning'],
            ['very_dissatisfied', 'Very Dissatisfied', 'la-angry', 'bg-danger'], ['staff_rated', 'Staff Rated', 'la-users', 'bg-secondary']] as [$key, $label, $icon, $color]) {
            $cards[] = compact('key', 'label', 'icon', 'color') + ['value' => $summaryCards[$key]];
        }
        $cards[5]['background'] = '#6f42c1';
        $topStaff = $surveyRatings->sortByDesc('total_surveys')->take(10)->values();
        $charts = [
            ['title' => 'Survey Rating Distribution', 'unit' => 'Rating answers', 'labels' => ['Very Satisfied', 'Satisfied', 'Dissatisfied', 'Very Dissatisfied'],
                'counts' => [$summaryCards['very_satisfied'], $summaryCards['satisfied'], $summaryCards['dissatisfied'], $summaryCards['very_dissatisfied']],
                'colors' => ['#198754', '#375ca6', '#ffc107', '#b02a37']],
            ['title' => 'Top 10 Rated Staff by Survey Responses', 'labels' => $topStaff->pluck('name')->all(), 'counts' => $topStaff->pluck('total_surveys')->all()],
        ];
    @endphp
    @include('admin.partials.report_kpi_cards', ['cards' => $cards])
    <p class="small text-muted mt-3 mb-0">Totals for the selected filters. Rating counts cover the four survey dimensions; each response can contribute four ratings. Table searches do not change these totals.</p>
    @include('admin.partials.survey_report_charts', ['charts' => $charts])

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <table id="surveyResponsesTable" class="table table-hover w-100 ec-report-table" data-report-table data-report-ajax="{{ route('page.survey_reports.responses', array_filter(['period' => $activePeriod, 'half' => $activeHalf, 'year' => $activeYear, 'range_start' => $filterStart?->toDateString(), 'range_end' => $filterEnd?->toDateString(), 'department_id' => $selectedDepartmentId, 'division_id' => $selectedDivisionId], fn ($value) => $value !== null)) }}" data-report-order-column="8" data-report-empty="No survey responses available." aria-label="Survey Responses">
                        <thead>
                        <tr>
                            <th class="control report-details-cell"><span class="visually-hidden">Details</span></th>
                            <th data-priority="1">Staff Rated</th>
                            <th>Division</th>
                            <th data-priority="2">Service</th>
                            <th data-report-rating="true">Timeliness</th>
                            <th data-report-rating="true">Client Handling</th>
                            <th data-report-rating="true">Quality of Service</th>
                            <th data-report-rating="true" data-priority="3">Overall Satisfaction</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($responses as $response)
                                <tr>
                                    <td class="report-details-cell"><button type="button" class="btn btn-sm btn-outline-secondary report-details-toggle" aria-label="Show additional survey details" aria-expanded="false">+</button></td>
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
                    <table id="surveyRatingsTable" class="table table-hover w-100 ec-report-table" data-report-table data-report-empty="No rated staff available." aria-label="Survey Rating">
                        <thead>
                        <tr>
                            <th class="control report-details-cell"><span class="visually-hidden">Details</span></th>
                            <th data-priority="1">Staff Name</th>
                            <th data-priority="2">Total Surveys</th>
                            <th>Very Dissatisfied</th>
                            <th>Dissatisfied</th>
                            <th>Satisfied</th>
                            <th>Very Satisfied</th>
                            <th data-priority="3">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($surveyRatings as $staff)
                                <tr>
                                    <td class="report-details-cell"><button type="button" class="btn btn-sm btn-outline-secondary report-details-toggle" aria-label="Show additional staff details" aria-expanded="false">+</button></td>
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
                                            data-bs-toggle="modal"
                                            data-bs-target="#surveyDetailsModal"
                                            data-staff="{{ json_encode($staff) }}"
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
                <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                <input type="hidden" name="division_id" value="{{ $selectedDivisionId }}">

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Select Date Range</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Start Date <span class="text-danger">*</span></label>
                            <input
                                type="date"
                                name="start_date"
                                class="form-control"
                                value="{{ $exportStartDate }}"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label>End Date <span class="text-danger">*</span></label>
                            <input
                                type="date"
                                name="end_date"
                                class="form-control"
                                value="{{ ($filterEnd ?? now())->format('Y-m-d') }}"
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

    <div class="modal fade" id="surveyDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Survey Details for: <strong id="detailStaffName">-</strong>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-3">Total Surveys: <strong id="detailTotalSurveys">0</strong></p>

                    <div class="table-responsive">
                        <table class="table table-hover ec-report-table w-100 mb-0">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th class="text-center">
                                        <span class="badge bg-danger w-100 py-2">Very Dissatisfied</span>
                                    </th>
                                    <th class="text-center">
                                        <span class="badge bg-warning w-100 py-2">Dissatisfied</span>
                                    </th>
                                    <th class="text-center">
                                        <span class="badge bg-primary w-100 py-2">Satisfied</span>
                                    </th>
                                    <th class="text-center">
                                        <span class="badge bg-success w-100 py-2">Very Satisfied</span>
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
                                    <th data-report-rating="true" data-priority="3">Overall Satisfaction</th>
                                    <td class="text-center" id="overallVeryDissatisfied">0</td>
                                    <td class="text-center" id="overallDissatisfied">0</td>
                                    <td class="text-center" id="overallSatisfied">0</td>
                                    <td class="text-center" id="overallVerySatisfied">0</td>
                                </tr>
                                <tr class="fw-bold">
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
</div>
@endsection

@push('after_styles')
    @basset(public_path('css/analytics.css'))
    @basset('https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css')
    @basset('https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css')

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
    @basset('https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js')
    @basset('https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js')
    @include('admin.partials.report_table_assets')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @basset(public_path('js/survey-report-charts.js'))

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
