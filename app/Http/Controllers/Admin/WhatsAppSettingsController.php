<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WhatsAppLog;
use App\Services\Commerce\NotificationActionSafetyService;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppSettingsController extends Controller
{
    public function __construct(
        protected StoreSettingsService $settingsService,
        protected WhatsAppServiceInterface $whatsAppService,
        protected NotificationActionSafetyService $notificationActionSafetyService,
    ) {
    }

    public function edit(Request $request): View
    {
        $storeSettings = $this->settingsService->all();

        $logs = WhatsAppLog::query()
            ->with('order:id,order_number,customer_name,customer_phone,grand_total,currency,status,delivery_status')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('message_type'), fn ($query) => $query->where('message_type', $request->string('message_type')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim((string) $request->string('search'));

                $query->where(function ($inner) use ($term) {
                    $inner->where('phone', 'like', "%{$term}%")
                        ->orWhere('normalized_phone', 'like', "%{$term}%")
                        ->orWhere('template_name', 'like', "%{$term}%")
                        ->orWhere('provider_message_id', 'like', "%{$term}%")
                        ->orWhereHas('order', function ($orderQuery) use ($term) {
                            $orderQuery->where('order_number', 'like', "%{$term}%")
                                ->orWhere('customer_name', 'like', "%{$term}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'total' => WhatsAppLog::query()->count(),
            'sent' => WhatsAppLog::query()->where('status', WhatsAppLog::STATUS_SENT)->count(),
            'failed' => WhatsAppLog::query()->where('status', WhatsAppLog::STATUS_FAILED)->count(),
            'skipped' => WhatsAppLog::query()->where('status', WhatsAppLog::STATUS_SKIPPED)->count(),
            'pending' => WhatsAppLog::query()->where('status', WhatsAppLog::STATUS_PENDING)->count(),
        ];

        $messageTypeSummary = [
            'order_confirmation' => WhatsAppLog::query()->where('message_type', 'order_confirmation')->count(),
            'order_status_update' => WhatsAppLog::query()->where('message_type', 'order_status_update')->count(),
            'delivery_update' => WhatsAppLog::query()->where('message_type', 'delivery_update')->count(),
        ];

        $recentOrders = Order::query()
            ->select('id', 'order_number', 'customer_name', 'status', 'delivery_status')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.settings.whatsapp', compact('storeSettings', 'logs', 'summary', 'messageTypeSummary', 'recentOrders'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_default_provider' => ['required', 'string', 'max:50'],
            'whatsapp_feature_order_confirmation' => ['nullable', 'boolean'],
            'whatsapp_feature_order_status_update' => ['nullable', 'boolean'],
            'whatsapp_feature_delivery_update' => ['nullable', 'boolean'],
            'whatsapp_fallback_locale' => ['required', 'in:ar,en'],
            'whatsapp_queue_enabled' => ['nullable', 'boolean'],
            'whatsapp_queue_connection' => ['nullable', 'string', 'max:100'],
            'whatsapp_queue_queue' => ['nullable', 'string', 'max:100'],
            'whatsapp_queue_tries' => ['nullable', 'integer', 'min:1', 'max:20'],
            'whatsapp_queue_backoff_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'whatsapp_queue_timeout' => ['nullable', 'integer', 'min:10', 'max:600'],
            'whatsapp_duplicate_window_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],

            'whatsapp_meta_base_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_meta_graph_version' => ['required', 'string', 'max:30'],
            'whatsapp_meta_phone_number_id' => ['nullable', 'string', 'max:100'],
            'whatsapp_meta_business_account_id' => ['nullable', 'string', 'max:100'],
            'whatsapp_meta_access_token' => ['nullable', 'string', 'max:5000'],
            'whatsapp_meta_verify_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_meta_app_secret' => ['nullable', 'string', 'max:255'],
            'whatsapp_meta_timeout' => ['nullable', 'integer', 'min:5', 'max:120'],

            'whatsapp_template_order_confirmation_name_ar' => ['nullable', 'string', 'max:191'],
            'whatsapp_template_order_confirmation_name_en' => ['nullable', 'string', 'max:191'],
            'whatsapp_template_order_confirmation_language_ar' => ['required', 'string', 'max:20'],
            'whatsapp_template_order_confirmation_language_en' => ['required', 'string', 'max:20'],

            'whatsapp_template_order_status_update_name_ar' => ['nullable', 'string', 'max:191'],
            'whatsapp_template_order_status_update_name_en' => ['nullable', 'string', 'max:191'],
            'whatsapp_template_order_status_update_language_ar' => ['required', 'string', 'max:20'],
            'whatsapp_template_order_status_update_language_en' => ['required', 'string', 'max:20'],

            'whatsapp_template_delivery_update_name_ar' => ['nullable', 'string', 'max:191'],
            'whatsapp_template_delivery_update_name_en' => ['nullable', 'string', 'max:191'],
            'whatsapp_template_delivery_update_language_ar' => ['required', 'string', 'max:20'],
            'whatsapp_template_delivery_update_language_en' => ['required', 'string', 'max:20'],
        ]);

        foreach ([
            'whatsapp_enabled',
            'whatsapp_feature_order_confirmation',
            'whatsapp_feature_order_status_update',
            'whatsapp_feature_delivery_update',
            'whatsapp_queue_enabled',
        ] as $booleanField) {
            $data[$booleanField] = $request->boolean($booleanField) ? '1' : '0';
        }

        $data['whatsapp_meta_base_url'] = $data['whatsapp_meta_base_url'] ?: 'https://graph.facebook.com';
        $data['whatsapp_meta_timeout'] = (string) ($data['whatsapp_meta_timeout'] ?? 20);
        $data['whatsapp_queue_tries'] = (string) ($data['whatsapp_queue_tries'] ?? 3);
        $data['whatsapp_queue_backoff_seconds'] = (string) ($data['whatsapp_queue_backoff_seconds'] ?? 30);
        $data['whatsapp_queue_timeout'] = (string) ($data['whatsapp_queue_timeout'] ?? 120);
        $data['whatsapp_duplicate_window_minutes'] = (string) ($data['whatsapp_duplicate_window_minutes'] ?? 30);

        $this->settingsService->save($data);

        return back()->with('success', __('WhatsApp settings were saved successfully.'));
    }

    public function retry(WhatsAppLog $log): RedirectResponse
    {
        $inspection = $this->notificationActionSafetyService->inspectWhatsAppRetry($log);

        if (! ($inspection['allowed'] ?? false)) {
            return back()->with('error', $inspection['message'] ?? __('This WhatsApp retry was blocked by a safety guard.'));
        }

        $retried = $this->whatsAppService->retry($log);

        return back()->with($retried ? 'success' : 'error', $retried
            ? __('The WhatsApp message retry was queued or executed safely.')
            : __('The WhatsApp retry could not be completed safely.'));
    }


    public function testSend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'locale' => ['required', 'in:ar,en'],
            'message_type' => ['required', 'in:order_confirmation,order_status_update,delivery_update'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $order = isset($validated['order_id'])
            ? Order::query()->findOrFail($validated['order_id'])
            : Order::query()->latest('id')->first();

        if (! $order) {
            return back()->with('error', __('Create at least one order before running a WhatsApp test send.'));
        }

        $meta = (array) ($order->meta ?? []);
        $meta['locale'] = $validated['locale'];
        $order->customer_phone = $validated['phone'];
        $order->meta = $meta;

        match ($validated['message_type']) {
            'order_confirmation' => $this->whatsAppService->sendOrderConfirmation($order),
            'order_status_update' => $this->whatsAppService->sendOrderStatusUpdate($order),
            'delivery_update' => $this->whatsAppService->sendDeliveryUpdate($order),
        };

        return back()->with('success', __('WhatsApp test send was executed. Check the latest logs for the result.'));
    }

    public function resendOrderEvent(Order $order, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message_type' => ['required', 'in:order_confirmation,order_status_update,delivery_update'],
        ]);

        match ($validated['message_type']) {
            'order_confirmation' => $this->whatsAppService->sendOrderConfirmation($order),
            'order_status_update' => $this->whatsAppService->sendOrderStatusUpdate($order),
            'delivery_update' => $this->whatsAppService->sendDeliveryUpdate($order),
        };

        return back()->with('success', __('WhatsApp event was re-sent successfully.'));
    }

    public function sendOrderEvent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'message_type' => ['required', 'in:order_confirmation,order_status_update,delivery_update'],
        ]);

        $order = Order::query()->findOrFail($validated['order_id']);

        match ($validated['message_type']) {
            'order_confirmation' => $this->whatsAppService->sendOrderConfirmation($order),
            'order_status_update' => $this->whatsAppService->sendOrderStatusUpdate($order),
            'delivery_update' => $this->whatsAppService->sendDeliveryUpdate($order),
        };

        return back()->with('success', __('WhatsApp event was executed successfully. Check the latest logs for the result.'));
    }
}
