<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Division;
use App\Models\Status;
use App\Models\TicketReassignmentRequest;
use App\Models\User;
use Carbon\Carbon;

/** Bounded reads; retain the original Carbon rounding and SLA semantics. */
class TicketReportQueries
{
    public static function widgets($query, Carbon $start, Carbon $end, $forceUnit = null, ?ReportOfficeScope $scope = null): array
    {
        $resolved = (int) Status::idByCode(Status::RESOLVED);
        $pending = (int) Status::idByCode(Status::PENDING);
        $reopened = (int) Status::idByCode(Status::REOPENED);
        $empty = collect();
        $kpi = TicketReportWidgets::buildKpiWidget($empty, $resolved, $pending, $reopened);
        $volume = TicketReportWidgets::buildVolumeTrendWidget($empty, $start, $end, $forceUnit);
        $distribution = TicketReportWidgets::buildResolutionDistributionWidget($empty, $resolved);
        $sla = TicketReportWidgets::buildSlaBreakdownWidget($empty, $resolved);
        $funnel = TicketReportWidgets::buildStatusFunnelWidget($empty);
        $statusIds = Status::idsByCode();
        $hrId = (int) Department::idByCode(Department::HRDO);
        $byDivision = [];
        if ($scope || $hrId) {
            $divisions = $scope ? $scope->divisions(Division::query())->orderBy('division_name')->get()
                : Division::allCached()->where('department_id', $hrId)->sortBy('division_name');
            foreach ($divisions as $division) {
                $byDivision[$division->division_name] = ['total' => 0, 'reassigned' => 0];
            }
        }
        // All request states count, but only tickets admitted by the caller's scope.
        $ticketIds = (clone $query)->reorder()->select('tickets.id');
        $reassigned = TicketReassignmentRequest::whereHas('ticket', function ($q) use ($start, $end) {
            $q->whereBetween('created_at', [$start, $end]);
        })->whereIn('ticket_id', $ticketIds)->distinct()->count('ticket_id');
        $minutesSum = 0;
        $minutesCount = 0;
        (clone $query)->with(['department', 'division', 'issue'])->reorder()->chunkById(500,
            function ($tickets) use (&$kpi, &$volume, &$distribution, &$sla, &$funnel, &$byDivision, &$minutesSum, &$minutesCount, $start, $end, $forceUnit, $resolved, $pending, $reopened, $statusIds, $hrId, $scope) {
                $part = TicketReportWidgets::buildKpiWidget($tickets, $resolved, $pending, $reopened);
                foreach (['total', 'resolved', 'pending', 'overdue_count', 'reopen_count'] as $key) { $kpi[$key] += $part[$key]; }
                foreach ($tickets as $ticket) {
                    if ((int) $ticket->status_id === $resolved && $ticket->resolved_at !== null) {
                        $minutesSum += (int) Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at), true);
                        $minutesCount++;
                    }
                }
                foreach ([['volume', TicketReportWidgets::buildVolumeTrendWidget($tickets, $start, $end, $forceUnit)], ['distribution', TicketReportWidgets::buildResolutionDistributionWidget($tickets, $resolved)]] as $result) {
                    $target = $result[0] === 'volume' ? $volume : $distribution;
                    foreach ($result[1]['counts'] as $i => $count) { $target['counts'][$i] += $count; }
                    if ($result[0] === 'volume') { $volume = $target; } else { $distribution = $target; }
                }
                $part = TicketReportWidgets::buildSlaBreakdownWidget($tickets, $resolved);
                $sla['overdue_count'] += $part['overdue_count'];
                $sla['on_time_count'] += $part['on_time_count'];
                foreach (['by_department', 'by_issue'] as $group) {
                    foreach ($part[$group] as $label => $counts) {
                        if (!isset($sla[$group][$label])) { $sla[$group][$label] = ['total' => 0, 'overdue' => 0]; }
                        foreach ($counts as $key => $count) { $sla[$group][$label][$key] += $count; }
                    }
                }
                $counts = $tickets->countBy('status_id');
                foreach (Status::CORE_CODES as $i => $code) { $funnel['counts'][$i] += (int) ($counts[$statusIds[$code] ?? null] ?? 0); }
                $ids = TicketReassignmentRequest::whereIn('ticket_id', $tickets->pluck('id'))->distinct()->pluck('ticket_id')->map(function ($id) { return (int) $id; })->all();
                foreach ($tickets as $ticket) {
                    if (!$scope && $hrId && (int) $ticket->department_id !== $hrId) { continue; }
                    $name = optional($ticket->division)->division_name ?? 'Unassigned Division';
                    if (!isset($byDivision[$name])) { $byDivision[$name] = ['total' => 0, 'reassigned' => 0]; }
                    $byDivision[$name]['total']++;
                    if (in_array((int) $ticket->id, $ids, true)) { $byDivision[$name]['reassigned']++; }
                }
            }, 'id', 'id');
        $kpi['avg_resolution_minutes'] = $minutesCount ? (int) round($minutesSum / $minutesCount) : 0;
        $kpi['avg_resolution_formatted'] = TicketReportWidgets::formatAverageMinutes($minutesCount ? $kpi['avg_resolution_minutes'] : null);
        $kpi['overdue_pct'] = $kpi['total'] ? round($kpi['overdue_count'] / $kpi['total'] * 100, 1) : 0.0;
        $kpi['reopen_rate'] = $kpi['total'] ? round($kpi['reopen_count'] / $kpi['total'] * 100, 1) : 0.0;
        foreach ($sla['by_department'] as $name => $counts) {
            $sla['department_labels'][] = $name;
            $sla['department_overdue'][] = $counts['overdue'];
            $sla['department_on_time'][] = $counts['total'] - $counts['overdue'];
        }
        foreach ($byDivision as &$counts) { $counts['rate'] = $counts['total'] ? round($counts['reassigned'] / $counts['total'] * 100, 1) : 0.0; }
        unset($counts);
        return ['kpis' => $kpi, 'volume' => $volume, 'resolution' => $distribution, 'sla' => $sla, 'funnel' => $funnel,
            'reassignment' => ['reassigned_count' => $reassigned, 'total' => $kpi['total'],
                'rate' => $kpi['total'] ? round($reassigned / $kpi['total'] * 100, 1) : 0.0,
                'by_division' => $byDivision, 'division_labels' => array_keys($byDivision), 'division_rates' => array_column($byDivision, 'rate')]];
    }

    public static function staffLeaderboard($query)
    {
        $resolved = (int) Status::idByCode(Status::RESOLVED);
        $statistics = self::staffResolutionStatistics((clone $query)->where('status_id', $resolved)
            ->whereNotNull('resolved_by')->whereNotNull('created_at')->whereNotNull('resolved_at')
            ->whereColumn('resolved_at', '>=', 'created_at'), true);
        $rows = $statistics->map(fn ($counts, $id) => array_merge($counts, ['user_id' => (int) $id]))
            ->sortBy([['resolved_count', 'desc'], ['on_time_count', 'desc'], ['avg_resolution_minutes', 'asc'], ['user_id', 'asc']])
            ->take(10)->values();
        $names = User::whereIn('id', $rows->pluck('user_id'))->pluck('name', 'id');
        return $rows->map(fn ($row) => array_merge($row, ['name' => $names[$row['user_id']] ?? 'Staff unavailable',
            'avg_resolution_formatted' => TicketReportWidgets::formatMinutes($row['avg_resolution_minutes'])]));
    }

    private static function staffResolutionStatistics($query, bool $preciseSla = false)
    {
        $resolved = (int) Status::idByCode(Status::RESOLVED);
        $totals = [];
        (clone $query)->select(['id', 'status_id', 'resolved_by', 'created_at', 'resolved_at'])->reorder()
            ->chunkById(500, function ($tickets) use (&$totals, $resolved, $preciseSla) {
                foreach ($tickets as $ticket) {
                    $id = $ticket->resolved_by;
                    $totals[$id] ??= ['resolved_count' => 0, 'on_time_count' => 0, 'overdue_count' => 0, 'minutes' => 0];
                    $totals[$id]['resolved_count']++;
                    $overdue = $preciseSla
                        ? $ticket->resolved_at->gt($ticket->created_at->copy()->addHours(72))
                        : TicketReportWidgets::isTicketOverdue($ticket, $resolved);
                    $totals[$id][$overdue ? 'overdue_count' : 'on_time_count']++;
                    $elapsed = Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at), true);
                    $totals[$id]['minutes'] += $preciseSla ? $elapsed : (int) $elapsed;
                }
            });
        return collect($totals)->map(function ($counts) {
            $average = (int) round($counts['minutes'] / $counts['resolved_count']);
            unset($counts['minutes']);
            return array_merge($counts, ['avg_resolution_minutes' => $average]);
        });
    }

    public static function staffResolutionAverages($query)
    {
        return self::staffResolutionStatistics($query)->map(fn ($counts) => $counts['avg_resolution_minutes']);
    }
}
