<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function dashboard()
    {
        // If the user is an employee, redirect them straight to the tickets list
        if (backpack_user()->hasRole('employee')) {
            return redirect(backpack_url('ticket'));
        }

        // Otherwise, load the normal Backpack dashboard for Admins and Heads
        $this->data['title'] = trans('backpack::base.dashboard'); // set the page title
        $this->data['breadcrumbs'] = [
            trans('backpack::crud.admin')     => backpack_url('dashboard'),
            trans('backpack::base.dashboard') => false,
        ];

        return view(backpack_view('dashboard'), $this->data);
    }
}
