<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use App\Models\Ticket;
use App\Models\Issue;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | TABLE DATA
        |--------------------------------------------------------------------------
        */
        $users = User::with('division')
            ->where('department_id', 1)
            ->withCount([
                'resolvedTickets', // This creates 'resolved_tickets_count'
                'overdueTickets'   // This creates 'overdue_tickets_count'
            ])
            ->get();

        $latestTickets = Ticket::with(['user','issue','status','priority'])
            ->latest()
            ->limit(10)
            ->get();

        $ticketOverview = DB::table('issues')
            // 1. Join Division directly to Issue (This ensures the name is always there)
            ->join('divisions', 'issues.division_id', '=', 'divisions.id')
            // 2. Left Join Tickets to count them (even if there are 0)
            ->leftJoin('tickets', 'issues.id', '=', 'tickets.issue_id')
            ->select(
                'issues.issue_description',
                'divisions.division_name',
                DB::raw('COUNT(tickets.id) as total_tickets'),
                // Average resolve time (will be NULL if 0 tickets)
                DB::raw("AVG(CASE WHEN tickets.status_id = 1 THEN TIMESTAMPDIFF(MINUTE, tickets.created_at, tickets.resolved_at) ELSE NULL END) as avg_resolve_minutes")
            )
            ->groupBy('issues.id', 'divisions.id', 'issues.issue_description', 'divisions.division_name')
            ->get();

        // Keep your existing transform logic
        $ticketOverview->transform(function ($row) {
            $totalMinutes = $row->avg_resolve_minutes ?? 0;
            $hours = floor($totalMinutes / 60);
            $minutes = round($totalMinutes % 60);

            $row->formatted_time = sprintf('%02dh %02dm', $hours, $minutes);
            return $row;
        });

        return view('admin.reports', [
            'title' => 'Reports',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Reports' => false,
            ],
            'page' => 'resources/views/admin/reports.blade.php',
            'controller' => 'app/Http/Controllers/Admin/ReportsController.php',

            'users' => $users,
            'latestTickets' => $latestTickets,
            'ticketOverview' => $ticketOverview,
        ]);
    }
}
