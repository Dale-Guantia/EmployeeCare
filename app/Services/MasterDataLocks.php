<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class MasterDataLocks
{
    // All writers take parent locks in the same order before eligibility checks.
    public static function acquire(Model $model): void
    {
        $ids = [];
        foreach (['departments' => 'department_id', 'divisions' => 'division_id', 'issues' => 'issue_id',
            'priorities' => 'priority_id', 'statuses' => 'status_id'] as $table => $field) {
            $ids[$table] = [$model->getAttribute($field), $model->getRawOriginal($field)];
            if ($model->getTable() === $table && $model->exists) {
                $ids[$table][] = $model->getKey();
            }
        }
        foreach (['from_', 'to_'] as $prefix) {
            $ids['departments'][] = $model->getAttribute($prefix . 'department_id');
            $ids['divisions'][] = $model->getAttribute($prefix . 'division_id');
        }
        // A newly chosen issue supplies the initial handling office and default priority.
        if (in_array($model->getTable(), ['tickets', 'surveys'], true) && $model->issue_id
            && (!$model->exists || $model->isDirty('issue_id'))) {
            $issue = $model->getConnection()->table('issues')->find($model->issue_id);
            if ($issue) {
                $ids['departments'][] = $issue->department_id;
                $ids['divisions'][] = $issue->division_id;
                $ids['priorities'][] = $issue->priority_id;
            }
        }
        foreach ($ids as $table => $values) {
            $values = array_unique(array_filter($values));
            sort($values, SORT_NUMERIC);
            foreach ($values as $id) {
                $model->getConnection()->table($table)->where('id', $id)->lockForUpdate()->first();
            }
        }
    }
}
