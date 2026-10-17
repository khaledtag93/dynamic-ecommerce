<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Hide whether an email is registered or an actual account was throttled.
     * The password broker still decides whether to issue/send a reset token;
     * only the public response is normalized for syntactically valid emails.
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return $this->sendResetLinkResponse($request, Password::RESET_LINK_SENT);
    }
}
