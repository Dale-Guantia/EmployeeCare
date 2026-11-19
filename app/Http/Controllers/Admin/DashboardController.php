<?php

namespace App\Http\Controllers\Admin;

use App\Models\Division;
use App\Models\Status;
use App\Models\Ticket;
use App\Services\ReportPeriodResolver;
use App\Services\ReportOfficeScope;
use App\Services\DivisionChartQueries;
use App\Services\DashboardTicketMetrics;
use App\Services\TicketReportWidgets;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    private const COLOR_PALETTE = [
        Division::ADMINISTRATIVE => '#FF6384',
        Division::CLAIMS => '#36A2EB',
        Division::INFORMATION_TECHNOLOGY => '#4BC0C0',
        Division::LEARNING_AND_DEVELOPMENT => '#FFCE56',
        Division::PERFORMANCE_MANAGEMENT => '#9966FF',
        Division::PAYROLL => '#FF9F40',
        Division::RECORDS => '#C9CBCF',
        Division::RSP => '#4D5360',
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

        $scope = ReportOfficeScope::fromRequest($request);
        return view(backpack_view('dashboard'), array_merge($this->data, $scope->viewData(), $this->getDashboardData($startDate, $endDate, $scope, ReportOfficeScope::forDivisionChart($request))));
    }

    protected function getDashboardData($startDate = null, $endDate = null, ?ReportOfficeScope $scope = null, ?ReportOfficeScope $chartScope = null)
    {
        $scope ??= ReportOfficeScope::fromRequest(request());
        $chartScope ??= ReportOfficeScope::forDivisionChart(request());
        $visibleTickets = $scope->apply(Ticket::visibleTo(backpack_user()));
        $at = now();
        $widgetStart = $startDate ? $startDate->copy() : (($earliest = (clone $visibleTickets)->min('created_at'))
            ? Carbon::parse($earliest)->startOfDay() : $at->copy()->startOfDay());
        $widgetEnd = $endDate ? $endDate->copy() : $at->copy()->endOfDay();
        $periodTickets = (clone $visibleTickets)->whereBetween('created_at', [$widgetStart, $widgetEnd]);
        $dashboard = DashboardTicketMetrics::summary(clone $periodTickets, $at);
        $olderOpenCount = (clone $visibleTickets)->where('status_id', '<>', Status::idByCode(Status::RESOLVED))
            ->where('created_at', '<', $widgetStart)->count();
        $visibleIds = (clone $periodTickets)->select('tickets.id');
        $ticketsPerDivisionRaw = DivisionChartQueries::data($chartScope, $startDate, $endDate, true)
            ->sortBy([['total', 'desc'], ['division_name', 'asc']])->values();

        $divisionLabels = [];
        $divisionCounts = [];
        $divisionColors = [];

        foreach ($ticketsPerDivisionRaw as $item) {
            $divisionLabels[] = $item->division_name;
            $divisionCounts[] = $item->total;
            $divisionColors[] = $item->id === null ? '#6c757d' : (self::COLOR_PALETTE[$item->division_code] ?? '#36A2EB');
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

        $resolvedStatusId = (int) Status::idByCode(Status::RESOLVED);
        $pendingStatusId = (int) Status::idByCode(Status::PENDING);
        $reopenedStatusId = (int) Status::idByCode(Status::REOPENED);

        $forceBucketUnit = (!$startDate && !$endDate) ? 'month' : null;
        $widgets = \App\Services\TicketReportQueries::widgets((clone $visibleTickets)->whereBetween('created_at', [$widgetStart, $widgetEnd])
            ->select(['id', 'status_id', 'department_id', 'division_id', 'issue_id', 'custom_issue', 'created_at', 'resolved_at', 'reopened_at']), $widgetStart, $widgetEnd, $forceBucketUnit, $scope);
        $dashKpis = $widgets['kpis'];
        $dashKpis['avg_resolution_minutes'] = $dashboard['metrics']['avg_resolution_minutes'];
        $dashKpis['avg_resolution_formatted'] = $dashboard['metrics']['avg_resolution_formatted'];
        $dashVolumeTrend = $widgets['volume'];
        $dashResolutionDistribution = $widgets['resolution'];
        $dashSlaBreakdown = $widgets['sla'];
        $dashReassignment = $widgets['reassignment'];

        return array_merge([
            'latestTickets' => $latestTickets,
            'divisionLabels' => $divisionLabels,
            'divisionCounts' => $divisionCounts,
            'divisionColors' => $divisionColors,
            'statusLabels' => $statusLabels,
            'statusData' => $statusCounts,
            'statusColors' => $statusColors,
            'dashKpis' => $dashKpis,
            'dashboardMetrics' => $dashboard['metrics'],
            'dashboardIssues' => $dashboard['issues'],
            'attentionTickets' => DashboardTicketMetrics::attention(clone $periodTickets, $at),
            'olderOpenCount' => $olderOpenCount,
            'dashboardAsOf' => $at,
            'staffLeaderboard' => \App\Services\TicketReportQueries::staffLeaderboard((clone $visibleTickets)
                ->whereBetween('created_at', [$widgetStart, $widgetEnd])),
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
        ], $chartScope->divisionChartViewData());
    }
}
