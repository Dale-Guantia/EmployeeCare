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
    }
}
