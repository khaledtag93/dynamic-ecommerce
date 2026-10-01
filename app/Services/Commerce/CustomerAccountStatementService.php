<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerAccountStatementService
{
    public const PAGE_SIZE = 50;
    public const PRINT_LIMIT = 500;
    public const EXPORT_LIMIT = 5000;

    public function build(
        User $customer,
        array $filters,
        int $perPage = self::PAGE_SIZE,
        ?int $page = null,
    ): array {
        [$from, $to, $type] = $this->periodAndType($filters);
        $perPage = max(10, min(100, $perPage));
        $page = max(1, $page ?? Paginator::resolveCurrentPage('page'));
        $summary = $this->summary($customer, $from, $to, $type);

        $indexRows = $this->orderedMovementIndex($customer, $from, $to, $type)
            ->forPage($page, $perPage)
            ->get();

        $movements = $this->hydrateMovementRows($indexRows);
        $paginator = new LengthAwarePaginator(
            $movements,
            $summary['matching_count'],
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
        $paginator->withQueryString();

        return $summary + [
            'movements' => $paginator,
            'from' => $from,
            'to' => $to,
            'per_page' => $perPage,
            'print_limit' => self::PRINT_LIMIT,
            'export_limit' => self::EXPORT_LIMIT,
        ];
    }

    public function buildBounded(User $customer, array $filters, int $limit): array
    {
        [$from, $to, $type] = $this->periodAndType($filters);
        $limit = max(1, $limit);
        $summary = $this->summary($customer, $from, $to, $type);

        $indexRows = $this->orderedMovementIndex($customer, $from, $to, $type)
            ->limit($limit + 1)
            ->get();

        $truncated = $indexRows->count() > $limit;
        $indexRows = $indexRows->take($limit)->values();
        $movements = $this->hydrateMovementRows($indexRows);

        return $summary + [
            'movements' => $movements,
            'from' => $from,
            'to' => $to,
            'row_limit' => $limit,
            'displayed_count' => $movements->count(),
            'truncated' => $truncated,
        ];
    }

    private function periodAndType(array $filters): array
    {
        return [
            CarbonImmutable::parse($filters['date_from'])->startOfDay(),
            CarbonImmutable::parse($filters['date_to'])->endOfDay(),
            (string) ($filters['type'] ?? ''),
        ];
    }

    private function summary(User $customer, CarbonImmutable $from, CarbonImmutable $to, string $type): array
    {
        $counts = [
            'orders' => $this->includesType($type, 'order')
                ? $this->orderScope($customer, $from, $to)->count()
                : 0,
            'payments' => $this->includesType($type, 'payment')
                ? $this->paymentScope($customer, $from, $to)->count()
                : 0,
            'refunds' => $this->includesType($type, 'refund')
                ? $this->refundScope($customer, $from, $to)->count()
                : 0,
            'returns' => $this->includesType($type, 'return')
                ? $this->returnScope($customer, $from, $to)->count()
                : 0,
        ];

        return [
            'totals_by_currency' => $this->totalsByCurrency($customer, $from, $to, $type),
            'counts' => $counts,
            'matching_count' => array_sum($counts),
        ];
    }

    private function totalsByCurrency(
        User $customer,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $type,
    ): Collection {
        $totals = [];

        if ($this->includesType($type, 'order')) {
            $rows = $this->orderScope($customer, $from, $to)
                ->selectRaw("COALESCE(NULLIF(orders.currency, ''), 'EGP') AS statement_currency")
                ->selectRaw('SUM(orders.grand_total) AS statement_total')
                ->groupByRaw("COALESCE(NULLIF(orders.currency, ''), 'EGP')")
                ->get();

            $this->mergeCurrencyTotals($totals, $rows, 'order_value');
        }

        if ($this->includesType($type, 'payment')) {
            $rows = $this->paymentScope($customer, $from, $to)
                ->selectRaw("COALESCE(NULLIF(payments.currency, ''), NULLIF(orders.currency, ''), 'EGP') AS statement_currency")
                ->selectRaw('SUM(payments.amount) AS statement_total')
                ->groupByRaw("COALESCE(NULLIF(payments.currency, ''), NULLIF(orders.currency, ''), 'EGP')")
                ->get();

            $this->mergeCurrencyTotals($totals, $rows, 'payments_captured');
        }

        if ($this->includesType($type, 'refund')) {
            $rows = $this->refundScope($customer, $from, $to)
                ->selectRaw("COALESCE(NULLIF(orders.currency, ''), 'EGP') AS statement_currency")
                ->selectRaw('SUM(order_refunds.amount) AS statement_total')
                ->groupByRaw("COALESCE(NULLIF(orders.currency, ''), 'EGP')")
                ->get();

            $this->mergeCurrencyTotals($totals, $rows, 'refunds_processed');
        }

        return collect(array_values($totals))
            ->sortBy('currency')
            ->values();
    }

    private function mergeCurrencyTotals(array &$totals, Collection $rows, string $field): void
    {
        foreach ($rows as $row) {
            $currency = (string) $row->statement_currency;

            if (! isset($totals[$currency])) {
                $totals[$currency] = [
                    'currency' => $currency,
                    'order_value' => '0.00',
                    'payments_captured' => '0.00',
                    'refunds_processed' => '0.00',
                ];
            }

            $totals[$currency][$field] = $this->canonicalMoney($row->statement_total);
        }
    }

    private function orderedMovementIndex(
        User $customer,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $type,
    ): QueryBuilder {
        return DB::query()
            ->fromSub($this->movementIndexQuery($customer, $from, $to, $type), 'statement_movements')
            ->select(['movement_type', 'movement_id', 'occurred_at'])
            ->orderByDesc('occurred_at')
            ->orderBy('movement_type')
            ->orderByDesc('movement_id');
    }

    private function movementIndexQuery(
        User $customer,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $type,
    ): QueryBuilder {
        $queries = [];

        if ($this->includesType($type, 'order')) {
            $queries[] = DB::table('orders')
                ->where('orders.user_id', $customer->id)
                ->whereNotNull('orders.placed_at')
                ->whereBetween('orders.placed_at', [$from, $to])
                ->selectRaw("'order' AS movement_type, orders.id AS movement_id, orders.placed_at AS occurred_at");

            $queries[] = DB::table('orders')
                ->where('orders.user_id', $customer->id)
                ->whereNull('orders.placed_at')
                ->whereBetween('orders.created_at', [$from, $to])
                ->selectRaw("'order' AS movement_type, orders.id AS movement_id, orders.created_at AS occurred_at");
        }

        if ($this->includesType($type, 'payment')) {
            $queries[] = DB::table('payments')
                ->join('orders', 'orders.id', '=', 'payments.order_id')
                ->where('orders.user_id', $customer->id)
                ->whereIn('payments.status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
                ->whereNotNull('payments.paid_at')
                ->whereBetween('payments.paid_at', [$from, $to])
                ->selectRaw("'payment' AS movement_type, payments.id AS movement_id, payments.paid_at AS occurred_at");
        }

        if ($this->includesType($type, 'refund')) {
            $queries[] = DB::table('order_refunds')
                ->join('orders', 'orders.id', '=', 'order_refunds.order_id')
                ->where('orders.user_id', $customer->id)
                ->where('order_refunds.amount', '>', 0)
                ->whereNotNull('order_refunds.processed_at')
                ->whereBetween('order_refunds.processed_at', [$from, $to])
                ->selectRaw("'refund' AS movement_type, order_refunds.id AS movement_id, order_refunds.processed_at AS occurred_at");

            $queries[] = DB::table('order_refunds')
                ->join('orders', 'orders.id', '=', 'order_refunds.order_id')
                ->where('orders.user_id', $customer->id)
                ->where('order_refunds.amount', '>', 0)
                ->whereNull('order_refunds.processed_at')
                ->whereBetween('order_refunds.created_at', [$from, $to])
                ->selectRaw("'refund' AS movement_type, order_refunds.id AS movement_id, order_refunds.created_at AS occurred_at");
        }

        if ($this->includesType($type, 'return')) {
            $queries[] = DB::table('return_requests')
                ->join('orders', 'orders.id', '=', 'return_requests.order_id')
                ->where('orders.user_id', $customer->id)
                ->whereNotNull('return_requests.requested_at')
                ->whereBetween('return_requests.requested_at', [$from, $to])
                ->selectRaw("'return' AS movement_type, return_requests.id AS movement_id, return_requests.requested_at AS occurred_at");

            $queries[] = DB::table('return_requests')
                ->join('orders', 'orders.id', '=', 'return_requests.order_id')
                ->where('orders.user_id', $customer->id)
                ->whereNull('return_requests.requested_at')
                ->whereBetween('return_requests.created_at', [$from, $to])
                ->selectRaw("'return' AS movement_type, return_requests.id AS movement_id, return_requests.created_at AS occurred_at");
        }

        /** @var QueryBuilder $union */
        $union = array_shift($queries);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function orderScope(User $customer, CarbonImmutable $from, CarbonImmutable $to): QueryBuilder
    {
        return DB::table('orders')
            ->where('orders.user_id', $customer->id)
            ->where(function (QueryBuilder $query) use ($from, $to) {
                $query->whereBetween('orders.placed_at', [$from, $to])
                    ->orWhere(function (QueryBuilder $fallback) use ($from, $to) {
                        $fallback->whereNull('orders.placed_at')
                            ->whereBetween('orders.created_at', [$from, $to]);
                    });
            });
    }

    private function paymentScope(User $customer, CarbonImmutable $from, CarbonImmutable $to): QueryBuilder
    {
        return DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.user_id', $customer->id)
            ->whereIn('payments.status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
            ->whereNotNull('payments.paid_at')
            ->whereBetween('payments.paid_at', [$from, $to]);
    }

    private function refundScope(User $customer, CarbonImmutable $from, CarbonImmutable $to): QueryBuilder
    {
        return DB::table('order_refunds')
            ->join('orders', 'orders.id', '=', 'order_refunds.order_id')
            ->where('orders.user_id', $customer->id)
            ->where('order_refunds.amount', '>', 0)
            ->where(function (QueryBuilder $query) use ($from, $to) {
                $query->whereBetween('order_refunds.processed_at', [$from, $to])
                    ->orWhere(function (QueryBuilder $fallback) use ($from, $to) {
                        $fallback->whereNull('order_refunds.processed_at')
                            ->whereBetween('order_refunds.created_at', [$from, $to]);
                    });
            });
    }

    private function returnScope(User $customer, CarbonImmutable $from, CarbonImmutable $to): QueryBuilder
    {
        return DB::table('return_requests')
            ->join('orders', 'orders.id', '=', 'return_requests.order_id')
            ->where('orders.user_id', $customer->id)
            ->where(function (QueryBuilder $query) use ($from, $to) {
                $query->whereBetween('return_requests.requested_at', [$from, $to])
                    ->orWhere(function (QueryBuilder $fallback) use ($from, $to) {
                        $fallback->whereNull('return_requests.requested_at')
                            ->whereBetween('return_requests.created_at', [$from, $to]);
                    });
            });
    }

    private function hydrateMovementRows(Collection $rows): Collection
    {
        $orderIds = $this->movementIds($rows, 'order');
        $paymentIds = $this->movementIds($rows, 'payment');
        $refundIds = $this->movementIds($rows, 'refund');
        $returnIds = $this->movementIds($rows, 'return');

        $orders = $orderIds === []
            ? collect()
            : Order::query()
                ->whereIntegerInRaw('id', $orderIds)
                ->get([
                    'id',
                    'order_number',
                    'status',
                    'payment_status',
                    'delivery_status',
                    'grand_total',
                    'currency',
                    'placed_at',
                    'created_at',
                ])
                ->keyBy('id');

        $payments = $paymentIds === []
            ? collect()
            : Payment::query()
                ->whereIntegerInRaw('id', $paymentIds)
                ->with('order:id,user_id,order_number,currency')
                ->get([
                    'id',
                    'order_id',
                    'method',
                    'status',
                    'transaction_reference',
                    'amount',
                    'currency',
                    'paid_at',
                ])
                ->keyBy('id');

        $refunds = $refundIds === []
            ? collect()
            : OrderRefund::query()
                ->whereIntegerInRaw('id', $refundIds)
                ->with([
                    'order:id,user_id,order_number,currency',
                    'returnRequest:id,reference',
                ])
                ->get([
                    'id',
                    'order_id',
                    'return_request_id',
                    'amount',
                    'reason',
                    'processed_at',
                    'created_at',
                ])
                ->keyBy('id');

        $returns = $returnIds === []
            ? collect()
            : ReturnRequest::query()
                ->whereIntegerInRaw('id', $returnIds)
                ->with('order:id,user_id,order_number,currency')
                ->get([
                    'id',
                    'reference',
                    'order_id',
                    'user_id',
                    'status',
                    'requested_at',
                    'created_at',
                ])
                ->keyBy('id');

        return $rows
            ->map(function ($row) use ($orders, $payments, $refunds, $returns) {
                $id = (int) $row->movement_id;

                return match ((string) $row->movement_type) {
                    'order' => ($order = $orders->get($id)) ? $this->orderMovement($order) : null,
                    'payment' => ($payment = $payments->get($id)) ? $this->paymentMovement($payment) : null,
                    'refund' => ($refund = $refunds->get($id)) ? $this->refundMovement($refund) : null,
                    'return' => ($returnRequest = $returns->get($id)) ? $this->returnMovement($returnRequest) : null,
                    default => null,
                };
            })
            ->filter()
            ->values();
    }

    private function movementIds(Collection $rows, string $type): array
    {
        return $rows
            ->where('movement_type', $type)
            ->pluck('movement_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function orderMovement(Order $order): array
    {
        return [
            'type' => 'order',
            'type_label' => __('Order placed'),
            'reference' => $order->order_number,
            'status_label' => $order->status_label,
            'amount' => $this->canonicalMoney($order->grand_total),
            'currency' => $order->currency ?: 'EGP',
            'occurred_at' => $order->placed_at ?: $order->created_at,
            'details' => __('Payment: :payment · Delivery: :delivery', [
                'payment' => $order->payment_status_label,
                'delivery' => $order->delivery_status_label,
            ]),
            'url' => route('admin.orders.show', $order),
        ];
    }

    private function paymentMovement(Payment $payment): array
    {
        return [
            'type' => 'payment',
            'type_label' => __('Payment captured'),
            'reference' => $payment->transaction_reference ?: ($payment->order?->order_number ?? '#'.$payment->id),
            'status_label' => $payment->status_label,
            'amount' => $this->canonicalMoney($payment->amount),
            'currency' => $payment->currency ?: ($payment->order?->currency ?? 'EGP'),
            'occurred_at' => $payment->paid_at,
            'details' => trim(($payment->method_label ?: __('Payment')).($payment->order ? ' · '.$payment->order->order_number : '')),
            'url' => route('admin.payments.show', $payment),
        ];
    }

    private function refundMovement(OrderRefund $refund): array
    {
        $returnReference = $refund->returnRequest?->reference;

        return [
            'type' => 'refund',
            'type_label' => __('Refund processed'),
            'reference' => $returnReference ?: ($refund->order?->order_number ?? '#'.$refund->id),
            'status_label' => __('Processed'),
            'amount' => $this->canonicalMoney($refund->amount),
            'currency' => $refund->order?->currency ?: 'EGP',
            'occurred_at' => $refund->processed_at ?: $refund->created_at,
            'details' => $refund->reason ?: __('Refund linked to :order', [
                'order' => $refund->order?->order_number ?? '#'.$refund->order_id,
            ]),
            'url' => $refund->returnRequest
                ? route('admin.returns.show', $refund->returnRequest)
                : ($refund->order ? route('admin.orders.show', $refund->order) : null),
        ];
    }

    private function returnMovement(ReturnRequest $returnRequest): array
    {
        return [
            'type' => 'return',
            'type_label' => __('Return request'),
            'reference' => $returnRequest->reference,
            'status_label' => $returnRequest->status_label,
            'amount' => null,
            'currency' => $returnRequest->order?->currency,
            'occurred_at' => $returnRequest->requested_at ?: $returnRequest->created_at,
            'details' => $returnRequest->order
                ? __('Linked order: :order', ['order' => $returnRequest->order->order_number])
                : __('Return request'),
            'url' => route('admin.returns.show', $returnRequest),
        ];
    }

    private function canonicalMoney(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))
            ->toScale(2, RoundingMode::Unnecessary);
    }

    private function includesType(string $filterType, string $candidate): bool
    {
        return $filterType === '' || $filterType === $candidate;
    }
}
