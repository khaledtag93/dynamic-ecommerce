<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerNotificationLiveActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_mark_owned_notification_read_with_live_response(): void
    {
        $user = User::factory()->create();
        $first = $this->notificationFor($user, 'First update', route('orders.index'));
        $this->notificationFor($user, 'Second update');

        $response = $this->actingAs($user)
            ->patchJson(route('notifications.read', $first));

        $response
            ->assertOk()
            ->assertJsonPath('notification_id', (string) $first->id)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('action_url', route('orders.index'))
            ->assertJsonPath('message', __('Notification marked as read.'));

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_customer_cannot_mark_another_customers_notification_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = $this->notificationFor($owner, 'Private update');

        $this->actingAs($other)
            ->patchJson(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_updates_only_signed_in_customer_and_returns_zero_count(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $first = $this->notificationFor($user, 'One');
        $second = $this->notificationFor($user, 'Two');
        $foreign = $this->notificationFor($other, 'Foreign');

        $this->actingAs($user)
            ->patchJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('message', __('All notifications marked as read.'));

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_notifications_page_keeps_progressive_enhancement_and_no_javascript_forms(): void
    {
        $user = User::factory()->create();
        $this->notificationFor($user, 'Open order', route('orders.index'));
        $this->notificationFor($user, 'Read me here');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('data-notification-read-all', false)
            ->assertSee('data-notification-read', false)
            ->assertSee('data-navigate-after-read="1"', false)
            ->assertSee('X-Notification-Live', false)
            ->assertSee('Mark all as read')
            ->assertSee('Mark as read')
            ->assertSee('View update');

        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('تم تعليم الإشعار كمقروء.', $arabic['Notification marked as read.'] ?? null);
        $this->assertSame('تم تعليم جميع الإشعارات كمقروءة.', $arabic['All notifications marked as read.'] ?? null);
    }

    private function notificationFor(User $user, string $title, ?string $actionUrl = null): DatabaseNotification
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => self::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(array_filter([
                'title' => $title,
                'body' => 'Notification body',
                'action_url' => $actionUrl,
            ], fn ($value) => $value !== null), JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DatabaseNotification::query()->findOrFail($id);
    }
}
