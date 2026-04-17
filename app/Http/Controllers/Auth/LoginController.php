<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Frontend\CartService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct(protected CartService $cartService)
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function authenticated($request, $user)
    {
        $this->cartService->mergeGuestCartIntoUserCart($user->id);

        if ((int) $user->role_as === 1) {
            return redirect()->route('admin.dashboard')->with('success', 'Welcome back to the admin dashboard.');
        }

        return redirect()->route('frontend.home')->with('success', 'Logged in successfully.');
    }
}
