<?php

namespace App\Services;

use App\Models\{Department, Division, Issue, Priority, Status};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MasterDataRules
{
    public static function references(Model $record): array
    {
        $id = $record->getKey();
        $connection = $record->getConnection();
        $counts = [];
        $columns = match (true) {
            $record instanceof Department => ['divisions' => ['department_id'], 'issues' => ['department_id'],
                'users' => ['department_id'], 'tickets' => ['department_id'],
                'ticket_reassignment_requests' => ['from_department_id', 'to_department_id']],
            $record instanceof Division => ['issues' => ['division_id'], 'users' => ['division_id'],
                'tickets' => ['division_id'], 'ticket_reassignment_requests' => ['from_division_id', 'to_division_id']],
            $record instanceof Issue => ['tickets' => ['issue_id'], 'surveys' => ['issue_id']],
            $record instanceof Priority => ['issues' => ['priority_id'], 'tickets' => ['priority_id']],
            $record instanceof Status => ['tickets' => ['status_id']],
            default => throw new \LogicException('Unsupported master record.'),
        };
        foreach ($columns as $table => $fields) {
            $counts[$table] = $connection->table($table)->where(function ($query) use ($fields, $id) {
                foreach ($fields as $field) {
                    $query->orWhere($field, $id);
                }
            })->count();
        }
        return $counts;
    }

    public static function assertCanDelete(Model $record): void
    {
        if (OfficeRetirementRules::isProtected($record)) {
            self::refuse('This department is required by the survey and cannot be deleted.');
        }
        if ($record instanceof Status && self::isCoreStatus($record)) {
            self::refuse('Core workflow statuses cannot be deleted.');
        }
        $references = array_filter(self::references($record));
        if ($references) {
            self::refuse('Cannot delete: ' . self::describe($references) . ' still reference this record. Archive it to preserve history.');
        }
    }

    public static function assertSaving(Model $record): void
    {
        if ($record instanceof Department || $record instanceof Division || $record instanceof Status || $record instanceof Priority) {
            PermanentCodeRules::prepare($record);
        }
        [$field, $scope] = match (true) {
            $record instanceof Department => ['department_name', null],
            $record instanceof Division => ['division_name', 'department_id'],
            $record instanceof Issue => ['issue_description', 'division_id'],
            $record instanceof Priority => ['priority_name', null],
            $record instanceof Status => ['status_name', null],
        };
        $nameFields = array_filter([$field, $scope, $record instanceof Issue ? 'department_id' : null]);
        if (!$record->exists || $record->isDirty($nameFields)) {
            $name = $record->getAttribute($field);
            if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 255) {
                self::refuse('Enter a name or description of at most 255 characters.', $field);
            }
            $duplicates = $record->newQuery()->where($field, $name);
            if ($scope) {
                $duplicates->where($scope, $record->getAttribute($scope));
            }
            if ($record instanceof Issue) {
                $duplicates->where('department_id', $record->department_id);
            }
            if ($record->exists) {
                $duplicates->whereKeyNot($record->getKey());
            }
            if ($duplicates->exists()) {
                self::refuse('This name or description is already used in the selected scope.', $field);
            }
        }
        if ($record instanceof Department || $record instanceof Division) {
            if ($record->exists && $record->isDirty('is_active') && !$record->is_active) {
                OfficeRetirementRules::assertCanArchive($record);
            }
            if ($record instanceof Division && (!$record->exists || $record->isDirty('department_id')
                || ($record->isDirty('is_active') && $record->is_active))) {
                if (!$record->department_id) {
                    self::refuse('A division requires an active parent department.', 'department_id');
                }
                OfficeSelectionRules::assertAvailable($record->department_id, null);
            }
        }
        if ($record instanceof Division && $record->exists && $record->isDirty('department_id')
            && array_sum(self::references($record))) {
            self::refuse('Cannot change the department of a referenced division. Create a replacement division or use a separately reviewed transfer process.', 'department_id');
        }
        if ($record instanceof Issue) {
            if ($record->exists && $record->isDirty(['department_id', 'division_id']) && array_sum(self::references($record))) {
                self::refuse('Cannot change the office of a referenced issue. Create a replacement issue and archive the old one.', 'division_id');
            }
            if (!$record->exists || $record->isDirty(['department_id', 'division_id'])
                || ($record->isDirty('is_active') && $record->is_active)) {
                if (!$record->department_id) {
                    self::refuse('An issue requires an active department.', 'department_id');
                }
                if (!$record->division_id && OfficeSelectionRules::issueDivisionRequired($record->department_id, $record)) {
                    self::refuse('Choose a division in the selected department.', 'division_id');
                }
                OfficeSelectionRules::assertAvailable($record->department_id, $record->division_id);
            }
            $reactivating = $record->exists && $record->isDirty('is_active') && $record->is_active;
            if ($record->priority_id && (!$record->exists || $record->isDirty('priority_id') || $reactivating)) {
                if (!Priority::whereKey($record->priority_id)->where('is_active', true)->exists()) {
                    self::refuse('Choose an active default priority or explicitly clear the default before reactivating this issue.', 'priority_id');
                }
            }
        }
        if ($record instanceof Priority) {
            if ($record->exists && $record->isDirty('is_active') && !$record->is_active) {
                $defaults = Issue::where('priority_id', $record->id)->where('is_active', true)->count();
                if ($defaults) {
                    self::refuse("Cannot archive: {$defaults} active issue default(s) use this priority. Replace or explicitly clear those defaults first.");
                }
            }
            self::assertColor($record->priority_color, 'priority_color');
        }
        if ($record instanceof Status) {
            self::assertColor($record->status_color, 'status_color');
            if (!$record->is_active && self::isCoreStatus($record)) {
                self::refuse('Core workflow statuses cannot be archived.');
            }
            if ($record->is_active && !self::isCoreStatus($record)
                && (!$record->exists || $record->isDirty('is_active'))) {
                self::refuse('Custom statuses require implemented transitions, assignment rules, and reporting behavior. This application currently supports only its four core statuses.', 'status_name');
            }
            if ($record->exists && $record->isDirty('is_active') && !$record->is_active
                && $record->tickets()->whereNull('resolved_at')->exists()) {
                self::refuse('Cannot archive a status while open tickets use it. Move them through a valid workflow transition first.');
            }
        }
    }

    public static function isCoreStatus(Status $status): bool
    {
        return in_array($status->status_code, Status::CORE_CODES, true);
    }

    public static function reactivationSummary(Model $record): string
    {
        if ($record instanceof Department) {
            $divisions = Division::where('department_id', $record->id)->where('is_active', true)->get();
            $issues = Issue::where('department_id', $record->id)->whereIn('division_id', $divisions->pluck('id'))
                ->where('is_active', true)->pluck('issue_description');
            return 'Divisions becoming available: ' . ($divisions->pluck('division_name')->implode(', ') ?: 'none')
                . '. Issues becoming available: ' . ($issues->implode(', ') ?: 'none') . '.';
        }
        if ($record instanceof Division) {
            return 'Issues becoming available when the department is active: '
                . (Issue::where('division_id', $record->id)->where('is_active', true)->pluck('issue_description')->implode(', ') ?: 'none') . '.';
        }
        return '';
    }

    private static function assertColor($color, string $field): void
    {
        if (!is_string($color) || !preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $color)) {
            self::refuse('Enter a valid hexadecimal color, for example #42ffc6.', $field);
        }
    }

    private static function describe(array $counts): string
    {
        return implode(', ', array_map(fn ($table, $count) => $count . ' ' . str_replace('_', ' ', $table), array_keys($counts), $counts));
    }

    private static function refuse(string $message, string $field = 'is_active'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
