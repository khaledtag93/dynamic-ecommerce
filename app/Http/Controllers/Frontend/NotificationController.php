<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
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

    public function markRead(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->notifiable_id === (int) auth()->id(), 403);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return redirect($notification->data['action_url'] ?? route('notifications.index'));
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('success', __('All notifications marked as read.'));
    }
}
