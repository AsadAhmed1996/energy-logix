<?php

namespace Tests\Feature;

use App\Services\CaptchaService;
use Mockery;
use Tests\TestCase;

class CaptchaReplenishTest extends TestCase
{
    public function test_it_returns_a_replenished_captcha_image(): void
    {
        $service = Mockery::mock(CaptchaService::class);
        $service->shouldReceive('generateAndPoolNext')
            ->once()
            ->andReturn('data:image/png;base64,example');
        $this->app->instance(CaptchaService::class, $service);

        $this->getJson(route('captcha.replenish'))
            ->assertOk()
            ->assertExactJson(['image' => 'data:image/png;base64,example']);
    }
}
