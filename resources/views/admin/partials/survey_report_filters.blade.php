@if($periodError)
    <div class="alert alert-warning py-2">{{ $periodError }}</div>
@endif
<form method="GET" class="row g-3 align-items-end mb-3" data-global-report-filters>
    <input type="hidden" name="year" value="{{ $activeYear }}">
    <div class="col-auto">
        <label for="surveyReportPeriod" class="small text-muted text-uppercase fw-bold mb-1">Date Range</label>
        <select id="surveyReportPeriod" name="period" class="form-control form-control-sm">
            @foreach(['all' => 'All Time', 'month' => 'This Month', 'half' => 'Semestral', 'custom' => 'Custom'] as $value => $label)
                <option value="{{ $value }}" @selected($activePeriod === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @if($showOfficeFilters ?? false)
        @if($officeFiltersEnabled)
            <div class="col-auto">
                <label for="surveyReportDepartment" class="small text-muted text-uppercase fw-bold mb-1">Department</label>
                <select id="surveyReportDepartment" name="department_id" class="form-control form-control-sm">
                    <option value="">All Departments</option>
                    @foreach($officeDepartments as $department)
                        <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>{{ $department->department_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label for="surveyReportDivision" class="small text-muted text-uppercase fw-bold mb-1">Division</label>
                <select id="surveyReportDivision" name="division_id" class="form-control form-control-sm" @disabled(!$selectedDepartmentId)>
                    <option value="">All Divisions</option>
                    @foreach($officeDivisions->where('department_id', $selectedDepartmentId) as $division)
                        <option value="{{ $division->id }}" @selected((string) $selectedDivisionId === (string) $division->id)>{{ $division->division_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    @endif
    <div class="col-auto" data-report-half @if($activePeriod !== 'half') hidden @endif>
        <label for="surveyReportHalf" class="small text-muted text-uppercase fw-bold mb-1">Half</label>
        <select id="surveyReportHalf" name="half" class="form-control form-control-sm">
            <option value="h1" @selected(($activeHalf ?? (now()->month <= 6 ? 'h1' : 'h2')) === 'h1')>Jan – Jun {{ $activeYear }}</option>
            <option value="h2" @selected(($activeHalf ?? (now()->month <= 6 ? 'h1' : 'h2')) === 'h2')>Jul – Dec {{ $activeYear }}</option>
        </select>
    </div>
    <div class="col-auto" data-report-custom @if($activePeriod !== 'custom') hidden @endif>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div><label for="surveyReportStart" class="small text-muted fw-bold mb-1">Start Date</label><input id="surveyReportStart" type="date" name="start" class="form-control form-control-sm" value="{{ $filterStart?->toDateString() }}"></div>
            <div><label for="surveyReportEnd" class="small text-muted fw-bold mb-1">End Date</label><input id="surveyReportEnd" type="date" name="end" class="form-control form-control-sm" value="{{ $filterEnd?->toDateString() }}"></div>
        </div>
    </div>
    <div class="col-auto ms-auto small text-muted">{{ $activePeriod === 'all' ? 'All Time' : $filterStart->format('M j, Y') . ' – ' . $filterEnd->format('M j, Y') }}</div>
</form>
<p class="ec-chart-subtitle mb-3">Filters use response creation dates. {{ ($showOfficeFilters ?? false) ? 'Office filters use the rated staff member’s current department and division.' : 'ARTA responses are filtered by date only.' }}</p>
@push('after_scripts')
    @basset(public_path('js/survey-report-filters.js'))
@endpush
