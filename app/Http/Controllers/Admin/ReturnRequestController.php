<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Commerce\ReturnRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReturnRequestController extends Controller
{
    public function __construct(
        protected ReturnRequestService $returnRequestService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),
            'per_page' => max(12, min(100, (int) $request->integer('per_page', 20))),
        ];

        $returns = ReturnRequest::query()
            ->with(['order', 'user'])
            ->withCount('items')
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('order', fn ($order) => $order
                            ->where('order_number', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.returns._results', compact('returns', 'filters'));
        }

        $stats = [
            'requested' => ReturnRequest::where('status', ReturnRequest::STATUS_REQUESTED)->count(),
            'approved' => ReturnRequest::where('status', ReturnRequest::STATUS_APPROVED)->count(),
            'received' => ReturnRequest::where('status', ReturnRequest::STATUS_RECEIVED)->count(),
            'completed' => ReturnRequest::where('status', ReturnRequest::STATUS_COMPLETED)->count(),
        ];

        return view('admin.returns.index', [
            'returns' => $returns,
            'filters' => $filters,
            'stats' => $stats,
            'statusOptions' => ReturnRequest::statusOptions(),
        ]);
    }

    public function show(ReturnRequest $returnRequest)
    {
        $returnRequest->load([
            'order',
            'user',
            'items.orderItem.product',
            'items.orderItem.variant',
            'reviewedBy',
            'receivedBy',
            'completedBy',
            'refunds',
            'exchangeOrder',
        ]);

        return view('admin.returns.show', compact('returnRequest'));
    }

    public function approve(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $data = $request->validate([
            'approved_quantities' => ['required', 'array'],
            'approved_quantities.*' => ['required', 'integer', 'min:0'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->returnRequestService->approve(
                $returnRequest,
                $data['approved_quantities'],
                $data['review_notes'] ?? null,
                $request->user(),
            );

            return back()->with('success', __('Return request approved.'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    public function reject(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $data = $request->validate([
            'review_notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            $this->returnRequestService->reject(
                $returnRequest,
                $data['review_notes'],
                $request->user(),
            );

            return back()->with('success', __('Return request rejected.'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    public function receive(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $data = $request->validate([
            'received_quantities' => ['required', 'array'],
            'received_quantities.*' => ['required', 'integer', 'min:0'],
            'restock_quantities' => ['required', 'array'],
            'restock_quantities.*' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $this->returnRequestService->receive(
                $returnRequest,
                $data['received_quantities'],
                $data['restock_quantities'],
                $request->user(),
            );

            return back()->with('success', __('Returned items marked received.'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    public function complete(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $data = $request->validate([
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'exchange_order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->returnRequestService->complete(
                $returnRequest,
                (float) ($data['refund_amount'] ?? 0),
                isset($data['exchange_order_id']) ? (int) $data['exchange_order_id'] : null,
                $data['completion_notes'] ?? null,
                $request->user(),
            );

            return back()->with('success', __('Return request completed.'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }
}
