<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\MyAccountController as BackpackMyAccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MyAccountController extends BackpackMyAccountController
{
    public function getAccountInfoForm()
    {
        $this->data['user'] = backpack_user();

        return view('vendor.backpack.base.my_account', $this->data);
    }

    public function postAccountInfoForm(Request $request)
    {
        $user = backpack_user();

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            backpack_authentication_column() => [
                'required',
                backpack_authentication_column() == backpack_email_column() ? 'email' : 'string',
                'max:255',
                Rule::unique('users', backpack_authentication_column())->ignore($user->id),
            ],
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',

            // 1. ADD VALIDATION FOR SKILLS
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:100',
        ]);

        $user->name = $request->input('name');
        $user->username = $request->input('username');
        $user->{backpack_authentication_column()} = $request->input(backpack_authentication_column());

        // 2. ASSIGN SKILLS TO THE USER MODEL
        // If the array is empty (user deleted all tags), it defaults to an empty array []
        $user->skills = $request->input('skills', []);

        $avatarError = null;

        try {
            if ($request->boolean('remove_avatar')) {
                if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                    Storage::disk('public')->delete($user->avatar_url);
                }

                $user->avatar_url = null;
            }

            if ($request->hasFile('avatar')) {
                if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                    Storage::disk('public')->delete($user->avatar_url);
                }

                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_url = $path;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[MyAccountController] Avatar update failed for user ' . $user->id . ': ' . $e->getMessage());
            $avatarError = 'Your other account details were saved, but the avatar could not be updated. Please try again.';
        }

        $user->save();

        if ($avatarError) {
            return redirect()->back()->with('error', $avatarError);
        }

        return redirect()->back()->with('success', trans('backpack::base.account_updated'));
    }

    public function postChangePasswordForm(Request $request)
    {
        return parent::postChangePasswordForm($request);
    }

    public function postNotificationsForm(Request $request)
    {
        $user = backpack_user();

        $user->notify_ticket_created = $request->has('notify_ticket_created');
        $user->notify_ticket_assigned = $request->has('notify_ticket_assigned');
        $user->notify_ticket_status_changed = $request->has('notify_ticket_status_changed');
        $user->notify_ticket_commented = $request->has('notify_ticket_commented');
        $user->save();

        return redirect()->back()->with('success', 'Notification settings updated successfully.');
    }
}
