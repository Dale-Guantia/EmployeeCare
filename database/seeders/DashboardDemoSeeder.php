<?php

namespace Database\Seeders;

use App\Models\{Department, Division, Issue, Priority, Status, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardDemoSeeder extends Seeder
{
    public const PREFIX = 'DEMO-DASH-';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Dashboard demo data may only be seeded locally or in tests.');
        }

        $created = DB::transaction(function () {
            $statuses = Status::where('is_active', true)->pluck('id', 'status_code');
            foreach (Status::CORE_CODES as $code) {
                if (! isset($statuses[$code])) {
                    throw new \LogicException('Seed the active core workflow statuses first.');
                }
            }

            $departments = Department::where('is_active', true)->pluck('id');
            $divisions = Division::where('is_active', true)->whereIn('department_id', $departments)->get()->keyBy('id');
            $priorities = Priority::where('is_active', true)->orderBy('id')->pluck('id');
            $requesters = User::role('employee')->where('is_active', true)->orderBy('id')->get();
            $staff = User::role(['hr_staff', 'div_head'])->where('is_active', true)
                ->whereIn('division_id', $divisions->keys())->orderBy('id')->get()
                ->filter(fn ($user) => (int) $user->department_id === (int) $divisions[$user->division_id]->department_id)
                ->groupBy('division_id');
            $groups = Issue::where('is_active', true)->whereIn('department_id', $departments)
                ->whereIn('division_id', $staff->keys())->orderBy('id')->get()
                ->filter(fn ($issue) => (int) $issue->department_id === (int) $divisions[$issue->division_id]->department_id)
                ->groupBy('division_id')->values();

            if ($requesters->isEmpty() || $groups->isEmpty() || $priorities->isEmpty()) {
                throw new \LogicException('Demo data needs active employees, priorities, and issues with staff in active offices.');
            }

            $now = now();
            $created = 0;
            for ($i = 0; $i < 240; $i++) {
                $reference = self::PREFIX . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
                // Never refresh or overwrite existing tickets, including edited demo tickets.
                if (DB::table('tickets')->where('reference_id', $reference)->exists()) {
                    continue;
                }

                $cycle = intdiv($i, $groups->count());
                $issues = $groups[$i % $groups->count()];
                $issue = $issues[$cycle % $issues->count()];
                $team = $staff[$issue->division_id];
                $assignee = $team[$cycle % $team->count()];
                $requester = $requesters[$i % $requesters->count()];
                $code = match ($i % 10) {
                    0 => Status::UNASSIGNED,
                    1, 2 => Status::PENDING,
                    3 => Status::REOPENED,
                    default => Status::RESOLVED,
                };
                $hours = [2, 6, 18, 36, 54, 72, 84, 108, 156][intdiv($i, 10) % 9];
                if ($i < 120) {
                    $submitted = $now->copy()->startOfMonth()->subMonthsNoOverflow(1 + intdiv($i, 24))
                        ->addDays(($i * 7) % 27)->addHours(8 + $i % 9);
                } else {
                    $availableHours = max(1, (int) $now->copy()->startOfMonth()->diffInHours($now));
                    $submitted = $now->copy()->subHours(($i * 13) % $availableHours);
                }
                if ($code === Status::RESOLVED && $submitted->copy()->addHours($hours)->gte($now)) {
                    $submitted = $now->copy()->subHours($hours + 2);
                }
                // Ensure the attention list includes fresh, due-soon, and overdue work.
                if ($i >= 230 && $code !== Status::RESOLVED) {
                    $submitted = $now->copy()->subHours([12, 60, 96, 120][$i % 10]);
                }
                $resolved = $code === Status::RESOLVED ? $submitted->copy()->addHours($hours) : null;
                $updated = $resolved ?? $submitted->copy()->addHour()->min($now);
                $priority = $priorities->contains($issue->priority_id) ? $issue->priority_id : $priorities[$i % $priorities->count()];

                // Import historical fixtures directly: model hooks generate live notifications
                // and replace historical resolution timestamps with the current time.
                $ticketId = DB::table('tickets')->insertGetId([
                    'reference_id' => $reference,
                    'user_id' => $requester->id,
                    'department_id' => $issue->department_id,
                    'division_id' => $issue->division_id,
                    'issue_id' => $issue->id,
                    'custom_issue' => null,
                    'is_custom_issue' => false,
                    'priority_id' => $priority,
                    'status_id' => $statuses[$code],
                    'assigned_to' => $code === Status::UNASSIGNED ? null : $assignee->id,
                    'resolved_by' => $resolved ? $assignee->id : null,
                    'message' => '[DEMO] Sample request about ' . $issue->issue_description . '. Fictional dashboard data for demonstration only.',
                    'attachments' => null,
                    'created_at' => $submitted,
                    'updated_at' => $updated,
                    'resolved_at' => $resolved,
                    'reopened_at' => $code === Status::REOPENED ? $updated : null,
                ]);

                if ($code !== Status::UNASSIGNED) {
                    DB::table('ticket_comments')->insert([
                        'ticket_id' => $ticketId, 'user_id' => $assignee->id,
                        'comment' => '[DEMO] Request received. Reviewing the details and coordinating the next steps.',
                        'created_at' => $submitted->copy()->addHour()->min($now), 'updated_at' => $submitted->copy()->addHour()->min($now),
                    ]);
                }
                if ($resolved) {
                    DB::table('ticket_comments')->insert([
                        'ticket_id' => $ticketId, 'user_id' => $assignee->id,
                        'comment' => '[DEMO] Review completed and the requested assistance provided. Sample request resolved.',
                        'created_at' => $resolved, 'updated_at' => $resolved,
                    ]);
                }
                if ($groups->count() > 1 && $i % 9 === 0) {
                    $source = $groups[($i + 1) % $groups->count()]->first();
                    DB::table('ticket_reassignment_requests')->insert([
                        'ticket_id' => $ticketId,
                        'from_department_id' => $source->department_id, 'from_division_id' => $source->division_id,
                        'to_department_id' => $issue->department_id, 'to_division_id' => $issue->division_id,
                        'requested_by' => $staff[$source->division_id]->first()->id,
                        'responded_by' => $assignee->id, 'status' => 'accepted',
                        'reason' => '[DEMO] Routed to the office responsible for this request.',
                        'response_note' => '[DEMO] Routing accepted.',
                        'responded_at' => $submitted->copy()->addMinutes(30)->min($now),
                        'created_at' => $submitted, 'updated_at' => $submitted->copy()->addMinutes(30)->min($now),
                    ]);
                }
                $created++;
            }

            return $created;
        });

        $this->command?->info("Added {$created} demo tickets. Existing records were preserved; no notifications were sent.");
    }
}
