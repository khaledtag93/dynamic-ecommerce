<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Frontend\CartService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers {
        login as protected frameworkLogin;
    }

    public function __construct(protected CartService $cartService)
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function login(Request $request)
    {
        // Laravel rotates the session during login. Capture only the server-side
        // pre-auth session identifier for the existing guest-cart handoff.
        $request->attributes->set('flowra_guest_cart_session_id', $request->session()->getId());

        return $this->frameworkLogin($request);
    }

    protected function authenticated($request, $user)
    {
        $this->cartService->mergeGuestCartIntoUserCart(
            $user->id,
            $request->attributes->get('flowra_guest_cart_session_id')
        );

        if ((int) $user->role_as === 1) {
            return redirect()->route('admin.dashboard')->with('success', 'Welcome back to the admin dashboard.');
        }

        return redirect()->route('frontend.home')->with('success', 'Logged in successfully.');
    }
}
