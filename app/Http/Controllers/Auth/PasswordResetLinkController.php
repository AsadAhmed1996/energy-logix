<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\Captcha;
use App\Services\CaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'captchaImages' => app(CaptchaService::class)->generatePool(5),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'captcha' => [config('services.captcha.enabled') ? 'required' : 'nullable', 'string', new Captcha()],
        ]);

        // We will attempt to send the password reset link to this email.
        // To prevent user enumeration, we always return a generic success message
        // whether the email exists in our system or not.
        Password::sendResetLink(
            $request->only('email')
        );

        return back()->with('status', __('passwords.sent') ?? 'We have emailed your password reset link.');
    }
}
