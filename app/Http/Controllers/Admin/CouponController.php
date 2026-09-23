<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'type' => (string) $request->string('type'),
            'status' => (string) $request->string('status'),
            'usage' => (string) $request->string('usage'),
            'per_page' => max(12, min(100, (int) $request->integer('per_page', 12))),
            'sort' => (string) $request->string('sort', 'id'),
            'direction' => strtolower((string) $request->string('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        $sortMap = [
            'id' => 'id',
            'code' => 'code',
            'value' => 'value',
            'used_count' => 'used_count',
            'ends_at' => 'ends_at',
        ];

        $sortColumn = $sortMap[$filters['sort']] ?? 'id';

        $coupons = Coupon::query()
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['usage'] === 'used', fn ($query) => $query->where('used_count', '>', 0))
            ->when($filters['usage'] === 'unused', fn ($query) => $query->where('used_count', 0))
            ->when($filters['usage'] === 'limit_reached', fn ($query) => $query->whereNotNull('usage_limit')->whereColumn('used_count', '>=', 'usage_limit'))
            ->when($filters['status'] !== '', function ($query) use ($filters) {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                }

                if ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                }

                if ($filters['status'] === 'expired') {
                    $query->whereNotNull('ends_at')->where('ends_at', '<', now());
                }
            })
            ->orderBy($sortColumn, $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        $stats = [
            'total' => Coupon::count(),
            'active' => Coupon::where('is_active', true)->count(),
            'expired' => Coupon::whereNotNull('ends_at')->where('ends_at', '<', now())->count(),
            'used' => Coupon::where('used_count', '>', 0)->count(),
            'unused' => Coupon::where('used_count', 0)->count(),
            'limit_reached' => Coupon::whereNotNull('usage_limit')->whereColumn('used_count', '>=', 'usage_limit')->count(),
        ];

        return view('admin.coupons.index', [
            'coupons' => $coupons,
            'filters' => $filters,
            'typeOptions' => Coupon::typeOptions(),
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return view('admin.coupons.form', [
            'coupon' => new Coupon(['is_active' => true, 'type' => Coupon::TYPE_FIXED]),
            'typeOptions' => Coupon::typeOptions(),
            'action' => route('admin.coupons.store'),
            'method' => 'POST',
            'title' => __('Create Coupon'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coupon = Coupon::create($this->validatedData($request));

        return redirect()->route('admin.coupons.edit', $coupon)->with('success', __('Coupon created successfully.'));
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', [
            'coupon' => $coupon,
            'typeOptions' => Coupon::typeOptions(),
            'action' => route('admin.coupons.update', $coupon),
            'method' => 'PUT',
            'title' => __('Edit Coupon'),
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validatedData($request, $coupon));

        return redirect()->route('admin.coupons.edit', $coupon)->with('success', __('Coupon updated successfully.'));
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', __('Coupon deleted successfully.'));
    }

    protected function validatedData(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type' => ['required', Rule::in(array_keys(Coupon::typeOptions()))],
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ($request->input('type') === Coupon::TYPE_PERCENT && (float) $value > 100) {
                        $fail(__('Percentage coupons cannot exceed 100%.'));
                    }
                },
            ],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
