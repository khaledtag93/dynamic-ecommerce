<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationController extends Controller
{
    public function index()
    {
        $loadError = null;
        $notifications = [];

        try {
            $items = auth()->user()
                ->notifications()
                ->latest()
                ->limit(100)
                ->get();

            foreach ($items as $notification) {
                if (! $notification instanceof DatabaseNotification) {
                    continue;
                }

                try {
                    $payload = is_array($notification->data) ? $notification->data : [];

                    $title = trim((string) Arr::get($payload, 'title', ''));
                    $body = trim((string) Arr::get($payload, 'body', ''));
                    $type = trim((string) Arr::get($payload, 'type', ''));
                    $channel = trim((string) Arr::get($payload, 'channel', ''));
                    $icon = trim((string) Arr::get($payload, 'icon', 'mdi-bell-outline'));
                    $actionUrl = Arr::get($payload, 'action_url');
                    $actionUrl = is_string($actionUrl) && $actionUrl !== '' ? $actionUrl : null;

                    $notifications[] = [
                        'id' => (string) $notification->getKey(),
                        'title' => $title !== '' ? $title : __('Notification'),
                        'body' => $body !== '' ? $body : __('No extra details were attached to this update.'),
                        'type' => $type !== '' ? $type : __('General'),
                        'channel' => $channel !== '' ? $channel : __('System'),
                        'icon' => $icon !== '' ? $icon : 'mdi-bell-outline',
                        'action_url' => $actionUrl,
                        'is_read' => ! is_null($notification->read_at),
                        'created_at_human' => optional($notification->created_at)->diffForHumans() ?: __('Just now'),
                    ];
                } catch (Throwable $notificationError) {
                    Log::warning('Skipping unreadable admin notification row.', [
                        'user_id' => auth()->id(),
                        'notification_id' => $notification->getKey(),
                        'message' => $notificationError->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            $loadError = __('Notifications are temporarily unavailable.');

            Log::error('Admin notifications inbox failed to load.', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);
        }

        $notifications = collect($notifications);
        $unreadCount = $notifications->where('is_read', false)->count();
        $actionableCount = $notifications->filter(fn (array $notification) => ! empty($notification['action_url']))->count();
        $latestNotification = $notifications->first();

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'actionableCount' => $actionableCount,
            'readCount' => max($notifications->count() - $unreadCount, 0),
            'latestNotification' => $latestNotification,
            'loadError' => $loadError,
        ]);
    }

    public function markRead(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->notifiable_id === (int) auth()->id(), 403);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('success', __('All notifications marked as read.'));
    }
}
