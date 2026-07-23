<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Division;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Services\SqlDialectHelper;
use App\Services\TicketReportWidgets;


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
        [$period, $startDate, $endDate, $half, $year, $periodError] = $this->resolvePeriod($request);

        $data = $this->getReportData($startDate, $endDate);

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
        ], $data));
    }

    private function resolvePeriod(Request $request): array
    {
        $period = $request->query('period', 'month');
        $year = (int) $request->query('year', now()->year);

        if ($period === 'all') {
            return ['all', null, null, null, $year, null];
        }

        if ($period === 'half') {
            $half = $request->query('half');
            if (!in_array($half, ['h1', 'h2'], true)) {
                // Default to whichever half today falls in.
                $half = now()->month <= 6 ? 'h1' : 'h2';
            }

            if ($half === 'h1') {
                $start = Carbon::create($year, 1, 1)->startOfDay();
                $end = Carbon::create($year, 6, 30)->endOfDay();
            } else {
                $start = Carbon::create($year, 7, 1)->startOfDay();
                $end = Carbon::create($year, 12, 31)->endOfDay();
            }

            return ['half', $start, $end, $half, $year, null];
        }

        if ($period === 'custom') {
            $startInput = $request->query('start');
            $endInput = $request->query('end');
            $start = null;
            $end = null;
            $error = null;

            if (!$startInput || !$endInput) {
                $error = 'Please provide both a start and end date for a custom range.';
            } else {
                try {
                    $start = Carbon::parse($startInput)->startOfDay();
                    $end = Carbon::parse($endInput)->endOfDay();
                } catch (\Exception $e) {
                    $error = 'Those dates could not be understood.';
                }

                if ($start && $end && $end->lt($start)) {
                    $error = 'End date must be on or after the start date.';
                    $start = null;
                    $end = null;
                }
            }

            if ($start && $end) {
                return ['custom', $start, $end, null, $year, null];
            }

            // Validation failed: fall back to "This Month" rather than crash
            // or silently swap the dates, carrying the error message so the
            // page can display it.
            $error = ($error ?? 'Invalid custom range.') . ' Showing this month instead.';
            $start = now()->startOfMonth()->startOfDay();
            $end = now()->endOfDay();

            return ['month', $start, $end, null, $year, $error];
        }

        // "month" (default) — current calendar month to date.
        $start = now()->startOfMonth()->startOfDay();
        $end = now()->endOfDay();

        return ['month', $start, $end, null, $year, null];
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

        $data = $this->getReportData($startDate, $endDate, $includeZeroActivity);

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

    protected function getReportData($startDate = null, $endDate = null, $includeZeroActivity = false)
    {
        $users = User::with('division')
            ->where('department_id', 1)
            ->withCount([
                'resolvedTickets as resolved_tickets_count' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('tickets.created_at', [$startDate, $endDate]);
                    }
                },
                'overdueTickets as overdue_tickets_count' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('tickets.created_at', [$startDate, $endDate]);
                    }
                },
                'assignedTickets as assigned_count' => function ($query) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('tickets.created_at', [$startDate, $endDate]);
                    }
                },
            ])
            ->get();

        $staffResolvedStatusId = (int) \App\Models\Status::idByName('Resolved');

        $staffResolutionRows = Ticket::where('status_id', $staffResolvedStatusId)
            ->whereNotNull('resolved_by')
            ->whereNotNull('resolved_at')
            ->whereIn('resolved_by', $users->pluck('id'))
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->get(['resolved_by', 'created_at', 'resolved_at']);

        $avgResolutionByStaff = $staffResolutionRows
            ->groupBy('resolved_by')
            ->map(function ($rows) {
                $minutes = $rows->map(function ($ticket) {
                    return Carbon::parse($ticket->created_at)->diffInMinutes(Carbon::parse($ticket->resolved_at));
                });

                return (int) round($minutes->avg());
            });

        $users->each(function ($user) use ($avgResolutionByStaff) {
            $avgMinutes = (int) ($avgResolutionByStaff[$user->id] ?? 0);
            $user->avg_resolution_minutes = $avgMinutes;
            $user->avg_resolution_formatted = TicketReportWidgets::formatMinutes($avgMinutes);
        });

        if (!$includeZeroActivity) {
            $users = $users->filter(function ($user) {
                return $user->resolved_tickets_count > 0 || $user->overdue_tickets_count > 0;
            })->values();
        }

        $latestTickets = Ticket::with(['user', 'issue', 'status', 'priority'])
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->latest()
            ->limit(10)
            ->get();

        $diffMinutesSql = SqlDialectHelper::diffMinutesSql('tickets.created_at', 'tickets.resolved_at');

        $ticketOverview = DB::table('issues')
            ->join('divisions', 'issues.division_id', '=', 'divisions.id')
            ->leftJoin('tickets', function ($join) use ($startDate, $endDate) {
                $join->on('issues.id', '=', 'tickets.issue_id');

                if ($startDate && $endDate) {
                    $join->whereBetween('tickets.created_at', [$startDate, $endDate]);
                }
            })
            ->select(
                'issues.issue_description',
                'divisions.id as division_id',
                'divisions.division_name',
                DB::raw('COUNT(tickets.id) as total_tickets'),
                DB::raw("
                    AVG(
                        CASE
                            WHEN tickets.resolved_at IS NOT NULL
                            THEN {$diffMinutesSql}
                            ELSE NULL
                        END
                    ) as avg_resolve_minutes
                ")
            )
            ->groupBy('issues.id', 'divisions.id', 'issues.issue_description', 'divisions.division_name')
            ->get();

        $ticketOverview->transform(function ($row) {
            $row->formatted_time = TicketReportWidgets::formatMinutes((int) ($row->avg_resolve_minutes ?? 0));
            return $row;
        });

        $divisions = Division::query()
            ->withCount(['tickets as tickets_count' => function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
            }])
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

        $resolvedStatusId = (int) Status::idByName('Resolved');
        $pendingStatusId = (int) Status::idByName('Pending');
        $reopenedStatusId = (int) Status::idByName('Reopened');

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
            $earliestCreatedAt = Ticket::min('created_at');
            $widgetStart = $earliestCreatedAt ? Carbon::parse($earliestCreatedAt)->startOfDay() : now()->startOfDay();
            $widgetEnd = now()->endOfDay();
        }

        $widgetTickets = Ticket::with(['department', 'division', 'issue'])
            ->whereBetween('created_at', [$widgetStart, $widgetEnd])
            ->get(['id', 'status_id', 'department_id', 'division_id', 'issue_id', 'custom_issue', 'assigned_to', 'created_at', 'resolved_at', 'reopened_at']);

        // All-time is open-ended by nature, so it always buckets by month
        // regardless of how much data currently falls inside it.
        $forceBucketUnit = (!$startDate && !$endDate) ? 'month' : null;

        $reportKpis = TicketReportWidgets::buildKpiWidget($widgetTickets, $resolvedStatusId, $pendingStatusId, $reopenedStatusId);
        $reportVolumeTrend = TicketReportWidgets::buildVolumeTrendWidget($widgetTickets, $widgetStart, $widgetEnd, $forceBucketUnit);
        $reportResolutionDistribution = TicketReportWidgets::buildResolutionDistributionWidget($widgetTickets, $resolvedStatusId);
        $reportSlaBreakdown = TicketReportWidgets::buildSlaBreakdownWidget($widgetTickets, $resolvedStatusId);
        $reportStatusFunnel = TicketReportWidgets::buildStatusFunnelWidget($widgetTickets);
        $reportReassignment = TicketReportWidgets::buildReassignmentWidget($widgetTickets, $widgetStart, $widgetEnd);

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
