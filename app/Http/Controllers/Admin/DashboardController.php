<?php

namespace App\Http\Controllers\Admin;

use App\Models\Division;
use App\Models\Status;
use App\Models\Ticket;
use App\Services\ReportPeriodResolver;
use App\Services\TicketReportWidgets;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    private const ABBREVIATION_MAP = [
        'Administrative'           => 'Admin',
        'Claims and Benefits'      => 'Claims',
        'Information Technology'   => 'IT',
        'Learning and Development' => 'L&D',
        'Performance Management'   => 'PM',
        'Payroll'                  => 'Payroll',
        'Records'                  => 'Records',
        'RSP'                      => 'RSP',
    ];

    private const COLOR_PALETTE = [
        'Admin'     => '#FF6384',
        'Claims'    => '#36A2EB',
        'IT'        => '#4BC0C0',
        'L&D'       => '#FFCE56',
        'PM'        => '#9966FF',
        'Payroll'   => '#FF9F40',
        'Records'   => '#C9CBCF',
        'RSP'       => '#4D5360',
    ];

    public function dashboard(Request $request)
    {
        // If the user is an employee, redirect them to the Submit Ticket landing page
        if (backpack_user()->hasRole('employee')) {
            return redirect()->route('submit-ticket.show');
        }
        abort_unless(backpack_user()->can('ticket.view'), 403);

        [$period, $startDate, $endDate, $half, $year, $periodError] = ReportPeriodResolver::resolve($request);

        $this->data['title'] = trans('backpack::base.dashboard');
        $this->data['breadcrumbs'] = [
            trans('backpack::crud.admin')     => backpack_url('dashboard'),
            trans('backpack::base.dashboard') => false,
        ];
        $this->data['activePeriod'] = $period;
        $this->data['activeHalf'] = $half;
        $this->data['activeYear'] = $year;
        $this->data['periodError'] = $periodError;

        return view(backpack_view('dashboard'), array_merge($this->data, $this->getDashboardData($startDate, $endDate)));
    }

    protected function getDashboardData($startDate = null, $endDate = null)
    {
        $visibleTickets = Ticket::visibleTo(backpack_user());
        $periodTickets = (clone $visibleTickets)->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        });
        $visibleIds = (clone $periodTickets)->select('tickets.id');
        $ticketsPerDivisionRaw = Division::leftJoin('tickets', function ($join) use ($visibleIds) {
            $join->on('tickets.division_id', '=', 'divisions.id')->whereIn('tickets.id', $visibleIds);
        })
            ->join('departments', 'divisions.department_id', '=', 'departments.id')
            ->where('divisions.division_name', '!=', 'Department Head')
            ->selectRaw('divisions.division_name, COUNT(tickets.id) as total')
            ->groupBy('divisions.division_name')
            ->orderBy('divisions.division_name')
            ->get();

        $divisionLabels = [];
        $divisionCounts = [];
        $divisionColors = [];

        foreach ($ticketsPerDivisionRaw as $item) {
            $shortName = self::ABBREVIATION_MAP[$item->division_name] ?? $item->division_name;
            $divisionLabels[] = $shortName;
            $divisionCounts[] = $item->total;
            $divisionColors[] = self::COLOR_PALETTE[$shortName] ?? '#36A2EB';
        }

        $statusData = Status::withCount(['tickets' => function ($query) use ($visibleIds) {
            $query->whereIn('tickets.id', $visibleIds);
        }])->get();

        $statusLabels = $statusData->pluck('status_name');
        $statusCounts = $statusData->pluck('tickets_count');
        $statusColors = $statusData->pluck('status_color');

        $latestTickets = (clone $periodTickets)->with(['user', 'issue', 'status', 'priority'])
            ->latest()
            ->take(10)
            ->get();

        $resolvedStatusId = (int) Status::idByName('Resolved');
        $pendingStatusId = (int) Status::idByName('Pending');
        $reopenedStatusId = (int) Status::idByName('Reopened');

        if ($startDate && $endDate) {
            $widgetStart = $startDate->copy();
            $widgetEnd = $endDate->copy();
        } else {
            // All-time: derive real bounds from the data so bucketing has an
            // actual range to work with, instead of an arbitrary epoch anchor.
            $earliestCreatedAt = (clone $visibleTickets)->min('created_at');
            $widgetStart = $earliestCreatedAt ? Carbon::parse($earliestCreatedAt)->startOfDay() : now()->startOfDay();
            $widgetEnd = now()->endOfDay();
        }

        $forceBucketUnit = (!$startDate && !$endDate) ? 'month' : null;
        $widgets = \App\Services\TicketReportQueries::widgets((clone $visibleTickets)->whereBetween('created_at', [$widgetStart, $widgetEnd])
            ->select(['id', 'status_id', 'department_id', 'division_id', 'issue_id', 'custom_issue', 'created_at', 'resolved_at', 'reopened_at']), $widgetStart, $widgetEnd, $forceBucketUnit);
        $dashKpis = $widgets['kpis'];
        $dashVolumeTrend = $widgets['volume'];
        $dashResolutionDistribution = $widgets['resolution'];
        $dashSlaBreakdown = $widgets['sla'];
        $dashReassignment = $widgets['reassignment'];

        return [
            'latestTickets' => $latestTickets,
            'divisionLabels' => $divisionLabels,
            'divisionCounts' => $divisionCounts,
            'divisionColors' => $divisionColors,
            'statusLabels' => $statusLabels,
            'statusData' => $statusCounts,
            'statusColors' => $statusColors,
            'dashKpis' => $dashKpis,
            'volumeTrendLabels' => $dashVolumeTrend['labels'],
            'volumeTrendCounts' => $dashVolumeTrend['counts'],
            'resolutionLabels' => $dashResolutionDistribution['labels'],
            'resolutionCounts' => $dashResolutionDistribution['counts'],
            'overdueByIssue' => $dashSlaBreakdown['by_issue'],
            'reassignedCount' => $dashReassignment['reassigned_count'],
            'reassignmentTotal' => $dashReassignment['total'],
            'reassignmentRate' => $dashReassignment['rate'],
            'reassignDivisionLabels' => $dashReassignment['division_labels'],
            'reassignDivisionRates' => $dashReassignment['division_rates'],
            'dashWidgetStart' => $widgetStart,
            'dashWidgetEnd' => $widgetEnd,
        ];
    }
}
