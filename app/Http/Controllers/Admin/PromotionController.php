<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PromotionRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'type' => (string) $request->string('type'),
            'status' => (string) $request->string('status'),
            'schedule' => (string) $request->string('schedule'),
            'per_page' => max(20, min(100, (int) $request->integer('per_page', 20))),
            'sort' => (string) ($request->input('sort') ?: 'priority'),
            'direction' => (string) ($request->input('direction') ?: 'desc'),
        ];

        $promotions = PromotionRule::query()
            ->with('category')
            ->when($filters['search'], fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['schedule'] === 'upcoming', fn ($query) => $query->whereNotNull('starts_at')->where('starts_at', '>', now()))
            ->when($filters['schedule'] === 'expired', fn ($query) => $query->whereNotNull('ends_at')->where('ends_at', '<', now()))
            ->when($filters['schedule'] === 'running', fn ($query) => $query->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now())))
            ->when($filters['status'] !== '', function ($query) use ($filters) {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy(in_array($filters['sort'], ['priority', 'name', 'type', 'discount_value', 'created_at']) ? $filters['sort'] : 'priority', $filters['direction'] === 'asc' ? 'asc' : 'desc')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $stats = [
            'total' => PromotionRule::count(),
            'active' => PromotionRule::where('is_active', true)->count(),
            'inactive' => PromotionRule::where('is_active', false)->count(),
            'buy_x_get_y' => PromotionRule::where('type', 'buy_x_get_y')->count(),
            'running' => PromotionRule::where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->count(),
            'upcoming' => PromotionRule::whereNotNull('starts_at')->where('starts_at', '>', now())->count(),
            'expired' => PromotionRule::whereNotNull('ends_at')->where('ends_at', '<', now())->count(),
        ];

        return view('admin.promotions.index', compact('promotions', 'filters', 'stats'));
    }

    public function create()
    {
        return view('admin.promotions.create', [
            'categories' => Category::orderBy('name')->get(),
            'promotion' => new PromotionRule(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        PromotionRule::create($this->validated($request));

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion rule created successfully.'));
    }

    public function edit(PromotionRule $promotion)
    {
        return view('admin.promotions.create', [
            'categories' => Category::orderBy('name')->get(),
            'promotion' => $promotion,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, PromotionRule $promotion)
    {
        $promotion->update($this->validated($request));

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion rule updated successfully.'));
    }

    public function destroy(PromotionRule $promotion)
    {
        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion rule deleted successfully.'));
    }

    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['order_percentage', 'order_fixed', 'category_percentage', 'buy_x_get_y'])],
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if (in_array($request->input('type'), ['order_percentage', 'category_percentage'], true) && (float) $value > 100) {
                        $fail(__('Percentage promotions cannot exceed 100%.'));
                    }
                },
            ],
            'category_id' => [
                Rule::requiredIf($request->input('type') === 'category_percentage'),
                'nullable',
                'exists:categories,id',
            ],
            'buy_quantity' => [Rule::requiredIf($request->input('type') === 'buy_x_get_y'), 'nullable', 'integer', 'min:1'],
            'get_quantity' => [Rule::requiredIf($request->input('type') === 'buy_x_get_y'), 'nullable', 'integer', 'min:1'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = (string) $validated['type'];

        if (! in_array($type, ['category_percentage', 'buy_x_get_y'], true)) {
            $validated['category_id'] = null;
        }

        if ($type !== 'buy_x_get_y') {
            $validated['buy_quantity'] = null;
            $validated['get_quantity'] = null;
        }

        if ($type === 'buy_x_get_y') {
            $validated['discount_value'] = 0;
        }

        $validated['priority'] = (int) ($validated['priority'] ?? 0);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
