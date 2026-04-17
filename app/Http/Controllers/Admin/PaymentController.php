<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Commerce\PaymentService;
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
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),
            'method' => (string) $request->string('method'),
        ];

        $payments = Payment::query()
            ->with('order')
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('transaction_reference', 'like', "%{$search}%")
                        ->orWhere('provider', 'like', "%{$search}%")
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['method'], fn ($query, $method) => $query->where('method', $method))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Payment::count(),
            'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
            'paid' => Payment::where('status', Payment::STATUS_PAID)->count(),
            'failed' => Payment::where('status', Payment::STATUS_FAILED)->count(),
            'amount_total' => (float) Payment::sum('amount'),
        ];

        return view('admin.payments.index', [
            'payments' => $payments,
            'stats' => $stats,
            'filters' => $filters,
            'statusOptions' => Payment::statusOptions(),
            'methodOptions' => \App\Models\Order::paymentMethodOptions(),
        ]);
    }

    public function show(Payment $payment)
    {
        $payment->load('order.items', 'order.user');

        return view('admin.payments.show', [
            'payment' => $payment,
            'statusOptions' => Payment::statusOptions(),
        ]);
    }

    public function updateStatus(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Payment::statusOptions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'provider_status' => ['nullable', 'string', 'max:255'],
        ]);

        $context = $validated + [
            'updated_by' => optional($request->user())->id,
        ];

        $this->paymentService->updateStatus($payment, $validated['status'], $context);

        return back()->with('success', __('Payment status updated successfully.'));
    }
}
