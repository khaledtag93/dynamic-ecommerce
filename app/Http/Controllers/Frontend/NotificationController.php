<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(15)->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('frontend.notifications._results', compact('notifications'));
        }

        $unreadCount = auth()->user()->unreadNotifications()->count();

        return view('frontend.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(Request $request, DatabaseNotification $notification)
    {
        abort_unless((int) $notification->notifiable_id === (int) auth()->id(), 403);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $actionUrl = $notification->data['action_url'] ?? null;

        if ($request->expectsJson() || $request->header('X-Notification-Live') === '1') {
            return response()->json([
                'message' => __('Notification marked as read.'),
                'notification_id' => (string) $notification->id,
                'unread_count' => (int) $request->user()->unreadNotifications()->count(),
                'action_url' => $actionUrl,
            ]);
        }

        return redirect($actionUrl ?: route('notifications.index'));
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson() || $request->header('X-Notification-Live') === '1') {
            return response()->json([
                'message' => __('All notifications marked as read.'),
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', __('All notifications marked as read.'));
    }
}
