<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Issue;
use App\Services\ReportOfficeScope;
use App\Models\User;
use App\Models\Division;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Services\SqlDialectHelper;
use App\Services\TicketReportWidgets;
use App\Services\ReportPeriodResolver;


class ReportsController extends Controller
{

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!backpack_user() || !backpack_user()->can('reports.view')) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        [$period, $startDate, $endDate, $half, $year, $periodError] = ReportPeriodResolver::resolve($request);

        $scope = ReportOfficeScope::fromRequest($request);
        $data = $this->getReportData($startDate, $endDate, false, $scope);
        $data['reportVisitors'] = $scope->apply(User::activeToday(), 'users')->where('last_login_at', '<=', now())->count();
        $ticketIds = $scope->apply(Ticket::visibleTo(backpack_user()))
            ->whereBetween('tickets.created_at', [$data['reportWidgetStart'], $data['reportWidgetEnd']])->select('tickets.id');
        $statuses = Status::withCount(['tickets' => fn ($query) => $query->whereIn('tickets.id', $ticketIds)])->get();
        $data['reportTicketsOverview'] = ['labels' => $statuses->pluck('status_name')->all(),
            'counts' => $statuses->pluck('tickets_count')->all(), 'colors' => $statuses->pluck('status_color')->all()];

        return view('admin.reports', array_merge([
            'title' => 'Reports',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Reports' => false,
            ],
            'page' => 'resources/views/admin/reports.blade.php',
            'controller' => 'app/Http/Controllers/Admin/ReportsController.php',
            'activePeriod' => $period,
            'activeHalf' => $half,
            'activeYear' => $year,
            'periodError' => $periodError,
        ], $scope->viewData(), $data));
    }

    public function downloadPdf(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'include_zero_activity' => 'nullable|boolean',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $includeZeroActivity = $request->boolean('include_zero_activity');

        $scope = ReportOfficeScope::fromRequest($request);
        $data = $this->getReportData($startDate, $endDate, $includeZeroActivity, $scope);

        try {
            $pdf = Pdf::loadView('admin.pdf.reports_pdf', array_merge($data, [
                'reportStartDate' => $startDate->format('F j, Y'),
                'reportEndDate' => $endDate->format('F j, Y'),
            ]))->setPaper('a4', 'portrait');

            return $pdf->stream(
                'ticketing-report-' . $startDate->format('Ymd') . '-to-' . $endDate->format('Ymd') . '.pdf'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[ReportsController] PDF generation failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not generate the PDF report. Try a narrower date range, or contact support if this continues.');
        }
    }

    protected function getReportData($startDate = null, $endDate = null, $includeZeroActivity = false, ?ReportOfficeScope $scope = null)
    {
        $scope ??= ReportOfficeScope::fromRequest(request());
        $baseTickets = $scope->apply(Ticket::visibleTo(backpack_user()));
        $periodTickets = (clone $baseTickets)->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('tickets.created_at', [$startDate, $endDate]);
        });
        $visibleIds = (clone $periodTickets)->reorder()->select('tickets.id');
        $users = $scope->apply(User::with('division'), 'users')
            ->withCount([
                'resolvedTickets as resolved_tickets_count' => fn ($query) => $query->whereIn('tickets.id', clone $visibleIds),
                'overdueTickets as overdue_tickets_count' => fn ($query) => $query->whereIn('tickets.id', clone $visibleIds),
                'assignedTickets as assigned_count' => fn ($query) => $query->whereIn('tickets.id', clone $visibleIds),
            ])->get();

        $staffResolvedStatusId = (int) \App\Models\Status::idByCode(\App\Models\Status::RESOLVED);

        $staffResolutionQuery = (clone $periodTickets)->where('status_id', $staffResolvedStatusId)
            ->whereNotNull('resolved_by')
            ->whereNotNull('resolved_at')
            ->whereIn('resolved_by', $users->pluck('id'));

        $avgResolutionByStaff = \App\Services\TicketReportQueries::staffResolutionAverages($staffResolutionQuery);

        $users->each(function ($user) use ($avgResolutionByStaff) {
            $avgMinutes = $avgResolutionByStaff->has($user->id) ? (int) $avgResolutionByStaff[$user->id] : null;
            $user->avg_resolution_minutes = $avgMinutes;
            $user->avg_resolution_formatted = TicketReportWidgets::formatAverageMinutes($avgMinutes);
        });

        if (!$includeZeroActivity) {
            $users = $users->filter(function ($user) {
                return $user->resolved_tickets_count > 0 || $user->overdue_tickets_count > 0;
            })->values();
        }

        $latestTickets = (clone $periodTickets)->with(['user', 'issue', 'status', 'priority'])
            ->latest()
            ->limit(10)
            ->get();

        $diffMinutesSql = SqlDialectHelper::diffMinutesSql('tickets.created_at', 'tickets.resolved_at');

        $category = "CASE WHEN tickets.is_custom_issue = 1 THEN COALESCE(NULLIF(tickets.custom_issue, ''), 'Custom request')
            ELSE COALESCE(issues.issue_description, NULLIF(tickets.custom_issue, ''), 'Issue unavailable') END";
        $ticketOverview = DB::table('tickets')
            ->leftJoin('issues', 'tickets.issue_id', '=', 'issues.id')
            ->leftJoin('divisions', 'tickets.division_id', '=', 'divisions.id')
            ->whereIn('tickets.id', clone $visibleIds)
            ->select('issues.id as issue_id', 'divisions.id as division_id', 'divisions.division_name',
                DB::raw($category . ' as issue_description'), DB::raw('COUNT(tickets.id) as total_tickets'))
            ->selectRaw("AVG(CASE WHEN tickets.status_id = ? AND tickets.resolved_at IS NOT NULL THEN {$diffMinutesSql} ELSE NULL END) as avg_resolve_minutes", [$staffResolvedStatusId])
            ->groupBy('issues.id', 'issues.issue_description', 'tickets.custom_issue', 'tickets.is_custom_issue', 'divisions.id', 'divisions.division_name')->get();
        if ($includeZeroActivity) {
            foreach ($scope->apply(Issue::with('division'), 'issues')->get() as $issue) {
                if (!$ticketOverview->contains(fn ($row) => (int) $row->issue_id === (int) $issue->id
                    && (string) $row->division_id === (string) $issue->division_id)) {
                    $ticketOverview->push((object) ['issue_id' => $issue->id, 'issue_description' => $issue->issue_description,
                        'division_id' => $issue->division_id, 'division_name' => $issue->division?->division_name,
                        'total_tickets' => 0, 'avg_resolve_minutes' => null]);
                }
            }
        }

        $ticketOverview->transform(function ($row) {
            $row->formatted_time = TicketReportWidgets::formatAverageMinutes($row->avg_resolve_minutes === null ? null : (int) $row->avg_resolve_minutes);
            return $row;
        });

        $divisions = $scope->divisions(Division::query())
            ->withCount(['tickets as tickets_count' => fn ($query) => $query->whereIn('tickets.id', clone $visibleIds)])
            ->get()
            ->map(function ($division) use ($ticketOverview, $includeZeroActivity) {
                $division->problemCategories = $ticketOverview
                    ->where('division_id', $division->id)
                    ->filter(function ($row) use ($includeZeroActivity) {
                        return $includeZeroActivity || $row->total_tickets > 0;
                    })
                    ->map(function ($row) {
                        return (object) [
                            'category_name' => $row->issue_description,
                            'tickets_count' => $row->total_tickets,
                            'average_resolve_time' => $row->formatted_time,
                        ];
                    })
                    ->values();

                return $division;
            });

        if (!$includeZeroActivity) {
            $divisions = $divisions->filter(function ($division) {
                return $division->tickets_count > 0 || $division->problemCategories->count() > 0;
            })->values();
        }
        $departmentCategories = $ticketOverview->whereNull('division_id');
        if ($departmentCategories->isNotEmpty()) {
            $divisions->push((object) [
                'id' => null, 'division_name' => 'Department-level (no division)',
                'tickets_count' => $departmentCategories->sum('total_tickets'),
                'problemCategories' => $departmentCategories->map(fn ($row) => (object) [
                    'category_name' => $row->issue_description, 'tickets_count' => $row->total_tickets,
                    'average_resolve_time' => $row->formatted_time,
                ])->values(),
            ]);
        }

        $resolvedStatusId = (int) Status::idByCode(Status::RESOLVED);
        $pendingStatusId = (int) Status::idByCode(Status::PENDING);
        $reopenedStatusId = (int) Status::idByCode(Status::REOPENED);

        // $startDate/$endDate now mean exactly what they say: null on both
        // means genuinely all-time (no filter), matching the same semantics
        // already used by the users/ticketOverview/divisions/latestTickets
        // queries above. There is no more "default to last 30 days" fallback
        // here — the caller (index(), or downloadPdf()'s validated request)
        // decides the window; index() defaults to "This Month" when no
        // period is chosen (see resolvePeriod()).
        if ($startDate && $endDate) {
            $widgetStart = $startDate->copy();
            $widgetEnd = $endDate->copy();
        } else {
            // All-time: derive real bounds from the data so bucketing has an
            // actual range to work with, instead of an arbitrary epoch anchor.
            $earliestCreatedAt = (clone $baseTickets)->min('created_at');
            $widgetStart = $earliestCreatedAt ? Carbon::parse($earliestCreatedAt)->startOfDay() : now()->startOfDay();
            $widgetEnd = now()->endOfDay();
        }

        $forceBucketUnit = (!$startDate && !$endDate) ? 'month' : null;
        $widgets = \App\Services\TicketReportQueries::widgets((clone $baseTickets)->whereBetween('created_at', [$widgetStart, $widgetEnd])
            ->select(['id', 'status_id', 'department_id', 'division_id', 'issue_id', 'custom_issue', 'created_at', 'resolved_at', 'reopened_at']), $widgetStart, $widgetEnd, $forceBucketUnit, $scope);
        $reportKpis = $widgets['kpis'];
        $reportVolumeTrend = $widgets['volume'];
        $reportResolutionDistribution = $widgets['resolution'];
        $reportSlaBreakdown = $widgets['sla'];
        $reportReassignment = $widgets['reassignment'];
        $reportStatusFunnel = $widgets['funnel'];

        return [
            'users' => $users,
            'latestTickets' => $latestTickets,
            'ticketOverview' => $ticketOverview,
            'divisions' => $divisions,
            'reportKpis' => $reportKpis,
            'reportVolumeTrend' => $reportVolumeTrend,
            'reportResolutionDistribution' => $reportResolutionDistribution,
            'reportSlaBreakdown' => $reportSlaBreakdown,
            'reportStatusFunnel' => $reportStatusFunnel,
            'reportReassignment' => $reportReassignment,
            'reportWidgetStart' => $widgetStart,
            'reportWidgetEnd' => $widgetEnd,
        ];
    }

}
