<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Commerce\PaymentService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService)
    {
    }

    public function index(Request $request)
    {
        $search = mb_substr(trim((string) $request->string('search')), 0, 100);
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%' . $escapedSearch . '%';

        $filters = [
            'search' => $search,
            'status' => (string) $request->string('status'),
            'method' => (string) $request->string('method'),
            'queue' => (string) $request->string('queue'),
            'per_page' => max(20, min(100, (int) $request->integer('per_page', 20))),
        ];

        $attentionFilter = function ($query): void {
            $query->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_FAILED])
                ->orWhere('meta->provider_reversal_evidence->canonical_refund_recorded', false);
        };

        $payments = Payment::query()
            ->with('order')
            ->when($filters['search'], function ($query) use ($like) {
                $query->where(function ($inner) use ($like) {
                    $inner->where('transaction_reference', 'like', $like)
                        ->orWhere('provider', 'like', $like)
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['method'], fn ($query, $method) => $query->where('method', $method))
            ->when($filters['queue'] === 'attention', fn ($query) => $query->where($attentionFilter))
            ->when($filters['queue'] === 'failed', fn ($query) => $query->where('status', Payment::STATUS_FAILED))
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $queueStats = [
            'failed' => Payment::where('status', Payment::STATUS_FAILED)->count(),
            'attention' => Payment::query()->where($attentionFilter)->count(),
        ];

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.payments._results', [
                'payments' => $payments,
                'filters' => $filters,
                'queueStats' => $queueStats,
            ]);
        }

        $currencyExpression = "UPPER(COALESCE(NULLIF(TRIM(payments.currency), ''), NULLIF(TRIM(orders.currency), ''), 'EGP'))";
        $capturedByCurrency = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereIn('payments.status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
            ->selectRaw($currencyExpression.' AS statement_currency')
            ->selectRaw('SUM(payments.amount) AS captured_amount')
            ->groupByRaw($currencyExpression)
            ->orderByRaw($currencyExpression)
            ->get()
            ->map(fn ($row): array => [
                'currency' => (string) $row->statement_currency,
                'amount' => (string) BigDecimal::of((string) $row->captured_amount)
                    ->toScale(2, RoundingMode::Unnecessary),
            ])
            ->values();

        $stats = $queueStats + [
            'total' => Payment::count(),
            'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
            'paid' => Payment::where('status', Payment::STATUS_PAID)->count(),
            'captured_by_currency' => $capturedByCurrency,
        ];

        return view('admin.payments.index', [
            'payments' => $payments,
            'stats' => $stats,
            'queueStats' => $queueStats,
            'filters' => $filters,
            'statusOptions' => Payment::statusOptions(),
            'methodOptions' => \App\Models\Order::paymentMethodOptions(),
        ]);
    }

    public function show(Payment $payment)
    {
        $payment->load('order.items', 'order.user');

        $allowedStatuses = $this->paymentService->allowedManualStatuses($payment);

        return view('admin.payments.show', [
            'payment' => $payment,
            'statusOptions' => array_intersect_key(Payment::statusOptions(), array_flip($allowedStatuses)),
            'paymentLocked' => in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_REFUNDED], true),
        ]);
    }

    public function updateStatus(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Payment::statusOptions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'provider_status' => ['nullable', 'string', 'max:255'],
            'bank_transfer_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['bank_transfer_reference'] = filled($validated['bank_transfer_reference'] ?? null)
            ? trim((string) $validated['bank_transfer_reference'])
            : null;

        if (
            $payment->method === \App\Models\Order::PAYMENT_METHOD_BANK_TRANSFER
            && $validated['status'] === Payment::STATUS_PAID
            && blank($validated['bank_transfer_reference'])
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bank_transfer_reference' => __('Bank transfer reference is required before marking this payment as paid.'),
            ]);
        }

        $context = $validated + [
            'updated_by' => optional($request->user())->id,
        ];

        $this->paymentService->updateStatus($payment, $validated['status'], $context);

        return back()->with('success', __('Payment status updated successfully.'));
    }
}
