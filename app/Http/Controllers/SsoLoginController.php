<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SsoLoginController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('SSO handler hit', ['token_present' => $request->has('token')]);

        $token = $request->query('token');

        if (!$token) {
            abort(403, 'Missing SSO token.');
        }

        $encrypter = new Encrypter(base64_decode(str_replace('base64:', '', env('SSO_KEY'))), 'AES-256-CBC');

        try {
            $payload = json_decode($encrypter->decrypt($token), true);
        } catch (\Exception $e) {
            Log::warning('SSO token decrypt failed', ['error' => $e->getMessage()]);
            abort(403, 'Invalid or tampered SSO token.');
        }

        if (!isset($payload['exp']) || $payload['exp'] < now()->timestamp) {
            abort(403, 'SSO token expired. Please click the link from GEMS again.');
        }

        $nonceKey = 'sso_nonce_' . $payload['nonce'];
        if (Cache::has($nonceKey)) {
            abort(403, 'This SSO token has already been used.');
        }
        Cache::put($nonceKey, true, 120); // block replay for 2 minutes

        $user = User::where('gems_user_id', $payload['gems_user_id'])->first();

        if (!$user) {
            // No gems_user_id match — fall back to email, and link the GEMS id
            // to that existing account instead of inserting a duplicate.
            $user = User::where('email', $payload['email'])->first();

            if ($user) {
                $user->gems_user_id = $payload['gems_user_id'];
                $user->save();
            }
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;
            $user = User::create([
                'gems_user_id' => $payload['gems_user_id'],
                'name'         => $payload['name'],
                'email'        => $payload['email'],
                'emp_code'     => $payload['emp_code'],
                'dept_code'    => $payload['dept_code'],
                'password'     => bcrypt(\Illuminate\Support\Str::random(32)),
            ]);
        }

        if ($isNewUser) {
            $roleMap = [
                'admin'      => 'admin',
                'hr_partner' => 'hr_staff',
            ];
            $role = $roleMap[$payload['role']] ?? 'employee';
            $user->assignRole($role); // Backpack Permission Manager
        }

        auth()->guard('web')->login($user); // adjust guard name to match your Backpack config

        return redirect()->to(backpack_url('dashboard'));
    }
}
