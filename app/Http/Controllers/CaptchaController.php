<?php

namespace App\Http\Controllers;

use App\Services\CaptchaService;
use Illuminate\Http\JsonResponse;

class CaptchaController extends Controller
{
    public function __construct(
        protected CaptchaService $captchaService
    ) {}

    /**
     * Generate a new captcha, append to session pool, and return base64 image.
     */
    public function replenish(): JsonResponse
    {
        return response()->json([
            'image' => $this->captchaService->generateAndPoolNext(),
        ]);
    }
}
