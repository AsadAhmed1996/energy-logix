<?php

namespace Tests\Unit;

use App\Services\CaptchaService;
use Tests\TestCase;

class CaptchaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session()->flush();
    }

    public function test_it_generates_images_and_persists_a_captcha_pool(): void
    {
        $images = (new CaptchaService())->generatePool(3);

        $this->assertCount(3, $images);
        $this->assertCount(3, session('captcha_pool'));

        foreach ($images as $image) {
            $this->assertStringStartsWith('data:image/png;base64,', $image);
        }

        foreach (session('captcha_pool') as $code) {
            $this->assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ]{5}$/', $code);
        }
    }

    public function test_it_keeps_only_the_ten_most_recent_captcha_codes(): void
    {
        $existingCodes = ['aaaaa', 'bbbbb', 'ccccc', 'ddddd', 'eeeee', 'fffff', 'ggggg', 'hhhhh', 'jjjjj', 'kkkkk'];
        session()->put('captcha_pool', $existingCodes);

        $image = (new CaptchaService())->generateAndPoolNext();
        $pool = session('captcha_pool');

        $this->assertStringStartsWith('data:image/png;base64,', $image);
        $this->assertCount(10, $pool);
        $this->assertSame(array_slice($existingCodes, 1), array_slice($pool, 0, 9));
        $this->assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ]{5}$/', $pool[9]);
    }
}
