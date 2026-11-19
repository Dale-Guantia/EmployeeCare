<?php

namespace App\Services;

use App\Models\{Division, Ticket, TicketReassignmentRequest};

class DivisionChartQueries
{
    public static function data(ReportOfficeScope $scope, $start = null, $end = null, bool $excludeDepartmentHead = false)
    {
        $tickets = $scope->apply(Ticket::visibleTo(backpack_user()))
            ->when($start && $end, fn ($query) => $query->whereBetween('tickets.created_at', [$start, $end]));
        $ids = (clone $tickets)->reorder()->select('tickets.id');
        $reassigned = TicketReassignmentRequest::whereIn('ticket_id', clone $ids)->select('ticket_id')->distinct();
        $rows = $scope->divisions(Division::query())
            ->when($excludeDepartmentHead, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('divisions.division_code')->orWhere('divisions.division_code', '<>', Division::DEPARTMENT_HEAD)))
            ->leftJoin('tickets', fn ($join) => $join->on('tickets.division_id', '=', 'divisions.id')->whereIn('tickets.id', clone $ids))
            ->leftJoinSub($reassigned, 'reassigned_tickets', fn ($join) => $join->on('reassigned_tickets.ticket_id', '=', 'tickets.id'))
            ->select('divisions.id', 'divisions.division_name', 'divisions.division_code')
            ->selectRaw('COUNT(tickets.id) AS total, COUNT(reassigned_tickets.ticket_id) AS reassigned')
            ->groupBy('divisions.id', 'divisions.division_name', 'divisions.division_code')->orderBy('divisions.division_name')->get();
        $departmentLevel = (clone $tickets)->whereNull('division_id');
        $total = (clone $departmentLevel)->count();
        if ($total) {
            $rows->push((object) ['id' => null, 'division_name' => 'Department-level (no division)', 'division_code' => null,
                'total' => $total, 'reassigned' => TicketReassignmentRequest::whereIn('ticket_id', $departmentLevel->select('tickets.id'))->distinct()->count('ticket_id')]);
        }
        return $rows->map(function ($row) {
            $row->total = (int) $row->total;
            $row->reassigned = (int) $row->reassigned;
            $row->rate = $row->total ? round($row->reassigned / $row->total * 100, 1) : 0.0;
            return $row;
        });
    }
}
