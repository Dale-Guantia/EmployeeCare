<div class="card h-100" data-division-chart-card="{{ $chartId }}">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong>{{ $chartTitle }}</strong>
        @if($divisionChartFiltersEnabled)
            <label class="small mb-0 d-flex align-items-center gap-2" style="min-width: 0;">Department
                <select class="form-control form-control-sm" style="width: auto; max-width: 12rem;" data-division-chart-department data-division-chart-param="{{ $chartDepartmentParam ?? 'chart_department_id' }}" aria-label="Department for {{ $chartTitle }}" @disabled($officeDepartments->isEmpty())>
                    @forelse($officeDepartments as $department)
                        <option value="{{ $department->id }}" @selected((string) $divisionChartDepartmentId === (string) $department->id)>{{ $department->department_name }}</option>
                    @empty
                        <option value="">No department available</option>
                    @endforelse
                </select>
            </label>
        @else
            <small class="text-muted">{{ $divisionChartDepartmentName }}</small>
        @endif
    </div>
    <div class="card-body">
        <p class="small text-muted mb-2">Uses this department and the selected date range.</p>
        <div class="ec-division-chart-scroll" style="max-height: 360px; overflow-y: auto;" tabindex="0" aria-label="{{ $chartTitle }} chart area">
            <div style="height: {{ max(240, count($labels) * 36 + 50) }}px; position: relative;" data-independent-division-chart>
                <canvas id="{{ $chartId }}" role="img" aria-label="{{ $chartTitle }} for {{ $divisionChartDepartmentName }}"></canvas>
                <p class="text-muted text-center pt-5" data-chart-empty hidden>No divisions in this department.</p>
                @php
                    $chartPayload = ['labels' => $labels, 'values' => $values, 'colors' => $colors ?? '#36A2EB'];
                @endphp
                <script type="application/json" data-division-chart-data>@json($chartPayload)</script>
            </div>
        </div>
    </div>
</div>
