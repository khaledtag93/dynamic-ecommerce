<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Frontend\AddressBookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddressBookController extends Controller
{
    public function __construct(protected AddressBookService $addressBook)
    {
    }

    public function index(Request $request)
    {
        return view('frontend.account.addresses.index', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default_shipping')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('frontend.account.addresses.form', ['address' => null]);
    }

    public function edit(Request $request, int $address)
    {
        return view('frontend.account.addresses.form', [
            'address' => $request->user()->addresses()->whereKey($address)->firstOrFail(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $address = $this->addressBook->save($request->user(), $this->validatedAddress($request));
        $message = __('Address saved.');

        if ($request->expectsJson() || $request->header('X-Address-Live') === '1') {
            return response()->json([
                'message' => $message,
                'redirect_url' => route('account.addresses.index'),
                'address' => ['id' => (int) $address->id],
            ], 201);
        }

        return redirect()->route('account.addresses.index')->with('success', $message);
    }

    public function update(Request $request, int $address): RedirectResponse|JsonResponse
    {
        $savedAddress = $this->addressBook->save($request->user(), $this->validatedAddress($request), $address);
        $message = __('Address updated.');

        if ($request->expectsJson() || $request->header('X-Address-Live') === '1') {
            return response()->json([
                'message' => $message,
                'redirect_url' => route('account.addresses.index'),
                'address' => ['id' => (int) $savedAddress->id],
            ]);
        }

        return redirect()->route('account.addresses.index')->with('success', $message);
    }

    public function destroy(Request $request, int $address): RedirectResponse|JsonResponse
    {
        $this->addressBook->delete($request->user(), $address);
        $message = __('Address deleted.');

        if ($request->expectsJson() || $request->header('X-Address-Live') === '1') {
            $addresses = $request->user()->addresses()
                ->orderByDesc('is_default_shipping')
                ->orderBy('id')
                ->get(['id', 'is_default_shipping', 'is_default_billing']);

            return response()->json([
                'message' => $message,
                'addresses' => $addresses->map(fn ($savedAddress) => [
                    'id' => (int) $savedAddress->id,
                    'is_default_shipping' => (bool) $savedAddress->is_default_shipping,
                    'is_default_billing' => (bool) $savedAddress->is_default_billing,
                ])->values(),
            ]);
        }

        return redirect()->route('account.addresses.index')->with('success', $message);
    }

    private function validatedAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country' => ['required', 'string', 'max:120'],
            'is_default_shipping' => ['sometimes', 'boolean'],
            'is_default_billing' => ['sometimes', 'boolean'],
        ]);
    }
}
