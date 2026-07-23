<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Division;
use App\Models\Status;
use App\Models\TicketReassignmentRequest;
use Carbon\Carbon;

/**
 * Shared ticket-analytics widget math: SLA/overdue determination, resolution
 * bucketing, volume-trend bucketing, and reassignment scoping. Used by both
 * ReportsController (date-ranged) and the dashboard (fixed to all-time) so
 * this logic lives in exactly one place instead of drifting between callers.
 */
class TicketReportWidgets
{
    public static function isTicketOverdue($ticket, int $resolvedStatusId): bool
    {
        $createdAt = Carbon::parse($ticket->created_at);

        if ((int) $ticket->status_id === $resolvedStatusId) {
            if (!$ticket->resolved_at) {
                return false;
            }

            return $createdAt->diffInHours(Carbon::parse($ticket->resolved_at)) > 72;
        }

        return $createdAt->lt(now()->subDays(3));
    }

    public static function formatMinutes(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%dh %02dm', $hours, $minutes);
    }

    public static function buildKpiWidget($tickets, int $resolvedStatusId, int $pendingStatusId, int $reopenedStatusId): array
    {
        $total = $tickets->count();

        $resolvedTickets = $tickets->filter(function ($ticket) use ($resolvedStatusId) {
            return (int) $ticket->status_id === $resolvedStatusId;
        });
        $resolved = $resolvedTickets->count();

        $pending = $tickets->filter(function ($ticket) use ($pendingStatusId) {
            return (int) $ticket->status_id === $pendingStatusId;
        })->count();

        $resolutionMinutes = $resolvedTickets
            ->filter(function ($ticket) {
                return $ticket->resolved_at !== null;
            })
            ->map(function ($ticket) {
                return Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at));
            });

        $avgResolutionMinutes = $resolutionMinutes->count() > 0
            ? (int) round($resolutionMinutes->avg())
            : 0;

        $overdueCount = $tickets->filter(function ($ticket) use ($resolvedStatusId) {
            return self::isTicketOverdue($ticket, $resolvedStatusId);
        })->count();

        // NOTE: Ticket::booted()'s saving() hook clears reopened_at whenever a
        // ticket transitions out of the Reopened status for any reason other
        // than resolving it. That means there is no persistent "was ever
        // reopened" flag once a re-reopened ticket is resolved/reassigned
        // again — only tickets *currently* Reopened are detectable here. This
        // matches the spec's stated rule (non-null reopened_at OR current
        // status = Reopened) but undercounts historical reopens.
        $reopenedCount = $tickets->filter(function ($ticket) use ($reopenedStatusId) {
            return (int) $ticket->status_id === $reopenedStatusId || $ticket->reopened_at !== null;
        })->count();

        return [
            'total' => $total,
            'resolved' => $resolved,
            'pending' => $pending,
            'avg_resolution_minutes' => $avgResolutionMinutes,
            'avg_resolution_formatted' => self::formatMinutes($avgResolutionMinutes),
            'overdue_count' => $overdueCount,
            'overdue_pct' => $total > 0 ? round(($overdueCount / $total) * 100, 1) : 0.0,
            'reopen_count' => $reopenedCount,
            'reopen_rate' => $total > 0 ? round(($reopenedCount / $total) * 100, 1) : 0.0,
        ];
    }

    public static function buildVolumeTrendWidget($tickets, Carbon $start, Carbon $end, $forceUnit = null): array
    {
        $rangeDays = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());

        if ($forceUnit) {
            $unit = $forceUnit;
        } elseif ($rangeDays <= 92) {
            $unit = 'day';
        } elseif ($rangeDays <= 730) {
            $unit = 'week';
        } else {
            $unit = 'month';
        }

        $counts = [];
        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        if ($unit === 'week') {
            $cursor = $cursor->copy()->startOfWeek();
        } elseif ($unit === 'month') {
            $cursor = $cursor->copy()->startOfMonth();
        }

        while ($cursor->lte($lastDay)) {
            $label = $unit === 'month' ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $counts[$label] = 0;

            if ($unit === 'day') {
                $cursor->addDay();
            } elseif ($unit === 'week') {
                $cursor->addWeek();
            } else {
                $cursor->addMonth();
            }
        }

        foreach ($tickets as $ticket) {
            $createdAt = Carbon::parse($ticket->created_at);

            if ($unit === 'day') {
                $label = $createdAt->format('Y-m-d');
            } elseif ($unit === 'week') {
                $label = $createdAt->copy()->startOfWeek()->format('Y-m-d');
            } else {
                $label = $createdAt->format('Y-m');
            }

            if (array_key_exists($label, $counts)) {
                $counts[$label]++;
            }
        }

        return [
            'labels' => array_keys($counts),
            'counts' => array_values($counts),
        ];
    }

    public static function buildResolutionDistributionWidget($tickets, int $resolvedStatusId): array
    {
        $buckets = [
            '<1 day' => 0,
            '1-3 days' => 0,
            '3-7 days' => 0,
            '7+ days' => 0,
        ];

        foreach ($tickets as $ticket) {
            if ((int) $ticket->status_id !== $resolvedStatusId || !$ticket->resolved_at) {
                continue;
            }

            $hours = Carbon::parse($ticket->created_at)->diffInHours(Carbon::parse($ticket->resolved_at));

            if ($hours < 24) {
                $buckets['<1 day']++;
            } elseif ($hours < 72) {
                $buckets['1-3 days']++;
            } elseif ($hours < 168) {
                $buckets['3-7 days']++;
            } else {
                $buckets['7+ days']++;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'counts' => array_values($buckets),
        ];
    }

    public static function buildSlaBreakdownWidget($tickets, int $resolvedStatusId): array
    {
        $overdue = 0;
        $onTime = 0;
        $byDepartment = [];
        $byIssue = [];

        foreach ($tickets as $ticket) {
            $isOverdue = self::isTicketOverdue($ticket, $resolvedStatusId);
            $isOverdue ? $overdue++ : $onTime++;

            $deptName = optional($ticket->department)->department_name ?? 'Unassigned Department';
            if (!isset($byDepartment[$deptName])) {
                $byDepartment[$deptName] = ['total' => 0, 'overdue' => 0];
            }
            $byDepartment[$deptName]['total']++;
            if ($isOverdue) {
                $byDepartment[$deptName]['overdue']++;
            }

            $issueName = optional($ticket->issue)->issue_description ?? ($ticket->custom_issue ?: 'Other');
            if (!isset($byIssue[$issueName])) {
                $byIssue[$issueName] = ['total' => 0, 'overdue' => 0];
            }
            $byIssue[$issueName]['total']++;
            if ($isOverdue) {
                $byIssue[$issueName]['overdue']++;
            }
        }

        $departmentLabels = [];
        $departmentOverdue = [];
        $departmentOnTime = [];
        foreach ($byDepartment as $name => $counts) {
            $departmentLabels[] = $name;
            $departmentOverdue[] = $counts['overdue'];
            $departmentOnTime[] = $counts['total'] - $counts['overdue'];
        }

        return [
            'overdue_count' => $overdue,
            'on_time_count' => $onTime,
            'by_department' => $byDepartment,
            'by_issue' => $byIssue,
            'department_labels' => $departmentLabels,
            'department_overdue' => $departmentOverdue,
            'department_on_time' => $departmentOnTime,
        ];
    }

    public static function buildStatusFunnelWidget($tickets): array
    {
        $counts = $tickets->countBy('status_id');

        $statusIdsByName = Status::pluck('id', 'status_name');

        $order = ['Unassigned', 'Pending', 'Resolved', 'Reopened'];
        $labels = [];
        $data = [];

        foreach ($order as $name) {
            $statusId = $statusIdsByName[$name] ?? null;
            $labels[] = $name;
            $data[] = $statusId !== null ? (int) ($counts[$statusId] ?? 0) : 0;
        }

        return [
            'labels' => $labels,
            'counts' => $data,
        ];
    }

    public static function buildReassignmentWidget($tickets, Carbon $start, Carbon $end): array
    {
        $total = $tickets->count();

        // Cast to int: some DB drivers (e.g. sqlsrv) return plucked column
        // values as strings while Eloquent model attributes like $ticket->id
        // stay int, which would silently break the strict in_array() check
        // below.
        $reassignedTicketIds = TicketReassignmentRequest::whereHas('ticket', function ($query) use ($start, $end) {
            $query->whereBetween('created_at', [$start, $end]);
        })->distinct()->pluck('ticket_id')->map(function ($id) {
            return (int) $id;
        })->all();

        $reassignedCount = count($reassignedTicketIds);

        $hrDepartmentId = (int) Department::where('department_name', 'City Human Resource Development Office')->value('id');

        // Zero-fill every HR division up front so the chart always shows a
        // stable, complete set of bars rather than only the divisions that
        // happened to have a ticket in this range.
        $byDivision = [];
        if ($hrDepartmentId) {
            Division::where('department_id', $hrDepartmentId)
                ->orderBy('division_name')
                ->pluck('division_name')
                ->each(function ($name) use (&$byDivision) {
                    $byDivision[$name] = ['total' => 0, 'reassigned' => 0];
                });
        }

        foreach ($tickets as $ticket) {
            if ($hrDepartmentId && (int) $ticket->department_id !== $hrDepartmentId) {
                continue;
            }

            $divisionName = optional($ticket->division)->division_name ?? 'Unassigned Division';
            if (!isset($byDivision[$divisionName])) {
                $byDivision[$divisionName] = ['total' => 0, 'reassigned' => 0];
            }
            $byDivision[$divisionName]['total']++;
            if (in_array($ticket->id, $reassignedTicketIds, true)) {
                $byDivision[$divisionName]['reassigned']++;
            }
        }

        $divisionLabels = [];
        $divisionRates = [];
        foreach ($byDivision as $name => $counts) {
            $rate = $counts['total'] > 0 ? round(($counts['reassigned'] / $counts['total']) * 100, 1) : 0.0;
            $byDivision[$name]['rate'] = $rate;
            $divisionLabels[] = $name;
            $divisionRates[] = $rate;
        }

        return [
            'reassigned_count' => $reassignedCount,
            'total' => $total,
            'rate' => $total > 0 ? round(($reassignedCount / $total) * 100, 1) : 0.0,
            'by_division' => $byDivision,
            'division_labels' => $divisionLabels,
            'division_rates' => $divisionRates,
        ];
    }
}
