<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function feed()
    {
        $user = backpack_user();

        $notifications = $user->notifications()
            ->latest() // Keep this so new notifications appear at the top!
            ->take(10)
            ->get()
            ->map(function ($notification) {
                // 1. Safely handle the data array (in case it's somehow null or malformed)
                $data = is_array($notification->data) ? $notification->data : [];

                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? 'Notification',
                    'message' => $data['message'] ?? '',
                    'url' => $data['url'] ?? '#',
                    'read_at' => $notification->read_at,

                    // 2. THE FIX: Safely check if created_at exists AND is a Carbon object
                    // before calling diffForHumans(). If it's missing, default to 'Just now'.
                    'created_at_human' => $notification->created_at
                        ? $notification->created_at->diffForHumans()
                        : 'Just now',
                ];
            });

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead($id)
    {
        $notification = backpack_user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        backpack_user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    public function clearAll()
    {
        backpack_user()->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications cleared.'
        ]);
    }

    public function index()
    {
        $notifications = backpack_user()->notifications()->latest()->paginate(20);

        return view('admin.notifications.index', [
            'title' => 'Notifications',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Notifications' => false,
            ],
            'notifications' => $notifications,
        ]);
    }
}
