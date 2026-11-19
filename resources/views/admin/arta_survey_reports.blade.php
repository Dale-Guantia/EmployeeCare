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
    @include('admin.partials.survey_report_filters', ['showOfficeFilters' => false])

    @php
        $cards = [];
        foreach ([['total_responses', 'Total Responses', 'la-list', 'bg-dark'], ['strongly_agree', 'Strongly Agree', 'la-smile', 'bg-success'],
            ['agree', 'Agree', 'la-thumbs-up', 'bg-primary'], ['neither', 'Neither', 'la-meh', 'bg-secondary'],
            ['disagree', 'Disagree', 'la-frown', 'bg-warning'], ['strongly_disagree', 'Strongly Disagree', 'la-angry', 'bg-danger']] as [$key, $label, $icon, $color]) {
            $cards[] = compact('key', 'label', 'icon', 'color') + ['value' => $artaSummary[$key]];
        }
        $cards[3]['background'] = '#6f42c1';
        $charts = [
            ['title' => 'Overall Satisfaction (SQD0)', 'labels' => ['Strongly Agree', 'Agree', 'Neither', 'Disagree', 'Strongly Disagree', 'N/A', 'Unanswered / Other'],
                'counts' => [$artaSummary['strongly_agree'], $artaSummary['agree'], $artaSummary['neither'], $artaSummary['disagree'], $artaSummary['strongly_disagree'], $artaSummary['not_applicable'], $artaSummary['unanswered_or_other']],
                'colors' => ['#198754', '#375ca6', '#6f42c1', '#ffc107', '#b02a37', '#94a3b8', '#64748b']],
            ['title' => 'Responses by Client Type', 'labels' => $artaClients->map(fn ($row) => filled($row->client_type) ? $row->client_type : 'Not specified')->all(),
                'counts' => $artaClients->pluck('total_responses')->map(fn ($count) => (int) $count)->all()],
        ];
    @endphp
    @include('admin.partials.report_kpi_cards', ['cards' => $cards])
    <p class="small text-muted mt-3 mb-0">Totals for the selected filters. Satisfaction cards use SQD0 (overall satisfaction). N/A: {{ $artaSummary['not_applicable'] }}; unanswered or other: {{ $artaSummary['unanswered_or_other'] }}. Table searches do not change these totals.</p>
    @include('admin.partials.survey_report_charts', ['charts' => $charts])

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <table id="surveyResponsesTable" class="table table-hover w-100 ec-report-table" data-report-table data-report-ajax="{{ route('page.arta_survey_reports.responses', array_filter(['period' => $activePeriod, 'half' => $activeHalf, 'year' => $activeYear, 'range_start' => $filterStart?->toDateString(), 'range_end' => $filterEnd?->toDateString()], fn ($value) => $value !== null)) }}" data-report-order-column="1" data-report-empty="No ARTA responses available." aria-label="ARTA Responses">
                        <thead>
                        <tr>
                            <th class="control report-details-cell"><span class="visually-hidden">Details</span></th>
                            <th data-priority="3">Date</th>
                            <th data-priority="2">Client Type</th>
                            <th data-priority="1">Service Availed</th>
                            <th>CC1</th>
                            <th>CC2</th>
                            <th>CC3</th>
                            <th data-report-rating="true" data-priority="4">SQD0</th>
                            <th data-report-rating="true">SQD1</th>
                            <th data-report-rating="true">SQD2</th>
                            <th data-report-rating="true">SQD3</th>
                            <th data-report-rating="true">SQD4</th>
                            <th data-report-rating="true">SQD5</th>
                            <th data-report-rating="true">SQD6</th>
                            <th data-report-rating="true">SQD7</th>
                            <th data-report-rating="true">SQD8</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($responses as $response)
                                <tr>
                                    <td class="report-details-cell"><button type="button" class="btn btn-sm btn-outline-secondary report-details-toggle" aria-label="Show additional ARTA details" aria-expanded="false">+</button></td>
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
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="{{ $exportStartDate }}" required>
                        </div>
                        <div class="mb-3">
                            <label>End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" value="{{ ($filterEnd ?? now())->format('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after_styles')
    @basset(public_path('css/analytics.css'))
    {{-- DataTables Core + Responsive CSS --}}
    @basset('https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css')
    @basset('https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css')


@endpush

@push('after_scripts')
    {{-- DataTables Core + Responsive JS --}}
    @basset('https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js')
    @basset('https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js')
    @include('admin.partials.report_table_assets')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @basset(public_path('js/survey-report-charts.js'))
    <script>
        $(document).ready(function () {
            const reportModal = document.getElementById('downloadSurveyReportModal');
            if (reportModal) document.body.appendChild(reportModal);
        });
    </script>
@endpush
