<?php

namespace App\Services;

use App\Models\{Department, Division};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportOfficeScope
{
    public function __construct(public ?int $departmentId, public ?int $divisionId, public bool $admin, public bool $empty = false)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $user = backpack_user();
        if (!$user?->isAdmin()) {
            // Department heads cannot widen or replace their actual office through URL parameters.
            if ($user?->isDeptHead()) {
                return new self($user->department_id ? (int) $user->department_id : null, null, false, !$user->department_id);
            }
            return new self(null, null, false);
        }
        $divisionRule = Rule::exists('divisions', 'id');
        if ($request->filled('department_id')) {
            $divisionRule->where('department_id', filter_var($request->input('department_id'), FILTER_VALIDATE_INT) !== false ? $request->input('department_id') : null);
        }
        $values = $request->validate([
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'division_id' => ['nullable', 'integer', $divisionRule],
        ]);
        $division = !empty($values['division_id']) ? Division::findOrFail($values['division_id']) : null;
        return new self(!empty($values['department_id']) ? (int) $values['department_id'] : $division?->department_id,
            $division?->id, true);
    }

    public function apply($query, string $table = 'tickets')
    {
        if ($this->empty) return $query->whereRaw('1 = 0');
        if ($this->departmentId !== null) $query->where($table . '.department_id', $this->departmentId);
        if ($this->divisionId !== null) $query->where($table . '.division_id', $this->divisionId);
        return $query;
    }

    public static function forDivisionChart(Request $request, string $parameter = 'chart_department_id'): self
    {
        $user = backpack_user();
        if (!$user?->isAdmin()) {
            return new self($user?->department_id ? (int) $user->department_id : null, null, false, !$user?->department_id);
        }
        $values = $request->validate([$parameter => ['nullable', 'integer', Rule::exists('departments', 'id')]]);
        $departmentId = $values[$parameter] ?? $user->department_id;
        if (!$departmentId) {
            $departmentId = Department::where('department_code', Department::HRDO)->value('id')
                ?? Department::orderBy('department_name')->value('id');
        }
        return new self($departmentId ? (int) $departmentId : null, null, true, !$departmentId);
    }

    public function divisionChartViewData(): array
    {
        return ['divisionChartDepartmentId' => $this->departmentId,
            'divisionChartDepartmentName' => $this->departmentId ? Department::whereKey($this->departmentId)->value('department_name') : 'No department assigned',
            'divisionChartFiltersEnabled' => $this->admin];
    }

    public function divisions($query)
    {
        if ($this->empty) return $query->whereRaw('1 = 0');
        if ($this->departmentId !== null) $query->where('divisions.department_id', $this->departmentId);
        if ($this->divisionId !== null) $query->where('divisions.id', $this->divisionId);
        return $query;
    }

    public function viewData(): array
    {
        return ['officeFiltersEnabled' => $this->admin, 'selectedDepartmentId' => $this->departmentId,
            'selectedDivisionId' => $this->divisionId,
            'officeDepartments' => $this->admin ? Department::orderBy('department_name')->get(['id', 'department_name']) : collect(),
            'officeDivisions' => $this->admin ? Division::orderBy('division_name')->get(['id', 'department_id', 'division_name']) : collect()];
    }
}
