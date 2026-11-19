<?php

namespace App\Services;

use Illuminate\Validation\Rule;

class PrioritySelectionRules
{
    public static function options($query, $currentPriorityId = null)
    {
        return $query->where(function ($query) use ($currentPriorityId) {
            $query->where('is_active', true);
            if ($currentPriorityId !== null) {
                $query->orWhere('id', $currentPriorityId);
            }
        });
    }

    public static function validation($currentPriorityId = null): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('priorities', 'id')->where(fn ($query) => self::options($query, $currentPriorityId)),
        ];
    }
}
