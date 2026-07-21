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
            // Removed latest() because notifications() already orders by created_at desc
            ->take(10)
            ->get()
            ->map(function ($notification) {
                $data = is_array($notification->data) ? $notification->data : [];

                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? 'Notification',
                    'message' => $data['message'] ?? '',
                    'url' => $data['url'] ?? '#',
                    'read_at' => $notification->read_at,
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
        // notifications() already orders by created_at desc (see feed() above) —
        // an extra ->latest() here would add a duplicate ORDER BY created_at,
        // which SQL Server rejects outright (MySQL tolerates it silently).
        $notifications = backpack_user()->notifications()->paginate(20);

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
