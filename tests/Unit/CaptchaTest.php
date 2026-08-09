<?php

namespace Tests\Unit;

use App\Rules\Captcha;
use Tests\TestCase;

class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.captcha.enabled' => true]);
        session()->flush();
    }

    public function test_it_skips_validation_when_captcha_is_disabled(): void
    {
        config(['services.captcha.enabled' => false]);

        $this->assertSame([], $this->validationMessages('incorrect'));
    }

    public function test_it_accepts_the_expected_code_case_insensitively(): void
    {
        session()->put('captcha', 'AbC12');

        $this->assertSame([], $this->validationMessages('aBc12'));
    }

    public function test_it_accepts_a_code_from_the_captcha_pool(): void
    {
        session()->put('captcha_pool', ['first1', 'SECOND']);

        $this->assertSame([], $this->validationMessages('second'));
    }

    public function test_it_rejects_an_unknown_code(): void
    {
        $this->assertSame(
            ['The CAPTCHA code is incorrect. Please try again.'],
            $this->validationMessages('incorrect'),
        );
    }

    private function validationMessages(mixed $value): array
    {
        $messages = [];

        (new Captcha())->validate('captcha', $value, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        return $messages;
    }
}
