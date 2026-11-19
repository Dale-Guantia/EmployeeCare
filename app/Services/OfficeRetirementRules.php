<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Division;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketReassignmentRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OfficeRetirementRules
{
    public static function isProtected($office): bool
    {
        return $office instanceof Department && $office->hasCode(Department::HRDO);
    }

    public static function assertCanArchive($office): void
    {
        if (self::isProtected($office)) {
            throw ValidationException::withMessages(['is_active' => 'This department is required by the survey. Its retirement needs a separate survey configuration change.']);
        }

        $department = $office instanceof Department;
        $field = $department ? 'department_id' : 'division_id';
        $id = $office->getKey();
        $children = $department ? Division::where('department_id', $id)->select('id') : null;
        $location = function ($query) use ($field, $id, $children) {
            $query->where($field, $id);
            if ($children) {
                $query->orWhereIn('division_id', $children);
            }
        };
        $users = User::where('is_active', true)->where($location)->count();
        $resolved = Status::idByCode(Status::RESOLVED);
        $tickets = Ticket::where($location)
            ->when($resolved !== null, fn ($query) => $query->where('status_id', '!=', $resolved))->count();
        $requests = TicketReassignmentRequest::where('status', TicketReassignmentRequest::STATUS_PENDING)
            ->where(function ($query) use ($field, $id, $children) {
                $query->where('from_' . $field, $id)->orWhere('to_' . $field, $id);
                if ($children) {
                    $query->orWhereIn('from_division_id', $children)->orWhereIn('to_division_id', $children);
                }
            })->count();

        if ($users || $tickets || $requests) {
            throw ValidationException::withMessages([
                'is_active' => "Cannot archive: {$users} active user account(s), {$tickets} unresolved ticket(s), and {$requests} pending transfer(s) still reference this office. Reassign users and resolve or transfer the work first.",
            ]);
        }
    }
}
