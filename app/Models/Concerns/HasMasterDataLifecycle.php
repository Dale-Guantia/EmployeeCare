<?php

namespace App\Models\Concerns;

use App\Services\MasterDataLocks;
use App\Services\MasterDataRules;

trait HasMasterDataLifecycle
{
    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            MasterDataLocks::acquire($this);
            $before = $this->exists ? $this->newQuery()->findOrFail($this->getKey())->getAttributes() : [];
            if ($this->exists) {
                // Rebase dirty fields on the locked row so a stale form/model cannot bypass a guard.
                $dirty = $this->getDirty();
                $this->setRawAttributes($before, true);
                $this->setRawAttributes(array_replace($before, $dirty));
            }
            $route = request()->route();
            if ($this->exists && $this->isDirty('is_active') && $route
                && $route->getAction('operation') === 'update'
                && $route->getControllerClass() === 'App\\Http\\Controllers\\Admin\\' . class_basename($this) . 'CrudController') {
                $entity = match ($this->getTable()) {
                    'departments' => 'department', 'divisions' => 'division', 'issues' => 'issue',
                    'priorities' => 'priority', 'statuses' => 'status',
                };
                // Recheck after locking, even if the state changed since FormRequest authorization.
                abort_unless(backpack_user()?->can($entity . ($this->is_active ? '.reactivate' : '.archive')), 403);
            }
            MasterDataRules::assertSaving($this);
            $saved = parent::save($options);
            if ($saved) {
                if (method_exists($this, 'forgetCodeCache')) {
                    $this::forgetCodeCache();
                }
                $this->auditMasterData($before, $this->getAttributes());
            }
            return $saved;
        });
    }

    public function delete()
    {
        if (!$this->exists) {
            return parent::delete();
        }
        return $this->getConnection()->transaction(function () {
            MasterDataLocks::acquire($this);
            $record = $this->newQuery()->findOrFail($this->getKey());
            MasterDataRules::assertCanDelete($record);
            $before = $record->getAttributes();
            $deleted = parent::delete();
            if ($deleted) {
                if (method_exists($this, 'forgetCodeCache')) {
                    $this::forgetCodeCache();
                }
                $this->auditMasterData($before, []);
            }
            return $deleted;
        });
    }

    private function auditMasterData(array $before, array $after): void
    {
        $fields = array_flip($this->getFillable());
        $before = array_intersect_key($before, $fields);
        $after = array_intersect_key($after, $fields);
        if ($before === $after) {
            return;
        }
        $action = !$before ? 'create' : (!$after ? 'delete' : 'update');
        if ($before && $after && (bool) ($before['is_active'] ?? true) !== (bool) ($after['is_active'] ?? true)) {
            $action = $after['is_active'] ? 'reactivate' : 'archive';
        }
        $reason = request()->input('reason');
        $this->getConnection()->table('master_data_audits')->insert([
            'record_type' => $this->getTable(), 'record_id' => $this->getKey(),
            'actor_id' => backpack_auth()->id(), 'action' => $action,
            'reason' => is_string($reason) && trim($reason) !== '' ? mb_substr(trim($reason), 0, 1000) : 'Master record ' . $action,
            'before_values' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_values' => $after ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(),
        ]);
    }
}
