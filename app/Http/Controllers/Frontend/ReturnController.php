<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Services\Commerce\ReturnRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    public function __construct(
        protected ReturnRequestService $returnRequestService,
    ) {
    }

    public function index(Request $request)
    {
        $returns = ReturnRequest::query()
            ->with(['order'])
            ->withCount('items')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('frontend.returns._results', compact('returns'));
        }

        return view('frontend.returns.index', compact('returns'));
    }

    public function create(Request $request, Order $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);

        if (! $this->returnRequestService->canCustomerRequest($order, $request->user())) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', __('This order is not currently eligible for a return request.'));
        }

        $order->load('items');

        $remainingByItem = $order->items
            ->mapWithKeys(fn ($item) => [
                $item->id => $this->returnRequestService->remainingReturnableQuantity($item),
            ]);

        return view('frontend.returns.create', [
            'order' => $order,
            'remainingByItem' => $remainingByItem,
            'reasonOptions' => ReturnRequestItem::reasonOptions(),
            'resolutionOptions' => ReturnRequestItem::resolutionOptions(),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.reason_code' => ['nullable', Rule::in(array_keys(ReturnRequestItem::reasonOptions()))],
            'items.*.reason_details' => ['nullable', 'string', 'max:1000'],
            'items.*.requested_resolution' => ['nullable', Rule::in(array_keys(ReturnRequestItem::resolutionOptions()))],
        ]);

        try {
            $returnRequest = $this->returnRequestService->createForCustomer(
                $order,
                $request->user(),
                $data['items'],
                $data['customer_notes'] ?? null,
            );

            $message = __('Return request submitted successfully.');

            if ($request->expectsJson() || $request->header('X-Return-Live') === '1') {
                return response()->json([
                    'message' => $message,
                    'redirect_url' => route('returns.show', $returnRequest),
                    'return' => [
                        'id' => (int) $returnRequest->id,
                        'status' => $returnRequest->status,
                        'status_label' => $returnRequest->status_label,
                    ],
                ], 201);
            }

            return redirect()
                ->route('returns.show', $returnRequest)
                ->with('success', $message);
        } catch (ValidationException $exception) {
            if ($request->expectsJson() || $request->header('X-Return-Live') === '1') {
                throw $exception;
            }

            return back()->withErrors($exception->errors())->withInput();
        }
    }

    public function show(Request $request, ReturnRequest $returnRequest)
    {
        abort_unless((int) $returnRequest->user_id === (int) $request->user()->id, 403);

        $returnRequest->load([
            'order',
            'items.orderItem',
            'refunds',
            'exchangeOrder',
        ]);

        return view('frontend.returns.show', compact('returnRequest'));
    }

    public function cancel(Request $request, ReturnRequest $returnRequest): RedirectResponse|JsonResponse
    {
        try {
            $cancelledReturn = $this->returnRequestService->cancelByCustomer($returnRequest, $request->user());
            $message = __('Return request cancelled.');

            if ($request->expectsJson() || $request->header('X-Return-Cancel-Live') === '1') {
                return response()->json([
                    'message' => $message,
                    'return' => [
                        'status' => $cancelledReturn->status,
                        'status_label' => $cancelledReturn->status_label,
                    ],
                ]);
            }

            return back()->with('success', $message);
        } catch (ValidationException $exception) {
            if ($request->expectsJson() || $request->header('X-Return-Cancel-Live') === '1') {
                throw $exception;
            }

            return back()->withErrors($exception->errors());
        }
    }
}
