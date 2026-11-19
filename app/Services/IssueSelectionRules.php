<?php

namespace App\Services;

use Illuminate\Validation\Rule;
use App\Models\Department;
use App\Models\Division;

class IssueSelectionRules
{
    public static function options($query, $currentIssueId = null)
    {
        return $query->where(function ($query) use ($currentIssueId) {
            $query->where(function ($active) {
                $active->where('is_active', true)
                    ->whereIn('department_id', Department::where('is_active', true)->select('id'))
                    ->where(function ($divisions) {
                        $divisions->whereNull('division_id')
                            ->orWhereIn('division_id', OfficeSelectionRules::divisions(Division::query())->select('id'));
                    });
            });
            if ($currentIssueId !== null) {
                $query->orWhere('id', $currentIssueId);
            }
        });
    }

    public static function validation($currentIssueId = null): array
    {
        return [
            'required_if:is_custom_issue,0',
            'nullable',
            'integer',
            Rule::exists('issues', 'id')->where(fn ($query) => self::options($query, $currentIssueId)),
        ];
    }
}
