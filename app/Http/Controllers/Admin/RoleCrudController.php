<?php

namespace App\Http\Controllers\Admin;

use Backpack\PermissionManager\app\Http\Controllers\RoleCrudController as VendorRoleCrudController;

class RoleCrudController extends VendorRoleCrudController
{
    public function setup()
    {
        parent::setup();

        if (!backpack_user()->can('role.view')) {
            abort(403);
        }

        // $this->crud->denyAccess(['create','update','delete']);

        // if (backpack_user()->can('role.create')) {
        //     $this->crud->allowAccess('create');
        // }

        // if (backpack_user()->can('role.update')) {
        //     $this->crud->allowAccess('update');
        // }

        // if (backpack_user()->can('role.delete')) {
        //     $this->crud->allowAccess('delete');
        // }
    }
}
