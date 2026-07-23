<?php

namespace App\Http\Controllers\Admin;

use Backpack\PermissionManager\app\Http\Controllers\PermissionCrudController as VendorPermissionCrudController;

class PermissionCrudController extends VendorPermissionCrudController
{
    public function setup()
    {
        parent::setup();

        if (!backpack_user()->can('permission.view')) {
            abort(403);
        }

        $this->crud->denyAccess(['create', 'update', 'delete']);

        if (backpack_user()->can('permission.create')) {
            $this->crud->allowAccess('create');
        }
        if (backpack_user()->can('permission.update')) {
            $this->crud->allowAccess('update');
        }
        if (backpack_user()->can('permission.delete')) {
            $this->crud->allowAccess('delete');
        }
    }
}
