<?php

namespace Tests\Feature;

use App\Models\ImportJob;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveListClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_pagination_returns_the_same_page_for_full_and_live_requests(): void
    {
        $admin = $this->createSuperAdmin();

        foreach (range(1, 21) as $number) {
            ImportJob::create([
                'type' => 'products',
                'file_name' => 'IMPORT-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'.csv',
                'status' => 'draft',
            ]);
        }

        $url = route('admin.imports.index', ['page' => 2]);

        $this->actingAs($admin)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('IMPORT-01.csv')
            ->assertDontSee('IMPORT-21.csv');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('IMPORT-01.csv')
            ->assertDontSee('IMPORT-21.csv')
            ->assertDontSee('<html', false);
    }

    public function test_customer_order_pagination_stays_scoped_to_the_signed_in_customer(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();

        foreach (range(1, 11) as $number) {
            $this->orderFor($customer, 'ACCOUNT-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT));
        }
        $this->orderFor($other, 'OTHER-CUSTOMER-ORDER');

        $url = route('orders.index', ['page' => 2]);

        $this->actingAs($customer)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('ACCOUNT-01')
            ->assertDontSee('ACCOUNT-11')
            ->assertDontSee('OTHER-CUSTOMER-ORDER');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('ACCOUNT-01')
            ->assertDontSee('OTHER-CUSTOMER-ORDER')
            ->assertDontSee('<html', false);
    }

    public function test_customer_notification_pagination_stays_scoped_to_the_signed_in_customer(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();

        foreach (range(1, 16) as $number) {
            $this->notificationFor($customer, 'NOTICE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), $number);
        }
        $this->notificationFor($other, 'OTHER-CUSTOMER-NOTICE', 100);

        $url = route('notifications.index', ['page' => 2]);

        $this->actingAs($customer)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('NOTICE-16')
            ->assertDontSee('OTHER-CUSTOMER-NOTICE');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('NOTICE-16')
            ->assertDontSee('OTHER-CUSTOMER-NOTICE')
            ->assertDontSee('<html', false);
    }

    private function orderFor(User $user, string $number): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => $number,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Account Street',
            'shipping_city' => 'Cairo',
            'grand_total' => 100,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    private function notificationFor(User $user, string $title, int $minutesAgo): void
    {
        $timestamp = now()->subMinutes($minutesAgo);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\Feature\\LiveListClosureNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => $title, 'body' => 'Notification body'], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}
