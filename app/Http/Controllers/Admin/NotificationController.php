<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\TicketComment;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class NotificationController extends Controller
{
    public function feed()
    {
        $user = backpack_user();

        $notifications = $user->notifications()
            // Removed latest() because notifications() already orders by created_at desc
            ->take(10)
            ->get();

        $legacyActors = $this->legacyCommentActors($notifications);
        $actors = User::whereIn('id', $notifications->map(fn ($notification) => $notification->data['actor_id'] ?? $legacyActors->get($notification->id))
            ->filter()->unique())->get(['id', 'name', 'avatar_url'])->mapWithKeys(function ($actor) {
                return [$actor->id => [
                    'name' => $actor->name,
                    'avatar_url' => $actor->avatar_url && Storage::disk('public')->exists($actor->avatar_url)
                        ? asset('storage/' . $actor->avatar_url) : null,
                ]];
            });

        $notifications = $notifications->map(function ($notification) use ($actors, $legacyActors) {
            $data = is_array($notification->data) ? $notification->data : [];
            $actorId = $data['actor_id'] ?? $legacyActors->get($notification->id);

            return [
                'id' => $notification->id,
                'title' => $data['title'] ?? 'Notification',
                'message' => $data['message'] ?? '',
                'url' => $data['url'] ?? '#',
                'type' => $data['type'] ?? null,
                'status_code' => $data['status_code'] ?? null,
                'actor' => [
                    'name' => $actorId ? ($actors->get($actorId)['name'] ?? 'Former user') : 'Employee Care',
                    'avatar_url' => $actorId ? ($actors->get($actorId)['avatar_url'] ?? null)
                        : asset('assets/icons/icon-192x192.png'),
                    'is_system' => !$actorId,
                ],
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

    private function legacyCommentActors(Collection $notifications): Collection
    {
        $legacy = $notifications->filter(function ($notification) {
            $data = $notification->data;
            return ($data['type'] ?? null) === 'ticket_commented'
                && !array_key_exists('actor_id', $data) && !empty($data['ticket_id'])
                && $notification->created_at;
        });
        if ($legacy->isEmpty()) return collect();

        // Old payloads omitted the sender. Recover only from a matching recorded comment,
        // never from the person's name alone, and leave stored notifications unchanged.
        $comments = TicketComment::query()
            ->join('users', 'users.id', '=', 'ticket_comments.user_id')
            ->join('tickets', 'tickets.id', '=', 'ticket_comments.ticket_id')
            ->whereIn('ticket_comments.ticket_id', $legacy->map(fn ($notification) => $notification->data['ticket_id'])->unique())
            ->whereBetween('ticket_comments.created_at', [
                $legacy->min('created_at')->copy()->subMinute(), $legacy->max('created_at'),
            ])->get(['ticket_comments.id', 'ticket_comments.ticket_id', 'ticket_comments.user_id',
                'ticket_comments.created_at', 'users.name as actor_name', 'tickets.reference_id']);

        return $legacy->mapWithKeys(function ($notification) use ($comments) {
            $data = $notification->data;
            $matches = $comments->filter(fn ($comment) =>
                (string) $comment->ticket_id === (string) $data['ticket_id']
                && $comment->created_at->betweenIncluded($notification->created_at->copy()->subMinute(), $notification->created_at)
                && ($data['message'] ?? '') === "{$comment->actor_name} commented on ticket {$comment->reference_id}.")
                ->pluck('user_id')->unique();

            return $matches->count() === 1 ? [$notification->id => $matches->first()] : [];
        });
    }

    public function markAsRead($id)
    {
        $notification = backpack_user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        backpack_user()->unreadNotifications()->update(['read_at' => now()]);

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
