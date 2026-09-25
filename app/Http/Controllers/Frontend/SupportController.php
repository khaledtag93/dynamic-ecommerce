<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\SupportCase;
use App\Services\Support\SupportCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(protected SupportCaseService $supportCaseService)
    {
    }

    public function index(Request $request)
    {
        $cases = SupportCase::query()
            ->where('customer_id', $request->user()->id)
            ->with(['order:id,order_number'])
            ->latest('updated_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('frontend.support._results', compact('cases'));
        }

        return view('frontend.support.index', compact('cases'));
    }

    public function create(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->latest('id')
            ->limit(50)
            ->get(['id', 'order_number', 'status', 'placed_at', 'created_at']);

        return view('frontend.support.create', compact('orders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $case = $this->supportCaseService->createForCustomer($request->user(), $validated);

        return redirect()
            ->route('support.show', $case)
            ->with('success', __('Your support request has been created.'));
    }

    public function show(Request $request, SupportCase $supportCase)
    {
        $this->assertOwnership($request, $supportCase);

        $supportCase->load([
            'order:id,order_number,status',
            'customerMessages.author:id,name',
        ]);

        return view('frontend.support.show', compact('supportCase'));
    }

    public function reply(Request $request, SupportCase $supportCase)
    {
        $this->assertOwnership($request, $supportCase);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $reply = $this->supportCaseService->addCustomerReply(
            $supportCase,
            $request->user(),
            $validated['message']
        );

        if ($request->expectsJson() || $request->header('X-Support-Live') === '1') {
            $reply->loadMissing('author:id,name');
            $freshCase = $supportCase->fresh();

            return response()->json([
                'message' => __('Your reply was added.'),
                'reply' => [
                    'id' => (int) $reply->id,
                    'body' => (string) $reply->body,
                    'author_label' => __('You'),
                    'created_at' => $reply->created_at?->format('d M Y H:i'),
                ],
                'case' => [
                    'status' => (string) $freshCase->status,
                    'status_label' => (string) $freshCase->status_label,
                    'updated_at' => $freshCase->updated_at?->format('d M Y H:i'),
                ],
            ]);
        }

        return back()->with('success', __('Your reply was added.'));
    }

    private function assertOwnership(Request $request, SupportCase $supportCase): void
    {
        abort_unless((int) $supportCase->customer_id === (int) $request->user()->id, 404);
    }
}
