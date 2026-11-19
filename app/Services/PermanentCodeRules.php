<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PermanentCodeRules
{
    public static function prepare(Model $record): void
    {
        $column = $record::CODE_COLUMN;
        if (!$record->exists && ($record->getAttribute($column) === null || $record->getAttribute($column) === '')) {
            // Existing programmatic callers may omit a code. Assign it once, independently of the label.
            $record->setAttribute($column, Str::singular($record->getTable()) . '_' . strtolower((string) Str::ulid()));
        }
        if ($record->exists && $record->isDirty($column)) {
            throw ValidationException::withMessages([$column => 'The permanent code cannot be changed. You may edit the display name.']);
        }
        if (!$record->exists) {
            validator([$column => $record->getAttribute($column)], self::validation($record::class))->validate();
        }
    }

    public static function validation(string $model, $id = null): array
    {
        $record = $id === null ? null : $model::findOrFail($id);
        $column = $model::CODE_COLUMN;
        return [$column => array_merge(
            ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_-]*$/'],
            $record ? [Rule::in([$record->getAttribute($column)])]
                : [Rule::unique((new $model)->getTable(), $column)]
        )];
    }
}
