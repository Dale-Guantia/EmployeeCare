<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Status;
use Illuminate\Support\Str;

class TicketAutoAssignmentService
{
    public function assignTicket(Ticket $ticket)
    {
        // 1. Base Query: Find all "hr_staff" within the specific Department
        $query = User::role('hr_staff')->where('department_id', $ticket->department_id);

        // Handle hierarchy: If the department has divisions, filter by the ticket's division
        if ($ticket->division_id) {
            $query->where('division_id', $ticket->division_id);
        }

        $eligibleStaff = $query->get();

        // If no staff available, leave unassigned
        if ($eligibleStaff->isEmpty()) {
            return false;
        }

        // 2. Content-Based Matching & Fairness Load Balancer
        $bestStaff = $this->analyzeContentAndMatch($ticket, $eligibleStaff);

        // 3. Apply the Assignment and Update Status
        if ($bestStaff) {
            $ticket->assigned_to = $bestStaff->id;

            // EXPLICIT STATUS UPDATE: Change to "Pending"
            $pendingStatus = Status::where('status_name', 'Pending')->first();
            if ($pendingStatus) {
                $ticket->status_id = $pendingStatus->id;
            }

            $ticket->save();

            return true;
        }

        return false;
    }

    /**
     * Analyzes ticket text and distributes fairly based on active workload.
     */
    private function analyzeContentAndMatch(Ticket $ticket, $eligibleStaff)
    {
        // Combine subject and description for keyword scanning (if subject exists)
        $subject = $ticket->subject ?? '';
        $ticketContent = Str::lower($subject . ' ' . $ticket->message);

        // Fetch exact IDs for active statuses to prevent hardcoding errors
        $pendingId = Status::where('status_name', 'Pending')->value('id');
        $reopenedId = Status::where('status_name', 'Reopened')->value('id');
        $activeStatusIds = array_filter([$pendingId, $reopenedId]);

        // Count active tickets for each staff member to ensure FAIRNESS
        $staffWithWorkload = $eligibleStaff->map(function ($staff) use ($activeStatusIds) {
            $staff->active_ticket_count = Ticket::where('assigned_to', $staff->id)
                                                ->whereIn('status_id', $activeStatusIds)
                                                ->count();
            return $staff;
        });

        // Content/Skill Scoring
        foreach ($staffWithWorkload as $staff) {
            $staff->match_score = 0;

            if (!empty($staff->skills) && is_array($staff->skills)) {
                foreach ($staff->skills as $skill) {
                    if (Str::contains($ticketContent, strtolower($skill))) {
                        $staff->match_score += 5;
                    }
                }
            }
        }

        // --- The Upgraded Fairness Algorithm ---
        // We use a custom sort closure to guarantee exact priorities:
        // Priority 1: Highest skill match wins.
        // Priority 2: If skills are tied, lowest active ticket count wins.
        $bestMatch = $staffWithWorkload->sort(function ($a, $b) {
            if ($a->match_score === $b->match_score) {
                return $a->active_ticket_count <=> $b->active_ticket_count;
            }
            return $b->match_score <=> $a->match_score;
        })->first();

        return $bestMatch;
    }
}
