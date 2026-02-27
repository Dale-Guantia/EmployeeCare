<?php

namespace App\Http\Controllers\Admin\Auth;

use Backpack\CRUD\app\Http\Controllers\Auth\RegisterController as BackpackRegisterController;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class RegisterController extends BackpackRegisterController
{
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name'     => 'required|max:255',
            'username' => 'required|max:255|unique:users', // Matches your new HTML input name
            'email'    => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name'      => $data['name'],
            'username'  => $data['username'], // Saves the username to DB
            'email'     => $data['email'],
            'password'  => bcrypt($data['password']),
            'is_active' => 1,
        ]);
    }
}
