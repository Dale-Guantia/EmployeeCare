<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Division;
use App\Models\Status;
use App\Models\TicketReassignmentRequest;
use Carbon\Carbon;

/** Bounded reads; retain the original Carbon rounding and SLA semantics. */
class TicketReportQueries
{
    public static function widgets($query, Carbon $start, Carbon $end, $forceUnit = null): array
    {
        $resolved = (int) Status::idByName('Resolved');
        $pending = (int) Status::idByName('Pending');
        $reopened = (int) Status::idByName('Reopened');
        $empty = collect();
        $kpi = TicketReportWidgets::buildKpiWidget($empty, $resolved, $pending, $reopened);
        $volume = TicketReportWidgets::buildVolumeTrendWidget($empty, $start, $end, $forceUnit);
        $distribution = TicketReportWidgets::buildResolutionDistributionWidget($empty, $resolved);
        $sla = TicketReportWidgets::buildSlaBreakdownWidget($empty, $resolved);
        $funnel = TicketReportWidgets::buildStatusFunnelWidget($empty);
        $statusIds = Status::pluck('id', 'status_name');
        $hrId = (int) optional(Department::allCached()->firstWhere('department_name', 'City Human Resource Development Office'))->id;
        $byDivision = [];
        if ($hrId) {
            foreach (Division::allCached()->where('department_id', $hrId)->sortBy('division_name') as $division) {
                $byDivision[$division->division_name] = ['total' => 0, 'reassigned' => 0];
            }
        }
        // Legacy count includes any request status and counts tickets in the date range.
        $reassigned = TicketReassignmentRequest::whereHas('ticket', function ($q) use ($start, $end) {
            $q->whereBetween('created_at', [$start, $end]);
        })->distinct()->count('ticket_id');
        $minutesSum = 0;
        $minutesCount = 0;
        (clone $query)->with(['department', 'division', 'issue'])->reorder()->chunkById(500,
            function ($tickets) use (&$kpi, &$volume, &$distribution, &$sla, &$funnel, &$byDivision, &$minutesSum, &$minutesCount, $start, $end, $forceUnit, $resolved, $pending, $reopened, $statusIds, $hrId) {
                $part = TicketReportWidgets::buildKpiWidget($tickets, $resolved, $pending, $reopened);
                foreach (['total', 'resolved', 'pending', 'overdue_count', 'reopen_count'] as $key) { $kpi[$key] += $part[$key]; }
                foreach ($tickets as $ticket) {
                    if ((int) $ticket->status_id === $resolved && $ticket->resolved_at !== null) {
                        $minutesSum += Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at));
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
                foreach ($funnel['labels'] as $i => $name) { $funnel['counts'][$i] += (int) ($counts[$statusIds[$name] ?? null] ?? 0); }
                $ids = TicketReassignmentRequest::whereIn('ticket_id', $tickets->pluck('id'))->distinct()->pluck('ticket_id')->map(function ($id) { return (int) $id; })->all();
                foreach ($tickets as $ticket) {
                    if ($hrId && (int) $ticket->department_id !== $hrId) { continue; }
                    $name = optional($ticket->division)->division_name ?? 'Unassigned Division';
                    if (!isset($byDivision[$name])) { $byDivision[$name] = ['total' => 0, 'reassigned' => 0]; }
                    $byDivision[$name]['total']++;
                    if (in_array((int) $ticket->id, $ids, true)) { $byDivision[$name]['reassigned']++; }
                }
            }, 'id', 'id');
        $kpi['avg_resolution_minutes'] = $minutesCount ? (int) round($minutesSum / $minutesCount) : 0;
        $kpi['avg_resolution_formatted'] = TicketReportWidgets::formatMinutes($kpi['avg_resolution_minutes']);
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

    public static function staffResolutionAverages($query)
    {
        $totals = [];
        (clone $query)->select(['id', 'resolved_by', 'created_at', 'resolved_at'])->reorder()->chunkById(500, function ($tickets) use (&$totals) {
            foreach ($tickets as $ticket) {
                $id = $ticket->resolved_by;
                if (!isset($totals[$id])) { $totals[$id] = ['sum' => 0, 'count' => 0]; }
                $totals[$id]['sum'] += Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at));
                $totals[$id]['count']++;
            }
        });
        return collect($totals)->map(function ($total) { return (int) round($total['sum'] / $total['count']); });
    }
}
