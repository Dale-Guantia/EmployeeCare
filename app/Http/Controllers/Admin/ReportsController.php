<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Division;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;


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

    public function index()
    {
        $data = $this->getReportData();

        return view('admin.reports', array_merge([
            'title' => 'Reports',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Reports' => false,
            ],
            'page' => 'resources/views/admin/reports.blade.php',
            'controller' => 'app/Http/Controllers/Admin/ReportsController.php',
        ], $data));
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

        $pdf = Pdf::loadView('admin.pdf.reports_pdf', array_merge($data, [
            'reportStartDate' => $startDate->format('F j, Y'),
            'reportEndDate' => $endDate->format('F j, Y'),
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream(
            'ticketing-report-' . $startDate->format('Ymd') . '-to-' . $endDate->format('Ymd') . '.pdf'
        );
    }

    protected function getReportData($startDate = null, $endDate = null, $includeZeroActivity = false)
    {
        $ticketQuery = Ticket::query();

        if ($startDate && $endDate) {
            $ticketQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $ticketIds = (clone $ticketQuery)->pluck('id');

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
                }
            ])
            ->get();

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
                            THEN TIMESTAMPDIFF(MINUTE, tickets.created_at, tickets.resolved_at)
                            ELSE NULL
                        END
                    ) as avg_resolve_minutes
                ")
            )
            ->groupBy('issues.id', 'divisions.id', 'issues.issue_description', 'divisions.division_name')
            ->get();

        $ticketOverview->transform(function ($row) {
            $totalMinutes = (int) ($row->avg_resolve_minutes ?? 0);
            $hours = floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;
            $row->formatted_time = sprintf('%dh %02dm', $hours, $minutes);
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

        return [
            'users' => $users,
            'latestTickets' => $latestTickets,
            'ticketOverview' => $ticketOverview,
            'divisions' => $divisions,
        ];
    }
}
