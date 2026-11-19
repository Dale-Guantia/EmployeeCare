<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Services\OfficeRetirementRules;

trait OfficeArchivingOperation
{
    use MasterDataArchivingOperation;

    protected function setupOfficeArchivingButtons(): void
    {
        $this->crud->addButtonFromView('line', 'office_archive', 'office_archive', 'end');
        $this->crud->moveButton('office_archive', 'after', 'update');
    }

    protected function destroyOffice($id)
    {
        $this->crud->hasAccessOrFail('delete');
        if (OfficeRetirementRules::isProtected($this->crud->getEntry($id))) {
            return response()->json(['error' => ['This department is required by the survey and cannot be deleted.']]);
        }
        return $this->traitDestroy($id);
    }
}
