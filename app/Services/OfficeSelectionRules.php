<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficeSelectionRules
{
    public static function departments($query, $retainedId = null)
    {
        return $query->where(function ($query) use ($retainedId) {
            $query->where('is_active', true);
            if ($retainedId !== null) {
                $query->orWhere('id', $retainedId);
            }
        });
    }

    public static function divisions($query, $departmentId = null, $retainedId = null)
    {
        $query->where(function ($query) use ($retainedId) {
            $query->where(function ($active) {
                $active->where('is_active', true)
                    ->whereIn('department_id', Department::where('is_active', true)->select('id'));
            });
            if ($retainedId !== null) {
                $query->orWhere('id', $retainedId);
            }
        });

        if ($departmentId !== null) {
            $query->where('department_id', is_scalar($departmentId) ? $departmentId : null);
        }
        return $query;
    }

    public static function departmentRule($retainedId = null)
    {
        return Rule::exists('departments', 'id')->where(fn ($query) => self::departments($query, $retainedId));
    }

    public static function divisionRule($departmentId, $retainedId = null)
    {
        return Rule::exists('divisions', 'id')
            ->where('department_id', filter_var($departmentId, FILTER_VALIDATE_INT) !== false ? $departmentId : null)
            ->where(fn ($query) => self::divisions($query, null, $retainedId));
    }

    public static function issueDivisionRequired($departmentId, $issue = null): bool
    {
        if ($issue?->exists && $issue->getRawOriginal('division_id') === null
            && (string) $issue->getRawOriginal('department_id') === (string) $departmentId) {
            return false;
        }
        return filter_var($departmentId, FILTER_VALIDATE_INT) !== false
            && self::divisions(Division::query(), $departmentId)->exists();
    }

    public static function assertAvailable($departmentId, $divisionId): void
    {
        if ($departmentId && !self::departments(Department::query())->whereKey($departmentId)->exists()) {
            throw ValidationException::withMessages(['department_id' => 'Please choose an active department.']);
        }
        if ($divisionId && !self::divisions(Division::query(), $departmentId)->whereKey($divisionId)->exists()) {
            throw ValidationException::withMessages(['division_id' => 'Please choose an active division in an active department.']);
        }
    }
}
