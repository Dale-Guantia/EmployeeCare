<?php

namespace App\Services;

use App\Models\Status;
use Carbon\Carbon;

class DashboardTicketMetrics
{
    public static function summary($query, Carbon $at): array
    {
        $resolved = (int) Status::idByCode(Status::RESOLVED);
        $unassigned = (int) Status::idByCode(Status::UNASSIGNED);
        $reopened = (int) Status::idByCode(Status::REOPENED);
        $metrics = ['total' => 0, 'open' => 0, 'unassigned' => 0, 'overdue_open' => 0, 'due_soon' => 0,
            'resolved' => 0, 'timed_resolved' => 0, 'on_time_resolved' => 0, 'late_resolved' => 0, 'reopened' => 0];
        $minutes = 0;
        $issues = [];
        (clone $query)->select(['id', 'status_id', 'assigned_to', 'created_at', 'resolved_at', 'issue_id', 'custom_issue', 'is_custom_issue'])
            ->with('issue:id,issue_description')->reorder()->chunkById(500,
                function ($tickets) use (&$metrics, &$minutes, &$issues, $resolved, $unassigned, $reopened, $at) {
                    foreach ($tickets as $ticket) {
                        $subject = self::subject($ticket);
                        $issues[$subject] ??= ['total' => 0, 'overdue_open' => 0, 'late_resolved' => 0, 'on_time_resolved' => 0, 'timed_resolved' => 0];
                        $metrics['total']++;
                        $issues[$subject]['total']++;
                        if ((int) $ticket->status_id === $resolved) {
                            $metrics['resolved']++;
                            if (!$ticket->resolved_at || $ticket->resolved_at->lt($ticket->created_at)) continue;
                            $metrics['timed_resolved']++;
                            $issues[$subject]['timed_resolved']++;
                            $key = $ticket->resolved_at->gt($ticket->created_at->copy()->addHours(72)) ? 'late_resolved' : 'on_time_resolved';
                            $metrics[$key]++;
                            $issues[$subject][$key]++;
                            $minutes += $ticket->created_at->diffInMinutes($ticket->resolved_at, true);
                        } else {
                            $metrics['open']++;
                            if (!$ticket->assigned_to || (int) $ticket->status_id === $unassigned) $metrics['unassigned']++;
                            if ((int) $ticket->status_id === $reopened) $metrics['reopened']++;
                            $state = self::deadlineState($ticket, $at);
                            if ($state === 'overdue') {
                                $metrics['overdue_open']++;
                                $issues[$subject]['overdue_open']++;
                            } elseif ($state === 'due_soon') {
                                $metrics['due_soon']++;
                            }
                        }
                    }
                });
        $metrics['avg_resolution_minutes'] = $metrics['timed_resolved'] ? (int) round($minutes / $metrics['timed_resolved']) : 0;
        $metrics['avg_resolution_formatted'] = TicketReportWidgets::formatAverageMinutes($metrics['timed_resolved'] ? $metrics['avg_resolution_minutes'] : null);
        $metrics['on_time_rate'] = $metrics['timed_resolved'] ? round($metrics['on_time_resolved'] / $metrics['timed_resolved'] * 100, 1) : null;
        $metrics['untimed_resolved'] = $metrics['resolved'] - $metrics['timed_resolved'];
        $issues = collect($issues)->map(function ($counts, $subject) {
            return $counts + ['subject' => (string) $subject, 'delay_count' => $counts['overdue_open'] + $counts['late_resolved'],
                'on_time_rate' => $counts['timed_resolved'] ? round($counts['on_time_resolved'] / $counts['timed_resolved'] * 100, 1) : null];
        })->sortBy([['delay_count', 'desc'], ['total', 'desc'], ['subject', 'asc']])->values();
        return ['metrics' => $metrics, 'issues' => $issues];
    }

    public static function attention($query, Carbon $at)
    {
        return (clone $query)->where('status_id', '<>', Status::idByCode(Status::RESOLVED))
            ->with(['issue:id,issue_description', 'assignee:id,name', 'priority:id,priority_name,priority_color'])
            ->reorder()->orderBy('created_at')->orderBy('id')->limit(10)->get()
            ->map(fn ($ticket) => ['ticket' => $ticket, 'subject' => self::subject($ticket),
                'deadline' => $ticket->created_at->copy()->addHours(72), 'state' => self::deadlineState($ticket, $at),
                'age' => TicketReportWidgets::formatMinutes(max(0, (int) $ticket->created_at->diffInMinutes($at, false)))]);
    }

    public static function deadlineState($ticket, Carbon $at): string
    {
        $deadline = $ticket->created_at->copy()->addHours(72);
        if ($deadline->lte($at)) return 'overdue';
        return $deadline->lte($at->copy()->addHours(24)) ? 'due_soon' : 'within_sla';
    }

    private static function subject($ticket): string
    {
        if ($ticket->is_custom_issue) return filled($ticket->custom_issue) ? $ticket->custom_issue : 'Custom request';
        $standard = $ticket->issue?->issue_description;
        return filled($standard) ? $standard : (filled($ticket->custom_issue) ? $ticket->custom_issue : 'Issue unavailable');
    }
}
