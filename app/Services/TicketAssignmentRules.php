<?php

namespace App\Services;

use App\Models\User;

class TicketAssignmentRules
{
    public static function canAssign($actor): bool
    {
        return $actor && $actor->is_active && ($actor->hasAnyRole(['admin', 'dept_head', 'div_head'])
            || ($actor->hasRole('hr_staff') && $actor->can('ticket.assign')));
    }

    public static function candidates($actor, $ticket = null)
    {
        $query = User::role('hr_staff')->where('is_active', true)
            ->whereHas('division', function ($division) {
                $division->whereColumn('divisions.department_id', 'users.department_id');
            });
        if (!self::canAssign($actor)) {
            return $query->whereRaw('1 = 0');
        }
        if ($actor->hasRole('admin')) {
            return $query;
        }
        if ($actor->hasRole('dept_head')) {
            return $actor->department_id ? $query->where('department_id', $actor->department_id) : $query->whereRaw('1 = 0');
        }
        if ($actor->hasRole('div_head')) {
            return $actor->division_id ? $query->where('division_id', $actor->division_id) : $query->whereRaw('1 = 0');
        }
        // Explicitly delegated HR staff retain the existing ticket-location boundary.
        return $query->where('department_id', $ticket->department_id ?? null)
            ->where('division_id', $ticket->division_id ?? null);
    }

    public static function validation($actor, $ticket = null): array
    {
        return ['nullable', 'integer', function ($attribute, $value, $fail) use ($actor, $ticket) {
            if (!self::canAssign($actor)) {
                if ((string) $value !== (string) ($ticket->assigned_to ?? null)) {
                    $fail('You are not permitted to change the assignee.');
                }
                return;
            }
            if ($value !== null && $value !== '' && !self::candidates($actor, $ticket)->whereKey($value)->exists()) {
                $fail('Please select an active eligible HR staff member within your assignment scope.');
            }
        }];
    }
}
