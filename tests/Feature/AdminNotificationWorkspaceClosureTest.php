<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminNotificationWorkspaceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bulk_read_updates_only_current_users_unread_notifications(): void
    {
        $admin = $this->createSuperAdmin();
        $other = User::factory()->create();

        $ownNotificationId = (string) Str::uuid();
        $otherNotificationId = (string) Str::uuid();

        DB::table('notifications')->insert([
            [
                'id' => $ownNotificationId,
                'type' => 'Tests\\Fixtures\\AdminNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $admin->id,
                'data' => json_encode(['title' => 'Own unread'], JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $otherNotificationId,
                'type' => 'Tests\\Fixtures\\AdminNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $other->id,
                'data' => json_encode(['title' => 'Other unread'], JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.notifications.read-all'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull(
            DB::table('notifications')->where('id', $ownNotificationId)->value('read_at')
        );

        $this->assertNull(
            DB::table('notifications')->where('id', $otherNotificationId)->value('read_at')
        );
    }

    public function test_notification_center_whatsapp_retry_only_reports_sent_logs_as_success(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NotificationCenterController.php'));

        $this->assertStringContainsString('$retrySucceeded = $retried?->status === WhatsAppLog::STATUS_SENT;', $controller);
        $this->assertStringContainsString("\$retrySucceeded ? 'whatsapp_log_retried' : 'whatsapp_log_retry_failed'", $controller);
        $this->assertStringContainsString("\$retried?->error_message ?: __('WhatsApp retry could not be completed safely.')", $controller);
        $this->assertStringNotContainsString("return back()->with(\$retried ? 'success' : 'error'", $controller);
    }

    public function test_admin_bulk_read_uses_query_level_update(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NotificationController.php'));

        $this->assertStringContainsString(
            "unreadNotifications()->update([",
            $controller
        );
        $this->assertStringNotContainsString(
            "unreadNotifications->markAsRead()",
            $controller
        );
    }
}
