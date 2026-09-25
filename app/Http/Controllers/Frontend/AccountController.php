<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('frontend.account.index', [
            'recentOrders' => $user->orders()->latest()->limit(3)->get(),
            'ordersCount' => $user->orders()->count(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'addressesCount' => $user->addresses()->count(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $emailChanged = $request->input('email') !== $user->email;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'current_password'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        $message = __('Profile updated.');

        if ($request->expectsJson() || $request->header('X-Account-Live') === '1') {
            return response()->json([
                'message' => $message,
                'user' => ['name' => $user->name, 'email' => $user->email],
                'email_verification_required' => $emailChanged,
            ]);
        }

        return back()->with('success', $message);
    }

    public function updatePassword(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validateWithBag('passwordUpdate', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);
        $message = __('Password updated.');

        if ($request->expectsJson() || $request->header('X-Account-Live') === '1') {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
