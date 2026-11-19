<?php

namespace App\Models\Concerns;

trait LocksOfficeReferences
{
    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            \App\Services\MasterDataLocks::acquire($this);
            if ($this->exists) {
                $dirty = $this->getDirty();
                $current = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey())->getAttributes();
                $this->setRawAttributes($current, true);
                $this->setRawAttributes(array_replace($current, $dirty));
            }
            return parent::save($options);
        });
    }
}
