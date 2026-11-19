@if($officeFiltersEnabled)
    <div class="col-auto" data-report-office-filters>
        <label for="reportDepartment" class="small text-muted text-uppercase fw-bold mb-1">Department</label>
        <select id="reportDepartment" class="form-control form-control-sm" data-report-department>
            <option value="">All Departments</option>
            @foreach($officeDepartments as $department)
                <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>{{ $department->department_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label for="reportDivision" class="small text-muted text-uppercase fw-bold mb-1">Division</label>
        <select id="reportDivision" class="form-control form-control-sm" data-report-division @disabled(!$selectedDepartmentId)>
            <option value="">All Divisions</option>
            @foreach($officeDivisions->where('department_id', $selectedDepartmentId) as $division)
                <option value="{{ $division->id }}" @selected((string) $selectedDivisionId === (string) $division->id)>{{ $division->division_name }}</option>
            @endforeach
        </select>
    </div>
    @push('after_scripts')
        @basset(public_path('js/report-office-filters.js'))
    @endpush
@endif
