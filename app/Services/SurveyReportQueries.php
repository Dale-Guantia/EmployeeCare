<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\User;

class SurveyReportQueries
{
    public static function summary($start = null, $end = null, bool $includeZeroActivity = false): array
    {
        $choices = ['very_satisfied' => 'Very Satisfied', 'satisfied' => 'Satisfied', 'dissatisfied' => 'Dissatisfied', 'very_dissatisfied' => 'Very Dissatisfied'];
        $dimensions = ['timeliness', 'handling', 'quality', 'overall'];
        $emptyDetails = [];
        foreach ($dimensions as $dimension) { $emptyDetails[$dimension] = array_fill_keys(array_keys($choices), 0); }
        $query = Survey::query()->when($start && $end, function ($q) use ($start, $end) { $q->whereBetween('created_at', [$start, $end]); });
        // One grouped result per rated staff, rather than every historical response.
        $query->select('user_id')->selectRaw('COUNT(*) AS total_surveys');
        $driver = $query->getModel()->getConnection()->getDriverName();
        foreach ($dimensions as $dimension) {
            foreach ($choices as $key => $choice) {
                $column = $dimension . '_rating';
                // The legacy Collection comparison is case-sensitive and keeps trailing spaces.
                if ($driver === 'sqlsrv') {
                    $predicate = $column . ' COLLATE Latin1_General_100_BIN2 = ? AND LEN(' . $column . " + '#') = " . (strlen($choice) + 1);
                } elseif ($driver === 'mysql') {
                    $predicate = 'BINARY ' . $column . ' = ?';
                } else {
                    $predicate = $column . ' = ?';
                }
                $query->selectRaw('SUM(CASE WHEN ' . $predicate . ' THEN 1 ELSE 0 END) AS ' . $dimension . '_' . $key, [$choice]);
            }
        }
        $groups = $query->groupBy('user_id')->get()->keyBy('user_id');
        $summary = array_merge(['total_surveys' => (int) $groups->sum('total_surveys')], array_fill_keys(array_keys($choices), 0), ['staff_rated' => $groups->count()]);
        foreach ($groups as $group) {
            foreach ($dimensions as $dimension) {
                foreach ($choices as $key => $choice) { $summary[$key] += (int) $group->{$dimension . '_' . $key}; }
            }
        }
        $staff = User::where(function ($q) {
            $q->whereHas('roles', function ($r) { $r->where('name', 'hr_staff'); })
                ->orWhere(function ($q) { $q->whereHas('roles', function ($r) { $r->where('name', 'div_head'); })->where('department_id', 1); });
        })->with('division')->get();
        $ratings = $staff->map(function ($staff) use ($groups, $choices, $dimensions, $emptyDetails) {
            $group = $groups[$staff->id] ?? null;
            $row = ['id' => $staff->id, 'name' => $staff->name, 'division_name' => optional($staff->division)->division_name ?? 'N/A',
                'total_surveys' => (int) ($group->total_surveys ?? 0)];
            $details = $emptyDetails;
            foreach ($choices as $key => $choice) {
                $row[$key . '_count'] = 0;
                foreach ($dimensions as $dimension) {
                    $count = (int) ($group->{$dimension . '_' . $key} ?? 0);
                    $details[$dimension][$key] = $count;
                    $row[$key . '_count'] += $count;
                }
            }
            $row['details'] = $details;
            return (object) $row;
        });
        if (!$includeZeroActivity) { $ratings = $ratings->filter(function ($row) { return $row->total_surveys > 0; })->values(); }
        return ['surveyRatings' => $ratings, 'summaryCards' => $summary];
    }
}
