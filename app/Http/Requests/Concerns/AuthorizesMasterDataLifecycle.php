<?php

namespace App\Http\Requests\Concerns;

trait AuthorizesMasterDataLifecycle
{
    private function authorizeMasterData(string $entity, string $model): bool
    {
        if (!backpack_auth()->check()) {
            return false;
        }
        $record = ($this->isMethod('PUT') || $this->isMethod('PATCH')) ? $model::find($this->route('id')) : null;
        if ($this->has('is_active') && in_array($this->input('is_active'), [true, false, 0, 1, '0', '1'], true)) {
            $active = $this->boolean('is_active');
            if (($record && (bool) $record->is_active !== $active) || (!$record && !$active)) {
                return backpack_user()->can($entity . ($active ? '.reactivate' : '.archive'));
            }
        }
        return true;
    }
}
