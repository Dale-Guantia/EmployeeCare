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
        // The Eloquent "created" event (which is what routes into this method
        // via TicketObserver) fires from inside performInsert(), BEFORE
        // syncOriginal() runs back in save(). That means $ticket->original is
        // still empty here, so every attribute — including the identity
        // column "id" that insertAndSetId() just set — reads as dirty. The
        // save() below would then emit an UPDATE that assigns "id" to itself.
        // MySQL tolerates that as a no-op; SQL Server's IDENTITY column
        // rejects it outright ("Cannot update identity column"). Syncing here
        // rebases dirty-tracking onto the just-inserted row so only the
        // fields this method actually changes end up in the UPDATE.
        $ticket->syncOriginal();

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
            $pendingStatusId = Status::idByName('Pending');
            if ($pendingStatusId) {
                $ticket->status_id = $pendingStatusId;
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
        // Fetch exact IDs for active statuses to prevent hardcoding errors
        $pendingId = Status::idByName('Pending');
        $reopenedId = Status::idByName('Reopened');
        $activeStatusIds = array_filter([$pendingId, $reopenedId]);

        // Count active tickets for each staff member to ensure FAIRNESS.
        // One grouped aggregate query instead of one COUNT per staff member.
        $workloadByStaffId = Ticket::whereIn('assigned_to', $eligibleStaff->pluck('id'))
            ->whereIn('status_id', $activeStatusIds)
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as cnt')
            ->pluck('cnt', 'assigned_to');

        $staffWithWorkload = $eligibleStaff->map(function ($staff) use ($workloadByStaffId) {
            $staff->active_ticket_count = (int) ($workloadByStaffId[$staff->id] ?? 0);
            return $staff;
        });

        // Custom (free-text) issues have no curated category to match skills
        // against reliably — a user typing "password thing" shouldn't route to
        // whoever happens to have a "Password Reset" skill by coincidence. So
        // custom issues skip skill scoring entirely and route purely by
        // workload; only standard, catalog-selected Issues get skill matching.
        if ($ticket->is_custom_issue) {
            foreach ($staffWithWorkload as $staff) {
                $staff->match_score = 0;
            }
        } else {
            // Combine the ticket's Issue name, subject, and message for keyword
            // scanning. The Issue name MUST be included — it's the structured
            // field that already routed this ticket to the division, and staff
            // skills are expected to match it directly (e.g. a skill of
            // "Office assignment/ Retagging" should match an Issue of the same
            // name even when the free-text message says nothing relevant).
            $issueName = optional($ticket->issue)->issue_description ?? '';
            $subject = $ticket->subject ?? '';
            $ticketContent = $this->normalizeForMatching($issueName . ' ' . $subject . ' ' . $ticket->message);

            foreach ($staffWithWorkload as $staff) {
                $staff->match_score = 0;

                if (!empty($staff->skills) && is_array($staff->skills)) {
                    foreach ($staff->skills as $skill) {
                        $normalizedSkill = $this->normalizeForMatching($skill);

                        if ($normalizedSkill !== '' && Str::contains($ticketContent, $normalizedSkill)) {
                            $staff->match_score += 5;
                        }
                    }
                }
            }
        }

        // --- The Upgraded Fairness Algorithm ---
        // We use a custom sort closure to guarantee exact priorities:
        // Priority 1: Highest skill match wins (always 0-0 for custom issues).
        // Priority 2: If skills are tied, lowest active ticket count wins.
        // Priority 3: If that's ALSO tied, lowest id wins — a deterministic,
        // explicit tie-breaker rather than relying on incidental collection
        // order, so the outcome never varies between runs or PHP versions.
        $bestMatch = $staffWithWorkload->sort(function ($a, $b) {
            if ($a->match_score !== $b->match_score) {
                return $b->match_score <=> $a->match_score;
            }
            if ($a->active_ticket_count !== $b->active_ticket_count) {
                return $a->active_ticket_count <=> $b->active_ticket_count;
            }
            return $a->id <=> $b->id;
        })->first();

        return $bestMatch;
    }

    // Lowercases, trims, collapses repeated whitespace, and removes whitespace
    // immediately around punctuation like "/" or "-". Without the last step,
    // "Office assignment/ Retagging" (issue name) and "office assignment
    // /retagging" (a skill typed with the space before the slash instead of
    // after) would lower/trim to two different strings and fail to match
    // even though they're clearly the same phrase.
    private function normalizeForMatching(string $text): string
    {
        $text = Str::lower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\s*([\/\-])\s*/', '$1', $text);

        return $text;
    }
}
