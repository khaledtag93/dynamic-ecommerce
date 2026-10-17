<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Frontend\CartService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    use RegistersUsers {
        register as protected frameworkRegister;
    }

    protected $redirectTo = '/';

    public function __construct(protected CartService $cartService)
    {
        $this->middleware('guest');
    }

    public function register(Request $request)
    {
        // The registration guard also rotates the session when logging in the
        // new account; preserve the previous guest-cart owner for this request.
        $request->attributes->set('flowra_guest_cart_session_id', $request->session()->getId());

        return $this->frameworkRegister($request);
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    protected function create(array $data)
    {
        try {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            // Another request can claim the email after unique validation,
            // but before this INSERT. Preserve database uniqueness and return
            // the same field-level response as an ordinary duplicate signup.
            if (! User::query()->where('email', $data['email'])->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }
    }

    protected function registered($request, $user)
    {
        $this->cartService->mergeGuestCartIntoUserCart(
            $user->id,
            $request->attributes->get('flowra_guest_cart_session_id')
        );

        return redirect()->route('frontend.home')->with('success', 'Your account has been created successfully.');
    }
}
