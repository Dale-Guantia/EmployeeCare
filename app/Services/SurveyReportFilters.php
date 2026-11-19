<?php

namespace App\Services;

use Illuminate\Http\Request;

class SurveyReportFilters
{
    public static function dates(Request $request, bool $paginated = false): array
    {
        // DataTables reserves "start" for the row offset.
        if ($paginated) {
            $request = $request->duplicate(array_merge($request->query(), [
                'start' => $request->query('range_start'), 'end' => $request->query('range_end'),
            ]));
        }
        $request->validate(['period' => ['nullable', 'string', 'in:all,month,half,custom'],
            'half' => ['nullable', 'string', 'in:h1,h2'], 'year' => ['nullable', 'integer', 'between:1900,2100'],
            'start' => ['nullable', 'string'], 'end' => ['nullable', 'string']]);
        return ReportPeriodResolver::resolve($request);
    }

    public static function apply($query, $start, $end, ?ReportOfficeScope $scope = null)
    {
        $table = $query->getModel()->getTable();
        $query->when($start && $end, fn ($query) => $query->whereBetween($table . '.created_at', [$start, $end]));
        if ($scope && ($scope->empty || $scope->departmentId !== null || $scope->divisionId !== null)) {
            $query->whereHas('staff', fn ($staff) => $scope->apply($staff, 'users'));
        }
        return $query;
    }

    public static function viewData(array $period): array
    {
        [$activePeriod, $filterStart, $filterEnd, $activeHalf, $activeYear, $periodError] = $period;
        return compact('activePeriod', 'filterStart', 'filterEnd', 'activeHalf', 'activeYear', 'periodError');
    }
}
