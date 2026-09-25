<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportCase;
use App\Models\SupportCaseMessage;
use App\Models\User;
use App\Services\Support\SupportCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportCaseController extends Controller
{
    public function __construct(protected SupportCaseService $supportCaseService)
    {
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),
            'priority' => (string) $request->string('priority'),
            'assigned_to_user_id' => $request->integer('assigned_to_user_id') ?: null,
            'per_page' => max(10, min(100, (int) $request->integer('per_page', 20))),
        ];

        $cases = SupportCase::query()
            ->with(['customer:id,name,email', 'order:id,order_number', 'assignee:id,name'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('case_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->when(array_key_exists($filters['status'], SupportCase::statusOptions()), fn ($query) => $query->where('status', $filters['status']))
            ->when(array_key_exists($filters['priority'], SupportCase::priorityOptions()), fn ($query) => $query->where('priority', $filters['priority']))
            ->when($filters['assigned_to_user_id'], fn ($query, $assigneeId) => $query->where('assigned_to_user_id', $assigneeId))
            ->latest('updated_at')
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.support._results', compact('cases'));
        }

        $stats = [
            'open' => SupportCase::query()->whereIn('status', [
                SupportCase::STATUS_OPEN,
                SupportCase::STATUS_PENDING_CUSTOMER,
                SupportCase::STATUS_PENDING_TEAM,
            ])->count(),
            'urgent' => SupportCase::query()->where('priority', SupportCase::PRIORITY_URGENT)->whereNotIn('status', [
                SupportCase::STATUS_RESOLVED,
                SupportCase::STATUS_CLOSED,
            ])->count(),
            'unassigned' => SupportCase::query()->whereNull('assigned_to_user_id')->whereNotIn('status', [
                SupportCase::STATUS_RESOLVED,
                SupportCase::STATUS_CLOSED,
            ])->count(),
            'resolved' => SupportCase::query()->where('status', SupportCase::STATUS_RESOLVED)->count(),
        ];

        $staff = User::query()
            ->where('role_as', 1)
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'support.view'))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);

        return view('admin.support.index', compact('cases', 'filters', 'stats', 'staff'));
    }

    public function create()
    {
        $customers = User::query()
            ->where(function ($query) {
                $query->whereNull('role_as')->orWhere('role_as', '!=', 1);
            })
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);

        $orders = Order::query()
            ->latest('id')
            ->limit(100)
            ->get(['id', 'user_id', 'order_number', 'customer_name', 'customer_email']);

        return view('admin.support.create', compact('customers', 'orders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['required', Rule::in(array_keys(SupportCase::priorityOptions()))],
            'visibility' => ['required', Rule::in([
                SupportCaseMessage::VISIBILITY_CUSTOMER,
                SupportCaseMessage::VISIBILITY_INTERNAL,
            ])],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $case = $this->supportCaseService->createForStaff($request->user(), $validated);

        return redirect()
            ->route('admin.support.show', $case)
            ->with('success', __('Support case created.'));
    }

    public function show(SupportCase $supportCase)
    {
        $supportCase->load([
            'customer:id,name,email',
            'order:id,order_number,status,payment_status,delivery_status,grand_total,currency',
            'assignee:id,name,email',
            'messages.author:id,name,email',
        ]);

        $staff = User::query()
            ->where('role_as', 1)
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'support.view'))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);

        return view('admin.support.show', compact('supportCase', 'staff'));
    }

    public function update(Request $request, SupportCase $supportCase): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportCase::statusOptions()))],
            'priority' => ['required', Rule::in(array_keys(SupportCase::priorityOptions()))],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->supportCaseService->updateCase($supportCase, $request->user(), $validated);

        return back()->with('success', __('Support case updated.'));
    }

    public function reply(Request $request, SupportCase $supportCase): RedirectResponse
    {
        $validated = $request->validate([
            'visibility' => ['required', Rule::in([
                SupportCaseMessage::VISIBILITY_CUSTOMER,
                SupportCaseMessage::VISIBILITY_INTERNAL,
            ])],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->supportCaseService->addStaffMessage(
            $supportCase,
            $request->user(),
            $validated['message'],
            $validated['visibility']
        );

        return back()->with(
            'success',
            $validated['visibility'] === SupportCaseMessage::VISIBILITY_INTERNAL
                ? __('Internal note added.')
                : __('Reply sent to the customer timeline.')
        );
    }
}
