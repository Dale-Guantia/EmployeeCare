<?php

namespace App\Http\Controllers\Admin\Auth;

use Backpack\CRUD\app\Http\Controllers\Auth\LoginController as BackpackLoginController;
use Illuminate\Http\Request;

class LoginController extends BackpackLoginController
{
    /**
     * Show login form.
     * If already logged in, redirect to EmployeeCare dashboard.
     */
    public function showLoginForm()
    {
        if (backpack_auth()->check()) {
            return redirect('/employeecare/employee-care/dashboard');
        }

        if (! app()->environment('local')) {
            return redirect('https://hrdo.gemspasig.ph/login');
        }

        return parent::showLoginForm();
    }

    /**
     * Override credentials method to allow
     * login via email OR username.
     */
    protected function credentials(Request $request)
    {
        $login = $request->input($this->username());

        return [
            filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username' => $login,
            'password' => $request->input('password'),
        ];
    }
}
