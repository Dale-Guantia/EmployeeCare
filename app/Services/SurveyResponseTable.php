<?php

namespace App\Services;

use App\Models\ArtaSurvey;
use App\Models\Survey;
use Illuminate\Http\Request;

/** Server-side DataTables paging, searching and ordering over the complete history. */
class SurveyResponseTable
{
    public static function data(Request $request, bool $arta = false): array
    {
        $request->validate(['draw' => 'nullable|integer|min:0', 'start' => 'nullable|integer|min:0',
            'length' => 'nullable|integer|min:1|max:100', 'search.value' => 'nullable|string|max:255',
            'order.0.column' => 'nullable|integer|min:0', 'order.0.dir' => 'nullable|in:asc,desc']);
        $query = $arta ? ArtaSurvey::query() : Survey::query()->leftJoin('users', 'users.id', '=', 'surveys.user_id')
            ->leftJoin('divisions', 'divisions.id', '=', 'users.division_id')->leftJoin('issues', 'issues.id', '=', 'surveys.issue_id');
        $table = $arta ? 'arta_surveys' : 'surveys';
        $date = $table . '.created_at';
        $driver = $query->getModel()->getConnection()->getDriverName();
        $displayDate = $driver === 'sqlsrv' ? "FORMAT($date, 'MM/dd/yyyy - h:mm tt', 'en-US')"
            : ($driver === 'mysql' ? "DATE_FORMAT($date, '%m/%d/%Y - %l:%i %p')" : "strftime('%m/%d/%Y', $date)");
        $columns = $arta ? [$date, 'client_type', 'service_availed', 'cc1', 'cc2', 'cc3', 'sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8']
            : ['users.name', 'divisions.division_name', 'issues.issue_description', 'timeliness_rating', 'handling_rating', 'quality_rating', 'overall_rating', $date];
        $total = (clone $query)->count();
        preg_match_all('/"([^"]+)"|(\S+)/u', (string) $request->input('search.value', ''), $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $term = $match[1] !== '' ? $match[1] : ($match[2] ?? '');
            $query->where(function ($q) use ($columns, $term, $displayDate) {
                $pattern = '%' . str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $term) . '%';
                foreach ($columns as $column) {
                    $q->orWhereRaw($column . " LIKE ? ESCAPE '!'", [$pattern]);
                    if (stripos('N/A', $term) !== false) { $q->orWhereNull($column); }
                }
                $q->orWhereRaw($displayDate . " LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $filtered = (clone $query)->count();
        $order = (int) $request->input('order.0.column', 0);
        $query->orderBy($columns[$order] ?? $columns[0], $request->input('order.0.dir', 'asc'))->orderBy($table . '.id');
        if (!$arta) { $query->with(['staff.division', 'service']); }
        $rows = $query->select($table . '.*')->offset((int) $request->input('start', 0))->limit((int) $request->input('length', 10))->get();
        $data = $rows->map(function ($row) use ($arta) {
            $date = optional($row->created_at)->format('m/d/Y - g:i A') ?? 'N/A';
            if ($arta) {
                $values = [$date];
                foreach (['client_type', 'service_availed', 'cc1', 'cc2', 'cc3', 'sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8'] as $key) { $values[] = $row->$key ?? 'N/A'; }
                return $values;
            }
            return [optional($row->staff)->name ?? 'N/A', optional(optional($row->staff)->division)->division_name ?? 'N/A',
                optional($row->service)->issue_description ?? 'N/A', $row->timeliness_rating ?? 'N/A', $row->handling_rating ?? 'N/A',
                $row->quality_rating ?? 'N/A', $row->overall_rating ?? 'N/A', $date];
        })->all();
        return ['draw' => (int) $request->input('draw', 0), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data];
    }
}
