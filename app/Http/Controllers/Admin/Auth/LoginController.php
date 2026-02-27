<?php

namespace App\Http\Controllers\Admin\Auth;

use Backpack\CRUD\app\Http\Controllers\Auth\LoginController as BackpackLoginController;
use Illuminate\Http\Request;

class LoginController extends BackpackLoginController
{
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
