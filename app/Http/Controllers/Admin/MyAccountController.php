<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\MyAccountController as BackpackMyAccountController;
use Illuminate\Http\Request;

class MyAccountController extends BackpackMyAccountController
{
    public function updateNotificationSettings(Request $request)
    {
        $user = backpack_user();

        $user->update([
            'notify_ticket_created' => $request->has('notify_ticket_created'),
            'notify_ticket_assigned' => $request->has('notify_ticket_assigned'),
            'notify_ticket_status_changed' => $request->has('notify_ticket_status_changed'),
            'notify_ticket_commented' => $request->has('notify_ticket_commented'),
        ]);

        return redirect()->back()->with('success', 'Notification settings updated successfully.');
    }
}
