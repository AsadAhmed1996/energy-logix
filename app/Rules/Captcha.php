<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Captcha implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('services.captcha.enabled')) {
            return;
        }

        $expected = session()->get('captcha');
        $pool = session()->get('captcha_pool', []);

        $valueStr = strtolower((string) $value);
        $expectedStr = $expected ? strtolower((string) $expected) : null;
        $poolStrs = array_map(fn($item) => strtolower((string) $item), $pool);

        $isValid = ($expectedStr && $valueStr === $expectedStr) || in_array($valueStr, $poolStrs, true);

        if (! $isValid) {
            $fail('The CAPTCHA code is incorrect. Please try again.');
        }
    }
}
